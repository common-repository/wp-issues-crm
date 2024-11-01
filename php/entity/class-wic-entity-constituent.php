<?php
/*
*
*	class-wic-entity-constituent.php
*
*
*/

class WIC_Entity_Constituent extends WIC_Entity_Parent {
	
	/*
	*
	* Request handlers
	*
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'constituent';
		$this->show_dup_list = true;
	} 
	
	// set values from update process to be visible on form after save or update
	protected function special_entity_value_hook ( &$wic_access_object ) { 
			$time_stamp = $wic_access_object->db_get_time_stamp( $this->data_object_array['ID']->get_value() );
			$this->data_object_array['last_updated_time']->set_value( $time_stamp->last_updated_time );
			$this->data_object_array['last_updated_by']->set_value( $time_stamp->last_updated_by );
	}

	/***************************************************************************
	*
	* Constituent -- special formatters and validators
	*
	****************************************************************************/ 	


	// note: since phone is multivalue, and formatter is not invoked in the 
	// WIC_Control_Multivalue class (rather at the child entity level), 
	// this function is only invoked in the list context
	public static function phone_formatter ( $phone_list ) {
		$phone_array = explode ( ',', $phone_list );
		$formatted_phone_array = array();
		foreach ( $phone_array as $phone ) {
			$formatted_phone_array[] = WIC_Entity_Phone::phone_number_formatter ( $phone );		
		}
		return ( implode ( '<br />', $formatted_phone_array ) );
	}
	
	public static function email_formatter ( $email_list ) {
		$email_array = explode ( ',', $email_list );
		$clean_email_array = array();
		foreach ( $email_array as $email ) {
			$clean_email_array[] = esc_html ( $email );		
		}
		return ( implode ( '<br />', $clean_email_array ) );
	}		

	public static function hard_delete ( $id, $second_constituent ) { 
		// test if have activities
		global $wpdb;

		if ( $id == $second_constituent ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'reason' => 'Looks like you selected the same constituent as a dup when you attempted to delete/dedup.  Try again.' ) );   
		} elseif ( WIC_Entity_Activity::has_frozen_activities( $id ) && ! $second_constituent ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'reason' => 'This constituent has activities before the freeze date.   
				Alter the freeze date in Configure to allow deletion of this constituent.') );
		} else {
			foreach ( array ( 'activity', 'address', 'constituent', 'email', 'phone' ) as $table_name ) {
				$var_name =  $table_name . '_table';
				${$var_name} = $wpdb->prefix . 'wic_' . $table_name;
			}
			if ( $second_constituent ) {
				// prepare to do deduping
				global $wic_db_dictionary;
				$current_user = wp_get_current_user();

				// get data for comparison
				$delete_constituent = $wpdb->get_results ( "SELECT * from $constituent_table WHERE ID = $id" );
				$move_to_constituent = $wpdb->get_results ( "SELECT * from $constituent_table WHERE ID = $second_constituent" );
				$fields = $wic_db_dictionary->list_non_multivalue_fields ( 'constituent' );
				
				// create set clause for update
				$set_clause = $wpdb->prepare ( 'SET last_updated_time = %s,  last_updated_by = %d', array ( current_time( 'mysql' ), $current_user->ID ) ) ;
				foreach ( $fields as $field ) {
					if ( '' < $delete_constituent[0]->$field && '' == $move_to_constituent[0]->$field ) {
						$set_clause .= $wpdb->prepare ( ", $field = %s ", array ( $delete_constituent[0]->$field ) );					
					}
				}
				$update_sql = "UPDATE $constituent_table $set_clause WHERE ID = $second_constituent";

				// update second constituent with deleted data
				$result = $wpdb->query ( $update_sql );
				if ( ! $result ) { // always updating, since updating last date and time
					return array ( 'response_code' => false, 'output' => 'Database error on attempted dedup/delete.' );   
				}
				
				// transfer subsidiary records over -- not checking return codes -- assuming if up on last step, still up
				foreach ( array ( 'activity', 'address', 'email', 'phone' ) as $table_name ) {
					$table = $wpdb->prefix . 'wic_' . $table_name;
					$sql = "UPDATE $table SET constituent_id = $second_constituent WHERE constituent_id = $id";
					$wpdb->query ( $sql );
				}			
			
				// delete primary constituent
				$result = $wpdb->query ( " DELETE from $constituent_table WHERE ID = $id " );
				$deleted = false !== $result;
				return array ( 'response_code' => true, 'output' => (object) array ( 
					'deleted' 			 => $deleted, 
					'second_constituent' => $second_constituent,
					'reason' 			 => $deleted ? 'This constituent has been deleted and data transferred -- will redirect to the other constituent momentarily.' : 'Database error on attempted delete.' 
					) 
				);
				
			} else {
				$sql = " DELETE ac, ad, c, e, p from $constituent_table c
					LEFT JOIN $activity_table ac on ac.constituent_id = c.ID
					LEFT JOIN $address_table ad on ad.constituent_id = c.ID
					LEFT JOIN $email_table e on e.constituent_id = c.ID
					LEFT JOIN $phone_table p on p.constituent_id = c.ID
					WHERE c.ID = $id ";
				$result = $wpdb->query( $sql );
				$deleted = false !== $result;
			}
		}
		return array ( 'response_code' => true, 'output' => (object) array ( 
			'deleted' => $deleted, 
			'second_constituent'=>false, 
			'reason' => $deleted ? 'This constituent has been deleted.' : 'Database error on attempted delete.' 
			) 
		);
	}
	
	/* utility to check if assigned and open with a staff member */
	public static function get_assigned_staff( $constituent_id ) {
		// return assigned staff if open and assigned; return 0 if not found or not open or not assigned;
		global $wpdb;
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$results = $wpdb->get_results ( "SELECT case_assigned, case_status FROM $constituent_table WHERE ID = $constituent_id" );
		if ( ! $results ) {
			return '';
		} else { 
			if ( 0 < $results[0]->case_assigned && 0 < $results[0]->case_status ) {
				return $results[0]->case_assigned;
			} else {
				return 0;
			}
		}
	}

	/* utility to check is_my_constituent flag */
	public static function is_my_constituent ( $constituent_id ) {
		// return is_my_constituent value or '' if not found
		global $wpdb;
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$results = $wpdb->get_results ( "SELECT is_my_constituent FROM $constituent_table WHERE ID = $constituent_id" );
		if ( ! $results ) {
			return '';
		} else { 
			return $results[0]->is_my_constituent;
		}
	}

	public static function list_delete_constituents( $dummy, $search_id ) {

		// set up global and table names for database access	
		global $wpdb;
		foreach ( array ( 'activity', 'address', 'constituent', 'email', 'phone' ) as $table_name ) {
			$var_name =  $table_name . '_table';
			${$var_name} = $wpdb->prefix . 'wic_' . $table_name;
		}
		$temp_constituent_table = WIC_List_Constituent_Export::temporary_id_list_table_name();
		$freeze_date = WIC_Entity_Activity::get_freeze_date(); // should not alter transactions before freeze date

		// create the temporary table
		WIC_List_Constituent_Export::create_temporary_id_table ( 'constituent_delete', $search_id );		

		// check that some constituents still exist
		if ( !$wpdb->get_results( "SELECT * from $temp_constituent_table LIMIT 0,1") ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'message' => "Database error or constituents changed or deleted by another user.") );
		}
		
		// now check for frozen activities
		$sql = "SELECT min(activity_date) as earliest_activity FROM  $temp_constituent_table t inner join $activity_table a on t.id = a.constituent_id ";
		if ( $result = $wpdb->get_results ( $sql ) ) {
			$earliest_activity = $result[0]->earliest_activity;
			// check for earliest activity -- note that no result is not value necessarily an error
			if ( $earliest_activity < $freeze_date ) { 
				return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'message' => "Could not delete constituents because some had activities with dates before the activity freeze date, $freeze_date.") );
			}
		}
		
		// do the deletes in one transaction
		$sql = " DELETE ac, ad, c, e, p from $temp_constituent_table t INNER JOIN $constituent_table c on c.ID = t.ID
			LEFT JOIN $activity_table ac on ac.constituent_id = c.ID
			LEFT JOIN $address_table ad on ad.constituent_id = c.ID
			LEFT JOIN $email_table e on e.constituent_id = c.ID
			LEFT JOIN $phone_table p on p.constituent_id = c.ID
			";
		
		// note that result will be the number of records deleted including subsidiary records -- cannot use it to report the # of constituents deleted
		if ( $result = $wpdb->query( $sql ) ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => true, 'message' => "Deleted selected constituents and any associated activity records.") );
		} else {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'message' => "Database error or constituents changed or deleted by another user.") );
		}
	}

}

