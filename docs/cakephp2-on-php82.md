# Working in a CakePHP 2 app on PHP 8.0–8.2

This is a quick reference for anyone — human or AI coding assistant — touching a Saltship-fork CakePHP 2 codebase running on PHP 8.0–8.2 (framework version 2.12.0+). Drop this file in your app's `docs/` directory.

## The framework

- **`cakephp/cakephp` Saltship fork, version `2.12.0+`** — keeps the 2.x API and codebase but is patched for PHP 8.0/8.1/8.2 compatibility. *Not* CakePHP 3/4/5. The 2.x architecture (PSR-0-ish `App::uses`, no namespaces in app code, `lib/Cake/` checked into the repo via Composer) is the world you're in.
- PHP requirement: `>=8.0,<8.3`. Composer.json should pin `phpunit/phpunit: ^9.6.20` and `doctrine/instantiator: ~2.0.0` in `require-dev` (2.1.0 uses PHP 8.3-only typed-constant syntax).
- The 2.x branch is upstream-EOL; only this fork is maintained. Upstream issues / Stack Overflow answers may reference behaviors that diverge here.

## Project layout reminders

- `app/` is the application skeleton. `app/Config/core.php` is the runtime config (debug level, salt, error handler, etc.). `app/Config/bootstrap.php` is where you wire Composer's autoloader and load plugins.
- `lib/Cake/` ships inside the repo (because cake 2.x predates Composer-installed framework cores). Don't edit it as part of app work — go to the framework repo.
- `app/tmp/cache/` is the write target for the file cache and view cache. Each subdirectory needs an `empty` placeholder for the framework's bake to recognize the project layout.

## PHP 8 things you'll bump into

### `@` is honest now

PHP 8.0 changed the `@` operator's interaction with error handlers. The framework's `ErrorHandler::handleError` now respects it correctly. **A `@`-suppressed warning produces no log entry.** Audit before relying on `@` for "silent best-effort"; explicit try/catch is clearer.

### String-to-int comparison flipped

- `"3 minutes" > 0` is `false` in PHP 8 (was `true` in PHP 7). The framework's `CacheHelper::cache()` and `PaginatorHelper::numbers()` both had this bug fixed at the framework level (2.12.0). **In your own app code**, look for `if ($string > 0)`, `in_array('x', [0, 1])`, `switch ($string) { case 0: }` — same trap.

### `call_user_func_array` + associative array = named arguments

PHP 8 reads string keys as named-parameter names. If the target function doesn't have a parameter with that name, it errors. Wrap with `array_values()` when you mean "positional".

### Removed functions

`get_magic_quotes_gpc`, `get_magic_quotes_runtime`, `each`, `create_function`, `money_format`, `mcrypt_*` — all gone. Cake's framework code is clean. Your app may still reference them. Grep before deploying.

### Dynamic property writes (PHP 8.2)

Writing to an undeclared property emits `E_DEPRECATED` in 8.2 (will be an error in 9.0). Framework base classes — `Controller`, `Model`, `View`, `Helper`, `Component`, `Shell` — are covered: they all extend `CakeObject` which carries `#[\AllowDynamicProperties]`. **In your own classes** that don't extend `CakeObject`, add the attribute or declare your properties.

### `is_executable()` quirk on Docker-Mac volumes

If you run tests inside Docker Desktop on macOS, bind-mounted files all report as executable. The framework's `FileTest::testExecutable` already detects and skips this; just know it's an environment quirk, not a real bug.

### Locale dependencies

`setlocale(LC_ALL, 'es_ES')` returns `false` if the locale isn't installed in the container. Generate it (`locale-gen` on debian) or skip the test.

## Framework features that are *not* obvious

### Autoload: `App::uses`, not PSR-4

Cake 2 has its own autoloader (`spl_autoload_register([App::class, 'load'])`). To use a class:

```php
App::uses('MyHelper', 'View/Helper');
```

The second arg is the *package*, not a namespace prefix. The class file lives at `App/View/Helper/MyHelper.php`. Composer's autoloader can coexist — `app/Config/bootstrap.php` should `require vendors/autoload.php` early.

### Configure leakage across tests

`Configure::write('debug', 0)` followed by a test that throws will leak `debug=0` into subsequent tests' setUp snapshots. The framework now snapshots the pristine `debug` value once per process and force-resets it. If you have *other* keys (e.g. `Security.salt`, your own feature flags) that misbehaving tests leak, replicate the pattern in your own test base class.

### Polyfilled PHPUnit assertions (when extending `CakeTestCase`)

- `assertContains($needle, $strHaystack)` — auto-dispatches to `assertStringContainsString`.
- `assertAttributeEquals`, `assertAttributeCount`, `attributeEqualTo` — read protected/private props via reflection.
- `setExpectedException($class, $msg = null, $code = null)`.
- `@expectedException`, `@expectedExceptionMessage`, `@expectedExceptionMessageRegExp`, `@expectedExceptionCode` docblock annotations.

### Polyfilled legacy PHPUnit class names

`PHPUnit_Framework_TestSuite`, `PHPUnit_Framework_Error`, etc. are `class_alias`'d to their modern namespaced equivalents at test-runner startup. You don't need to mass-rename in test files — but new tests should use the namespaced names directly.

### `$_SERVER` / `$_POST` etc. snapshot

`CakeTestCase::setUp` snapshots `$_SERVER`, `$_POST`, `$_GET`, `$_FILES`, `$_COOKIE` and restores in `tearDown`. Your tests can mutate them without cross-test leakage.

### Test runner invocation

- One test class: `./lib/Cake/Console/cake test app YourController` (from app dir).
- All framework tests: `./lib/Cake/Console/cake test core AllTests --stderr`.
- All app tests: `./lib/Cake/Console/cake test app AllTests --stderr`.
- The CLI runs through `lib/Cake/Console/Command/TestShell.php`. PHPUnit args (`--filter`, etc.) are forwarded.

## When something goes wrong

### Test mass-failures on PHPUnit upgrade

Most likely a polyfill is missing. Check `lib/Cake/TestSuite/CakeTestCase.php` for the polyfill list. If your test uses an assertion that's not there, either add it locally to a project-level base class or upstream a fix.

### "Class X already declared" during a test run

Class loading is per-process and irreversible. If a test depends on a class *not* being loaded yet, that test will fail when run after other tests load the class. Either skip the preflight assertion with a guard (`if (!class_exists(X, false))`), or run the offending test in process isolation.

### "Cannot open file Test/bootstrap.php" or class-name resolution errors

The CakePHP test runner had several PHPUnit 9 incompatibilities. Make sure you're on framework 2.12.0+, which fixes `TestShell::_run()` arg construction and `CakeTestRunner::getLoader()`.

### Cache silently not working

Pre-2.12 with PHP 8: relative-time `cacheAction` strings silently failed to write. Fixed in 2.12.0. If a 2.12+ app still doesn't cache, check `Cache.check`, `Cache.disable`, and that `app/tmp/cache/views/` is writable.

### `Security::encrypt` returns suspect data

Did the production server flip backends? `Security.useOpenSsl` defaults to "openssl if mcrypt missing." If a server with mcrypt installed previously used mcrypt and a new server uses openssl, ciphertexts cross-incompatible. Set the flag explicitly to lock the choice.

## Quick links

- Framework upgrade notes: `<framework-repo>/UPGRADE-8.2.md`
- Framework repo: https://github.com/SaltShipDesign/cakephp
- Framework version: `lib/Cake/VERSION.txt`
