<?php

/**
 *
 * Bootstrap modal class
 *
 */

class Modal {

    /**
     *  @modal print ---------------------
     */

    /**
     * Print modal item.
     *
     * @access public
     * @param mixed $header
     * @param mixed $content
     * @param string $footer_text (default: "Save")
     * @param mixed $action_script
     * @return void
     */
    public function modal_print ($header, $content, $footer_text = "Save", $action_script) {
        // set html
        $html[] = $this->modal_header ($header);
        $html[] = $this->modal_body ($content);
        $html[] = $this->modal_footer ($footer_text, $action_script);
        $html[] = $this->modal_action ($action_script);
        // print
        print implode("\n", $html);
    }

    /**
     * Set modal header.
     *
     * @access private
     * @param mixed $header (default: null)
     * @return void
     */
    private function modal_header ($header = null) {
        // define
        $html = array();
        // null
        if(is_null($header))    { $header = "Naslov"; }
        // set html
        $html[] = "<div class='modal-header'>";
        $html[] = " <button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>";
        $html[] = " <h4 class='modal-title' id='myModalLabel'>";
        $html[] = $header;
        $html[] = " </h4>";
        $html[] = "</div>";
        // return content
        return implode("\n", $html);
    }

    /**
     * Set modal content.
     *
     * @access private
     * @param mixed $content
     * @return void
     */
    private function modal_body ($content) {
        // define
        $html = array();
        // set html
        $html[] = "<div class='modal-body'>";
        $html[] = $content;
        $html[] = "</div>";
        // return content
        return implode("\n", $html);
    }

    /**
     * Set modal footer.
     *
     * @access private
     * @param mixed $footer_text
     * @return void
     */
    private function modal_footer ($footer_text) {
        // define
        $html = array();
        // set html
        $html[] = "<div class='modal-footer'>";
        $html[] = " <div class='btn-group'>";
        $html[] = "     <button type='button' class='btn btn-sm btn-default' data-dismiss='modal'>Close</button>";
        if (strlen($footer_text)>0)
        $html[] = "     <button type='button' class='btn btn-sm btn-primary modal-execute'>$footer_text</button>";
        $html[] = " </div>";
        $html[] = " <div class='modal-result text-left' style='margin-top:10px;display:none'></div>";
        $html[] = "</div>";
        // return content
        return implode("\n", $html);
    }

    /**
     * Set modal JS action.
     *
     * @access private
     * @param mixed $action_script
     * @return void
     */
    private function modal_action ($action_script) {
        // define
        $html = array();
        // set JS for save
        if (strlen($action_script)>0) {
        $html[] = "<script type='text/javascript'>";
        $html[] = "$(document).ready(function() {";
		$html[] = "$('.modal-execute').click(function () {";
		$html[] = " $('.loading').fadeIn('fast')";
		$html[] = "	var postdata = $('#modal-form').serialize();";
		$html[] = "	$.post('$action_script', postdata, function(data) {";
		$html[] = "		$('#modal1 .modal-result').html(data).fadeIn('fast');";
		$html[] = "     if(data.search('alert-danger')===-1 && data.search('alert-warning')===-1) {";
		$html[] = "         setTimeout(function (){window.location.reload();}, 1500);";
		$html[] = "     } else {";
		$html[] = "         $('.loading').fadeOut('fast')";
		$html[] = "     } ";
		$html[] = "	});";
		$html[] = "	return false;";
		$html[] = "});";
		$html[] = "})";
		$html[] = "</script>";
		}
        // return content
        return implode("\n", $html);
    }

}


?>