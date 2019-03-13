<?php
ob_start();

# database object
$Database 	= new Database_PDO;
$Result	= new Result;
$User	= new User ($Database);

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

	<!-- icon -->
    <link rel="icon" type="image/png" href="css/favicon_big.png">

	<!-- css -->
	<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap.min.css">
<!-- 	<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap-custom.css"> -->
	<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap-custom-black.css">
	<link rel="stylesheet" type="text/css" href="css/font-awesome/font-awesome.min.css">

	<!-- js -->
	<script type="text/javascript" src="js/jquery-2.1.3.min.js"></script>
	<script type="text/javascript" src="js/magic.js"></script>
	<script type="text/javascript" src="js/bootstrap.min.js"></script>
</head>

<!-- body -->
<body>

<hr>

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
<div class="container" style="margin-top: 20px;width:600px">
    <h4 class="red text-center">Login to phptrapd server</h4>
    <hr>
    <div class='text-center'>Please enter your username / password to login to system. In case of any issues please contact system administrator!</div>

    <div class="login">
    <?php
    include("login-form.php");
    ?>
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
