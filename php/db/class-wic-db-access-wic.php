<?php
/*
*
* class-wic-db-access-wic.php
*		main db access for constituents, etc.
*
*/


class WIC_DB_Access_WIC Extends WIC_DB_Access {


	/*
	*
	* save an individual database record for the object entity based on the save_update_array containing fields and values
	*
	*/
	protected function db_save ( &$save_update_array ) { 
		global $wpdb;
		$table  = $wpdb->prefix . 'wic_' . $this->entity;  
		
		$set = $this->parse_save_update_array( $save_update_array ); // adds time stamp variables while formatting set clause
		// hook for updating parallel table in class extension
		$set = $this->external_update( $set );
  		$set_clause_with_placeholders = $set['set_clause_with_placeholders'];
		$sql = $wpdb->prepare( "
				INSERT INTO $table 	
				$set_clause_with_placeholders
				",
			$set['set_value_array'] );
		$save_result = $wpdb->query( $sql );
				
		if ( 1 == $save_result ) {
			$this->outcome = true;		
			$this->insert_id = $wpdb->insert_id;
		} else {		
			$this->outcome = false;
			$this->explanation = __( 'Unknown database error. Save may not have been successful', 'wp-issues-crm' );
		}
		$this->sql = $sql;
		return;
	}
	
	protected function external_update ( $set ) {
		return ( $set );	
	}	
	
	
	/*
	*
	* update an individual database record for the object entity based on the save_update_array containing fields and values
	*
	*/
	protected function db_update ( &$save_update_array ) {

		global $wpdb;
		$table  = $wpdb->prefix . 'wic_' . $this->entity;

		// parse the array to set up clause and value array for $wpdp->prepare
		$set = $this->parse_save_update_array( $save_update_array ); // adds time stamp variables while formatting set clause
		$set_clause_with_placeholders = $set['set_clause_with_placeholders'];

		// set up the sql and do the update
		$sql = $wpdb->prepare( "
				UPDATE $table
				$set_clause_with_placeholders
				WHERE ID = %s
				",
			$set['set_value_array'] );
		$update_result = $wpdb->query( $sql );

		// set outcome as false if falsey -- since tracking for change, a 0 update result is an error as is false.  Will hit this error if attempting to update a deleted record; stops multivalue processing.
		$this->outcome = ! ( false == $update_result );
		$this->explanation = ( $this->outcome ) ? '' : __( 'Possible database error. Update may not have been successful. Reload record to confirm.', 'wp-issues-crm' );
		$this->sql = $sql;

		return;
	}

	/*
	*
	* retrieve database records joined across parent and child tables based on array of search parameters
	*
	*/
	public function search( $meta_query_array, $search_parameters ) { 

		global $wic_db_dictionary;

		// default search parameters
		$select_mode 		= 'id';
		$sort_order 		= false;
		$compute_total 		= false;
		$retrieve_limit 	= '100';

		extract ( $search_parameters, EXTR_OVERWRITE );

		// implement search parameters
		$top_entity = $this->entity;
		if ( 'id' == $select_mode || 'download' == $select_mode ) {
			$select_list = $top_entity . '.' . 'ID ';	
		} else {
			$select_list = $top_entity . '.' . '* '; 
		}
		$sort_clause = $sort_order ? $wic_db_dictionary->get_sort_order_for_entity( $this->entity ) : '';
		$order_clause = ( '' == $sort_clause ) ? '' : " ORDER BY $sort_clause "; 
		$found_rows = $compute_total ? 'SQL_CALC_FOUND_ROWS' : '';
		// retrieve limit goes directly into SQL
		 
		// set global access object 
		global $wpdb;

		// prepare $where and join clauses
		$table_array = array( $this->entity );
		$where = '';
		$join = '';
		$values = array();
		// explode the meta_query_array into where string and array ready for wpdb->prepare
		foreach ( $meta_query_array as $where_item ) { 

			// pull out tables for join clause		
			if( ! in_array( $where_item['table'], $table_array ) ) {
				$table_array[] = $where_item['table'];			
			}

			$field_name		= $where_item['key'];
			$table 			= $where_item['table'];
			$compare 		= $where_item['compare'];
			
			// set up $where clause with placeholders and array to fill them
			if ( '=' == $compare || '!=' == $compare || '>' == $compare || '<' == $compare || '>=' == $compare || '<=' == $compare ) {  // straight strict match			
				$where .= " AND $table.$field_name $compare %s ";
				$values[] = $where_item['value'];
			} elseif ( 'like' == $compare ) { // right wild card like match
				$where .= " AND $table.$field_name like %s ";
				$values[] = $wpdb->esc_like ( $where_item['value'] ) . '%' ;	
			} elseif( 'scan' == $compare ) { // right and left wild card match
				$where .= " AND $table.$field_name like %s ";
				$values[] = '%' . $wpdb->esc_like ( $where_item['value'] )  . '%';
			} elseif ( 'BETWEEN' == $compare ) { // date range
				$where .= " AND $table.$field_name >= %s AND $table.$field_name <= %s" ;  			
				$values[] = $where_item['value'][0];
				$values[] = $where_item['value'][1];
			} else {
				WIC_Function_Utilities::wic_error ( sprintf( "Incorrect compare settings for field %1$s.",  $where_item['key']  ), __FILE__, __LINE__, __METHOD__, true );
			}	 
		}
		// prepare a join clause		
		$array_counter = 0;
		foreach ( $table_array as $table ) {
			$table_name  = $wpdb->prefix . 'wic_' . $table;
			$child_table_link = $top_entity . '_id';
			$join .= ( 0 < $array_counter ) ? " INNER JOIN $table_name as $table on $table.$child_table_link = $top_entity.ID " : " $table_name as $table " ;
			$array_counter++; 		
		}
		$join = ( '' == $join ) ? $wpdb->prefix . 'wic_' . $this->entity : $join; 

		// prepare SQL ( or skip prepare if no user input to where clause)
		if ( $where > '' ) {
			$sql = $wpdb->prepare( "
					SELECT $found_rows	$select_list
					FROM 	$join
					WHERE 1=1 $where 
					GROUP BY $top_entity.ID
					$order_clause
					LIMIT 0, $retrieve_limit
					",
				$values );
		} else {
			$sql = "
					SELECT $found_rows	$select_list
					FROM 	$join
					WHERE 1=1  
					GROUP BY $top_entity.ID
					$order_clause
					LIMIT 0, $retrieve_limit
					";
		}	
		// $sql group by always returns single row, even if multivalues for some records 

		$sql_found = "SELECT FOUND_ROWS() as found_count";
		$this->sql = $sql; 

		if ( 'download' == $select_mode ) {
			$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();			
			$sql = "CREATE temporary table $temp_table " . $sql;
			$temp_result = $wpdb->query  ( $sql );
			if ( false === $temp_result ) {
				WIC_Function_Utilities::wic_error ( sprintf( 'Error in download, likely permission error.' ), __FILE__, __LINE__, __METHOD__, true );
			}			
		
		} else {
			// do search
			$this->result = $wpdb->get_results ( $sql );
		 	$this->showing_count = count ( $this->result );
			// only do sql_calc_found_rows on id searches; in other searches, found count will always equal showing count
			$found_count_object_array = $wpdb->get_results( $sql_found );
			$this->found_count = $found_count_object_array[0]->found_count;
			// set value to say whether found_count is known
			$this->found_count_real = $compute_total;
			$this->retrieve_limit = $retrieve_limit;
			$this->outcome = true;  // wpdb get_results does not return errors for searches, so assume zero return is just a none found condition (not an error)
											// codex.wordpress.org/Class_Reference/wpdb#SELECT_Generic_Results 
			$this->explanation = ''; 
		}

	}	
	
	/*
	*
	* parse a save update array into clauses and value array for pre-processing by $wpdb->prepare before a save or update 
	*  -- with just an ID clause in the array, just sets up to do a time stamp 
	*
	*/
	protected function parse_save_update_array( $save_update_array ) {
		global $wpdb;
		$set_clause_with_placeholders = $wpdb->prepare ( 'SET last_updated_time = %s', array ( current_time ('mysql') ) );
		$set_array = array();
		$current_user = wp_get_current_user();
		$entity_id = '';
		
		foreach ( $save_update_array as $save_update_clause ) {
			if ( $save_update_clause['key'] != 'ID' ) {
				$set_clause_with_placeholders .= ', ' . $save_update_clause['key'] . ' = %s'; 		
				$set_value_array[] = $save_update_clause['value'];
				if ( isset( $save_update_clause['secondary_alpha_search'] ) ) {
					// this supports address line being indexed by street name -- assumes address line begins with number/suffix and then a space
					// may entrain apartment number, but not a problem if searching this field on a right wild card basis.
					// implemented in data dictionary as field type alpha
					$set_clause_with_placeholders .= ', ' . $save_update_clause['secondary_alpha_search'] . ' = %s ';
					$first_space = strpos( $save_update_clause['value'], ' ' );	
					$probable_alpha_value = trim ( substr ( $save_update_clause['value'], $first_space ) );
					$set_value_array[] = $probable_alpha_value;
				}
			} else { 
				$entity_id = $save_update_clause['value'];
			}
		}

		$set_clause_with_placeholders .= ', last_updated_by = %d ';
		$set_value_array[]  = $current_user->ID;		

		if ( $entity_id > '' ) {
			$set_value_array[] = $entity_id; // tag entity ID on to end of array (will not be present in save cases, since is a readonly field)
		}	// see setup in WIC_CONTROL_Parent::create_update_clause
	
		return ( array (
			'set_clause_with_placeholders' => $set_clause_with_placeholders,
			'set_value_array'	=> $set_value_array,
				)		
			);
	}

	/* 
	* hard delete a record
	*
	* this is passed through from parent delete_by_id which is used in WIC_Control_Multivalue->set_value to clean out deleted rows
	* before populating the data_object_array (in other words before other updates coming from a form are applied) 
	*/
	protected function db_delete_by_id ( $id ) {
		global $wpdb;		
		$table  = $wpdb->prefix . 'wic_' . $this->entity;
		$outcome = $wpdb->delete ( $table, array( 'ID' => $id ) );
		if ( ! ( 1 == $outcome ) ) {
			WIC_Function_Utilities::wic_error ( sprintf( 'Database error on execution of requested delete of %s.', $this->entity  ), __FILE__, __LINE__, __METHOD__, false );
		} 
	}

	/* 
	* 	do a time stamp -- expects $table to be last part of table name (e.g., as in $this->entity or $this->parent ) 
	*
	*	used by the delete function to time stamp parent when deleting child
	*  also used by the top level entity to do time stamp on parent (e.g. constituent) if child entities (e.g. email) made changes 
	*/
 
	protected function db_do_time_stamp ( $table, $id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'wic_' . $table;
		$last_updated_time 	= current_time('mysql');
		$last_updated_by 		= get_current_user_id();
		$sql = "UPDATE $table SET last_updated_time = '$last_updated_time', last_updated_by = $last_updated_by WHERE ID = $id";
		$results = $wpdb->query ( $sql );
		if ( false === $results ) {
			WIC_Function_Utilities::wic_error ( sprintf ( 'Time stamp error for %s ID # %s', $this->entity, $id ) , __FILE__, __LINE__, __METHOD__,false );
		}
		// no return error handling
	}

	/* time stamp -- get time stamp from current table for requested id */ 
	public function db_get_time_stamp ( $id ) { 
		global $wpdb;
		$table  = $wpdb->prefix . 'wic_' . $this->entity;
		return ( $wpdb->get_row ( " SELECT last_updated_by, last_updated_time FROM $table WHERE ID = $id " ) );  		 
	}

	/* 
	*
	* 	retrieve list of results from list of ids -- supports list classes 
	*
	*/
	protected function db_list_by_id ( $id_string ) { 

		global $wpdb;
		global $wic_db_dictionary;	

		$top_entity = $this->entity;
		$table_array = array( $this->entity );
		$where = $top_entity . '.ID IN ' . $id_string . ' ';
		$join = $wpdb->prefix . 'wic_' . $this->entity . ' AS ' . $this->entity;
		$sort_clause = $wic_db_dictionary->get_sort_order_for_entity( $this->entity );
		$order_clause = ( '' == $sort_clause ) ? '' : " ORDER BY $sort_clause"; 
		$select_list = '';	

		// assemble list query based on dictionary list specification
		$fields =  $wic_db_dictionary->get_list_fields_for_entity( $this->entity );
		// retrieving those with non-zero listing order -- SQL error will occur if none have non-zero listing order
		// either for main list or for fields of multivalue entities which are included in main list
		foreach ( $fields as $field ) {
			// standard single field addition to list
			if ( 'multivalue' != $field->field_type ) {
				$select_list .= ( '' == $select_list ) ? $top_entity . '.' : ', ' . $top_entity . '.' ;
				$select_list .= $field->field_slug . ' AS ' . $field->field_slug ;
			// else multivalue field calls for multiple instances, compressed into single value
			} else {
				// create comma separated list of list fields for entity 
				$select_list .= '' == $select_list ? '' : ', ';
				$sub_fields = $wic_db_dictionary->get_list_fields_for_entity( $field->field_slug );
				$sub_field_list = ''; 
				foreach ( $sub_fields as $sub_field ) {
					if ( 'ID' != $sub_field->field_slug ) { 
						$sub_field_list .= ( '' == $sub_field_list ) ? $field->field_slug . '.' : ',\'| \',' . $field->field_slug . '.' ;
						$sub_field_list .= $sub_field->field_slug;
					}
				}

				// concat multivalues for single row display
				$select_list .= ' GROUP_CONCAT( DISTINCT ' . $sub_field_list . ' SEPARATOR \', \' ) AS ' . $field->field_slug;
				$join .= ' LEFT JOIN ' .  $wpdb->prefix . 'wic_' . $field->field_slug . ' ' . $field->field_slug . ' ON ' . 
					$this->entity . '.ID = ' . $field->field_slug . '.' . $this->entity . '_id ';
			}		
		}	

		$sql = 	"SELECT $select_list
					FROM 	$join
					WHERE $where
					GROUP BY $top_entity.ID
					$order_clause
					";

		$this->sql = $sql; 
		if ( '()' != $id_string ) {
			$this->result = $wpdb->get_results ( $sql );
	 		$this->showing_count = count ( $this->result );
	 		$this->found_count = $this->showing_count;
	 	} else {
	 		$this->sql = 'Empty ID string -- not valid'; 
			$this->result = array();
			$this->showing_count = 0;
			$this->found_count = 0;	 	
	 	} 
		$this->outcome = true;  // wpdb get_results does not return errors for searches, so assume zero return is just a none found condition (not an error)
										// codex.wordpress.org/Class_Reference/wpdb#SELECT_Generic_Results 
		$this->explanation = ''; 
	}

	// quick look up that by passes search logging, etc. for use in displaying the search log
	public static function get_constituent_name ( $id ) {

		global $wpdb;
		$table = $wpdb->prefix . 'wic_constituent';
		
		$sql = $wpdb->prepare(
			"
			SELECT trim( concat ( first_name, ' ', last_name ) ) as name
			FROM $table
			WHERE ID = %s
			",
			array ( $id )
			);

		$result = $wpdb->get_results( $sql ); 		

		return ( isset ( $result[0]->name ) ?  $result[0]->name : ''  );
	
	}	

	// quick look up that by passes search logging, etc. for use in displaying the search log
	public static function get_constituent_names ( $id ) {

		global $wpdb;
		$table = $wpdb->prefix . 'wic_constituent';
		
		$sql = $wpdb->prepare(
			"
			SELECT first_name, middle_name, last_name, salutation, gender
			FROM $table
			WHERE ID = %s
			",
			array ( $id )
			);

		$result = $wpdb->get_results( $sql ); 		

		return ( isset ( $result[0] ) ?  $result[0] : ''  );
	
	}	

	// option counts for use in displaying existing option usage in options update screens
	protected function db_get_option_value_counts( $field_slug ) {
			
		global $wpdb;
	
		$table = $wpdb->prefix . 'wic_' . $this->entity;
	
		$sql = "SELECT $field_slug as field_value, count(ID) as value_count from $table group by $field_slug ORDER BY $field_slug";
	
		$field_value_counts = $wpdb->get_results( $sql );
		
		return ( $field_value_counts );	
	
	} 

}

