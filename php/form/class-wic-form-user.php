<?php
/*
*
*  class-wic-form-user-update.php
*
*/

class WIC_Form_User extends WIC_Form_Parent  {

	// used in a page in the email settings form that is hooked to update the user signature 
	public function prepare_settings_form( &$data_array ) { 
		
		global $wic_db_dictionary;
		$current_user = wp_get_current_user();	
		$group_output = 
			'<div id="currently_logged_in">Signature for ' . esc_html( $current_user->display_name ) . '</div>' .
			'<div id = "signature-editor-wrapper">';
				// one group and one field		
				$groups = $this->get_the_groups(); 
				foreach ( $groups as $group ) { 
					$group_fields =  $wic_db_dictionary->get_fields_for_group ( $this->get_the_entity(), $group->group_slug ); 
					$group_output .= $this->the_controls ( $group_fields, $data_array );
				} // close foreach group		
		$group_output .= 
			'</div>';
			
		return $group_output;
			
	}
	


	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		$button_args_main = array(
			'entity_requested'			=> 'user',
			'action_requested'			=> 'form_save_update',
			'button_label'				=> __('Update', 'wp-issues-crm')
		);	
		return ( $this->create_wic_form_button ( $button_args_main ) ) ;
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		$display_name = self::format_name_for_title ( $data_array );
		$formatted_message = sprintf ( __('Set Preferences for %1$s. ' , 'wp-issues-crm'), $display_name )  . $message;
		return ( $formatted_message );
	}
	
	// support function for message
	public static function format_name_for_title ( &$data_array ) {
	
		return  ( $data_array['display_name']->get_value() );
	}
	
	protected function group_screen( $group ) {  
		return true;
	}
	
	// special group handling for the comment group
	protected function group_special ( $group ) {
		return ( false );	
	}

	protected function pre_button_messaging ( &$data_array ){}

	
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function post_form_hook ( &$data_array ) {
		echo '<p class="wic-form-legend user-legend">Access main WP Issues CRM Configure <a target="_blank" href="' . admin_url() . 'admin.php?page=wp-issues-crm-settings">here.</a></p>';
	}
	 	
}