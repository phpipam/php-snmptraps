<?php

# user selfedit


# functions
require('../../functions/functions.php');

# valid scriots
$scripts = array("maintaneance", "exceptions", "severity_definitions", "users");

# Objects
$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);
$Table_print= new Table_print ();
# Common class
$Common = new Database_wrapper ();
$Modal      = new Modal ();

# check session
$User->check_user_session();

// table definitions
$fields_db = $Common->get_table_definition("users");


// content
$title = "Edit your profile";

$html[] = "<form id='modal-form'>";
$html[] = "<table class='table table-striped table-noborder table-condensed'>";

// remove password for domain users
if ($User->user->auth_method!=="local") { unset($User->user->password); }

// loop
foreach ($fields_db as $f) {
    // no id
    if(!in_array($f->Field, array("id", "role", "auth_method", "last_login", "last_activity", "username", "hostnames"))) {
        // ignore pass
        if($f->Field=="password") {
            $User->user->{$f->Field} = "";
        }

        // required
        $required = $f->{'Null'}=="NO" ? "*" : "";
        // content
        $html[] = "<tr>";
        $html[] = " <td>$f->Field <span class='alert alert-danger'>$required</span></td>";
        $html[] = " <td>".$Table_print->prepare_input_item ($f, $User->user->{$f->Field});
        if(strlen($f->Default)>0 && (strpos($f->Type, "varchar")!==false || strpos($f->Type, "int")!==false || strpos($f->Type, "time")!==false))
        $html[] = "     <span class='text-muted' style='color:#666;'>Default: ".$f->Default."</span>";
        $html[] = " </td>";
        $html[] = "</tr>";
    }
}
$html[] = "</table>";
$html[] = " </form>";

// save content
$content = implode("\n", $html);

# print modal
$Modal->modal_print ($title, $content, "Save", "app/settings/user-self-edit-submit.php");