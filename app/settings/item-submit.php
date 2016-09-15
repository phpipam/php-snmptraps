<?php

# edit message - general


# functions
require('../../functions/functions.php');

# Objects
$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);
# Common class
$Common = new Database_wrapper ();

# make sure user is admin
$User->is_admin ();

# strip tags
$_POST = $User->strip_input_tags ($_POST);


# valid scripts
$scripts = array("maintaneance", "exceptions", "severity_definitions", "users");

# validate script
if (!in_array($_POST['script'], $scripts)) {
    $Result->show ("danger", 'Invalid script', true);
}

# users checkbox override
if($_POST['script']=="users" && $_POST['action']!="delete") {
    // init
    $notification_types = array();
    $notification_severities = array();

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
        elseif (strpos($k, "hostnames")!==false) {
            $hostnames[] = str_replace("hostnames-", "", $k);
            unset($_POST[$k]);
        }
    }

    // join
    $_POST['notification_types'] = implode(";", $notification_types);
    $_POST['notification_severities'] = implode(";", $notification_severities);
    $_POST['hostnames'] = implode(";", $hostnames);

    // empty fix
    if (strlen($_POST['notification_types'])==0)        { $_POST['notification_types'] = "none"; }
    if (strlen($_POST['notification_severities'])==0)   { $_POST['notification_severities'] = "none"; }
    if (strlen($_POST['hostnames'])==0)                 { $_POST['hostnames'] = "all"; }

    // password
    if($_POST['action']=="edit" && strlen($_POST['password'])==0)   { unset($_POST['password']); }
    elseif ($_POST['auth_method']=="ad")                { unset($_POST['password']); }
    elseif (strlen($_POST['password'])<8)               { $Result->show("danger", "Invalid password - 8 characters required!", true); }
    else                                                { $_POST['password'] = $User->crypt_user_pass ($_POST['password']); }

    // hostnames
    if ($_POST['role']=="administrator")                { $_POST['hostnames'] = "all"; }
}

# add
if ($_POST['action']=="add") {
    if($Common->create_object ($_POST['script'], $_POST)===true)        { $Result->show ("success", ucwords($_POST['script']).' object created', false); }
    else                                                                { $Result->show ("danger",  ucwords($_POST['script']).' object create failed', false); }
}
# delete
elseif ($_POST['action']=="delete") {
    if($Common->remove_object ($_POST['script'], $_POST['id'])===true)  { $Result->show ("success", ucwords($_POST['script']).' object removed', false); }
    else                                                                { $Result->show ("danger",  ucwords($_POST['script']).' object delete failed', false);}
}
# edit
elseif ($_POST['action']=="edit") {
    if($Common->update_object ($_POST['script'], $_POST)===true)        { $Result->show ("success", ucwords($_POST['script']).' object updated', false); }
    else                                                                { $Result->show ("danger",  ucwords($_POST['script']).' object update failed', false);}
}
# die
else {
    $Result->show ("danger", 'Invalid action', false);
}
?>