# CLAUDE.md — php-snmptraps

## Project Overview

**php-snmptraps** is a PHP-based SNMP trap handler and web UI for network monitoring. It:
- Receives SNMP traps via `snmptrapd` (net-snmp) and processes them through `traphandler.php`
- Stores traps in a MySQL/MariaDB database
- Sends notifications (email, SMS, Pushover, Slack/Mattermost)
- Provides an HTML5 web UI for viewing, filtering, searching, and managing traps

**Current version:** 0.3.1 (see `functions/version.php`)
**Minimum PHP version:** 7.0

---

## Repository Structure

```
php-snmptraps/
├── index.php               # Main web entry point; routes requests via ?app= param
├── traphandler.php         # CLI script: receives STDIN from snmptrapd, processes traps
├── config.dist.php         # Config template (copy to config.php before use)
├── .htaccess               # mod_rewrite rules for pretty URLs
├── app/                    # Web UI pages (included by index.php)
│   ├── dashboard/          # Dashboard showing traps by severity group
│   ├── host/               # Traps filtered by host
│   ├── json/               # JSON API endpoint for live update data
│   ├── live/               # Live trap view with auto-refresh
│   │   └── update.php      # AJAX endpoint: returns new traps since a given ID
│   ├── login/              # Login/logout pages and auth check
│   ├── message/            # Per-message view and editing
│   ├── search/             # Search form and results
│   ├── settings/           # Admin-only settings (severity, exceptions, maintenance, MIBs, users)
│   ├── severity/           # Traps filtered by severity
│   ├── trap/               # Single trap detail view and popup
│   ├── footer.php          # Page footer include
│   └── top-menu.php        # Navigation bar include
├── functions/
│   ├── functions.php       # Bootstrap: loads config + all classes
│   ├── version.php         # Version constants ($version array)
│   ├── check_version.php   # PHP version check at runtime
│   ├── classes/
│   │   ├── class.Common.php        # Base class with shared utility methods
│   │   ├── class.Database.php      # DB abstraction: DB (abstract), Database_PDO, Database_wrapper
│   │   ├── class.Modal.php         # Bootstrap modal helpers
│   │   ├── class.Notify.php        # Notification classes: Trap_notify, mail, sms, pushover, slack
│   │   ├── class.Result.php        # Alert/result output helper
│   │   ├── class.SNMP.php          # Snmp_read_MIB, Trap_read, Trap_update classes
│   │   ├── class.Table_print.php   # HTML table rendering for trap lists
│   │   ├── class.traphandler.php   # Trap, Trap_file classes (trap parsing and file writing)
│   │   └── class.User.php          # User authentication and session management
│   ├── PHPMailer/          # PHPMailer v6+ (current, namespaced)
│   ├── PHPMailer_old/      # PHPMailer legacy (not used by default)
│   └── adLDAP/             # adLDAP library for Active Directory auth
├── db/
│   ├── SCHEMA.sql          # Full database schema with default seed data
│   └── UPDATE.SQL          # Incremental ALTER statements by version
├── css/                    # Bootstrap, Font Awesome, custom CSS, screenshots
├── js/                     # jQuery, Bootstrap JS, bootstrap-table, custom magic.js
└── README.md
```

---

## Configuration

The application requires `config.php` in the root (not version-controlled). Copy from the template:

```bash
cp config.dist.php config.php
```

Key config variables (all set directly as PHP globals):

| Variable | Purpose |
|---|---|
| `$db` | MySQL connection: host, user, pass, name, port |
| `$debugging` | Show/hide PHP errors |
| `$phpsessname` | Session name (improves security) |
| `BASE` | URL base if not in document root (e.g. `/snmptraps/index.php`) |
| `$use_database` | Write traps to database (default: true) |
| `$filename` | File path for debug output, or `false` to disable |
| `$mib_directory` | Path to MIB files (default: `/usr/share/snmp/mibs/`) |
| `$notification_methods` | Array of enabled methods: `mail`, `sms`, `pushover`, `slack` |
| `$notification_params` | Per-method config arrays |
| `$url` | Application base URL (used in notification links) |
| `$ad` | Active Directory config (if using AD auth) |
| `$strip_hostname_domain` | Strip domain from hostnames in display |

There is no database-stored settings table in this project. All configuration is file-based via `config.php`.

---

## Database Schema

Database: MySQL/MariaDB, charset UTF-8.

### Tables

**`traps`** — All received SNMP traps
- `id`, `hostname`, `ip`, `oid`, `date` (timestamp), `message`, `severity`, `content` (text), `raw` (text)
- Indexed on `severity` and `hostname`

**`users`** — User accounts
- `id`, `username`, `real_name`, `auth_method` (`local`|`ad`|`krb`), `password` (crypt'd), `role` (`user`|`operator`|`administrator`)
- `email`, `tel`, `notification_types` (semicolon-separated), `notification_severities` (semicolon-separated)
- `quiet_time_start`, `quiet_time_stop` (time fields for notification suppression)
- `reload_page`, `dash_layout` (e.g. `6:30;6:30;12:15;12:15`), `hostnames` (semicolon-separated or `all`)

**`exceptions`** — OID/host combos to silently ignore
- `id`, `oid`, `hostname`, `content`, `comment`
- Unique constraint on (`oid`, `content`, `hostname`)

**`severity_definitions`** — Custom severity mappings per OID
- `id`, `oid`, `severity` (enum), `content`, `comment`
- Ships with ~50 pre-seeded entries for common Cisco/Juniper/standard MIBs

**`maintaneance`** *(note: typo is in the DB schema)* — Maintenance windows
- `id`, `hostname`, `start`, `stop`, `comment`

### Setup

```bash
mysql -u root -p -e "create database snmptraps;"
mysql -u root -p -e "GRANT ALL on snmptraps.* to snmptraps@localhost identified by 'snmptraps';"
mysql -u root -p snmptraps < db/SCHEMA.sql
```

**Upgrading existing installations:** Apply `db/UPDATE.SQL` manually in order.

---

## Trap Processing Flow

1. **`snmptrapd`** invokes `traphandler.php` for each trap:
   ```
   traphandle default /usr/bin/php /var/www/traphandler.php
   ```

2. **`traphandler.php`** reads raw trap from `STDIN` (line array) and:
   - Instantiates `Trap` class → parses the message
   - Optionally writes to file (`Trap_file`)
   - Writes to database (`Trap::write_trap()`) — checks exceptions first
   - Sends notifications via `Trap_notify::send_notification()`

3. **`Trap` class** (`functions/classes/class.traphandler.php`) parses:
   - Line 0: hostname
   - Line 1: source IP (`UDP:[ip:port]` format)
   - Line 2: uptime
   - Line 3: OID (e.g. `IF-MIB::linkDown`)
   - Lines 4+: content key-value pairs

4. **Severity detection** (in priority order):
   - Looks for `Severity`/`severity` field in trap content
   - Checks `severity_definitions` table for OID matches
   - Falls back to `unknown`

5. **Exception check**: If an OID+hostname+content matches an entry in `exceptions`, the trap is discarded (not stored, not notified).

---

## Web Routing

URL routing uses Apache `mod_rewrite` (`.htaccess`):

```
/app/               → index.php?app=app
/app/page/          → index.php?app=app&page=page
/app/page/id/       → index.php?app=app&page=page&id1=id
```

The `index.php` dispatches on `$_GET['app']`:
- `login` / `logout` → `app/login/index.php`
- Everything else → `app/{app}/index.php` (requires authenticated session)

All pages are included fragments (not standalone PHP files). Global objects (`$Database`, `$Result`, `$User`, `$Trap`, `$Table_print`) are initialized in `index.php` and available to all included pages.

---

## Class Architecture

### Inheritance Hierarchy

```
Common_functions
└── User

DB (abstract)
└── Database_PDO
    └── Database_wrapper

Snmp_read_MIB
└── Trap_read
    └── Trap_update

Trap          (standalone, used in traphandler.php)
Trap_file     (standalone)
Trap_notify   (standalone)
  ├── mail
  ├── sms
  ├── pushover
  └── slack
```

### Key Classes

**`Database_PDO`** — PDO wrapper. Reads connection from `config.php`. Methods: `getObject`, `getObjects`, `getObjectQuery`, `getObjectsQuery`, `insertObject`, `updateObject`, `deleteObject`, `findObjects`, `runQuery`.

**`Database_wrapper`** — Extends `Database_PDO` with try/catch helpers: `fetch_all_objects`, `fetch_object`, `fetch_multiple_objects`, `create_object`, `update_object`, `remove_object`.

**`Trap`** — Parses raw SNMP trap from stdin array. Detects severity, message, and special protocol messages (linkUp/Down, BGP, OSPF, IKE, etc.).

**`Trap_read`** — Reads traps from DB with filtering by severity, hostname, date, and search terms. Supports pagination (`reset_print_limit`, `reset_print_offset`).

**`Trap_update`** — Extends `Trap_read`. Handles define/delete/ignore actions on traps.

**`Trap_notify`** — Dispatches notifications. Checks maintenance windows and user quiet hours. Instantiates notification class by method name (e.g. `new mail(...)`, `new slack(...)`).

**`User`** — Session management and authentication. Supports `local` (crypt), `ad` (adLDAP), and `krb` (Kerberos/Apache `REMOTE_USER`). Roles: `user`, `operator`, `administrator`.

---

## Notification System

Notification methods are class names matching entries in `$notification_methods` config array. Adding a new method means:
1. Adding the class name to `$notification_methods` in `config.php`
2. Adding a config section to `$notification_params['methodname']`
3. Creating a class with a `send($message_details, $recipients)` method in `class.Notify.php`

The `Trap_notify::send_notification()` method dynamically instantiates: `new $method($params)` and calls `->send()`.

### Notification filtering
- Per-user severity filter: `notification_severities` (semicolon-separated list)
- Per-user quiet hours: `quiet_time_start` and `quiet_time_stop`
- Per-user hostname filter: `hostnames` field (`all` or semicolon-separated list)
- Maintenance windows: checked against `maintaneance` table

---

## Authentication

Three methods configured per-user via `auth_method` field:

- **`local`**: Password stored as `crypt()` hash (SHA-512 preferred)
- **`ad`**: Active Directory via adLDAP library (configured in `$ad` array)
- **`krb`**: Kerberos via Apache `REMOTE_USER` variable

Session is stored in PHP `$_SESSION['trapusername']`.

---

## Severity Levels

Eight levels (in descending severity):
`emergency` → `alert` → `critical` → `error` → `warning` → `notice` → `informational` → `debug`

Plus `unknown` for unmatched traps.

Dashboard groups:
- High: emergency, alert, critical
- Medium: error, warning
- Low: notice, informational, debug
- Other: unknown

---

## MIB Support

MIB files are read from `$mib_directory` (default `/usr/share/snmp/mibs/`). Supported extensions: `.my`, `.txt`, `.mib`.

The `Snmp_read_MIB` class parses MIB files to extract:
- `NOTIFICATION-TYPE` definitions
- Trap objects list
- Trap description text

MIB files can be uploaded/managed via Settings → MIB files in the web UI.

---

## Development Conventions

### PHP Style
- PHP 7.0+ compatible (avoid 8.x-only features for broad compatibility, though recent commits target 8.3)
- Classes use `camelCase` methods, `snake_case` for some older methods
- All classes documented with PHPDoc-style block comments
- Error handling uses `try/catch` with `Exception`; errors displayed via `$Result->show()`
- Input sanitization: use `$User->strip_input_tags()` on `$_POST`/`$_GET` before processing
- Database queries use PDO prepared statements (parameterized)

### File Organization
- Each web page is a fragment in `app/{section}/index.php`
- Action handlers (form submissions) are `app/{section}/{action}-submit.php`
- AJAX endpoints return HTML fragments or `"False"` string

### No Build System
There is no Composer autoloader, no npm, no build step. All dependencies are vendored in `functions/`:
- PHPMailer (v6, PSR-4 style but manually included via `require`)
- adLDAP (manually included)

### Adding a New App Page
1. Create `app/{newpage}/index.php`
2. Access at `/{newpage}/` (mod_rewrite handles routing)
3. All global objects (`$Database`, `$User`, `$Trap`, etc.) are available

### Database Changes
- Add schema changes to `db/UPDATE.SQL` with a version comment
- Keep `db/SCHEMA.sql` as the full current schema for fresh installs

---

## Setup Summary

1. Copy `config.dist.php` to `config.php` and configure
2. Enable Apache `mod_rewrite`
3. Import `db/SCHEMA.sql` into MySQL
4. Configure `snmptrapd` to call `traphandler.php`
5. Access the web UI; default login: `Admin` / `snmptraps`

### Testing Trap Reception

```bash
snmptrap -v 2c -c public <host_ip> '' \
  .1.3.6.1.4.1.2636.4.1.1 \
  .1.3.6.1.4.1.2636.4.1.1 s "Power supply failure"
```

Debug output (if `$filename` is set in config):
```bash
tail -f /tmp/trap.txt
```

---

## Known Issues / Notes

- The `maintaneance` table name has a typo (double 'a') — this is intentional/historical; do not rename without updating all references throughout the codebase
- `traphandler.php` has a method call `$Trap->write_t1rap()` (line 54) with a typo — the actual method is `write_trap()` in the class; the file calls `write_t1rap` which will cause a fatal error. The working method name is `write_trap`
- `PHPMailer_old/` directory is retained for reference but not used
- SSL certificate verification for SMTP is disabled by default (`verify_peer => false`)
- The `$User->settings->prettyLinks` reference in `Common_functions::create_link()` expects a `settings` table that does not exist in this schema — pretty links are configured via `BASE` constant and mod_rewrite only
