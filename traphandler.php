<?php


/**
 * php traphandler for snmp traps
 *
 *  set configuration parameters under functions/config.php
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 *
 * example trap:
 *   snmptrap -v 2c -c public 10.12.50.51 '' .1.3.6.1.4.1.5089.1.0.1 .1.3.6.1.4.1.5089.2.0.999 s "123456"
 *
 **/


# include config and trap class
require( dirname(__FILE__) . '/functions/classes/class.Database.php' );
require( dirname(__FILE__) . '/functions/classes/class.Result.php' );
require( dirname(__FILE__) . '/functions/classes/class.Notify.php' );
require( dirname(__FILE__) . '/functions/classes/class.traphandler.php' );
require( dirname(__FILE__) . '/config.php' );
require( dirname(__FILE__) . '/functions/version.php' );


# --- check php version
require('functions/check_version.php');


# --- process

# get data from stdin
while($f = fgets(STDIN)){
	$trap_content[] = $f;
}


# --- load traphandler and process provided trap
$Trap = new Trap ($trap_content);


# --- write file
if ($filename!==false) {
    $File = new Trap_file ($Trap->get_trap_details ());
    // set where to write
    $File->set_file ($filename);
    // write raw file
    // $Trap->write_file ();
    // write parsed file
    $File->write_file_parsed ();
}


# --- write to database
$Trap->write_trap ();


# --- send notification
if ($notification_methods !== false && $Trap->exception === false) {
    // load object and send trap
    $Notify = new Trap_notify ($Trap->get_trap_details (), $notification_params, $filename);
    // send
    $Notify->send_notification ();
}

# --- close connaection and file
$File->close_file ();