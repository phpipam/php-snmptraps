<?php

/**
 *
 * ftch trap by id
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
$Table_print = new Table_print ();
$Modal      = new Modal ();

# verify that user is logged in
$User->check_user_session();

# fetch item
$item = $Trap->fetch_snmp_trap ($_GET['id']);

# does it exist ?
if ($item!==false) {

    # process oid
    $Trap->process_oid ($item->oid);
    $item->trap_description = $Trap->trap_description;

    # set fields
    $tfields = array(   "id"=>"ID",
                        "hostname"=>"Hostname",
                        "ip"=>"IP address",
                        "date"=>"Date",
                        "message"=>"Message",
                        "oid"=>"OID",
                        "severity"=>"Severity",
                        "content"=>"Content",
                        "raw"=>"Raw trap",
                        "trap_description"=>"Trap description",
                        "actions"=>"Actions"
                        );
    $Table_print->set_snmp_table_fields ($tfields);

    # check for error
    if ($item===false) {
        $title = "Error";
        $btn_text = "";
        $content = $Result->show ("danger", _('Invalid item'), false, false, true);
    }
    # print
    else {
        $content[] = "<table class='table snmp table-noborder table-condensed table-hover'>";
        $content[] = $Table_print->print_snmp_item ($item, true);
        $content[] = "</table>";
        $content = implode("\n", $content);
    }
}
else {
    $title = "Error";
    $btn_text = "";
    $content = $Result->show ("danger", _('Invalid item'), false, false, true);
}


# print modal
$Modal->modal_print ("Details for message id $_GET[id]", $content, "", "");
?>