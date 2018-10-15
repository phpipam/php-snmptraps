<?php

/**
 * Checks for php version. >7.0 is required.
 */

if (phpversion() < $version['php']) {
	# cli
	if(php_sapi_name()=="cli") {
		print "Error: php version $version[php] or later is required, you are using php version ".phpversion().". Please update your installation.\n";
	}
	# gui
	else {
		print "<div style='padding: 20px;' class='text-center'><span class='badge badge1 alert alert-danger' style='border-top:1px solid;border-bottom:1px solid'><strong>Error:</strong><br><br>php version $version[php] or later is required, you are using php version ".phpversion().". Please update your installation.<span></div>";

		print "</div>";
		print "</body>";
		print "</html>";
	}
	# end
	die();
}