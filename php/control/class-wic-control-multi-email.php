<?php
/*
* wic-control-multi-email.php
*
*/
class WIC_Control_Multi_Email extends WIC_Control_Text {

	public function form_control () { 
		$this->default_control_args['input_class'] .= ' constituent-email wic-autocomplete ';
		return (
			'<div class="multi-email-wrapper">' .
				'<div class="multi-email-input-item" id="' . $this->get_field_slug() . '-multi-mail-item">' . parent::form_control() . '</div>' . 
			'</div>' 
		);	
	}
}


