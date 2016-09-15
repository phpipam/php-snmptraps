<?php

/** menu definitions **/
$menu = array(
            "dashboard"      => array("href"=>"dashboard", "icon"=>"fa fa-dashboard", "name"=>"Dashboard"),
            "all"            => array("href"=>"severity/all", "icon"=>"fa fa-globe", "name"=>"All"),
            "live"           => array("href"=>"live", "icon"=>"fa fa-bolt", "name"=>"Live"),
            "major"          => array("href"=>"severity/major", "icon"=>"fa fa-exclamation-triangle", "name"=>"Major"),
            "minor"          => array("href"=>"severity/minor", "icon"=>"", "name"=>"Medium"),
            "informational"  => array("href"=>"severity/informational", "icon"=>"fa fa-info",  "name"=>"Minor"),
            "unknown"        => array("href"=>"severity/unknown", "icon"=>"fa fa-question-circle", "name"=>"Unknown"),
            "host"           => array("href"=>"host", "icon"=>"fa fa-sitemap", "name"=>"Hosts"),
            "search"         => array("href"=>"search", "icon"=>"fa fa-search",  "name"=>" Search"),
            );


?>

<nav class="navbar navbar-default navbar-inverse" style="width:100%;">

    <!-- Brand and toggle get grouped for better mobile display -->
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse" aria-expanded="false">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
    </div>


    <div id="navbar" class="collapse navbar-collapse" style="padding-left: 0px;padding-right: 0px;">
        <ul class="nav navbar-nav">
            <?php
            # print menu
            if ($_GET['app']!="login" && $_GET['app']!="logout" && $_GET['app']!="timeout") {
                foreach ($menu as $k=>$m) {
                    if (isset($_GET['page']))
                        $class = $m['href']==$_GET['app']."/".$_GET['page'] ? "active" : "";
                    else
                        $class = $k==$_GET['app'] ? "active" : "";

                    // host
                    if ($_GET['app']=="host" && isset($_GET['page']) && $m['href']=="host")
                        $class = "active";
                    // host
                    if ($_GET['app']=="message" && isset($_GET['page']) && $m['href']=="message")
                        $class = "active";
                    // print
                    print "<li class='$class'><a href='$m[href]/'><i class='$m[icon]'></i> $m[name]</a></li>";
                }
            ?>

        </ul>


        <ul class="nav navbar-nav navbar-collapse navbar-right">

            <?php if($User->is_admin(false) !== false) { ?>
            <li class="<?php if($_GET['app']=="settings") { print "active"; } ?>">
                <a href='settings/' class="settings"><i class='fa fa-cogs fa-red'></i> Settings</a>
            </li>
            <?php } ?>

            <li class="dropdown" id="dropdown" style="margin-right: 0px;">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-user"></i> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                    <li class="dropdown-header"><?php print $User->user->real_name; ?><hr></li>
                    <li class='role'><a><?php print ucwords($User->user->role); ?></a></li>
                    <li><a href='app/settings/user-self-edit.php' class="load-modal"><i class='fa fa-pencil'></i>Profile</a></li>
                    <li><a href='logout/' class="settings"><i class='fa fa-sign-out'></i>Logout</a></li>
                </ul>
            </li>
            <?php } ?>
        </ul>
    </div>

</nav>
