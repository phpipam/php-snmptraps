<?php

/**
 *
 * Fetch all traps and display them
 *
 **/

# verify that user is logged in
$User->check_user_session();

# set limit to 10
$Trap->reset_print_limit (200);

# strip tags
$_GET = $User->strip_input_tags ($_GET);

# decode
$_GET['page'] = base64_decode($_GET['page']);

# fetch all traps
if(isset($_GET['page'])) {
    $all_traps = $Trap->fetch_traps_message ($_GET['page']);

    # get first item and output description
    if($all_traps!==false) {
        $msg_description = $Trap->fetch_snmp_trap ($all_traps[0]->id);;

        # process oid
        if ($msg_description!==false) {
            $Trap->process_oid ($msg_description->oid);
            // set additional items
            $msg_description->trap_description  = strlen($Trap->trap_description)>0 ? str_replace("\n", "", $Trap->trap_description) : "/";
            $msg_description->trap_objects      = is_array($Trap->trap_objects) ? " - ".implode("<br> - ", $Trap->trap_objects) : "/";
            $msg_description->mib_file_name     = strlen($Trap->mib_file_name)>0 ? $Trap->mib_file_name : "/";

            # set fields
            $tfields = array(   "message"=>"Message",
                                "oid"=>"OID",
                                "severity"=>"Severity",
                                "trap_objects"=>"Trap objects",
                                "trap_description"=>"Trap description",
                                "mib_file_name"=>"MIB file",
                                "actions"=>"Actions"
                                );
            $Table_print->set_snmp_table_fields ($tfields);

            # structure
            print "<h4>Details for message <strong>$_GET[page]</strong></h4><hr>";
            print "<div class='container-fluid message-wrapper' style='padding:10px;'>";

            // print
            print "<table class='table snmp table-noborder table-auto table-condense1d table-hover table-striped'>";
            $Table_print->print_snmp_item ($msg_description);
            print "</table>";

            print "</div>";
        }
    }
}

# print title
print "<h4>Traps for message <strong>$_GET[page]</strong></h4><hr>";

// if queried
if (isset($_GET['page'])) {
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


    # print table
    print "<table class='table snmp sorted table-noborder table-condensed table-hover' data-cookie-id-table='message'>";
    $Table_print->print_snmp_table ($all_traps, true, true, false, true);
    print "</table>";
}