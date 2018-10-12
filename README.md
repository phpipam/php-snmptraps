## php-snmptraps

###### php snmptrap handler and web UI for management

php-snmptraps is an php trpahandler that processes snmp traps, writes them to database and/or file and sends
notifications via sms/email/pushover to users, based on web settings.

It comes with nice HMTL5 UI:
* Dashboard that shows overview of last messages
* Displays messages sent by specific host
* Displays messages per severity
* Displays specific messages received
* Live update of received traps

and more.

You can set severity level for each received message, ignore specific message types, create maintenance periods
for hosts, set per-user quiet hours and more.


Notifications are set per-user/severity, by default aupported are:
* Email notification
* Pushover notification
* SMS notification
* Slack/Mattermost notification

The can be easily extended to any other custom notification type.


## screenshots

Some sample UI screenshots can be found below:
![Screen1](/css/screenshots/Screen1.png?raw=true "Screen1")
![Screen2](/css/screenshots/Screen2.png?raw=true "Screen2")
![Screen3](/css/screenshots/Screen3.png?raw=true "Screen3")
![Screen4](/css/screenshots/Screen4.png?raw=true "Screen4")


## setup

This guide assumes you have a working net-snmp, apache, php and mysql installation with default apache files stored
 in /var/www/.

1.Set traphandler

First, edit file `/etc/snmp/snmptrapd.conf` and add traphandler for default files to traphandler.php:

```
# listen on
agentaddress my_ip_address:162
# set php-snmptraps as default trap handler
traphandle default /usr/bin/php /var/www/traphandler.php
```

2.Prepare and edit config file

Now go to /var/www/ directory and copy config.dist.php to config.php:
```
cp /var/www/config.dist.php /var/www/config.php
```
and edit the config file to match your settings.


3.Set mod_rewrite

mod_rewrite is required for snmptraps. Example here:

http://phpipam.net/documents/prettified-links-with-mod_rewrite/

4.Import database schema

Now you have to import chema for mysql database with following command:

```
mysql -u root -p
Enter password:

mysql> create database snmptraps;
Query OK, 1 row affected (0.00 sec)

mysql> GRANT ALL on snmptraps.* to snmptraps@localhost identified by "snmptraps";
Query OK, 0 rows affected (0.00 sec)

mysql> exit
Bye

# import SCHEMA.SQL file
mysql -u root -p snmptraps < db/SCHEMA.sql
```

That should be it, fire up browser and login. Default user/pass is Admin/snmptraps.


5.Send test message and debugging

To test snmptrap you can use the following command:

```snmptrap -v 2c -c public my_public_ip '' .1.3.6.1.4.1.2636.4.1.1 .1.3.6.1.4.1.2636.4.1.1 s "Power supply failure"```

Check also the file you set in config.php for possible errors:

```tail -f /tmp/trap.txt```


