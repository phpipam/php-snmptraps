<?php

/**
 *
 * Fetch all traps and display them
 *
 **/

# verify that user is logged in
$User->check_user_session();

# strip tags
$_POST = $User->strip_input_tags ($_POST);

# error flag
$error = false;

# results
print "<h4>Search results:</h4><hr>";

# checks

// time
if (strlen($_POST['start_date'])>0) {
    // fake stop if not set
    if (strlen($_POST['stop_date'])==0)    { $_POST['stop_date'] = date("Y-m-d H:i:s"); }

    // validate dates
    if (date('Y-m-d H:i:s', strtotime($_POST['start_date'])) !== $_POST['start_date']) {
        $Result->show("danger", "Error: Invalid start date", false);
        $error = true;
    }
    // validate dates
    if (date('Y-m-d H:i:s', strtotime($_POST['stop_date'])) !== $_POST['stop_date']) {
        $Result->show("danger", "Error: Invalid stop date", false);
        $error = true;
    }
    // stop must be bigger than start
    if (strtotime($_POST['start_date']) > strtotime($_POST['stop_date']))  {
        $Result->show("danger", "Error: Invalid date span", false);
        $error = true;
    }
}
else {
    unset($_POST['stop_date'], $_POST['start_date']);
}

// hostname check
if ($_POST['hostname']!=="all") {
    if (!array_key_exists($_POST['hostname'], $unique_hosts)) {
        $Result->show("danger", "Error: Invalid hostname", false);
        $error = true;
    }
}
else {
    unset($_POST['hostname']);
}

// severity check
if ($_POST['severity']!=="all") {
    if(!in_array($_POST['severity'], $Trap->severities)) {
        $Result->show("danger", "Error: Invalid severity", false);
        $error = true;
    }
}
else {
    unset($_POST['severity']);
}

// message
if(strlen($_POST['message'])==0) {
    unset($_POST['message']);
}

# if no errors do search
if ($error!==true) {
    // set limit to 10
    $Trap->reset_print_limit ($_POST['limit']);

    # search
    $traps = $Trap->search_traps ($_POST);

    // set fields
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
    print "<table class='table snmp sorted table-noborder table-condensed table-hover' data-cookie-id-table='search'>";
    $Table_print->print_snmp_table ($traps, true, true, false, true);
    print "</table>";
}