# Core2 — Agent Instructions

## What this is
PHP fullstack framework for business apps. Modular MVC with CLI, Gearman workers, REST/SOAP APIs.
Version: 2.9.1 | PHP >= 8.2 | MySQL/PostgreSQL

## Developer Commands

```bash
composer install                          # Install deps (classmap-authoritative)
php cli.php --module cron --action run    # Run CLI task
php cli.php --module cron --action runJob --param 123
php worker.php -d -c conf.ini -s site.com # Start Gearman worker daemon
php worker.php -H                         # Worker help

# Static analysis
vendor/bin/phpstan analyse                # Level 6, scans inc/ and mod/admin/

# Tests
vendor/bin/phpunit                        # PHPUnit 9.5 (minimal coverage)
```

## Project Layout

| Path | What |
|------|------|
| `inc/classes/` | 58 core classes — Db, Cache, Acl, Config, Router, Init, Api, SSE |
| `inc/CoreController.php` | Admin panel controller (action_* methods) |
| `mod/<name>/` | Modules (admin, auth, billing, cron, webservice, oauth, etc.) |
| `mod/<name>/vX.Y.Z/` | Module version dirs (e.g. `mod/cron/v3.6.0/`) |
| `workers/` | Gearman workers (Eventer, Logger, Workhorse) |
| `html/{default,light,material}/` | UI themes |

## Architecture Facts

- **Entry point**: `Init::dispatch()` routes `module/action/params` → controller method `action_<action>()`
- **Routing**: `Router::routeParse()` parses URI. `/api/...` → API dispatch, else → module controller
- **Module naming**: Class `Mod<Name>Controller` for module `<name>`. Submodules use `module_sm_key` as resource ID
- **Magic getters**: `$this->db`, `$this->cache`, `$this->log`, `$this->translate`, `$this->modAdmin`, `$this->dataUsers`, `$this->apiProfile` — all resolved via `__get()` in `Common`/`Db` base classes
- **ACL**: Laminas Permissions ACL, role-based. Resources = modules + submodules. Types: `access`, `list_all`, `read_all`, `edit_all`, `delete_all`, `*_owner`, `*_default`. Cached per role
- **XAJAX**: POST requests from UI go through `post()` function in `Init.php` → `ModAjax.php` methods prefixed with `ax` (e.g. `axSave`, `axDelete`)
- **SSE**: `/sse` route handled by `Core2\SSE` class
- **Systemd**: `core2_worker.service` template for Gearman daemon (user=www-data, php8.2)

## Configuration

- **App config**: `conf.ini` next to `index.php`, section = `$_SERVER['SERVER_NAME']` or `production`
- **Core config**: `core2/conf.ini` (gearman, cache, auth, SSE defaults)
- **Extended config**: `conf.ext.ini` (optional, merged if present)
- **Module config**: `mod/<name>/conf.ini` — read via `$this->moduleConfig`

## Key Conventions

- Controller action methods: `public function action_<name>()`
- Translations: `$this->_("string")` or `$this->translate->tr("string", $module)`
- Models: `$this->data<Name>` auto-resolves to model classes (e.g. `$this->dataUsers`)
- API classes: `$this->api<Name>` auto-resolves (e.g. `$this->apiProfile`)
- Cross-module controllers: `$this->mod<Name>` (e.g. `$this->modAdmin`)
- File handler contexts: `fileid`, `thumbid`, `tfile`, `field_<name>`

## Gotchas

- `.gitignore` excludes `mod/*` and `html/*` — only `mod/admin` is tracked. Other modules/themes are external
- `vendor/` is gitignored — always run `composer install`
- `phpstan-baseline.neon` and `gen/` are gitignored
- Tests use `$GLOBALS['DB_NAME']`, `$GLOBALS['DB_USER']`, etc. for DB config — set before running
- Test bootstrap expects `core2/` subdirectory structure (DOC_ROOT points 2 levels up from `tests/`)
- Multiple DEPRECATED auth paths still present (`HTTP_CORE2M` header, `apikey` param)
- FIXME/TODO comments throughout — do not treat as implemented

## Security Notes

- Default admin password is MD5("admin") = `ad7123ebca969de21e49c12a7d69ce25` — change immediately
- `Tool::password_verify_secure()` used for password verification
- POST requests check `HTTP_REFERER` for same-host validation
