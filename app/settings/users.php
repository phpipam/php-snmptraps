<h4 class="red">User management</h4>
<br>

User management. 'Administrator' role can define severities, manage users etc, 'User' can only view traps and edit own notification options.
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
$users = $Common->fetch_all_objects ("users", "username", true);
// table definitions
$fields_db = $Common->get_table_definition("users");


# set fields
$tfields = array();
if($fields_db!==false) {
    foreach ($fields_db as $f) {
        // no id
        if($f->Field!=="id" && $f->Field!=="password" && $f->Field!=="last_activity" && $f->Field!=="reload_page") {
            $tfields[] = $f->Field;
        }
    }
}
// add actions
$tfields["actions"]="actions";

// print
$Table_print->set_snmp_table_fields ($tfields);
// print add item
$Table_print->print_add_item ("app/settings/item-edit.php", "users");
# print table
print "<table class='table snmp sorted table-noborder table-hover' data-cookie-id-table='settings_users'>";
$Table_print->print_table ($users, true, "app/settings/item-edit.php", "users");
print "</table>";