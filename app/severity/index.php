<?php

/**
 *
 * By severity
 *
 **/

# verify that user is logged in
$User->check_user_session();

# set limit to 10
$Trap->reset_print_limit (200);

# set severity
if($_GET['page']=="all")                { $traps = $Trap->fetch_traps ("all"); }
elseif($_GET['page']=="major")          { $traps = $Trap->fetch_traps (array("emergency", "alert", "critical")); }
elseif($_GET['page']=="minor")          { $traps = $Trap->fetch_traps (array("error", "warning")); }
elseif($_GET['page']=="informational")  { $traps = $Trap->fetch_traps (array("notice", "informational", "debug")); }
elseif($_GET['page']=="unknown")        { $traps = $Trap->fetch_traps (array("unknown")); }


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

# structure
print "<div class='container-fluid row'>";

# critical
print "<h4>".ucwords($_GET['page'])." severities</h4><hr>";
print "<table class='table snmp sorted table-noborder table-condensed table-hover'>";
$Table_print->print_snmp_table ($traps);
print "</table>";

print "</div>";


?>