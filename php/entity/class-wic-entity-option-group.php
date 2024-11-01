<?php
/*
*
*	wic-option-group.php
*
*/

class WIC_Entity_Option_Group extends WIC_Entity_Parent {
	
	
	/*
	*
	* Request handlers
	*
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'option_group';
	} 
	
	// set values from update process to be visible on form after save or update
	protected function special_entity_value_hook ( &$wic_access_object ) { 
			$time_stamp = $wic_access_object->db_get_time_stamp( $this->data_object_array['ID']->get_value() );
			$this->data_object_array['last_updated_time']->set_value( $time_stamp->last_updated_time );
			$this->data_object_array['last_updated_by']->set_value( $time_stamp->last_updated_by );
	}
	
	public static function option_group_slug_sanitizor ( $raw_slug ) { 
		return ( preg_replace("/[^a-zA-Z0-9_]/", '', $raw_slug) ) ;
	}
	
	protected function list_option_groups () {
		// table entry in the access factory will make this a standard WIC DB object
		$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		// do simple search array to select those that are not system reserved option groups
		$meta_query_array  = array ( 
			array (
				"table"	=> "option_group",
				"key"		=> "is_system_reserved",
				"value"	=> 0,
				"compare"=> "=",
				"wp_query_parameter" => ""
			),
		);
		$wic_query->search ( $meta_query_array, array( 'retrieve_limit' => 9999 ) );
		$lister_class = 'WIC_List_' . $this->entity ;
		$lister = new $lister_class;
		$list = $lister->format_entity_list( $wic_query, '' ); 
		echo $list;
	}
	
	public static function option_label_list_formatter ( $list ) {
		// sorts labels and replaces emptystring with the word BLANK
		$label_array = explode ( ',', $list );
		
		sort( $label_array );

		if ( count( $label_array ) > 0 ) {	
			for ( $i = 0; $i < count ( $label_array); $i++ )  {
				$label_array[$i] =  '' == trim( $label_array[$i] )  ? 'BLANK' : trim($label_array[$i]);		
			}
		}

		return ( implode ( '|', $label_array ) );
	}
	
}