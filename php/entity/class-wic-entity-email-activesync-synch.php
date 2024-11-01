<?php
/*
*
*	wic-entity-email-activesync-synch.php
*
*	
*/
// this is the location of the copy of the php ews client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'activesync'  . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
use \jamesiarmes\PhpEws\ArrayType\ArrayOfFolderIdType;
use \jamesiarmes\PhpEws\Autodiscover; 		// autodiscover
use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DisposalType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Enumeration\SyncFolderItemsScopeType;
use \jamesiarmes\PhpEws\Request\DeleteItemType;
use \jamesiarmes\PhpEws\Request\GetFolderType;
use \jamesiarmes\PhpEws\Request\SyncFolderItemsType;
use \jamesiarmes\PhpEws\Response\DeleteItemResponseType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\FolderResponseShapeType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use \jamesiarmes\PhpEws\Type\TargetFolderIdType;

class WIC_Entity_Email_ActiveSync_Synch Extends WIC_Entity_Email_OAUTH_Synch {

	/*
	* Note that if the synchronization here is a full sync
	*	1 -- takes a full snapshot of the inbox and adds any missing records to the DB
	*	2 -- mark deletes an records on the DB that are no longer in the inbox
	*	3 -- does a soft delete on server for records that have been marked to be moved on the server
	*	4 -- as part of 1, updates records that are present on both copies, but marked as no_longer_in_folder on the DB side 
	*		 -- but not if they are marked to be moved.  This allows user to reverse or recover from archival/refiling of messages that have not been responded to.
	*		It does not move records that are marked to be moved, indicating that they have already been processed on the db side.
	*		-- the value of to_be_moved_on_server remains 1 after records are moved 
	*
	* Process is essentially the same on partial sync, but does not mark delete records that are not in the snapshot
	*
	* Critical notes here:
	* https://docs.microsoft.com/en-us/exchange/client-developer/exchange-web-services/ews-identifiers-in-exchange
	*   ID field length s/b 512 -- am I oversizing this since including one component?
	*	Binary comparisons -- case sensitive
	*	ID IS NOT INVARIANT!!!
	*
	* https://docs.microsoft.com/en-us/exchange/client-developer/exchange-web-services/mailbox-synchronization-and-ews-in-exchange
	* https://docs.microsoft.com/en-us/exchange/client-developer/exchange-web-services/ews-throttling-in-exchange
	*/

	const SYNC_POINT_PREFIX =  'wp-issues-crm-activesync-start-sync-for-';

	// take place of IMAP synch function when activesync
	public static function synch_inbox ( $online = false ) {

  		// time_stamp for start processing -- use for reporting only
		$check_connection_time = microtime (true);

    	// get client 
    	$response = WIC_Entity_Email_ActiveSync::get_connection();
    	if ( ! $response['response_code'] ) {
    		return $response;
    	} else {
    		$client = $response['output'];
    	}

		// time stamp for connection setup completed -- reporting purposes only -- show connection speed and collect results of this run in inbox
		$synch_fetch_time = microtime(true);
		$synch_fetch_time_stamp = floor( $synch_fetch_time );

		// get folder from main router	
		$folder = WIC_Entity_Email_Account::get_folder();
    	
		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- connection initiated for timestamp $synch_fetch_time_stamp -- folder $folder. ");

		// set up temporary table to capture download of message_ids
		$temporary_table = '';
		$result = self::create_temporary_staging_table ( $synch_fetch_time_stamp );
		if ( !$result['response_code'] ) {
			return $result;
		} else {
			$temporary_table = $result['output'];
		}

		// attempt to lock inbox before deciding whether full or partial synch -- this forces wait for last synch to complete and set processing flag in overrun situation
		self::lock_inbox( $temporary_table );
		

		// what kind of synch?
		$sync_start_point = self::SYNC_POINT_PREFIX . $folder;
		// if online do full synch -- prior synch points are irrelevant and no longer correct
		if ( $online ) {
			delete_option ( $sync_start_point );		
			$sync_start = null;
		} else {
			// if not online, and have a start point, then do partial synch
			if ( !$sync_start = get_option (  $sync_start_point ) ) {
			// if not online, but do not have a start point, do a full synch
				$sync_start = null;
			}
		}

		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- starting with inbox table locked -- " . ( !$sync_start ? 'full' : 'update/partial'  ) . " synch for $folder. ");


		/*
		*
		* get inbox message count at start of run
		*
		*/
		$num_msgs = false;
		// Build the request -- properties requested
		$request = new GetFolderType();
		$request->FolderShape = new FolderResponseShapeType();
		$request->FolderShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;
		// Build the request -- folder requested
		$inbox_folder = new TargetFolderIdType();
		$inbox_folder->DistinguishedFolderId = new DistinguishedFolderIdType();
		$inbox_folder->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::INBOX;
		$request->FolderIds = $inbox_folder; // looks like it should be an array, but takes singular
		// make the request
		$response = $client->GetFolder ( $request );
		// Iterate over the results, checking for errors (expecting one message in response array-- error or result)
		$response_messages = $response->ResponseMessages->GetFolderResponseMessage;
		foreach ($response_messages as $response_message) {
			// Make sure the request succeeded.
			if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
				$code = $response_message->ResponseCode;
				$message = $response_message->MessageText;
				self::unlock_inbox();
				return array ( 'response_code' => false, 'output' =>  'Server said on WIC_Entity_Email_ActiveSync_Synch::synch_inbox: ' . $message );	
				continue;
			} else {
				$num_msgs = $response_message->Folders->Folder[0]->TotalCount;
			}
		}
		if ( false === $num_msgs )  {
			self::unlock_inbox();
			return array ( 'response_code' => false, 'output' =>  'Unable to retrieve message count in WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- unexpected response.' );	
		}

		// time stamp the run start 		
		self::save_stub_log_entry( $folder, $synch_fetch_time_stamp, $num_msgs );
	
		// actually go out to activesync and get the records
		$result = self::populate_temporary_staging_table ( $folder, $synch_fetch_time_stamp, $client, $temporary_table, $sync_start );
		if ( !$result['response_code'] ) {
			self::unlock_inbox();
			return $result;
		} else {
			$final_sync_state = $result['output'];
		}	

		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- continuing with staging table populated for timestamp $synch_fetch_time_stamp -- folder $folder. ");
		
		// synch against the temp table -- add new records ( all and only those labelled INBOX and not present already on image)
		$save_result = self::save_stub_records ( $temporary_table, $folder, $synch_fetch_time_stamp );
		if ( !$save_result['response_code'] ) {
			self::unlock_inbox();
			return $save_result;
		} else { 
			self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_new', $save_result['output'] );
		}	

		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- continuing with stub records saved for timestamp $synch_fetch_time_stamp -- folder $folder. ");


		// for legacy compatibility -- stub records are saved without UID used as unique ID from IMAP functions
		self::patch_folder_uid( $folder );
		
		$do_deletes_time = microtime(true); // just for reporting
		// in full synch, doing deletions by comparison to the temp table; in partial synch, doing it from the list of deletes as part of populate table stage
		if ( !$sync_start ) {
			$delete_result = self::mark_delete_obsolete_records ( $temporary_table, $folder ); 
			if ( !$delete_result['response_code'] ) {
				self::unlock_inbox();
				return $delete_result;
			} else {
				self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_image_mark_deleted', $delete_result['output'] );
			}
		}
		
		// at this point, have completed all synch from the server -- can save synch state
		update_option ( $sync_start_point, $final_sync_state );

		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- continuing with server synch complete timestamp $synch_fetch_time_stamp -- folder $folder. ");
		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- folder $folder at $synch_fetch_time_stamp run end had final_sync_state = " . ( $final_sync_state ? $final_sync_state : 'MISSING' )  . ". ");


		//  delete messages out of activesync inbox that have been processed on WP Issues CRM
		$process_moves_time = microtime(true);
		$move_result = self::soft_delete_processed_records  ( $client, $folder ); 
		if ( !$move_result['response_code'] ) {
			self::unlock_inbox();
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


		// log end 
		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Synch::synch_inbox -- completed " . ( !$sync_start ? 'full' : 'update/partial'  ) . " synch for $folder. ");


		// all processing done for inbox -- release_lock and return
		self::unlock_inbox();
		return 	array ( 'response_code' => true , 'output' => 'Successful ActiveSync Sync Run' );
	 
	}

	// get list of all the messages in the inbox and save to temp table
	// while in the loop also handle deletes -- (a) in full sync, making temp table right; (b) in partial sync mark deleting them on db
	protected static function populate_temporary_staging_table ( $folder, $synch_fetch_time_stamp, $client, $temporary_table, $sync_start) {

		// set up counters
  		$count_saved = 0;
  		$retrieved_all_changes = 0;
  		
		/*
		*  Build the request base.
		*/
		$request = new SyncFolderItemsType();
		// set startpoint
		$request->SyncState = $sync_start;
		// paging
		$request->MaxChangesReturned = 512;
		// getting only ids
		$request->ItemShape = new ItemResponseShapeType();
		$request->ItemShape->BaseShape = DefaultShapeNamesType::ID_ONLY;
		// syncing the inbox
		$inbox_folder = new TargetFolderIdType();
		$inbox_folder->DistinguishedFolderId = new DistinguishedFolderIdType();
		$inbox_folder->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::INBOX;
		$request->SyncFolderId = $inbox_folder;
		// request full folder information
  		$request->SyncScope = SyncFolderItemsScopeType::NORMAL;  // NORMAL_AND_ASSOCIATED returns additional items that are not actually messages
  		// start loop -- grab 512 at a time and save all messages to a temporary staging table
		do {
			$response = $client->SyncFolderItems($request); 
			$response_messages = $response->ResponseMessages->SyncFolderItemsResponseMessage;
			$new_message_ids = array();
			foreach ( $response_messages as $response_message ) {
				// Make sure the request succeeded.
				if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
					$code = $response_message->ResponseCode;
					$message = $response_message->MessageText;
					return 	array ( 'response_code' => false , 'output' => 'Server said on WIC_Entity_Email_ActiveSync_Synch::populate_temporary_staging_table: ' . $message  );
				}
				// Store SyncState for the next loop.
				$request->SyncState = $response_message->SyncState;
				// True will terminate 'while' loop
				$retrieved_all_changes = $response_message->IncludesLastItemInRange;
				// if there are additions, save them into the temp table
				if ( $response_message->Changes->Create ) {
					$save_result = self::save_temp_message_id_records ( $response_message->Changes->Create , $temporary_table );
					if ( !$save_result['response_code'] ) {
						return $save_result;
					} else {
						$count_saved = $count_saved + $save_result['output'];
						self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_on_temp_table', $count_saved );
					} 
    			}
				if ( $response_message->Changes->Delete ) {
	    			// if there are deletions (normally none in full synch, but could happen if deleted while paging through) delete them from the new list and decrement saved count
					if ( !$sync_start ) {
						$delete_result = self::delete_temp_message_id_records ( $response_message->Changes->Delete, $temporary_table );
						if ( !$delete_result['response_code'] ) {
							return $delete_result;
						} else {
							$count_saved = $count_saved - $delete_result['output'];
							self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_on_temp_table', $count_saved );
						} 
					// if there are deletions on a partial synch, need to mark delete them directly from the database and these count as deletions
    				} else {
						$delete_result = self::mark_delete_records ( $response_message->Changes->Delete, $folder );
						if ( !$delete_result['response_code'] ) {
							return $delete_result;
						} else {
							self::update_stub_log_entry( $folder, $synch_fetch_time_stamp, 'count_image_mark_deleted', $delete_result['output'] );
						}     
					}
    			}
				// not handling changes->readflag or changes->modifications
			}		
		} while (!$retrieved_all_changes );
		return array ( 'response_code' => true , 'output' => $request->SyncState  ); // return the last synch state (will have been set with last, although not executed with last)
	}

	
	// batch inserts of temp records into staging table
	private static function save_temp_message_id_records ( &$changes_create, $temporary_table ) {
		// shouldn't be here if nothing to do
		if ( ! $changes_create ) {
			return 	array ( 'response_code' => true , 'output' => 0 );
		}
		// if some messages, then proceed
		global $wpdb;
		$sql = "INSERT INTO $temporary_table ( message_id ) VALUES ";
		$count_saved = 0;
		foreach ( $changes_create as $create ) {
			/*
			* a single value of $create is an object with many properties, each corresponding to a potential message object type (e.g., message, meeting request )
			* only one will be set, but we need to iterate to find out which one and use it -- the types are all extensions of the base message object type, so
			* they have the properties that we will use downstream
			*/
			foreach ( $create as $key => $value ) {
				if ( isset ( $value->ItemId ) ) {
					$sql .= $wpdb->prepare(  "( %s ), ", array ( $value->ItemId->Id ) ); // {} notation truncated at 64 in some circumstances?
				}
			}
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
	
	//  deletions of temp records in staging table (should rarely happen)
	private static function delete_temp_message_id_records ( &$changes_delete, $temporary_table ) {
		// shouldn't be here if nothing to do 
		if ( ! $changes_delete ) {
			return 	array ( 'response_code' => true , 'output' => 0 );
		}
		// if some messages, then proceed
		global $wpdb;
		// construct single query -- packet size limited by page size
		$sql = "DELETE FROM $temporary_table WHERE BINARY message_id IN(";
		$count_deleted = 0;
		foreach ( $changes_delete as $delete ) {
			$sql .= (  "'{$delete->ItemId->Id}',");
			$count_deleted++;
		}
		$sql = trim ( $sql, ', ');
		$sql .= ')' ; 
		$result = $wpdb->query ( $sql );
		if ( false === $result ) {
			return 	array ( 'response_code' => false , 'output' => $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true , 'output' => $count_deleted );
		}
	}

	protected static function save_stub_records ( $temporary_table, $folder, $synch_fetch_time_stamp ) {
		
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		// do the inserts
		$insert_sql =  
			"
			INSERT INTO $inbox_table ( full_folder_string, utc_time_stamp, extended_message_id ) 
			SELECT '$folder', %f, message_id 
			FROM $temporary_table LEFT JOIN  $inbox_table as i2 
			ON BINARY $temporary_table.message_id = i2.extended_message_id 
			WHERE extended_message_id IS NULL
			"
			;
		
		$result1 = $wpdb->query ( $wpdb->prepare ( $insert_sql, array ( $synch_fetch_time_stamp ) ) );

		// no second update for activesync because message id will change if moved out of folder ( compare gmail sync where message id is immutable )

		// report results
		if ( false === $result1 ) {
			return array ( 'response_code' => false, 'output' => 'Database error: ' . $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true, 'output' => $result1 );
		}
	}

	
	private static function mark_delete_records ( $changes_delete, $folder ) {
		// shouldn't be here if nothing to do 
		if ( ! $changes_delete ) {
			return 	array ( 'response_code' => true , 'output' => 0 );
		}
		// construct list to be mark deleted
		$message_in_phrase = "IN(";
		$count_deleted = 0;
		foreach ( $changes_delete as $delete ) {
			$message_in_phrase .= (  "'{$delete->ItemId->Id}',");
			$count_deleted++;
		}		
		$message_in_phrase = trim ( $message_in_phrase, ', ');
		$message_in_phrase .= ')' ; 			

		// do the mark deletes
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$sql =  $wpdb->prepare(
			"
			UPDATE $inbox_table 
			SET no_longer_in_server_folder = 1
			WHERE full_folder_string = %s AND
			no_longer_in_server_folder = 0 AND
			BINARY extended_message_id $message_in_phrase
			",
			array( 
				$folder
			)
		);

		$result = $wpdb->query ( $sql );
		if ( false === $result ) {
			return 	array ( 'response_code' => false , 'output' => $wpdb->last_error );
		} else {
			return 	array ( 'response_code' => true , 'output' => $count_deleted );
		}

	}

	// paginate the soft_delete of records that have been handled already 
	private static function soft_delete_processed_records  ( $client, $folder ) {
		/*
		* no locking necessary here -- this can be repeated or run simultaneously
		* 
		* this process can prevent WP Issues CRM undo of message if user has bad timing
		* but consider this acceptable -- no reversal after the undo button expires and bad timing just accelerates expiration
		*
		*/		
	
		// get messages that need to be deleted -- just do move to trash, not special folder
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		// to_be_moved_on_server = 1 -- delete, record or reply action already taken on WP Issues CRM
		// no_longer_in_server_folder = 0 -- message has not been marked processed on the server
		// limiting to 10 arbitrarily (but cf 10 recommend for getItem) -- normally will suffice to make deletes a single call
		$current_id = 0;
		$sql_template = 
			"
			SELECT ID, extended_message_id FROM 
			$inbox_table 
			WHERE to_be_moved_on_server = 1 
			AND no_longer_in_server_folder = 0
			AND full_folder_string = '$folder'
			AND ID > %d
			ORDER BY ID 
			LIMIT 0, 10
			"
		;		
		$count_moved = 0;
		
		/*
		*  Build the request base.
		*/
		$request = new DeleteItemType();
		$request->DeleteType = DisposalType::MOVE_TO_DELETED_ITEMS;
		while ( $results = $wpdb->get_results ( $wpdb->prepare ( $sql_template, array( $current_id ) ) ) ) {
			// build array of ids to be submitted in request
			$request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
			// parallel build simple arrays of ids and message_ids for use in marking results
			$IDs = array(); 
			$message_ids = array();
			foreach ( $results as $result ) {
   				 $item = new ItemIdType();
  				 $item->Id = $result->extended_message_id;
   				 $request->ItemIds->ItemId[] = $item;
   				 $IDs[] = $result->ID;
   				 $message_ids[] = $result->extended_message_id;

			}
			// execute request
			$response = $client->DeleteItem($request); 
			$response_messages = $response->ResponseMessages->DeleteItemResponseMessage;
			foreach ($response_messages as $order => $response_message) {  
				// move the while pointer in case loop again
				$current_id = $IDs[$order];
				/*
				* Will get one message back per sent id.  Some could be successful.  Some failure.
				*
				* https://docs.microsoft.com/en-us/exchange/client-developer/web-service-reference/deleteitemresponsemessage
				* if there is a warning, do not update the message_id in the next step.  If a permanent error, assume should be deleted.
				*
				* Order of messages -- maps to order of id's sent[no documentation says this, but it has to be true, b/c respones do not identify messages]. 
				* https://docs.microsoft.com/en-us/exchange/client-developer/exchange-web-services/how-to-process-email-messages-in-batches-by-using-ews-in-exchange
				*/
				if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
					$code = $response_message->ResponseCode;
					$message = $response_message->MessageText;
					if ( $response_message->ResponseClass == ResponseClassType::WARNING ) {
						unset ( $IDs[$order] );  // remaining in ids array are all successful or error.  On error don't bother to retry later, likely was moved out of folder manually
						WIC_Entity_Email_Cron::log_mail( 'Server warned on WIC_Entity_Email_ActiveSync_Synch::soft_delete_processed_records: ' . $message .  "Pertained to message response $order, with ID $current_id and extended_message_id:" . $message_ids[$order] );
					}
				}
			}
			// request was successful (or was permanent error )-- update messages as out of inbox
			$id_string = " IN('" . implode( "','", $IDs ) . "') ";
			$sql = "
				UPDATE $inbox_table 
				SET no_longer_in_server_folder = 1
				WHERE ID $id_string 
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


	protected static function lock_inbox ( $temporary_table ) {
		
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$option_table = $wpdb->prefix . 'options';


		$locked = $wpdb->query (
			"
 			LOCK TABLES $inbox_table WRITE, $inbox_table as i2 READ, $temporary_table WRITE, $synch_log_table WRITE, $option_table WRITE
 			"
		);
		if ( false === $locked ) { 
			return array ( 'response_code' => false, 'output' => 'Could not lock inbox table for synchronization: ' . $wpdb->last_error );
		} else {
			return array ( 'response_code' => true, 'output' => 'Inbox lock successful' );
		}

	}
	
	
	
	protected static function unlock_inbox () {
		
		global $wpdb;
	
		// release the reservation table lock
		$wpdb->query (
			"
			UNLOCK TABLES
			"
		);		

	}


} // close class