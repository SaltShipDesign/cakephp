# Upgrading a Saltship CakePHP 2 application to PHP 8.0–8.2

This guide assumes you're running an application built on `cakephp/cakephp` (the Saltship fork) at version 2.11.x on PHP 7.x, and you want to move to PHP 8.0/8.1/8.2 against version **2.12.0** of the framework.

## TL;DR

1. Bump framework dependency in your app's `composer.json` to `~2.12.0` (or `^2.12`).
2. Bump PHP requirement to `>=8.0,<8.3`.
3. Pin `phpunit/phpunit` to `^9.6.20` and `doctrine/instantiator` to `~2.0.0` in `require-dev`.
4. `composer update cakephp/cakephp phpunit/phpunit doctrine/instantiator`.
5. Run your test suite. Fix the small set of PHP 8 issues you'll find in your own app code (the categories are listed below).
6. Audit production logs after deploy for the `@`-operator change (see "Behavior changes").

## 1. Composer

```json
{
    "require": {
        "php": ">=8.0.0,<8.3.0",
        "cakephp/cakephp": "~2.12.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6.20",
        "doctrine/instantiator": "~2.0.0"
    }
}
```

Why pin `doctrine/instantiator`: the 2.1.0 release uses typed class constants (PHP 8.3 syntax). On 8.0–8.2 you need `~2.0.0` to avoid a parse error in the test boot.

## 2. Bootstrap & autoload

If your `app/Config/bootstrap.php` doesn't already load Composer's autoloader, add this near the top:

```php
if (file_exists(dirname(dirname(__DIR__)) . '/vendors/autoload.php')) {
    require dirname(dirname(__DIR__)) . '/vendors/autoload.php';
}
```

(The path depends on your `vendor-dir` setting. The framework's `composer.json` uses `vendors/`; the default Composer location is `vendor/`. Adjust accordingly.)

## 3. Behavior changes you'll see at runtime

These are intentional. Audit each one in your app:

### `@` operator now suppresses for real

PHP 8.0 changed the `@` operator. Before this framework version, *every* `@`-suppressed warning still hit `ErrorHandler::handleError` and got logged or displayed. From 2.12.0 the handler respects suppression.

**Impact**: any silent-by-design `@operation()` call now produces no log entry. If you were monitoring logs for these "shadow" warnings to detect issues, that signal is gone.

### `Security::encrypt` / `Security::decrypt` default to openssl

On any PHP install without the mcrypt extension (which is gone since 7.2 unless you built it manually), and without `Configure::write('Security.useOpenSsl', …)` being set, the framework now uses openssl. Previously these methods fatally referenced `MCRYPT_RIJNDAEL_128`.

**Impact**: code paths that previously fataled will start running. Existing ciphertexts written by the openssl branch remain readable; ciphertext written by mcrypt is readable only if mcrypt is installed.

If you want to keep mcrypt explicitly: `Configure::write('Security.useOpenSsl', false)` in your core config, and ensure ext-mcrypt is present.

### CacheHelper full-page caching with relative-time strings

`public $cacheAction = ['index' => '+1 day'];` and similar relative-time configs were silently *not* writing cache files on PHP 8 (string-to-int comparison change). They work again as of 2.12.0.

**Impact**: if your app relied on full-page caching being silently broken, expect cache files to start appearing under `app/tmp/cache/views/` again. If this is unintended, set `'cacheAction' => 0` to disable explicitly.

### PaginatorHelper "last" link separator

`$this->Paginator->numbers(true)` no longer emits a duplicate ` | ` separator before the "last" link. View templates that worked around this bug by stripping the duplicate from HTML output can drop that workaround.

## 4. Changes you'll likely need in your own app code

These are PHP 8 changes, not framework changes — every app upgrading from 7.x sees them.

### Removed functions

- `get_magic_quotes_gpc()`, `get_magic_quotes_runtime()`, `each()`, `create_function()`, `money_format()` — all removed in PHP 8.0. Grep your app for these and remove.
- `mcrypt_*` — removed in PHP 7.2; if you still call any of these directly, switch to `openssl_*` or `sodium_*`.

### Removed PHP 4-style constructors

- A method named the same as its class is no longer the constructor in PHP 8. Rename to `__construct`. Cake's own code is already clean; check your app classes.

### Strict comparisons & type juggling

- `"some string" == 0` now returns `false` (was `true` in PHP 7). Any `in_array('foo', [0, 1, …])`, `switch ($string) { case 0: … }`, or `if ($key > 0)` against non-numeric strings will flip behavior.
- Non-numeric strings used in arithmetic now throw `TypeError` instead of silently coercing to 0.

### `call_user_func_array` and named arguments

- `call_user_func_array($fn, $assocArray)` interprets the array's string keys as named arguments. Wrap with `array_values($assocArray)` if you mean positional.

### `ReflectionMethod` with null

- `new ReflectionMethod($obj, null)` throws `ValueError` (not `ReflectionException`). Guard against null method names.

### `trigger_error()` signature

- Two args only: `(message, level)`. If you have legacy code passing 3+ args (a common copy-paste bug from sprintf-like patterns), fix.

### Dynamic properties (PHP 8.2)

- Writing to an undeclared property triggers a `Deprecated` notice in 8.2 (will be an error in 9.0). Framework classes are covered via `#[\AllowDynamicProperties]` on `CakeObject`. **For your own classes** that don't extend `CakeObject` and don't define `__set`, either:
  - declare the properties up front, or
  - add `#[\AllowDynamicProperties]` to the class (one-line attribute, no-op on < 8.2).

### `foreach (… as &$x)`

- Always `unset($x)` after the loop. Reusing the variable with `&$x` still bound is a long-standing footgun that PHP 8 surfaces more often.

### `null` to non-nullable internal-function args (8.1)

- `strlen(null)`, `str_replace($s, null, $x)`, `parse_str($s)` etc. emit deprecation warnings on 8.1 and are errors in 9.0. Cast or guard.

### `is_resource()` on what used to be resources

- `curl_init()` returns `CurlHandle` (object), `imagecreate*()` returns `GdImage`, etc. Code using `is_resource($handle)` should be reviewed.

## 5. Test suite

The framework's test infrastructure has been migrated to PHPUnit 9.6 with polyfills for the methods PHPUnit 9 removed. If your own app tests extend `CakeTestCase`, you'll inherit:

- `assertContains($needle, $stringHaystack)` dispatches to `assertStringContainsString` automatically.
- `setExpectedException()`, `assertAttributeEquals()`, `assertAttributeCount()`, `attributeEqualTo()` are polyfilled.
- `@expectedException`, `@expectedExceptionMessage`, `@expectedExceptionMessageRegExp`, `@expectedExceptionCode` docblock annotations still work — `CakeTestCase::setUp()` parses them.
- `setUp(): void`, `tearDown(): void`, `setUpBeforeClass(): void`, `tearDownAfterClass(): void` are required (PHPUnit 9). Add the `: void` return type to every override in your app tests.
- `$_SERVER`, `$_POST`, `$_GET`, `$_FILES`, `$_COOKIE` are snapshotted in setUp and restored in tearDown — your tests can mutate them freely without leaking.

Items NOT polyfilled (deprecation warnings — fix when convenient):

- `assertRegExp` → `assertMatchesRegularExpression`
- `assertNotRegExp` → `assertDoesNotMatchRegularExpression`
- `$this->at(N)` matcher — restructure to `expects($this->exactly(N))->withConsecutive(…)` or `expects($this->any())` + tracked state.

## 6. Database test config

The runner reads `app/Config/database.php`. For local PHP 8 validation against SQLite in-memory:

```php
<?php
class DATABASE_CONFIG {
    public $test = [
        'datasource' => 'Database/Sqlite',
        'persistent' => false,
        'database' => ':memory:',
        'prefix' => '',
    ];
    public $default = array(
        'datasource' => 'Database/Sqlite',
        'persistent' => false,
        'database' => ':memory:',
        'prefix' => '',
    );
}
```

(The file is gitignored, so this stays local.)

## 7. Validation checklist

- [ ] `composer update` completes without errors.
- [ ] Your test suite runs and is green (or no worse than before the upgrade).
- [ ] Production logs after first deploy: no new patterns of errors, especially nothing from `Configure::write('Error.handler', …)`.
- [ ] Any code path using `@`-suppression: behaves as expected (truly silent).
- [ ] Any code calling `Security::encrypt`/`decrypt`: still decrypts existing data correctly.

## See also

- `docs/cakephp2-on-php82.md` — a one-page reference you can drop into your app repo for anyone (including AI coding assistants) who works in that codebase.
