<?php
/*
*
*	wic-entity-synch.php
*
*/

class WIC_Entity_Synch extends WIC_Entity_Parent {
	
	/*
	*
	* Request handlers
	*
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition
		$this->entity = 'synch';
	} 

	public function show_synch_status() {
		$data_object_array = array();
		$message = '';
		$message_level = 'guidance';
		$synch_form = new WIC_Form_Synch;
		$synch_form->layout_form ( $data_object_array, $message, $message_level );	
	}

	public static function do_synch( $synch_action, $mode ) {
	
		if ( 'init' != $mode && 'exec' != $mode ) {
			return array ( 'response_code' => false, 'output' => "Attempted mode, \"$mode\", not supported by WIC_Entity_Synch::do_synch." );	
		}
	
		// get current blog db prefix
		global $wpdb;
		$this_blog_prefix = $wpdb->prefix;
		
		// get master blog db prefix
		switch_to_blog( BLOG_ID_CURRENT_SITE );
		$master_blog_prefix = $wpdb->prefix;
		restore_current_blog();
		
		/*
		*
		* create sql fragments for use in queries
		*
		*/
		// identify tables
		$current_constituent_table 	= $this_blog_prefix   . 'wic_constituent'; 	// c
		$current_address_table 		= $this_blog_prefix   . 'wic_address';		// ca
		$master_constituent_table 	= $master_blog_prefix . 'wic_constituent';	// m
		$master_address_table 		= $master_blog_prefix . 'wic_address';		// ma
		
		// get district selection parameters
		$selector_field = get_option( 'wic_owner_type' );
		$selector_id 	= get_option( 'wic_owner_id' );
		$address_record_selectors = array ( 'city', 'state', 'zip' );
		// create fragments to implement parameters
		if ( in_array( $selector_field, $address_record_selectors ) ) {
			$selector_join 	= " LEFT JOIN $master_address_table ma on ma.constituent_id = m.id ";
			$selector_where = $wpdb->prepare ( " ma.$selector_field = %s " , $selector_id );
			$selector_not_where = $wpdb->prepare ( " ma.$selector_field != %s " , $selector_id );
		} else {
			$selector_join = '';
			$selector_where = $wpdb->prepare ( " m.$selector_field = %s " , $selector_id );
			$selector_not_where = $wpdb->prepare ( " m.$selector_field != %s " , $selector_id );
		}
		
		// create field lists
		global $wic_db_dictionary;
		$constituent_field_array 	= $wic_db_dictionary->get_synch_fields_for_entity('constituent');
		$constituent_field_list 	= implode( ',', $constituent_field_array ); // list without table identifier
		$address_field_array 		= $wic_db_dictionary->get_synch_fields_for_entity('address');
		$address_field_list 		= implode( ',', $address_field_array );
		$ma_address_field_list = 	'ma.' . implode( ', ma.', $address_field_array  );
		// create constituent change condition list
		$constituent_change_where_list = '';
		$or = "";
		foreach ( $constituent_field_array as $field ) {
			if ( in_array ( $field, array ('last_updated_time', 'last_updated_by' ))) {continue;}
			$constituent_change_where_list .=  " $or ( c.$field != m.$field AND m.$field > '' ) ";
			$or = "OR";

		}
		// create constituent set clause
		$constituent_change_set_clause = '';
		$comma = '';
		foreach ( $constituent_field_array as $field ) {
			$constituent_change_set_clause .=  " $comma c.$field = if( m.$field > '', m.$field, c.$field ) ";
			$comma = ",";

		}
		// create address change condition list 
		$address_change_where_list = '';
		$or = "";
		foreach ( $address_field_array as $field ) {
		if ( in_array ( $field, array ('last_updated_time', 'last_updated_by' ))) {continue;}
			$address_change_where_list .=  " $or ( ca.$field != ma.$field AND ma.$field > '' ) ";
			$or = "OR";
		
		}		
		// create address set clause
		$address_change_set_clause = '';
		$comma = '';
		foreach ( $address_field_array as $field ) {
			$address_change_set_clause .=  " $comma ca.$field = if( ma.$field > '', ma.$field, ca.$field ) ";
			$comma = ",";

		}		
		
		/*
		*
		* For each $synch_action/$mode combination
		* 	- Use fragments to construct SQL
		*	- Execute SQL
		*	- Return report
		*
		*/
		if ( 'added' == $synch_action ) {
			/* 
			*
			* sql is designed to avoid catastrophic over match if admin fails to set registration_id
			* also refuses to pass dup records for single registration id; take last added
			*
			*/
			$list_sql = "(
					SELECT max(m.id) as mid, m.registration_id as mregistration_id
					FROM $master_constituent_table m
					$selector_join
					LEFT JOIN $current_constituent_table c 
					ON c.registration_id = m.registration_id 
					WHERE 
						$selector_where AND 
						m.registration_id > '' AND
						c.id IS NULL
					GROUP BY m.registration_id
					) 
				";
			if ( 'init' == $mode ) {
				$result = $wpdb->get_results ( "SELECT COUNT(mid) as to_be_added FROM $list_sql master_id_list" );
				if ( !$result ) { // result should be non-empty array, even if count is zero
					$return_html = 'Error in add count: ' . $wpdb->last_error;
					return array ( 'response_code' => false, 'output' => $return_html );
 				}
				$count = $result[0]->to_be_added;		
				if ( !$count ) {
					$return_html = "<span class=\"dashicons dashicons-yes\"></span>No new constituents to be added.";
				} else {
					$return_html = self::create_synch_action_button ( 'added', "Add $count Constituents"  );
				};
				return array ( 'response_code' => true, 'output' => $return_html );
			} elseif ( 'exec' == $mode ) { 
				$temp_sql = "CREATE TEMPORARY TABLE master_id_list $list_sql";
				$result = $wpdb->query ( $temp_sql );
				$add_sql = "
					INSERT INTO $current_constituent_table ( $constituent_field_list, registration_synch_status, is_my_constituent )
					SELECT $constituent_field_list, 'A', 'Y' 
					FROM $master_constituent_table m
					INNER JOIN master_id_list
					ON master_id_list.mid = m.id
					";
				$result = $wpdb->query ( $add_sql );
				$constituent_add_error = $wpdb->last_error;
				/*
				*
				* the following is safe, because id is always unique and registration id is made unique in master list set 
				* and registration id on master was previously not existent on current table so registration id on current table is also unique
				*
				* if master contains multiple address types, they will be saved as multiple instances of registered address
				* if screen for type, create another possible admin error (defining wrong type or omitting type); unlikely
				* to be multiple address records, but accept them.
				*
				*/
				$add_address_sql = "
					INSERT INTO $current_address_table ( constituent_id, address_type, $address_field_list )
					SELECT c.id, 'wic_reserved_4', $ma_address_field_list
					FROM $master_address_table ma 
					INNER JOIN master_id_list ON master_id_list.mid = ma.constituent_id
					INNER JOIN $current_constituent_table c on c.registration_id = master_id_list.mregistration_id
				";
				$result_a = $wpdb->query ( $add_address_sql );
				$address_add_error = $wpdb->last_error;
			
				if ( ! $result ) {
					return array ( 'response_code' => false, 'output' => "No constituents added. Database error or perhaps another user already added the constituents." .
						( $constituent_add_error > '' ? "<p>Insert sql error was: $constituent_add_error.</p>" : '' ) );	
				} else {
					$missing_addresses = $result - $result_a;
					if ( 0 < $missing_addresses ) {  
						return array ( 'response_code' => true, 'output' => "$result constituents were added, but $missing_addresses had missing addresses." . 
							( $address_add_error > '' ? " There was a database error adding addresses: $address_add_error." : '' ) );	
					} else {
						return array ( 'response_code' => true, 'output' => "<span class=\"dashicons dashicons-yes\"></span>$result constituents were added. " );
					}
				}
			}

		} elseif ( 'deleted' == $synch_action ) {
			/*
			* note regarding duplicate registration_id's in the delete action:
			* registration_id should be unique on master copy and is forced to uniqueness in process of addition to district copy (IF records added by synch)
			* if it is non-unique on master and the dups are in different districts, then it will be added to both, but will also show as deletable (departed) on both
			*/
			if ( 'init' == $mode ) {
				$list_sql = "
						SELECT count( c.id ) as to_be_deleted
						FROM $current_constituent_table c
						LEFT JOIN $master_constituent_table m
						ON m.registration_id = c.registration_id 
						$selector_join 
						WHERE 
							c.registration_id > '' AND c.registration_synch_status != 'N' AND
							( 
								m.registration_id IS NULL 
								OR $selector_not_where
							) 
						";
				$result = $wpdb->get_results ( $list_sql ); 
				if ( !$result ) { // result should be non-empty array, even if count is zero
					$return_html = 'Error in delete count: ' . $wpdb->last_error;
					return array ( 'response_code' => false, 'output' => $return_html );
 				}
				$count = $result[0]->to_be_deleted;		
				if ( !$count ) {
					$return_html = "<span class=\"dashicons dashicons-yes\"></span>No constituents to be marked. ";
				} else {
					$return_html = self::create_synch_action_button ( 'deleted', "Mark $count Constituents as Not Found"  );
				};			
				return array ( 'response_code' => true, 'output' => $return_html );

			} elseif ( 'exec' == $mode ) {
				$delete_sql = "
						UPDATE $current_constituent_table c		
						LEFT JOIN $master_constituent_table m
						ON m.registration_id = c.registration_id 
						$selector_join 
						SET c.registration_synch_status = 'N'
						WHERE 
							c.registration_id > '' AND c.registration_synch_status != 'N' AND
							( 
								m.registration_id IS NULL 
								OR $selector_not_where
							) 
						";			
				$result = $wpdb->query( $delete_sql );
				$delete_error = $wpdb->last_error;

				if ( ! $result ) {
					return array ( 'response_code' => false, 'output' => "Apparent database error.  No constituents marked deleted." .
							( $delete_error > '' ? " There was a database error marking not found records: $delete_error." : '' ) );		
				} else {
					return array ( 'response_code' => true, 'output' => "<span class=\"dashicons dashicons-yes\"></span>$result constituents were marked as not found." );
				}
			}	
		} elseif ( 'registration' == $synch_action ) {
			/*
			* note regarding duplicate registration_id's in the registration action:
			* if registration_id is non-unique on master within the same district, then this synch will overcount by number of dups
			* will update district copy with one or the other; more refined approach forces multiple steps at exect stage
			*/
			if ( 'init' == $mode ) {
				$list_sql = "
						SELECT count( c.id ) as to_be_refreshed
						FROM $master_constituent_table m	
						INNER JOIN $current_constituent_table c ON m.registration_id = c.registration_id 
						$selector_join 
						WHERE 
							c.registration_id > '' AND $selector_where
							AND ( $constituent_change_where_list )
						";
				$result = $wpdb->get_results ( $list_sql );
				if ( !$result ) { // result should be non-empty array, even if count is zero
					$return_html = 'Error in registration refresh count: ' . $wpdb->last_error;
					return array ( 'response_code' => false, 'output' => $return_html );
 				}
				$count = $result[0]->to_be_refreshed;		
				if ( !$count ) {
					$return_html = "<span class=\"dashicons dashicons-yes\"></span>No constituents to be refreshed. ";
				} else {
					$return_html = self::create_synch_action_button ( 'registration', "Refresh data for $count Constituents"  );
				};	
				return array ( 'response_code' => true, 'output' => $return_html );
		
			} elseif ( 'exec' == $mode ) {
				$update_sql = "
						UPDATE $master_constituent_table m	
						INNER JOIN $current_constituent_table c ON m.registration_id = c.registration_id 
						$selector_join 
						SET $constituent_change_set_clause, c.registration_synch_status = 'R'
						WHERE 
							c.registration_id > '' AND $selector_where
							AND ( $constituent_change_where_list )
						";		
				$result = $wpdb->query( $update_sql );
				$registration_error = $wpdb->last_error;
				
				if ( ! $result ) {
					return array ( 'response_code' => false, 'output' => "Apparent database error.  No constituents refreshed." .
						( $registration_error > '' ? " There was a database error refreshing registrations: $registration_error." : '' ) );		
	
				} else {
					return array ( 'response_code' => true, 'output' => "<span class=\"dashicons dashicons-yes\"></span>Registration data refreshed for $result constituents. " );
				}
			}	
		} elseif ( 'address' == $synch_action ) {
			/*
			* note regarding duplicates in the address action
			* duplicate registration id's on master ( multiple addresses for single registration id on master) will result in repetitive updates to the same record,
			* leaving one address in place which will result in refreshes from one to the other (repetitively in successive user actions).
			* address for one dup could be associated with registration header for another dup.
			*/
			// set additional sql fragments
			$main_join = 
				" $master_constituent_table m	
				INNER JOIN $master_address_table ma ON ma.constituent_id = m.id	
				INNER JOIN $current_constituent_table c ON m.registration_id = c.registration_id 
				LEFT JOIN $current_address_table ca ON ca.constituent_id = c.id AND ca.address_type = 'wic_reserved_4' ";
			$constant_where 	= " WHERE c.registration_id > '' AND $selector_where ";
			$primary_and_where 	= " AND ( ca.id IS NULL OR $address_change_where_list )";
		
			if ( 'init' == $mode ) {
				$list_sql = "
						SELECT count( c.id ) as to_be_refreshed
						FROM $main_join
						$constant_where
						$primary_and_where
						";
				$result = $wpdb->get_results ( $list_sql );
				if ( !$result ) { // result should be non-empty array, even if count is zero
					$return_html = 'Error in address refresh count: ' . $wpdb->last_error;
					return array ( 'response_code' => false, 'output' => $return_html );
 				}
				$count = $result[0]->to_be_refreshed;		
				if ( !$count ) {
					$return_html = "<span class=\"dashicons dashicons-yes\"></span>No addresses to be refreshed.";
				} else {
					$return_html = self::create_synch_action_button ( 'address', "Refresh addresses for $count Constituents"  );
				};	
				return array ( 'response_code' => true, 'output' => $return_html );
		
			} elseif ( 'exec' == $mode ) {

				// first mark top level entity as refreshed (before change database making count inaccessible )
				$mark_refreshed_sql = "		
						UPDATE $main_join
						SET c.registration_synch_status = 'R'	
						$constant_where
						$primary_and_where
						";
				$refreshed_count = $wpdb->query ( $mark_refreshed_sql );

				// next update cases where type 4 addresses were found but differed
				$refresh_found_sql = "
						UPDATE $main_join
						SET $address_change_set_clause	
						$constant_where
						AND ($address_change_where_list )
						";		
				$result_found = $wpdb->query( $refresh_found_sql );
				
				// now do inserts for the rest
				$refresh_unfound_sql = "
						INSERT INTO $current_address_table ( constituent_id, address_type, $address_field_list )
						SELECT c.id, 'wic_reserved_4', $ma_address_field_list
							FROM $main_join	
							$constant_where
							AND ( ca.id IS NULL )
							";				
				$result_unfound = $wpdb->query( $refresh_unfound_sql );
				

				$total_addresses_refreshed = $result_found + $result_unfound;	
				/*
				*
				* not that $refreshed_count may not equal $total_addresses_refreshed because if status is already 'R' from previous, does not count in $refreshed_count
				*
				*/		
				if ( ! $total_addresses_refreshed) {
					return array ( 'response_code' => false, 'output' => "Apparent database error.  No constituent addresses refreshed.");	
				} else {
					return array ( 'response_code' => true, 'output' => "<span class=\"dashicons dashicons-yes\"></span>Addresses were refreshed for $total_addresses_refreshed constituents." );
				}
			} 	
		} else {
			return array ( 'response_code' => false, 'output' => "Attempted action, \"$synch_action\",not supported by WIC_Entity_Synch::do_synch." );	
		}
	
	}



	private static function create_synch_action_button ( $action, $action_label ) {
				// create the basic button html
		$button_args = array (
			'name'	=> $action . '_synch_button',
			'id'	=> $action . '_synch_button',
			'type'	=> 'button',
			'value'	=> '',
			'button_class'	=> 'wic-form-button action-synch-button ',
			'button_label'	=>	$action_label,
			'title'	=>	__( 'Do action now.', 'wp-issues-crm' ),
		);
		return WIC_Form_Parent::create_wic_form_button( $button_args );
	
	}






	protected function special_entity_value_hook ( &$wic_access_object ) {}
	
}
