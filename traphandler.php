<?php


/**
 * php traphandler for snmp traps
 *
 *  set configuration parameters under functions/config.php
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 *
 * example trap:
 *   snmptrap -v 2c -c public 10.12.50.51 '' .1.3.6.1.4.1.5089.1.0.1 .1.3.6.1.4.1.5089.2.0.999 s "test trap"
 *
 **/

try {
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

    # get data from stdin to array
    while($f = fgets(STDIN)){
    	$trap_content[] = $f;
    }


    # --- load traphandler and process provided trap
    $Trap = new Trap ($trap_content);


    # --- write file for each received trap
    if ($filename!==false && $debugging) {
        $File = new Trap_file ($Trap->get_trap_details ());
        // set where to write
        $File->set_file ($filename);
        // write raw file
        // $Trap->write_file ();
        // write parsed file
        $File->write_file_parsed ();
    }


    # --- write trap to database
    $Trap->write_t1rap ();


    # --- send notification
    if ($notification_methods !== false && $Trap->exception === false) {

            // //open file
            // $fh = fopen('/tmp/out.txt', 'a') or die("can't open file");
            // // write
            // fwrite($fh, date("Y-m-d H:i:s")."----- \n");
            // fwrite($fh, implode("",$trap_content));
            // fwrite($fh, "\n-----\n");
            // fclose($fh);

        try {
            // load object and send trap
            $Notify = new Trap_notify ($Trap->get_trap_details (), $notification_params, $filename);
            // send
            $Notify->send_notification ();
        }
        catch (Exception $e) {
            // open file
            $fh = fopen('/tmp/out.txt', 'a') or die("can't open file");
            // write
            fwrite($fh, date("Y-m-d H:i:s")."----- \n");
            fwrite($fh, implode("\n",$e->getMessage()));
            fwrite($fh, "-----\n");
            fclose($fh);
        }
    }

    # --- close connaection and file
    $File->close_file ();
}
catch (Exception $e) {
    # Write to file
    if ($filename!==false) {
        // open file
        $fh = fopen($filename, 'a') or die("can't open file");
        // write
        fwrite($fh, date("Y-m-d H:i:s")."----- \n");
        fwrite($fh, implode("\n",$e->getMessage()));
        fwrite($fh, "-----\n");
        fclose($fh);
    }
    die($e->getMessage());
}