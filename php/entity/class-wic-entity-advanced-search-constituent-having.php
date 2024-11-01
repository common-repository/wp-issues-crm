<?php
/*
*
*	wic-entity-advanced-search-constituent-having.php
*
*/



class WIC_Entity_Advanced_Search_Constituent_Having extends WIC_Entity_Advanced_Search_Row {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_constituent_having';
		$this->entity_instance = $instance;
	} 

	public static function constituent_having_fields () {

		global $wic_db_dictionary;
		
		// get activity fields
		$all_activity_fields =  $wic_db_dictionary->get_search_field_options( 'activity' );
		
		// select appropriate for aggregation
		$having_fields = array();	
		foreach ( $all_activity_fields as $field ) { 
			if ( 	false !== strpos ( $field['label'], 'activity:activity_amount' ) || 
					false !== strpos ( $field['label'], 'activity:activity_date' ) ||
					false !== strpos ( $field['label'], 'activity:last_updated_time' ) 			
				 )  {
				$having_fields[] = $field;			
			}
		}
		
		return ( $having_fields );
	}

	public static function advanced_search_issue_cats() {
  		return (WIC_Entity_Issue::get_post_category_options());
	}
	
	public static function constituent_having_comparison_sanitizor ( $incoming ) { 
		return ( WIC_Entity_Advanced_Search_Constituent::constituent_comparison_sanitizor ( $incoming ) );	
	}	

	public static function activity_type_options_all () {
		return WIC_Entity_Advanced_Search_Activity::activity_type_options_all();
	}

	protected function manage_count_field ( $aggregatorIsCount ) {
		if ( $aggregatorIsCount ) {
			$this->data_object_array['constituent_having_field']->set_input_class_to_make_element_invisible();
		}
	} 


}