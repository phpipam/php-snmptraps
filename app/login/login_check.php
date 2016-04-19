<?php

/**
 *
 * Script to verify userentered input and verify it against database
 *
 * If successfull write values to session and go to main page!
 *
 */


/* functions */
require( dirname(__FILE__) . '/../../functions/functions.php');

# initialize user object
$Database 	= new Database_PDO;
$User 		= new User ($Database);
$Result 	= new Result ();

# strip input tags
$_POST = $User->strip_input_tags ($_POST);

# Authenticate
if( !empty($_POST['trapusername']) && !empty($_POST['trappassword']) )  {
	# all good, try to authentucate user
	$User->authenticate ($_POST['trapusername'], $_POST['trappassword']);
}
# Username / pass not provided
else {
	$Result->show("danger", _('Please enter your username and password'), true);
}
?>
