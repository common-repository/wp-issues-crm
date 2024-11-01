<?php
/*
*
*	class-wic-interface-transaction.php
*
*	(1) accepts array of data describing one constituent and one activity with respect to that constituent
*	(2) checks for possibly matching constituent
*   (3) if necessary adds constituent
*   (4) adds activity
*
*/
class WIC_Interface_Transaction {

	const SUBMITTED_DATA_BOUNDARY 			=  "\n\nSUBMITTED DATA:\n\n"; 
	
	private $fields_array; 
	private $data_object_array;  // will be dual keyed single array -- by entity_slug, field_slug
	private $wic_query;
	private $wic_email_query;
	private $wic_address_query;
	private $wic_phone_query;
	private $wic_activity_query;
	private $wic_issue_query;

	public function __construct() {
		global $wic_db_dictionary;
		/* get array of field arrays -- want only entity and field within each field array */
		$this->fields_array = $wic_db_dictionary->get_uploadable_fields (); 
		// set up WIC search object for matching and update
		$this->wic_query 			= WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );
		// set up additional query objects for updates
		$this->wic_email_query	 	= WIC_DB_Access_Factory::make_a_db_access_object( 'email' );
		$this->wic_address_query 	= WIC_DB_Access_Factory::make_a_db_access_object( 'address' );
		$this->wic_phone_query 		= WIC_DB_Access_Factory::make_a_db_access_object( 'phone' );
		$this->wic_activity_query 	= WIC_DB_Access_Factory::make_a_db_access_object( 'activity' );
	} 

	public function save_constituent_activity ( 
		$key_value_array, // if includes key 'preset_wic_constituent_id', this will be handled as defined constituent ID and no look will be done
		$special_activity_key_value_array = array(),
		$policies = array(), 
		$unsanitized = true, 
		$match_strategies =  array (
			'emailfn',	
			'email',
			'lnfnaddr',
			'lnfnaddr5',
			'lnfndob',
			'lnfnzip',
			'fnphone',
			), 
		$dry_run = false, // return found constituent ID or 0 after lookup
		$email_activity = false
		) {

		global $wpdb;

		// default update rules
		$policy_identity 		= 'blank_existing';
		$policy_custom_data 	= 'non_blank_incoming';
		$policy_phone			= 'never'; // this is the incoming email policy; form default for interfaces is set as update_test_match
		$policy_address			= 'never'; // this is the incoming email policy; default for interfaces is set as update_test_match
		$policy_email			= 'always';
		extract ( $policies );
		/*
		* fields in key value array can be keyed arbitrarily, BUT if they don't contain first_name, last_name or email_address, will be saving unidentified constituents
		* 
		* incoming keys will be compared to the list of uploadable fields and those uploadable will be saved as such
		* keys that don't match uploadable fields will be bulked into the activity_note field
		* special_activity_key_value_array will be saved on to the activity record -- keys must be correctly named or will have error: this is responsibility of the calling routine
		*
		* incoming data will be sanitized if unsanitized = true ( full sanitize_text_field, except for the non-uploadable fields which will just be kses_post (kill scripts, but keep white space and safe tags)
		*/
	
		// begin by resetting data object array -- do not assume that repeated calls to save will have same set of $keys
		$this->data_object_array	= array();
				
		// add case review date into incoming array if missing for an assigned case		
		if ( isset ( $key_value_array['case_assigned'] ) ) {
			if ( $key_value_array['case_assigned'] > 0 ) {
				$key_value_array['case_review_date'] = current_time( 'Y-m-d');
			}
		}

		// add activity note into incoming array as blank if missing
		if ( ! isset ( $key_value_array['activity_note'] ) ) {
			 $key_value_array['activity_note'] = '';
		}

		// add activity date into incoming array as today if missing
		if ( ! isset ( $key_value_array['activity_date'] ) ) {
			 $key_value_array['activity_date'] = current_time( 'Y-m-d');
		}

		// now populate data_object_array ( two dimensional rather than separate objects );
		foreach ( $this->fields_array as $field ) {
			if ( isset ( $key_value_array[$field['field']] ) ) {
				if ( 
					'ID' != $field['field'] && // have to avoid treating ID as constituent ID, push it to activity_note as unrecognized
					false === stripos(  $field['field'], 'address_line_part_') // address_line_part_ fields will not be visible if saved per se, push to activity_note (previous step should consolidate);
					) {
					$control = WIC_Control_Factory::make_a_control( $field['type'] );
					$control->initialize_default_values( $field['entity'], $field['field'], ''); 
					$control->set_value(  $key_value_array[$field['field']] );
					$this->data_object_array[$field['entity']][$field['field']] = $control;
				}
			}
		}

		// pack all fields into string and append to activity_note field for reference in cases where updates not applied
		// but check if handling an email activity to layout activity_note and do not append (redundant)
		if ( ! $email_activity ) {
			$data = self::SUBMITTED_DATA_BOUNDARY;
			$excluded_keys = array ( 'activity_note', 'g-recaptcha-response' );
			foreach ( $key_value_array as $key => $value ) {
				if ( ! in_array( $key, $excluded_keys ) && $value ) {
					$data .= "$key: $value,\n\n";
				}
			}
			$this->data_object_array['activity']['activity_note']->set_value ( $this->data_object_array['activity']['activity_note']->get_value () . $data);
		}
		// sanitize if not already sanitized
		if ( $unsanitized ) {
			foreach ( $this->data_object_array as $entity => $controls ) {
				foreach ( $controls as $control ) {
					$control->sanitize();
				}
			}
		}		

		// validate preset constituent id
		$constituent_id = 0;
		$added_new_constituent = false;  // set true if add constituent
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		if ( isset ( $key_value_array['preset_wic_constituent_id'] ) ) {
			$constituent_id = $key_value_array['preset_wic_constituent_id'];  
			// check id -- note that this could return a legacy soft-deleted constituent_id -- accept this outcome
			$check_id = array();
			if ( $constituent_id ) {
				$check_id = $wpdb->get_results ( "SELECT ID FROM $constituent_table WHERE ID = $constituent_id" );
			}
			// if id not good, set to zero and proceed to look up using other variables if available
			if ( ! $check_id ) {
				$constituent_id = 0;
			// if it is good and doing dry run, then done
			} elseif ( $dry_run ) {
				return $constituent_id;
			}
		}

		// if no valid preset, look up and/or add constituent
		if ( ! $constituent_id ) {

			// get collection of match strategies to try
			$strategies_object = new WIC_Entity_Upload_Match_Strategies;
			$interface_match_strategies = $strategies_object->get_match_strategies ( $match_strategies );

			// set up fixed search paramters		
			$search_parameters = array(
				'select_mode' => 'id',
				'retrieve_limit' => 1,
				'sort_order' => false,
			);
				
			// the query object persists across calls, so need to assure that values are reset before a query attempt
			$this->wic_query->found_count = 0;

			// search for a matching constituent, running through strategies in order
			foreach ( $interface_match_strategies as $strategy => $strategy_elements ) {
				$meta_query_array = array();
				$element_missing = false;
				// see if elements are available for strategy and assemble array
				foreach ( $strategy_elements as $element ) {
					$value = '';
					if ( isset ( $this->data_object_array[$element[0]][$element[1]] ) ) {
						$value = $this->data_object_array[$element[0]][$element[1]]->get_value();
					}
					if ( $value > '' ) {
						$meta_query_array[] =  array (
							'table'	=> $element[0],
							'key' 	=> $element[1],
							'value'	=> 0 == $element[2] ? $value : WIC_Entity_Email_Process::first_n1_chars_or_first_n2_words ( $value, $element[2], 1 ),
							'compare'=> 0 == $element[2] ? '=' : 'like',
							'wp_query_parameter' => '',
						);	
					} else {
						$element_missing = true;
						break;
					}			
				}
				// if array assembled OK, do search
				if ( ! $element_missing )  {
					$this->wic_query->search ( $meta_query_array, $search_parameters );	
				}

				// if successful quit
				if ( $this->wic_query->found_count > 0 ) {
					break;
				} 
			}

			// return at this point if just doing a dry run to find matched constituent
			if ( $dry_run ) {
				if (  0  == $this->wic_query->found_count ) {
					return 0; 
				} else {	
					return $this->wic_query->result[0]->ID;
				}
			}


			// if no match add constituent top record
			if ( 0  == $this->wic_query->found_count ) {
				// note that names and email could be all blank -- allowing this from interface  . . . up to interface input to prevent
				$save_update_array = array ();
				foreach ( $this->data_object_array['constituent'] as $control ) {
					$save_update_array[] = $control->create_update_clause();				
				}
				$this->wic_query->direct_save( $save_update_array );
				$constituent_id = $this->wic_query->insert_id;
				$added_new_constituent = true;
			// otherwise use found constituent id (note set query parameter result limit as 1 above == relaxing dedup here)
			} elseif  ( 1  == $this->wic_query->found_count )   {
				$constituent_id = $this->wic_query->result[0]->ID;
			}

			/*
			* handle error -- if don't have a constituent_id yet, then error; not checking all WIC database response codes after this -- assume OK if this OK
			*/
			if ( 0 == $constituent_id ) {
				return array ( 'response_code' => false, 'output' => 'Database error saving new constituent record -- likely database outage; processing halted.' );
			} 

		} // close if no preset constituent id
		
		// in case of pre-existing (preset or found) constituent, do updates as appropriate (if was new, then all constituent record  level values already set)
		if ( ! $added_new_constituent ) {

			// first determine whether the interface is doing assignment
			$case_assigned = 0;
			if ( isset ( $this->data_object_array['constituent']['case_assigned'] ) ) {
				$case_assigned = $this->data_object_array['constituent']['case_assigned']->get_value();
			}

			// only consider update if there is a staff to be assigned and their may be other updates to be applied
			if ( $case_assigned > 0 || $policy_identity != 'never' || $policy_custom_data != 'never' ) {
			
				// first, get the relevant constituent record to test values -- so far have only been retrieving ID to test match
				$target_record_array = $wpdb->get_results ( "SELECT * FROM $constituent_table WHERE ID = $constituent_id" );
				$target_record = $target_record_array[0];
				/*
				* apply policies to keep or delete field in incoming data_object_array before update	
				* 
				*/
				$save_update_array = array();
				foreach ( $this->data_object_array['constituent'] as $field => $control ) {
					if ( 'custom_field_' == substr( $field, 0, 13 ) ) {
						$applicable_policy = $policy_custom_data;
					} elseif ( in_array ( $field, array ( 'case_assigned', 'case_status', 'case_review_date' ) ) ) {
						if ( 'case_assigned' == $field ) {
							if ( 0 != $target_record->case_assigned &&
								 "0" < $target_record->case_status ) {
								$applicable_policy = 'never'; // don't reassign open cases	 
							} else {
								$applicable_policy = 'non_blank_incoming'; // don't unassign cases
							}
						} elseif ( 'case_status' == $field ) {
							if ( "0" < $target_record->case_status ) {
								$applicable_policy = 'never'; // don't change open "1" or other user defined status -- string > "0"
							} else {
								$applicable_policy = 'always'; // freely overwrite unset or "0" closed status
							}
						} else {
							$applicable_policy = 'always'; // case review date
						}
					} else {
						$applicable_policy = $policy_identity;
					} 
					switch ( $applicable_policy ) {
						case 'always':
							$save_update_array[] = $control->create_update_clause();
							break;
						case 'never':
							break;
						case 'non_blank_incoming':
							if ( '' < $control->get_value() ) {
								$save_update_array[] = $control->create_update_clause();
							}
							break;
						case 'blank_existing':
							if ( '' == $target_record->$field ) {
								$save_update_array[] = $control->create_update_clause();
							}
					}
				}

				$save_update_array[] = array ( 
					'key' => 'ID',
					'value' => $constituent_id,
					'wp_query_parameter' => ''
				);
				$this->wic_query->direct_update( $save_update_array );
			}
		}
		
		/*
		* now do updates for the multivalue rows
		*/
		
		$multivalue_constituent_fields = array ( 
			'activity'  => '', 
			'address'	=> 'address_line', 
			'phone'		=> 'phone_number', 
			'email' 	=> 'email_address'
		);

		foreach ( $multivalue_constituent_fields as $row => $row_equality_test ) {
		
			/* 
			*  if don't have a row of data, nothing to do.
			*  
			*  even as to activity calling function should have supplied at least one from the row if wished to populate it
			*  -- special_activity_key_value_array is only for link fields -- not enough to support a row
			*/
			if ( ! isset ( $this->data_object_array[$row] ) ) {
				continue;
			}
		
			// determine row update policy
			if ( $row != 'activity' && ! $added_new_constituent ) {
				$policy_name = 'policy_' . $row;
				$applicable_policy = $$policy_name;
			} else {
				$applicable_policy = 'regardless'; 
			}
			// if 'never' for this row, done with this row
			if ( 'never' == $applicable_policy ) {
				continue;
			}
	 	
		 	// presumptive action based on policy
		 	$action = 'save';
		 	// if 'activity' row or new constituent, just take presumptive action (save), otherwise, test existing data to set action plan
		 	if ( 'regardless' != $applicable_policy ) {
				$row_table = $wpdb->prefix . 'wic_' . $row;
				$row_record_array = $wpdb->get_results ( "SELECT * FROM  $row_table WHERE constituent_id = $constituent_id" );
				// does the type already exist ( all incoming key value arrays have type values, possibly empty )
				$matching_type_record = 0;
				$matching_type_value = '';
				if ( ! empty ( $row_record_array )  ){
					foreach ( $row_record_array as $row_record) {
						if ( $this->data_object_array[$row][$row.'_type']->get_value() == $row_record->{$row . '_type'} ) {
							$matching_type_record = $row_record->ID;
							$matching_type_value  = $row_record->$row_equality_test;
							break;
						}
					}
				}
				// does the value already exist
				$matching_value_record = 0;
				if ( ! empty ( $row_record_array ) && isset ( $this->data_object_array[$row][$row_equality_test] ) ) {
					foreach ( $row_record_array as $row_record) {
						if ( $this->data_object_array[$row][$row_equality_test]->get_value() == $row_record->$row_equality_test ) {
							$matching_value_record = $row_record->ID;
							break;
						}
					}
				}
				
				// 
				switch ( $applicable_policy ) {
					case 'always':
						if ( $matching_value_record ) {
							$action = 'none';  // if no matching value record and always, will 'save'
						}
						break;
					case 'update': 
						if ( $matching_type_record ) {
							$action = 'update'; // if no matching type record and update, will 'save'
						}
						break;			
					case 'update_test_match': 
						if ( $matching_type_record ) {  // if no matching type record and update_test_match, will 'save'
							$action = 'none'; // if update_test_match, do nothing, unless fail soft match
							if ( isset (  $this->data_object_array[$row][$row_equality_test] ) ) {
								if ( substr ( $matching_type_value, 0, 5) != 
									 substr( $this->data_object_array[$row][$row_equality_test]->get_value(), 0, 5 ) 
									 ) { 
									$action = 'update';
								}							
							}
						}
						break;
				}
			}
		
			// have set $action to add, update or none based on policy and incoming value;
			if ( 'none' == $action ) {
				continue;
			} else {
				// construct $save_update_array 
				$save_update_array = array(
					array ( 
						'key' => 'constituent_id',
						'value' => $constituent_id,
						'wp_query_parameter' => '',						
					)
				);			
				// if doing update, add ID
				if ( 'update' == $action ) {
					$save_update_array [] = array ( 
						'key' => 'ID',
						'value' => $matching_type_record,
						'wp_query_parameter' => '',						
					);
				}							
				// add terms from incoming array
				$some_values_non_blank = false;
				foreach ( $this->data_object_array[$row] as $control ) {
					$save_update_array[] = $control->create_update_clause();
					if ( $control->get_value() > '' && substr( $control->get_field_slug(), -5 ) != '_type' ) {
						$some_values_non_blank = true;
					}
				}
				// add terms which are not in the dictionary and not "uploadable" for activity (if supplied)
				if ( 'activity' == $row && ! empty ( $special_activity_key_value_array ) ) {
					// add in special activity fields -- e.g., for email activity
					foreach ( $special_activity_key_value_array as $key => $value ) {
						$save_update_array[] = array (
							'key' => $key,
							'value' => $unsanitized ? sanitize_text_field ( $value ) : $value,
							'wp_query_parameter' => '',				
						);
						if ( $value > '' ) {
							$some_values_non_blank = true;
						}
					}
					// if the incoming is an email transaction, set flag for origination of constituent
					if ( isset ( $special_activity_key_value_array['related_inbox_image_record'] ) || isset( $special_activity_key_value_array['related_outbox_record'] ) ) {
						$save_update_array[] = array(
								'key' => 'email_batch_originated_constituent',
								'value' => $added_new_constituent ? 1 : 0,
								'wp_query_parameter' => '',
							);
					}	
				}
				// if all that is in the save array is ID and constituent ID don't bother
				// if interface doesn't check non-blank, may save blank values.
				if ( $some_values_non_blank ) {
					$query_object = 'wic_' . $row . '_query';
					$method = 'direct_' . $action;
					$this->$query_object->$method ( $save_update_array );
				}
			}
		}
		// made it to here, all is good, returning with constituent_id
		return array ( 'response_code' => true, 'output' => 
			(object) array ( 
				'constituent_id' => $constituent_id,
				'constituent_names' => WIC_DB_Access_WIC::get_constituent_names( $constituent_id ),
				'activity_id'	 => $this->wic_activity_query->insert_id,	
			 )
		);
	
	}

}
