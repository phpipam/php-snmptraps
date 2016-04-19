<?php
# make sure user is admin
$User->is_admin ();
?>

<h4 class="red">General settings</h4>
<hr>

General settings must be for now set in functions/config.php file, later they will move to database. Here is content of config.php:

<br><br>

<pre style="width:auto;float:left;padding-top:0px;"><xmp><?php
$f = readfile(dirname(__FILE__)."/../../config.php");
?></xmp>
</pre>