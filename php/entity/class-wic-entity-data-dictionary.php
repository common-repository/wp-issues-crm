<?php
/*
*
*	wic-entity-data-dictionary.php
*
*/

class WIC_Entity_Data_Dictionary extends WIC_Entity_Parent {
	
	/*
	*
	* Request handlers
	*
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'data_dictionary';
	} 

	//handle an update request coming from an update form
	protected function form_update () {
		$this->form_save_update_generic ( false, 'WIC_Form_Data_Dictionary_Update', 'WIC_Form_Data_Dictionary_Update' );
		return;
	}
	
	//handle a save request coming from a save form
	protected function form_save () {
		$this->form_save_update_generic ( true, 'WIC_Form_Data_Dictionary_Save', 'WIC_Form_Data_Dictionary_Update' );
		return;
	}
	
	
	protected function list_custom_fields () {
		// table entry in the access factory will make this a standard WIC DB object
		$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		// construct a simple search array to select only those fields that are customizable
		$meta_query_array  = array ( 
			array (
				"table"	=> "data_dictionary",
				"key"		=> "customizable",
				"value"	=> 1,
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
	
	protected function special_entity_value_hook ( &$wic_access_object ) { 
		// field_slug value set in wic-db-access-dictionary->process_save_update_array 
		if ( '' == $this->data_object_array['field_slug']->get_value() ) {
			$this->data_object_array['field_slug']->set_value( $wic_access_object->field_slug );
		}
		// need to bring this back to update form, since not created on save form, but instead by save process
	}
		
	
	
}