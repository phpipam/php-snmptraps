# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

php-snmptraps: `snmptrapd` traphandle script that parses SNMP traps, stores them in MariaDB and sends notifications, plus a web UI to browse traps and manage severities / ignore rules / maintenance / users. Rewritten in v1.0.0 (namespaced classes, front controller, `public/` docroot). No framework, no Composer, no build step.

## Deployment (production)

- Server: `root@10.12.50.51` (FreeBSD 14, **nginx** + php-fpm, **PHP 8.3**, MariaDB 10.11). App at `/usr/local/www/snmptraps`, UI at `http://10.12.50.51/snmptraps/`.
- The local dir is synced to `/usr/local/www/snmptraps` (Sublime SFTP config in the parent dir: `../sftp-config.json`, remote_path `/usr/local/www/`). There is **no local git** - the git repo (origin `phpipam/php-snmptraps`, branch `master`) lives on the server in `/usr/local/www/snmptraps`; commit there after uploading (`.git` is not web-reachable). Pushing to GitHub is done by the user - don't push unless asked.
- Upload, verify, commit:
  ```bash
  COPYFILE_DISABLE=1 tar czf - --no-mac-metadata --exclude config.php --exclude .DS_Store src views public bin vendor db docs *.md config.dist.php \
    | ssh root@10.12.50.51 'tar xzf - -C /usr/local/www/snmptraps 2>/dev/null; chmod 755 /usr/local/www/snmptraps/bin/*.php'
  # verify: md5 of local vs server files must match (macOS tar prints harmless xattr warnings, exit code is not reliable)
  # commit on the server: check `git status` first and add explicit paths - `git add -A` also picks up untracked files
  ```
- The server working tree **is the live app**: never `git checkout`/`switch` to another commit or branch there (it swaps the running code). To move a branch use `git fetch . a:b` / `git branch -f`.
- `.git/info/exclude` on the server lists the user's own screenshots in `docs/screenshots/` (`details.png`, `maintaneance.png`, `mib.png`, `severity.png`) - untracked on purpose, not decided yet whether they go into the README (they show a real name and the server address).
- Changes to the receiver (`bin/traphandler.php`, `src/Trap/*`, `Notify/*`, `Schema`) run for every trap immediately: test them locally first (pipe a trap into `php bin/traphandler.php` against a config with `$notification_methods = array();`), then upload and watch `$filename` - a broken receiver loses traps (snmptrapd does not retry).
- nginx (`/usr/local/etc/nginx/nginx.conf`) exposes only `public/`: `^~ /snmptraps/css|js/` are aliased, everything else under `/snmptraps/` goes to `public/index.php`. `.htaccess` is irrelevant. Other apps (cacti, ranip, sap, cp-radius) share that nginx server block - don't touch them.
- snmptrapd (`/usr/local/share/snmp/snmptrapd.conf`, runs as user `snmpd`): `traphandle default /usr/local/bin/php /usr/local/www/snmptraps/bin/traphandler.php`. Reload config with `kill -HUP $(cat /var/run/snmptrapd.pid)`.
- Backups on the server in `/root/snmptraps-backup-20261124/`: pre-1.0 code (incl. old `.git`), nginx/snmptrapd configs, small-table DB dump, and code / `config.php` copies taken before later deploys (`pre-101-*`, `pre-103/`, `config.php.before-*`).
- Receiver errors go to `$filename` (`/tmp/traps.txt`, owned by snmpd).
- Cron on the server purges old traps directly via SQL (table `traps` only).
- `config.php` is not versioned/uploaded; its legacy global-variable format is read by `src/Config.php` (repairs `$url` "http:/" typo, auto-detects the MIB dir `/usr/local/share/snmp/mibs/`). Options added in 1.0.x (missing = default): `$notification_dedup_minutes` (15), `$repeat_window_minutes` (10) - both in `config.dist.php` - and `$screenshot_mode` (false; only in the server's `config.php`, intentionally not in `config.dist.php`). New options go into `config.dist.php` and the server's `config.php` (back it up first). php-fpm opcache revalidates every 2 s, so config edits apply without a restart.
- nginx also denies `^~ /db_bkp/` and `^~ /ranip/bkp/` (database dumps of other apps were downloadable).

## Commands

```bash
# lint (local PHP is 8.5; code must also run on the server's 8.3 -> no 8.4+ only syntax/functions
# like property hooks, array_find, new-without-parens chaining)
find src bin public views -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax'

# replay a trap through the full pipeline (writes to the DB of config.php, may notify!)
printf 'host\nUDP: [10.0.0.1]:1->[10.0.0.2]:162\nX::up 1\nSNMPv2-MIB::snmpTrapOID.0 IF-MIB::linkDown\nIF-MIB::ifName.3 ge-0/0/1\n' | php bin/traphandler.php

# command line scripts (all in bin/, CLI only)
php bin/migrate.php --check             # DB migration status, exit 1 if pending (--yes apply, --install fresh DB)
php bin/test-mail.php <email> [trap-id] # send the notification e-mail of a stored trap (--html [id] = print only)
php bin/rebuild-repeats.php             # recompute fingerprint / repeats / superseded of all traps (~20 s)
```

Testing the web UI without a browser login: run the app locally with PHP's built-in server against the production DB through an SSH tunnel (`ssh -f -N -L 13306:127.0.0.1:3306 root@10.12.50.51`, a local copy of `config.php` with `$db['port'] = 13306` and notifications disabled, a router script that sets a fixed session cookie for an existing session file), then use headless Chrome (`--screenshot`, `--dump-dom`) for screenshots and JS checks. For quick checks on the server itself, create a temporary session file in `/tmp/sess_<id>` (`trapusername|s:5:"Admin";csrf_token|s:64:"...";`, owner `www`), curl with that cookie, and delete it afterwards. Any test trap / maintenance entry written to the production DB must use an obvious test hostname and be deleted afterwards.

There is no test suite in the repo. When changing the parser, compare old vs new output on real `traps.raw` rows from the DB (content must stay byte-identical, see below).

## Architecture

- `src/bootstrap.php` - constants (`APP_ROOT`, `APP_VERSION`, `APP_MIN_PHP`), PSR-4-ish autoloader (`Snmptraps\` -> `src/`, `PHPMailer\PHPMailer\` -> `vendor/PHPMailer/src/`), loads `Config`. Shared by both entry points.
- **Receiver**: `bin/traphandler.php` -> `Trap\TrapProcessor`: `Trap\TrapParser` (stdin lines -> `Trap\Trap`), exception check, repeat check (`Trap\Repeats`), insert into `traps`, `Notify\Notifier` (skipped for repeats within `$notification_dedup_minutes`).
- **Web**: `public/index.php` -> `Web\App::run()`: parses the path below `Config::$base`, starts the session, enforces CSRF on every POST (field `csrf_token` or header `X-CSRF-Token`), requires login, dispatches to `Web\Controller\Pages` (full pages), `Ajax` (`/ajax/{action}/` HTML fragments for modals and the live view) or `Settings` (admin). Controllers return HTML; `App::layout()` wraps pages in `views/layout.php`.
- `Web\View` renders `views/*.php` templates; `Web\TrapPresenter` renders trap rows/cells. All output is escaped with `e()` / `e_nl()` (`src/helpers.php`). `HttpException` messages are rendered as HTML - only pass static strings.
- `TrapRepository` - all trap reads/writes. It is constructed with the user's permitted hosts (`Auth::permittedHosts()`, `null` = all) and applies that restriction to every query - always go through `$app->traps()`.
- `Auth` - session, login (`local` = `password_verify`, legacy crypt hashes auto-rehashed; `ad` = `Ldap` bind), roles `user` < `operator` (define/ignore/delete traps) < `administrator` (settings, users).
- `Settings::tables()` - explicit field definitions drive the generic add/edit/delete modal and validation for `severity_definitions`, `exceptions`, `maintenance`, `users`.
- Notification e-mail (`MailChannel::html()`): the card always uses the UI colours as hex; the page background is transparent, `#111827` only in dark mode (`prefers-color-scheme` + Outlook.com `[data-ogsb]`). Colours are hex (`MailChannel::UI`, `Severity::HEX` - e-mail clients don't support oklch). Preview / test: `php bin/test-mail.php --html [trap-id]` or `php bin/test-mail.php <email> [trap-id]`.
- `Notify\*` - `Channel` implementations (`mail` PHPMailer, `sms` HTTP GET gateway, `pushover`, `slack`) registered in `Notifier::CHANNELS`; HTTP via `HttpClient` (PHP streams - the server has **no curl extension**).
- `Mib` - reads NOTIFICATION-TYPE/TRAP-TYPE from MIB files (basenames inside the MIB dir only), module->file index cached in the system temp dir.
- `Schema` - detects missing migrations from the live schema (cached 1 min per OS user in the system temp dir); used by the admin warning banner, `bin/migrate.php`, the receiver and `TrapRepository` (features that need new columns stay off until the migration is applied).
- `Trap\Repeats` - fingerprint and repeat-chain rules, shared by the receiver and `bin/rebuild-repeats.php` (see Invariants).
- `Severity::color()` / `Severity::HEX` - UI colours as hex, used by the e-mail and Slack.
- `docs/screenshots/` - README screenshots (1440x900, taken locally with `$screenshot_mode = true` and the production title). Check them for leaked hostnames / names before committing; form field values are never masked, so don't screenshot filled-in edit forms.

## Invariants - don't break these

- **Stored trap format**: `traps.content` is `"name => value"` lines produced by `TrapParser` exactly as the pre-1.0 parser did (only varbinds of the trap's own MIB module if any, instance index stripped, module prefix stripped). Exception rules (`exceptions.content`, substring match) and message-based severity definitions depend on it. Same for the generated `message`.
- **Severity detection**: content "Severity" varbind first, then the first `severity_definitions` row (by id) whose `oid` is a substring of the trap OID; `content` of definitions is only used when re-classifying stored traps. Changing this changes who gets paged.
- **Exceptions**: hostname equal or `all`, OID substring, empty content matches all.
- Notification recipients: severity in `notification_severities`, host permitted, outside quiet hours (`start == stop` = none), host not in an active `maintenance` window.
- Repeats (`Trap\Repeats`, used by `TrapProcessor` and `bin/rebuild-repeats.php`): traps are compared by `fingerprint` = md5(hostname, OID, content with timestamp values blanked - TimeTicks values and `*Timestamp`/`*LastChanged` varbinds). Before insert, the latest trap with the same fingerprint decides `repeats` (previous + 1 if younger than `$repeat_window_minutes`, else 0), `first_date` (chain start) and whether notifications are skipped (younger than `$notification_dedup_minutes`, all channels). The previous trap of a chain gets `superseded = 1`; `TrapRepository` lists only `superseded = 0` (filter `repeats => true` to include all). The trap is always stored. After changing the window run `php bin/rebuild-repeats.php`.
- Message URLs use base64url (`b64url_encode`); `/message/` also accepts legacy standard base64 links (may contain `/`) from old e-mails.

## Database

MariaDB in STRICT mode - truncate to column length before insert (see `TrapProcessor::MAX_LEN`). `db/SCHEMA.sql` is the full current schema (fresh installs); every schema change also needs a `db/migrations/<version>.sql`, an entry in `db/UPDATE.SQL` and a detection check in `Schema::MIGRATIONS` / `Schema::detect()` (plus `Schema::POST_MIGRATION` for backfill scripts); `php bin/migrate.php` applies pending migrations (`--check` = status only, `--install` = load SCHEMA.sql into an empty DB). Write migrations re-runnable (`IF [NOT] EXISTS`), statements end with `;` at line end (admins see a warning banner while a migration is missing; code that needs new columns must check `Schema::has()` so the app keeps working before the migration). Keep `SCHEMA.sql` updated whenever the schema changes.

## UI

Tabler 1.7.5 (dark, Bootstrap 5 bundled in `public/js/tabler.min.js` and exposed as `tabler.bootstrap`, not `window.bootstrap`) + Tabler Icons 3.52 webfont (split per-category font files in `public/css/tabler/fonts/`) + jQuery 3.7 + tippy.js/Popper, all vendored in `public/` (no CDN), styled to match the sibling project `pw.ugbb.net/nalarms-dev.ugbb.net`. To upgrade, copy `dist/css/tabler.min.css`, `dist/js/tabler.min.js` (@tabler/core) and `dist/tabler-icons.min.css` + the woff2 files it references (@tabler/icons-webfont) over the vendored files. Asset URLs carry `App::assetVersion()` (file mtimes), so replaced files bypass the 7-day nginx cache.

- Theme: `View::theme()` puts the colour mode + `View::THEME` (primary `azure`, base `gray`) on `<html>` of `layout.php`, `login.php`, `bare.php`; needs `public/css/tabler/tabler-themes.min.css`. Dark by default; the navbar sun/moon icon (`.toggle-theme` in app.js) switches `data-bs-theme` live and sets cookie `theme=light` (365 days, removed for dark), read server-side so pages render without a flash. Use theme variables (`var(--tblr-...)`) in `app.css`, not hard-coded colours.
- `views/layout.php`: navbar, reload timer (`users.reload_page`, paused via cookie `stop-refresh`), single `#modal1`.
- Trap tables are one line per trap (`table-layout: fixed`, cells clipped with an ellipsis in `public/css/app.css`); the full content is in the message tooltip / quick view. Traps with `repeats > 0` get a repeat badge after the message (`TrapPresenter::repeatBadge()`); the last column holds the quick-view eye badge and the actions dropdown. Traps younger than `TrapPresenter::RECENT_SECONDS` (5 min) get an animated status dot before the message (outside the clipped text). Dashboard widgets show at most `Auth::DASH_MAX_ROWS` (20) traps.
- Quick view / trap page (`partials/trap-details.php`): host, IP, date, severity, message, OID, database id (link to the trap page), repeats with first / last occurrence, content, MIB description, raw trap.
- Settings: left menu + one card whose header holds the page title. Maintenance list shows start + stop as one secondary "Active from / to" column, ordered by stop time (newest first). Users have a list (`partials/user-list.php`, two-line rows) and a details page `settings/users/{id}/` (`partials/user-details.php`, helpers in `Web\UserPresenter`). `.load-modal[data-redirect]` sends the browser there after a successful modal submit instead of reloading.
- Trap lists are server-side paginated (`Pages::listing()`: `?page`, `?limit`, `?q`), rendered by `views/list.php` + `partials/trap-table.php` + `partials/pagination.php`; row HTML comes from `Web\TrapPresenter` (Tabler colour per severity group via `Severity::tablerColor()`).
- `public/js/app.js`: `.load-modal` links load their href into `#modal1`; `.modal-execute[data-action]` posts `#modal-form` and reloads unless the response contains `alert-danger`/`alert-warning`; live view polls `ajax/live/`.
- Date / time inputs: always `Web\DateTimeField` - date = Tabler Datepicker (`data-bs-toggle="datepicker"`, `data-bs-date-format="YYYY-MM-DD"`; needs `public/js/vendor/vanilla-calendar-pro.js`), time = Tabler InputMask (`data-mask="00:00"`; needs `public/js/vendor/imask.min.js`), both loaded before `tabler.min.js`. A date+time field `x` posts `x_date` + `x_time`; read it with `DateTimeField::combine()`. No native `type=date/time/datetime-local` inputs. `initInputs(el)` in app.js activates masks in HTML loaded later (modals).
- Screenshot mode (`$screenshot_mode = true` in config.php only - intentionally undocumented in config.dist.php; applies to all users): `App::run()` wraps the output (pages and AJAX fragments) in `Web\Anonymizer`, which masks hostnames (traps, maintenance, exceptions, users' permitted hosts, incl. short names), IPv4/IPv6/MAC addresses and users' personal data (real names, usernames as whole words, e-mail addresses, phone numbers) as `******` in text nodes and `tippy-title`/`title`/`placeholder` attributes only - hrefs and form values stay real.
- Tooltips: `data-toggle="tooltip"` + `tippy-title` (tippy `allowHTML`) - the attribute holds HTML, so trap-derived text is escaped twice (`TrapPresenter::tip()`). Call `initTooltips(el)` after inserting HTML.

## Known data quirks (production)

- Some `severity_definitions.comment` values (imported from MIBs by the old version) contain literal `<br>` text, which the settings list shows escaped.
- User `noc` has `hostnames = 'hostnames'` (legacy value), shown as "1 host".
- Juniper BGP traps carry peer addresses hex-encoded in values (`"0A 02 13 46 "`); screenshot mode does not mask those.
