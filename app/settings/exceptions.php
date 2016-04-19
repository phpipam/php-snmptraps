<h4 class="red">Ignored messages (Exceptions)</h4>
<br>

Below messages will not be placed to database, and notification message for below definitions will not be sent. If only OID is set, than trap containing this OID will not be processes.
If hostname is set it will match hostname also, same goes for messages for better fine-tuning.
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
print "<table class='table snmp sorted table-noborder table-condensed table-hover'>";
$Table_print->print_table ($maintaneance, true, "app/settings/item-edit.php", "exceptions");
print "</table>";

?>