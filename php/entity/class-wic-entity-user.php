<?php
/*
*
*	wic-entity-user.php
*
*/
class WIC_Entity_User extends WIC_Entity_Email_Settings {
	
	const WIC_METAKEY =  'wic_data_user_preferences';
	
	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'user';
	} 

	public function new_blank_form_noecho ( $args = '', $guidance = '' ) {
		global $wic_db_dictionary;
		$this->fields = $wic_db_dictionary->get_form_fields( $this->entity );
		$this->initialize_data_object_array();
		$this->data_object_array['signature']->set_value( self::get_wic_user_preference ( 'signature' ) );
		$new_form = new WIC_Form_User;
		return $new_form->prepare_settings_form( $this->data_object_array );
	}	

	// return preference value for specified user preference string
	public static function get_wic_user_preference ( $preference ) {
		$user_id = get_current_user_id(); 
		$wic_user_meta = get_user_meta ( $user_id, self::WIC_METAKEY ) ;
		$preferences = ( count ( $wic_user_meta ) > 0 ) ? unserialize ( $wic_user_meta[0] ) : array();
		return ( isset ( $preferences[$preference] ) ?  $preferences[$preference] : false );
	}

	// return preference value for specified user preference string
	public static function set_wic_user_preference ( $preference, $set_value ) {
		$user_id = get_current_user_id(); 
		$wic_user_meta = get_user_meta ( $user_id, self::WIC_METAKEY ) ;
		$preferences = ( count ( $wic_user_meta ) > 0 ) ? unserialize ( $wic_user_meta[0] ) : array();
		if ( isset ( $preferences[$preference]  ) ) {
			if ( $preferences[$preference] == $set_value )  {
				return array ( 'response_code' => true, 'output' => '' );
			}
		} 
		// sanitize preserving html before saving; do not sanitize arrays and objects
		$preferences[$preference] = is_string( $set_value ) ? wp_kses_post( $set_value ) : $set_value; 
		$result = update_user_meta ( $user_id, self::WIC_METAKEY, serialize ( $preferences ) );
		return array ( 'response_code' => $result, 'output' => false === $result ? 'function update_user_meta failed' : '' );
	}

	public static function get_current_user_sig() {
		$current_user = wp_get_current_user();
		$wic_user_meta = get_user_meta ( $current_user->ID, self::WIC_METAKEY ) ;
		if ( isset ( $wic_user_meta[0] ) ) {
			$preferences = unserialize ( $wic_user_meta[0] );
			if ( isset ( $preferences['signature'] ) ) {
				return ( $preferences['signature'] );
			} 
		}
		return ( '' );
	}

}