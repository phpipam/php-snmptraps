<?php

/**
 * trap_notify class.
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 *
 */
class Trap_notify {

    /**
     * List of allowed methods (classes) for sending notifications
     *
     * (default value: array())
     *
     * @var array
     * @access private
     */
    private $allowed_methods = array();

    /**
     * Notification parameters - $notification_params
     *
     * (default value: object)
     *
     * @var mixed
     * @access private
     */
    private $params = object;

    /**
     * Trap details
     *
     * @var object
     * @access protected
     */
    protected $trap_details;

    /**
     * Database handler
     *
     * @var mixed
     * @access protected
     */
    protected $Database;

    /**
     * filename to write errors to
     *
     * @var mixed
     * @access public
     */
    public $filename;






    /**
     * __construct function.
     *
     * @access public
     * @param mixed $trap_details
     * @param mixed $params
     * @param string $filename (default: "/tmp/trap.txt")
     * @return void
     */
    public function __construct ($trap_details, $params, $filename = "/tmp/trap.txt") {
        // save filename for errors
        $this->filename = $filename;
        // save params
        $this->params = (object) $params;
        // set requested notification methods
        $this->set_allowed_methods ();
        // open db connection
        $this->db_open_connection ();
        // save trap details
        $this->trap_details = $trap_details;
    }

    /**
     * Sets allowed authentication methods.
     *
     * @access private
     * @return void
     */
    private function set_allowed_methods () {
        include(dirname(__FILE__)."/../../config.php");
        $this->notification_methods = $notification_methods;
    }

    /**
     * Writes new error to file
     *
     * @access private
     * @param mixed $error
     * @return void
     */
    private function write_error ($error) {
        // we need object
        if (is_object($error))       { $out = (array) $error; }
        elseif (is_string($error))   { $out = array();  $out['error'] = $error; }
        else                         { $out = (array) $error; }

        // start file object, set file and write error
        $File = new Trap_file ($this->params);
        $File->set_file ($this->filename);
        $File->write_file_parsed ($out);
    }

    /**
     * Send notification function.
     *
     * @access public
     * @return void
     */
    public function send_notification () {
        // fetch all users
        $this->db_get_notification_users ();

        // check maintaneance
        if ($this->db_check_maintaneance () === true) {
            $this->write_error ("Notification skipped: Maintaneance mode");
            return true;
        }
        // send
        else {
            // fetch users
            $users = $this->db_get_notification_users ();
            // check if some are to receive message
            if ($users!==false) {
                // put to notification methods
                $methods = array();
                foreach ($users as $u) {
                    $permitted = false;
                    // validate if hostname is permitted !
                    if($u->hostnames!=="all") {
                        $hostnames = explode(";", $u->hostnames);
                        if(is_array($hostnames)) {
                            if(in_array($this->trap_details->hostname, $hostnames)) {
                                $permitted = true;
                            }
                        }
                    }
                    else {
                        $permitted = true;
                    }

                    // permitted ?
                    if ($permitted) {
                        // to array
                        $user_methods = explode(";", $u->notification_types);
                        // save
                        foreach ($user_methods as $m) {
                            if ($m!="none" && strlen($m)>0) {
                                $methods[$m][] = $u;
                            }
                        }
                    }
                }
                // filter out blank
                $methods = array_filter($methods);
                // if set
                if (sizeof($methods)>0) {
                    foreach ($methods as $k=>$m) {
                        // init object
                        unset($Obj);
                        $Obj = new $k ((array) $this->params);
                        $Obj->send ($this->trap_details, $m);
                    }
                }
            }
            else {
                return true;
            }
        }
    }

    /**
     * Opens database connection
     *
     * @access private
     * @return void
     */
    private function db_open_connection () {
        # open DB connection
        try {
            $this->Database = new Database_PDO;
        }
        catch (Exception $e) {
            $this->write_error ("Database error: ".$e->getMessage());
            die();
        }
    }

    /**
     * Get all users to set notifications for specific severity, also check quiet times
     *
     * @access private
     * @return void
     */
    private function db_get_notification_users () {
        // try to fetch
		try { $users = $this->Database->getObjectsQuery("select * from `users` where `notification_severities` like ? and CURTIME() not between `quiet_time_start` and '00:00:00' and CURTIME() not between '00:00:00' and `quiet_time_start`;", array("%".$this->trap_details->severity."%")); }
		catch (Exception $e) {
			$this->write_error ("Database error: ".$e->getMessage());
		}
		// result
		return sizeof($users)>0 ? $users : false;
    }

    /**
     * Check maintaneance, dont send mails if maintaneance scheduled for host
     *
     * @access private
     * @return void
     */
    private function db_check_maintaneance () {
        // check for database for exceptions
        if ($this->trap_details->hostname!==false) {
            // set dates
            $now = date("Y-m-d H:i:s");
            // try to fetch
    		try { $exceptions = $this->Database->getObjectQuery("select count(*) as `cnt` from `maintaneance` where `hostname` = ? and NOW() between `start` and `stop`;", array($this->trap_details->hostname)); }
    		catch (Exception $e) {
    			$this->write_error ("Database error: ".$e->getMessage());
    		}
    		// check
    		return $exceptions->cnt > 0 ? true : false;
        }
    }
}









/**
 * Mail class to send sms notifications
 *
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 */
class sms {

	/**
	 * SMS ettings from config.php
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $sms_settings = null;

    /**
     * __construct function.
     *
     * @access public
     * @param array $params (default: array())
     * @return void
     */
    public function __construct($params = array()) {
        // check
        if (!isset($params['sms']))  { $this->write_error ("Error: Invalid SMS parameters"); }
		# save sms settings
		$this->sms_settings = (object) $params['sms'];
    }

    /**
     * Writes new error to file
     *
     * @access private
     * @param mixed $error
     * @return void
     */
    private function write_error ($error) {
        // we need object
        if (is_object($error))       { $out = (array) $error; }
        elseif (is_string($error))   { $out = array();  $out['error'] = $error; }
        else                         { $out = (array) $error; }

        // set master settings
        include(dirname(__FILE__)."/../../config.php");

        // start file object, set file and write error
        $File = new Trap_file ($out);
        $File->set_file ($filename);
        $File->write_file_parsed ($out);
    }

    /**
     * Sends sms to all recipients.
     *
     * @access public
     * @param mixed $message_details          // message details
     * @param object|array $recipients        // array of recipients
     * @return void
     */
    public function send ($message_details, $recipients) {
        # set content
        $content = "[".$message_details->hostname."] - ".$message_details->msg." (".$message_details->severity.") \n\n".implode("\n", $message_details->content);

        # validate and send
        foreach ($recipients as $r) {
            // validate recipient
            if ($this->validate_recipient ($r)===false) {
                $this->write_error ("Invalid recipient ".$r->real_name." : tel: ".$r->tel);
            }
            else {
                // remove +
                $r->tel = str_replace("+", "", $r->tel);
                // send
                $this->send_sms ($r->tel, $content);
            }
        }
    }

    /**
     * Sends sms
     *
     * @access private
     * @param mixed $recipient
     * @param mixed $content
     * @return void
     */
    private function send_sms ($recipient, $content) {
        # set url
        $url = "http://".$this->sms_settings->server.$this->sms_settings->uri."sendsms?sender=".$this->sms_settings->sender."&recipient=$recipient&appid=".$this->sms_settings->appid."&reference=".time()."&message=".urlencode($content);

        # send
        $sms_resp = file_get_contents($url);
        # parse response
        $resp = json_decode($sms_resp);
        # check for ok
        if ($resp->SendSmsResponse->status!=="OK") {
            $this->write_error ($resp->SendSmsResponse->status);
        }
    }

    /**
     * Validate sms recipients.
     *
     * @access private
     * @param array $recipients (default: array())
     * @return void
     */
    private function validate_recipient ($recipient) {
        // remove +
        $recipient->tel = str_replace("+", "", $recipient->tel);
        // numeric check
        if (!is_numeric($recipient->tel)) {
            return false;
        }
        else {
            return true;
        }
    }
}










/**
 * Mail class to send mail notifications
 *
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 */
class mail {

	/**
	 * Php_mailer object
	 *
	 * @var mixed
	 * @access public
	 */
	public $Php_mailer;

	/**
	 * Settings from config.php
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $settings = null;

	/**
	 * Mailer settings
	 *
	 * (default value: false)
	 *
	 * @var bool
	 * @access protected
	 */
	protected $mail_settings = false;

	/**
	 * message_details
	 *
	 * (default value: false)
	 *
	 * @var bool
	 * @access protected
	 */
	protected $message_details = false;

    /**
     * __construct function.
     *
     * @access public
     * @param array $params (default: array())
     * @return void
     */
    public function __construct($params = array()) {
        // check
        if (!isset($params['mail']))  { $this->write_error ("Error: Invalid mail parameters"); die();  }
        # import parameters from config file
        include(dirname(__FILE__)."/../../config.php");
        $this->settings = array();
        $this->settings = array("url"=>$url);
		# save mail settings
		$this->mail_settings = (object) $params['mail'];
        # init mailer
        $this->initialize_mailer ();
    }

    /**
     * Writes new error to file
     *
     * @access private
     * @param mixed $error
     * @return void
     */
    private function write_error ($error) {
        // we need object
        if (is_object($error))       { $out = (array) $error; }
        elseif (is_string($error))   { $out = array();  $out['error'] = $error; }
        else                         { $out = (array) $error; }

        // set master settings
        include(dirname(__FILE__)."/../../config.php");

        // start file object, set file and write error
        $File = new Trap_file ($out);
        $File->set_file ($filename);
        $File->write_file_parsed ($out);
    }

	/**
	 * Initializes mailer object.
	 *
	 * @access public
	 * @return void
	 */
	public function initialize_mailer () {
		# we need phpmailer
		require_once( dirname(__FILE__).'/../PHPMailer/PHPMailerAutoload.php');

		# initialize object
		$this->Php_mailer = new PHPMailer(true);			//localhost by default
		$this->Php_mailer->CharSet="UTF-8";					//set utf8
		$this->Php_mailer->SMTPDebug = 0;					//default no debugging

		# localhost or smtp?
		if ($this->mail_settings->type=="smtp")    { $this->set_smtp(); }
	}

	/**
	 * Sets SMTP parameters
	 *
	 * @access private
	 * @return void
	 */
	private function set_smtp() {
		//set smtp
		$this->Php_mailer->isSMTP();
		//tls, ssl?
		if ($this->mail_settings->security != 'none')
		$this->Php_mailer->SMTPSecure = $this->mail_settings->security == 'ssl' ? 'ssl' : 'tls';
		//server
		$this->Php_mailer->Host = $this->mail_settings->server;
		$this->Php_mailer->Port = $this->mail_settings->port;
		//permit self-signed certs and dont verify certs
		$this->Php_mailer->SMTPOptions = array("ssl"=>array("verify_peer"=>false, "verify_peer_name"=>false, "allow_self_signed"=>true));
		//set smtp auth
		$this->set_smtp_auth();
	}

	/**
	 * Set SMTP login parameters
	 *
	 * @access private
	 * @return void
	 */
	private function set_smtp_auth() {
		if ($this->mail_settings->auth == "yes") {
			$this->Php_mailer->SMTPAuth = true;
			$this->Php_mailer->Username = $this->mail_settings->user;
			$this->Php_mailer->Password = $this->mail_settings->pass;
		} else {
			$this->Php_mailer->SMTPAuth = false;
		}
	}

    /**
     * Sends mail to all recipients.
     *
     * @access public
     * @param mixed $message_details          // message details
     * @param object|array $recipients        // array of recipients
     * @return void
     */
    public function send ($message_details, $recipients) {
        # save details
        $this->message_details = (object) $message_details;
        # set subject
        $subject = "[".$message_details->hostname."] - ".$message_details->msg;

        # set mail body content
        $body = array();
        $body[] = "<div style='padding:10px;'><font face='Helvetica, Verdana, Arial, sans-serif' style='font-size:12px;color:#333;'>";
        $body[] = "New snmp trap received:";
        $body[] = "<br><br>";
        $body[] = "<table>";
        $body[] = "<tr><td>Hostname:</td><td style='padding-left: 10px;'><strong>".$message_details->hostname."</strong></td></tr>";
        $body[] = "<tr><td>IP:</td><td style='padding-left: 10px;'>".$message_details->ip."</td></tr>";
        $body[] = "<tr><td>Message:</td><td style='padding-left: 10px;'><strong>".$message_details->msg."</strong></td></tr>";
        $body[] = "<tr><td>Date:</td><td style='padding-left: 10px;'>".date("d/m/Y H:i:s")."</td></tr>";
        $body[] = "<tr><td>Severity:</td><td style='padding-left: 10px;'><strong>".$message_details->severity."</strong></td></tr>";
        $body[] = "<tr><td>OID:</td><td style='padding-left: 10px;'>".$message_details->oid."</td></tr>";
        $body[] = "<tr><td><strong>Content</strong>:</td><td style='padding-left: 10px;vertical-align:top;'>".implode("<br>", $message_details->content)."</td></tr>";
        $body[] = "</table>";
        $body[] = "</font></div>";

        # get content
        $mail_content_html  = $this->generate_message (implode("\r\n", $body));
        $mail_content_plain = $this->generate_message_plain (implode("\r\n", strip_tags(str_replace("<br>","\n",$body))));

        # try to send
        try {
        	$this->Php_mailer->setFrom($this->mail_settings->from);
        	foreach($recipients as $r) {
        	$this->Php_mailer->addAddress($r->email, addslashes(trim($r->real_name)));
        	}
        	$this->Php_mailer->Subject = $subject;
        	$this->Php_mailer->msgHTML($mail_content_html);
        	$this->Php_mailer->AltBody = $mail_content_plain;
        	//send
        	$this->Php_mailer->send();
        } catch (phpmailerException $e) {
        	$this->write_error ("Mailer Error: ".$e->errorMessage());
        } catch (Exception $e) {
        	$this->write_error ("Mailer Error: ".$e->errorMessage());
        }
    }

	/**
	 * Generates mail message
	 *
	 * @access public
	 * @param string $body
	 * @return string
	 */
	public function generate_message ($body) {
		$html[] = $this->set_header ();			//set header
		$html[] = $this->set_body_start ();		//start body
		$html[] = $body;						//set body
		$html[] = $this->set_footer ();			//set footer
		$html[] = $this->set_body_end ();		//end
		# return
		return implode("\n", $html);
	}

	/**
	 * Generates plain text mail
	 *
	 * @access public
	 * @param mixed $body
	 * @return void
	 */
	public function generate_message_plain ($body) {
		$html[] = $body;						//set body
		$html[] = $this->set_footer_plain ();	//set footer
	}

	/**
	 * set_header function.
	 *
	 * @access private
	 * @return string
	 */
	private function set_header () {
		$html[] = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>";
		$html[] = "<html><head>";
		$html[] = "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
		$html[] = "<meta name='viewport' content='width=device-width, initial-scale=0.7, maximum-scale=1, user-scalable=no'>";
		$html[] = "</head>";
		# return
		return implode("\n", $html);
	}

	/**
	 * Begins message body
	 *
	 * @access private
	 * @return string
	 */
	private function set_body_start () {
		return "<body style='margin:0px;padding:0px;background:#f9f9f9;border-collapse:collapse;'>";
	}

	/**
	 * Sets message body
	 *
	 * @access public
	 * @param mixed $body
	 * @return void
	 */
	public function set_body ($body) {
		return is_array($body) ? implode("\n", $body) : $body;
	}

	/**
	 * ends message body and html
	 *
	 * @access private
	 * @return string
	 */
	private function set_body_end () {
		return "</body></html>";
	}

	/**
	 * Sets footer
	 *
	 * @access public
	 * @return string
	 */
	public function set_footer () {
		$html[] = "<table style='margin-left:10px;margin-top:25px;width:auto;padding:0px;border-collapse:collapse;'>";
		$html[] = "<tr>";
		$html[] = "	<td><font face='Helvetica, Verdana, Arial, sans-serif' style='font-size:12px;'>E-mail</font></td>";
		$html[] = "	<td><font face='Helvetica, Verdana, Arial, sans-serif' style='font-size:12px;'><a href='mailto:".$this->mail_settings->from."' style='color:#08c;'>".$this->mail_settings->from."</a></font></td>";
		$html[] = "</tr>";
		$html[] = "<tr>";
		$html[] = "	<td><font face='Helvetica, Verdana, Arial, sans-serif' style='font-size:12px;'>www</font></td>";
		$html[] = "	<td><font face='Helvetica, Verdana, Arial, sans-serif' style='font-size:12px;'><a href='".$this->settings['url']."message/".base64_encode($this->message_details->msg)."/' style='color:#08c;'>".$this->settings['url']."message/".base64_encode($this->message_details->msg)."/</a></font></td>";
		$html[] = "</tr>";
		$html[] = "</table>";
		# return
		return implode("\n", $html);
	}

	/**
	 * Sets plain footer
	 *
	 * @access public
	 * @return string
	 */
	public function set_footer_plain () {
		return "\r\n------------------------------\r\n".$this->settings['mail']['from']." :: ".$this->settings['url'];
	}

}











/**
 * Pushover class to send pushover notifications to smartphone
 *
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 */
class pushover {

    /**
     * Pushover APP token
     *
     *
     * @var array
     * @access private
     */
    private $p_token = false;

    /**
     * Pushover grop / user key
     *
     * (default value: "giMGTjL9QPhcUNQQSNU5rSgsbtdCUc")
     *
     * @var string
     * @access private
     */
    private $p_key = false;

    /**
     * Pushover priority field
     *
     * (default value: 0)
     *
     * @var int
     * @access private
     */
    private $p_priority = 0;


    /**
     * __construct function.
     *
     * @access public
     * @param array $params (default: array())
     * @return void
     */
    public function __construct($params = array()) {
        // check
        if (!isset($params['pushover']['token']) && !isset($params['pushover']['key']))  { $this->write_error ("Error: Invalid pushover parameters"); ; }
        // save params
        $this->p_token = $params['pushover']['token'];
        $this->p_key =$params['pushover']['key'];
    }

    /**
     * Sets pushover alert to pushover service.
     *
     *  https://pushover.net/api
     *
     * @access public
     * @param mixed $message_details
     * @param mixed $recipients
     * @return void
     */
    public function send ($message_details, $recipients) {
        // set priority
        $this->set_pushover_priority ($message_details->severity);
        // init curl
        curl_setopt_array($ch = curl_init(), array(
            // set URL
            CURLOPT_URL => "https://api.pushover.net/1/messages.json",
            // set vars
            CURLOPT_POSTFIELDS => array(
                "token" => $this->p_token,
                "user" => $this->p_key,
                "title" => $message_details->hostname." [".$message_details->severity."] ".$message_details->msg,
                "priority" => $this->p_priority,
                "message" => implode("\n", $message_details->content)
            ),
            CURLOPT_SAFE_UPLOAD => true,
            )
        );
        // send
        curl_exec($ch);
        // close
        curl_close($ch);
    }

    /**
     * Sets pushover priority
     *
     * @access private
     * @param mixed $severity
     * @return void
     */
    private function set_pushover_priority ($severity) {
        if ($severity=="emergency")          { $this->p_priority =  2; }
        elseif ($severity=="alert")          { $this->p_priority =  1; }
        elseif ($severity=="critical")       { $this->p_priority =  1; }
        elseif ($severity=="error")          { $this->p_priority =  0; }
        elseif ($severity=="warning")        { $this->p_priority =  0; }
        elseif ($severity=="notice")         { $this->p_priority = -1; }
        elseif ($severity=="informational")  { $this->p_priority = -2; }
        elseif ($severity=="debug")          { $this->p_priority = -2; }
        else                                 { $this->p_priority =  0; }
    }
}




/**
 * slack class to send pushover notifications to slack via webhooks
 *
 *
 * @author: Miha Petkovsek <miha.petkovsek@gmail.com>
 */
class slack {

    /**
     * slack APP URL
     *
     *
     * @var array
     */
    private $url = false;

    /**
     * Pushover webhook key
     *
     * (default value: false)
     *
     * @var string
     */
    private $key = false;

    /**
     * Channel to send to
     *
     * @var string
     */
    private $channel = "";

    /**
     * Mentions
     *
     * @var string
     */
    private $mentions = "";

    /**
     * Username to send via
     *
     * note: Enable Overriding of Usernames from Webhooks must be enabled
     *
     * @var string
     */
    private $username = "php-snmptraps";

    /**
     * Color
     *
     * @var string
     */
    private $color = "#333333";


    /**
     * __construct function.
     *
     * @access public
     * @param array $params (default: array())
     * @return void
     */
    public function __construct($params = array()) {
        // import parameters from config file
        include(dirname(__FILE__)."/../../config.php");
        $this->settings = array();
        $this->settings = array("url"=>$url);

        // check
        if (!isset($params['slack']['url']) && !isset($params['slack']['key']))  { $this->write_error ("Error: Invalid slack parameters"); ; }
        // save params
        $this->url = $params['slack']['url'];
        $this->key = $params['slack']['key'];
        // set optional params
        if(isset($params['slack']['channel'])) {
            if(strlen($params['slack']['channel'])>0) {
                $this->channel = $params['slack']['channel'];
            }
        }
        if(isset($params['slack']['mentions'])) {
            if(sizeof($params['slack']['mentions'])>0) {
                foreach ($params['slack']['mentions'] as $m) {
                    $this->mentions .= $m." ";
                }
            }
        }
        if(isset($params['slack']['username'])) {
            if(strlen($params['slack']['username'])>0) {
                $this->username = $params['slack']['username'];
            }
        }
    }

    /**
     * Sets POST alert to webhooks service.
     *
     *  https://your_url/hooks/hooks_key
     *
     *  test: curl -i -v -X POST -d 'payload={"text": "New trap received."}' https://your_url/hooks/your_key
     *
     * @access public
     * @param mixed $message_details
     * @param mixed $recipients
     * @return void
     */
    public function send ($message_details, $recipients) {
        // set color
        $this->set_color($message_details->severity);
        // set payload
        $payload = array (
                            "username"    => $this->username,
                            "fallback"    => $this->mentions." [".$message_details->severity."] ".$message_details->hostname." ".$message_details->oid."\n".implode("\n", $message_details->content),
                            "mrkdwn"      => true,
                            "text"        => $this->mentions." new snmp trap received",
                            "attachments" => array (
                                                   array (
                                                            "color"      => $this->color,
                                                            "title"      => "[".$message_details->severity."] ".$message_details->hostname." ".$message_details->oid,
                                                            "title_link" => $this->settings['url']."message/".base64_encode($message_details->msg)."/",
                                                            "text"       => "* ".implode("\n* ", $message_details->content)
                                                        )
                                                   )
                          );
        // set channel
        if($this->channel != "") {
            $payload['channel'] = $this->channel;
        }
        // to json
        $payload = "payload=".json_encode($payload);
        // init curl
        curl_setopt_array($ch = curl_init(), array(
            // set URL
            CURLOPT_URL => $this->url.$this->key,
            CURLOPT_POSTFIELDS => $payload
            )
        );
        // send
        curl_exec($ch);
        // close
        curl_close($ch);
    }

    /**
     * Sets color to links
     *
     * @method set_color
     * @param  string    $s
     */
    private function set_color ($s) {
        if ($s=="emergency" || $s=="alert" || $s=="critical")       { $this->color = "#A7254E"; }
        elseif ($s=="error" || $s=="warning")                       { $this->color = "#D66300"; }
        elseif ($s=="notice"|| $s=="debug" || $s=="informational")  { $this->color = "#66AA00"; }
        else                                                        { $this->color = "#3366CC"; }
    }
}

?>