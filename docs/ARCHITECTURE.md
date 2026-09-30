# CodeQuest — Architecture and Development Guide

## What this is

A LAN-deployable coding assessment system. It mirrors a three-tier model —
**Aptitude → Program Writing → Debugging** — with case-study driven questions,
timed assessments, automated scoring, anti-cheat controls, and CSV/JSON/PDF
data portability.

## Design constraint: no dependencies

The application ships with **no Composer, no npm, and no runtime internet
requirement**. That is deliberate: the target is a LAN install where the
machine may be offline. Consequences worth knowing before you change anything:

- The only bundled library is `vendor/fpdf/fpdf.php` (PDF export), vendored
  directly into the tree.
- There is no `composer.json`, and adding one would not be used by the deploy
  path.
- Tests therefore use a small self-contained runner rather than PHPUnit.

## Layout

```
config.php          constants + lazy db() PDO factory
schema.sql          full MySQL/MariaDB schema (11 tables)
seed.php            sample data
install.php         browser-driven installer
includes/helpers.php  escaping, CSRF, flash, auth guards, settings, JSON
includes/layout.php   shared HTML chrome
index.php           candidate login / landing
register.php        candidate registration
quiz.php            timed assessment runner
result.php          score breakdown
beacon.php          anti-cheat beacon (focus / visibility / single-tab)
auto_submit.php     auto-submit on timeout
admin/              admin console (exams, questions, case studies, reports,
                    import/export, settings, candidates)
assets/             CSS + anti-cheat JS
tests/run.php       test runner
```

`db()` is a lazy static PDO factory, so including `config.php` or
`includes/helpers.php` does **not** open a database connection. That is what
makes the test suite runnable without MySQL.

## Security model

- Every mutating request goes through `require_csrf()`; tokens are 32 random
  bytes, hex-encoded, and compared with `hash_equals()`.
- Tokens are accepted from `$_POST['csrf_token']` or the `X-CSRF-Token` header
  (the latter is what `beacon.php` uses).
- Output escaping is centralised in `e()` (`htmlspecialchars` with
  `ENT_QUOTES`, UTF-8). Use it on every interpolated value.
- `require_admin()` and `require_candidate()` are the access guards. Call one
  at the top of any page that needs it.
- `DEFAULT_ADMIN_USER` / `DEFAULT_ADMIN_PASS` in `config.php` exist for first
  install. Change them.

## Tests

```bash
php tests/run.php
```

31 assertions covering `e()`, `sanitize_input()`, the CSRF token lifecycle
(`csrf_token`, `csrf_field`, `verify_csrf` including the header path, wrong,
missing and truncated tokens), flash message set/read/consume and escaping,
and the admin/candidate session predicates. No database, no network.

The exit code is 0 on success and 1 on any failure, so CI can gate on it.

## Deployment

1. Copy the tree into the web root.
2. Point a MySQL 5.7+ / MariaDB 10.3+ database at it.
3. Open `install.php` once to create the schema and the admin account.
4. Delete or lock `install.php` afterwards.
5. `.htaccess` handles SPA-style routing and denies direct access to
   `config.php` and `schema.sql` on Apache.

The test suite and `php -l` over every PHP file both run in CI, so a syntax
error or a helper regression fails the build.
