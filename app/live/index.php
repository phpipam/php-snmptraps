<?php

/**
 *
 * By severity
 *
 **/

# verify that user is logged in
$User->check_user_session();

# set limit to 10
$Trap->reset_print_limit (50);
# set permitted hostnames
$Trap->set_permitted_hostnames ($User->hostnames);

# fetch all traps
$traps = $Trap->fetch_traps ("all");


# set fields
$tfields = array(   "id"=>"",
                    "hostname"=>"Hostname",
                    "ip"=>"IP address",
                    "date"=>"Date",
                    "message"=>"Message",
                    "severity"=>"Severity",
                    "content"=>"Content"
                    );
$Table_print->set_snmp_table_fields ($tfields);

# structure
print "<div class='container-fluid row'>";

# critical
print "<h4>Live message update</h4><hr>";
print "<div class='container pull-left'>Messages will be updated every 15 seconds</div>";
print "<table class='table snmp live sorted table-noborder table-condensed table-hover' data-cookie-id-table='live'>";
$Table_print->print_snmp_table ($traps, true, true, false, true);
print "</table>";

print "</div>";

?>


<script type="text/javascript">
$(document).ready(function() {
   //update messages each 15 seconds
   var t = setInterval(update_table,15000);

   // stop
   //clearInterval(t);

   // update function
   function update_table () {
       // get last id
       var id = $('table.table.snmp.live').children('tbody').children('tr:first').attr('data-id');
       // remove updated class
       $('table.table.snmp tr').removeClass('new');
       // get updated tr items
        $.ajax({
            type: "POST",
            url: "app/live/update.php",
            data: "id="+ id,
            cache: false,
            success: function(html){
                if(html!=="False")
                $('table.table.snmp.live tbody').prepend(html);
            }
        });
   }
});
</script>