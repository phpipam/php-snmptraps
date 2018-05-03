<h4 class="red">Severity definitions</h4>
<br>

List of all user-defined severity definitions.
<br><br>
<?php

/**
 *
 * Fetch all traps and display them
 *
 **/

# Common class
$Common = new Database_wrapper ();

# make sure user is admin
$User->is_admin ();

// fetch all objects
$maintaneance = $Common->fetch_all_objects ("severity_definitions", "oid", true);
// table definitions
$fields_db = $Common->get_table_definition("severity_definitions");


# set fields
$tfields = array();
if($fields_db!==false) {
    foreach ($fields_db as $f) {
        // no id
        if($f->Field!=="id") {
            $tfields[] = $f->Field;
        }
    }
}
// add actions
$tfields["actions"]="actions";

// print
$Table_print->set_snmp_table_fields ($tfields);
// print add item
$Table_print->print_add_item ("app/settings/item-edit.php", "severity_definitions");
# print table
print "<table class='table snmp sorted table-noborder table-condensed table-hover' data-cookie-id-table='settings_severity'>";
$Table_print->print_table ($maintaneance, true, "app/settings/item-edit.php", "severity_definitions");
print "</table>";