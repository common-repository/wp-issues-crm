<?php
/*
*
*	wic-entity-email-oauth-update.php
*
*	(1) gets message details for message IDs via Gmail API
*   (2) parses and stores messages in standard inbox object
*	(3) does a history based update to capture new record stubs via the API for the next cycle
*
*	Gets messages for steps 1 and 2 in groups of 10
*	Timed to run every two minutes; stops work before running out of time
*/
Class WIC_Entity_Email_OAUTH_Update {


	public static function update_inbox() { 
		
		WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_OAUTH_Update:update_inbox -- starting.' );

		// set time limit for job
		// two minutes less some Wordpress set up time to get here and some time to complete parse 
		$max_allowed_time = 100; 
		/* 
		* not actually setting max_execution time at any level -- want as much time as possible in case trigger new synch.  
		* but don't want two of these jobs running at once -- they will compete for the same records are double work them ( no harm, but wasteful )
  		* stopping only the parsing -- job may continue to partial or full synch without time limit
  		*
  		* sequencing of synch after parse assures that synch will see
  		*/
  		$cut_off_time = time() + $max_allowed_time;


		/*
		* prepare for API Access -- will be doing access regardless of whether any unparsed to chdck for updates
		*
		* 
		*
		*/
		$check_return = WIC_Entity_Email_OAUTH::check_user_auth();
		if ( !$check_return['response_code'] ) {
			WIC_Entity_Email_Cron::log_mail ( 'Failed to connect to Gmail in WIC_Entity_Email_OAUTH_Update::update_inbox -- check_connect said: ' . $check_return['output'] );
			return true;
		} 
		// get access token 
		$access_token = $check_return['output'];
		// try to set it up in client -- should be perfect;
		try  { 
			$client = WIC_Entity_Email_OAUTH::get_client();
			$client->setAccessToken( $access_token );
		} catch ( Exception $e ) {
			WIC_Entity_Email_Cron::log_mail ( 'On setAccessTokenGmail Client said in WIC_Entity_Email_OAUTH_Update::update_inbox : ' . $e->getMessage );
			return true;
		}
		$service = new Google_Service_Gmail( $client );

		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$max_packet_size = WIC_Entity_Email_Inbox_Parse::get_max_packet_size();
		$folder = WIC_Entity_Email_Account::get_folder();

		// define sql to get next short page of message stubs -- limiting to ten to minimize risk of memory issues; API would batch 100
		// but most expensive call is API, so use batch
		$current_id = 0;
		$page_size = 10; // can change this limit here without adjustment elsewhere, but max is 100
		$sql_template = "
			SELECT ID, extended_message_id FROM $inbox_table 
			WHERE 
				full_folder_string = '$folder' AND
				no_longer_in_server_folder = 0 AND
				serialized_email_object = '' AND
				ID > %d
			ORDER BY ID ASC
			LIMIT 0, $page_size
			";

		// set up counters
		$deleted_messages = 0;
		$parsed_messages = 0;
		$errors = 0;	
		// do we have work to do at this stage? if not, so note and continue;
		if ( ! $wpdb->get_results ( $wpdb->prepare ( $sql_template, array( $current_id ) ) ) ) {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_OAUTH_Update:update_inbox found no messages to parse.' );
		// loop through messages to parse, getting in batche
		} else {
			// look for blank serialized_email_object and parse them for as long as allowed (and there are any)
			while ( 
				time() < $cut_off_time &&
				$stubs =  $wpdb->get_results ( $wpdb->prepare ( $sql_template, array( $current_id ) ) )
				) {
				// get panel of 10 to reduce -- https://developers.google.com/api-client-library/php/guide/batch
				$client->setUseBatch(true);
				$batch = new Google_Http_Batch( $client );
				$id_look_up = array(); // working array to support double loop
				foreach ( $stubs as $stub ) {
					// keep tabs on current_id so will not loop on errors or unparse
					$current_id = $stub->ID;
					$messageId = $stub->extended_message_id;
 					$batch->add( $service->users_messages->get( 'me', $messageId ), $messageId );	
 					$id_look_up[$stub->extended_message_id] = $stub->ID;			
				}
				// get batch
				$results = $batch->execute();
				/*
				*
				* first loop through and store basics and payload
				*
				*/
				$do_more_work_on = array();
				foreach ( $results as $key => $result ) {
					// extract message id
					$extended_message_id = substr ( $key, 9 ); // key is of form 'response-' . $messageID
					$result_class = get_class ( $result ); // should just be exceptions and messages

					// if 404, mark not found -- this should be the only class of exception that returns results
					if ( 'Google_Service_Exception' == $result_class && '404' == $result->getCode() ) {
						$result = self::mark_message_no_longer_on_folder ( $extended_message_id, $folder, 0 ); // don't know history id -- is a gone message anyway
						if ( false === $result ) {
							WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
							return true; // end processing for this loop
						}
						$deleted_messages++;
					// if found
					} elseif ( 'Google_Service_Gmail_Message' == $result_class ) {
						// test labels to see if still inbox
						$labelId_array = $result->getLabelIds();
						if ( !in_array('INBOX', $labelId_array ) ) {
							$result = self::mark_message_no_longer_on_folder ( $extended_message_id, $folder, $result->getHistoryId());
							if ( false === $result ) {
								WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
								return true; // end processing for this loop							}	
							}
							$deleted_messages++;
						// not exception, still in inbox -- processing;
						} else {
							// prepare the email object
							$working_id = $id_look_up[$extended_message_id];
							$email_object = new WIC_DB_Email_Message_Object_Gmail;
							// preload thread_id, snippet and category provided by gmail
							$email_object->account_thread_id = $result->getThreadId();
							$email_object->snippet = $result->getSnippet ();
							foreach ( $labelId_array as $labelID ) {
								if ( 'CATEGORY_' == substr( $labelID, 0, 9) ) {
									$email_object->category = $labelID;
									break; 
								}
							}
							// now do the major build
							$email_object->build_from_gmail_payload ( $working_id, $extended_message_id, $result->getPayload(), $max_packet_size );
							if ( WIC_Entity_Email_Inbox_Parse::save_mail_object  ( $working_id, $email_object, $folder, $working_id, $max_packet_size  )   ) {
								$parsed_messages++;
							// if bad outcome, die -- something wrong with database on this run
							} else {
								WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
								return true; // end processing for this loop			
							}
						} // close not exception, still in inbox -- processing
					// returned object was neither exception nor message
					} else {
						WIC_Entity_Email_Cron::log_mail (  "WIC_Entity_Email_OAUTH_Update:update_inbox got unanticipated reponse of Class $result_class on Get for $extended_message_id:\n" . print_r( $result, true) );
					} // close returned object unanticipated
				} // close for loop through page of messages
			}  // close while still found message to parse
			// when have all messages, update last in thread field
			WIC_Entity_Email_Inbox_Parse::compile_thread_date_times( $folder );
		} // close any found messages to parse
		// log completion in debug mode
		WIC_Entity_Email_Cron::log_mail ( "WP Issues CRM -- GMAIL API message parser completed, parsing $parsed_messages messages and deleting $deleted_messages messages; $errors messages had update errors.  WIC_Entity_Email_OAUTH_Update::update_inbox." );
		
		// now with history id's updated for what we already have  . . .,
		self::get_history_updates ( $folder ); // do it for the folder that we just parsed fully 
		return true;
	}

	private static function mark_message_no_longer_on_folder ( $extended_message_id, $folder ) {
		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		 
		$sql = $wpdb->prepare ( 
			"
			UPDATE $inbox_table 
			SET no_longer_in_server_folder = 1
			WHERE extended_message_id = %s AND full_folder_string = %s
			", 
			array (
				$extended_message_id, 
				$folder 
			)
		);
		$result = $wpdb->query ( $sql );
		
		return $result;
	}

	// allowing move back, but only to_be_moved_on_server = 0, i.e., reversing an archival decision from the gmail side
	private static function mark_message_back_in_folder ( $extended_message_id, $folder ) {
		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$result = $wpdb->query ( 
			$wpdb->prepare ( 
				"
				UPDATE $inbox_table 
				SET no_longer_in_server_folder = 0
				WHERE extended_message_id = %s AND full_folder_string = %s
				AND no_longer_in_server_folder = 1 and to_be_moved_on_server = 0
				", 
				array (
					$extended_message_id, 
					$folder 
				)
			)
		);
		return $result;
	}

	// test for remote possibility that message has already been added by a coincidence and if not, add				
	private static function verified_add_single ( $folder, $process_time_stamp, $message_id, $thread_id ) {
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
	
		// lock the inbox table to prevent remote possibility of conflicting synch add transaction (select,select,insert,insert)
		$locked = $wpdb->query (
			"
			LOCK TABLES $inbox_table WRITE
			"
		);

		// unsuccessful lock
		if ( false === $locked ) { 
			return false;
		}
		
		// does the message exist already ?
		$sql = $wpdb->prepare ( 
			"
			SELECT extended_message_id FROM $inbox_table WHERE extended_message_id = %s and full_folder_string = %s LIMIT 0, 1
			",
			array( $message_id, $folder)
		);
		$result = $wpdb->get_results ( $sql );
		if ( false === $result ) { // database error
			// release the lock
			$wpdb->query (
				"
				UNLOCK TABLES
				"
			);
			return false;
		} elseif ( $result ) { // array including found record
			// release the lock
			$wpdb->query (
				"
				UNLOCK TABLES
				"
			);
			return 0; // no record added
		}
		
		// successfully checked, but did not find record, so go ahead and add
		$sql = $wpdb->prepare (
			"
			INSERT INTO $inbox_table ( full_folder_string, utc_time_stamp, extended_message_id, account_thread_id ) VALUES 
			( %s, %f, %s, %s )
			",
			array ( $folder, $process_time_stamp, $message_id, $thread_id )
		);
		$result = $wpdb->query ( $sql );
		// release the lock
		$wpdb->query (
			"
			UNLOCK TABLES
			"
		);
		return $result; // will be false or 1
	
	} 

	
	/*
	*
	*  THIS UPDATE-SYNCH PROCESSES UPDATES IN CHRONOLOGICAL ORDER AND UPDATES HISTORY START POINT SO IF IT WERE INTERRUPTED, IT WOULD RESTART IN THE RIGHT PLACE
	*
	*  STEPS
	*	(1) ADD added records, with labels and history id
	*	(2) Mark delete 
	*		deleted records
	*		and records for which have removed inbox label via gmail
	*	(3) remove inbox label as in last step of full synch
	*	(4) like full synch, also move things back to inbox upon relabelling as inbox, but only if not deliberately moved on db side
	*
	*	Takes folder from end of parse process to assure that not changed intra-run
	*/	
	
	private static function get_history_updates ( $folder ) { 
	
		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$process_time_start = microtime( true );
		$process_time_stamp = floor ( $process_time_start );
	
		/*
		* prepare for API Access -- will be doing access regardless of whether any unparsed to check for updates
		*
		* 
		*
		*/
		$check_return = WIC_Entity_Email_OAUTH::check_user_auth();
		if ( !$check_return['response_code'] ) {
			WIC_Entity_Email_Cron::log_mail ( 'Failed to connect to Gmail in WIC_Entity_Email_OAUTH_Update::get_history_updates -- check_connect said: ' . $check_return['output'] );
			return true;
		} 
		// get access token 
		$access_token = $check_return['output'];
		// try to set it up in client -- should be perfect;
		try  { 
			$client = WIC_Entity_Email_OAUTH::get_client();
			$client->setAccessToken( $access_token );
		} catch ( Exception $e ) {
			WIC_Entity_Email_Cron::log_mail ( 'On setAccessTokenGmail Client said in WIC_Entity_Email_OAUTH_Update::get_history_updates : ' . $e->getMessage );
			return true;
		}
		$service = new Google_Service_Gmail( $client );
		$check_connection_time = microtime(true);
		// check message count
		$inbox_label = $service->users_labels->get ( 'me', 'INBOX' );
		$num_msg = $inbox_label->messagesTotal;

		/* 
		*   RELYING HISTORYID TO DETERMINE START POINT -- GOING BY UPDATE OF $history_id_option_name
		* 	SET BELOW HIGHEST SNAPSHOT VALUE BY GRABBING FROM PROFILE BEFORE FULL SYNCH 
		*	UPDATED AFTER EACH HISTORY TRANSACTION RECORDED FOR RERUNNABILITY FROM FURTHEST POINT ALONG
		*	CAN BE RESET BY FULL SYNCH (AND WILL BE IF NOT FOUND, 0, ALPHA OR GET A 404 ON IT)
		*/
		
		// get start for synch
		$history_id_option_name = 'wp-issues-crm-gmail-api-start-sync-for-' . $folder; 
		$start_id = get_option ( $history_id_option_name );
		// if not set or zero do a full sync and nothing more
		if ( !$start_id || !is_numeric( $start_id ) ) {
			$response =  WIC_Entity_Email_OAUTH_Synch::synch_inbox( true ); // as if online
			if ( ! $response['response_code'] ) {
				WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_OAUTH_Synch::synch_inbox reported: ' . $response['output'] );
			}
			return true;
		} else {
			WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_OAUTH_Update::get_history_updates starting partial synch for $folder at $start_id" );
		}
		
		// get history starting above $start_id and only for messages in/formerly-in inbox -- will include deletes and removals of label inbox
		$opt_param = array( 'startHistoryId' => $start_id, 'labelId' => 'INBOX' );
		$pageToken = NULL;
		$histories = array();
		/*
		* note: the following derives from the example provided at https://developers.google.com/gmail/api/v1/reference/users/history/list
		*
		* $historyResponse is an object of type Google_Service_Gmail_ListHistoryResponse
		* 	https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/class-Google_Service_Gmail_ListHistoryResponse.html
		*
		* $historyResponse->getHistory is an array of objects of type Google_Service_Gmail_History
		*	https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/class-Google_Service_Gmail_History.html
		*	
		*	methods of the Google_Service_Gmail_History type refer to messages plural, they sometimes return more than one message
		*		they return an array that will have zero or one or more elements -- zero if the history item does not include that type of event
		*	in addition to getID() for the historyId, there are five methods callable on the history object: 
		*		-- getMessages ( returns an array with a message object, only populated with id and history id, present for all history events, but useless ) 
		*		-- the other four always return array, but will be zero length unless the history object refers to that event (not clear if can ever have more than one return positive array on single event) 
		*			getMessagesAdded 
		*			getMessagesDeleted
		*			getLabelsAdded
		*			getLabelsRemoved
		*   The later two return an object wrapping a message object populated by labels -- get that message object to access the labels.
		*  
		*   History events come in in chronological order;  may be clustered for a single message without any event reporting an action;  apparently some intermediate events
		*       -- label adds generate multiple history entries, but only final entry with a getLabelsAdded value.
		*		-- hence the instruction about the return from getMessages : "List of messages changed in this history record. The fields for specific change types, 
		*			such as messagesAdded may duplicate messages in this field. We recommend using the specific change-type fields instead of this."
		*			https://developers.google.com/gmail/api/v1/reference/users/history/list
		*		-- NO RULE SAYS THAT AT MOST ONE OF THE ACTION METHODS WILL RETURN A POSITIVE ARRAY FOR ONE HISTORY OBJECT - ALTHOUGH THIS APPEARS TO BE TRUE, DON'T ASSUME IT
		*/
		do {
			try {
				if ($pageToken) {
					$opt_param['pageToken'] = $pageToken;
				}
				$historyResponse = $service->users_history->listUsersHistory( 'me', $opt_param);
				if ($historyResponse->getHistory()) {
					$histories = array_merge($histories, $historyResponse->getHistory());
					$pageToken = $historyResponse->getNextPageToken();
				}
			} catch (Exception $e) {
				$error_code = $e->getCode();
				if ( 400 == $error_code || 404 == $error_code ) {
					$response =  WIC_Entity_Email_OAUTH_Synch::synch_inbox( true ); // as if online
					if ( ! $response['response_code'] ) {
						WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_OAUTH_Synch::synch_inbox reported: ' . $response['output'] );
					}
					return true;
				} else {
					WIC_Entity_Email_Cron::log_mail ( 'On getHistory service said in WIC_Entity_Email_OAUTH_Update::get_history_updates : ' . print_r( $e, true ) );
				}
				return true;
			}
		} while ($pageToken);
		
		$adds = 0;
		$deletes = 0;
		$label_adds = 0;
		$label_deletes = 0;
		$total_history_items = 0;

		foreach ( $histories as $history ) {
			// has an action been taken on this history id?
			$action_taken = false;

			// get the id for the history entry
			$history_id = $history->getId(); 

			if ( $messages = $history->getMessagesAdded() ) {
				/* 
				* if the entry includes a new record, $messageObject is Google_Service_Gmail_HistoryMessageAdded
				* https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/class-Google_Service_Gmail_HistoryMessageAdded.html
				*/
				foreach ( $messages as $messageObject ) {
					$message = $messageObject->getMessage();
					// exclude sent and drafts that google includes in inbox feed
					if ( in_array( 'INBOX', $message->getLabelIDs() ) ) {
						// test for remote possibility that message has already been added by a coincidence and if not, add				
						$result = self::verified_add_single ( $folder, $process_time_stamp, $message->getId(), $message->getThreadId() );
						if ( false === $result ) {
							WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
							return true; // end processing for this loop
						} else {
							$adds = $adds + $result; // 0 or 1
							$action_taken = true;
						}
					}
				}
			}

			// for legacy compatibility -- stub records are saved without UID used as unique ID from IMAP functions
			WIC_Entity_Email_OAUTH_Synch::patch_folder_uid( $folder );

			if ( $messages = $history->getMessagesDeleted() ){
				/*
				* if the entry includes message deleted, $messageObject is Google_Service_Gmail_HistoryMessageDeleted
				* https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/class-Google_Service_Gmail_HistoryMessageDeleted.html
				*/
				foreach ( $messages as $messageObject ) {
					$message = $messageObject->getMessage();
					$deletes++;
					// no need to check for duplication; repeated update is OK
					$result = self::mark_message_no_longer_on_folder ( $message->getId(), $folder );
					if ( false === $result ) {
							WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
							return true; // end processing for this loop					
					} else {			
						$action_taken = true;
					}
				}
			}
			if ( $messages = $history->getLabelsRemoved () ) {
				/* 
				* if the entry includes labels removed, $messageObject is Google_Service_Gmail_HistoryLabelRemoved
				* https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/class-Google_Service_Gmail_HistoryLabelRemoved.html
				*/
				foreach ( $messages as $messageObject ) {
					if ( in_array( 'INBOX', $messageObject->getLabelIDs() ) ) {
						$message = $messageObject->getMessage();
						$label_deletes++;
						// no need to check for duplication; repeated update is OK
						$result = self::mark_message_no_longer_on_folder ( $message->getId(), $folder );		
						if ( false === $result ) {
							WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
							return true; // end processing for this loop
						} else {
							$action_taken = true;
						}				
					}
				}
			}
			if ( $messages = $history->getLabelsAdded() ) { 
				/* 
				* if the entry includes labels added, $messageObject is Google_Service_Gmail_HistoryLabelAdded
				* https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/class-Google_Service_Gmail_HistoryLabelAdded.html
				*/
				foreach ( $messages as $messageObject ) {
					if ( in_array( 'INBOX', $messageObject->getLabelIDs() ) ) { // belt and suspenders since selecting inbox . . .
						$message = $messageObject->getMessage();				
						$label_adds++;
						// record may not exist on db, as for example, retrieve from spam
						$result = self::verified_add_single ( $folder, $process_time_stamp, $message->getId(), $message->getThreadId() );
						if ( false === $result ) { // bad db return on attempt add record
							WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
							return true; // end processing for this loop						
						} elseif ( 1 === $result ) { // added record; done
							$action_taken = true;
						} elseif ( 0 === $result) { // record already added, but not in folder, so mark it back in folder	
							$result = self::mark_message_back_in_folder ( $message->getId(), $folder );		
							if ( false === $result ) {
								WIC_Entity_Email_Cron::log_mail ( 'Error in WIC_Entity_Email_OAUTH_Update at line ' . __line__ .  ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) );
								return true; // end processing for this loop							
							} else {
								$action_taken = true;
							}
						}				
					}			
				}
			}
			
			/*
			*
			* all steps are rerunnable
			* but assure max progress on rerun by updating the start point after each successful loop
			*
			*/
			if ( $action_taken ) {
				update_option ( $history_id_option_name, $history_id );
			}
			
			$total_history_items++;
		} // close history loop
		
		$time_elapsed_in_synch = microtime(true ) - $process_time_start; 
		
		// lastly, must do moves driven by to_be_moved_on_folder -- supplying blank temp table just causes read of all available moves (a few of which may be unnecessary)
		$move_result = WIC_Entity_Email_OAUTH_Synch::move_processed_records  ( $service, '', $folder );	
		if ( !$move_result['response_code'] ) {
			WIC_Entity_Email_Cron::log_mail ( $move_result['output'] );
		}	
		$move_time = microtime(true) - ( $time_elapsed_in_synch + $process_time_start );
		// finally save results 
		$result = WIC_Entity_Email_Inbox_Synch::inbox_synch_status ( $folder, $process_time_stamp ); // snapshot
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$sql = $wpdb->prepare(
			"INSERT into $synch_log_table
				( 
				full_folder_string,
				utc_time_stamp, 
				num_msg,
				count_new,
				count_deleted,
				count_image_mark_deleted,
				synch_count,
				incomplete_record_count,
				pending_move_delete_count,
				stamped_synch_count,
				stamped_incomplete_record_count,
				added_with_this_timestamp,
				check_connection_time,
				synch_fetch_time,
				do_deletes_time,
				process_moves_time
				 ) VALUES
				(%s,%f,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%f,%f,%f,%f)",
				array(
					$folder, 
					$process_time_stamp,
					$num_msg, // checked at folder start
					$adds + $label_adds,   // added or restored by this run
					$move_result['output'], // count of pending/move deletes accomplished from db to server
					$deletes + $label_deletes, // mark deleted by this run
					$result->synch_count, // total images on server at end of run
					$result->incomplete_record_count, // unparsed messages in image at end of run (snapshot)
					$result->pending_move_delete_count, // >messages still found on image as marked to be moved from folder after this run
					$result->stamped_synch_count, // net new messages
					$result->stamped_incomplete_record_count, // incomplete with this time stamp
					$result->added_with_this_timestamp, // hard adds only
					$check_connection_time - $process_time_start,
					$time_elapsed_in_synch,
					$time_elapsed_in_synch,
					$move_time,					 
				)
			);
		$wpdb->query( $sql ); 


		WIC_Entity_Email_Cron::log_mail ( "WP Issues CRM -- GMAIL API partial synch completed:  Processed $total_history_items history items in $time_elapsed_in_synch seconds -- 
		$adds adds, $deletes deletes, $label_adds label adds with INBOX still present, $label_deletes label deletes with INBOX no longer present." );


		return true; // essentially always returning true -- no error handling and want to move on in cron cycle if there was an error	
	}
	
}