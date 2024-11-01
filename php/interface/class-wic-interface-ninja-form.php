<?php
/*
*
*	wic-interface-wpcf7.php
*
*/

class WIC_Interface_Ninja_Form extends WIC_Interface_Parent {

	const INTERFACE_TYPE = 'ninja_form';

	public function activate_interface() { 
		add_action( 'ninja_forms_after_submission', array ( $this, 'load_posted_data') );
	}

	// in this case, a filter, passing $posted_data through;
	public function load_posted_data ( $processing_array, $form_data = array() ) {
		
		$current_form = $processing_array['form_id'];
		// if listening to this form . . .
		if ( ! in_array( $current_form, $this->identifiers ) ) {
			return false;
		}
		
		$this->current_form = $current_form;			
		$this->posted_data = array();
		// insert the ninja form values from the processing array into a key value array
		foreach ( $processing_array['fields'] as $field  ) {
			$this->posted_data[$field['settings']['key']] = $field['settings']['value'];
		}
		$this->do_interface();

	} 


	public static function get_field_list ( $form_id  ) {
		global $wpdb;
		$field_list = array();
		$ninja_field_table = $wpdb->prefix . 'nf3_fields';
		$results = $wpdb->get_results ( "SELECT `key`, `label` from $ninja_field_table WHERE `parent_id` = $form_id AND  `type` NOT IN('submit','hidden','html','hr','spam','recaptcha')" );
		foreach ( $results as $result ) {
			$label = trim ( $result->label ) > '' ? trim ( $result->label ) : $result->key;
			$field_list[] = array ( $result->key, $label ); // tag, label, but not extract labels from CF7 Forms;
		}
		return $field_list; 	
	}
	
}