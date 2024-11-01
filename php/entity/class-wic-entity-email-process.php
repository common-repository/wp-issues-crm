<?php
/*
*
*	wic-entity-email-process.php
*
*/
class WIC_Entity_Email_Process  {


	/*
	*
	*	functions supporting processing of emails from bulk and online
	*
	*	all processes architected to be interruptible and to not conflict with either other runs or other online actions
	*		-- UID record/reply transactions, followed by hard move
	*		-- if two processes want the same UID, the first acts and the second bypasses it with an error
	*		-- folder actions are set at the start of the job 
	*	
	*	possiblities are:
	*	  (1) training mode on or off 	-- save a new subject line from client
	*	  (2) sweep mode on or off 		-- use trained subject line mappings, saved on record ( cannot be training at same time); reply except for |*no_reply*|
	*	  (3) reply mode 				-- both values of reply compatible with both values of training -- if training and not replying, set reply to '|*no_reply*|'
	*	  										-- when sweeping, do not reply if template is '|*no_reply*|';
	*/
	
	
	public static function handle_inbox_action_requests( $dummy_id, $data ) {

		// check for folder change
		$folder_test = WIC_Entity_Email_Account::check_online_folder ( $dummy_id, $data->fullFolderString ); 
		if ( ! $folder_test['response_code'] ) {
			return $folder_test;
		} else {
			$folder = $folder_test['output'];
		}

		/*
		* note that no need or reason to sort UIDs; sorted UID sequence actually results in more collisions because first process
		* catches up with second process and then keeps fighting for the same UID
		*/
		
		// set up outcome counters object
		$fail_counters = (object) array (
			'uid_already_reserved'				=> 0, // not an error, tracked only for debugging ( two processes running, conflict prevented )
			'uid_still_valid_failures' 			=> 0, // not an error, tracked only for debugging ( two processes running, this copy is second )
			'data'								=> $data // send back the original
		);

		// outside loop, set up reusable save entity
		$constituent_activity = new WIC_Interface_Transaction;

		// starting to set options (unchanging in loop) and set up in key_value arrays
		$key_value_array = array (
			'activity_type'	=> 'wic_reserved_00000000',
			'address_type' 	=> 'incoming_email_parsed',
			'email_type' 	=> 'incoming_email_parsed',
			'phone_type' 	=> 'incoming_email_parsed',
		);

		// set up variable to capture the md5s to save
		$mapped_md5_set = false;

		// used after loop for error processing			
		$bad_issue = false;
		// begin the processing loop
		foreach ( $data->uids as $current_uid ) {
			
			// reserve uid for action
			if ( ! WIC_Entity_Email_UID_Reservation::reserve_uid ( $current_uid, $data ) ) {
				$fail_counters->uid_already_reserved++;
				continue;
			}

			// gathers preparsed message data
			$message = WIC_DB_Email_Message_Object::build_from_image_uid( $folder, $current_uid );
			// check outcome and reject if message is not built
			if ( false === $message )  {
				$fail_counters->uid_still_valid_failures++;
				WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
				continue;
			} 	
			if ( $data->sweep ) {
				$working_issue 		= $message->mapped_issue;
				$working_pro_con 	= $message->mapped_pro_con;
				// message was unmapped by another instance
				if ( ! $message->mapped_issue ) {
					WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
					continue;
				}
				$working_template	= WIC_Entity_Email_Message::get_reply_template ( $working_issue, $working_pro_con )['output'];	
			} else {
				$working_issue 		= $data->issue;
				$working_pro_con	= $data->pro_con;
				$working_template 	= $data->template;		
			}
			/* 
			* bullet proof against issue was trashed or deleted between issue assignment and processing, most likely on sweep, but also possible from inbox
			*
			*/
			if ( ! WIC_DB_Access_WP::fast_id_validation( $working_issue ) ){
				WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
				if ( $data->sweep ) {
					// not setting bad issue since will be overlayed and only controls non-sweep training actions
					continue;					
				} else {
					$bad_issue = true;
					break; // no point in continuing since if not sweeping, all messages have the same issue
				}
			}

			/*
			*
			* set up values in key_value_array for constituent_activity save
			*
			*/
			// if have a non-zero assigned constituent, put it in the array for override of matching, otherwise, unset that array value
			if ( $message->assigned_constituent ) {
				$key_value_array['preset_wic_constituent_id'] = $message->assigned_constituent;  
			} else {
				if ( isset ( $key_value_array['preset_wic_constituent_id'] ) ) {
					unset ( $key_value_array['preset_wic_constituent_id'] );
				}
			}
			
			// expand key_value array with subject and pro_con
			$key_value_array['pro_con'] = $working_pro_con;
			$key_value_array['issue'] 	= $working_issue;			
			// set array of message parms to be pulled without name change
			$parsed_message_keys = array ( 
				'activity_date',
				'address_line',
				'city',
				'email_address',
				'first_name',
				'last_name',
				'middle_name',
				'phone_number',
				'state',
				'zip',
				'is_my_constituent'
			);	
			// push message parms onto key value array
			foreach ( $parsed_message_keys as $key ) {
				if ( '_name' == substr ( $key, -5 ) ) {
					$key_value_array[$key] = ucfirst( strtolower ( $message->$key) );
				} else { 
					$key_value_array[$key] = $message->$key;
				}
			}
			// add activity note -- formatted with message headers as well as content
			$key_value_array['activity_note'] = 		
				'<div id="recipients-display-line">' . 
					WIC_Entity_Email_Message::setup_recipients_display_line ( $message ) .
				'</div>' .
				'<div id="attachments-display-line">' . 
					WIC_Entity_Email_Message::setup_attachments_display_line( $message->inbox_image_id, 0 ) . 
				'</div>' .
				'<div id="inbox_message_text">' .  
					 WIC_Entity_Email_Attachment::replace_cids_with_display_url(  $message->raw_html_body, $message->inbox_image_id, 0  ) . 
				'</div>';
			// add email specific activity fields
			$special_activity_key_value_array = array();
			$special_activity_key_value_array['related_inbox_image_record']	= $message->inbox_image_id; // NOT folder_uid
			// match or save as new the constituent and save the email activity record  ( false says do no additional sanitization )
			$result = $constituent_activity->save_constituent_activity ( 
				$key_value_array, 
				$special_activity_key_value_array, 
				$policies = array(), 
				$unsanitized = false, 
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
				$email_activity = true				
				);  
			if ( false === $result['response_code'] ) {
				WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
				return $result; // bad response code means database outage; halt processing
			}
			/*
			*
			* if matching updated the constituent id or names, update the message object, so that is fully updated for reply purposes; 
			*  	this also serializes the properties loaded by build_from_image_uid
			*
			*/
			self::check_update_constituent_properties ( $folder, $current_uid, $message, $working_issue, $working_pro_con, $result  );
			/*
			* Now save the reply to queue if so ordered
			*
			*/
			if ( $data->reply ) {
				/*
				* if sweeping or have more than one uid, then just using address from parsing and id/name from found constituent
				*/
				if ( $data->sweep || count( $data->uids ) > 1 ) {
					$final_subject = 'RE: ' . $message->subject;
					$to_array  = array( 
						array( 
							trim( $result['output']->constituent_names->first_name . ' ' . $result['output']->constituent_names->last_name ), 
							$message->email_address,
							$result['output']->constituent_id 
						) 
					);
					$cc_array = array();
					$bcc_array = array();
				} else {
					$final_subject = $data->subjectUI;
					$to_array = $data->to_array;
					$cc_array = $data->cc_array;
					$bcc_array = $data->bcc_array;
				}
				$addresses = array( 'to_array' => $to_array, 'cc_array' => $cc_array, 'bcc_array' => $bcc_array );	
				
	
				// handle dear token if any
				$working_template =  WIC_Entity_Email_Send::replace_dear_token( $result['output']->constituent_names, $working_template );
			
				// set up object for queue_message
				$outgoing_object = new WIC_DB_Email_Outgoing_Object ( 
					$addresses, 				// address array
					$final_subject, 			// subject
					$working_template . 
						WIC_Entity_Email_Message::get_transition_line ( $message ) . 
						$message->raw_html_body,// html_body
					$working_template .
						WIC_Entity_Email_Send::body_break_string . 
						WIC_Entity_Email_Message::get_transition_line ( $message ) . 
						$message->raw_html_body,// most complete version of message --  will be filtered to text body in queue_message
					0,							// is_draft = false 
					$message->inbox_image_id,	// is_reply_to 	
					isset ( $data->include_attachments ) ? $data->include_attachments : false,	
					$working_issue, 
					$working_pro_con 
				);
				
				// queue_message unless working template is '' -- this could occur in sweep for record-only trained messages
				if ( $working_template > '' ) {
					$reply_result = WIC_Entity_Email_Send::queue_message ( $outgoing_object, false ); 
					// an error here should stop the job -- indicative of a database problem
					if ( false === $reply_result['response_code'] ) {
						WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
						return $reply_result;
					}
				}
			}	
		
			// if got to here OK, mark the message as to be moved
			self::delete_release_uid ( $folder, $current_uid ); 

			// use first uid with good sentences to do md5 training map
			if ( $data->train && !$mapped_md5_set && $message->sentence_md5_array ) {
				$mapped_md5_set = $message->sentence_md5_array;
			}

		} // end of loop

		// note that neither of the actions within this possible condition are active during sweeps
		if ( ! $bad_issue ) {
			// if training (never on sweeps), store the new subject line record and parallel content map
			if ( $data->train ) {
				$training_response = WIC_Entity_Email_Subject::save_new_subject_line_mapping( $data );
				if ( $mapped_md5_set ) {
					WIC_Entity_Email_MD5::create_md5_map_entries ( $mapped_md5_set, $data->issue, $data->pro_con );
				}
			} 
		}

		return array ( 'response_code' => true, 'output' => $fail_counters );

	} 

	public static function handle_delete_block_request ( $dummy_id, $data ) {
	
		// first test incoming
		if ( ! isset ( $data->uids ) || ! is_array ( $data->uids ) ) {
			$message = "Bad call to WIC_Entity_Email_Process::handle_delete_block_request.\n No uids.  data was: " 
					. print_r($data, true) . "\n\n" 
					. wic_generate_call_trace(true);
			error_log ( $message );
			return array ( 'response_code' => false, 'output' => $message );

		}
	
		// check for folder change
		$folder_test = WIC_Entity_Email_Account::check_online_folder ( $dummy_id, $data->fullFolderString ); 
		if ( ! $folder_test['response_code'] ) {
			return $folder_test;
		} else {
			$folder = $folder_test['output'];
		}

		// begin the processing loop
		foreach ( $data->uids as $current_uid ) {
			// test each element
			if ( ! $current_uid || ! is_numeric ( $current_uid ) ) {
				$message = "Bad call to WIC_Entity_Email_Process::handle_delete_block_request.\nIncluded 0 or non-numeric uid.  data passed was \n" 
					. print_r($data, true) . "\n\n"
					. wic_generate_call_trace(true);
				error_log ( $message );
				return array ( 'response_code' => false, 'output' => $message );

			}
			// reserve uid for action
			if ( ! WIC_Entity_Email_UID_Reservation::reserve_uid ( $current_uid, $data ) ) {
				continue;
			}
			// do the delete and release the record
			self::delete_release_uid ( $folder, $current_uid );
			// reread the record, construct and apply the filter (block is true only in single record cases);
			if ( true === $data->block ) {
				WIC_Entity_Email_Block::set_address_filter ( $folder, $current_uid, $data->wholeDomain );
			}
		} // end of loop

		return array ( 'response_code' => true, 'output' => '' );
	}


	public static function delete_release_uid ( $folder, $current_uid ) {
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$sql = "UPDATE $inbox_table SET to_be_moved_on_server = 1 WHERE full_folder_string = '$folder' AND folder_uid = $current_uid";
		$wpdb->query ( $sql );
		WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
	}

	public static function setup_settings_form() { 

		$settings_form_entity = new WIC_Entity_Email_Settings ( 'no_action', '' );
		return array ( 'response_code' => true, 'output' => $settings_form_entity->settings_form() );
	}
	
	// sanitize and save options 
	public static function save_processing_options( $dummy, $options ) {
	
	
		/*
		* sanitize options
		*/
		foreach ( $options as $key => $value ) {
			// will also hard sanitize this option before using it -- comes in charcater limited and is verified characger limited
			if ( 'team_list' == $key) {
				$options->$key == preg_replace ( '/[^%@.+_A-Za-z0-9-]/', '', $value );
			} elseif (  'non_constituent_response_message' == $key ) {
				$options->key = wp_kses_post ( $value );
			} else {
				$options->$key = sanitize_text_field ( $value );
			}
		}
		/*
		* don't save option unless there is a change, so that a false return is a real error
		*/
		$new_options_serialized = serialize( $options );
		$old_options_serialized = serialize(  get_option ( 'wp-issues-crm-email-processing-options' ) );
		if ( $new_options_serialized == $old_options_serialized ) {
			$result = true;
		} else {
			$result = update_option ( 'wp-issues-crm-email-processing-options', $options ) ;
		}
		
		$response =  $result ? __( 'Options saved OK.', 'wp-issues-crm' ) : __( 'Error saving options', 'wp-issues-crm' );
		return array ( 'response_code' => $result, 'output' => $response );
	}
	
	/*
	* called to support settings form population and in every instance where settings are used -- no direct access to the option 
	*
	* should never raw get_option, instead: $option_object = WIC_Entity_Email_Process::get_processing_options()['output'];
	*/
	public static function get_processing_options( ) {
		// load options -- comes back unserialized (no longer serializing on save since update_option serializes )
		$options_object = get_option ( 'wp-issues-crm-email-processing-options' );

		// if non-existent at start up, supply
		if ( !is_object ( $options_object ) ) {
			$options_object = (object) array();
		}

		// restore basic option defaults from dictionary if missing ( on error or first use )
		global $wic_db_dictionary;
		$defaults = $wic_db_dictionary->get_field_defaults_for_entity( 'email_settings');
		foreach ( $defaults as $field => $default ) {
			if ( !isset ( $options_object->$field ) || '' == $options_object->$field  ) {
				@$options_object->$field = $default;
			}
		}
		// now additional defaults store in address words object
		$address_term_types = array ( 'disqualifiers', 'states', 'streets', 'apartments', 'special_streets','pre_titles','post_titles','closings','non_names' );
		foreach ( $address_term_types as $address_term_type ) {
			// if have a value and it is non-blank, do nothing
			if ( isset ( $options_object->$address_term_type ) ) {
				if ( $options_object->$address_term_type > '' ) {
					continue;
				}
			}
			// otherwise, load default
			@$options_object->$address_term_type = WIC_DB_US_Address_Words_Object::get_terms ( $address_term_type );
		}

		// in start up case, supply unset settings that will be missing
		$other_properties = array ( 'non_constituent_response_subject_line', 'non_constituent_response_message', 'imc_qualifiers', 'forget_date_phrase', 'activesync_email_address', 'activesync_sender_name' );
		foreach ( $other_properties as $property ) {
			// if have a value, even if blank
			if ( isset ( $options_object->$property ) ) {
				continue;
			}
			// otherwise, set blank property
			@$options_object->$property ='';
		}

		// not handling error from retrieval of option -- on start up, want to load overloaded properties anyway
		return array ( 'response_code' => true, 'output' =>  $options_object );
	}

	public static function first_n1_chars_or_first_n2_words ( $utf8, $n1, $n2 ) {
		if ( function_exists ( 'iconv_substr' ) ) { // note that this branch will generally be used since iconv is installed by default and wp issues crm requires it for charactiver conversion
			return iconv_substr( $utf8, 0, $n1, 'UTF-8' );
		} else {
			$shortened_utf8 = '';
			$utf8_word_array = explode ( ' ', $utf8 );
			$max = count ( $utf8_word_array );
			for ( $i=0; $i<$n2; $i++ ) {
				if ( $i == $max ) {
					break;
				}
				$shortened_utf8 .= ( $utf8_word_array[$i] . ' ' );
			}		
			return trim ( $shortened_utf8 );
		} 
	}
	
	

	// takes interface-transaction result and updates message properties if necessary
	private static function check_update_constituent_properties ( $folder, $current_uid, &$message, $working_issue, $working_pro_con, $result  ) {
		// if check OK, just return
		if (
			$message->first_name 			== $result['output']->constituent_names->first_name &&
			$message->last_name  			== $result['output']->constituent_names->last_name  &&
			$message->assigned_constituent	== $result['output']->constituent_id &&
			$message->mapped_issue			== $working_issue &&
			$message->mapped_pro_con 		== $working_pro_con 
		) {
			return;
		}

		// set correct values
		$message->first_name 			= $result['output']->constituent_names->first_name;
		$message->last_name  			= $result['output']->constituent_names->last_name;
		$message->assigned_constituent 	= $result['output']->constituent_id;
		$message->mapped_issue			= $working_issue;
		$message->mapped_pro_con 		= $working_pro_con;
		$reserialized_inbox_message = serialize ( $message );

		// update the image record
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		$sql = $wpdb->prepare ( 
			"UPDATE $inbox_table SET assigned_constituent = %d, serialized_email_object = %s, mapped_issue = %d, mapped_pro_con = %s
				WHERE full_folder_string = %s AND folder_uid = %d",
			array( $result['output']->constituent_id, $reserialized_inbox_message, $working_issue, $working_pro_con , $folder, $current_uid )
		);
		$wpdb->query ( $sql );
		return;
	}
	
	
}