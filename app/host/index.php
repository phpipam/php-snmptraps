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
$Trap->reset_print_limit (1);
# set permitted hostnames
$Trap->set_permitted_hostnames ($User->hostnames);

# fetch all unique hosts
$unique_hosts = $Trap->fetch_unique_hosts ();

# print badges if page set
if (isset($_GET['page'])) {
    # print title
    print "<h4>Traps for host $_GET[page]</h4>";

    # badges
    print "<div class='container-fluid text-left row'>";
    print "<div class='col-lg-6 col-sm-12'></div>";
    print "<div class='col-lg-6 col-sm-12 hosts-wrapper'>";
    if(isset($unique_hosts)) {
    foreach ($unique_hosts as $h) {
        // ignore unknown
        if ($h->hostname!="<UNKNOWN>") {
            // active
            $active = $h->hostname==$_GET['page'] ? "badge-active" : "";
            // print
            print "<span class='badge badge1 badge5 merged $active'><a href='host/$h->hostname/'>$h->hostname</a></span>";
        }
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

    // table
    print "<div class='container-fluid row' style='margin-bottom:20px;'>";
    print "<table class='table snmp sorted sorted-ajax table-noborder table-condensed table-hover' data-url='".BASE."app/json/?app=host&type={$_GET[page]}'>";
    // headers only
    $Table_print->print_snmp_table ($traps, true, false, false, true);
    // data
    print "</table>";
    print "</div>";

    print "</div>";

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