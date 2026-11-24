<?php

/**
 * php-snmptraps configuration
 *
 * Copy to config.php (outside the web root, next to public/) and edit.
 */

/*  database connection details
 ******************************/
$db['host'] = "localhost";
$db['user'] = "snmptraps";
$db['pass'] = "snmptraps";
$db['name'] = "snmptraps";
$db['port'] = 3306;
// optional TLS to the database server
// $db['ssl']     = true;
// $db['ssl_ca']  = "/path/to/ca.pem";
// $db['ssl_key'] = "/path/to/client-key.pem";
// $db['ssl_cert']= "/path/to/client-cert.pem";

/**
 * php debugging on/off
 *
 * true  = show PHP errors / exception details in the UI
 * false = hide (errors are logged by PHP)
 ******************************/
$debugging = false;

/**
 * Session cookie name
 */
$phpsessname = "snmptraps";

/**
 * URL path the web UI is served from (must match the web server config),
 * e.g. "/" or "/snmptraps/"
 ******************************/
if (!defined('BASE'))
define('BASE', "/snmptraps/");

/**
 * Absolute URL of the web UI, used for links in notifications.
 * Empty = no links.
 */
$url = "http://127.0.0.1/snmptraps/";

/**
 * Site title / footer text
 */
$title  = "Snmptraps server";
$footer = "php-snmptraps";

/**
 * Show only the first label of hostnames in trap lists
 */
$strip_hostname_domain = true;

/**
 * Repeated traps (same hostname, OID and content; timestamp values such as
 * clogHistTimestamp are ignored when comparing)
 *
 *  notification_dedup_minutes: do not send notifications for a trap if an identical
 *                              trap was received within this many minutes (0 = off)
 *  repeat_window_minutes:      identical traps received less than this many minutes after
 *                              the previous one are counted as repeats; the count is shown
 *                              as a badge after the message in the UI, where only the
 *                              latest trap of a repeat chain is listed (0 = off).
 *                              After changing it run: php bin/rebuild-repeats.php
 ******************************/
$notification_dedup_minutes = 15;
$repeat_window_minutes      = 10;


# --- trap receiver

/**
 * Log file for receiver errors (and debug output below). false = PHP error_log.
 * Must be writable by the user snmptrapd runs as.
 */
$filename = "/tmp/traps.txt";

/**
 * Additionally dump every received trap (raw and/or parsed) to $filename
 */
$write_raw_file   = false;
$write_debug_file = false;

/**
 * MIB directory used for trap descriptions and the MIB import.
 * Leave unset to auto-detect /usr/local/share/snmp/mibs/ or /usr/share/snmp/mibs/.
 */
// $mib_directory = "/usr/local/share/snmp/mibs/";


# --- authentication

/**
 * Active Directory / LDAP bind for users with auth_method "ad" (requires php-ldap)
 */
$ad = array (
    'domain_controllers' => array("127.0.0.1"),
    'account_suffix'     => "domain.local",   // user -> user@domain.local
    'ad_port'            => 389,
    'use_ssl'            => false,            // ldaps://
    'use_tls'            => false,            // StartTLS
    'timeout'            => 5,
);


# --- notifications

/**
 * Enabled notification methods: any of "mail", "sms", "pushover", "slack".
 * Users choose which of these they receive in their profile.
 */
$notification_methods = array("mail");

$notification_params = array();

/**
 * SMS gateway: GET http://{server}{uri}sendsms?sender=&recipient=&appid=&reference=&message=
 */
$notification_params['sms'] = array(
    "server" => "127.0.0.1:8080",
    "uri"    => "/sms/",
    "appid"  => "snmptraps",
    "sender" => "snmptraps",
    "length" => 640                 // max message length, 0 = unlimited
);

/**
 * E-mail
 */
$notification_params['mail'] = array (
    "type"        => "localhost",   // 'localhost' (sendmail) or 'smtp'
    "server"      => "127.0.0.1",
    "port"        => 25,
    "security"    => "none",        // 'none', 'ssl', 'tls'
    "verify_peer" => false,         // verify SMTP server TLS certificate
    "auth"        => false,
    "user"        => "",
    "pass"        => "",
    "from"        => "snmptraps@domain.local",
    "from_name"   => "php-snmptraps",
);

/**
 * Pushover (https://pushover.net) - one message to the given user/group key
 */
$notification_params['pushover'] = array (
    "token" => "",                  // application token
    "key"   => ""                   // user or group key
);

/**
 * Slack / Mattermost incoming webhook (posted to url . key)
 */
$notification_params['slack'] = array (
    "url"      => "",
    "key"      => "",
    "mentions" => array (),         // e.g. array("@channel")
    "channel"  => "",               // optional channel override
    "username" => "php-snmptraps",
);
