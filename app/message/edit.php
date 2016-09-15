<?php

# edit message - general


# functions
require('../../functions/functions.php');

# Objects
$Database   = new Database_PDO;
$Result     = new Result;
$User       = new User ($Database);
# snmp class
$Trap       = new Trap_read ($Database);
$Modal      = new Modal ();
$Common     = new Database_wrapper ();

# make sure user is operator
$User->is_operator (true, true);

# strip tags
$_GET = $User->strip_input_tags ($_GET);

# fetch item
$item = $Trap->fetch_snmp_trap ($_GET['id']);


# validate
if ($item!==false) {
    // set title
    $title = ucwords($_GET['action'])." message";
    // footer text
    $btn_text = ucwords($_GET['action']);


    // add exception
    if ($_GET['action']=="ignore") {

        // distinct hosts
        $uniq = $Common->fetch_unique_items ("traps", "hostname");
        if ($uniq!==false) {
            foreach ($uniq as $u) {
                $unique_hosts[] = $u->hostname;
            }
            // add all
            $unique_hosts = array_filter(array_merge(array("all"=>"all"), $unique_hosts));
        }

        // title
        $title = "Ignore message";

        // content
        $html[] = "Here you define for some OID to be excluded from processing. All existing records with this OID will be deleted.<br><br>";

        $html[] = "<form id='modal-form'>";
        $html[] = "<table class='table table-noborder table-condensed'>";
        $html[] = "<tr>";
        $html[] = " <td style='width:160px;'>OID:</td><td><strong>".$item->oid."</strong></td>";
        $html[] = "</tr>";
        $html[] = " <td>Hostname:</td>";
        $html[] = " <td>";
        $html[] = " <select name='hostname' class='form-control input-w-auto input-sm'>";
        // loop
        foreach ($unique_hosts as $h) {
            $selected = $s == $item->hostname ? "selected" : "";
            // print
            $html[] = "<option value='$h' $selected>$h</option>";
        }
        $html[] = " </select>";
        $html[] = " <input type='hidden' name='oid' value='".$item->oid."'>";
        $html[] = " <input type='hidden' name='action' value='".$_GET['action']."'>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Content</td>";
        $html[] = " <td>";
        $html[] = " <input type='text' class='form-control input-sm' name='content' value='".$item->content."'>";
        $html[] = " <span class='text-muted'>* Leave blank to match full OID</span>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Comment</td>";
        $html[] = " <td>";
        $html[] = " <input type='text' class='form-control input-sm' name='comment'>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "</table>";
        $html[] = " </form>";

        // save content
        $content = implode("\n", $html);
    }
    // define
    elseif ($_GET['action']=="define") {
        // title
        $title = "Define severity for message";

        // content
        $html[] = "Here you can change severities for OID messages. All existing records with this OID will be updated.<br><br>";

        $html[] = "<form id='modal-form'>";
        $html[] = "<table class='table table-noborder table-condensed'>";
        $html[] = "<tr>";
        $html[] = " <td style='width:160px;'>OID:</td><td><strong>".$item->oid."</strong></td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Current severity:</td><td><strong>".$item->severity."</strong></td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>New severity:</td>";
        $html[] = " <td>";
        $html[] = " <select name='newseverity' class='form-control input-w-auto input-sm'>";
        // loop
        foreach ($Trap->severities as $s) {
            $selected = $s == $item->severity ? "selected" : "";
            // print
            $html[] = "<option value='$s' $selected>$s</option>";
        }
        $html[] = " </select>";
        $html[] = " <input type='hidden' name='oldseverity' value='".$item->severity."'>";
        $html[] = " <input type='hidden' name='oid' value='".$item->oid."'>";
        $html[] = " <input type='hidden' name='id' value='".$item->id."'>";
        $html[] = " <input type='hidden' name='action' value='".$_GET['action']."'>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Message</td>";
        $html[] = " <td>";
        $html[] = " <input type='text' class='form-control input-sm' name='content' value='".$item->message."'>";
        $html[] = " <span class='text-muted'>* Leave blank to match full OID</span>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Comment</td>";
        $html[] = " <td>";
        $html[] = " <input type='text' class='form-control input-sm' name='comment'>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "</table>";
        $html[] = " </form>";

        // save content
        $content = implode("\n", $html);

    }
    // delete all
    elseif ($_GET['action']=="delete") {
        // title
        $title = "Delete all traps for oid";

        // content
        $html[] = "Here you can remove all traps for specific OID. All existing records with this OID will be removed. If content is defined than it will search by content also.<br><br>";

        $html[] = "<form id='modal-form'>";
        $html[] = "<table class='table table-noborder table-condensed'>";
        $html[] = "<tr>";
        $html[] = " <td style='width:160px;'>OID:</td><td><strong>".$item->oid."</strong></td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Severity:</td><td><strong>".$item->severity."</strong></td>";
        $html[] = "</tr>";
        $html[] = "<tr>";
        $html[] = " <td>Content</td>";
        $html[] = " <td>";
        $html[] = " <input type='text' class='form-control input-sm' name='content' value='".$item->content."'>";
        $html[] = " <input type='hidden' name='oid' value='".$item->oid."'>";
        $html[] = " <input type='hidden' name='action' value='".$_GET['action']."'>";
        $html[] = " </td>";
        $html[] = "</tr>";
        $html[] = "</table>";
        $html[] = " </form>";

        // save content
        $content = implode("\n", $html);
    }
    // false
    else {
        $title = "Error";
        $btn_text = "";
        $content = $Result->show ("danger", _('Invalid action'), false, false, true);
    }
}
else {
    $title = "Error";
    $btn_text = "";
    $content = $Result->show ("danger", _('Invalid item'), false, false, true);
}



# print modal
$Modal->modal_print ($title, $content, $btn_text, "app/message/edit-submit.php");
?>