<?php
/*
*
*  class-wic-form-upload.php
*
*/

class WIC_Form_Upload extends WIC_Form_Parent  {

	// associate form with entity in data dictionary
	protected function get_the_entity() {
		return 'upload';
	}

	// layout form consistent with other upload forms that will use the upload-form-slot for fast load
	public function layout_form (  &$data_array, $message, $message_level, $sql = '' ) {
	
		global $wic_db_dictionary;		

		echo '<div id="wic-forms">'; 

			$first_form_object = $this->get_form_object (  $data_array, $message, $message_level, $sql = ''  );

			// output message 
			echo '<div id="post-form-message-box" class = "' .  $first_form_object->css_message_level . '" >' . esc_html( $first_form_object->message ) . '</div>';

			//output form
			echo '</ul></div>' .
			'<div id="upload-form-slot">' .
				$first_form_object->form  .
			'</div>';

		echo '</div>'; // end wic-forms
	}

	// this standard message function only used when load form directly.
	protected function format_message ( &$data_array, $message ) {
		return sprintf ( __( 'File %s successfully copied from client to server -- now check settings and parse the file. ' , 'wp-issues-crm' ), $data_array['upload_file']->get_value() ) ;
	} 

	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {

		global $wic_db_dictionary;		
		
		$css_message_level	= $this->message_level_to_css_convert[$message_level];
		
		$buttons			= $this->get_the_buttons( $data_array );
		$group_array = $this->generate_group_content_for_entity( $data_array );
		extract ( $group_array );
		$form 	= 	'<form id = "' . $this->get_the_form_id() . '" ' . $this->supplemental_attributes() . 'class="wic-post-form" method="POST" autocomplete = "on">' .
					$buttons .	'<div id = "wic-upload-progress-bar"></div><div id = "wic-upload-console"></div>' .
					'<div id="wic-form-body">' . '<div id="wic-form-main-groups">' . $main_groups . '</div>' .
					'<div id="wic-form-sidebar-groups">' . $sidebar_groups . '</div>' . '</div>' . 
					'<div class = "wic-form-field-group" id = "bottom-button-group">' .
						wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, false ) .
						$this->get_the_legends( $sql ) .
					'</div>' .								
					'</form>';
		return  (object) array ( 'css_message_level' => $css_message_level, 'message' => $this->format_message ( $data_array, $message ), 'form' => $form ) ;
		
	}



	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {

		$button_args_main = array(
			'entity_requested'			=> 'upload',
			'action_requested'			=> 'form_save_update',
			'name'						=> 'wic_upload_verify_button',
			'id'						=> 'wic_upload_verify_button',
			'type'						=> 'button',
			'button_label'				=> '<span class="button-highlight">Next:  </span>Parse File',
		);	

		$buttons = $this->create_wic_form_button ( $button_args_main );
						
		return ( $buttons  ) ;
	}


	// group screen
	protected function group_screen( $group ) {
		return 
			'save_options' == $group->group_slug ||   
			'initial' == $group->group_slug; 
	}
	
	// special group handling for the upload parameters group
	protected function group_special ( $group ) {
		return false;
	}


	protected function supplemental_attributes () {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {}
	 	
}