<h4 class="red">Mib files</h4>
<br>

Here is a list of MIBS accessible by system to load descriptions etc. You can choose to import items found in this mibs to define severities.
<br><br>
<?php

/**
 *
 * Fetch all traps and display them
 *
 **/


# SNMP class to read file
$Snmp_read_MIB = new Snmp_read_MIB;
$Snmp_read_MIB->set_mib_directory ($mib_directory);

# make sure user is admin
$User->is_admin ();

// get mibs
$mibs = $Snmp_read_MIB->read_mib_directory();

// loop and get items
if ($mibs!==false) {
foreach ($mibs as $k=>$m) {
    // new array object
    $mibs_processed[$k] = new StdClass;
    $mibs_processed[$k]->file = $m;

    // process mib
    $tmp = $Snmp_read_MIB->read_mib_file ($Snmp_read_MIB->mib_directory.$m);

    // save notification objects
    $mibs_processed[$k]->notification_objects_full = $Snmp_read_MIB->detect_mib_objects ();
    $mibs_processed[$k]->notification_objects = is_array($mibs_processed[$k]->notification_objects_full) ? implode("<br>", $mibs_processed[$k]->notification_objects_full) : "/";

    // save oid
    $mibs_processed[$k]->oid = $Snmp_read_MIB->detect_mib_oid();

    // actions
    if ($mibs_processed[$k]->notification_objects != "/") {
         $mibs_processed[$k]->create_definition = "<a class='btn btn-success btn-xs pull-right load-modal' href='app/settings/mib_definition_create.php?mibfile=$Snmp_read_MIB->mib_directory$m'><i class='fa fa-plus'></i></a>";
    }
    else {
        $mibs_processed[$k]->create_definition = "";
    }
}
}

# set fields
$tfields = array(   "file"=>"file",
                    "oid"=>"oid",
                    "traps"=>"notification_objects",
                    "create_definition"=>"create_definition"
                    );
$Table_print->set_snmp_table_fields ($tfields);


/*
print "<pre>";
var_dump($mibs_processed);
*/

// print snmp location info
$Result->show("info", "MIB directory: ".$Snmp_read_MIB->mib_directory, false);
# print table
print "<table class='table snmp sorted table-noborder table-condensed table-hover' data-cookie-id-table='settings_mib'>";
$Table_print->print_table ($mibs_processed, true, "", "");
print "</table>";