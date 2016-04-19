<form name="login" id="login" method="post">
<div class="row">

	<!-- username -->
	<div class="col-xs-12"><strong><?php print _('Username'); ?></strong></div>
	<div class="col-xs-12">
		<input type="text" id="username" name="trapusername" class="login form-control input-sm" placeholder="<?php print _('Username'); ?>" autofocus="autofocus" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"></input>
	</div>

	<!-- password -->
	<div class="col-xs-12" style="margin-top: 10px;"><strong><?php print _('Password'); ?></strong></div>
	<div class="col-xs-12">
	    <input type="password" id="password" name="trappassword" class="login form-control input-sm" placeholder="<?php print _('Password'); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"></input>
	    <?php
	    // add requested var for redirect
	    if(isset($_COOKIE['phptrapredirect'])) {
		    //ignore login, logout
		    if(strpos($_COOKIE['phptrapredirect'],"login")==0 && strpos($_COOKIE['phptrapredirect'],"logout")==0)
	        print "<input type='hidden' name='phptrapredirect' id='phptrapredirect' value='".@$_COOKIE['phptrapredirect']."'>";
	    }
	    ?>
	</div>
	<div class="col-xs-12">
		<hr>
		<input type="submit" value="<?php print _('Login'); ?>" class="btn btn-sm btn-default pull-right"></input>
	</div>

</div>

</form>


<!-- login response -->
<div id="loginCheck">
<?php
# deauthenticate user
if ( $User->is_authenticated() ) {
	# print result
	if($_GET['app']=="timeout")		{ $Result->show("success", _('You session has timed out')); }
	else							{ $Result->show("success", _('You have logged out')); }
	# destroy session
	$User->destroy_session();
}
?>
</div>
