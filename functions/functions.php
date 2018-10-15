<?php

/* @config file ------------------ */
require( dirname(__FILE__) . '/../config.php' );

/* @debugging functions ------------------- */
ini_set('display_errors', 1);
if (!$debugging) { error_reporting(E_ERROR ^ E_WARNING); }
else			 { error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT); }

/* @classes ---------------------- */
require( dirname(__FILE__) . '/version.php' );
require( dirname(__FILE__) . '/classes/class.Common.php' );
require( dirname(__FILE__) . '/classes/class.Result.php' );
require( dirname(__FILE__) . '/classes/class.Database.php' );
require( dirname(__FILE__) . '/classes/class.User.php' );
require( dirname(__FILE__) . '/classes/class.SNMP.php' );
require( dirname(__FILE__) . '/classes/class.Table_print.php' );
require( dirname(__FILE__) . '/classes/class.Modal.php' );