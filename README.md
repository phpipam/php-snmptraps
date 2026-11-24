## php-snmptraps

###### php snmptrap handler and web UI for management

php-snmptraps is a PHP trap handler for net-snmp's `snmptrapd`. It parses received traps, stores them in a
MySQL/MariaDB database and sends notifications (e-mail, SMS, Pushover, Slack/Mattermost) to users based on
their settings.

Web UI:
* Dashboard with the latest traps grouped by severity
* Traps per host, per severity and per message, live view, search
* Per-OID severity definitions, ignore rules (exceptions), maintenance windows, MIB import
* Per-user notification methods, severities, permitted hosts and quiet hours

Requirements: PHP 8.3 - 8.5 (extensions: pdo_mysql, mbstring, openssl; ldap for AD login), MySQL/MariaDB, net-snmp.
The curl extension is **not** required.

![Dashboard](docs/screenshots/dashboard.png?raw=true "Dashboard")

*Dashboard: latest traps per severity group. A pulsing dot marks traps from the last 5 minutes, the badge after
the message counts repeats of the same trap, the eye opens the quick view. More in [Screenshots](#screenshots).*


## Layout

```
public/        web document root (index.php front controller + css/js) - the ONLY directory the web server exposes
bin/           command line scripts:
                 traphandler.php      trap receiver, called by snmptrapd
                 migrate.php          database install / upgrade
                 test-mail.php        send a test notification e-mail
                 rebuild-repeats.php  recalculate repeat counters of stored traps
src/           application classes (namespace Snmptraps\)
views/         HTML templates
vendor/        PHPMailer
db/            SCHEMA.sql (fresh install), migrations/ (upgrades) - used by bin/migrate.php
docs/          screenshots
config.php     configuration (copy of config.dist.php, not in git)
```



## Screenshots

**All traps** - one line per trap, paginated, with a quick filter. Repeated traps are listed once (latest).

![All traps](docs/screenshots/traps.png?raw=true "All traps")

**Quick view** - trap details, repeats (first / last occurrence), content and raw trap.

![Quick view](docs/screenshots/quickview.png?raw=true "Quick view")

**Message** - all traps with the same message, with the MIB description of the notification.

![Message](docs/screenshots/message.png?raw=true "Message")

**Live view** - new traps are added automatically.

![Live view](docs/screenshots/live.png?raw=true "Live view")

**Search** - by text, host, severity and time range.

![Search](docs/screenshots/search.png?raw=true "Search")

**User management** - role, authentication, contact, notification methods, quiet time and permitted hosts.

![User management](docs/screenshots/users.png?raw=true "User management")

**Maintenance windows** - no notifications for a host while its window is active.

![Maintenance](docs/screenshots/maintenance.png?raw=true "Maintenance")

## Installation

All commands are run from the application directory (e.g. `/usr/local/www/snmptraps`).

1. **Code**

   ```
   git clone https://github.com/phpipam/php-snmptraps.git /usr/local/www/snmptraps
   ```

2. **Database and user**

   ```
   mysql -u root -p -e "CREATE DATABASE snmptraps CHARACTER SET utf8mb3;"
   mysql -u root -p -e "CREATE USER 'snmptraps'@'localhost' IDENTIFIED BY 'secret';
                        GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON snmptraps.* TO 'snmptraps'@'localhost';"
   ```

3. **Configuration**: `cp config.dist.php config.php` and edit it - at least `$db`, `BASE` (web path, e.g. `/snmptraps/`),
   `$url` (links in notifications), `$notification_methods` / `$notification_params` and `$filename` (log file).

4. **Schema** - loads `db/SCHEMA.sql` into the empty database, using the credentials from `config.php`:

   ```
   php bin/migrate.php --install
   php bin/migrate.php --check        # should report "Database schema is up to date."
   ```

   (`mysql -u root -p snmptraps < db/SCHEMA.sql` does the same.)

5. **snmptrapd** (`snmptrapd.conf`), then restart / HUP snmptrapd:

   ```
   traphandle default /usr/local/bin/php /usr/local/www/snmptraps/bin/traphandler.php
   ```

   The user snmptrapd runs as must be able to read the application and write `$filename`.

6. **Web server** - expose only `public/` and route everything else to `public/index.php`.
   nginx example for the UI at `/snmptraps/` (set `BASE` to `/snmptraps/`):

   ```nginx
   location = /snmptraps { return 301 /snmptraps/; }
   location ^~ /snmptraps/css/ { alias /usr/local/www/snmptraps/public/css/; expires 7d; }
   location ^~ /snmptraps/js/  { alias /usr/local/www/snmptraps/public/js/;  expires 7d; }
   location ^~ /snmptraps/ {
       include        fastcgi_params;
       fastcgi_param  SCRIPT_FILENAME /usr/local/www/snmptraps/public/index.php;
       fastcgi_param  SCRIPT_NAME     /snmptraps/index.php;
       fastcgi_pass   unix:/var/run/php-fpm.socket;
   }
   ```

   Apache: point `DocumentRoot` (or an `Alias`) to `public/` and add
   `FallbackResource /snmptraps/index.php`.

7. Log in with **Admin / snmptraps**, change the password and set your e-mail and notification options
   in the profile. Then send a test trap and a test e-mail (see [Testing](#testing)).


## Upgrading

1. Back up the database.
2. Replace the code (keep your `config.php`).
3. Update the database:

   ```
   php bin/migrate.php --check    # list pending migrations (exit code 1 if any)
   php bin/migrate.php            # apply them (asks first; --yes for scripts)
   ```

   It detects which `db/migrations/<version>.sql` are missing, applies them in order and runs the follow-up
   steps (e.g. `bin/rebuild-repeats.php`). Administrators see a warning in the UI while a migration is missing.
4. From 0.3.x: point the web server at `public/` (see above) and snmptrapd at `bin/traphandler.php`.


## Testing

**Test trap** - send one over the network to snmptrapd:

```
snmptrap -v 2c -c public <server> '' IF-MIB::linkDown IF-MIB::ifIndex.3 i 3 IF-MIB::ifAdminStatus.3 i 1 IF-MIB::ifOperStatus.3 i 2
```

(snmptrapd must accept the community, e.g. `authCommunity log,execute public` in `snmptrapd.conf`.)

or feed one directly to the handler (stored in the database and notified like a real trap):

```
printf 'test-host\nUDP: [192.0.2.1]:1->[192.0.2.2]:162\nDISMAN-EVENT-MIB::sysUpTimeInstance 1\nSNMPv2-MIB::snmpTrapOID.0 IF-MIB::linkDown\nIF-MIB::ifName.3 ge-0/0/1\n' \
  | php bin/traphandler.php
```

The trap appears in the UI (Dashboard / Live). If not, check the log file set in `$filename`
(`$write_debug_file = true` also dumps every parsed trap there).

**Test e-mail** - sends the notification e-mail for a stored trap (default: the latest one) with the mail
settings from `config.php`, independent of user notification settings:

```
php bin/test-mail.php you@example.com             # latest trap
php bin/test-mail.php you@example.com 12345       # trap with database id 12345 (shown in the quick view)
php bin/test-mail.php --html 12345 > mail.html    # only write the HTML, nothing is sent
```
