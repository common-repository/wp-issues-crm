<?php
/*
*
*	wic-entity-advanced-search-activity.php
*
*/
class WIC_Entity_Advanced_Search_Activity extends WIC_Entity_Advanced_Search_Row 	{

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_activity';
		$this->entity_instance = $instance;
	} 
	
	public static function activity_fields () {
		global $wic_db_dictionary;
		return( $wic_db_dictionary->get_search_field_options( 'activity' ) );
	}
	
	public static function activity_comparison_sanitizor ( $incoming ) {
		return ( WIC_Entity_Advanced_Search_Constituent::constituent_comparison_sanitizor ( $incoming ) );	
	}	

	// supports incoming array from substituted activity-value field	
	public static function activity_value_sanitizor ( $incoming ) {
		if ( is_array ($incoming) ) {
			foreach ( $incoming as $key => $value ) {
				if ( $value != absint( $value ) ) {
					WIC_Function_Utilities::wic_error ( sprintf ( 'Invalid value for multiselect field %s', $this->field->field_slug ) , __FILE__, __LINE__, __METHOD__,true );
				}	
			}
			return ( $incoming );			
		} else {
			return ( sanitize_text_field ( stripslashes ( $incoming ) ) );
		}	
	}	

	// look up values including system reserved values (overwrite reservation value)
	public static function activity_type_options_all () {
		global $wic_db_dictionary;
		$options = $wic_db_dictionary->lookup_option_values( 'activity_type_options' );
		$unreserved_options = array();
		foreach ( $options as $option ) {
			$option['is_system_reserved'] = 0;
			$unreserved_options[] = $option;
		}
		return ( $unreserved_options );
	}
}