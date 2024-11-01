<?php
/*
*
*  class-wic-form-external.php
*
*/

class WIC_Form_External extends WIC_Form_Parent  {
	
	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons ( $data_array ) . '<a href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-externals">' . __( 'Back to External Interface List', 'wp-issues-crm' ) . '</a>');
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		return ( $this->get_the_verb( $data_array ) . __(' External Interface. ', 'wp-issues-crm') . $message );
	}

	protected function group_special( $group_slug ) { 
		return  'delete' == $group_slug;	
	}
	

	protected function group_special_delete ( &$doa ) {
		$button_args_main = array(
			'entity_requested'			=> 'constituent',
			'action_requested'			=> 'delete',
			'button_label'				=> 'Delete',
			'type'						=> 'button',
			'name'						=> 'wic-external-delete-button',
			'id'						=> 'wic-external-delete-button',
			'title'						=>  0 == $doa['ID']->get_value() ? 'No interface saved' : 'Start delete dialog.',
			'value'						=> $doa['ID']->get_value(),
			'disabled'					=> 0 == $doa['ID']->get_value(),
		);	
		return $this->create_wic_form_button ( $button_args_main ) ;
	}

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 

}