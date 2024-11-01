<?php
/*
*
*  class-wic-form-activity.php
*
*  a replacement for multivalue model -- list with pop-up form
*
*/

class WIC_Form_Activity extends WIC_Form_Parent  {

	public function __construct () {
		$this->entity = 'activity';
	}
	
	protected function group_screen ( $group ) {
		return true;
	}

	public static function create_wic_activity_area () {
		return '<div id="activity-area-ajax-loader"> ' . 'Loading constituent activities ' .
					'<img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) . '">' .
				'</div>' .
				'<div id="wic-activity-area"></div>';	 
	}

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_buttons( &$data_array ){}
	protected function format_message ( &$data_array, $message ) {}	
	protected function group_special( $group ) {}
	protected function pre_button_messaging ( &$data_array ){}
    protected function post_form_hook ( &$data_array ) {}  

}