<?php

/**
 *
 * ftch trap by id
 *
 **/

# verify that user is logged in
$User->check_user_session();


# fetch item
$item = $Trap->fetch_snmp_trap ($_GET['page']);

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

    # structure
    print "<div class='container '>";

    # check for error
    if ($item===false) {
        $Result->show("danger", "Invalid identifier", false);
    }
    # print
    else {
        // print
        print "<h4>Details for message id $_GET[page]</h4><hr>";
        print "<table class='table snmp table-noborder table-condensed table-hover'>";
        $Table_print->print_snmp_item ($item);
        print "</table>";
    }
    print "</div>";

}
else {
    print "<div class='container '>";
    $Result->show("danger", "Invalid ID", false);
    print "</div>";
}
?>