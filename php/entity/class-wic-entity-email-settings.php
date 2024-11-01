<?php
/*
*
*	wic-entity-email-settings.php
*
*	entity invoked by WIC_Form_Email_Inbox::setup_settings_form 
*/
class WIC_Entity_Email_Settings extends WIC_Entity_Parent {

	/*
	*
	* basic entity functions
	*
	*
	*/
	protected function set_entity_parms( $args ) {
		$this->entity = 'email_settings';
		$this->entity_instance = '';
	} 

	// dummy to pass to constructor
	protected function no_action ( $args ) {}

	// populated by js
	public function settings_form ( $args = '', $guidance = '' ) {
		global $wic_db_dictionary;
		// set up blank array
		$this->fields = $wic_db_dictionary->get_form_fields( $this->entity );
		$this->initialize_data_object_array();
		// get option values, including refreshed defaults for text areas
		$options_object = WIC_Entity_Email_Process::get_processing_options()['output'];
		/* 
		* populate array with saved option values
		*
		* note that blank is never a valid option for any of the fields that might have non-blank defaults
		*
		*/
		foreach ( $this->data_object_array as $field_slug => $control ) { 
			if ( isset ( $options_object->$field_slug ) &&  $options_object->$field_slug > '' ) { 
				$control->set_value ( $options_object->$field_slug  );
			}
		} 
		$new_form = new WIC_Form_Email_Settings;
		return $new_form->prepare_settings_form( $this->data_object_array, $guidance );
	}	

	public static function save_parms ( $dummy, $parms_object ) {

		if ( function_exists ( 'wp_issues_crm_local_parms_save' ) ) {
			return wp_issues_crm_local_parms_save ( $parms_object );
		}

		// test sanitize fields
		$sanitized_i = sanitize_text_field ( $parms_object->i );
		$sanitized_o = sanitize_text_field ( $parms_object->o );
		$sanitized_a = sanitize_text_field ( $parms_object->a );
		// this is belt and suspenders because js already limits to numbers letters and special characters
		if ( $sanitized_i != $parms_object->i || $sanitized_o != $parms_object->o || $sanitized_a != $parms_object->a ) {
			return array ( 'response_code' => true, 'output' => (object) array (
			 	'saved' => false, 
			 	'message' => "Invalid value supplied -- do not use extra white space or html tags in your password." 
				)
			);	
		}

		// set up answer phrase and check for both blank
		$what_saved_phrase = '';
		if ( $parms_object->i && ! $parms_object->o ) {
			$what_saved_phrase = 'incoming email password.';
		} elseif ( !$parms_object->i && $parms_object->o ) {
			$what_saved_phrase = 'outgoing email password.';		
		} elseif ( $parms_object->i && $parms_object->o ) {
			$what_saved_phrase = 'incoming and outgoing email passwords.';
		} elseif  ( $parms_object->a ) {
			$what_saved_phrase = 'ActiveSync password.';
		} else  {
			return array ( 'response_code' => true, 'output' => (object) array (
			 	'saved' => false, 
			 	'message' => "No  values supplied.") 
			);
		}

		// get current values
		$option_name = 'wp-issues-crm-saved-parms';
		$current_parms = get_option ( $option_name );
		$i = isset ( $current_parms->i ) ? $current_parms->i : '';
		$o = isset ( $current_parms->o ) ? $current_parms->o : '';
		$a = isset ( $current_parms->a ) ? $current_parms->a : '';

		
		// encode and save if new
		if ( $parms_object->i ) {
			$i = self::wic_ssl_encode_parm(  $parms_object->i );
		}
		if ( $parms_object->o ) {
			$o = self::wic_ssl_encode_parm(  $parms_object->o );
		}
		if ( $parms_object->a ) {
			$a = self::wic_ssl_encode_parm(  $parms_object->a );
		}
		$outcome = update_option ( $option_name, (object) array ( 'i' => $i,  'o' => $o, 'a' => $a ) );
				
		return array ( 'response_code' => true, 'output' => (object) array (
			 'saved' => $outcome, 
			 'message' => $outcome ? "Saved $what_saved_phrase" : "No changed value supplied or update unsuccessful.") 
		);
	
	}

	public static function get_parms() {
	
		if ( function_exists ( 'wp_issues_crm_local_parms_get' ) ) {
			return wp_issues_crm_local_parms_get ();
		}	

		$option_name = 'wp-issues-crm-saved-parms';
		$current_parms = get_option ( $option_name );
		$i = isset ( $current_parms->i ) ? $current_parms->i : '';
		$o = isset ( $current_parms->o ) ? $current_parms->o : '';
		$a = isset ( $current_parms->a ) ? $current_parms->a : '';

		return ( object ) array ( 'i' => self::wic_ssl_decode_parm ( $i ), 'o' => self::wic_ssl_decode_parm ( $o ), 'a' => self::wic_ssl_decode_parm ( $a ) );
	}

	// encryption sufficient to assure that even if hacker has the open source code and accesses database alone, cannot learn passwords; 
	// but if can access config.php, at which point has full access anyway, then can decode passwords
	// http://php.net/manual/en/function.openssl-encrypt.php
	private static function wic_ssl_encode_parm ( $plaintext ) {
		$ivlen = openssl_cipher_iv_length( $cipher="AES-128-CBC" );
		$iv = openssl_random_pseudo_bytes($ivlen);
		$key = substr( AUTH_KEY, 0, 32) . substr( AUTH_SALT, 0, 32);
		$ciphertext_raw = openssl_encrypt(
			$plaintext, 
			$cipher,
			$key, 
			$options=OPENSSL_RAW_DATA, 
			$iv
			);
		return base64_encode( $iv . $ciphertext_raw );
	}
	
	private static function wic_ssl_decode_parm( $ciphertext ) {
		$c = base64_decode($ciphertext);
		$ivlen = openssl_cipher_iv_length( $cipher="AES-128-CBC" );
		$iv = substr( $c, 0, $ivlen );	
		$ciphertext_raw = substr( $c, $ivlen );
		$key = substr( AUTH_KEY, 0, 32) . substr( AUTH_SALT, 0, 32);
		return openssl_decrypt(
			$ciphertext_raw, 
			$cipher,  
			$key,
			$options=OPENSSL_RAW_DATA,
			$iv
			);
	}
}