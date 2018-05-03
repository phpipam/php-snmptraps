<?php
# make sure user is admin
$User->is_admin ();

// items
$items = array(
    "general"              => "General settings",
    "severity_definitions" => "Severity definitions",
    "exceptions"           => "Ignored messages",
    "maintaneance"         => "Maintenance",
    "mibs"                 => "MIB files",
    "users"                => "User management"
);
// default
if(!isset($_GET['page']))   { $_GET['page'] = "severity_definitions"; }

# strip tags
$_GET = $User->strip_input_tags ($_GET);
?>


<h4>Settings</h4>
<hr style="margin-bottom: 0px;">


<div class="container-fluid">
<div class="row">

    <!-- menu -->
    <div class="col-xs-12 col-sm-3 col-md-2" style="padding-left: 0px;">
        <ul class="settings-nav">
            <?php
            foreach ($items as $k=>$i) {
                // active
                $active = $k==$_GET['page'] ? "active" : "";
                // print
                print "<li class='$active'>";
                print " <a href='settings/$k/'>$i</a>";
                print "</li>";
            }
            ?>
        </ul>
    </div>

    <!-- content -->
    <div class="col-xs-12 col-sm-9 col-md-10">
    <div class="container-fluid" style="padding: 10px;">
        <?php
        // open settings
        if (isset($_GET['page'])) {
            // exists
            if (file_exists(dirname(__FILE__)."/".$_GET['page'].".php") && array_key_exists($_GET['page'], $items)) {
                include(dirname(__FILE__)."/".$_GET['page'].".php");
            }
            // not
            else {
                $Result->show("danger", "Error: Invalid page!", false);
            }
        }
        else {
            print "Select settings on side menu.";
        }
        ?>
    </div>
    </div>

</div>
</div>