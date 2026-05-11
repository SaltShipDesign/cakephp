# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository purpose

This is the **Saltship fork of CakePHP 2.10.x** (currently version 2.11.7, see `lib/Cake/VERSION.txt`). It is published under `cakephp/cakephp` in `composer.json` and consumed by Saltship apps via the `SaltShipDesign/cakephp` repository. Upstream CakePHP 2.x is unmaintained; this fork exists to keep it usable on newer PHP runtimes and PHPUnit 9.

When changing behavior, remember that **this is a library**: downstream apps depend on it and will see breakages on `composer update`. Bump `lib/Cake/VERSION.txt` and tag releases for any user-visible change. The default branch for work is `develop`; PRs are merged into `master`.

### PHP version constraint — read before touching

`composer.json` pins `php: ">=7.3.0,<8.0.0"` even though the README states the goal is "support for PHP 8". The upper bound is intentional for the current line — do not relax it casually. Recent commits (`Php minimum version downgrade to 7.3`, `Remove the compatibility wrappers for expectException`) indicate active normalization of the supported range; check `git log` before changing the constraint or any `version_compare` / polyfill shims.

## Common commands

All commands run from the repo root.

```bash
# Run the full core test suite (uses the CakePHP test runner, not phpunit directly)
./lib/Cake/Console/cake test core AllTests --stderr --verbose

# Run a single test group — substitute any AllXxxTest.php file under lib/Cake/Test/Case/
./lib/Cake/Console/cake test core Model       # AllModelTest
./lib/Cake/Console/cake test core Controller  # AllControllerTest

# Run one test file by class name
./lib/Cake/Console/cake test core BasicsTest

# Coding standards (CakePHP sniffer)
./vendors/bin/phpcs -p --extensions=php --standard=CakePHP ./lib/Cake

# Composer shortcuts (defined in composer.json scripts)
composer test       # alias for the cake test command above
composer cs-check   # alias for the phpcs command above
composer check      # cs-check + test
```

The composer vendor dir is `vendors/` (plural, lowercase) — not `vendor/`. `./vendors/bin/phpcs` will not exist until you run `composer install`.

### Test infrastructure caveats

- The runner is **CakePHP's `cake test` shell**, which wraps PHPUnit. It loads `app/Config/database.php` (a real file you must create from `database.php.default` for DB-backed tests) and bootstraps the framework before invoking PHPUnit. Calling `phpunit` directly will not work for most suites.
- Test groups are aggregated by hand: each `lib/Cake/Test/Case/All*Test.php` is a `PHPUnit_Framework_TestSuite` that explicitly adds files. When you add a new test case, register it in the matching `All*Test.php` or it won't run in CI.
- PHPUnit was upgraded to 9.6 (commit `cc5bd51`) and the `expectException` compatibility shims were removed (commit `268d686`). Write tests against the PHPUnit 9 API directly; do not reintroduce shims for older PHPUnit.

## Codebase layout

Two top-level trees that look similar but serve different roles:

- **`lib/Cake/`** — the framework itself. This is what gets packaged and shipped to downstream apps. Edits here are library changes.
- **`app/`** — a **skeleton application** bundled with the framework so the repo is runnable standalone and so the test suite has an app context to bootstrap into. Edits here usually only matter for tests or local dev; do not put framework logic in `app/`.

Inside `lib/Cake/`, the directories map to CakePHP 2.x's classic MVC layering: `Controller/`, `Model/`, `View/`, `Routing/`, `Network/` (request/response, HTTP, email, sessions), `Console/` (shell/CLI), `TestSuite/` (test framework integration), `Cache/`, `Log/`, `I18n/`, `Event/`, `Utility/`, `Error/`, `Configure/`, `Core/`.

The `cake` binary is `lib/Cake/Console/cake` (bash wrapper) → `cake.php` → `ShellDispatcher`. The composer `bin` entry points at the bash wrapper.

## Conventions specific to this codebase

- **CakePHP 2.x coding standards** — enforced by `cakephp/cakephp-codesniffer ^1.0.0`. Tabs for indentation (see `.editorconfig`), PHP 5-style array syntax in older code, class-per-file with PascalCase filenames matching the class. Run `composer cs-check` before committing changes under `lib/Cake/`.
- **No `use` statements for framework classes** — CakePHP 2.x uses `App::uses('ClassName', 'Package')` for autoloading. Match the surrounding style when adding new files.
- **Commit message style** — short, imperative, often a single line (see `git log --oneline`). PRs are squash-merged into `master`.
