<?php

/**
 *
 * Traphandler class
 *
 */
class Trap {

    /**
     * RAW message
     *
     * (default value: false)
     *
     * @var bool|object|array
     * @access public
     */
    public $message = false;

    /**
     * Message details
     *
     * @var mixed|object|array
     * @access public
     */
    public $message_details;

    /**
     * message details
     *
     * @var mixed
     * @access public
     */
    public $details;

    /**
     * Severities
     *
     * (default value: false)
     *
     * @var bool
     * @access public
     */
    public $severities =  array(
                                "emergency"     => "emergency",
                                "alert"         => "alert",
                                "critical"      => "critical",
                                "error"         => "error",
                                "warning"       => "warning",
                                "notice"        => "notice",
                                "informational" => "informational",
                                "debug"         => "debug"
                            );

    /**
     * OID filename ifMIB::linkDonw => ifMIB
     *
     * (default value: false)
     *
     * @var bool|string
     * @access public
     */
    public $oid_file = false;

    /**
     * Flag if message iz exception
     *
     * (default value: false)
     *
     * @var bool
     * @access public
     */
    public $exception = false;

    /**
     * Database connections
     *
     * @var mixed
     * @access private
     */
    private $Database;


    /**
     * Constructs, with trap message as array.
     *
     * @access public
     * @param mixed $message
     */
    public function __construct ($message) {
        # save message
        $this->message = $message;
        # set database object
        $this->set_database ();
        # parse message
        $this->parse_message ();
    }

    /**
     * PArses message
     *
     * @access private
     * @return void
     */
    private function parse_message () {
        # init object for details
        $this->message_details = new StdClass ();
        # parse elements
        $this->set_hostname ();
        $this->set_src_ip ();
        $this->set_uptime ();
        $this->set_oid ();
        $this->set_content ();
        $this->detect_severity ();
        $this->detect_msg ();
        $this->remove_unneeded_content ();
    }







    /**
     * Sets trap sender - hostname
     *
     * @access private
     * @return void
     */
    private function set_hostname () {
        $this->message_details->hostname = trim($this->message[0]);
    }

    /**
     * Saves source IP address
     *
     * @access private
     * @return void
     */
    private function set_src_ip () {
        // remove udp
        $this->message_details->ip = str_replace(array("UDP:","[","]"), "", $this->message[1]);
        // get ip
        $this->message_details->ip = trim(strstr($this->message_details->ip, ":", true));
    }

    /**
     * Save uptime
     *
     * @access private
     * @return void
     */
    private function set_uptime () {
        $this->message_details->uptime = trim(strstr($this->message[2], " "));
    }

    /**
     * Sets trapname.
     *
     * @access private
     * @return void
     */
    private function set_oid () {
        // full oid
        $this->message_details->oid = trim(strstr($this->message[3], " "));
        // master oid
        $this->oid_file = strstr($this->message_details->oid, "::", true);
    }

    /**
     * Sets content
     *
     * @access private
     * @return void
     */
    private function set_content () {
        // if some
        if (sizeof($this->message)>4) {
            $size = sizeof($this->message);
            // loop
            for ($m=4; $m<$size; $m++) {
                // it must match OID
                if (strpos($this->message[$m], $this->oid_file)!==false) {
                    // separate oid from content
                    $content = trim(strstr($this->message[$m], " "));
                    $oid = trim(strstr($this->message[$m], " ", true));
                    // remove index
                    $oid_tmp = explode(".", $oid);
                    array_pop($oid_tmp);
                    $oid = implode(".", $oid_tmp);
                    // remove oid file name
                    $oid = str_replace($this->oid_file."::", "", $oid);
                    // save
                    $this->message_details->content[] = "$oid => $content";
                }
            }

            # if none then save all
            if (!isset($this->message_details->content) || sizeof($this->message_details->content)==0) {
            $size = sizeof($this->message);
            for ($m=4; $m<$size; $m++) {
                // separate oid from content
                $content = trim(strstr($this->message[$m], " "));
                $oid = trim(strstr($this->message[$m], " ", true));
                // remove index
                $oid_tmp = explode(".", $oid);
                array_pop($oid_tmp);
                $oid = implode(".", $oid_tmp);
                // remove oid file name
                $oid = str_replace($this->oid_file."::", "", $oid);
                // save
                $this->message_details->content[] = "$oid => $content";
            }
            }
        }
        else {
            $this->message_details->content = "NONE";
        }
    }

    /**
     * Tries to detect severity
     *
     * @access private
     * @return void
     */
    private function detect_severity () {
        // default severity
        $this->message_details->severity = "unknown";

        // loop through message, search for Severity in each content, default null
        foreach ($this->message_details->content as $c) {
            if (strpos($c, "Severity")!==false || strpos($c, "severity")!==false) {
                $tmp = explode(" => ", $c);
                $this->message_details->severity = $tmp[1];
            }
        }

        // search database for exceptions and definitions
        if (is_object($this->Database)) {
            $severity_definitions = $this->fetch_severity_definitions ();
            if ($severity_definitions!==false) {
                // loop and check
                foreach ($severity_definitions as $d) {
                    if (strpos($this->message_details->oid, $d->oid)!==false) {
                        $this->message_details->severity = $this->severities[$d->severity];
                        break;
                    }
                }
            }
        }
    }

    /**
     * Tries to detect message
     *
     * @access private
     * @return void
     */
    private function detect_msg () {
        // default msg = oid
        $this->message_details->msg = str_replace("::","",strstr($this->message_details->oid, "::"));
        // define message array values
        $search_values = array("Msg",
                               "msg",
                               "Message",
                               "message"
                               );
        // changed flag
        $changed = false;
        // loop through message, search for Severity in each content, default null
        foreach ($search_values as $sv) {
            foreach ($this->message_details->content as $c) {
                if ( strpos($c, $sv)!==false ) {
                    $tmp = explode(" => ", $c);
                    $this->message_details->msg = $changed ? $this->message_details->msg." :: ".$tmp[1] : $tmp[1];
                    $changed = true;
                }
            }
        }
        // detect and format special messages
        $this->detect_special_messages ();
    }

    /**
     * Detects and format special messages.
     *
     * @access private
     * @return void
     */
    private function detect_special_messages () {
        // detect IF-MIB
        $this->detect_if ();
        // detect BRIDGE-MIB::topologyChange
        $this->detect_topologyChange ();
        // detect vlan created
        $this->detect_vtpVlanCreated ();
        // detect IKE
        $this->detect_ike ();
        // detect mteTrigger
        $this->detect_mte_trigger ();
        // auth failure IP address
        $this->detect_authfailure_ip ();
        // BGP state change
        $this->detect_bgp_change ();
    }

    /**
     * Tries to detect interface and replaces message
     *
     * @access private
     * @return void
     */
    private function detect_if () {
        // array of values to search
        $search_values = array("linkUp", "linkDown", "cieLinkUp", "cieLinkDown", "ipv6IfStateChange");
        // loop
        foreach ($search_values as $sv) {
            if ($this->message_details->msg == $sv) {
                foreach($this->message_details->content as $k=>$c) {
                    // explode
                    $c = explode(" => ", $c);
                    // check - first name, then status
                    if ($c[0] == "ifName") {
                        if ($sv=="linkUp" || $sv=="cieLinkUp")          { $this->message_details->msg = "Interface ".$c[1]. " changed state to Up";  }
                        elseif ($sv=="linkDown" || $sv=="cieLinkDown")  { $this->message_details->msg = "Interface ".$c[1]. " changed state to Up";  }
                        else                                            { $this->message_details->msg = "Interface ".$c[1]. " IPv6 state change"; }
                    }
                    elseif ($c[0] == "ifDescr")                         { $this->message_details->msg .= " (".$c[1].")"; }
                    elseif ($c[0] == "ifAlias")                         { $this->message_details->msg .= " ".$c[1]; }
                }
            }
        }
    }

    /**
     * detect_vtpVlanCreated function.
     *
     * @access private
     * @return void
     */
    private function detect_vtpVlanCreated () {
         if ($this->message_details->msg == "detect_vtpVlanCreated") {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check - first name, then status
                if (strpos($c[0], "vtpVlanName")!==false)     { $this->message_details->msg .= " :: ".$c[1]." (vlan ".array_pop(explode(".", $c[0])).")"; }
           }
        }
    }

    /**
     * Detect VTP VLAN toppology change and new root
     *
     * @access private
     * @return void
     */
    private function detect_topologyChange () {
        // topology change
        if (in_array($this->message_details->msg, array("topologyChange", "newRoot"))) {
            foreach($this->message as $k=>$c) {
                // explode
                $c = explode(" ", $c);
                // check - first name, then status
                if (strpos($c[0], "ifName")!==false)                { $this->message_details->msg .= " (Interface ".$c[1].")"; }
                elseif (strpos($c[0], "vtpVlanIndex")!==false)      { $this->message_details->msg .= " :: vlan ".$c[1]; }
            }
        }
    }

    /**
     * Detects ike messages
     *
     * @access private
     * @return void
     */
    private function detect_ike () {
        if (in_array( $this->message_details->msg, array("cikeTunnelStart", "_cikeTunnelStop"))) {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check
                if(strpos($c[0], "cikePeerRemoteAddr")!==false)        { $this->message_details->msg .= " :: peer ".$this->hex_to_ip($c[1]);  $this->message_details->content[] = "Remote address => ".$this->hex_to_ip($c[1]); }
                elseif(strpos($c[0], "cikeTunHistTermReason")!==false) { $this->message_details->msg .= " (".$c[1].")";                       $this->message_details->content[] = "Terminate reason => ".$this->hex_to_ip($c[1]); }
            }
        }
    }

    /**
     * detect_mte_trigger function.
     *
     * @access private
     * @return void
     */
    private function detect_mte_trigger () {
         if ($this->message_details->msg == "mteTriggerFired") {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check - first name, then status
                if ($c[0]=="mteHotTrigger")     { $this->message_details->msg .= " :: ".$c[1]; }
                elseif ($c[0]=="mteHotValue")   { $this->message_details->msg .= " (".$c[1]."%)"; }
           }
        }
    }

    /**
     * detect_authfailure_ip function.
     *
     * @access private
     * @return void
     */
    private function detect_authfailure_ip () {
          if ($this->message_details->msg == "authenticationFailure") {
            foreach($this->message as $k=>$c) {
                // explode
                $c = explode(" ", $c);
                // check - first name, then status
                if(strpos($c[0], "authAddr")!==false)  { $this->message_details->msg .= " :: ".$c[1]; }
           }
        }
    }

    /**
     * Detects BGP change.
     *
     * @access private
     * @return void
     */
    private function detect_bgp_change () {
        // juniper
        if (in_array( $this->message_details->msg, array("bgpEstablished", "bgpBackwardTransition"))) {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check
                if(strpos($c[0], "bgpPeerState")!==false)  {
                    $this->message_details->msg .= " :: peer ".str_replace("bgpPeerState.", "", $c[0]);
                    $this->message_details->msg .= " (state ".$c[1].")";
                }
            }
        }
    }

    /**
     * Transformas hex message to ip (C0 A8 FE 02).
     *
     * @access private
     * @param mixed $hex
     * @return void
     */
    private function hex_to_ip ($hex) {
        // to array
        $hex = array_filter(explode(" ", trim(str_replace("\"", "", $hex))));
        foreach ($hex as $k=>$v) {
            $hex[$k] = hexdec($v);
        }
        // resukt
        return implode(".", $hex);
    }

    /**
     * Returns details for current trap.
     *
     * @access public
     * @return void
     */
    public function get_trap_details () {
        return $this->message_details;
    }










    /** ----------- db ----------- */

    /**
     * Opens database connection
     *
     * @access private
     * @return void
     */
    private function set_database () {
        # open DB connection
        try {
            # det database
            $this->Database = new Database_PDO;
        }
        catch (Exception $e) {
            $this->write_error ($e->getMessage());
        }
    }

    /**
     * Fetches all exceptions from database - oids to be ignored.
     *
     * @access private
     * @return void
     */
    private function fetch_exceptions () {
        // try to fetch
		try { $exceptions = $this->Database->getObjects("exceptions", 'id', true); }
		catch (Exception $e) {
			$this->write_error ($e->getMessage());
			die();
		}
		// if some save it
		return sizeof($exceptions)>0 ? $exceptions : false;
    }

    /**
     * Fetches custom severity definitions
     *
     * @access private
     * @return void
     */
    private function fetch_severity_definitions () {
        // try to fetch
		try { $definitions = $this->Database->getObjects("severity_definitions", "id", true); }
		catch (Exception $e) {
			$this->write_error ($e->getMessage());
			die();
		}
		// if some save it
		return sizeof($definitions)>0 ? $definitions : false;
    }

    /**
     * Writes trap to database.
     *
     * @access public
     * @return void
     */
    public function write_trap () {
        // first check for exceptions
        $exceptions = $this->fetch_exceptions ();
        // check and loop
        if ($exceptions!==false) {
            foreach ($exceptions as $exc) {
                if (($exc->hostname==$this->message_details->hostname || $exc->hostname=="all") && strpos($this->message_details->oid, $exc->oid)!==false) {
                    // check for string
                    if (strlen($exc->content)>0) {
                        if (strpos(implode("\n", $this->message_details->content), $exc->content)!==false) {
                            $this->exception = true;
                            return true;
                        }
                    }
                    else {
                        $this->exception = true;
                        return true;
                    }
                }
            }
        }
        // prepare what to insert
        $values = array("hostname" => $this->message_details->hostname,
                        "ip"       => $this->message_details->ip,
                        "oid"      => $this->message_details->oid,
                        "message"  => $this->message_details->msg,
                        "severity" => $this->message_details->severity,
                        "content"  => implode("\n", $this->message_details->content),
                        "raw"      => implode("", $this->message)
                        );
        // write
		try { $this->Database->insertObject("traps", $values); }
		catch (Exception $e) {
			$this->write_error ($e->getMessage());
			die();
		}
        // ok
        return true;
    }

    /**
     * write_error function.
     *
     * @access private
     * @param string $error (default: "")
     * @return void
     */
    private function write_error ($error = "") {
        // create object
        $err_obj = new StdClass ();
        $err_obj->Error = $error;
        // init
        $Trap_file = new Trap_file ($err_obj);
        // write
        $Trap_file->write_error ($error);
    }

    /**
     * Removes unneeded values from trap
     *
     * @access private
     * @return void
     */
    private function remove_unneeded_content () {
        // define
        $unneeded_values = array("SNMP-COMMUNITY-MIB::snmpTrap",
                                 "SNMP-COMMUNITY-MIB::snmpTrapCommunity.0",
                                 "SNMP-COMMUNITY-MIB::snmpTrapAddress.0 ",
                                 "SNMPv2-MIB::snmpTrapEnterprise",
                                 "CISCO-SYSLOG-MIB::clogHistSeverity",
                                 "CISCO-SYSLOG-MIB::clogHistFacility",
                                 "CISCO-SYSLOG-MIB::clogHistTimestamp");
        // check and remove
        foreach ($unneeded_values as $uv) {
            foreach ($this->message_details->content as $k=>$c) {
                //content explode
                $c = explode(" => ", $c);
                // checl
                if (strpos($c[0], $uv)!==false) {
                    unset($this->message_details->content[$k]);
                }
            }
        }
    }
}


/**
 * Write trap file.
 */
class Trap_file {


    /**
     * file_handler
     *
     * (default value: false)
     *
     * @var bool|object
     * @access private
     */
    private $file_handler = false;

    /**
     * filename to write to
     *
     * (default value: "/tmp/trap.txt")
     *
     * @var string
     * @access protected
     */
    protected $filename = "/tmp/trap.txt";

    /**
     * Trap object
     *
     * @var mixed
     * @access private
     */
    private $trap_object;


    /**
     * __construct function.
     *
     * @access public
     * @param mixed $trap
     */
    public function __construct($trap) {
        // save trap
        $this->trap_object = $trap;
    }


    /**
     * Sets which file to write for debugging
     *
     * @access public
     * @param mixed $filename
     * @return void
     */
    public function set_file ($filename = false) {
        if ($filename!==false) {
            $this->filename = $filename;
        }
    }

    /**
     * Opens file for writing (appending)
     *
     * @access private
     * @return void
     */
    private function open_file () {
        // open file
        if(strlen($this->filename)>0) {
            $this->file_handler = fopen($this->filename, 'a') or die("can't open file");
        }
        else {
            die();
        }
    }

    /**
     * Writes file
     *
     * @access public
     * @return void
     */
    public function write_file () {
        // open file
        if ($this->file_handler === false)  { $this->open_file (); }
        //write
        fwrite($this->file_handler, implode("", $this->trap_object)."\n");
    }

    /**
     * Saves error to file.
     *
     * @access public
     * @return void
     */
    public function write_error ($error = "") {
        // open file
        if ($this->file_handler === false)  { $this->open_file (); }
        //write
        fwrite($this->file_handler, $error."\n");
        // close
        $this->close_file ();
    }

    /**
     * Writes file - parsed format
     *
     * @access public
     * @return void
     */
    public function write_file_parsed ($content = false) {
        // open file
        if ($this->file_handler === false)  { $this->open_file (); }

        // set what to write
        $content = $content===false ? $this->trap_object : $content;

        //write
        foreach ($content as $k=>$d) {
            // if array
            if (is_array($d)) {
                fwrite($this->file_handler, $k." => array:\n");
                foreach ($d as $k2=>$d2) {
                    fwrite($this->file_handler, "\t$d2\n");
                }
            }
            else {
                    fwrite($this->file_handler, $k." => ".$d."\n");
            }
        }
        fwrite($this->file_handler, "-----\n");
    }

    /**
     * Closes file handler
     *
     * @access public
     * @return void
     */
    public function close_file () {
        if ($this->file_handler !== false) {
            fclose($this->file_handler);
            $this->file_handler = false;
        }
    }

}

?>