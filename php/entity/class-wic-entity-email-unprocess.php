<?php
/*
*
*	wic-entity-email-unprocess.php
*
*/
class WIC_Entity_Email_Unprocess  {

	public static function handle_undo ( $dummy_id, $data ) {
		// do not check for folder change -- want to undo what online last intended to do
		$folder = $data->fullFolderString;
		// set up uid vars
		$uid_string_term = ' IN(' . implode( ',', $data->uids ) . ') ';
		$uid_count = count( $data->uids );
		/*
		*
		* step one: attempt to reverse the deletes (testing in case have already been processed to the server)
		*
		*/
		global $wpdb;
		$inbox = $wpdb->prefix . 'wic_inbox_image';
		$sql = $wpdb->prepare(
			"
			UPDATE $inbox 
			SET to_be_moved_on_server = 0
			WHERE 
				full_folder_string = %s AND 
				no_longer_in_server_folder = 0 AND
				folder_uid $uid_string_term
			",
			array ( $folder ) 
			);		
		$undeleted_count = $wpdb->query ( $sql );
		// if too late, reset and stop here
		if ( $undeleted_count != $uid_count ) {
			self::reverse_undeletes ( $folder, $uid_string_term );
			return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => false, 'message' => 'Message moves already synchronized to inbox.' ) );
		}
		/*
		*
		* step oneA: reverse the filter creation executed by a block command
		*
		*/
		if ( ! empty( $data->block ) ) {
			// supporting bulk block/delete
			foreach ( $data->uids as $uid ) {
				WIC_Entity_Email_Block::unset_address_filter ( $folder, $uid, $data->wholeDomain );
			}
		} 
		// can now return for delete/block cases
		if ( ! empty( $data->deleter ) ) {
			return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => true, 'message' => '' ) );
		}
		/*
		* steps two and three apply only to reply actions as opposed to record action
		*/
		$activity_table = $wpdb->prefix . 'wic_activity'; // need for step five as well as two and three
		if ( $data->reply ) { // reply is always defined, although possibly false, if not blocking or deleting
		/*
		*
		* step two: attempt to recall the outgoing messages
		*
		* sql selects the activity records ( that are linked to the inbox_image_records that meet the folder_uid/folder criterion AND ) have not been sent
		*/
			$activity_table = $wpdb->prefix . 'wic_activity';
			$outbox_table = $wpdb->prefix . "wic_outbox";
			
			$sql = $wpdb->prepare(
				"
				UPDATE $outbox_table o INNER JOIN $inbox i on o.is_reply_to = i.ID
				SET held = 1
				WHERE 
					i.folder_uid $uid_string_term
					AND i.full_folder_string = %s
					AND o.sent_ok = 0
				",
				array ( $folder ) 
				);
			$unqueued_count = $wpdb->query ( $sql );
			// if too late, reset and stop here
			if ( $unqueued_count != $uid_count ) {
				self::reverse_unqueues( $folder, $uid_string_term );
				self::reverse_undeletes ( $folder, $uid_string_term );
				return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => false, 'message' => 'Messages already transmitted.' ) );
			}
		/*
		*
		* step three: with messages successfuly put on hold, proceed to physically delete them (and their related outgoing activity records)
		*
		*/
			$sql = $wpdb->prepare(
				"
				DELETE a, o FROM $activity_table a INNER JOIN 
				$outbox_table o on o.ID = a.related_outbox_record INNER JOIN 
				$inbox i on o.is_reply_to = i.ID
				WHERE 
					i.folder_uid $uid_string_term
					AND i.full_folder_string = %s
				",
				array ( $folder ) 
				);
			$deleted_message_count = $wpdb->query ( $sql );
			if ( $deleted_message_count != 2 * $uid_count ) {
				self::reverse_undeletes ( $folder, $uid_string_term );
				return array ( 'response_code' => false, 'output' =>  'Database error on deletion of outgoing messages.  Check outgoing queue and issue activity records.' );
			}
		} // close if $data->reply
		/*
		*
		* step four: remove any created constituent records and associated records if added by transaction
		*
		* not checking return codes in this step and beyond -- at this point, we are committed  
		* and, if fail, the only consequence is garbage records, not bad sent email
		*/
		$wic_prefix = $wpdb->prefix . 'wic_';
		$entity_array = array ( 'constituent', 'phone', 'email', 'address' );
		foreach ( $entity_array as $entity ) {
			$id = ( 'constituent' == $entity ) ? 'ID' : 'constituent_id';	
			$table = $wic_prefix . $entity;		
			$sql = $wpdb->prepare (
				"DELETE d FROM $table d 
					INNER JOIN $activity_table a ON a.constituent_id = d.$id 
					INNER JOIN $inbox i on i.ID = a.related_inbox_image_record
					WHERE 
						i.full_folder_string = %s AND
						i.folder_uid $uid_string_term AND
						a.email_batch_originated_constituent = 1
				",
				array( $folder )
				);
			$wpdb->query ( $sql );
		}
		/*
		*
		* step five: physically delete the incoming email log records
		*
		*/		
		$sql = $wpdb->prepare(
			"
			DELETE a FROM $activity_table a
			INNER JOIN $inbox i on i.ID = a.related_inbox_image_record
			WHERE 
				i.folder_uid $uid_string_term AND
				i.full_folder_string = %s
			",
			array ( $folder ) 
			);
		$wpdb->query ( $sql );
		/*
		*
		* step six: undo training
		*
		* undo only the subject mapping -- this is enough to force ungroup and user can retrain
		*
		*/
		if ( $data->train ) {
			WIC_Entity_Email_Subject::unmap_subject ( $data->subject );
		}

		return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => true, 'message' => '' ) );

	}

	private static function reverse_undeletes( $folder, $uid_string_term ) {
		global $wpdb;
		$inbox = $wpdb->prefix . 'wic_inbox_image';	
		$sql = $wpdb->prepare(
			"
			UPDATE $inbox 
			SET to_be_moved_on_server = 1
			WHERE 
				full_folder_string = %s AND 
				folder_uid $uid_string_term
			",
			array ( $folder ) 
			);		
		$redeleted_count = $wpdb->query ( $sql );
	}
	
	private static function reverse_unqueues( $folder, $uid_string_term ) {
		global $wpdb;
		$outbox_table = $wpdb->prefix . "wic_outbox";
		$inbox = $wpdb->prefix . 'wic_inbox_image';
		$sql = $wpdb->prepare(
			"
			UPDATE $outbox_table o inner join $inbox i on o.is_reply_to = i.id
			SET o.held = 0
			WHERE 
				i.folder_uid $uid_string_term
				AND i.full_folder_string = %s
				AND o.held = 1
			",	
			array ( $folder ) 
			);	
		$requeued_count = $wpdb->query ( $sql );
	}

}