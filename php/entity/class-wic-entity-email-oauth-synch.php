<?php
/*
*
*	wic-entity-email-oauth.php
*
*	
*/
// this is the location of the copy of the google client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor'  . DIRECTORY_SEPARATOR . 'autoload.php';

class WIC_Entity_Email_OAUTH_Synch {

	/*
	* Note that the synchronization here:
	*	1 -- takes a full snapshot of the gmail inbox and adds any missing records to the DB
	*	2 -- mark deletes an records on the DB that are no longer labelled inbox
	*	3 -- removes the inbox label for records that have been marked to be moved on the server
	*	4 -- as part of 1, updates records that are present on both copies, but marked as no_longer_in_folder on the DB side 
	*		 -- but not if they are marked to be moved.  This allows user to recover from inadvertent gmail archival of messages that have not been responded to.
	*		It does not move records that are marked to be moved, indicating that they have already been processed on the db side.
	*		-- the value of to_be_moved_on_server remains 1 after records are moved in all three synch routines (IMAP, Gmail and Gmail Update) 
	*
	*/

	// take place of IMAP synch function when using gmail
	public static function synch_inbox ( $online = false ) {
	
		// do not run this off line from the cron synch task -- full synch will be triggered if necessary by the partial synch which is part of the parse routine normally
		if ( !$online ) {
			return array ( 'response_code' => false , 'output' => 'WIC_Entity_Email_OAUTH_Synch::synch_inbox called from cron task; exited, deferring to partial synch.');
		}
		
		// do not set time limit -- instead write log records to show where failure occured in what should be a rare case
  
  		// time_stamp for start processing -- use for reporting only
		$check_connection_time = microtime (true);
    
  		// check user auth -- note that this will get a refreshed access token if necessary and possible
  		$check_return = WIC_Entity_Email_OAUTH::check_user_auth();
		if ( !$check_return['response_code'] ) {
			$error = 'Failed to connect to Gmail in WIC_Entity_Email_OAUTH_Synch::synch_inbox -- check_connect said: ' . $check_return['output'];
			return array ( 'response_code' => false , 'output' => $error );
		} 

  		// get access token 
  		$access_token = $check_return['output'];
		// try to set it up in client -- should be perfect;
		try  { 
			$client = WIC_Entity_Email_OAUTH::get_client();
			$client->setAccessToken( $access_token );
		} catch ( Exception $e ) {
			return array ( 'response_code' => false , 'output' => 'Gmail Server said on OAUTH_Synch: ' . $e->getMessage );
		}
		
		$folder = WIC_Entity_Email_Account::get_folder();
		$service = new Google_Service_Gmail( $client );

		// time stamp for connection setup completed -- reporting purposes only -- show connection speed and collect results of this run in inbox
		$synch_fetch_time = microtime(true);
		$synch_fetch_time_stamp = floor( $synch_fetch_time );
		// inbox message count at start of run
		$inbox_label = $service->users_labels->get ( 'me', 'INBOX' );
		$num_msgs = $inbox_label->messagesTotal;
		// time stamp the run start 		
		self::save_stub_log_entry( $folder, $synch_fetch_time_stamp, $num_msgs );
	
		// set up temporary table to capture download of message_ids
		$temporary_table = '';
		$result = self::create_temporary_staging_table ( $synch_fetch_time_stamp );
		if ( !$result['response_code'] ) {
			return $result;
		} else {
			$temporary_table = $result['output'];
		}
		/*
		*
		* get the latest history id *before* the pull and save it as a starting point for partial synch
		*
		* history events could occur after this call or during the pull or the parsing of the pull; this synch point catches all that 
		*
		* partial synch is rerunnable, so no problem if repeats previously done updates
		*
		*/
		$profile = $service->users->getProfile( 'me' );
		$history_id = $profile->getHistoryId();
		update_option ( 'wp-issues-crm-gmail-api-start-sync-for-' . $folder, $history_id ); 
		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_OAUTH_Synch::synch_inbox -- starting full synch for $folder from $history_id. ");

		// actually go out to google and get the records
		$result = self::populate_temporary_staging_table ( $folder, $synch_fetch_time_stamp, $service, $temporary_table );
		if ( !$result['response_code'] ) {
			return $result;
		} 	
		
		// synch against the temp table -- add new records ( all and only those labelled INBOX and not present already on image)
		$save_result = self::save_stub_records ( $temporary_table, $folder, $synch_fetch_time_stamp );
		if ( !$save_result['response_code'] ) {
			return $save_result;
		} else { 
			self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_new', $save_result['output'] );
		}	

		// for legacy compatibility -- stub records are saved without UID used as unique ID from IMAP functions
		self::patch_folder_uid( $folder );
		
		// synch against the temp table -- mark delete obsolete records
		$do_deletes_time = microtime(true); // just for reporting
		$delete_result = self::mark_delete_obsolete_records ( $temporary_table, $folder ); 
		if ( !$delete_result['response_code'] ) {
			return $delete_result;
		} else {
			self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_image_mark_deleted', $delete_result['output'] );
		}
		
		// synch against the temp table -- move messages out of gmail inbox that have been processed on WP Issues CRM
		$process_moves_time = microtime(true);
		$move_result = self::move_processed_records  ( $service, $temporary_table, $folder ); 
		if ( !$move_result['response_code'] ) {
			return $move_result;
		} else {
			self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_deleted', $move_result['output'] );
		}		
		// wrap up diagnostics
		$work_done_time = microtime(true);
		self::update_final_diagnostics( 
			$folder, 
			$synch_fetch_time_stamp,  // as record key
			$synch_fetch_time - $check_connection_time, // establish connection
			$do_deletes_time - $synch_fetch_time, // get temp records and do inserts of new
			$process_moves_time - $do_deletes_time, // time to delete obsolete records
			$work_done_time - $process_moves_time	// time to relabel moved records 		
			 );
		
		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_OAUTH_Synch::synch_inbox -- ending full synch for $folder from $history_id. ");

		return 	array ( 'response_code' => true , 'output' => 'Successful Gmail Synch Run' );
	 
	}

	/*
	* set up staging table as basis for synch 
	* 512 is recommended length for EWS ItemId (although only really only 150 -- does this include other field I am not using?)
	* have to use case sensitive comparisons among Ids so would like to use binary ascii fields here
	* BUT need to keep using utf-8 throughout inbox table or trigger silent wpdb failures -- thinks it sees illegal mix of collations even when the fields are compared are same collation
	*    AND UTF-8 takes three bytes so can't index 512 within the 1024 index byte limit
	* Solution is UTF-8 512, non-unique index here and make sure to specify binary in every comparison
	*
	*/
	protected static function create_temporary_staging_table ( $synch_fetch_time_stamp ) {
		global $wpdb;
		$temporary_table = $wpdb->prefix . 'wic_sync_staging_table_' .  $synch_fetch_time_stamp ;
		$result = $wpdb->query( "
			CREATE TEMPORARY TABLE $temporary_table (
			message_id varchar(512) NOT NULL, 
			thread_id varchar(512) NOT NULL, 
			KEY  (message_id(300))
			) DEFAULT CHARSET=utf8mb4;
			");
		if ( 1 != $result ) {
			return 	array ( 'response_code' => false , 'output' => $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true , 'output' => $temporary_table );
		}

	}


	// get list of all the messages in the inbox and save to temp table
	private static function populate_temporary_staging_table ( $folder, $synch_fetch_time_stamp, $service, $temporary_table ) {
		// set up message retrieval loop
		$pageToken = NULL;
  		$messages = array();
  		$opt_param = array( 'labelIds'=> array( 'INBOX' ) );
  		$count_retrieved =  0;
  		$count_saved = 0;
  		
  		// start loop -- grab 100 at a time and save all messages to a temporary staging table
		do {
			// access page list from google -- 100 messages. $message object has all fields, but only id and thread_id are populated
			try {
			  	if ($pageToken) {
					$opt_param['pageToken'] = $pageToken;
			 	}
			  	$messagesResponse = $service->users_messages->listUsersMessages( 'me', $opt_param);
			  	if ($messagesResponse->getMessages()) {
					$messages = $messagesResponse->getMessages();
					$pageToken = $messagesResponse->getNextPageToken();
			  	}
			} catch (Exception $e) {
				return array ( 'response_code' => false , 'output' => 'In response to listUsersMessages, GMail said: Error ' .  $e->getCode() . ', ' .  $e->getMessage()  );
			}
			// save the block of messages into the temporary table
			$save_result = self::save_temp_message_id_records ( $messages, $temporary_table );
			if ( !$save_result['response_code'] ) {
				return $save_result;
			} else {
				$count_saved = $count_saved + $save_result['output'];
				self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_on_temp_table', $count_saved );
			}
		} while ( $pageToken );
	
		return array ( 'response_code' => true , 'output' => ''  );
	
	}
	
	// batch inserts of temp records into staging table
	private static function save_temp_message_id_records ( $messages, $temporary_table ) {
		// $messages may be a zero length array if no records in the inbox
		if ( ! $messages ) {
			return 	array ( 'response_code' => true , 'output' => 0 );
		}
		// if some messages, then proceed
		global $wpdb;
		$sql = "INSERT INTO $temporary_table ( message_id, thread_id ) VALUES ";
		$count_saved = 0;
		foreach ( $messages as $message ) {
			$sql .= (  "( '{$message->getID()}', '{$message->getThreadId()}' ), ");
			$count_saved++;
		}
		$sql = trim ( $sql, ', ');
		$result = $wpdb->query ( $sql );
		if ( ! $result ) {
			return 	array ( 'response_code' => false , 'output' => $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true , 'output' => $count_saved );
		}
	}
	
	
	/* 
	* save new inbox image records in single transaction against temporary table	
	* 
	*
	*/
	protected static function save_stub_records ( $temporary_table, $folder, $synch_fetch_time_stamp ) {
		
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		// lock the inbox table to prevent remote possibility of conflicting synch add transation
		$locked = $wpdb->query (
			"
			LOCK TABLES $inbox_table WRITE, $inbox_table as i2 READ, $temporary_table READ
			"
		);
		if ( false === $locked ) { 
			return array ( 'response_code' => false, 'output' => 'Could not lock inbox table for synchronization: ' . $wpdb->last_error );
		}

		// do the inserts
		$insert_sql =  
			"
			INSERT INTO $inbox_table ( full_folder_string, utc_time_stamp, extended_message_id, account_thread_id ) 
			SELECT '$folder', %f, message_id, thread_id 
			FROM $temporary_table LEFT JOIN  $inbox_table as i2 
			ON BINARY $temporary_table.message_id = i2.extended_message_id 
			WHERE extended_message_id IS NULL
			"
			;
		
		$result1 = $wpdb->query ( $wpdb->prepare ( $insert_sql, array ( $synch_fetch_time_stamp ) ) );
		$err1 =  false === $result1 ? $wpdb->last_error : '';

		/*
		* create possibility of recovering from inadvertent archival on gmail side, but do not back track on deliberately moved records 
		*
		* this is always an empty set in active sync because a move back and forth changes ItemId
		*/
		$update_sql = 
			"
			UPDATE $inbox_table INNER JOIN $temporary_table ON BINARY $temporary_table.message_id = extended_message_id 
			SET no_longer_in_server_folder = 0
			WHERE full_folder_string = '$folder' AND no_longer_in_server_folder = 1 AND to_be_moved_on_server = 0
			";
		$result2 = $wpdb->query ( $update_sql );
		$err2 =  false === $result2 ? $wpdb->last_error : '';

		// release the lock
		$wpdb->query (
			"
			UNLOCK TABLES
			"
		);		
		// report results
		if ( false === $result1 || false === $result2 ) {
			return array ( 'response_code' => false, 'output' => 'Database error: ' . $err1 . '|' . $err2 );
		} else {
			return 	array ( 'response_code' => true, 'output' => $result1 );
		}
	}

	// mark delete obsolete records on the inbox table ( those not present at gmail/activesync )
	public static function mark_delete_obsolete_records ( $temporary_table, $folder ) {

		/*
		* no locking necessary here -- this can be repeated or run simultaneously
		* there is no reason or mechanism to undelete records once found obsolete (see note on add record)
		* so, if it was obsolete on any run of this process, it is permanently obsolete
		*/
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		$update_sql =  "
			UPDATE $inbox_table LEFT JOIN $temporary_table  
			ON BINARY $temporary_table.message_id = $inbox_table.extended_message_id 
			SET no_longer_in_server_folder = 1
			WHERE $temporary_table.message_id IS NULL
			AND no_longer_in_server_folder = 0
			AND full_folder_string = '$folder'
			"
			;
		$result = $wpdb->query ( $update_sql );
		if ( false === $result ) {
			return array ( 'response_code' => false, 'output' => 'Database error: ' . $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true, 'output' => $result );
		}
	}

	// paginate the relabeling of records that have been handled already by the API.
	public static function move_processed_records  ( $service, $temporary_table, $folder ) {
		/*
		* no locking necessary here -- this can be repeated or run simultaneously
		* 
		* this process can prevent WP Issues CRM undo of message if user has bad timing
		* but consider this acceptable -- no reversal after the undo button expires and bad timing just accelerates expiration
		*
		* the batch API does not care if requests are repeated -- does not generate error, so if one process runs over another, no harm
		*
		* if the API call fails, will not do the final update, so can rerun/interrupt; 
		* if API call successful, but final update is not done, then will reattempt on next run, but API does not care, will not fail 
		*/		
		// get label id for proceessed label
		$label_id = self::check_create_processed_label ( $service );
		if ( ! $label_id ) {
			return array ( 'response_code' => false, 'output' => 'Gmail did not allow creation of processed label.' );
		}
		
		// $temp_table phrase can be blank and routine can be used to just run the moves in the folder
		$temp_table_phrase = $temporary_table ? "INNER JOIN $temporary_table t ON BINARY t.message_id = i.extended_message_id" : '';
		
		// get messages that need to be relabeled
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		// to_be_moved_on_server = 1 -- delete, record or reply action already taken on WP Issues CRM
		// no_longer_in_server_folder = 0 -- message has not been marked processed on GMail (to the knowledge of the server)
		// limited to 1000 calls per batch request -- https://developers.google.com/discovery/v1/batch
		$sql_while = "
			SELECT extended_message_id FROM 
			$inbox_table i $temp_table_phrase  
			WHERE to_be_moved_on_server = 1 
			AND no_longer_in_server_folder = 0
			AND full_folder_string = '$folder'
			LIMIT 0, 990
			"
			;		

		$count_moved = 0;
		while ( $results = $wpdb->get_results ( $sql_while ) ) {
			// build array of ids to be submitted in request
			$ids = array ();
			foreach ( $results as $result ) {
				$ids[] = $result->extended_message_id;
			}
			// prepare batch request 
			$request = new Google_Service_Gmail_BatchModifyMessagesRequest();
			$request->setAddLabelIds( array( $label_id ) );
			$request->setRemoveLabelIds( array ( 'INBOX' ) );
			$request->setIds ( $ids ); 
			// batchModify will not fail if some or all ID's not found or badly formatted or label already applied, but label ID's must be valid
		 	try {
				$messages = $service->users_messages->batchModify( 'me', $request );
			} catch (Exception $e) {
				return false;
		  	}
			// request was successful -- update messages as out of inbox
			$id_string = " IN('" . implode( "','", $ids ) . "') ";
			// leave the to_be_moved flat set
			$sql = "
				UPDATE $inbox_table 
				SET no_longer_in_server_folder = 1
				WHERE BINARY extended_message_id $id_string 
				AND full_folder_string = '$folder'
				";
			$result = $wpdb->query ( $sql );
			if ( false === $result ) {
				return array ( 'response_code' => false, 'output' => 'Database error: ' . $wpdb->last_error );
			} else {
				$count_moved = $count_moved + $result;
			}
		}
		return array ( 'response_code' => true, 'output' => $count_moved );
	}

	// returns label ID for processed folder or false 
	private static function check_create_processed_label ( $service ) {
		$processed_label = WIC_Entity_Email_Connect::PROCESSED_FOLDER;
		// get the labels for the user and scan for processed folder 
		$results = $service->users_labels->listUsersLabels('me');
		foreach ( $results->getLabels() as $label ) {
  			if ( $processed_label == $label->getName() ) {
  				return $label->getId();
  			}
		}
		// label does not exist, create it
		$label = new Google_Service_Gmail_Label();
 		$label->setName( $processed_label) ;
		try {
			$label = $service->users_labels->create( 'me', $label);
			return $label->getId();
		} catch (Exception $e) {
			return false;
		}
	}


	// save stub record at start of run	
	public static function save_stub_log_entry ( $folder, $synch_fetch_time_stamp, $num_msgs ) {
		global $wpdb;
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$sql = $wpdb->prepare(
			"INSERT into $synch_log_table ( full_folder_string, utc_time_stamp, num_msg ) VALUES ( %s, %f, %d )", 
			array(	$folder, $synch_fetch_time_stamp, $num_msgs )
		);
		$result = $wpdb->query( $sql );
		if ( 1 != $result ) {
			return 	array ( 'response_code' => false , 'output' => $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true , 'output' => '' );
		}
	}
	
	
	public static function update_stub_log_entry ( $folder, $synch_fetch_time_stamp, $field, $new_value ) { // $new_value is integer
		global $wpdb;
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$sql = $wpdb->prepare(
			"UPDATE $synch_log_table SET $field = $new_value WHERE full_folder_string = %s AND utc_time_stamp = %f ", 
			array(	$folder, $synch_fetch_time_stamp )
		);
		$result = $wpdb->query( $sql );
		if ( 1 != $result ) {
			return 	array ( 'response_code' => false , 'output' => $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true , 'output' => '' );
		}
	}

	// diagnostic record was saved intra-run -- so this just updates it
	public static function update_final_diagnostics( $folder, $synch_fetch_time_stamp, $make_connection_time, $do_adds_time, $do_deletes_time, $do_moves_time ) {

		global $wpdb;
		$result = WIC_Entity_Email_Inbox_Synch::inbox_synch_status ( $folder, $synch_fetch_time_stamp );
	
		// always log result
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$sql = $wpdb->prepare(
			"UPDATE $synch_log_table
			SET 
				stamped_synch_count = %d,
				synch_count = %d,
				incomplete_record_count = %d,
				pending_move_delete_count = %d,
				stamped_incomplete_record_count = %d,
				added_with_this_timestamp = %d,
				check_connection_time = %f,
				synch_fetch_time = %f,
				do_deletes_time = %f,
				process_moves_time = %f
			WHERE full_folder_string = %s AND utc_time_stamp = %f
			",
			array(
				// data from database
				$result->stamped_synch_count, 
				$result->synch_count, // added by this run but not deleted on db (yet)
				$result->incomplete_record_count, // all not deleted
				$result->pending_move_delete_count, // but for time diff should be same as count to_be_deleted
				$result->stamped_incomplete_record_count, // incomplete with this time stamp
				$result->added_with_this_timestamp, // should always equal count added	
				// data from processing timestamps in this job				
				$make_connection_time,
				$do_adds_time, 
				$do_deletes_time, 
				$do_moves_time, 
				// identifier
				$folder, 
				$synch_fetch_time_stamp,
				)
			)
		;

		$wpdb->query( $sql );	
	
	}

	// legacy imap compatibility
	public static function patch_folder_uid( $folder ) {
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		$sql = $wpdb->query (
			"
			UPDATE $inbox_table SET folder_uid = ID WHERE full_folder_string = '$folder' AND folder_uid = 0 ;
			"
		);
	}

} // close class