<?php
/*
*
*	wic-entity-option-value.php
*
*/



class WIC_Entity_Option_Value extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'option_value';
		$this->entity_instance = $instance;
	} 

	public static function option_value_sanitizor ( $raw_slug ) { 
		return ( preg_replace("/[^a-zA-Z0-9_]/", '', $raw_slug) ) ;
	}
	
	public function row_form() {
		$this->freeze_system_reserved_option_values();
		$new_update_row_object = new WIC_Form_Multivalue ( $this->entity, $this->entity_instance );
		$new_update_row = $new_update_row_object->layout_form( $this->data_object_array, null, null );
		return $new_update_row;
	}

	protected function freeze_system_reserved_option_values () {
		
		global $wic_db_dictionary;

		/*
		*  freeze_system_reserved_option_values
		*/
		if ( '' <  $this->data_object_array['parent_option_group_slug']->get_value() ) {
			$reserved_types = $wic_db_dictionary->get_reserved_option_values( $this->data_object_array['parent_option_group_slug']->get_value() );
			if ( count( $reserved_types ) > 0 ) {
				if ( in_array ( $this->data_object_array['option_value']->get_value(), $reserved_types ) ) { 
					foreach ( $this->data_object_array as $control ) {
						$control->override_readonly( true );
					}
				}
			}
		}
	}	
}