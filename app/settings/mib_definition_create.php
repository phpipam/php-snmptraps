<?php

# edit message - general


# functions
require('../../functions/functions.php');

$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);
$Table_print= new Table_print ();
$Modal      = new Modal ();

# make sure user is admin
$User->is_admin ();

# SNMP class to read file
$Snmp_read_MIB = new Snmp_read_MIB;
$Snmp_read_MIB->set_mib_direcotry ();


// set items
$mib = new StdClass;
$mib->file = $m;

// process mib
$tmp = $Snmp_read_MIB->read_mib_file ($_GET['mibfile']);

// save
$mib->notification_objects_full = $Snmp_read_MIB->detect_mib_objects ();
// save oid
$mib->oid = $Snmp_read_MIB->detect_mib_oid();

// title
$title = "Create new severity definition";

// content
$html[] = " <form id='modal-form'>";
$html[] = " <table class='table table-striped table-noborder table-condensed'>";

// select notification
$html[] = " <tr>";
$html[] = " <td>Select notification:</td>";
$html[] = " <td>";
$html[] = " <select name='oid' class='form-control input-sm input-w-auto'>";
foreach ($mib->notification_objects_full as $o) {
$html[] = " <option value='$mib->oid::$o'>$o</option>";
}
$html[] = " </select>";
$html[] = " </td>";
$html[] = " </tr>";
// severity
$html[] = " <tr>";
$html[] = " <td>Select severity:</td>";
$html[] = " <td>";
$html[] = " <select name='severity' class='form-control input-sm input-w-auto'>";
foreach ($Snmp_read_MIB->define_severities() as $s) {
$html[] = " <option value='$s'>$s</option>";
}
$html[] = " </select>";
$html[] = " </td>";
$html[] = " </tr>";
// comment
$html[] = " <tr>";
$html[] = " <td>Comment:</td>";
$html[] = " <td>";
$html[] = " <input type='text' name='comment' class='form-control input-sm'></input";
$html[] = " </select>";
$html[] = " </td>";
$html[] = " </tr>";


$html[] = " <input type='hidden' name='script' value='severity_definitions'>";
$html[] = " <input type='hidden' name='action' value='add'>";
$html[] = "</table>";
$html[] = " </form>";

// save content
$content = implode("\n", $html);



# print modal
$Modal->modal_print ($title, $content, "Add", "app/settings/item-submit.php");
?>