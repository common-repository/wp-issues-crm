<?php
/*
*
*  class-wic-form-constituent.php
*
*/

class WIC_Form_Constituent extends WIC_Form_Parent  {

	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons( $data_array ) );
	}	
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) { 
		$title = self::format_name_for_title ( $data_array );
		$formatted_message = $this->get_the_verb( $data_array ) . ' ' . ' ' . $title . '. ' .  $message;
		return ( $formatted_message );
	}

	// support function for message
	public static function format_name_for_title ( &$data_array ) {

		// construct title starting with first name
		$title = 	$data_array['first_name']->get_value(); 
		// if present, add last name, with a space if also have first name		
		$title .= 	( '' == $data_array['last_name']->get_value() ) ? '' : ( ( $title > '' ? ' ' : '' ) . $data_array['last_name']->get_value() );
		// if still empty and email may be available, add email
			// note, the following phrase is broken down for older version of php:
			// if ( '' == $title && isset( $data_array['email']->get_value()[0] ) ) {
			$control = $data_array['email'];
			$result = $control->get_value();
			$email_available = isset( $result[0] );
		if ( '' == $title && $email_available ) {
			// $title = $data_array['email']->get_value()[0]->get_email_address();
			$title = $result[0]->get_email_address();
		} 
		// if still empty, insert word constitent
		$title =		( '' == $title ) ? __( 'Constituent', 'wp-issues-crm' ) : $title;
		
		return  ( $title );
	}
	
	// special group handling for the comment group
	protected function group_special ( $group ) {
		return 'activity' == $group || 'delete' == $group || 'dedup' == $group;	
	}

	// function to be called for special group
	protected function group_special_dedup ( &$doa ) {
		return '';
	}
	
	// function to be called for special group
	protected function group_special_activity ( &$doa ) {
		return WIC_Form_Activity::create_wic_activity_area();
	}

	protected function group_special_delete ( &$doa ) {
		$button_args_main = array(
			'entity_requested'			=> 'constituent',
			'action_requested'			=> 'delete',
			'button_label'				=> 'Delete/Dedup',
			'type'						=> 'button',
			'name'						=> 'wic-constituent-delete-button',
			'id'						=> 'wic-constituent-delete-button',
			'title'						=>  0 == $doa['ID']->get_value() ? 'No constituent to delete' : 'Start hard delete dialog.',
			'value'						=> $doa['ID']->get_value(),
			'disabled'					=> 0 == $doa['ID']->get_value(),
		);	
		return $this->create_wic_form_button ( $button_args_main ) ;
	}

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array )  {
		// slot for constituent form and contents of delete dialog box
		echo '<div id="hidden-blank-activity-form"></div>'  . 
			'<div id="constituent_delete_shell"><h4>' . 'Immediately and permanently delete this constituent and associated activities?' . '</h4>' .
				'<p>If you select a second constituent below, when you delete the current constituent, information will be transferred to the second constituent:' . '</p>' . 
				'<ol><li>All activity records will be reassigned to the second constituent.</li>' .
					'<li>All address, phone, and email subrecords will be added to the second constituent; they will not overlay any existing data for the second constituent.</li>' .
					'<li>All other data -- name, personal info, case management and code data, including custom field data -- will be copied to the second constituent, but only to the extent the second constituent has blank values in those fields.</li>' .
				'</ol>' .
				$data_array['duplicate_constituent']->form_control() .
				'<a href="\" id="switch_to_dup_link">Switch to this constituent</a>' .
				'<p><strong>' . 'These actions cannot be undone.' . '</strong></p>' .
			'</div>';			
	}

}