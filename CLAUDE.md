# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

php-snmptraps: `snmptrapd` traphandle script that parses SNMP traps, stores them in MariaDB and sends notifications, plus a web UI to browse traps and manage severities / ignore rules / maintenance / users. Rewritten in v1.0.0 (namespaced classes, front controller, `public/` docroot). No framework, no Composer, no build step.

## Deployment (production)

- Server: `root@10.12.50.51` (FreeBSD 14, **nginx** + php-fpm, **PHP 8.3**, MariaDB 10.11). App at `/usr/local/www/snmptraps`, UI at `http://10.12.50.51/snmptraps/`.
- The local dir is synced to `/usr/local/www/snmptraps` (Sublime SFTP config in the parent dir: `../sftp-config.json`, remote_path `/usr/local/www/`). There is **no local git** - the git repo (origin `phpipam/php-snmptraps`, working branch `rewrite-1.0`) lives on the server in `/usr/local/www/snmptraps`; commit there after uploading (`.git` is not web-reachable). Upload manually, e.g.
  `tar czf - --exclude config.php --exclude .DS_Store src views public bin vendor db *.md config.dist.php | ssh root@10.12.50.51 'tar xzf - -C /usr/local/www/snmptraps'`
- nginx (`/usr/local/etc/nginx/nginx.conf`) exposes only `public/`: `^~ /snmptraps/css|js/` are aliased, everything else under `/snmptraps/` goes to `public/index.php`. `.htaccess` is irrelevant. Other apps (cacti, ranip, sap, cp-radius) share that nginx server block - don't touch them.
- snmptrapd (`/usr/local/share/snmp/snmptrapd.conf`, runs as user `snmpd`): `traphandle default /usr/local/bin/php /usr/local/www/snmptraps/bin/traphandler.php`. Reload config with `kill -HUP $(cat /var/run/snmptrapd.pid)`.
- Pre-1.0 backup (old code incl. `.git`, nginx/snmptrapd configs, small-table DB dump): `/root/snmptraps-backup-20261124/` on the server.
- Receiver errors go to `$filename` (`/tmp/traps.txt`, owned by snmpd).
- Cron on the server purges old traps directly via SQL (table `traps` only).
- `config.php` is not versioned/uploaded; its legacy global-variable format is read by `src/Config.php` (repairs `$url` "http:/" typo, auto-detects the MIB dir `/usr/local/share/snmp/mibs/`).

## Commands

```bash
# lint (local PHP is 8.5; code must also run on the server's 8.3 -> no 8.4+ only syntax/functions
# like property hooks, array_find, new-without-parens chaining)
find src bin public views -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax'

# replay a trap through the full pipeline (writes to the DB of config.php, may notify!)
printf 'host\nUDP: [10.0.0.1]:1->[10.0.0.2]:162\nX::up 1\nSNMPv2-MIB::snmpTrapOID.0 IF-MIB::linkDown\nIF-MIB::ifName.3 ge-0/0/1\n' | php bin/traphandler.php
```

There is no test suite in the repo. When changing the parser, compare old vs new output on real `traps.raw` rows from the DB (content must stay byte-identical, see below).

## Architecture

- `src/bootstrap.php` - constants (`APP_ROOT`, `APP_VERSION`, `APP_MIN_PHP`), PSR-4-ish autoloader (`Snmptraps\` -> `src/`, `PHPMailer\PHPMailer\` -> `vendor/PHPMailer/src/`), loads `Config`. Shared by both entry points.
- **Receiver**: `bin/traphandler.php` -> `Trap\TrapProcessor`: `Trap\TrapParser` (stdin lines -> `Trap\Trap`), exception check, insert into `traps`, `Notify\Notifier`.
- **Web**: `public/index.php` -> `Web\App::run()`: parses the path below `Config::$base`, starts the session, enforces CSRF on every POST (field `csrf_token` or header `X-CSRF-Token`), requires login, dispatches to `Web\Controller\Pages` (full pages), `Ajax` (`/ajax/{action}/` fragments/JSON for modals, bootstrap-table, live view) or `Settings` (admin). Controllers return HTML; `App::layout()` wraps pages in `views/layout.php`.
- `Web\View` renders `views/*.php` templates; `Web\TrapPresenter` renders trap rows/cells. All output is escaped with `e()` / `e_nl()` (`src/helpers.php`). `HttpException` messages are rendered as HTML - only pass static strings.
- `TrapRepository` - all trap reads/writes. It is constructed with the user's permitted hosts (`Auth::permittedHosts()`, `null` = all) and applies that restriction to every query - always go through `$app->traps()`.
- `Auth` - session, login (`local` = `password_verify`, legacy crypt hashes auto-rehashed; `ad` = `Ldap` bind), roles `user` < `operator` (define/ignore/delete traps) < `administrator` (settings, users).
- `Settings::tables()` - explicit field definitions drive the generic add/edit/delete modal and validation for `severity_definitions`, `exceptions`, `maintenance`, `users`.
- `Notify\*` - `Channel` implementations (`mail` PHPMailer, `sms` HTTP GET gateway, `pushover`, `slack`) registered in `Notifier::CHANNELS`; HTTP via `HttpClient` (PHP streams - the server has **no curl extension**).
- `Mib` - reads NOTIFICATION-TYPE/TRAP-TYPE from MIB files (basenames inside the MIB dir only), module->file index cached in the system temp dir.

## Invariants - don't break these

- **Stored trap format**: `traps.content` is `"name => value"` lines produced by `TrapParser` exactly as the pre-1.0 parser did (only varbinds of the trap's own MIB module if any, instance index stripped, module prefix stripped). Exception rules (`exceptions.content`, substring match) and message-based severity definitions depend on it. Same for the generated `message`.
- **Severity detection**: content "Severity" varbind first, then the first `severity_definitions` row (by id) whose `oid` is a substring of the trap OID; `content` of definitions is only used when re-classifying stored traps. Changing this changes who gets paged.
- **Exceptions**: hostname equal or `all`, OID substring, empty content matches all.
- Notification recipients: severity in `notification_severities`, host permitted, outside quiet hours (`start == stop` = none), host not in an active `maintenance` window.
- Message URLs use base64url (`b64url_encode`); `/message/` also accepts legacy standard base64 links (may contain `/`) from old e-mails.

## Database

MariaDB in STRICT mode - truncate to column length before insert (see `TrapProcessor::MAX_LEN`). `db/SCHEMA.sql` is the full current schema (fresh installs); every schema change also needs a `db/migrations/<version>.sql` and an entry in `db/UPDATE.SQL`. Keep `SCHEMA.sql` updated whenever the schema changes.

## UI

Tabler 1.7.5 (dark, Bootstrap 5 bundled in `public/js/tabler.min.js` and exposed as `tabler.bootstrap`, not `window.bootstrap`) + Tabler Icons 3.52 webfont (split per-category font files in `public/css/tabler/fonts/`) + jQuery 3.7 + tippy.js/Popper, all vendored in `public/` (no CDN), styled to match the sibling project `pw.ugbb.net/nalarms-dev.ugbb.net`. To upgrade, copy `dist/css/tabler.min.css`, `dist/js/tabler.min.js` (@tabler/core) and `dist/tabler-icons.min.css` + the woff2 files it references (@tabler/icons-webfont) over the vendored files. Asset URLs carry `App::assetVersion()` (file mtimes), so replaced files bypass the 7-day nginx cache.

- `views/layout.php`: navbar, reload timer (`users.reload_page`, paused via cookie `stop-refresh`), single `#modal1`.
- Trap tables are one line per trap (`table-layout: fixed`, cells clipped with an ellipsis in `public/css/app.css`); the full content is in the message tooltip / quick view. Dashboard widgets show at most `Auth::DASH_MAX_ROWS` (20) traps.
- Settings: left menu + one card whose header holds the page title. Users have a list (`partials/user-list.php`, two-line rows) and a details page `settings/users/{id}/` (`partials/user-details.php`, helpers in `Web\UserPresenter`). `.load-modal[data-redirect]` sends the browser there after a successful modal submit instead of reloading.
- Trap lists are server-side paginated (`Pages::listing()`: `?page`, `?limit`, `?q`), rendered by `views/list.php` + `partials/trap-table.php` + `partials/pagination.php`; row HTML comes from `Web\TrapPresenter` (Tabler colour per severity group via `Severity::tablerColor()`).
- `public/js/app.js`: `.load-modal` links load their href into `#modal1`; `.modal-execute[data-action]` posts `#modal-form` and reloads unless the response contains `alert-danger`/`alert-warning`; live view polls `ajax/live/`.
- Tooltips: `data-toggle="tooltip"` + `tippy-title` (tippy `allowHTML`) - the attribute holds HTML, so trap-derived text is escaped twice (`TrapPresenter::tip()`). Call `initTooltips(el)` after inserting HTML.
