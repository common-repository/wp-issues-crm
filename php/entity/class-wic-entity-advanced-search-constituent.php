<?php
/*
*
*	wic-entity-advanced-search-constituent.php
*
*/



class WIC_Entity_Advanced_Search_Constituent extends WIC_Entity_Advanced_Search_Row {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_constituent';
		$this->entity_instance = $instance;
	} 

	public static function constituent_fields () {
		global $wic_db_dictionary;
		return( $wic_db_dictionary->get_search_field_options( 'constituent' ) );
	}

	public static function constituent_type_options (){
		return ( array ( array ( 'value' => '', 'label' => 'Type is N/A' ) ) );	
	}

	// in lieu of sanitize text field default sanitizor, test that in option values
	// sanitize text field replaces <= operators
	public static function constituent_comparison_sanitizor ( $incoming ) {
		global $wic_db_dictionary;
		$comparison_sets = array ( 'advanced_search_general_comparisons', 'advanced_search_select_comparisons', 'advanced_search_quantitative_comparisons', 'advanced_search_issue_comparisons' );
		foreach ( $comparison_sets as $comparison_set ) {
			$valid_array = $wic_db_dictionary->lookup_option_values( $comparison_set );
			foreach ( $valid_array as $option_value_pair ) {
				if ( $incoming == $option_value_pair['value'] ) {
					return $incoming;			
				}		
			} 	
		}
		return '=';
	}

	// manage slot within parent interaction rules method
	protected function type_interaction( $field_entity ){
		if ( 'constituent' == $field_entity ) {
			$this->data_object_array['constituent_entity_type']->set_input_class_to_hide_element();
		} else { 
			$this->data_object_array['constituent_entity_type']->set_options( $field_entity. '_type_options' );
		}
	}
	
}