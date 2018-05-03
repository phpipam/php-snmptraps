<?php

# user selfedit submit


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

// init
$notification_types = array();
$notification_severities = array();

# strip tags
$_POST = $User->strip_input_tags ($_POST);

// loop
foreach ($_POST as $k=>$p) {
    if (strpos($k, "notification_types")!==false) {
        $notification_types[] = str_replace("notification_types-", "", $k);
        unset($_POST[$k]);
    }
    elseif (strpos($k, "notification_severities")!==false) {
        $notification_severities[] = str_replace("notification_severities-", "", $k);
        unset($_POST[$k]);
    }
}

// join
$_POST['notification_types'] = implode(";", $notification_types);
$_POST['notification_severities'] = implode(";", $notification_severities);

// empty fix
if (strlen($_POST['notification_types'])==0)        { $_POST['notification_types'] = "none"; }
if (strlen($_POST['notification_severities'])==0)   { $_POST['notification_severities'] = "none"; }

// password
if(strlen($_POST['password'])==0)                   { unset($_POST['password']); }
elseif (strlen($_POST['password'])<8)               { $Result->show("danger", "Invalid password - 8 characters required!", true); }
else                                                { $_POST['password'] = $User->crypt_user_pass ($_POST['password']); }

// no role change
if(isset($_POST['id']))                             { unset($_POST['id']); }
if(isset($_POST['role']))                           { unset($_POST['role']); }
if(isset($_POST['auth_method']))                    { unset($_POST['auth_method']); }
if(isset($_POST['last_login']))                     { unset($_POST['last_login']); }
if(isset($_POST['last_activity']))                  { unset($_POST['last_activity']); }
if(isset($_POST['username']))                       { unset($_POST['username']); }
if(isset($_POST['hostnames']))                      { unset($_POST['hostnames']); }

// add action
$_POST['action'] = "edit";
$_POST['id'] = $User->user->id;

// execute
if($Common->update_object ("users", $_POST)===true)        { $Result->show ("success", 'Profile updated', false); }
else                                                       { $Result->show ("danger",  'Failed to update profile', false);}