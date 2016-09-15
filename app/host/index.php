<?php

/**
 *
 * Fetch all traps and display them
 *
 **/

# verify that user is logged in
$User->check_user_session();

# strip tags
$_GET = $User->strip_input_tags ($_GET);

# set limit to 10
$Trap->reset_print_limit (200);

# fetch all traps
if(isset($_GET['page'])) {
    $all_traps = $Trap->fetch_traps_host ($_GET['page']);
}

# fetch all unique hosts
$unique_hosts = $Trap->fetch_unique_hosts ();

# print badges if page set
if (isset($_GET['page'])) {
    # print title
    print "<h4>Traps for host $_GET[page]</h4>";

    # badges
    print "<div class='container-fluid text-right row'>";
    print "<div class='col-lg-6 col-sm-12'></div>";
    print "<div class='col-lg-6 col-sm-12 hosts-wrapper'>";
    foreach ($unique_hosts as $h) {
        // ignore unknown
        if ($h->hostname!="<UNKNOWN>") {
            // active
            $active = $h->hostname==$_GET['page'] ? "badge-active" : "";
            // print
            print "<span class='badge badge1 badge5 marged $active'><a href='host/$h->hostname/'>$h->hostname</a></span>";
        }
    }
    print "</div>";
    print "</div>";
    print "<hr><br>";


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
    print "<table class='table snmp sorted table-noborder table-condensed table-hover'>";
    $Table_print->print_snmp_table ($all_traps, true, true, false, true);
    print "</table>";
}
else {
    # print title
    print "<h4>Select host</h4>";

    print "<div class='container-fluid text-left row'>";
    print "<div class='col-md-6 col-sm-12'>";
    foreach ($unique_hosts as $h) {
        // ignore unknown
        if ($h->hostname!="<UNKNOWN>") {
            // active
            $active = $h->hostname==$_GET['page'] ? "badge-active" : "";
            // print
            print "<span class='badge badge1 badge5 marged $active'><a href='host/$h->hostname/'>$h->hostname</a></span>";
        }
    }
    print "</div>";
}
?>