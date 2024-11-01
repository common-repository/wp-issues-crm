<?php
/*
*
*	wic-entity-email-inbox-parse.php
*
*   GMAIL: notes for each function reflect 12/2018 mods to convert to Gmail API -- Review Complete
*
*/
Class WIC_Entity_Email_Inbox_Parse {

	// this function superseded entirely when using GMail or activesync
	public static function parse_messages() {
		
		WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Inbox_Parse:parse_messages -- starting.' );

		// set time limit for job ( in later versions get as setting )
		$max_allowed_time = 110; // two minutes less some Wordpress set up time to get here and some time to complete parse
		// if defined( 'WEB_JOB_BLOG_ID' ), then running continuous task
		if( !defined( 'WEB_JOB_BLOG_ID' ) ) {
			ini_set( 'max_execution_time', $max_allowed_time + 10 );
		}
  		$cut_off_time = time() + $max_allowed_time;

		// get the full folder string
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$folder = isset ( $wic_plugin_options['imap_inbox'] ) ? $wic_plugin_options['imap_inbox'] : '';	

		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		$max_packet_size = self::get_max_packet_size();

		// define sql to get next blank message 
		// ordering just reduces probability of unnecessary parsing of to be deleted msgs
		$sql = "SELECT ID, folder_uid FROM $inbox_table 
				WHERE 
					full_folder_string = '$folder' AND
					no_longer_in_server_folder = 0 AND
					serialized_email_object = ''
				ORDER BY folder_uid ASC
				LIMIT 0,1";

		// do we have work to do? if so, open connection; if not, quit;
		if ( $wpdb->get_results ( $sql ) ) {
			// check connection to inbox folder and get processed folder string
			$connection_response = WIC_Entity_Email_Connect::check_connection();
			if ( false === $connection_response['response_code'] ) {
				WIC_Entity_Email_Cron::log_mail ( 'Failed to connect on WIC_Entity_Email_Inbox_Parse::parse_messages -- error was: ' . $connection_response['output'] );
				return true;
			}
			// after check connection, if no error, this global is the inbox stream resource
			global $wp_issues_crm_IMAP_stream;
		} else {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Inbox_Parse:parse_messages found no messages to parse.' );
			return true; // end current processing			
		}

		// set up counters
		$deleted_messages = 0;
		$parsed_messages = 0;
		$errors = 0;	
		// look for blank serialized_email_object and parse them for as long as allowed (and there are any)
		while ( 
			time() < $cut_off_time &&
			$result = $wpdb->get_results ( $sql )
			) {
			$current_uid = $result[0]->folder_uid; 
			$ID = $result[0]->ID;
			$email_object = new WIC_DB_Email_Message_Object;
			$email_object->build_from_stream_uid ( $ID, $wp_issues_crm_IMAP_stream, $current_uid, $max_packet_size );
			// if uid has apparently been deleted while parse in progress mark it as such
			//  (but first check to make sure it was not a connection error)
			//  note that since working with uid's, no possibility of seq shift error
			//  note that deleting bad uid outside main delete process cannot conflict because 
			// 	   main UID process only deletes by range -- will simply show as not deleted if attempted
			//	   if throws off delete count, will exit loop by too many hunts or step beyond or time
			if ( ! $email_object->uid_still_valid ) {
				if ( false === imap_num_msg( $wp_issues_crm_IMAP_stream ) ) {
					WIC_Entity_Email_Cron::log_mail ( "Apparently lost connection in WIC_Entity_Email_Inbox_Parse::parse_messages." );
					return true;
				} else {
					$sql_delete = "UPDATE $inbox_table SET no_longer_in_server_folder = 1 WHERE folder_uid = $current_uid";
					$wpdb->query ( $sql_delete );
					$deleted_messages++;
					continue;
				}
			}
			
			if ( self::save_mail_object  ( $current_uid, $email_object, $folder, $ID, $max_packet_size  )  ) {
				$parsed_messages++;
			} else {
				$errors++;
			}
			
			// redefine query to limit to next UID -- preventing looping on update failure (database gone away or lost connection)
			$sql = "SELECT ID, folder_uid FROM $inbox_table 
				WHERE 
					full_folder_string = '$folder' AND
					no_longer_in_server_folder = 0 AND
					serialized_email_object = '' AND
					folder_uid > $current_uid
				ORDER BY folder_uid ASC
				LIMIT 0,1";
			
		}
		// after message parsing is complete compile conversation threads to identify latest element in them
		self::compile_thread_date_times( $folder );
		// log completion in debug mode
		WIC_Entity_Email_Cron::log_mail ( "WP Issues CRM -- message parser completed, parsing $parsed_messages messages and deleting $deleted_messages messages; $errors messages had update errors.  WIC_Entity_Email_Inbox_Parse:parse_messages." );
		// close connection
		imap_close ( $wp_issues_crm_IMAP_stream );
		 
		return true; 
	}

	// used by gmail
	public static function save_mail_object (  $current_uid, $email_object, $folder, $ID, $max_packet_size  ) {
		global $wpdb;
		/*
		* protect against oversized messages (should be exceedingly rare)
		*/
		$sql_update = self::prepare_message_update_sql ( $email_object, $folder, $current_uid );
		if ( strlen ( $sql_update ) > $max_packet_size ) { 
			$email_object->raw_html_body = 
				"<h3>Message size (plus overhead) exceeds system max_packet_size ( $max_packet_size ) -- unable to diplay.</h3>"; 
			$sql_update = self::prepare_message_update_sql ( $email_object, $folder, $current_uid );
		}

		$result = $wpdb->query ( $sql_update );
		// save md5 representation of the non_address text of the message
		if ( false !== $result ) {
			WIC_Entity_Email_MD5::save_md5_digest ( $email_object->sentence_md5_array, $ID );
			return true;
		} else {
			return false;
		}	
	
	}

	// no change
	public static function get_max_packet_size () {
		
		// allow for local max packet size
		if( defined('LOCAL_MAX_PACKET_SIZE') ) {
			return LOCAL_MAX_PACKET_SIZE;
		}
	
		global $wpdb;
		// determine maximum packet size
		$vars = $wpdb->get_results( " select VARIABLE_VALUE from information_schema.GLOBAL_VARIABLES where VARIABLE_NAME = 'max_allowed_packet' ");
		$max_packet_size = isset( $vars[0]->VARIABLE_VALUE ) ? $vars[0]->VARIABLE_VALUE : 'UNSET' ;
		// if this query worked as expected -- nonzero number -- use it, other wise use 1000000 (haven't seen lower; go much higher to 16M)
		if ( is_numeric ( $max_packet_size ) ) {
			$max_packet_size = $max_packet_size ? $max_packet_size : 1000000;
		} else {
			$max_packet_size = 1000000;
		}
		
		return $max_packet_size;
	}	
	
	// indirectly used by gmail
	private static function prepare_message_update_sql ( $email_object, $folder, $current_uid ) {

		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		// do md5 guess mapping
		$md5_map_result = WIC_Entity_Email_MD5::get_issue_and_pro_con_map_from_md5_v2( $email_object->sentence_md5_array );
		// do subject mapping
		$subject_line_map_result = WIC_Entity_Email_Subject::get_subject_line_mapping( $email_object->subject );

		// do constituent lookup
		$constituent_id = self::do_constituent_lookup( $email_object );
		
		// apply rules to label parse quality
		$parse_quality = self::evaluate_parse_quality( $email_object );
		
		// apply blocked email address filtering
		$filtered = WIC_Entity_Email_Block::apply_email_address_filter ( $email_object->from_email );

		// apply geography filtering, if any, do auto_reply if set (never set if not filtered) and carry is_my_constituent into final update
		$is_my_constituent = '';
		if ( ! $filtered ) {
			$filtered_constituent = self::apply_physical_address_filter ( $email_object, $constituent_id, $subject_line_map_result  );
			$filtered = $filtered_constituent['filtered'];
			$is_my_constituent = $filtered_constituent['is_my_constituent'];
		}

		// subject and category local filters -- may be defined in a local plugin -- gmail supplies own category, but local can override
		// https://developer.wordpress.org/reference/functions/apply_filters/
		$subject 	= 	apply_filters ( 'wp_issues_crm_local_subject_filter', $email_object->subject, $email_object );	
		// CATEGORY_GENERAL correponds to the base case non-gmail, no local filter tab selection in WIC_Entity_Email_Account::get_tabs
		if ( !$email_object->category ) {
			$email_object->category = 'CATEGORY_GENERAL';
		}

		// local category filter may or may not respect CATEGORY_TEAM
		$category   = 	apply_filters ( 'wp_issues_crm_local_category_filter', $email_object->category, $email_object, 'Y' == $is_my_constituent );	

		// prepare sql from message object 
		return $wpdb->prepare ( "
				UPDATE $inbox_table SET
					from_personal = %s,
					from_email = %s,
					from_domain = %s,	
					raw_date = %s,		 	
					email_date_time = %s,
					subject = %s,
					category = %s,
					snippet = %s,
					account_thread_id = %s,			
					activity_date = %s,
					guess_mapped_issue = %d,
					guess_mapped_issue_confidence = %d,
					guess_mapped_pro_con = %s,
					non_address_word_count = %s,
					mapped_issue = %s,
					mapped_pro_con = %s,
					serialized_email_object = %s,
					assigned_constituent = %d,
					parse_quality = %s,
					to_be_moved_on_server = %s,
					is_my_constituent_guess = %s
				WHERE full_folder_string = %s AND folder_uid = %d
				",
				array(
					$email_object->from_personal,
					$email_object->from_email,
					$email_object->from_domain,	
					$email_object->raw_date,		 	
					$email_object->email_date_time,		 	
					$subject,
					$category,	
					$email_object->snippet,	
					isset ( $email_object->account_thread_id ) ? $email_object->account_thread_id : '',	
					$email_object->activity_date,
					$md5_map_result['guess_mapped_issue'],
					$md5_map_result['guess_mapped_issue_confidence'],
					$md5_map_result['guess_mapped_pro_con'],
					$md5_map_result['non_address_word_count'],
					$subject_line_map_result ? $subject_line_map_result['mapped_issue'] : '',
					$subject_line_map_result ? $subject_line_map_result['mapped_pro_con'] : '',
					serialize ( $email_object ),
					$constituent_id,
					$parse_quality,
					$filtered,
					$is_my_constituent,
					$folder,
					$current_uid
				)							
			);

	}

	// indirectly used by gmail
	private static function do_constituent_lookup ( &$email_object ) {
		
		// passing only constituent lookup elements of array
		$parsed_message_keys = array ( 
				'email_address',
				'phone_number',
				'first_name',
				'middle_name',
				'last_name',
				'address_line',
				'city',
				'state',
				'zip',
				'activity_date'
		);	

		// push message parms onto key value array (all are set, although may be blank);
		$key_value_array = array();
		foreach ( $parsed_message_keys as $key ) {
			if (  $email_object->$key ) {
				$key_value_array[$key] = $email_object->$key;
			}
		}

		if ( ! $key_value_array ) {
			return 0;
		} else {
			$constituent_activity = new WIC_Interface_Transaction;
			$constituent_id = $constituent_activity->save_constituent_activity ( 
				$key_value_array, 
				array(), // no special key values 
				array(), // no special policies
				true, // unsanitized = true (so sanitize)
				array (
					'emailfn',	
					'email',
					'lnfnaddr',
					'lnfnaddr5',
					'lnfndob',
					'lnfnzip',
					'fnphone',
				),
				true // this is just a dry run
			);  
		}

		return $constituent_id;
		
	}
	
	// classify results from 6 worst to 1 best -- indirectly used by gmail
	private static function evaluate_parse_quality ( &$email_object ) {
		/*
		*	GROUPING OUTCOMES AS FOLLOWS ( RANKING )
		*		STRICT WITH NAME & ADDRESS-- have both name and address and good source for both email and name
		*		Outcomes 14,15
		*		MEDIUM WITH NAME & ADDRESS -- have both name and address, but less confirmation on email
		*		Outcomes 12, 13
		*		MEDIUM WITH NAME ONLY
		*		Outcome 4-7
		*		MEDIUM WITH NO NAME (EMAIL ONLY OR ADDRESS WITHOUT NAME)
		*		Outcome 8-11
		*		LOOSE
		*		Outcomes 0-3 (note that 1,2,3 are not valid outcomes)
		*/
		if ( '' == $email_object->email_address ) {
			return 6; // below lowest quality
		} elseif ( 4 > $email_object->outcome ) {
			return 5;
		} elseif ( 8 > $email_object->outcome ) {
			return 3;
		} elseif ( 12 > $email_object->outcome ) {
			return 4;
		} elseif ( 14 > $email_object->outcome ) {
			return 2;
		} // what is left? 14, 15
		return 1;
	}

	// indirectly used by gmail
	private static function  apply_physical_address_filter ( $email_object, $constituent_id, $subject_line_map_result ) {

		// set default response
		$response = array (
			'filtered' => 0,
			'is_my_constituent' => '',		
		);

		// get working parameters 
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		$use_is_my_constituent_rules = isset ( $form_variables_object->use_is_my_constituent_rules ) ? $form_variables_object->use_is_my_constituent_rules : 'N';
		$imc_qualifiers = isset ( $form_variables_object->imc_qualifiers ) ? $form_variables_object->imc_qualifiers : '';
		/*
		*
		* should we be doing anything at all?
		* if filtering not affirmatively enabled, return 0,'' -- not to be filtered and is_my_constituent set to empty 	
		*
		*/
		if ( 'Y' != $use_is_my_constituent_rules  ) {
			return $response;
		}
		/*
		*
		* if have passed to this stage, geographic location setting is on
		*
		*/
		// next check if found constituent -- go by is_my_constituent value if set 
		if ( $constituent_id ) {
			$response['is_my_constituent'] = WIC_Entity_Constituent::is_my_constituent ( $constituent_id );
		}
		/*
		*
		* if not found on constituent record (or found, but unknown/unset) examine geography settings
		* see if any of city, state or zip values appear in positive screening list
		* if so, set is_my_constituent = 'Y'.  If not, and parse was good, set as 'N'. Otw, blank
		*
		*/
		if ( '' == $response['is_my_constituent'] ) {
			if ( count (
					array_intersect (
						// note that city state and zip have all passed regex screens in class-wic-db-address-block-object.php
						array (
							$email_object->city, 
							$email_object->zip, 
							$email_object->state 
						),
						// note that $imc_qualifiers already sanitized through regex in email-settings.js
						explode ( '|', $imc_qualifiers ) 
					)
				  ) > 0
				) {
				$response['is_my_constituent'] = 'Y';
			} else {
				// if parse returned name and address (parse quality 1 or 2), but yet not matched, then affirmatively rate as non-constituent
				if ( self::evaluate_parse_quality( $email_object ) < 3 ){
					$response['is_my_constituent'] = 'N';
				}
			}
		}
			
		/*
		*
		* have set is_my_constituent, filtered is still false, can return if not going to do autoreplies
		*
		*/
		if ( ! $subject_line_map_result ) { 
			return $response;
		}
		/*
		*
		* remaining cases pertain to trained replies -- now progressing to consider auto replies and filtering
		*
		*/
		$use_non_constituent_responder = isset ( $form_variables_object->use_non_constituent_responder ) ? $form_variables_object->use_non_constituent_responder : '1';
		$non_constituent_response_subject_line = isset ( $form_variables_object->non_constituent_response_subject_line ) ? $form_variables_object->non_constituent_response_subject_line : '';
		$non_constituent_response_message = isset ( $form_variables_object->non_constituent_response_message ) ? $form_variables_object->non_constituent_response_message : '';
		// apply settings to determine whether to auto respond and filter
		if ( 
			( 2 == $use_non_constituent_responder && 'N' == $response['is_my_constituent']  ) ||
			( 3 == $use_non_constituent_responder && 'Y' != $response['is_my_constituent']  )
			) { 
			// belt and suspenders that not sending empty -- already checked in .js
			if ( strlen( $non_constituent_response_subject_line ) > 4 && strlen ( $non_constituent_response_message ) > 19 ) {
				$response['filtered'] = 1;		 	
				WIC_Entity_Email_Send::send_standard_reply ( $email_object );
			}
		}
		
		return ( $response );
	
	}


	public static function compile_thread_date_times( $folder ) {
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$sql =" 
			UPDATE $inbox_table i INNER JOIN
			(
				SELECT account_thread_id, max(email_date_time) as medt
				FROM $inbox_table 
				WHERE full_folder_string = '$folder' AND
					no_longer_in_server_folder = 0 AND
					to_be_moved_on_server = 0 AND
					serialized_email_object > '' AND
					account_thread_id > ''
				GROUP BY account_thread_id
			) lm
			ON lm.account_thread_id = i.account_thread_id
			SET account_thread_latest = lm.medt 
			WHERE full_folder_string = '$folder' AND
					no_longer_in_server_folder = 0 AND
					to_be_moved_on_server = 0 AND
					serialized_email_object > '' AND
					i.account_thread_id > ''
		";
		$wpdb->query ( $sql );
	
	}

	// GMAIL Reviewed -- no change needed
	public static function unparse_inbox() {
		/* 
		* note: this process could start in the middle of a parse process
		* (1) parse process runs monotonically by uid (id in the case of gmail); it will not go back to retry parsed messages that this routine unparses
		* (2) this process has no effect on unparsed messages
		* (3) this process has no effect on half-parsed messages -- saved md5/attachments, but not object . . . but this will not be selected
		*
		* messages reset will get reparsed on the next parse run (which will start at lowest unparsed folder_uid or id in the case of gmail)
		*
		* no need to unset activities, since not touching to_be_move_on_server records
		* could start this process after loading of inbox -
		* (1) if run this before, scroll to message, will get not found error
		* (2) if run this before attempt to process, will skip message and ignore -- will resurface in inbox
		* (3) if run this after reserved for processing, will complete processing and mark for deletion (may show as originally parsed in done list)-- OK outcome
		* (4) if run this after processed and released, will not see message
		* 
		*/
		global $wpdb;
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$folder = WIC_Entity_Email_Account::get_folder();
		
		// table names
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$md5_table = $wpdb->prefix . 'wic_inbox_md5';
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref'; // don't worry about deleting attachment itself; may be used elsewhere; purge orphans on purge

		// purge attachments xref (but not attachments) and md5 coding of image text			
		$sql = $wpdb->prepare ( 
			"
			DELETE m5, a 
			FROM $inbox_table m LEFT JOIN 
				$md5_table m5 on m5.inbox_message_id = m.ID LEFT JOIN
				$attachments_xref_table a on a.message_id = m.ID
			WHERE full_folder_string = %s and to_be_moved_on_server = 0 and no_longer_in_server_folder = 0 and serialized_email_object > ''
			", array ( $folder ) ); 		
		$result = $wpdb->query ( $sql );
	
		// reset the serialized_email_object to empty string			
		$sql = $wpdb->prepare ( 
			"
			UPDATE $inbox_table m 
			SET serialized_email_object = ''
			WHERE full_folder_string = %s and to_be_moved_on_server = 0 and no_longer_in_server_folder = 0 and serialized_email_object > ''
			", array ( $folder ) ); 		
		$result = $wpdb->query ( $sql );		
		
		WIC_Entity_Email_Cron::log_mail ( "WP Issues CRM -- inbox parse reset completed, reset $result messages.  WIC_Entity_Email_Inbox_Parse::unparse_inbox." );

		return WIC_Entity_Email_Inbox_Synch::load_staged_inbox ( '', '' );  
	}
	
	// used in cron setting in lieu of get_user_by because not all user functions are loaded
	public static function does_user_email_exist ( $email ) {
		global $wpdb;
		
		if ( is_multisite() ) {
			switch_to_blog( BLOG_ID_CURRENT_SITE );
			$user_table_prefix = $wpdb->prefix;
			restore_current_blog();
		} else {
			$user_table_prefix = $wpdb->prefix;
		}
		$user_table = $user_table_prefix . 'users';
		
		$count = $wpdb->get_results ( $wpdb->prepare ( "SELECT count(ID) as ucount FROM $user_table WHERE user_email = %s", array ( $email ) ) );
		if ( $count[0]->ucount ) {
			return true;
		} else {
			return false;
		}
	}	
	
}