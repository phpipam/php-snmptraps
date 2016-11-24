<?php




/**
 *  @print SNMP tables ---------------------
 */
class Table_print_snmp {

    /**
     * Resut object
     *
     * @var mixed
     * @access protected
     */
    protected $Result;

    /**
     * Default print limit
     *
     * (default value: 100)
     *
     * @var int
     * @access public
     */
    public $print_limit = 100;

    /**
     * Table fields to display
     *
     * @var mixed|array
     * @access public
     */
    public $tfields;


    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct() {
        # Result
        $this->Result = new Result ();
        # default print headers
        $this->set_default_table_fields ();
    }

    /**
     * Sets default table fields
     *
     * @access protected
     * @return void
     */
    protected function set_default_table_fields () {
        $this->tfields = array(
                                "id"=>"ID",
                                "hostname"=>"Hostname",
                                "ip"=>"IP address",
                                "oid"=>"OID",
                                "date"=>"Date",
                                "message"=>"Message",
                                "severity"=>"Severity",
                                "content"=>"Content"
                                );
    }

    /**
     * Resets table fields
     *
     * @access public
     * @param array $fields (default: array())
     * @return void
     */
    public function set_snmp_table_fields ($fields=array()) {
        $this->tfields = $fields;
    }

    /**
     * Resets visible fields to default
     *
     * @access public
     * @return void
     */
    public function reset_snmp_fields () {
        $this->set_default_table_fields ();
    }

    /**
     * Prints SNMP table
     *
     * @access public
     * @param (obj) $table
     * @param bool $headers
     * @param bool $tbody       //needed for live update
     * @param bool $newClass    //needed for live update
     * @param bool $fullScreen    //needed for stretch
     * @return void
     */
    public function print_snmp_table ($table, $headers = true, $tbody = true, $newClass = false, $fullScreen = false) {
        //headers
        if ($headers)
        $this->print_snmp_table_headers ($fullScreen);
        //table
        $this->print_snmp_table_content ($table, $tbody, $newClass, $fullScreen);
    }

    /**
     * Prints single snmp item
     *
     * @access public
     * @param mixed $item
     * @param bool $return
     * @return void
     */
    public function print_snmp_item ($item, $return = false) {
        // append actions
        if (array_key_exists("actions", $this->tfields)) { $item->actions = $item->id; }
        //set severity
        $severity = $this->set_severity_class ($item->severity, false);
        // format
        $item = $this->format_snmp_table_content ($item, true);
        // print if some
        $html = array();
        foreach ($this->tfields as $k=>$l) {
            // add hr for raw
            if($k=="content")   { $item->$k = "<hr>".$item->$k; }
            elseif($k=="raw")   { $item->$k = "<hr>".$item->$k."<hr>"; }

            $html[] = "<tr class='$severity'>";
            $html[] = "<td style='vertical-align: middle !important;' class='field-$k'>$l</td>";
            $html[] = "<td class='field-$k'>".$item->$k."</td>";
            $html[] = "</tr>";
        }
        // join and print
        if ($return)    { return implode("\n", $html); }
        else            { print implode("\n", $html); }
    }

    /**
     * Prints table headers
     *
     * @access protected
     * @param mixed $fullScreen
     * @return void
     */
    protected function print_snmp_table_headers ($fullScreen) {
        $html[] = "<thead>";
        $html[] = "<tr>";
        // headers
        foreach ($this->tfields as $k=>$f) {
            // set hidden
            $hidden = in_array($k, array("ip", "content", "header-full_screen")) ? "hidden-xs" : "";
            // save
            $html[] = "<th id='header-$k' class='$hidden'>$f</th>";
        }
        if($fullScreen)
        $html[] = "<th class='header-full_screen disable-sorting'><i class='fa fa-arrows-alt'></i></th>";
        $html[] = "</tr>";
        $html[] = "</thead>";
        // join and print
        print implode("\n", $html);
    }

    /**
     * Prints table1
     *
     * @access private
     * @param mixed $table
     * @param bool $tbody       //needed for live update
     * @param bool $newClass    //needed for live update
     * @param mixed $fullScreen
     * @return void
     */
    private function print_snmp_table_content ($table, $tbody = true, $newClass = false, $fullScreen) {
        // start
        if($tbody)
        $html[] = "<tbody>";
        // if some
        if (sizeof($table)>0 && $table!==false) {
            // headers
            foreach ($table as $f) {
                // append actions
                if (array_key_exists("actions", $this->tfields)) { $f->actions = $f->id; }
                // set severity
                $severity = $this->set_severity_class ($f->severity, $newClass);
                // save
                $html[] = "<tr class='$severity tooltip2' data-id='$f->id' title='".str_replace("\n", "<br>", htmlspecialchars($f->content, ENT_QUOTES))."'>";
                // format
                $f = $this->format_snmp_table_content ($f, false, $newClass);
                // loop
                foreach ($this->tfields as $k=>$l) {
                    // set hidden
                    $hidden = in_array($k, array("ip", "content", "header-full_screen")) ? "hidden-xs" : "";
                    // save
                    $html[] = "<td class='field-$k $hidden'>".$f->$k."</td>";
                }
                // fullscreen
                if ($fullScreen)
                $html[] = "<td></td>";
                $html[] = "</tr>";
            }
        }
        else {
            // calculate size
            $size = $fullScreen ? sizeof($this->tfields)+1 : sizeof($this->tfields);
            // save
            $html[] = "<tr>";
            $html[] = "  <td colspan='$size'>";
            $html[] = $this->Result->show("info", "No records found", false, false, true);
            $html[] = "  </td>";
            $html[] = "</tr>";
        }
        if($tbody)
        $html[] = "</tbody>";
        // join and print
        print implode("\n", $html);
    }

    /**
     * Sets tr class
     *
     * @access private
     * @param mixed $s
     * @param bool $newClass    //needed for live update
     * @return void
     */
    private function set_severity_class ($s, $newClass) {
        // red
        if ($s=="emergency" || $s=="alert" || $s=="critical")       { $c = "danger"; }
        elseif ($s=="error" || $s=="warning")                       { $c = "warning"; }
        elseif ($s=="notice"|| $s=="debug" || $s=="informational")  { $c = "success"; }
        else                                                        { $c = "info"; }
        // newclass ?
        return $newClass ? $c." new" : $c;
    }

    /**
     * Formats table line
     *
     * @access private
     * @param mixed $line
     * @param mixed $single     // print single item
     * @param mixed $newClass
     * @return void
     */
    private function format_snmp_table_content ($line, $single = false, $newClass = false) {
        // format
        foreach ($line as $k=>$v) {
            if ($k=="severity")        { $line->severity = $this->format_snmp_table_severity ($v, $single); }
            elseif ($k=="hostname")    { $line->hostname = $this->format_snmp_table_hostname ($v, $single); }
            elseif ($k=="date")        { $line->date     = $this->format_snmp_table_date ($v, $single); }
            elseif ($k=="message")     { $line->message  = $this->format_snmp_table_message ($v, $single); }
            elseif ($k=="content")     { $line->content  = $this->format_snmp_table_content_breaks ($v, $single); }
            elseif ($k=="raw")         { $line->raw      = $this->format_snmp_table_content_breaks ($v, $single); }
            elseif ($k=="id")          { $line->id       = $this->format_snmp_table_id ($v, $single); }
            elseif ($k=="actions")     { $line->actions  = $this->format_snmp_table_actions ($v); }
        }
        // table
        return $line;
    }

    /**
     * Formats hostname
     *
     * @access private
     * @param mixed $severity
     * @param bool $single
     * @return void
     */
    private function format_snmp_table_severity ($severity, $single) {
        return $single ? "<span class='badge badge1 alert-".$this->set_severity_class ($severity, false)."'>$severity</span>" : "<span class='badge badge1'>$severity</span>";
    }

    /**
     * Format hostname - link
     *
     * @access private
     * @param mixed $hostname
     * @param mixed $single
     * @return void
     */
    private function format_snmp_table_hostname ($hostname, $single) {
        return "<strong><a href='host/$hostname/'>$hostname</a></strong>";
    }

    /**
     * Formats date format.
     *
     * @access private
     * @param mixed $date
     * @param mixed $single
     * @return void
     */
    private function format_snmp_table_date ($date, $single) {
        // reformat
        $date = date("d/m H:i:s", strtotime($date));
        // return
        return $date;
    }

    /**
     * Format message - create link
     *
     * @access private
     * @param mixed $msg
     * @return void
     */
    private function format_snmp_table_message ($msg, $single) {
        return "<strong><a href='message/".base64_encode($msg)."/'>$msg</a></strong>";
    }

    /**
     * Reformats line breaks in content.
     *
     * @access private
     * @param mixed $content
     * @return void
     */
    private function format_snmp_table_content_breaks ($content, $single) {
        return implode("<br>",array_filter(explode("\n", $content)));
        return str_replace("\n", "<br>", $content);
    }

    /**
     * Adds link
     *
     * @access private
     * @param mixed $id
     * @return void
     */
    private function format_snmp_table_id ($id, $single) {
        return $single ? $id :  "<div class='btn-group'><a class='btn btn-default btn-xs load-modal-big' href='app/trap/popup.php?id=$id'><i class='fa fa-eye'></i></a><a class='btn btn-default btn-xs' href='trap/$id/'><i class='fa fa-angle-right' style='width:12px;'></i></a></div>";
    }

    /**
     * Append actions
     *
     * @access private
     * @param mixed $id
     * @return void
     */
    private function format_snmp_table_actions ($id) {
        $html[] = "<div class='btn-group'>";
        $html[] =  "<a class='btn btn-default btn-xs load-modal' href='app/message/edit.php?action=define&id=$id'><i class='fa fa-cogs'></i></a> ";
        $html[] =  "<a class='btn btn-default btn-xs load-modal' href='app/message/edit.php?action=ignore&id=$id'><i class='fa fa-volume-off'></i></a> ";
        $html[] =  "<a class='btn btn-default btn-xs load-modal' href='app/message/edit.php?action=delete&id=$id'><i class='fa fa-remove'></i></a> ";
        $html[] = "</div>";
        // return
        return implode("\n", $html);
    }

}


/**
 * Generic print.
 *
 * @extends Table_print_snmp
 */
class Table_print extends Table_print_snmp {

    /**
     * Field not to dupicate JS scripts for datetime
     *
     * (default value: false)
     *
     * @var bool
     * @access public
     */
    public $datetimeset = false;

    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct() {
        # Result
        $this->Result = new Result ();
        # default print headers
        $this->set_default_table_fields ();
    }


    /**
     * Prints generic table
     *
     * @access public
     * @param (obj) $table_content
     * @param bool $headers
     * @param mixed $script     // which script to target via modal
     * @param mixed $target     // which script to target via modal
     * @return void
     */
    public function print_table ($table_content, $headers = true, $script, $target) {
        //headers
        if ($headers)
        $this->print_table_headers ();
        //table
        $this->print_table_content ($table_content, $script, $target);
    }

    /**
     * Prints table headers
     *
     * @access protected
     * @return void
     */
    protected function print_table_headers () {
        $html[] = "<thead>";
        $html[] = "<tr>";
        // headers
        foreach ($this->tfields as $k=>$f) {
            $html[] = "<th id='header-$f'>".ucwords($f)."</th>";
        }
        $html[] = "</tr>";
        $html[] = "</thead>";
        // join and print
        print implode("\n", $html);
    }

    /**
     * Prints generic table
     *
     * @access private
     * @param mixed $table_content
     * @param mixed $script
     * @return void
     */
    private function print_table_content ($table_content, $script, $target) {
        // start
        $html[] = "<tbody>";
        // if some
        if (sizeof($table_content)>0 && $table_content!==false) {
            // print
            foreach ($table_content as $f) {
                $html[] = "<tr>";
                // loop
                foreach ($this->tfields as $k=>$l) {
                    // format actions
                    if ($k=="actions") {
                        $f->actions = $this->format_table_actions ($script, $target, $f->id);
                    }
                    // replace ; with breaks
                    if ($l=="notification_severities" || $l=="notification_types" || $l=="hostnames") {
                        $f->$l = "&middot; ".str_replace(";", "<br>&middot; ", $f->$l);
                    }
                    // save
                    $html[] = "<td class='field-$k'>".$f->$l."</td>";
                }
                $html[] = "</tr>";
            }
        }
        else {
           $html[] = "<tr>";
           $html[] = "  <td colspan='".sizeof($this->tfields)."'>";
           $html[] = $this->Result->show("info", "No records found", false, false, true);
           $html[] = "  </td>";
           $html[] = "</tr>";
        }
        $html[] = "</tbody>";
        // join and print
        print implode("\n", $html);
    }

    /**
     * Append actions
     *
     * @access private
     * @param mixed $id
     * @return void
     */
    private function format_table_actions ($script, $target_table, $id) {
        // check
        if (file_exists($script)) {
            $html[] = "<div class='btn-group'>";
            $html[] =  "<a class='btn btn-default btn-xs load-modal' href='$script?script=$target_table&action=edit&id=$id'><i class='fa fa-pencil'></i></a> ";
            $html[] =  "<a class='btn btn-default btn-xs load-modal' href='$script?script=$target_table&action=delete&id=$id'><i class='fa fa-remove'></i></a> ";
            $html[] = "</div>";
        }
        else {
            $html[] = "";
        }
        // return
        return implode("\n", $html);
    }

    /**
     * Creates form item based on $field->type
     *
     *    ["Field"]=> "hostname"
     *    ["Type"]=> "varchar(64)"
     *    ["Null"]=> "YES"
     *    ["Key"]=>  ""
     *    ["Default"]=> NULL
     *    ["Extra"]=> string(0) ""
     *
     * @access public
     * @param object $field
     * @param bool|mixed $value
     * @param array $additional_params (default: false)
     * @return void
     */
    public function prepare_input_item ($field, $value = false, $additional_params = false) {
        // severities override
        if ($field->Field=="notification_severities" || $field->Field=="notification_types") {
            return $this->prepare_multiple_checkboxes ($field, $value, $additional_params);
        }
        // varchar
        elseif (strpos($field->Type, "varchar")!==false) {
            return $this->prepare_varchar_input ($field, $value);
        }
        // enum, set
        elseif (substr($field->Type, 0,3) == "set" || substr($field->Type, 0,4) == "enum") {
            return $this->prepare_set_input ($field, $value);
        }
        // time
        elseif ($field->Type == "time") {
            return $this->prepare_time_input ($field, $value);
        }
        // date, datetime
        elseif ($field->Type == "date" || $field->Type == "datetime") {
            return $this->prepare_date_input ($field, $value);
        }
        // text
        elseif($field->Type == "text") {
            return $this->prepare_text_input ($field, $value);
        }
        // boolean
        elseif($field->Type == "tinyint(1)") {
            return $this->prepare_bool_input ($field, $value);
        }
        // default
        else {
            return $this->prepare_varchar_input ($field, $value);
        }
    }

    /**
     * Prepares multiple selections
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @param array $additional_params
     * @return void
     */
    private function prepare_multiple_checkboxes ($field, $value, $additional_params) {
        // get all possible items
        if ($field->Field=="notification_severities")   {
            $options =  array('emergency','alert','critical','error','warning','notice','informational','debug', 'unknown');
        }
        elseif ($field->Field=="notification_types") {
            include(dirname(__FILE__)."/../../config.php");
            $options = $notification_methods;
        }
        elseif ($field->Field=="hostnames") {
            $options = $additional_params;
        }
        else {
            return "";
        }

        // values
        $values_parsed = explode(";", $value);

        // print
        $html = array();
        foreach ($options as $o) {
            // cehcked
            $checked = in_array($o, $values_parsed) ? "checked" : "";
            // print
            $html[] = "<input type='checkbox' name='".$field->Field."-".$o."' $checked> $o<br>";
        }
        // return
        return implode("", $html);
    }

    /**
     * Prepares varchar input and DEFAULT input.
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @return void
     */
    private function prepare_varchar_input ($field, $value) {
        // value
        $value = $value===false ? "" : "value='$value'";
        // content
        return "<input type='text' class='form-control input-sm' name='$field->Field' $value>";
    }

    /**
     * Prepares SET and ENUM input
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @return void
     */
    private function prepare_set_input ($field, $value) {
        //parse values
        $tmp = substr($field->Type, 0,3)=="set" ? explode(",", str_replace(array("set(", ")", "'"), "", $field->Type)) : explode(",", str_replace(array("enum(", ")", "'"), "", $field->Type));
        //null
        if($field->{'Null'}!="NO") { array_unshift($tmp, ""); }

        $html[] = "<select name='$field->Field' class='form-control input-sm input-w-auto'>";
        foreach($tmp as $v) {
            if($v==@$value)    { $html[] = "<option value='$v' selected='selected'>$v</option>"; }
            else               { $html[] = "<option value='$v'>$v</option>"; }
        }
        $html[] = "</select>";

        // content
        return implode("", $html);
    }

    /**
     * Prepares time input.
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @return void
     */
    private function prepare_time_input ($field, $value) {
        // value
        $value = $value===false ? "" : "value='$value'";

        // just for first
        if($this->datetimeset===false) {
            $html = $this->prepare_datetime_js ();
            // set flag
            $this->datetimeset = true;
        }

        //set size
        if($field->Type == "date")  { $size = 10; $class='datepicker';        $format = "yyyy-MM-dd"; }
        else                        { $size = 19; $class='datetimepicker';    $format = "yyyy-MM-dd"; }
        $size = 8;
        $format = "";


        //field
        $html[] = ' <input type="text" class="timepicker form-control input-sm input-w-auto" data-format="'.$format.'" name="'. $field->Field .'" maxlength="'.$size.'" '.$value.'>'. "\n";
        // content
        return implode("", $html);
    }


    /**
     * Prepares datetime input.
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @return void
     */
    private function prepare_date_input ($field, $value) {
        // value
        $value = $value===false ? "" : "value='$value'";

        // init
        $html = array();

        // just for first
        if($this->datetimeset===false) {
            $html = $this->prepare_datetime_js ();
            // set flag
            $this->datetimeset = true;
        }

        //set size
        if($field->Type == "date")  { $size = 10; $class='datepicker';        $format = "yyyy-MM-dd"; }
        else                        { $size = 19; $class='datetimepicker';    $format = "yyyy-MM-dd"; }

        //field
        $html[] = ' <input type="text" class="'.$class.' form-control input-sm input-w-auto" data-format="'.$format.'" name="'. $field->Field .'" maxlength="'.$size.'" '.$value.'>'. "\n";
        // content
        return implode("", $html);
    }

    /**
     * Prepares JS for datetime.
     *
     * @access private
     * @return void
     */
    private function prepare_datetime_js () {
        $html[] = '<link rel="stylesheet" type="text/css" href="css/bootstrap/bootstrap-datetimepicker.min.css">';
        $html[] = '<script type="text/javascript" src="js/bootstrap-datetimepicker.min.js"></script>';
        $html[] = '<script type="text/javascript">';
        $html[] = '$(document).ready(function() {';
        //time only
        $html[] = '    $(".timepicker").datetimepicker( {pickDate: false, pickTime: true, pickSeconds: true });';
        //date only
        $html[] = '    $(".datepicker").datetimepicker( {pickDate: true, pickTime: false, pickSeconds: false });';
        //date + time
        $html[] = '    $(".datetimepicker").datetimepicker( { pickDate: true, pickTime: true } );';
        $html[] = '})';
        $html[] = '</script>';
        // ok
        return $html;
    }

    /**
     * Prepares text input - textarea.
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @return void
     */
    private function prepare_text_input ($field, $value) {
         // value
        $value = $value===false ? "" : $value;
        // content
        return '<textarea class="form-control input-sm" name="'. $field->Field .'" rowspan=3>'. $value. '</textarea>';
    }

    /**
     * Prepares boolean inputs.
     *
     * @access private
     * @param mixed $field
     * @param mixed $value
     * @return void
     */
    private function prepare_bool_input ($field, $value) {
        $html[] = "<select name='$field->Field' class='form-control input-sm input-w-auto'>";
        $tmp = array(0=>"No",1=>"Yes");
        //null
        if($field->{'Null'}!="NO") { $tmp[2] = ""; }

        foreach($tmp as $k=>$v) {
            if(strlen(@$value)==0 && $k==2)    { $html[] = "<option value='$k' selected='selected'>"._($v)."</option>"; }
            elseif($k==@$value)                { $html[] = "<option value='$k' selected='selected'>"._($v)."</option>"; }
            else                               { $html[] = "<option value='$k'>"._($v)."</option>"; }
        }
        $html[] = "</select>";

        // content
        return implode("", $html);
    }

    /**
     * Prints add new button that opens modal
     *
     * @access public
     * @param mixed $script
     * @param mixed $target_table
     * @param string $text (default: "Create new")
     * @param string $icon (default: "fa-plus")
     * @return void
     */
    public function print_add_item ($script, $target_table, $text = "Create new", $icon = "fa-plus") {
        print "<a class='btn btn-success btn-sm load-modal' href='$script?script=$target_table&action=add'><i class='fa $icon'></i> $text</a>";
    }

}


?>
