<h4 class="red">Ignored messages (Exceptions)</h4>
<br>

Messages listed here will not be written to the database and Notifications will not be sent.
If either only the OID, hostname or the message is set, then traps containing this OID, hostname or message will not be processed. This allows for more granular control and better fine tuning.
<br><br>
<?php

/**
 *
 * Fetch all traps and display them
 *
 **/

# make sure user is admin
$User->is_admin ();

# Common class
$Common = new Database_wrapper ();

// fetch all objects
$maintaneance = $Common->fetch_all_objects ("exceptions", "id", false);
// table definitions
$fields_db = $Common->get_table_definition("exceptions");


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
$Table_print->print_add_item ("app/settings/item-edit.php", "exceptions");
# print table
print "<table class='table snmp sorted table-noborder table-condensed table-hover' data-cookie-id-table='settings_exceptions'>";
$Table_print->print_table ($maintaneance, true, "app/settings/item-edit.php", "exceptions");
print "</table>";