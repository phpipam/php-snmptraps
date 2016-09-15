<?php

# functions
require('../../functions/functions.php');

# Objects
$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);

$Trap_update = new Trap_update ($Database);

# make sure user is operator
$User->is_operator (true);

# strip tags
$_GET = $User->strip_input_tags ($_GET);

/*
print "<pre>";
var_dump($_POST);
die('alert-danger');
*/

# execute
if ($_POST['action']=="define")     { $Trap_update->update_trap ($_POST['action'], $_POST); }
elseif ($_POST['action']=="delete") { $Trap_update->update_trap ($_POST['action'], $_POST); }
elseif ($_POST['action']=="ignore") { $Trap_update->update_trap ($_POST['action'], $_POST); }
else                                { $Result->show ("warning", "Not implemented yet !", false); }
?>