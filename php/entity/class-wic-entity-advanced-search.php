<?php
/*
*
*	wic-entity-advanced-search.php
*
*   SEE NOTES IN advanced-search.js
*
*/

class WIC_Entity_Advanced_Search extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a top level entity does not process them -- no instance arg
		$this->entity = 'advanced_search';
	} 

	// in metaquery array assembly, group multivalue rows into single top-level array entries
	// never dup checking in advanced search, so carrying dup check parameter only for consistency with parent
	public function assemble_meta_query_array ( $dup_check = false ) {

		$meta_query_array = array ();

		foreach ( $this->data_object_array as $field => $control ) {
			
			// get query clause
			if ( ! $control->is_multivalue() ) { 
				$query_clause = $control->create_search_clause();
			} else { 
				$query_clause = $control->create_advanced_search_clause();
			}
			
			// add to array if returned a query clause (not a blank string)
			if ( is_array ( $query_clause ) ) { 
				$meta_query_array = array_merge ( $meta_query_array, $query_clause ); // will do append since the arrays of arrays are not keyed arrays
			}  

		}

		return $meta_query_array;
	}		

	// the reverse process to assembly -- have to convert array back to format expected by multivalue controls
	protected function populate_data_object_array_with_search_parameters ( $search ) { 

		$this->initialize_data_object_array();

		// reformat $search_parameters array
		$key_value_array = self::reformat_search_array ( $search['unserialized_search_array'] );	

		// already in key value format
		$combined_form_values = array_merge ( $key_value_array, $search['unserialized_search_parameters']);

		// pass data object array and see if have values
		foreach ( $this->data_object_array as $field_slug => $control ) { 
			if ( isset ( $combined_form_values[$field_slug] ) ) {
					$control->set_value ( $combined_form_values[$field_slug] );
			}
		} 
	}

	public static function reformat_search_array( $unserialized_search_array ) {
		$key_value_array = array();		
		foreach ( $unserialized_search_array as $search_array ) { 
			if ( isset ( $search_array['table'] ) ) { // these are the search connect terms
				$key_value_array[$search_array['key']] = $search_array['value'];
			} else { // is a row array for a multivalue control
				$row_field = $search_array[1][0]['table'];				
				$row_array = array(); 
				foreach ( $search_array[1] as $term ) {
					$row_array[$term['key']] = $term['value'];				
				} 
				if ( ! isset ( $key_value_array[$row_field] ) ) {
					$key_value_array[$row_field] = array ( $row_array );			
				} else {
					$key_value_array[$row_field] = array_merge ( $key_value_array[$row_field], array( $row_array ) );
				}
			}
		} 
		return ( $key_value_array );
	}

	protected function pre_list_message ( &$lister, &$wic_query, &$data_object_array ) {
		if ( 'activity' == $wic_query->entity_retrieved ) {
			$message = $lister->format_message( $wic_query ); 		
			return ( '<div id="post-form-message-box" class = "wic-form-routine-guidance" >' . esc_html( $message ) . '</div>' );
		} else {
			return ( '' );		
		}	
	}

	protected function handle_search_results ( $wic_query ) { 
		$sql = $wic_query->sql;
		$this->search_log_id = $wic_query->search_id; 
		if ( 0 == $wic_query->found_count ) {
			$message = __( ' No matching record found.', 'wp-issues-crm' );
			$message = WIC_Entity_Advanced_Search::add_blank_rows_message ( $wic_query, $message );
			$message_level =  'error';
			$form = new WIC_Form_Advanced_Search;
			$form->layout_form ( $this->data_object_array, $message, $message_level, $sql );			
		} else {
			// spoof lister based on entity retrieved
			$wic_query->entity = $wic_query->entity_retrieved; 
			$lister_class =  'WIC_List_' . $wic_query->entity_retrieved;
			$lister = new $lister_class; 	
			$list = $lister->format_entity_list( $wic_query, '' );
			$message = $this->pre_list_message( $lister, $wic_query, $this->data_object_array );
			echo $message;			
			echo $list;	
		}
	}
	
	/*
	* make_blank_control_object called on server side by row objects to swap in correct control for search value corresponding to search field and compare field 
	* wrapper routines, make_blank_control and make_blank_row called on change field, or, in some instances changed comparisons, from client side by ajax
	* 
	* $data is an object with following properties:
	*	comparison	-- the value of the comparison field in the row;
	*	entity 		-- the row entity (constituent, activity or constituent-having)
	*	field_slug 	-- being swapped ( always the value field from the row, but name varies according to entity )
	*   instance 	-- the instance of the row entity
	*/
	public static function make_blank_row ( $new_control_field_id, $data ) {
		$args = array (
			'instance' => $data->instance,
			'form_row_array' => array ( substr ( $data->entity, 16 ) . '_field' => $new_control_field_id ),
		);
		$entity_class = 'WIC_Entity_' . $data->entity;
		$row_object = new $entity_class ( 'populate_from_form', $args );
		return array ( 'response_code' => true, 'output' => $row_object->row_form() );	
	}
	
	
	public static function make_blank_control( $new_control_field_id, $data  ) {
		$control_object = self::make_blank_control_object ( $new_control_field_id, $data  );
		$control = $control_object->form_control();
		return array ( 'response_code' => true, 'output' => $control );	
	}
	
	public static function make_blank_control_object ( $new_control_field_id, $data  ) 	{ 
		// step one: what field are we making a control for? 
		global $wic_db_dictionary;
		$field = $wic_db_dictionary->get_field_rules_by_id( $new_control_field_id  );
		// if the field is issue, perhaps override field based on comparison value 
		if ( 'issue' == $field['field_slug'] ) {
			if ( 'cat' == substr ( $data->comparison, 0, 3 ) ) {
				$field = array ( 
					'field_type' 	=> 'multiselect',
					'field_slug' 	=> 'post_category',
					'entity_slug' 	=> 'issue',			
				);
			} elseif ( 'TAG' == $data->comparison ) {
				$field = array (
					'field_type' 	=> 'text',
					'field_slug' 	=> 'tags_input',
					'entity_slug' 	=> 'issue'
				);			
			} elseif ( 'SCAN' == $data->comparison ) {
		 		$field = array (
					'field_type' 	=> 'text',
					'field_slug' 	=> 'post_title',
					'entity_slug' 	=> 'issue'
				);	
			}
			// for having row, dummy count control
		} elseif ( $data->aggregatorIsCount ) {
		 	$field = array (
				'field_type' 	=> 'text',
				'field_slug' 	=> 'having_count',
				'entity_slug' 	=> 'advanced_search_constituent_having'
			);	
			// don't use autocomplete for advanced search fields		
		} elseif ( 'autocomplete' == $field['field_type'] ) {
			$field['field_type'] = 'text';
		}
		
		// step two: make the control
		// want to use a binary select control in cases of checked fields to allow negative selection on field
		if ( 'checked' == $field['field_type'] ) {
			$control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$control->initialize_default_values(  'advanced_search', 'advanced_search_checked_substitute', 'placeholder' );
		} else {	
			$control = WIC_Control_Factory::make_a_control ( $field['field_type'] );
			// need to initialize with values from field being searched for, not field in advanced_search form
			$control->initialize_default_values(  $field['entity_slug'], $field['field_slug'], 'placeholder' );
		}
		
		// step three: give the control default field slug values which will result in correct name and id
		// it should be identified by entity and instance as an activity value field
		// it should also lose its standard form label (value field has no label)
		$control->set_default_control_slugs ( $data->entity, $data->field_slug, $data->instance );
		$control->set_default_control_label ('');
		
		// step four: controls should be updateable
		$control->override_readonly( false );
		
		return $control;

	}

	// filter applied to messages for activity and constituent lists and in self::handle_search_results
	public static function add_blank_rows_message ( &$wic_query, $message ) { 
		if ( isset ( $wic_query->blank_rows_ignored ) ) {
			if ( 0 < $wic_query->blank_rows_ignored ) {
				$message_add_on = ( 1 == $wic_query->blank_rows_ignored ) ?
					 __( 'One row with a missing search value ignored. ',	'wp-issues-crm' ) :
					 sprintf ( __( '%d rows with missing search values ignored. ',	'wp-issues-crm' ) , $wic_query->blank_rows_ignored )  ;					 
				$message = $message_add_on . $message; 
			}
		}
		return ( $message );
	}
	
	// to get next page of an advanced search, don't actually need to instantiate the advanced search entity
	// talk to search log and query object and lister statically
	public static function get_search_page ( $search_id, $offset ) {
		$search = WIC_Entity_Search_Log::get_search_from_search_log ( array( 'id_requested' => $search_id ) );
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'advanced_search' );
		$search['unserialized_search_parameters']['redo_search'] = true; // don't want to log previously logged search again
		$search['unserialized_search_parameters']['old_search_id'] = $search['search_id'];
		$search['unserialized_search_parameters']['list_page_offset'] = $offset;
		$wic_query->search ( $search['unserialized_search_array'], $search['unserialized_search_parameters'] ); 
		$wic_query->entity = $wic_query->entity_retrieved; 
		$lister_class =  'WIC_List_' . $wic_query->entity_retrieved;
		$lister = new $lister_class; 
		return array ( 'response_code' => true, 'output' => $lister->rows_only( $wic_query ) );	
	}
}