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
     * (default value: false)
     *
     * @var bool|string
     * @access public
     */
    public $mib_directory = false;

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
        $this->set_mib_direcotry ();
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
    	return array('emergency','alert','critical','error','warning','notice','informational','debug');
	}

    /**
     * Sets default directory for MIB files
     *
     * @access public
     * @param bool $dir (default: false)
     * @return void
     */
    public function set_mib_direcotry ($dir = false) {
        if ($dir!==false)                       { $this->mib_directory = $dir; }
        elseif ($this->mib_directory===false)   { $this->mib_directory = "/usr/share/snmp/mibs/"; }
    }

    /**
     * Gets all item in directory.
     *
     * @access public
     * @return void
     */
    public function read_mib_directory () {
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
     * How many traps to fetchw
     *
     * (default value: 100)
     *
     * @var int|string
     * @access private
     */
    private $print_limit = 100;             // result limit

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
	 * define_severities function.
	 *
	 * @access public
	 * @return void
	 */
	public function define_severities () {
    	$this->severities = array('emergency','alert','critical','error','warning','notice','informational','debug');
	}








    /**
     *  @check database methods ---------------------
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
	 *
	 * @access public
	 * @param string $severity (default: "all")
	 * @return void
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
	 * Fetches all traps from database
	 *
	 * @access private
	 * @return void
	 */
	private function fetch_all_traps () {
		# execute
		try { $traps = $this->Database->getObjects("traps", "id", false, $this->print_limit); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
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
    	// validate
    	if (!is_numeric($id) || $id==0 || is_null($id)) {
        	return false;
    	}
 		# execute
		try { $traps = $this->Database->getObjectsQuery("select * from `traps` where `id` > ? order by `id` asc;", array($id)); }
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
            // loo)
        	foreach ($severities as $s) {
            	$tmp[] = "severity = ?";
            	$tmp_sev[] = $s;
        	}
        	// set query
        	$query = "select * from traps where ".implode(" or ", $tmp)." order by id desc limit $this->print_limit;";
    	}
    	else {
    	    // set query
            $query = "select * from traps where `severity` = ? order by id desc limit $this->print_limit;";
            $tmp_sev[] = $severities[0];
    	}
    	// fetch traps
		try { $traps = $this->Database->getObjectsQuery($query, $tmp_sev); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
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
 		# execute
		try { $traps = $this->Database->findObjects("traps", "hostname", $host, "id", false, false, false, $this->print_limit); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
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
        	$query[]  = sizeof($query)>1 ? " and `message` like ? " : " where `message` like ? ";
        	$values[] = "%".$post['message']."%";
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

?>
