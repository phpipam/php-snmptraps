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

![Screen1](docs/screenshots/Screen1.png?raw=true "Screen1")


## Layout

```
public/        web document root (index.php front controller + css/js) - the ONLY directory the web server exposes
bin/           traphandler.php - called by snmptrapd
src/           application classes (namespace Snmptraps\)
views/         HTML templates
vendor/        PHPMailer
db/            SCHEMA.sql (fresh install), migrations/ (upgrades)
config.php     configuration (copy of config.dist.php, not in git)
```


## Setup

1. **Database**

   ```
   mysql -u root -p -e "CREATE DATABASE snmptraps CHARACTER SET utf8mb3;"
   mysql -u root -p -e "CREATE USER 'snmptraps'@'localhost' IDENTIFIED BY 'snmptraps';
                        GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON snmptraps.* TO 'snmptraps'@'localhost';"
   mysql -u root -p snmptraps < db/SCHEMA.sql
   ```

2. **Configuration**: `cp config.dist.php config.php` and edit (database, `BASE`, `$url`, notifications).

3. **snmptrapd** (`snmptrapd.conf`):

   ```
   traphandle default /usr/local/bin/php /usr/local/www/snmptraps/bin/traphandler.php
   ```

   The user snmptrapd runs as must be able to read the application and write `$filename` (log file).

4. **Web server** - expose only `public/` and route everything else to `public/index.php`.
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

5. Log in with **Admin / snmptraps** and change the password.


## Upgrading from 0.3.x

1. Back up the database.
2. Replace the code (keep your `config.php`; its format is unchanged).
3. Apply `db/migrations/1.0.0.sql` (renames `maintaneance` to `maintenance`, adds indexes, etc.).
4. Point the web server at `public/` (see above) and snmptrapd at `bin/traphandler.php`.


## Testing

Send a trap:

```
snmptrap -v 2c -c public <server> '' .1.3.6.1.4.1.2636.4.1.1 .1.3.6.1.4.1.2636.4.1.1 s "Power supply failure"
```

or feed one directly to the handler:

```
printf 'host\nUDP: [10.0.0.1]:1->[10.0.0.2]:162\nDISMAN-EVENT-MIB::sysUpTimeInstance 1\nSNMPv2-MIB::snmpTrapOID.0 IF-MIB::linkDown\nIF-MIB::ifName.3 ge-0/0/1\n' \
  | php bin/traphandler.php
```

Errors are written to the file set in `$filename` (set `$write_debug_file = true` to dump every parsed trap).
