<?php
/*
* wic-control-autocomplete.php
*
*/
class WIC_Control_Autocomplete extends WIC_Control_Text {

	public function form_control () { 
		$this->default_control_args['input_class'] .= ' wic-autocomplete ';
		return ( parent::form_control() );	
	}

		
}


