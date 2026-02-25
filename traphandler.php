<?php

/**
 * php traphandler for snmptraps
 *
 *  It receives snmptrap, parses it and enters it to database:
 *    [root@server /usr/local/share/snmp] more snmptrapd.conf | grep traphan
 *    # set traphandler
 *    traphandle default /usr/local/bin/php /usr/local/www/snmptraps/traphandler.php
 *
 *  Set configuration parameters for notifications etc under functions/config.php
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 *
 * example trap:
 *   snmptrap -v 2c -c public 10.12.50.51 '' .1.3.6.1.4.1.5089.1.0.1 .1.3.6.1.4.1.5089.2.0.999 s "test trap"
 *
 **/

try {
    # --- include config and trap classes
    require( dirname(__FILE__) . '/functions/classes/class.Database.php' );
    require( dirname(__FILE__) . '/functions/classes/class.Result.php' );
    require( dirname(__FILE__) . '/functions/classes/class.Notify.php' );
    require( dirname(__FILE__) . '/functions/classes/class.traphandler.php' );
    require( dirname(__FILE__) . '/config.php' );
    require( dirname(__FILE__) . '/functions/version.php' );

    # --- check php version
    require('functions/check_version.php');

    # --- process trap

    # get data from stdin to array
    while($f = fgets(STDIN)){
    	$trap_content[] = $f;
    }

    # --- load traphandler and process provided trap
    $Trap = new Trap ($trap_content);

    # --- write trap to database
    $Trap->write_trap ();

    # --- send notification if needed
    if ($notification_methods !== false && $Trap->exception === false) {
        // load object and send trap
        $Notify = new Trap_notify ($Trap->get_trap_details (), $notification_params);
        // send
        $Notify->send_notification ();
    }

    # --- write error to file
    if ($filename!==false && ( @$write_raw_file===true || @$write_debug_file===true )) {
        // init file to write
        $File = new Trap_file ($Trap->get_trap_details (), $trap_content);
        // set where to write
        $File->set_file ($filename);
        // write raw file
        if(@$write_raw_file===true)
        $File->write_file ();
        // write parsed file
        if(@$write_debug_file===true)
        $File->write_file_parsed ();
        //  close connaection and file
        $File->close_file ();
    }
}
catch (Exception $e) {
    # Write to file
    if ($filename!==false) {
        // open file
        $fh = fopen($filename, 'a') or die("can't open file");
        // write
        fwrite($fh, "\n".date("Y-m-d H:i:s")." :: ".$e->getMessage());
        fclose($fh);
    }
    # print
    die($e->getMessage());
}