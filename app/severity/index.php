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
print "<h4>".ucwords(htmlspecialchars($_GET['page'], ENT_QUOTES, 'UTF-8'))." severities</h4><hr>";
// table
$page_escaped = htmlspecialchars($_GET['page'], ENT_QUOTES, 'UTF-8');
print "<table class='table snmp sorted sorted-ajax table-noborder table-condensed table-hover' data-url='".BASE."app/json/?app=severity&type={$page_escaped}'>";
// headers only
$Table_print->print_snmp_table ($traps, true, false, false, true);
// data
print "</table>";

print "</div>";