<?php
/*
*
*  class-wic-form-advanced-search.php
*
*/

class WIC_Form_Advanced_Search extends WIC_Form_Parent  {

	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		$button_args_main = array(
			'entity_requested'			=> 'advanced_search',
			'action_requested'			=> 'form_search',
			'button_label'				=> __('Search', 'wp-issues-crm')
		);	
		
		$button = $this->create_wic_form_button ( $button_args_main );
			
		return ( $button  ) ;
	}	
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		$formatted_message = sprintf ( __('Advanced Search. ' , 'wp-issues-crm') )  . $message;
		return ( $formatted_message );
	}

	// hooks not implemented
	protected function post_form_hook( &$data_array ) {}
	public static function format_name_for_title ( &$data_array ) {}
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}

}