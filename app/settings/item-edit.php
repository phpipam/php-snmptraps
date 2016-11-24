<?php

# edit message - general


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


# make sure user is admin
$User->is_admin ();

# strip tags
$_GET = $User->strip_input_tags ($_GET);


// validate script
if (!in_array($_GET['script'], $scripts)) {
    $title = "Error item ".$_GET['action'];
    $btn_text = "";
    $content = $Result->show("danger", "Invalid script", false, false, true);
}
else {
    // fetch object
    if($_GET['action']!=="add") {
        $item = $Common->fetch_object ($_GET['script'], "id", $_GET['id']);
    }
    else {
        $item = true;
    }


    // table definitions
    $fields_db = $Common->get_table_definition($_GET['script']);

    // distinct hosts
    $uniq = $Common->fetch_unique_items ("traps", "hostname");
    if ($uniq!==false) {
        foreach ($uniq as $u) {
            $unique_hosts[] = $u->hostname;
        }
    }

    // remove unneeded
    if ($_GET['script']=="users") {
        foreach ($fields_db as $k=>$f) {
            if ($f->Field=="last_login" || $f->Field=="last_activity") {
                unset($fields_db[$k]);
            }
        }
    }

    # validate
    if ($item!==false) {
        // set title
        $title = ucwords($_GET['action'])." ".$_GET['script']." item";
        // footer text
        $btn_text = ucwords($_GET['action']);


        // add
        if ($_GET['action']=="add") {
            // content
            $html[] = "Add new item to $_GET[script]:<hr><br>";

            $html[] = "<form id='modal-form'>";
            $html[] = "<table class='table table-striped table-noborder table-condensed'>";
            // loop
            if ($fields_db!==false) {
                foreach ($fields_db as $f) {
                    // no id
                    if ($f->Field!=="id") {

                        // fake hostname
                        if ($f->Field=="hostname") {
                            $f->Type="set('".implode("','", $unique_hosts)."')";
                        }

                        // required
                        $required = $f->{'Null'}=="NO" ? "*" : "";
                        // content
                        $html[] = "<tr>";
                        $html[] = " <td>$f->Field <span class='alert alert-danger'>$required</span></td>";
                        $html[] = " <td>".$Table_print->prepare_input_item ($f, false)."</td>";
                        $html[] = "</tr>";
                    }
                }
            }
            $html[] = " <input type='hidden' name='script' value='".$_GET['script']."'>";
            $html[] = " <input type='hidden' name='action' value='".$_GET['action']."'>";
            $html[] = "</table>";
            $html[] = " </form>";

            // save content
            $content = implode("\n", $html);
        }

        // edit
        elseif ($_GET['action']=="edit") {
            // content
            $html[] = "Edit $_GET[script] item:<hr><br>";

            $html[] = "<form id='modal-form'>";
            $html[] = "<table class='table table-striped table-noborder table-condensed'>";
            // loop
            foreach ($fields_db as $f) {
                // no id
                if ($f->Field!=="id") {

                    // fake hostname
                    if ($f->Field=="hostname") {
                        $f->Type="set('".implode("','", $unique_hosts)."')";
                    }
                    // ignore pass
                    if($f->Field=="password") {
                        $item->{$f->Field} = "";
                    }

                    // required
                    $required = $f->{'Null'}=="NO" ? "*" : "";
                    // content
                    $html[] = "<tr>";
                    $html[] = " <td>$f->Field <span class='alert alert-danger'>$required</span></td>";
                    $html[] = " <td>".$Table_print->prepare_input_item ($f, $item->{$f->Field}, $unique_hosts)."</td>";
                    $html[] = "</tr>";
                }
            }
            $html[] = " <input type='hidden' name='id' value='".$_GET['id']."'>";
            $html[] = " <input type='hidden' name='script' value='".$_GET['script']."'>";
            $html[] = " <input type='hidden' name='action' value='".$_GET['action']."'>";
            $html[] = "</table>";
            $html[] = " </form>";

            // save content
            $content = implode("\n", $html);
        }

        // delete
        elseif ($_GET['action']=="delete") {
            // content
            $html[] = "Remove the following $_GET[script] item:<hr><br>";

            $html[] = "<table class='table table-striped table-noborder table-condensed'>";
            // loop
            foreach ($fields_db as $f) {
                // no id
                if ($f->Field!=="id") {
                    $html[] = "<tr>";
                    $html[] = " <td>$f->Field</td>";
                    $html[] = " <td>".$item->{$f->Field}."</td>";
                    $html[] = "</tr>";
                }
            }
            $html[] = "</table>";

            // fake form
            $html[] = " <form id='modal-form'>";
            $html[] = " <input type='hidden' name='id' value='".$item->id."'>";
            $html[] = " <input type='hidden' name='script' value='".$_GET['script']."'>";
            $html[] = " <input type='hidden' name='action' value='".$_GET['action']."'>";
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


}

# print modal
$Modal->modal_print ($title, $content, $btn_text, "app/settings/item-submit.php");
?>