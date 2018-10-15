<?php

/**
 *
 *  JSON loading of table data for pagination etc.
 *
 *
 * Request example:
 * 	http://server/snmptraps/app/json/?app=severity&type=major&order=asc&offset=0&limit=50&search=searchstring
 *
 */

/* site functions */
require('../../functions/functions.php');

# database object
$Database = new Database_PDO;
$Result   = new Result;
$User     = new User ($Database);

// verify that user is logged in
$User->check_user_session();

// init classes
$Trap        = new Trap_read ($Database);
$Table_print = new Table_print ();

// set permitted hostnames
$Trap->set_permitted_hostnames ($User->hostnames);

// strip tags
$_GET = $User->strip_input_tags ($_GET);

// set print parameters
$Trap->reset_print_limit  ($_GET['limit']);
$Trap->reset_print_offset ($_GET['offset']);
$Trap->reset_print_order  ($_GET['order']);
$Trap->set_print_filter   (@$_GET['search']);

// result defaults
$result_final = array (
                       "total" => 0,
                       "rows"  => array ()
                       );

/**
 * Severity
 */
if($_GET['app']=="severity") {
	# set severity
	if($_GET['type']=="all")                { $result = $Trap->fetch_traps ("all"); }
	elseif($_GET['type']=="major")          { $result = $Trap->fetch_traps (array("emergency", "alert", "critical")); }
	elseif($_GET['type']=="minor")          { $result = $Trap->fetch_traps (array("error", "warning")); }
	elseif($_GET['type']=="informational")  { $result = $Trap->fetch_traps (array("notice", "informational", "debug")); }
	elseif($_GET['type']=="unknown")        { $result = $Trap->fetch_traps (array("unknown")); }
}
/**
 * Host
 */
elseif($_GET['app']=="host") {
	$result = $Trap->fetch_traps_host ($_GET['type']);
}
else {

}

// found rows
$result_final['total'] = $Trap->found_rows->cnt;

// process result
$m=0;
if($result!==false) {
	foreach ($result as $r) {
		// append
		$r->full_screen = "";
		$r->actions = $r->id;
		$r->{"classes"} = $Table_print->set_severity_class ($r->severity, false)." tooltip2";

		// reformat
		$result_final['rows'][] = $Table_print->format_snmp_table_content ($r, true);
		$m++;
	}
}

# return result
print_r(json_encode($result_final));