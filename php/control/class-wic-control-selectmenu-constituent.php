<?php
/*
* wic-control-selectmenu-constituent.php
*
*/
class WIC_Control_Selectmenu_Constituent extends WIC_Control_Selectmenu {

	public function create_options_array ( $control_args ) {
		return array();
	}

	protected static function identify_additional_values_source() {
		return 'constituent';
	}
		
}


