<div class="row">
    <div class="col-xs-12 col-sm-6 col-lg-4"></div>
    <div class="col-xs-12 col-sm-6 col-lg-4">
        <?php print @$footer . " :: version ". implode(".", $version) ." :: <a href='app/settings/user-self-edit.php' class='load-modal'>". $User->user->real_name."</a>"; ?>
    </div>
    <div class="col-xs-12 col-sm-12 col-lg-4">
        <?php if($User->user->reload_page!=0 && $_GET['app']!=="settings" && $_GET['app']!=="live") { ?>
        <div class="pbar">
            <span class="pbar_text">Page will reload in <span class='pbar_percentage'><?php print $User->user->reload_page; ?></span> s <i class="fa fa-pause tooltip2 fa-toggle-timer" title='Click to toggle timer'></i></span>
            <div class="progress-bar progress-bar-gray" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            0%
            </div>
            <div class="hidden progress-bar-reload"><?php print $User->user->reload_page; ?></div>
        </div>
        <?php } ?>
    </div>
</div>