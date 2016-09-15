<?php

# edit message - general


# functions
require('../../functions/functions.php');

# Objects
$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);
$Trap_update= new Trap_update ($Database);
# Common class
$Common = new Database_wrapper ();

# make sure user is admin
$User->is_admin ();

# strip tags
$_POSt = $User->strip_input_tags ($_POSt);


# create array of items
$values = array();
// loop
foreach ($_POST as $k=>$p) {
    // oid
    if (strpos($k, "oid-")!==false)             { $values[substr($k, 4)]['oid'] = $p; }
    // severity
    elseif (strpos($k, "severity-")!==false)    { $values[substr($k, 9)]['newseverity'] = $p;   $values[substr($k, 9)]['oldseverity'] = "null"; }
    // severity
    elseif (strpos($k, "comment-")!==false)     { $values[substr($k, 8)]['comment'] = $p; }
}

# update
foreach ($values as $v) {
    $Trap_update->update_trap ("define", $v);
}
?>