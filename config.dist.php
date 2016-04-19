<?php

/*	database connection details
 ******************************/
$db['host'] = "localhost";
$db['user'] = "snmptrapd";
$db['pass'] = "snmptrapd";
$db['name'] = "snmptrapd";
$db['port'] = 3306;

/*	AD connect details
 ******************************/
$ad = false;

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
 *	BASE definition if phpipam
 * 	is not in root directory (e.g. /phpipam/)
 *
 *  Also change
 *	RewriteBase / in .htaccess
 ******************************/
if(!defined('BASE'))
define('BASE', "/");




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
 * Send notifications flag
 *
 * false to not send
 *
 *  otherwise provide array of required methods:
 *      - pushover
 *      - mail
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
    "type"=>"smtp",                         // mailserver type ('localhost','smtp')
    "server"=>array("127.0.0.1"),           // array of mailservers
    "port"=>25,                             // smtp port number
    "security"=>"none",                     // security type ('none','ssl','tls')
    "auth"=>false,                          // authentication (true, false)
    "user"=>"",                             // username for smtp auth
    "pass"=>"",                             // password for smtp auth
    "from"=>"lan-trap@domain.si"            // as who to send mail
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
 * Mail parameters
 *
 *  if Mail selected for notifications
 *
 * @var array
 */
$ad = array (
    'base_dn'=>"CN=domain,CN=local",
    'ad_port'=>389,
    'account_suffix'=>"domain.local",
    'domain_controllers'=>array("127.0.0.1")
);

?>
