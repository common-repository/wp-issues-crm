<?php
/*
*
*	class-wic-interface-parent.php
*
*	each incoming form interface should be implemented as an extension of this class  WIC_Interface_{type}
*   additionally must 
*		(1) add to external_type_options in wic-entity-external.php 
*		(2) create an identifier option generator there
*
*/
abstract class WIC_Interface_Parent {
	
	const INTERFACE_TYPE = 'undefined'; // set this in each extension
	protected $identifiers;
	protected $posted_data;
	protected $current_form;

	// set filters and action hooks
	abstract protected function activate_interface ();
	
	// accept data for $this->posted_data, possibly filter, set other parms
	abstract protected function load_posted_data ( $posted_data, $form_data = array() );
	
	public function __construct ( $identifiers ) {
		$this->identifiers = $identifiers;
		$this->activate_interface();
	}

	// execute the constituent/activity save (may be invoked by load_posted_data or triggered after validation (wpcf7)
	protected function do_interface () {

		// get interface ID and defaults
		global $wpdb;
		$external_table = $wpdb->prefix . 'wic_external';
		$results = $wpdb->get_results ( 
			$wpdb->prepare ( 
				"SELECT ID FROM $external_table WHERE external_type = %s AND external_identifier = %d",
				array ( static::INTERFACE_TYPE, $this->current_form )
			)
		);

		// get interface defaults, policies and map
		$interface_id = $results[0]->ID;
		$results = $wpdb->get_results ( 
				"SELECT * FROM $external_table WHERE ID = $interface_id"
			);
		$defaults = array();
		$policies = array();
		$field_map = false;
		$front_end_post_settings = array();
		$excluded_fields = array (
			'ID', 'external_type', 'external_identifier','last_updated_time', 'last_updated_by', 'external_name', 'enabled'
		);
		foreach ( (array) $results[0] as $key => $value ) {
			if ( 'policy' == substr ( $key, 0, 6) ) {
				$policies[$key] = $value;
			} elseif ( 'front_end_post' == substr ( $key, 0, 14 ) ) {
				$front_end_post_settings[$key] = $value;
			} elseif ( 'serialized_field_map' == $key ) {
				$field_map = json_decode ( $value );
			} elseif ( ! in_array ( $key, $excluded_fields ) ) {
				$defaults[$key] = $value;
			}
		}
		// get mappings and apply to filter_post_data 
		if ( count ( ( array ) $field_map ) > 0 ) {
			foreach ( ( array ) $field_map as $external_field => $entity_field_object ) {
				if ( $external_field != $entity_field_object->field ) {
					if ( isset ( $this->posted_data[$external_field] ) ) {
						$temp = $this->posted_data[$external_field];
						unset (  $this->posted_data[$external_field] );
						$this->posted_data[$entity_field_object->field] = $temp;
					}					
				}
			}
		} 		

		// overwrite default keys from input if included (merge takes second value);		
		$key_value_array_complex = array_merge ( 
			(array) $defaults,
			$this->posted_data 
		);
		
		// flatten all array values in case form plugin sends data as array
		$key_value_array = array();
		foreach ( $key_value_array_complex as $key=>$value ) {
			$key_value_array[$key] = is_array( $value ) ? implode( '|', $value ) : $value;
		}
		
		
		$new_post = new WIC_Interface_New_Post ( $key_value_array, $front_end_post_settings );
		if ( ! $new_post->response_code ) {
			$key_value_array['Post Creation'] = 'No new post created with this activity: ' . $new_post->output;
		} else {
			$key_value_array['Post Creation'] = $new_post->output;
		}
		
		$wic_constituent_activity = new WIC_Interface_Transaction;
		$result = $wic_constituent_activity->save_constituent_activity( $key_value_array, array(), $policies );
		if ( false === $result['response_code'] && WP_DEBUG ) {
			error_log ( sprintf ( "Bad return from save_constituent_activity in %s interface to WP Issues CRM: %s. ",
				 static::INTERFACE_TYPE, print_r ( $result['output'], true ) ) );
		}
	}

}