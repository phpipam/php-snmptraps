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
$Snmp_read_MIB->set_mib_directory ($mib_directory);

# trap
$Trap_read = new Trap_read ($Database);

# strip tags
$_GET = $User->strip_input_tags ($_GET);


// set items
$mib = new StdClass;
$mib->file = $m;

// process mib
$tmp = $Snmp_read_MIB->read_mib_file ($_GET['mibfile']);

// save
$mib->notification_objects_full = $Snmp_read_MIB->detect_mib_objects ();
// save oid
$mib->oid = $Snmp_read_MIB->detect_mib_oid ();

// title
$title = "Create new severity definitions from MIB";

// content
$html[] = " <form id='modal-form'>";
$html[] = " <table class='table table-striped table-noborder table-condensed'>";

// headers
$html[] = " <tr>";
$html[] = " <th>Notification</th>";
$html[] = " <th>Severity</th>";
$html[] = " <th>Old severity</th>";
$html[] = " <th>Comment</th>";
$html[] = " </tr>";

$html[] = " <tr>";
$html[] = " <th colspan='4'><hr></th>";
$html[] = " </tr>";

// content
foreach ($mib->notification_objects_full as $k=>$o) {

$Trap_read->process_oid ("$mib->oid::$o");

// check if it exists and set
$item = $Trap_read->fetch_severity_definition ("$mib->oid::$o");
if($item===false)   { $oldseverity = "new item"; $item = new StdClass(); $item->comment = str_replace(array("'", "\""), "", $Trap_read->trap_description); }
else                { $oldseverity = $item->severity; }

$html[] = " <tr>";
// notification
$html[] = " <td>";
$html[] = " $o";
$html[] = " <input type='hidden' name='oid-$k' value='$mib->oid::$o'>";
$html[] = " </td>";
// severity
$html[] = " <td>";
$html[] = " <select class='form-control input-sm' name='severity-$k'>";
foreach ($Snmp_read_MIB->define_severities() as $s) {
# selected
$selected = $oldseverity==$s ? "selected" : "";
$html[] = " <option value='$s' $selected>$s</option>";
}
$html[] = " </select>";
$html[] = " </td>";
// old severity
$html[] = " <td class='text-center'>$oldseverity</td>";
// comment
$html[] = " <td>";
$html[] = " <input type='text' name='comment-$k' class='form-control input-sm' value='$item->comment'>";
$html[] = " </select>";

}
// end
$html[] = "</table>";
$html[] = " </form>";

// save content
$content = implode("\n", $html);



# print modal
$Modal->modal_print ($title, $content, "Create", "app/settings/mib-definitions-save.php");
?>