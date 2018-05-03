<?php

/**
 *
 * By severity
 *
 **/

# verify that user is logged in
$User->check_user_session();

# set limit
$Trap->reset_print_limit (30);
# set permitted hostnames
$Trap->set_permitted_hostnames ($User->hostnames);
# fetch all traps for each
$all_error_traps    = $Trap->fetch_traps (array("emergency", "alert", "critical"));

$Trap->reset_print_limit (13);
$all_warning_traps  = $Trap->fetch_traps (array("error", "warning"));
$all_info_traps     = $Trap->fetch_traps (array("notice", "informational", "debug"));
$all_unknown_traps  = $Trap->fetch_traps (array("unknown"));

# set fields
$tfields = array(   "id"=>"",
                    "hostname"=>"Hostname",
                    "ip"=>"IP address",
                    "date"=>"Date",
                    "message"=>"Message",
                    "severity"=>"Severity"
                    );
$Table_print->set_snmp_table_fields ($tfields);

# structure
print "<div class='container-fluid row' id='dashboard'>";

# critical
print "<div class='col-xs-12 col-md-6 widget-dash'>";
print "<div class='inner'>";
print "<h4><a href='severity/major/'>Emergency, Critical and Alert events</a></h4>";
print "<div class='hContent'>";
print "<table class='table snmp table-striped table-condensed table-hover'>";
if ($all_error_traps!==false)   { $Table_print->print_snmp_table ($all_error_traps); }
else                            { print "<tr><td>".$Result->show("info", "No messages found", false, false, true)."<td></tr>"; }
print "</table>";
print "</div>";
print "</div>";
print "</div>";

# warning, info
print "<div class='col-xs-12 col-md-6 widget-dash'>";
print "<div class='inner'>";
print "<h4><a href='severity/minor/'>Error and Warning events</a></h4>";
print "<div class='hContent'>";
print "<table class='table snmp table-striped table-condensed table-hover'>";
if ($all_warning_traps!==false) { $Table_print->print_snmp_table ($all_warning_traps); }
else                            { print "<tr><td>".$Result->show("info", "No messages found", false, false, true)."<td></tr>"; }
print "</table>";
print "</div>";
print "</div>";
print "</div>";

print "<div class='col-xs-12 col-md-6 widget-dash'>";
print "<div class='inner'>";
print "<h4><a href='severity/informational/'>Informational, Notice and Debug events</a></h4>";
print "<div class='hContent'>";
print "<table class='table snmp table-striped table-condensed table-hover'>";
if ($all_info_traps!==false)    { $Table_print->print_snmp_table ($all_info_traps); }
else                            { print "<tr><td>".$Result->show("info", "No messages found", false, false, true)."<td></tr>"; }
print "</table>";
print "</div>";
print "</div>";
print "</div>";

# unknown
print "<div class='col-xs-12 widget-dash'>";
print "<div class='inner'>";
print "<h4><a href='severity/unknown/'>Unknown severity events</a></h4>";
print "<div class='hContent'>";
print "<table class='table snmp table-striped table-condensed table-hover'>";
if ($all_unknown_traps!==false) { $Table_print->print_snmp_table ($all_unknown_traps); }
else                            { print "<tr><td>".$Result->show("info", "No messages found", false, false, true)."<td></tr>"; }
print "</table>";
print "</div>";
print "</div>";
print "</div>";

print "</div>";