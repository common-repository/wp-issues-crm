<?php
/*
*
*	wic-entity-address.php
*
*/



class WIC_Entity_Address extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'address';
		$this->entity_instance = $instance;
	} 

	public static function zip_validator ( $zip ) { 
		$options = get_option ('wp_issues_crm_plugin_options_array');
		if ( isset ( $options['do_zip_code_format_check'] ) && '' < $zip ) {
			if ( ! preg_match ( "/^\d{5}([\-]?\d{4})?$/i", $zip ) ) {
				return ( __( 'Invalid USPS Zip Code supplied.', 'wp-issues-crm' ) ); 			
			}
		}	
		return ( '' );
	}


	public function row_form() {
	
		$address_line_composed = $this->data_object_array['address_line']->get_value() . ' -- ' . $this->data_object_array['city']->get_value() ; 
	
		// include send email button 
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-location-alt"></span>',
			'type'						=> 'button',
			'id'						=> '',			
			'name'						=> '',
			'title'						=> 'Map Address',
			'button_class'				=> 'wic-form-button map-individual-address-button',
			'value'						=> 'show_point,' . $this->get_lat() . ',' . $this->get_lon() . ',' . $address_line_composed,
		);	
		
		
		
		$message = WIC_Form_Parent::create_wic_form_button ( $button_args_main );
		$new_update_row_object = new WIC_Form_Address ( $this->entity, $this->entity_instance );
		$new_update_row = $new_update_row_object->layout_form( $this->data_object_array, $message, 'address_line_2' );
		return $new_update_row;
	}


	private function get_zip_from_usps () {
		
		$uspsRequest = new WIC_Entity_Address_USPS(); //class instantiation
		$uspsRequest->address2 = $this->data_object_array['address_line']->get_value();   
		$uspsRequest->address1 = '';
		$uspsRequest->city = $this->data_object_array['city']->get_value();
		$uspsRequest->state = $this->data_object_array['state']->get_value();
		$uspsRequest->zip = '';
 
		if ( $uspsRequest->address2 > '' && $uspsRequest->city > '' && $uspsRequest->state > '' ) {	

			$result = $uspsRequest->submit_request();

			if ( !empty( $result ) ) {
				$xml = new SimpleXMLElement( $result );
				if( ! isset($xml->Address[0]->Error) && ! isset($xml->Number) ) {
				// if not an address lookup error and also not a basic access error, then overlay entered data
					$this->data_object_array['address_line']->set_value( (string) $xml->Address[0]->Address2 );
					$this->data_object_array['city']->set_value( (string) $xml->Address[0]->City );	
					$this->data_object_array['zip']->set_value( (string) $xml->Address[0]->Zip5 ); 		 		 
				}
			} else {
				echo '<div id="filtered-all-warning">' . __( 'Empty return from USPS ZipCode Validator. Unknown error. You can disable validator at: ', 'wp-issues-crm' )  . '<a href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-settings#usps"> WP Issues CRM settings.</a>' . '</div>';
			}
			if ( strpos ( $result, '80040B' ) ) {
				echo '<div id="filtered-all-warning">' . __( 'USPS ZipCode Validator error -- check User Name setting in: ', 'wp-issues-crm') . '<a target = "_blank" href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-settings#usps"> WP Issues CRM settings</a> or contact your site administrator.' . '</div>';
			}
		}
	}





	// validate values
	public function validate_values () {
			
		if ( isset ( $this->data_object_array['is_changed'] ) && '0' === $this->data_object_array['is_changed']->get_value() ) {
			return '';
		}

		// do I have a geocoder
		if ( function_exists ( 'wp_wic_lookup_geocodes_local') || WIC_Entity_Geocode::get_geocodio_api_key() ) {
			// do I have a geocodable address
			$address_line 	= $this->data_object_array['address_line']->get_value();   
			$city 			= $this->data_object_array['city']->get_value();
			$state 			= $this->data_object_array['state']->get_value();
			if ( $city && $state ) {
				$address_string = ( trim( $address_line ) ? ( trim( $address_line ) . ', ') : '' ) . $city . ', ' . $state;
				// false return just does nothing -- leaves address ungeocoded
				if ( $lat_lng_zip = WIC_Entity_Geocode::get_single_geocode( $address_string ) ) {
					$this->data_object_array['lat']->set_value( (string) $lat_lng_zip[0]->lat );
					$this->data_object_array['lon']->set_value( (string) $lat_lng_zip[0]->lng );	
					if (  $lat_lng_zip[1] > '' ) {
						$this->data_object_array['zip']->set_value( (string) $lat_lng_zip[1] ); 		 		 
					}
				}
			}
		
		} 

		// do I have a postal address standardizer -- invoke b/c does better job standardizing street address than geocodio		
		$options = get_option ('wp_issues_crm_plugin_options_array');
		if ( isset ( $options['use_postal_address_interface'] ) ) {
			$this->get_zip_from_usps();
		}

		return ( parent::validate_values() );
	} 

	public function get_lat() {
		return ( $this->data_object_array['lat']->get_value() );
	}

	public function get_lon() {
		return ( $this->data_object_array['lon']->get_value() );
	}
}