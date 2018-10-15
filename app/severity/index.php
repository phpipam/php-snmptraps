<?php

/**
 *
 * By severity
 *
 **/

# verify that user is logged in
$User->check_user_session();

# set permitted hostnames
$Trap->set_permitted_hostnames ($User->hostnames);
$Trap->reset_print_limit  (1);
# strip tags
$_GET = $User->strip_input_tags ($_GET);

# headers
$traps = $Trap->fetch_traps ("all");

# set fields
$tfields = array(
					"id"       => "",
					"hostname" => "Hostname",
					"ip"       => "IP address",
					"date"     => "Date",
					"message"  => "Message",
					"severity" => "Severity",
					"content"  => "Content"
                );
$Table_print->set_snmp_table_fields ($tfields);

// structure
print "<div class='container-fluid row'>";
// title
print "<h4>".ucwords($_GET['page'])." severities</h4><hr>";
// table
// print "<table class='table snmp sorted sorted-ajax table-noborder table-condensed table-hover' data-cookie-id-table='severity' data-url='".BASE."app/json/?app=severity&type={$_GET[page]}'>";
print "<table class='table snmp sorted sorted-ajax table-noborder table-condensed table-hover' data-url='".BASE."app/json/?app=severity&type={$_GET[page]}'>";
// headers only
$Table_print->print_snmp_table ($traps, true, false, false, true);
// data
print "</table>";

print "</div>";