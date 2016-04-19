<?php

/**
 * phpIPAM class with common functions, used in all other classes
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 */
class Common_functions  {

	/**
	 * settings
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	public $settings = null;

	/**
	 * Cache file to store all results from queries to
	 *
	 *  structure:
	 *
	 *      [table][index] = (object) $content
	 *
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	public $cache = array();

	/**
	 * cache_check_exceptions
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access private
	 */
	private $cache_check_exceptions = array();

	/**
	 * Database
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Database;

	/**
	 * Result
	 *
	 * @var mixed
	 * @access public
	 */
	public $Result;

	/**
	 * Log
	 *
	 * @var mixed
	 * @access public
	 */
	public $Log;

	/**
	 * Net_IPv4
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Net_IPv4;

	/**
	 * Net_IPv6
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Net_IPv6;

	/**
	 * NET_DNS object
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $DNS2;

	/**
	 * debugging flag
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $debugging;




	/**
	 * fetches settings from database
	 *
	 * @access private
	 * @return none
	 */
	public function get_settings () {
		# constant defined
		if (defined('SETTINGS')) {
			if ($this->settings === null || $this->settings === false) {
				$this->settings = json_decode(SETTINGS);
			}
		}
		else {
			# cache check
			if($this->settings === null) {
				try { $settings = $this->Database->getObject("settings", 1); }
				catch (Exception $e) { $this->Result->show("danger", _("Database error: ").$e->getMessage()); }
				# save
				if ($settings!==false)	 {
					$this->settings = $settings;
				}
			}
		}
	}

	/**
	 * get_settings alias
	 *
	 * @access public
	 * @return void
	 */
	public function settings () {
		return $this->get_settings();
	}





	/**
	 * Sets debugging
	 *
	 * @access private
	 * @return void
	 */
	public function set_debugging () {
		include( dirname(__FILE__) . '/../../config.php' );
		$this->debugging = $debugging ? true : false;
	}

	/**
	 * Strip tags from array or field to protect from XSS
	 *
	 * @access public
	 * @param array|string $input
	 * @return array|string
	 */
	public function strip_input_tags ($input) {
		if(is_array($input)) {
			foreach($input as $k=>$v) {
    			$input[$k] = strip_tags($v);
            }
		}
		else {
			$input = strip_tags($input);
		}
		# stripped
		return $input;
	}

	/**
	 * Changes empty array fields to specified character
	 *
	 * @access public
	 * @param array|object $fields
	 * @param string $char (default: "/")
	 * @return array
	 */
	public function reformat_empty_array_fields ($fields, $char = "/") {
    	$out = array();
    	// loop
		foreach($fields as $k=>$v) {
			if(is_null($v) || strlen($v)==0) {
				$out[$k] = 	$char;
			} else {
				$out[$k] = $v;
			}
		}
		# result
		return $out;
	}

	/**
	 * Removes empty array fields
	 *
	 * @access public
	 * @param array $fields
	 * @return array
	 */
	public function remove_empty_array_fields ($fields) {
    	// init
    	$out = array();
    	// loop
		foreach($fields as $k=>$v) {
			if(is_null($v) || strlen($v)==0) {
			}
			else {
				$out[$k] = $v;
			}
		}
		# result
		return $out;
	}

	/**
	 * Function to verify checkbox if 0 length
	 *
	 * @access public
	 * @param mixed $field
	 * @return void
	 */
	public function verify_checkbox ($field) {
		return @$field==""||strlen(@$field)==0 ? 0 : $field;
	}

	/**
	 * Transforms array to log format
	 *
	 * @access public
	 * @param mixed $logs
	 * @param bool $changelog
	 * @return void
	 */
	public function array_to_log ($logs, $changelog = false) {
		$result = "";
		# reformat
		if(is_array($logs)) {
			// changelog
			if ($changelog===true) {
			    foreach($logs as $key=>$req) {
			    	# ignore __ and PHPSESSID
			    	if( (substr($key,0,2) == '__') || (substr($key,0,9) == 'PHPSESSID') || (substr($key,0,4) == 'pass') || $key=='plainpass' ) {}
			    	else 																  { $result .= "[$key]: $req<br>"; }
				}

			}
			else {
			    foreach($logs as $key=>$req) {
			    	# ignore __ and PHPSESSID
			    	if( (substr($key,0,2) == '__') || (substr($key,0,9) == 'PHPSESSID') || (substr($key,0,4) == 'pass') || $key=='plainpass' ) {}
			    	else 																  { $result .= " ". $key . ": " . $req . "<br>"; }
				}
			}
		}
		return $result;
	}

	/**
	 * Create URL for base
	 *
	 * @access public
	 * @return void
	 */
	public function createURL () {
		# reset url for base
		if($_SERVER['SERVER_PORT'] == "443") 		{ $url = "https://$_SERVER[HTTP_HOST]"; }
		// reverse proxy doing SSL offloading
		elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') 	{ $url = "https://$_SERVER[SERVER_NAME]"; }
		elseif(isset($_SERVER['HTTP_X_SECURE_REQUEST'])  && $_SERVER['HTTP_X_SECURE_REQUEST'] == 'true') 	{ $url = "https://$_SERVER[SERVER_NAME]"; }
		// custom port
		elseif($_SERVER['SERVER_PORT']!="80")  		{ $url = "http://$_SERVER[SERVER_NAME]:$_SERVER[SERVER_PORT]"; }
		// normal http
		else								 		{ $url = "http://$_SERVER[HTTP_HOST]"; }

		//result
		return $url;
	}

    /**
     * create link function
     *
     *	if rewrite is enabled in settings use rewrite, otherwise ugly links
     *
     * @access public
     * @param mixed $l0 (default: null)
     * @param mixed $l1 (default: null)
     * @param mixed $l2 (default: null)
     * @return void
     */
    public function create_link ($l0 = null, $l1 = null, $l2 = null) {
    	# get settings
    	global $User;

    	# set normal link array
    	$el = array("app", "page", "id");

    	# set rewrite
    	if($User->settings->prettyLinks=="Yes") {
    		if(!is_null($l2))	    { $link = "$l0/$l1/$l2/"; }
    		elseif(!is_null($l1))	{ $link = "$l0/$l1/"; }
    		elseif(!is_null($l0))	{ $link = "$l0/"; }
    		else					{ $link = ""; }
    	}
    	# normal
    	else {
    		if(!is_null($l2))	    { $link = "?$el[0]=$l0&$el[1]=$l1&$el[2]=$l2"; }
    		elseif(!is_null($l1))	{ $link = "?$el[0]=$l0&$el[1]=$l1"; }
    		elseif(!is_null($l0))	{ $link = "?$el[0]=$l0"; }
    		else					{ $link = ""; }
    	}
    	# prepend base
    	$link = BASE.$link;

    	# result
    	return $link;
    }

	/**
	 * Creates links from text fields if link is present
	 *
	 *	source: https://css-tricks.com/snippets/php/find-urls-in-text-make-links/
	 *
	 * @access public
	 * @param mixed $field_type
	 * @param mixed $text
	 * @return void
	 */
	public function create_links ($text, $field_type = "varchar") {
        // create links only for varchar fields
        if (strpos($field_type, "varchar")!==false) {
    		// regular expression
    		$reg_exUrl = "#(http|https|ftp|ftps|telnet|ssh)://\S+[^\s.,>)\];'\"!?]#";

    		// Check if there is a url in the text
    		if(preg_match($reg_exUrl, $text, $url)) {
    	       // make the urls hyper links
    	       $text = preg_replace($reg_exUrl, "<a href='{$url[0]}' target='_blank'>{$url[0]}</a> ", $text);
    		}
        }
        // return text
        return $text;
	}

}
?>
