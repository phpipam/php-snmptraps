## php-snmptraps

###### php snmptrap handler and web UI for management

php-snmptraps is an php trpahandler that processes snmp traps, writes them to database and/or file and sends
notifications via sms/email/pushover to users, based on web settings.

It comes with nice HMTL5 UI:
..* Dashboard that shows overview of last messages
..* Displays messages sent by specific host
..* Displays messages per severity
..* Displays specific messages received
..* Live update of received traps

and more.

You can set severity level for each received message, ignore specific message types, create maintaneance periods
for hosts, set per-user quiet hours and more.


Notifications are set per-user/severity, by default aupported are:
..* Email notification
..* Pushover notification
..* SMS notification

The can be easily extended to any other custom notification type.
