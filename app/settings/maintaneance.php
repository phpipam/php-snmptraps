<h4 class="red">Maintenance periods for hosts</h4>
<br>

Here you can set maintenance periods for specific hosts. Notifications will be disabled for the duration of Maintenance period for such hosts. Notifications will still be received and written to the database.
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
$maintaneance = $Common->fetch_all_objects ("maintaneance", "start", false);
// table definitions
$fields_db = $Common->get_table_definition("maintaneance");


# set fields
$tfields = array();
if($fields_db!==false) {
    foreach ($fields_db as $f) {
        // no id
        if($f->Field!=="id") {
            $tfields[] = $f->Field;
        }
    }
    $tfields[] = "valid";
}
// add validity
if ($maintaneance!==false) {
    foreach ($maintaneance as $k=>$m) {
        $maintaneance[$k]->valid = strtotime(date("Y-m-d H:i:s")) > strtotime($m->stop) ? "<p class='badge alert alert-danger' style='padding:0px'>No</p>" : "<p class='badge alert alert-success' style='padding:0px'>Yes</p>";
    }
}
// add actions
$tfields["actions"]="actions";

// print
$Table_print->set_snmp_table_fields ($tfields);
// print add item
$Table_print->print_add_item ("app/settings/item-edit.php", "maintaneance");
# print table
print "<table class='table snmp sorted table-noborder table-condensed table-hover' data-cookie-id-table='settings_maintaneance'>";
$Table_print->print_table ($maintaneance, true, "app/settings/item-edit.php", "maintaneance");
print "</table>";