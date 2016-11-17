<?php

/*	database connection details
 ******************************/
$db['host'] = "localhost";
$db['user'] = "snmptrapd";
$db['pass'] = "snmptrapd";
$db['name'] = "snmptrapd";
$db['port'] = 3306;

/**
 * php debugging on/off
 *
 * true  = SHOW all php errors
 * false = HIDE all php errors
 ******************************/
$debugging = true;

/**
 *	manual set session name for auth
 *	increases security
 *	optional
 */
$phpsessname = "snmptrapd";

/**
 *	BASE definition if snmptraps
 * 	is not in root directory (e.g. /snmptraps/)
 ******************************/
if(!defined('BASE'))
define('BASE', "/index.php");




# --- trap receiver configuration

/**
 * Use database flag to write file to database
 *
 *  Set database variables under config.php
 *
 * (default value: true)
 *
 * @var bool
 * @access public
 */
$use_database  = true;

/**
 * AD connection parameters
 *
 *  if AD selected for authentication
 *
 * @var array
 */
$ad = array (
    'base_dn'=>"DC=domain,DC=local",
    'ad_port'=>389,
    'account_suffix'=>"domain.local",
    'domain_controllers'=>array("127.0.0.1")
);

/**
 * Write result to file or not ?
 *
 *  Useful for DB debugging
 *
 *  false will not write to file
 *
 * (default value: "/tmp/traps.txt")
 *
 * @var string|bool
 * @access public
 */
$filename = "/tmp/traps.txt";

/**
 * Default MIB directory
 *
 * (default value: "/usr/share/snmp/mibs/")
 *
 * @var string
 * @access public
 */
$mib_directory = "/usr/share/snmp/mibs/";

/**
 * Send notifications flag
 *
 * false to not send
 *
 *  otherwise provide array of required methods:
 *      - pushover
 *      - mail
 *      - sms
 *      - custom (read docs)
 *
 * @var bool
 * @access public
 */
$notification_methods = array("mail");


/**
 * Params array init
 *
 * (default value: array())
 *
 * @var array
 * @access public
 */
$notification_params = array();

/**
 * SMS parmeters
 *
 * @var mixed
 * @access public
 */
$notification_params['sms'] = array(
    "server"=>"127.0.0.1:8080",                 // sms server
    "uri"=>"/sms/",                             // sms server uri
    "appid"=>"snmptraps",                       // application id
    "sender"=>"snmptraps"                       // from
);

/**
 * Mail parameters
 *
 *  if Mail selected for notifications
 *
 * @var array
 */
$notification_params['mail'] = array (
    "type"=>"localhost",                    // mailserver type ('localhost','smtp')
    "server"=>"127.0.0.1",                  // array of mailservers seperated by ;
    "port"=>25,                             // smtp port number
    "security"=>"none",                     // security type ('none','ssl','tls')
    "auth"=>false,                          // authentication (true, false)
    "user"=>"",                             // username for smtp auth
    "pass"=>"",                             // password for smtp auth
    "from"=>"lsnmptraps@domain.si"          // as who to send mail
);

/**
 * Pushover parameters
 *
 *  if pushover selected for notifications
 *
 *  https://pushover.net
 *
 * @var array
 */
$notification_params['pushover'] = array (
    "token"=>"",     // Pushover APP token
    "key"=>""        // Pushover APP group / user key
);





# --- UI parameters

/**
 * URL parameter
 *
 * (default value: "")
 *
 * @var string
 * @access public
 */
$url = "http:/127.0.0.1/";

/**
 * Site title
 *
 * (default value: "Snmptraps server")
 *
 * @var string
 * @access public
 */
$title = "Snmptraps server";

/**
 * Footer text
 *
 * (default value: "")
 *
 * @var string
 * @access public
 */
$footer = "php-snmptrap management";

?>
