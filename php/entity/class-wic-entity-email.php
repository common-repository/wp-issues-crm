<?php
/*
*
*	wic-entity-email.php
*
*/



class WIC_Entity_Email extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'email';
		$this->entity_instance = $instance;
	} 

	public function row_form() {
	
		// include send email button 
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-email-alt"></span>',
			'type'						=> 'button',
			'id'						=> '',			
			'name'						=> '',
			'title'						=> 'Compose new email',
			'button_class'				=> 'wic-form-button email-action-button in-line-email-compose-button email-compose-button',
			'value'						=> 'mailto,' . $this->get_email_address_id() . ',0',
		);	
		
		$message = WIC_Form_Parent::create_wic_form_button ( $button_args_main );
		$new_update_row_object = new WIC_Form_Email ( $this->entity, $this->entity_instance );
		$new_update_row = $new_update_row_object->layout_form( $this->data_object_array, $message, 'email_row' );
		return $new_update_row;
	}
		
	public static function email_address_validator ( $email ) { 
		$error = '';
		if ( $email > '' ) {	
			$error = filter_var( $email, FILTER_VALIDATE_EMAIL ) ? '' : __( 'Email address appears to be not valid. ', 'wp-issues-crm' );
		}
		return $error;	
	}	

	public function get_email_address() {
		return ( $this->data_object_array['email_address']->get_value() );	
	}
	
	public function get_email_address_id() {
		return ( $this->data_object_array['ID']->get_value() );
	}
	
	// build an array for use in email composition from email id 
	public static function get_email_address_array_from_id ( $id ) {
		if ( ! $id ) {
			return false;
		}
		global $wpdb;
		$email_table = $wpdb->prefix . 'wic_email';
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$sql = $wpdb->prepare ( "SELECT c.id as cid, first_name, last_name, email_address FROM $constituent_table c INNER JOIN $email_table e on e.constituent_id = c.id WHERE e.id = %d", array( $id ) );
		$result = $wpdb->get_results ( $sql );
		if ( ! $result ) {
			return false;
		} else {
			return array ( array ( trim( $result[0]->first_name . ' ' . $result[0]->last_name ), $result[0]->email_address, $result[0]->cid ) ); 
		}
	
	
	} 
}