<?php

/**
*
*	SNMP class to read MIB file and process it
*
*/

class Snmp_read_MIB {

    /**
     * Mib filename (.my. txt)
     *
     * (default value: false)
     *
     * @var bool|string
     * @access public
     */
    public $mib_file_name = false;

    /**
     * Mib file name - extracted from OID
     *
     * (default value: false)
     *
     * @var bool|string
     * @access private
     */
    private $mib_file = false;

    /**
     * Directory of mib files
     *
     * (default value: "/usr/share/snmp/mibs/")
     *
     * @var bool|string
     * @access public
     */
    public $mib_directory = "/usr/share/snmp/mibs/";

    /**
     * OID from trap
     *
     *  IF-MIB::linkDown
     *
     * (default value: false)
     *
     * @var bool|string
     * @access private
     */
    private $oid = false;

    /**
     * Mib trap - extracted from OID
     *
     * (default value: false)
     *
     * @var bool|string
     * @access private
     */
    private $mib_trap = false;

    /**
     * Parsed string of whole MIB file
     *
     * (default value: false)
     *
     * @var bool|string
     * @access public
     */
    public $file = false;

    /**
     * PArsed trap from MIB
     *
     * (default value: false)
     *
     * @var bool|string
     * @access public
     */
    public $trap_parsed = false;

    /**
     * Objects in mib trap
     *
     * (default value: false)
     *
     * @var bool|array
     * @access public
     */
    public $trap_objects = false;

    /**
     * Trap decription from MIB file
     *
     * (default value: "")
     *
     * @var string
     * @access public
     */
    public $trap_description = "";




    /**
     *  @format MIB tables ---------------------
     */

    /**
     * Process MIB file from OID
     *
     * @access public
     * @param mixed $oid
     * @return void
     */
    public function process_oid ($oid) {
        // save OID
        $this->oid = $oid;
        // set directory for MIB files
        $this->set_mib_directory ();
        // verify and set file
        $this->verify_mib_file ($oid);
        // if not existing die
        if ($this->mib_file_name===false)   { return false; }
        // read mib file
        $this->read_mib_file ();
        // get object from MIB
        $this->get_trap_from_mib ();

        // set objects
        $this->set_trap_objects ();
        // set description
        $this->set_trap_description ();
    }

	/**
	 * define_severities function.
	 *
	 * @access public
	 * @return void
	 */
	public function define_severities () {
    	return array('emergency','alert','critical','error','warning','notice','informational','debug', 'audit');
	}

    /**
     * Sets default directory for MIB files
     *
     * @access public
     * @param bool $dir (default: false)
     * @return void
     */
    public function set_mib_directory ($dir = false) {
        if ($dir!==false && strlen($dir)>0)     { $this->mib_directory = $dir; }
        elseif ($this->mib_directory===false)   { $this->mib_directory = "/usr/share/snmp/mibs/"; }
    }

    /**
     * Gets all item in directory.
     *
     * @access public
     * @return void
     */
    public function read_mib_directory () {
        if (file_exists($this->mib_directory)) {
            if ($handle = opendir($this->mib_directory)) {
                $out = array();
                while (false !== ($entry = readdir($handle))) {
                    if (strpos($entry, ".txt")!==false || strpos($entry, ".my")!==false || strpos($entry, ".mib")!==false) {
                        $out[] = $entry;
                    }
                }
                closedir($handle);
                // return
                return $out;
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    /**
     * Verify that MIB file exists
     *
     * @access private
     * @param mixed $oid
     * @return void
     */
    private function verify_mib_file ($oid) {
        // separate MIB from OBJECT
        $mib = explode("::", $oid);
        // save
        $this->mib_file   = $mib[0];
        $this->mib_trap = $mib[1];

        // make sure file exists
        if (file_exists($this->mib_directory.$this->mib_file.".my"))      { $this->mib_file_name = $this->mib_directory.$this->mib_file.".my"; }
        elseif (file_exists($this->mib_directory.$this->mib_file.".txt")) { $this->mib_file_name = $this->mib_directory.$this->mib_file.".txt"; }
        elseif (file_exists($this->mib_directory.$this->mib_file.".mib")) { $this->mib_file_name = $this->mib_directory.$this->mib_file.".mib"; }
        else                                                              { return false; }
    }

    /**
     * Reads MIB file to var $file
     *
     * @access public
     * @param bool $filename (default: false)
     * @return void
     */
    public function read_mib_file ($filename=false) {
        if($filename!==false)   { $this->mib_file_name = $filename; }
        // we only allow .txt and .my
        if (strpos($this->mib_file_name, ".txt")!==false || strpos($this->mib_file_name, ".my")!==false || strpos($this->mib_file_name, ".mib")!==false) {
            // read file and put it to array
            $file_h = fopen($this->mib_file_name,"r");
            if (filesize($this->mib_file_name)>0) {
                $this->file = fread($file_h,filesize($this->mib_file_name));
                fclose($file_h);
            }
            else {
                fclose($file_h);
                return false;
            }
        }
        else {
            return false;
        }
    }

    public function read_mib_file_oid ($filename=false) {
        if($filename!==false)   { $this->mib_file_name = $filename; }
        // we only allow .txt and .my
        if (strpos($filename, ".txt")!==false || strpos($filename, ".my")!==false || strpos($filename, ".mib")!==false) {
            // read file and put it to array
            $file_h = fopen($this->mib_file_name,"r");
            if (filesize($this->mib_file_name)>0) {
                $this->file = fread($file_h,filesize($this->mib_file_name));
                fclose($file_h);
            }
            else {
                fclose($file_h);
                return false;
            }
        }
        else {
            return false;
        }
    }

    /**
     * Gets object part form MIB file
     *
     *  Searches MIB file for trap (IF_MIB::linkDown) => fetches linkDown definition from MIB
     *
     * @access private
     * @return void
     */
    private function get_trap_from_mib () {
        // init
        $res = array();
        // put string to array
        $file_arr = explode("\n", $this->file);

        // find first occurance > begins with $this->mib_trap
        foreach ($file_arr as $k=>$l) {
            // search for start if false
            if (!isset($start_pointer)) {
                if (strpos($l, $this->mib_trap." NOTIFICATION-TYPE")!==false) {
                    $start_pointer = $k;
                    $res[] = trim($l);
                }
            }
            // search for stop index
            else {
                // search for end
                if (strpos($l, "::=")!==false) {
                    break;
                }
                // save result
                $res[] = trim($l);
            }
        }
        // save result
        $this->trap_parsed = implode("\n",$res);
    }

    /**
     * .
     *
     * @access private
     * @return void
     */
    private function set_trap_objects () {
        // make sure we have a match
        if (strpos($this->trap_parsed, "OBJECTS")!==false) {
            // they are in brackets
            $this->trap_objects = explode(",", trim(str_replace(array("{", "}", "OBJECTS", " ", "\n"), "", strstr(strstr($this->trap_parsed, "OBJECTS"), "}", true))));
        }
    }

    /**
     * Saves trap description
     *
     * @access private
     * @return void
     */
    private function set_trap_description () {
        $this->trap_description = trim(str_replace(array("DESCRIPTION", "\""), "", strstr($this->trap_parsed, "DESCRIPTION")));
        $this->trap_description = str_replace("\n", "<br>", $this->trap_description);
    }

    /**
     * Detects all objects (traps) from mib file
     *
     * @access public
     * @return void
     */
    public function detect_mib_objects () {
        // init
        $res = array();
        // put string to array
        $file_arr = explode("\n", $this->file);

        // find first occurance > begins with $this->mib_trap
        foreach ($file_arr as $k=>$l) {
            if (strpos($l, " NOTIFICATION-TYPE")!==false) {
                // remove
                $tmp = trim(str_replace(" NOTIFICATION-TYPE","", $l));
                // no breaks
                if (strpos($tmp, " ")===false) {
                    $res[] = $tmp;
                }
            }
        }
        // result
        return sizeof($res)>0 ? $res : false;
    }

    /**
     * Detects OID from mib file.
     *
     * @access public
     * @return void
     */
    public function detect_mib_oid () {
        // init
        $res = array();
        // put string to array
        $file_arr = explode("\n", $this->file);

        // find first occurance > begins with $this->mib_trap
        foreach ($file_arr as $k=>$l) {
            if (strpos($l, " DEFINITIONS ::= BEGIN")!==false) {
                // remove
                $tmp = trim(str_replace(" DEFINITIONS ::= BEGIN","", $l));
                // no breaks
                if (strpos($tmp, " ")===false) {
                    return $tmp;
                }
            }
        }
        // result
        return false;
    }
}






/**
*
*	Reads traps from database
*
*/
class Trap_read extends Snmp_read_MIB {

    /**
     * How many traps to fetch
     *
     * (default value: 50)
     *
     * @var int
     * @access private
     */
    private $print_limit = 50;

    /**
     * Set db search offset
     *
     * (default value: 50)
     *
     * @var int
     * @access private
     */
    private $print_offset = 0;

    /**
     * Set db search order
     *
     * (default value: 'asc')
     *
     * @var string
     * @access private
     */
    private $print_order = 'desc';

	/**
	 * Database object
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Database;

	/**
	 * Result holder
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Result;

	/**
	 * Permitted hostnames
	 *
	 * (default value: "all")
	 *
	 * @var bool
	 * @access public
	 */
	public $hostnames = "all";

	/**
	 * severities
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	public $severities = array();


	/**
	 * __construct function.
	 *
	 * @access public
	 * @param Database_PDO $database
	 * @param int $print_limit (default: 100)
	 */
	public function __construct (Database_PDO $database, $print_limit = 100) {
		# Save database object
		$this->Database = $database;
		# Result
		$this->Result = new Result ();
		# severities
		$this->define_severities ();
	}

	/**
	 * Resets default print limit
	 *
	 * @access public
	 * @param mixed $print_limit
	 * @return void
	 */
	public function reset_print_limit ($print_limit) {
    	if (is_numeric($print_limit)) {
        	$this->print_limit = $print_limit;
    	}
	}

    /**
     * Set offset za DB search
     *
     * @method reset_print_offset
     * @param  int $offset
     * @return void
     */
    public function reset_print_offset ($offset) {
        if (is_numeric($offset)) {
            $this->print_offset = $offset;
        }
    }

    /**
     * Reset print order
     *
     * @method reset_print_order
     * @param  string $order
     * @return void
     */
    public function reset_print_order ($order) {
        if ($order=="asc" || $order=="desc") {
            $this->print_order = $order;
        }
    }

    /**
     * Set filter for SQL search
     * @method set_print_filter
     * @param  string $filter
     */
    public function set_print_filter ($filter = "") {
        if (strlen($filter)>0) {
            $this->filter_query       = " message like ? or content like ? or severity = ? or hostname = ? ";
            $this->filter_query_value = ["%".$filter."%", "%".$filter."%", $filter, $filter];
        }
    }

	/**
	 * Sets permitted hostnames
	 *
	 * @access public
	 * @param mixed $hostnames
	 * @return void
	 */
	public function set_permitted_hostnames ($hostnames = "all") {
    	// all
    	if($hostnames!=="all") {
        	$this->hostnames = $hostnames;
    	}
	}

	/**
	 * define_severities function.
	 *
	 * @access public
	 * @return void
	 */
	public function define_severities () {
    	$this->severities = array('emergency','alert','critical','error','warning','notice','informational','debug','audit');
	}








    /**
     *  @check database methods ---------------------
     */

    /**
     * fetch_severity_definition function.
     *
     * @access public
     * @param mixed $oid
     * @param mixed $content (default: null)
     * @return void
     */
    public function fetch_severity_definition ($oid, $content = null) {
        # set query
        if (is_null($content))  { $query = "select * from `severity_definitions` where `oid` = ?;"; $values = array($oid); }
        else                    { $query = "select * from `severity_definitions` where `oid` = ? and `content` = ?;"; $values = array($oid, $content); }

        # update
		try { $res=$this->Database->getObjectQuery($query, $values); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error updating traps: ").$e->getMessage(), false);
			return false;
		}
		# return
		return sizeof($res)==0 ? false : $res;
    }







    /**
     *  @fetch SNMP methods ---------------------
     */

    /**
     * Fetches SNMP traps from database.
     * @method fetch_traps
     *
     * @param  string $severity
     * @return array|false
     */
	public function fetch_traps ($severity="all") {
    	// all
    	if ($severity=="all")           { return $this->fetch_all_traps (); }
    	// array
    	elseif (is_array($severity))    { return $this->fetch_multiple_severities ($severity); }
	}

	/**
	 * Fetches single snmp trap by id
	 *
	 * @access public
	 * @param mixed $id
	 * @return void
	 */
	public function fetch_snmp_trap ($id) {
    	if(!is_numeric($id))          { return false; }
    	else {
     		# execute
    		try { $trap = $this->Database->getObject("traps", $id); }
    		catch (Exception $e) {
    			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
    			return false;
    		}
    		# return
    		return sizeof($trap)>0 ? $trap : false;
    	}
	}

	/**
	 * Returns array to append to check for selected queries
	 *
	 * @access private
	 * @param string $type (default: "where")
	 * @return void
	 */
	private function set_hostnames_query ($type = "where") {
    	// hostnames
    	if ($this->hostnames!=="all") {
        	foreach ($this->hostnames as $s) {
            	$hname[] = "hostname = ?";
            	$params[] = $s;
        	}
        	// where
        	if($type === "where") {
            	$hostnames = " where ".implode(" or ", $hname);
            }
            // and
            else {
            	$hostnames = " and ( ".implode(" or ", $hname)." ) ";
            }
    	}
    	else {
        	$hostnames = "";
        	$params = array();
    	}
    	// result
    	return array(
                    "query"  =>$hostnames,
                    "params" =>$params
    	           );
	}

    /**
     * Returns array to append to check for filtered queries
     *
     * @method set_search_filter_query
     * @param  array $hostnames
     * @param  array $hostnames
     */
    private function set_search_filter_query ($hostnames = [], $type = "where") {
        if (isset($this->filter_query)) {
            // no hostname query
            if (strlen(@$hostnames['query'])==0) {
                $hostnames['query']  = " $type (".$this->filter_query.") ";
                $hostnames['params'] = $this->filter_query_value;
            }
            else {
                $hostnames['query']  = $hostnames['query']." and (".$this->filter_query.")";
                $hostnames['params'] =  array_merge($hostnames['params'], $this->filter_query_value);
            }
            // return
            return $hostnames;
        }
        // no change
        else {
            return $hostnames;
        }
    }

	/**
	 * Fetches all traps from database
     *
     * @method fetch_all_traps
     * @return false|array
     */
	private function fetch_all_traps () {
    	// hostnames
    	$hostnames = $this->set_hostnames_query ("where");
        $hostnames = $this->set_search_filter_query ($hostnames, "where");
    	// set query
    	$query = "select * from traps $hostnames[query] order by id $this->print_order limit $this->print_limit offset $this->print_offset;";
        $query_c = "select count(*) as cnt from traps $hostnames[query]";
    	// fetch traps
		try { $traps = $this->Database->getObjectsQuery($query, $hostnames['params']); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
        // found rows
        try { $this->found_rows = $this->Database->getObjectQuery($query_c, $hostnames['params']); }
        catch (Exception $e) {
            return false;
        }
		# return
		return sizeof($traps)>0 ? $traps : false;
	}

	/**
	 * Returns new traps from one id onward.
	 *
	 * @access public
	 * @param mixed $id
	 * @return void
	 */
	public function get_new_traps ($id) {
    	// hostnames
    	$hostnames = $this->set_hostnames_query ("and");
        $hostnames = $this->set_search_filter_query ($hostnames, "and");
    	// validate
    	if (!is_numeric($id) || $id==0 || is_null($id)) {
        	return false;
    	}
 		# execute
		try { $traps = $this->Database->getObjectsQuery("select * from `traps` where `id` > ? $hostnames[query] order by `id` asc;", array_merge(array($id), $hostnames['params'])); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
		# return
		return sizeof($traps)>0 ? $traps : false;
	}

	/**
	 * Fetch multiple severities, defined in array
	 *
	 * @access private
	 * @param mixed $severities
	 * @return void
	 */
	private function fetch_multiple_severities ($severities) {
        // define search
        if (sizeof($severities)>0) {
            $tmp = array();
            $tmp_sev = array();
            // loop
        	foreach ($severities as $s) {
            	$tmp[] = "severity = ?";
            	$tmp_sev[] = $s;
        	}
        	// hostnames
        	$hostnames = $this->set_hostnames_query ("and");
            $hostnames = $this->set_search_filter_query ($hostnames, "and");
        	$tmp_sev = array_merge($tmp_sev, $hostnames['params']);
        	// set query
        	$query   = "select * from traps where (".implode(" or ", $tmp).") $hostnames[query] order by id $this->print_order limit $this->print_limit offset $this->print_offset;";
            $query_c = "select count(*) as `cnt` from traps where (".implode(" or ", $tmp).") $hostnames[query]";
    	}
    	else {
        	// hostnames
        	$hostnames = $this->set_hostnames_query ("and");
            $hostnames = $this->set_search_filter_query ($hostnames,"and");
    	    // set query
            $query   = "select * from traps where `severity` = ? $hostnames[query] order by id $this->print_order limit $this->print_limit offset $this->print_offset;";
            $query_c = "select count(*) as cnt from traps where `severity` = ? $hostnames[query]";

            $tmp_sev[] = $severities[0];
        	$tmp_sev = array_merge($tmp_sev, $hostnames['params']);
    	}

    	// fetch traps
		try { $traps = $this->Database->getObjectsQuery($query, $tmp_sev); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
        // found rows
        try { $this->found_rows = $this->Database->getObjectQuery($query_c, $tmp_sev); }
        catch (Exception $e) {
            return false;
        }
		# return
		return sizeof($traps)>0 ? $traps : false;
	}

	/**
	 * Fetches traps for specific message
	 *
	 * @access public
	 * @param mixed $message
	 * @return void
	 */
	public function fetch_traps_message ($message) {
 		# execute
		try { $traps = $this->Database->findObjects("traps", "message", $message, "id", false, true, false, $this->print_limit); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
		# return
		return sizeof($traps)>0 ? $traps : false;
	}

	/**
	 * Fetches traps for specific host
	 *
	 * @access public
	 * @param mixed $host
	 * @return void
	 */
	public function fetch_traps_host ($host) {
    	# validate
    	if($this->hostnames!=="all") {
        	if(!in_array($host, $this->hostnames)) {
            	$this->Result->show("danger", _("Error: Not allowed to view this host"), true);
        	}
    	}
        // set filter
        $hostnames = $this->set_search_filter_query ([], "and");
        $params = is_array($hostnames['params']) ? array_merge([$host], $hostnames['params']) : [$host];

        // set queries
        $query   = "select * from traps where `hostname` = ? $hostnames[query] order by id $this->print_order limit $this->print_limit offset $this->print_offset;";
        $query_c = "select count(*) as cnt from traps where `hostname` = ? $hostnames[query]";

        // fetch traps
        try { $traps = $this->Database->getObjectsQuery($query, $params); }
        catch (Exception $e) {
            $this->Result->show("danger", _("Error: ").$e->getMessage(), false);
            return false;
        }
        // found rows
        try { $this->found_rows = $this->Database->getObjectQuery($query_c, $params); }
        catch (Exception $e) {
            return false;
        }

		# return
		return sizeof($traps)>0 ? $traps : false;
	}

	/**
	 * Fetches unique hosts from traps table
	 *
	 * @access public
	 * @return void
	 */
	public function fetch_unique_hosts () {
    	// set query
    	$query = "select distinct(hostname) as hostname from traps;";
		try { $traps = $this->Database->getObjectsQuery($query); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
		# filter
		if($this->hostnames!=="all") {
    		if(sizeof($traps)>0) {
        		foreach($traps as $k=>$t) {
            		if(!in_array($t->hostname, $this->hostnames)) {
                		unset($traps[$k]);
            		}
        		}
    		}
		}
		# return
		return sizeof($traps)>0 ? $traps : false;
	}

	/**
	 * Searches database for traps.
	 *
	 * @access public
	 * @param array $post (default: array())
	 * @return void
	 */
	public function search_traps ($post = array()) {
    	# init arrays
    	$query = array();
    	$values = array();

    	# set query
    	$query[] = "select * from `traps` ";
    	// severity
    	if(isset($post['severity'])) {
        	$query[]  = " where `severity` = ? ";
        	$values[] = $post['severity'];
    	}
    	// hostname
    	if (isset($post['hostname'])) {
        	$query[]  = sizeof($query)>1 ? " and `hostname` = ? " : " where `hostname` = ? ";
        	$values[] = $post['hostname'];
    	}
    	// date
    	if (isset($post['start_date'])) {
        	$query[]  = sizeof($query)>1 ? " and `date` between ? and ? " : " where `date` between ? and ? ";
        	$values[] = $post['start_date'];
        	$values[] = $post['stop_date'];
    	}
    	// message
    	if (isset($post['message'])) {
        	$query[]  = sizeof($query)>1 ? " and (`message` like ? or `content` like ?) " : " where (`message` like ? or `content` like ?) ";
        	$values[] = "%".$post['message']."%";
        	$values[] = "%".$post['message']."%";
    	}
		// filter permitted hostnames
		if($this->hostnames!=="all") {
            $hostnames = sizeof($values)>0 ? $this->set_hostnames_query ("and") : $this->set_hostnames_query ("where");
            $hostnames = $this->set_search_filter_query ($hostnames, "and");
            $values = array_merge($values, $hostnames['params']);
            $query[] = $hostnames['query'];
		}
    	// limit
    	$query[] = " order by `id` $post[order] limit ".$this->print_limit.";";

 		# execute
		try { $traps = $this->Database->getObjectsQuery(implode("\n",$query), $values); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			return false;
		}
		# return
		return sizeof($traps)>0 ? $traps : false;
	}
}





/**
 * Trap_update class.
 *
 *  Updates database
 *
 */
class Trap_update extends Trap_read {

	/**
	 * Database object
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Database;

	/**
	 * Result holder
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Result;





	/**
	 * __construct function.
	 *
	 * @access public
	 * @param Database_PDO $database
	 * @return void
	 */
	public function __construct (Database_PDO $database) {
		# Save database object
		$this->Database = $database;
		# Result
		$this->Result = new Result ();
		# severities
		$this->define_severities ();
	}

	/**
	 * Updates object
	 *
	 * @access public
	 * @param mixed $table
	 * @param mixed $object
	 * @param string $key (default: "id")
	 * @return void
	 */
	public function update_object ($table, $object, $key = "id") {
        // execute
		try { $this->Database->updateObject($table, $object, $key); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error updating object: ").$e->getMessage(), false);
			return false;
		}
		// ok
		return true;
	}

	/**
	 * Creates new trap object.
	 *
	 * @access public
	 * @param mixed $table
	 * @param mixed $object
	 * @return void
	 */
	public function create_object ($table, $object) {
         // execute
		try { $this->Database->insertObject($table, $object); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error creating object: ").$e->getMessage(), false);
			return false;
		}
		// ok
		return true;
	}

	/**
	 * Update trap wrapper.
	 *
	 * @access public
	 * @param mixed $action
	 * @param array $item (default: array())
	 * @return void
	 */
	public function update_trap ($action, $item = array()) {
    	// return based on action
    	if ($action == "define")        { $this->update_trap_severity ($item); }
    	// return based on action
    	elseif ($action == "delete")    { $this->delete_trap_oid ($item); }
    	// return based on action
    	elseif ($action == "ignore")    { $this->add_trap_exception ($item); }
    	// die
    	else                            { $this->Result->show("danger", "Missing or unimplemented action", true); }
	}

	/**
	 * Deletes trap by oid and message.
	 *
	 * @access private
	 * @param array $item (default: array())
	 * @return void
	 */
	private function delete_trap_oid ($item = array()) {
    	if (strlen($item['content'])>0) { $query = "delete from `traps` where `oid` = ? and `content` like ?"; $object = array($item['oid'], "%".$item['content']."%"); }
    	else                            { $query = "delete from `traps` where `oid` = ?"; $object = array($item['oid']); }

		try { $this->Database->runQuery($query, $object); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error removing traps: ").$e->getMessage(), false);
			return false;
		}

		$this->Result->show("success", "Traps removed", false);
	}

	/**
	 * Removes existing traps and creates new exception
	 *
	 * @access private
	 * @param array $item (default: array())
	 * @return void
	 */
	private function add_trap_exception ($item = array()) {
        // remove old traps
        $this->delete_trap_oid ($item);
        // create update array
        $values = array("oid"=>$item['oid'], "hostname"=>$item['hostname'], 'content'=>$item['content'], 'comment'=>$item['comment']);
        // add exception
        if($this->create_object ("exceptions", $values)!==false)                                    { $this->Result->show("success", "Exception created", false); }
    }

	/**
	 * Updates severity for existing class
	 *
	 * @access private
	 * @param array $item (default: array())
	 * @return void
	 */
	private function update_trap_severity ($item = array()) {
    	// check for required fields
    	if (!isset($item['newseverity']) || !isset($item['oldseverity']) || !isset($item['oid']))   { $this->Result->show("danger", "Missing required fields", true); }
    	// validate severity
    	elseif (!in_array($item['newseverity'], $this->severities))                                 { $this->Result->show("danger", "Invalid severity", true); }
    	// if same
    	elseif ($item['newseverity'] == $item['oldseverity'])                                       { $this->Result->show("warning", "Set different severity to change", true); }
    	// update
    	else {
    		# fetch existing definition
    		if(strlen(@$item['content'])==0) $item['content'] = null;
    		$existing = $this->fetch_severity_definition ($item['oid'], $item['content']);

    		# set update query and object
    		$object = array("oid"=>$item['oid'], "severity"=>$item['newseverity'] );
            if (strlen($item['content'])>0) {
                $object['content'] = $item['content'];
            }
            if (strlen($item['comment'])>0) {
                $object['comment'] = $item['comment'];
            }
            if ($item!==false) {
                $object['id'] = $existing->id;
            }


    		if ($existing===false)  { $definition_update = $this->create_object ("severity_definitions", $object); }
    		else                    { $definition_update = $this->update_object ("severity_definitions", $object); }

            if ($definition_update === true)                                                        { $this->Result->show("success", "Definition updated", false); }



            # update all existing traps
            if ($item['content'] == null)   { $query = "update `traps` set `severity` = ? where `oid` = ?"; $object_traps = array($item['newseverity'], $item['oid']); }
            else                            { $query = "update `traps` set `severity` = ? where `oid` = ? and `message` = ?"; $object_traps = array($item['newseverity'], $item['oid'], $item['content']); }

    		try { $this->Database->runQuery($query, $object_traps); }
    		catch (Exception $e) {
    			$this->Result->show("danger", _("Error updating existing traps: ").$e->getMessage(), false);
    			return false;
    		}

    		$this->Result->show("success", "Traps severity updated", false);
    	}
	}


}
