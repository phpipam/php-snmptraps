<?php

/**
 *
 * Updates new items based on provided trap id
 *
 **/


# functions
require('../../functions/functions.php');

# Objects
$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);
# snmp class
$Trap       = new Trap_read ($Database);
$Table_print= new Table_print ();

# verify that user is logged in
$User->check_user_session();

# strip tags
$_POST = $User->strip_input_tags ($_POST);

# fetch all traps
$traps = $Trap->get_new_traps ($_POST['id']);

# false - no new items
if ($traps!==false) {

    # set fields
    $tfields = array(   "id"=>"",
                        "hostname"=>"Hostname",
                        "ip"=>"IP address",
                        "date"=>"Date",
                        "message"=>"Message",
                        "severity"=>"Severity",
                        "content"=>"Content"
                        );
    $Table_print->set_snmp_table_fields ($tfields);

    # Print new items
    $Table_print->print_snmp_table ($traps, false, false);

}
else {
    print "False";
}
?>