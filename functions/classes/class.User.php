<?php

/**
*
*	User class to work with current user, authentication etc
*
*/

class User extends Common_functions {

	/**
	 * public variables
	 */
	public $username;						// (char) username
	public $authenticated = false;			// (bin) flag if user is authenticated
	public $timeout = false;				// (bin) timeout flag
	public $user = null;					// (obj) user details
	public $isadmin = false;				// (bin) flag if user is admin

	/**
	 * private variables
	 */
	private $ip;							// (char) Users IP address

	/**
	 * protected variables
	 */
	protected $sessname = "snmptraps";		// session name - default is snmptraps
	protected $debugging = false;			// (bool) debugging flag

	/**
	 * object holders
	 */
	protected $Database;					// for Database connection
    public $Result;





	/**
	 * __construct function
	 *
	 * @access public
	 */
	public function __construct (Database_PDO $database, $api = false) {
        # set result
        $this->Result = new Result ();
		# Save database object
		$this->Database = $database;
		# register new session
		$this->register_session ();
		# set authenticated flag
		$this->is_authenticated ();
	}










	/**
	 * @session management functions
	 * ------------------------------
	 */

	/**
	 * registers new session
	 *
	 * @access private
	 * @return void
	 */
	private function register_session () {
		// not for api
		if ($this->api !== true) {
			//set session name
			$this->set_session_name();
			//register session
			session_name($this->sessname);
			if(@$_SESSION===NULL) {
			session_start();
			}
		}
	}

	/**
	 * destroys session
	 *
	 * @access public
	 * @return void
	 */
	public function destroy_session () {
		session_destroy();
		$this->authenticated = false;
	}

	/**
	 * sets session name if specified in config file
	 *
	 * @access private
	 * @return void
	 */
	private function set_session_name () {
		include( dirname(__FILE__) . '/../../config.php' );
		$this->sessname = strlen(@$phpsessname)>0 ? $phpsessname : "snmptraps";
	}

	/**
	 * saves parameters to session after authentication succeeds
	 *
	 * @access private
	 * @return void
	 */
	private function write_session_parameters () {
		// not for api
		if ($this->api !== true) {
			$_SESSION['trapusername'] = $this->user->username;
		}
	}

	/**
	 * Checks if user is authenticated - session is set
	 *
	 * @access public
	 * @return void
	 */
	public function is_authenticated ($die = false) {
		# if checked for subpages first check if $user is array
		if(!is_array($this->user)) {
			if( isset( $_SESSION['trapusername'] ) && strlen( @$_SESSION['trapusername'] )>0 ) {
				# save username
				$this->username = $_SESSION['trapusername'];
				# fetch user profile and save it
				$this->fetch_user_details ($this->username);
				$this->authenticated = true;
			}
			else {
				$this->authenticated = false;
			}
		}
		# return
		return $this->authenticated;
	}

	/**
	 * Checks if current user is admin or not
	 *
	 * @access public
	 * @param bool $die (default: true)
	 * @return void
	 */
	public function is_admin ($die = true) {
		if($this->isadmin)		{ return true; }
		else {
			if($die)			{ $this->Result->show("danger", _('Administrator level privileges required'), true); }
			else				{ return false; }
		}
	}

	/**
	 * checks if user is authenticated, if not redirects to login page
	 *
	 * @access public
	 * @param bool $redirect (default: true)
	 * @return void
	 */
	public function check_user_session ($redirect = true) {
		# not authenticated
		if($this->authenticated===false && $redirect) {
			# set url
			$url = $this->createURL();

			# error print for AJAX
			if(@$_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest") {
				# for AJAX always check origin
				$this->check_referrer ();
				# kill session
				$this->destroy_session ();
				# error
				$this->Result->show("danger", _('Please login first')."!<hr><a class='btn btn-sm btn-default' href='".$url.$this->create_link ("login")."'>"._('Login')."</a>", true, true);
				die();
			}
			else {
				header("Location:".$url.$this->create_link ("login"));
				die();
			}
		}
	}










	/**
	* @authentication functions
	* -------------------------------
	*/

	/**
	 * Main function for authenticating users
	 *
	 *	> tries to fetch user details from database by username
	 *	> sets authentication method and checks validity
	 *	> authenticates
	 *
	 * @access public
	 * @param mixed $username
	 * @param mixed $password
	 * @return void
	 */
	public function authenticate ($username, $password) {
        # first we need to check if username exists
        $this->fetch_user_details ($username);
        # set method name variable
        $authmethodtype = "auth_check_".$this->user->auth_method;
        # authenticate
        $this->$authmethodtype ($username, $password);
	}

	/**
	 * tries to fetch user datails from database by username if not already existing locally
	 *
	 * @access private
	 * @param mixed $username
	 * @return void
	 */
	private function fetch_user_details ($username) {
		# only if not already active
		if(!is_object($this->user)) {
			try { $user = $this->Database->findObject("users", "username", $username); }
			catch (Exception $e) 	{ $this->Result->show("danger", _("Error: ").$e->getMessage(), true);}

			# if not result return false
			$usert = (array) $user;

			# admin?
			if($user->role == "administrator")	{ $this->isadmin = true; }

			if(sizeof($usert)==0)	{ $this->Result->show("danger", _("Invalid username or password"), true);}
			else 					{ $this->user = $user; }
		}
	}

    /**
     * local user authentication method, authenticates users through local DB entry
     * we provide user object from DB, and username/password entered by users
     *
     * @access private
     * @param mixed $username
     * @param mixed $password
     * @return void
     */
    private function auth_check_local ($username, $password) {
        # auth ok
        if($this->user->password == crypt($password, $this->user->password)) {
            # save to session
            $this->write_session_parameters ();
            # print success
            $this->Result->show("success", _("Login successful"));
            # write last logintime
            $this->update_login_time ();
        }
        # auth failed
        else {
            $this->Result->show("danger", _("Invalid username or password"), true);
        }
    }

	/**
	 *	Authenticate against a directory
	 *
	 *	Authenticates users against a directory - AD or LDAP
	 *	Using library > adLDAP - LDAP Authentication with PHP for Active Directory
	 *	http://adldap.sourceforge.net
	 *
	 * @access private
  	 * @param array $ad
  	 * @param mixed $username
	 * @param mixed $password
	 * @return void
	 */
	private function auth_check_ad ($username, $password) {
		// connect
		$adldap = $this->directory_connect();

		# authenticate
		try {
			if ($adldap->authenticate($username, $password)) {
				# save to session
				$this->write_session_parameters();

				$this->Result->show("success", _($method . " Login successful"));

				# write last logintime
				$this->update_login_time();
			} # wrong user/pass by default
			else {
				$this->Result->show("danger", _("Invalid username or password for " . $username ), true);

			}
		} catch (adLDAPException $e) {
			$this->Result->show("danger", _("Error: ") . $e->getMessage(), true);
		}

	}

	/**
	 *	Connect to a directory given our auth method settings
	 *
	 *	Connect using adLDAP
	 *
	 * @access private
	 * @param mixed $ad
	 * @return adLDAP object
	 */
	private function directory_connect () {
		# adLDAP script
		require(dirname(__FILE__) . "/../../config.php");
		require(dirname(__FILE__) . "/../adLDAP/src/adLDAP.php");

		# open connection
		try {
			$dirconn = new adLDAP($ad);
		} catch (adLDAPException $e) {
			$this->Result->show("danger", _("Error: ") . $e->getMessage(), true);
		}

		return $dirconn;
	}

	/**
	 *	AD (Active directory) authentication function
	 *
	 *
	 * @access private
	 * @param mixed $username
	 * @param mixed $password
	 * @return void
	 */
	private function auth_AD ($username, $password) {
		// parse settings for LDAP connection and store them to array
		$ad = json_decode($this->authmethodparams, true);
		// authenticate
		$this->directory_authenticate($ad, $username, $password);
	}









    /**
     *    @crypt functions
     *    ------------------------------
     */


    /**
     *    function to crypt user pass, randomly generates salt. Use sha256 if possible, otherwise Blowfish or md5 as fallback
     *
     *        types:
     *            CRYPT_MD5 == 1           (Salt starting with $1$, 12 characters )
     *            CRYPT_BLOWFISH == 1        (Salt starting with $2a$. The two digit cost parameter: 09. 22 characters )
     *            CRYPT_SHA256 == 1        (Salt starting with $5$rounds=5000$, 16 character salt.)
     *            CRYPT_SHA512 == 1        (Salt starting with $6$rounds=5000$, 16 character salt.)
     *
     * @access public
     * @param mixed $input
     * @return void
     */
    public function crypt_user_pass ($input) {
        # initialize salt
        $salt = "";
        # set possible salt characters in array
        $salt_chars = array_merge(range('A','Z'), range('a','z'), range(0,9));
        # loop to create salt
        for($i=0; $i < 22; $i++) { $salt .= $salt_chars[array_rand($salt_chars)]; }
        # get prefix
        $prefix = $this->detect_crypt_type ();
        # return crypted variable
        return crypt($input, $prefix.$salt);
    }

    /**
     *    this function will detect highest crypt type to use for system
     *
     * @access public
     * @return void
     */
    private function detect_crypt_type () {
        if(CRYPT_SHA512 == 1)        { return '$6$rounds=3000$'; }
        elseif(CRYPT_SHA256 == 1)    { return '$5$rounds=3000$'; }
        elseif(CRYPT_BLOWFISH == 1)    { return '$2y$'.str_pad(rand(4,31),2,0, STR_PAD_LEFT).'$'; }
        elseif(CRYPT_MD5 == 1)        { return '$5$rounds=3000$'; }
        else                        { $this->Result->show("danger", _("No crypt types supported"), true); }
    }

    /**
     * Returns crypt type used to encrypt password
     *
     * @access public
     * @return void
     */
    public function return_crypt_type () {
        if(CRYPT_SHA512 == 1)        { return 'CRYPT_SHA512'; }
        elseif(CRYPT_SHA256 == 1)    { return 'CRYPT_SHA256'; }
        elseif(CRYPT_BLOWFISH == 1)    { return 'CRYPT_BLOWFISH'; }
        elseif(CRYPT_MD5 == 1)        { return 'CRYPT_MD5'; }
        else                        { return "No crypt types supported"; }
    }










	/**
	 *	@updating user methods
	 *	------------------------------
	 */

    /**
     * Updates last users login time
     *
     * @access public
     * @return void
     */
    public function update_login_time () {
        # fix for older versions
        if($this->settings->version!="1.1") {
            # update
            try { $this->Database->updateObject("users", array("last_login"=>date("Y-m-d H:i:s"), "id"=>$this->user->id)); }
            catch (Exception $e) {
                $this->Result->show("danger", _("Error: ").$e->getMessage(), false);
                return false;
            }
        }
    }

	/**
	 * User self update method
	 *
	 * @access public
	 * @param mixed $post //posted user details
	 * @return void
	 */
	public function self_update($post) {
		# set items to update
		$items  = array("real_name"=>$post['real_name'],
						"mailNotify"=>$post['mailNotify'],
						"mailChangelog"=>$post['mailChangelog'],
						"email"=>$post['email'],
						"lang"=>$post['lang'],
						"id"=>$this->user->id,
						//display
						"compressOverride"=>$post['compressOverride'],
						"hideFreeRange"=>$this->verify_checkbox(@$post['hideFreeRange']),
						"printLimit"=>@$post['printLimit']
						);
		if(strlen($post['password1'])>0) {
		$items['password'] = $this->crypt_user_pass ($post['password1']);
		}

	    # prepare log file
	    $log = $this->array_to_log ($post);

		# update
		try { $this->Database->updateObject("users", $items); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
		# update language
		$this->update_session_language ();

		# ok, update log table
	    return true;
	}
}
?>
