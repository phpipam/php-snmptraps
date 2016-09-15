<?php

/**
 *
 * Fetch all traps and display them
 *
 **/

# verify that user is logged in
$User->check_user_session();
# set permitted hostnames
$Trap->set_permitted_hostnames ($User->hostnames);

# fetch all unique hosts
$unique_hosts = $Trap->fetch_unique_hosts ();
if($unique_hosts!==false) {
    foreach ($unique_hosts as $h) {
        $out[$h->hostname] = $h;
    }
    $unique_hosts = $out;               // needed for result check
}

# strip tags
if(isset($_POST))
$_POST = $User->strip_input_tags ($_POST);

?>

<h4>Search messages</h4><hr>


<div class="container-fluid search-wrapper" style="padding: 10px;">
<form name="search" id="search" method="post">
<table class="table table-condensed table-noborder table-auto">

<tr>
    <td>Hostname</td>
    <td>
        <select name="hostname" class="form-control input-sm input-w-auto">
            <option value="all" <?php if($_POST['hostname']=="all") { print "selected"; } ?>>All</option>
            <?php
            foreach ($unique_hosts as $h) {
                // ignore unknown
                if ($h->hostname!="<UNKNOWN>") {
                    // active
                    $selected = $h->hostname==$_POST['hostname'] ? "selected" : "";
                    // print
                    print "<option value='$h->hostname' $selected>$h->hostname</option>";
                }
            }

            ?>
        </select>
    </td>
    <td><span class='muted'>Select hostname</span></td>
</tr>


<tr>
    <td>Severity</td>
    <td>
        <select name="severity" class="form-control input-sm input-w-auto">
            <option value="all" <?php if($_POST['severity']=="all") { print "selected"; } ?>>All</option>
            <?php
            // loop
            foreach ($Trap->severities as $s) {
                $selected = $s == $_POST['severity'] ? "selected" : "";
                // print
                print "<option value='$s' $selected>$s</option>";
            }
            ?>
        </select>
    </td>
    <td><span class='muted'>Select severity</span></td>
</tr>

<tr>
    <td>From time</td>
    <td>
        <input type="text" class="form-control input-sm datetimepicker" data-format="yyyy-MM-dd" style='width:150px;' name="start_date" placeholder="<?php print date("Y-m-d H:i:s"); ?>" value='<?php if(isset($_POST['start_date'])) { print $_POST['start_date']; } ?>'>
    </td>
    <td><span class='muted'>Select Start date</span></td>
</tr>

<tr>
    <td>To time</td>
    <td>
        <input type="text" class="form-control input-sm datetimepicker" data-format="yyyy-MM-dd" style='width:150px;' name="stop_date" placeholder="<?php print date("Y-m-d H:i:s", strtotime("1 day ago")); ?>" value='<?php if(isset($_POST['stop_date'])) { print $_POST['stop_date']; } ?>'>
    </td>
    <td><span class='muted'>Select Start date</span></td>
</tr>

<tr>
    <td>Message</td>
    <td>
        <input type="text" name="message" class="form-control input-sm" style='width:250px;' placeholder="Enter messages to search for" value="<?php if(isset($_POST['message'])) { print $_POST['message']; } ?>">
    </td>
    <td><span class='muted'>Enter message for search</span></td>
</tr>

<tr>
    <td>Order</td>
    <td>
        <select name="order" class="form-control input-sm input-w-auto">
            <option value="desc" <?php if($_POST['order']=="desc") { print "selected"; } ?>>Descending</option>
            <option value="asc"  <?php if($_POST['order']=="asc") { print "selected"; } ?>>Ascending</option>
        </select>
    </td>
    <td><span class='muted'>Descending shows newest first</span></td>
</tr>

<tr>
    <td>Limit</td>
    <td>
        <select name="limit" class="form-control input-sm input-w-auto">
            <?php
            $limits = array(500, 250, 100, 50);
            // loop
            foreach ($limits as $s) {
                $selected = $s == $_POST['limit'] ? "selected" : "";
                // print
                print "<option value='$s' $selected>$s</option>";
            }
            ?>
        </select>
    </td>
    <td><span class='muted'>Number of result to display</span></td>
</tr>


<tr>
    <td colspan="3"><hr></td>
</tr>

<tr>
    <td></td>
    <td class="text-right">
        <button type="submit" class="btn btn-sm btn-success"><i class='fa fa-search'></i> Search</button>
    </td>
    <td></td>
</tr>

</table>
</form>
</div>


<?php
if(sizeof($_POST)>0)   {
    include("search-results.php");
}
?>


<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap-datetimepicker.min.css">
<script type="text/javascript" src="js/bootstrap-datetimepicker.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $(".datetimepicker").datetimepicker( {
    	pickDate: true,
    	pickTime: true
    });
})
</script>