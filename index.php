<?php
ob_start();

/* config */
if (!file_exists("config.php"))	{ die("<br><hr>-- config.php file missing! Please copy default config file `config.dist.php` to `config.php` and set configuration!"); }

/* site functions */
require('functions/functions.php');

# set default page
if(!isset($_GET['app'])) { $_GET['app'] = "dashboard"; }

# database object
$Database 	= new Database_PDO;
$Result	= new Result;
$User	= new User ($Database);

/** include proper subpage **/
if($_GET['app']=="login")		{ require("app/login/index.php"); }
elseif($_GET['app']=="logout")	{ require("app/login/index.php"); }
else {
	# verify that user is logged in
	$User->check_user_session();

	# init classes
	$Trap        = new Trap_read ($Database);
	$Table_print = new Table_print ();
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
	<base href="<?php print BASE; ?>">

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="Cache-Control" content="no-cache, must-revalidate">

	<meta name="Description" content="">
	<meta name="title" content="Snmptraps">
	<meta name="robots" content="noindex, nofollow">
	<meta http-equiv="X-UA-Compatible" content="IE=9" >

	<meta name="viewport" content="width=device-width, initial-scale=0.7, maximum-scale=1, user-scalable=yes">

	<!-- chrome frame support -->
	<meta http-equiv="X-UA-Compatible" content="chrome=1">

	<!-- title -->
	<title>Snmptraps</title>

	<!-- css -->
	<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap.min.css">
<!-- 	<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap-custom.css"> -->
	<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap-custom-black.css">
	<link rel="stylesheet" type="text/css" href="css/font-awesome/font-awesome.min.css">

	<!-- js -->
	<script type="text/javascript" src="js/jquery-2.1.3.min.js"></script>
	<script type="text/javascript" src="js/magic.js"></script>
	<script type="text/javascript" src="js/bootstrap.min.js"></script>
	<script type="text/javascript" src="js/tooltip.js"></script>
	<script type="text/javascript" src="js/bdt/jquery.sortelements.js"></script>
	<script type="text/javascript" src="js/bdt/jquery.bdt.js"></script>
	<script type="text/javascript" src="js/stickytableheaders/jquery.stickytableheaders.min.js"></script>
	<script type="text/javascript">
	$(document).ready(function(){
	     if ($("[rel=tooltip]").length) { $("[rel=tooltip]").tooltip(); }
	});
	</script>
</head>

<!-- body -->
<body>

<!-- wrapper -->
<div class="wrapper">

<!-- loader -->
<div class="loading"><?php print _('Loading');?>...<br><i class="fa fa-spinner fa-spin"></i></div>

<!-- header -->

<div class="container-fluid" style="margin:0px;padding:0px;">
	<?php include('app/top-menu.php'); ?>
</div>


<!-- content -->
<div class="content_overlay">
<div class="container-fluid" id="mainContainer">
    <?php
    // include page
    if(!file_exists("app/$_GET[app]/index.php"))    { $Result->show("danger", _("Invalid APP"), false); }
    else                                            { include("app/$_GET[app]/index.php"); }
    ?>
</div>
</div>



<!-- modals -->
<div class="modal fade" id="modal1" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
        </div>
    </div>
</div>

<div class="modal fade" id="modal2" tabindex="-2" role="dialog" aria-labelledby="myModalLabel2" style="z-index:1042">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
        </div>
    </div>
</div>


<!-- pusher -->
<div class="pusher"></div>

<!-- Base for IE -->
<div class="iebase hidden"><?php print BASE; ?></div>

<!-- end wrapper -->
</div>

<!-- Page footer -->
<div class="footer"><?php include('app/footer.php'); ?></div>


<!-- end body -->
</body>
</html>
<?php ob_end_flush(); ?>
<?php } ?>