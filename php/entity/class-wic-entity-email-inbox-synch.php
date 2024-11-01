<?php
/*
*
*	wic-entity-email-inbox-synch.php
*
*
*   GMAIL: notes for each function reflect 12/2018 mods to convert to Gmail API -- Review complete
*
**/
Class WIC_Entity_Email_Inbox_Synch {
	
	// GMAIL: folder, folder_label and server replaced with _OAUTH functions
	public static function load_staged_inbox ( $dummy_id, $data ) {

		// get current folder 
		$folder = WIC_Entity_Email_Account::get_folder();
		if ( '' == $folder ) {
			return array ( 'response_code' => false , 'output' => 'Check settings -- no inbox folder selected.' ) ;
		}
		$folder_label = WIC_Entity_Email_Account::get_folder_label();
		$server = WIC_Entity_Email_Account::get_server();	

		global $wpdb;

		// get folder count
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$sql = "SELECT 
			sum(if( no_longer_in_server_folder = 0, 1, 0 ) ) AS current_messages,
			sum(if( no_longer_in_server_folder = 0 AND to_be_moved_on_server = 0 AND serialized_email_object > '', 1, 0 ) ) AS inbox_count,
			sum(if( no_longer_in_server_folder = 0 AND to_be_moved_on_server = 0 AND serialized_email_object = '', 1, 0 ) ) AS pending_parse,
			sum(if( no_longer_in_server_folder = 0 AND to_be_moved_on_server = 1 AND serialized_email_object > '', 1, 0 ) ) AS pending_deletes,
			sum(if( no_longer_in_server_folder = 1, 1, 0 ) ) AS archived_messages, 
			count(folder_uid) as all_messages,
			sum(if( serialized_email_object > '' AND no_longer_in_server_folder = 1, 1, 0 ) ) AS never_parsed
			FROM $inbox_table 
			WHERE full_folder_string = '$folder'";		
		$result = $wpdb->get_results ($sql);
		// format header for detail list
		$output = "<h3>Instantaneous count of messages in current WP Issues CRM image of $folder_label at $server</h3>";
		$output	.=	'<table class="synch_status wp-issues-crm-stats">
					<colgroup>
						<col style="width:35%">
						<col style="width:65%">
					</colgroup>
					<tbody>
						<tr><th></th><th class="wic-statistic-text">Statistics as of last refresh of this page.</th></tr>	
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->current_messages ) . '</td>
							<td class="wic-statistic-text">unarchived messages in image</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->inbox_count ) . '</td>
							<td class="wic-statistic-text">messages showing in inbox</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->pending_parse ) . '</td>
							<td class="wic-statistic-text">unparsed, but not archived, messages in image ( do not show in inbox )</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->pending_deletes ) . '</td>
							<td class="wic-statistic-text">parsed messages in image marked to be moved and archived on next synch ( do not show in inbox )</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->archived_messages ) . '</td>
							<td class="wic-statistic-text">archived messages ( already moved from server folder, do not show in inbox )</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->all_messages ) . '</td>
							<td class="wic-statistic-text">all messages in image, including archived</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . intval( $result[0]->never_parsed ) . '</td>
							<td class="wic-statistic-text">archived messages that were deleted before parse ( memo )</td>
						</tr>
					</tbody>
				</table>';



		// get last completed run stats (note -- may not be last started)
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$where = $folder ? " where full_folder_string = '$folder' " : '';
		$sql = "SELECT * from $synch_log_table $where ORDER BY ID DESC LIMIT 0, 1";
		$result = $wpdb->get_results ( $sql );
		
		if ( $result ) {

			$output .= '
		
				<h3>Statistics from last synchronization for ' . $folder_label . ' at ' . $server . '</h3>
				<table class="synch_status wp-issues-crm-stats">
					<colgroup>
						<col style="width:35%">
						<col style="width:65%">
					</colgroup>
					<tbody>
						<tr><th></th><th class="wic-statistic-text">Note: Stats are last started synch if Gmail (and may be partial), otherwise last ended synch.</th></tr>	
						<tr>
							<td class="wic-statistic-text">' . get_date_from_gmt( date( 'Y-m-d H:i:s', $result[0]->utc_time_stamp ), 'F j, Y H:i:s' ) . '</td>
							<td class="wic-statistic-text">when last completed run started</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->full_folder_string . '</td>
							<td class="wic-statistic-text">folder synchronized (full access string)</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->num_msg  . '</td>
							<td class="wic-statistic-text">messages in folder on server at start of synchronization</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->count_on_temp_table  . '</td>
							<td class="wic-statistic-text">real time count of temporary image of Gmail API or ActiveSync inbox as built</td>
						</tr>						<tr>
							<td class="wic-statistic-text">' . $result[0]->count_new . '</td>
							<td class="wic-statistic-text">new message IDs used by server and tentatively stored on image (may exceed actual message count)</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->stamped_synch_count  . '</td>
							<td class="wic-statistic-text">new message IDs net of any probable deletes</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->image_extra_uids  . '</td>
							<td class="wic-statistic-text">approximate number of deletes to mirror from server to image (image count plus new ID count less server count)</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->count_image_mark_deleted  . '</td>
							<td class="wic-statistic-text">messages mark deleted on image by synch process (maybe low if 2d process ran at same time)</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->count_deleted . '</td>
							<td class="wic-statistic-text">messages moved from folder by this run (on image and on server)</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->pending_move_delete_count . '</td>
							<td class="wic-statistic-text">messages still found on image as marked to be moved from folder after this run</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->synch_count . '</td>
							<td class="wic-statistic-text">total messages on image (and not marked deleted) at end of run</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->incomplete_record_count  . '</td>
							<td class="wic-statistic-text">unparsed messages in image at end of run (snapshot)</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->check_connection_time  . '</td>
							<td class="wic-statistic-text">seconds required to establish connection</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->synch_fetch_time  . '</td>
							<td class="wic-statistic-text">seconds required to insert new message header records</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->do_deletes_time  . '</td>
							<td class="wic-statistic-text">seconds required to identify and delete server-deleted messages</td>
						</tr>
						<tr>
							<td class="wic-statistic-text">' . $result[0]->process_moves_time  . '</td>
							<td class="wic-statistic-text">seconds required to process folder moves for archived messages</td>
						</tr>
					</tbody>
				</table>';
			} else {
				$output = "<h3>No synch log entries yet or log purged by Manage Storage.</h3>";
			}
		
	

		return array ( 'response_code' => true, 'output' => $output ); 
	
	}

	// GMAIL: Complete rewrite with new API -- this function just calls new
	public static function synch_inbox( $online = false ) { 
	
		/*
		* The approach taken here is based on the following findings:
		* -- Some web hosts may insist on a two minute (or shorter) maximum actual run time and enforce this from outside php
		* -- IMAP calls are infinitely slower than database calls, so want to minimize IMAP calls to get through most of job
		* -- seq_no based IMAP calls are much faster than UID based calls
		* -- fetch all headers is slow and potentially memory intensive in larger inbox and is to be avoided: use inbox math
		*
		* All components of process all interruptible and rerunnable (start with actual status of inbox and image).
		* They also can handle simultaneity (may not complete, but will not do bad inserts or mark-deletes).
		* They will not loop on error conditions -- all loops have hard limits and/or escapes if external conditions have changed.
		* -- Single insert step adds all UIDs used since last poll (even if deleted before poll)
		*	+ Always adds upwards, so interruptible; does dupchecking (unique key), so can support simultaneous
		*	+ Ignores all flags, so only purge can reset 
		*		Purge in middle of insert loop could result in some messages missing
		* -- Delete step is built to handle deletes happening by another client and simultaneous adds --
		*	None of the delete process can invalidly mark delete a UID regardless of seq_no changing
		*	+ Three mark-delete mechanisms safely delete although based on indeterminate UIDs
		*		- deletion of high UIDs ( synch_inbox when $imap_max_uid < $folder_max_uid )
		*		- deletion of all UIDs in zero message case -- safe based on SEQUENCE_ASSUMPTION (mark_delete_uids_in_folder)
		*		- deletion of UIDS in gap -- safe based on self checking ( mark_delete_uids_in_gap )
		*	+ Two other mark-delete mechanisms run by known UID so are safe from seq_no changes
		*		- deletion after move
		*		- deletion if not found in parse_messages
		*	Worst case, the second submitted process keeps repeating bad hunt until it errors out (long before time out)
		* -- Move step handles each message transaction individually; if dup attempt, no action
		*
		* Any synch run is about a folder and folder is set once for the whole run, so sequential runs matter only as against a single folder
		* Utc_time_stamp ($synch_fetch_time) has no processing role -- only used for analysis/reporting purposes.
		*
		* Online resynch doesn't gain user much since running every two minutes and will be confusing since parsing is independent -- don't allow 
		*/
		
		// set time limit for job ( in later versions get as setting )
		$max_allowed_time = 115; // two minutes less some Wordpress set up time to get here
		if ( ! defined( 'WEB_JOB_BLOG_ID' ) ) {
			ini_set( 'max_execution_time', $max_allowed_time + 10 );
		}
		$move_time = 20; // time to be reserved at end of job for processing any moves back to server
  
		// check connection to inbox folder and get processed folder string
		$check_connection_time = microtime (true);
		$connection_response = WIC_Entity_Email_Connect::check_connection();
		if ( false === $connection_response['response_code'] ) {
			return ( $connection_response );
		}
		
		// after check connection, if no error, this global is the inbox stream resource
		global $wp_issues_crm_IMAP_stream;

		// get the full folder string
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$folder = $wic_plugin_options['imap_inbox'];
		$is_office365 = (false !== stripos( $folder, 'office365') );

		// define the inbox table
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		// get message count on server ( use this throughout -- ignore later added messages )
		$synch_fetch_time = microtime(true);
		$num_msg = @imap_num_msg ( $wp_issues_crm_IMAP_stream );
		if ( false === $num_msg || ( !$num_msg && $is_office365 )) {
			$error =  'WIC_Entity_Email_Inbox_Synch::synch_inbox was unable to get message count. Likely connection lost. ' .
					  'In Office 365, this may reflect a zero count truly or falsely returned.  Synch will proceed when Office 365 says mailbox has at least one message.';
			return array ( 'response_code' => false, 'output' => $error );
		}

		/*
		* get absolute maximum UID from data base (irregardless of whether marked as deleted or to_be_moved)
		* SEQUENCE_ASSUMPTION: validity of max in later steps presumes safely that later starting process 
		* (getting higher imap_num_msg and/or imap_max_uid) will be unable to add higher before this one snaps this database count
		*
		* need this even if $num_msg == 0 to bound mark_deletions in the 0 msg case
		*/
		$sql = $wpdb->prepare ( "SELECT max(folder_uid) as folder_max_uid FROM $inbox_table where full_folder_string = %s", array ( $folder ) ); 
		// note not checking if deleted -- highest is whether or not deleted
		$result = $wpdb->get_results ( $sql );
		$folder_max_uid = $result ? $result[0]->folder_max_uid : 0; // substitute 0 for a falsy return

		/*
		*
		* add messages from highest uid on db + 1 through highest on server
		* + if simultaneous adds and deletes cause $num_msg to point later uid, no problem -- go ahead and add)
		* + if deletes cause $num_msg to point beyond end, die on error
		* + if adds after $num_msg checked, no problem -- ignore
		* step is fully interruptible -- if don't complete adds, will start in right place next time
		* do no time checking (should be very fast anyway).
		*
		*/
		$count_added = 0; // define even if not entering this branch
		$early_deletes_for_high_folder_uid = 0;		
		if ( $num_msg > 0 ) {
			/* 
			* imap_max_uid is based on initial num_msg -- max could vary as follows due to activity between getting num_msg and imap_max_uid
			* -- if messages added (can only be above) after setting imap_max_uid will get later
			* -- if messages deleted (can only care below) generate error
			* -- if both added and deleted and points to a valid uid, it as if fresh
			*/ 
			$imap_max_uid = @imap_uid( $wp_issues_crm_IMAP_stream, $num_msg ); 
			// if can't get top uid		
			if ( ! $imap_max_uid ) {
				$error =  'WIC_Entity_Email_Inbox_Synch::synch_inbox was unable to get top message. Likely collision or connection lost.';
				return array ( 'response_code' => false, 'output' => $error );
			}
			
			// if synching inbox from scratch or from purged inbox should start from bottom of range, not from all time, in adding uids 
			if ( 0 == $folder_max_uid ) {
				$folder_max_uid = @imap_uid( $wp_issues_crm_IMAP_stream, 1 );
				if ( ! $folder_max_uid ) {
					$error =  'WIC_Entity_Email_Inbox_Synch::synch_inbox was unable to get first message. Likely collision or connection lost.';
					return array ( 'response_code' => false, 'output' => $error );
				} 
				$folder_max_uid = $folder_max_uid - 1; // so that insert loop starts with first0
			}
			/* 
			*
			* reconcile maxes between server and image so can search for deletes between integer seq_nos starting from 0
			* after this step, either 0 msgs on server or top uid on server == top uid on image: $folder_max_uid = $imap_max_uid
			*
			*/
			// counter for deletes occuring in this sequence
			$early_deletes_for_high_folder_uid = 0;
			// add new UIDs
			if ( $folder_max_uid < $imap_max_uid ) {
				/* 
				* load *probable* UID's that haven't been seen before
				* note that it is possible that adding non-existent UIDs at this step (e.g. if some already deleted by another client or by an server-side rule)
				* but often will be exactly right and efficient delete strategy makes this a reasonable approach
				*/
				for ( $i = $folder_max_uid + 1; $i < $imap_max_uid + 1; $i++ ) {
					$sql = "INSERT INTO $inbox_table ( full_folder_string, folder_uid, utc_time_stamp ) VALUES ( '$folder', $i, $synch_fetch_time )";
					// duplicate key insert is not really an error
					$wpdb->hide_errors();
					if ( $wpdb->query ( $sql ) ) {
						$count_added++;
					} else {
						// unlikely to ever occur; recent tests of UID maxes should prevent dups entirely (but folder/uid key is unique as belt and suspenders
						WIC_Entity_Email_Cron::log_mail ( "Unsuccessful UID add in Inbox Synch -- likely simultaneous submission collision for uid: $i." );
					}
					$wpdb->show_errors();
				}
			// delete high uids
			} elseif ( $imap_max_uid < $folder_max_uid ) {
				/*
				* since folder_max_uid reflects previous max add, any later running process will be adding higher uid's than folder_max_uid
				*	-- this mark-delete step is simultaneous safe
				*/
				$sql = "UPDATE $inbox_table SET no_longer_in_server_folder = 1
						WHERE
							full_folder_string = '$folder' AND
							no_longer_in_server_folder = 0 AND
							folder_uid <= $folder_max_uid AND
							folder_uid > $imap_max_uid
							";
				$early_deletes_for_high_folder_uid = $wpdb->query ( $sql );
				
				if ( $early_deletes_for_high_folder_uid > 5 ) {
					WIC_Entity_Email_Cron::log_mail ( "$early_deletes_for_high_folder_uid messages deleted from $inbox_table on high_folder_uid condition with high folder uid = $folder_max_uid and low imap uid = $imap_max_uid  -- this is a diagnostic trace and not necessarily an error.");				
				}
			}
		} // close num_msg > 0
		/*
		* now mark-deleting UIDs on image that have been moved or deleted from IMAP folder
		* 
		* work up from seq_no 1 on server comparing count of UIDs on db below the UID for the current seq_no 
		*	+ count should equal seq_no - 1
		*	+ if not mark delete UIDs satisfying  imap_uid(seq_no - 1) < UID < imap_uid(seq_no)	
		*	+ have already made it true that last seq_no corresponds to top UID on folder
		*
		* find gaps using strategy dictated by experience in current delete run
		*	+ note, do know density of missing UIDs, but this says nothing about where gaps are or how many gaps there are
		*		- density = ( count(UID) where UID > imap_uid(seq_no) )  /  ( msg_no - seq_no )
		*		- BUT all missing could be between two consecutive seq_nos or interleaved between every pair of seq_nos
		*	+ do know number of steps between previous gaps in current run and number of steps to last gap found
		*		-- people skip around inbox, but density of gaps may look like consistent in a neighborhood
		*		-- SO, use number of steps to find current gap as predictor of number of steps to next
		*   	-- certain number of imap_uid calls needed to find next gap by territory dividing hunt is computed from log2 ( msg_no ) 
		*				(starting from scratch on hunts, allows reset of gap search if low deletes );
		*		-- do the hunt if log2 ( msg_no ) < # of steps to last gap
		*		-- also do the hunt if low density of deletes ahead
		*
		* step is fully interruptible, never does deletes unless verified that seq_no map is has not changed at wrong moment
		* always finds first, regardless of previous delete activity, so if shifting seq_no's made one run miss a delete will catch next run
		*
		* Special cases
		*	0 messages on mailbox: mark delete all in folder
		*	1 message in mailbox: go to normal stepwise ( will do one step );
		*
		*/
		$do_deletes_time = microtime( true );
		// set up ceiling for hunt decision ( if zero or one messages, set to 1 );
		$imap_calls_in_hunt = $num_msg > 1 ? ceil ( log ( $num_msg, 2) ) : 1; // this approximate +/- 1
		// four control variables are maintained in looping:
		// (1) number of UIDs remaining to be deleted ( should always be >= 0 at this stage )
		// 		note -- have added all uids from server and max on server = max on image
		//      note -- if an earlier submitted process is moving UIDs and marking them deleted, this can snapshot as lower than true or negative
		//			if image count is more current than num_msg either b/c of sequence in this script or because of delayed num_msg update
		//			 . . . consequence is that mirroring of delete from server to image may be delayed to next cycle on some messages
		$extra_uid_count = self::get_count_uid_in_folder( $folder ) - $num_msg;
		$extra_uid_count_original = $extra_uid_count; // for diagnostics
		// (2) search pointer = first seq_no for which #uids on folder might be < imap_uid(seq_no) = imap (seq_no -1 ) 
		$imap_inbox_pointer = 0; // will be incremented before testing -- want to test stepwise on first step (old msgs likely deleted);
		// (3) step counter to sense neighborhood density in inbox
		$steps_to_last_gap = 0;  // always start stepwise
		// (4) hunt counter to sense simultaneous run collisions or lost connection
		$bad_hunt_counter = 0;
		// begin and continue hunt while have extra uids and have time
		if ( $extra_uid_count ) {
			// if no messages on server, then should mark delete all on db
			if ( 0 === $num_msg )	{ 
				// this mark_deletion is safe based on SEQUENCE_ASSUMPTION
				// also, note that $folder_max_uid is first computed outside condition of $num_msg > 0; 
				//  . . . refinements in that condition, not applicable in this branch.
				$zero_num_deletes = self::mark_delete_uids_in_folder ( $folder, $folder_max_uid );
				$extra_uid_count = $extra_uid_count - $zero_num_deletes;
				if ( $zero_num_deletes > 5 ) {
					WIC_Entity_Email_Cron::log_mail ( "$zero_num_deletes messages deleted from $inbox_table on num_msg = 0 condition -- this is a diagnostic trace and not necessarily an error.");				
				}
			} else {	
			// otherwise, synchronize delete activity
				while ( 
					$extra_uid_count > 0 && 
					( time() < ( $check_connection_time + $max_allowed_time - $move_time ) ) &&
					$bad_hunt_counter < 3
					) {
					// do the hunt strategy instead of just stepwise checking only after first step and only under either of two conditions--
					if (
						// not just starting out AND
						0 < $imap_inbox_pointer && 
							(
								// going step by step has recently (in this folder run) been slower than doing hunt OR
								$steps_to_last_gap > $imap_calls_in_hunt ||
								// density of deletes ahead is low
								( ( $num_msg - $imap_inbox_pointer ) / $extra_uid_count ) > $imap_calls_in_hunt
							)
						) { 
						$found_gap = self::hunt_first_seq_no_that_skips( $folder, $num_msg );
						if ( $found_gap ) {
							$hunt_deleted_count = self::mark_delete_uids_in_gap ( $folder, $found_gap );
							if ( $hunt_deleted_count ) {
								$extra_uid_count = $extra_uid_count - $hunt_deleted_count;
								// track steps to last gap -- may switch back to stepwise checking
								$steps_to_last_gap = max ( $found_gap - $imap_inbox_pointer, 1 );
								$imap_inbox_pointer = $found_gap;
								$bad_hunt_counter = 0;
							} else {
								$bad_hunt_counter++;
							}
						} else {
						// if not found gap (or as in previous line, found non-gap), hunt degenerated -- just go around again but don't try more than 3 times
							$bad_hunt_counter++;
						}
					} else { 
					// otherwise, go step by step looking for deletions 
						$steps_to_last_gap = 0;
						// will always enter loop since min $imap_calls_in_hunt = 1
						while ( $steps_to_last_gap <= $imap_calls_in_hunt ) {
							// move imap pointer to next message
							$imap_inbox_pointer++;
							// count the step
							$steps_to_last_gap++;
							// if have moved pointer beyond end of retrieved inbox quit completely
							if ( $imap_inbox_pointer > $num_msg ) {
								break (2);
							} 
							// get next uid
							$next_uid = @imap_uid( $wp_issues_crm_IMAP_stream, $imap_inbox_pointer );
							// if don't point to a valid uid, overran end of inbox which moved in due to deletions by another client; 
							// in this case, by definition, there are more deletes to do, but need to reset pointer by hunting
							// leave the inner while loop and come back through the outer to the hunt
							if ( ! $next_uid ) {
								$steps_to_last_gap = $imap_calls_in_hunt + 1; 
								break;
							}
							// for valid next uid test for missing uids
							if ( self::get_count_uid_in_folder ( $folder, $next_uid ) > $imap_inbox_pointer - 1 ) {
								$deleted = self::mark_delete_uids_in_gap ( $folder, $imap_inbox_pointer ); 
								if ( $deleted )  {
									$extra_uid_count = $extra_uid_count - $deleted;
								} else {
									// if none were deleted, then seq_nos shifted due to other client deletions
									// set up step count so that will automatically go to full hunt on next loop
									// note that other client deletions could leave pointer at another valid gap . . .
									// but unless all future steps have gaps, will hit this branch at some point and reset to hunt and get back on track
									// will pick up the missed deletes as new on a future run regardless
									$steps_to_last_gap = $imap_calls_in_hunt + 1; 
								}
								break;
							}
						}
					}
				} 
			}			
		}
		/* 
		* now turning to connection for move/expunge		
		* fully rerunnable since process is rerunnable if fail
		*  -- don't mark deleted until actually done so
		* reserved time to get here, but don't care if get killed
		*/
		// get uid's to be deleted
		$process_moves_time = microtime(true);
		$sql = "
				SELECT folder_uid FROM $inbox_table 
				WHERE 
					to_be_moved_on_server = 1 AND 
					no_longer_in_server_folder = 0 AND 
					full_folder_string = '$folder'
				"
				;
		$uids_to_be_deleted = $wpdb->get_results ( $sql );
		$count_to_be_deleted = count( $uids_to_be_deleted );
		$count_deleted = 0;
		// for each pending deletion, move, expunge and mark deleted
		// note about timing in this loop:  move is roughly 10x slower than expunge. Expunge is 40ms on gmail.
		// do the expunge in the loop for integrity
		if ( $uids_to_be_deleted ) {
			// verify existence of move to folder and create it if needed (false return could just be a connection error, so process anyway)
			$check_create_result = WIC_Entity_Email_Connect::check_create_processed_folder();
			if ( false === $check_create_result['response_code'] )  {
				WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_Inbox_Synch::synch_inbox was unable to open second connection to verify/create processed folder.
					Error was: " .  $check_create_result['output'] . ".  
					If this error condition repeats, manually create a folder named " . WIC_Entity_Email_Connect::PROCESSED_FOLDER . 
					"on your server at the same tree level as the INBOX (or within the INBOX depending on server)." );
			} else {
				// loop through and do moves
				foreach ( $uids_to_be_deleted as $to_be_deleted ) {
					// check time -- OK to die, but would prefer to get to final diagnostics
					if ( time() > ( $check_connection_time + $max_allowed_time - 2 ) ) {
						break;
					}
					$current_uid = $to_be_deleted->folder_uid;
					if ( imap_mail_move (
							$wp_issues_crm_IMAP_stream, 
							$current_uid,
							$check_create_result['output'],
							CP_UID
							) ){ 
						imap_expunge( $wp_issues_crm_IMAP_stream );
						// mark it as deleted in the inbox image ( leave the to_be_moved flag set )
						$sql = "UPDATE $inbox_table SET no_longer_in_server_folder = 1 WHERE full_folder_string = '$folder' AND folder_uid = $current_uid";
						$wpdb->query ( $sql );
						$count_deleted++;						
					}
				}
			}
		}
		// close connection 
		$imap_close_time = microtime(true);
		imap_close ( $wp_issues_crm_IMAP_stream );
		
		// 



		/*
		*
		* do diagnostics
		*
		*/
		$result = self::inbox_synch_status ( $folder, $synch_fetch_time );
		$error_types = '';
		$error_types = $result->added_with_this_timestamp != $count_added  > 0 ? ( $error_types . ' ' . 'Record add failures for timestamp.' ) : $error_types;

		// flag error
		if ( $error_types ) {
			 WIC_Entity_Email_Cron::log_mail ( "Inbox synch at $synch_fetch_time had errors: $error_types" );
		} 
		
		// always log result
		$synch_log_table = $wpdb->prefix . 'wic_inbox_synch_log';
		$sql = $wpdb->prepare(
			"INSERT into $synch_log_table
				( 
				full_folder_string,
				utc_time_stamp, 
				num_msg,
				count_new,
				count_deleted,
				count_to_be_deleted,
				image_extra_uids,
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
				(%s,%f,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%f,%f,%f,%f)",
				array(
					$folder, 
					$synch_fetch_time,
					$num_msg,
					$count_added,   // uids inserted, possibly later deleted
					$count_deleted, // count of pending/move deletes accomplished from db to server
					$count_to_be_deleted, // count of pending/move deletes to be accomplished
					$extra_uid_count_original + $early_deletes_for_high_folder_uid,
					$extra_uid_count_original - $extra_uid_count + $early_deletes_for_high_folder_uid, // mark deleted by this run
					$result->synch_count, // added by this run but not deleted on db (yet)
					$result->incomplete_record_count, // all not deleted
					$result->pending_move_delete_count, // but for time diff should be same as count to_be_deleted
					$result->stamped_synch_count, // net new messages
					$result->stamped_incomplete_record_count, // incomplete with this time stamp
					$result->added_with_this_timestamp, // should always equal count added
					$synch_fetch_time - $check_connection_time,
					$do_deletes_time - $synch_fetch_time,
					$process_moves_time - $do_deletes_time,
					$imap_close_time - $process_moves_time,					 
				)
			);
		$wpdb->query( $sql );
		
		// return supports possibility of opposite result in online environment; in background, no checking
		WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Inbox_Synch::synch_inbox -- normal completion' );
		return array ( 'response_code' => true, 'output' => 'Successful synch run.' );
	
	}

	// GMAIL: Complete rewrite with new API -- this function not used
	private static function hunt_first_seq_no_that_skips ( $folder, $num_msg ) {
		/* 
		* this function uses strategy of fence closing to hunt for next server folder seq_no for which
		* there is a UID extant on the db image that is missing between its UID and prior seq_no's UID
		*
		* note: always start with fresh msg count in doing imap search because another client could delete before start of loop
		*
		* degenerate cases
		*	+ called when there is no gap -- will return num_msg as first break
		*	+ 0 messages -- returns 0 as high ( zero low )
		*	+ 1 message -- returns that uid as high ( zero low )
		*	+ msgs deleted while loop is ongoing effectively 
		*		if deleted below $low before test, shifting low high pointers to bracket higher uids
		*		-- if first gap remains within the fences, no problem, will still converge to it
		*		-- if first gap escapes below the fence, high will converge to low + 1 with no actual gap
		*		if deleted within fences before test, should converge anyway
		*		if deleted above high before test, will find converge to lower gap anyway.
		*/
		global $wp_issues_crm_IMAP_stream;
		// set top seq_no fence to actual message count but not beyond starting count
		// if new message count is 0 or false, will return 0 (which will trigger bad_hunt_count in main loop if repeated)
		$high = min( $num_msg, @imap_num_msg ( $wp_issues_crm_IMAP_stream ) );
		// start bottom seq_no fence at zero
		$low  = 0;
		
		// bringing the fences together until consecutive with a gap
		while ( $high - $low > 1 ) {
			// take the mid point of the seq_nos
			$test = ceil( ( $high + $low ) / 2 );
			// check the corresponding uid
			$test_uid = @imap_uid( $wp_issues_crm_IMAP_stream, $test );
			// if uid is bad, reset and break loop 
			if ( ! $test_uid ) {
				$high = 0;
				$low = 0;
				break;
			}
			// if inbox is synched to this seq_no, then count below uid should equal previous seq_no
			$count_folder_uids = self::get_count_uid_in_folder( $folder, $test_uid );
			if ( $count_folder_uids == ( $test - 1 ) ) {
				$low = $test;
			} elseif ( $count_folder_uids > ( $test - 1 ) ) {
				$high = $test;
			} else {
				// another process is deleting UIDS simultaneously! 
				// if was purge, then this will repeat three times in main loop and die
				$high = 0;
				$low = 0;
				break;
			}
		}
		// barring degenerate cases, this is first seq_no below which there are extra uids
		// need to test for degenerate cases before doing delete anyway
		return $high;
	}

	// GMAIL: Complete rewrite with new API -- this function not used
	private static function get_count_uid_in_folder( $folder, $high = 999999999999 ) {
		// counts messages (not marked as not on server) that have uid strictly less than $high
		global $wp_issues_crm_IMAP_stream;
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';		

		// get minimum uid on db that has not been deleted
		$sql = $wpdb->prepare ( "SELECT count(folder_uid) as folder_count FROM $inbox_table 
			WHERE 
				full_folder_string = %s 
				AND no_longer_in_server_folder = 0 
				AND folder_uid < $high", array ( $folder ) ); 
		// note checking if deleted -- only want those that have not been deleted already
		$result = $wpdb->get_results ( $sql );
		return( $result ? $result[0]->folder_count : 0 ); // substitute 0 for a falsy return
	
	}

	// GMAIL: Complete rewrite with new API -- this function not used
	// mark delete uids between passed and prior seq_no if any
	// safe if seq_no's shift (either immediately prior to or during function call)
	private static function mark_delete_uids_in_gap ( $folder, $seq_no) {

		if ( ! $folder ) {
			return 0;
		}

		global $wp_issues_crm_IMAP_stream;

		// get uid for current pointer
		$high = @imap_uid ( $wp_issues_crm_IMAP_stream, $seq_no );
		if ( ! $high ) {
			return 0;
		}

		// get uid for immediate prior
		if ( 1 == $seq_no ) {
			$low = 0;
		} else {
			$low = @imap_uid ( $wp_issues_crm_IMAP_stream, $seq_no - 1 ); 
			if ( ! $low ) {
				return 0;
			}
		}

		// note that got low after high, so that if low but not high pointer was moved by deletions below it, error condition created
		// -- $low would point to at least one uid higher, so zero
		// also testing for a no gap case -- if both pointers moved together, go ahead and do delete iff there exists a gap
		if ( ( $high - $low  ) <= 1 ) {
			return 0;
		}
		// note -- this does not delete the low or high values (open interval)
		global $wp_issues_crm_IMAP_stream;
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';		
	
		$sql = $wpdb->prepare ( "UPDATE $inbox_table SET no_longer_in_server_folder = 1 
			WHERE 
				full_folder_string = %s 
				AND no_longer_in_server_folder = 0 
				AND folder_uid > $low 
				AND folder_uid < $high
				", 
			array ( $folder ) ); 
		// return count mark deleted
		$gap_deleted_count = $wpdb->query ( $sql );
		if ( $gap_deleted_count > 5 ) {
			WIC_Entity_Email_Cron::log_mail ( "$gap_deleted_count messages deleted from $inbox_table on gap condition with high $high and low $low on seq_no $seq_no -- this is a diagnostic trace and not necessarily an error.");				
		}	
		return $gap_deleted_count;
	
	}

	// GMAIL: Complete rewrite with new API -- this function not used
	// limit to known max to avoid deleting adds by another process
	private static function mark_delete_uids_in_folder ( $folder, $folder_max_uid ) {

		if ( ! $folder ) {
			return 0;
		}

		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';		
	
		$sql = $wpdb->prepare ( "UPDATE $inbox_table SET no_longer_in_server_folder = 1 
			WHERE 
				full_folder_string = %s 
				AND no_longer_in_server_folder = 0 
				AND folder_uid < ( $folder_max_uid + 1 )
				", 
			array ( $folder ) ); 
		// return count mark deleted	
		return ( $wpdb->query ( $sql ) );

	}
	// Gmail -- no change -- calling function supplies folder
	public static function inbox_synch_status( $folder, $timestamp ) {
		// can pass folder or timestamp as false
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$sql_timestamp = $timestamp ? 
			", SUM(if(no_longer_in_server_folder = 0 AND utc_time_stamp = $timestamp, 1, 0)) as stamped_synch_count,
			SUM(if(no_longer_in_server_folder = 0 AND serialized_email_object = '' AND utc_time_stamp <= $timestamp,1,0)) as stamped_incomplete_record_count,
			SUM(if(utc_time_stamp = $timestamp,1,0)) as added_with_this_timestamp"
			: '';
		$sql_folder = $folder ? "WHERE full_folder_string = '$folder'" : "GROUP BY full_folder_string";
		$sql = "
			SELECT
			COUNT(folder_uid) as image_count,
			SUM(if(no_longer_in_server_folder = 0, 1, 0)) as synch_count,
			SUM(if(no_longer_in_server_folder = 0 AND serialized_email_object = '',1,0)) as incomplete_record_count,
			SUM(if(no_longer_in_server_folder = 0 AND to_be_moved_on_server = 1,1,0)) as pending_move_delete_count
			$sql_timestamp
			FROM $inbox_table 
			$sql_folder
			"; 
		$result = $wpdb->get_results ( $sql );
		return ( $folder ? $result[0] : $result );		
	
	}

	// Gmail: get folder via OAUTH
	public static function purge_inbox() {
		/* 
		* note: this process could run in the middle of a synch process
		* possible cases include -
		*	after get msg count, but before get database max -- OK
		*	 . . . , but before data base inserts -- not OK won't load low uids, can fix with retry purge (will be obvious; warn);
		*    . . . , but before count extra uids -- OK, will not attempt any deletes  
		*	 . . . , but before deletes complete -- OK, will be out of synch before next run
		*		+ if in step mode, will continue in step mode until shifts to hunt mode
		*		+ if in hunt mode, will loop three times and quit on bad hunt counter
		*	 . . . , but before moves complete -- not OK will lose moves
		*
		* allow possibility of purging inbox for blank folder setting -- might have saved blank before 3.2.1
		*
		* if using active sync or gmail, the sequencing is either before or after the lock of $inbox table
		*	if before -- perfect, the big join happens on a fresh box and all are added
		*		the new sync stamp will occur after this reset
		*	if after  -- also OK, the subsequent updates in the sync process will fail/do nothing because this process deleted the records, 
		*		but that is not error; nothing is added or missing as a result  
		*/
		
		global $wpdb;
		$folder = WIC_Entity_Email_Account::get_folder();
		// begin by unsetting sync marker so that next sync will be full -- these folder names are full email address, so only one will apply
		delete_option ( WIC_Entity_Email_ActiveSync_Synch::SYNC_POINT_PREFIX . $folder );
		delete_option ( 'wp-issues-crm-gmail-api-start-sync-for-' . $folder ); 

		// table names
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$md5_table = $wpdb->prefix . 'wic_inbox_md5';
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref'; // this table includes all attachments, not just inbox attachments
		$activity_table = $wpdb->prefix . 'wic_activity';
	
		// first unset links from activity records
		$sql = $wpdb->prepare ( 
			"UPDATE $activity_table a 
			LEFT JOIN $inbox_table i ON i.ID = a.related_inbox_image_record 
			SET a.related_inbox_image_record = 0
			WHERE full_folder_string = %s", array ( $folder ) );
		$result = $wpdb->query ( $sql );

		// purge image, attachments xrf and md5 coding of image text			
		$sql = $wpdb->prepare ( 
			"DELETE m, m5, a 
			FROM $inbox_table m LEFT JOIN 
				$md5_table m5 on m5.inbox_message_id = m.ID LEFT JOIN
				$attachments_xref_table a on a.message_id = m.ID
			WHERE full_folder_string = %s", array ( $folder ) ); 		
		$result = $wpdb->query ( $sql );
		
		// purge orphan attachments ( not used by other email messages )
		WIC_Entity_Manage_Storage::purge_orphan_attachments();
		
		WIC_Entity_Email_Cron::log_mail ( "WP Issues CRM -- inbox purge completed.  WIC_Entity_Email_Inbox_Synch:purge_inbox." );

		return WIC_Entity_Email_Inbox_Synch::load_staged_inbox ( '', '' );  

	}

}