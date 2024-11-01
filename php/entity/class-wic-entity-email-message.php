<?php
/*
*
*	wic-entity-email-message.php
*
*/
Class WIC_Entity_Email_Message {

	const WIC_REPLY_METAKEY_STUB = 'wic_data_reply_template_'; // append pro_con value to complete key

	public static function load_message_detail ( $UID, $data ) {

		//  get low confidence threshold
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		$mapped_threshold = isset ( $form_variables_object->mapped_threshold ) ? $form_variables_object->mapped_threshold : 85;
		$word_minimum_threshold = isset ( $form_variables_object->word_minimum_threshold ) ? $form_variables_object->word_minimum_threshold : 200;

		// build message object from serialized object previously built
		$message = WIC_DB_Email_Message_Object::build_from_image_uid( $data->fullFolderString, $UID);
		if ( false === $message ) {
			$message_output = 
				'<h3>Message deleted from server since last Inbox refresh.</h3>' .
				'<p>Possibilities include:</p>'.
				'<ol>
				<li>This was the last message on the previous page and your page refreshed before the deletion completed.</li> 
				<li>A background process (like delete or train) finished after the last inbox refresh?</li> 
				<li>You have WP Issues CRM up on another page?</li>
				<li>You deleted it through your standard mail client?</li>
				<li>You initiated an inbox reparse that has not completed?</li>
				</ol>';

			$response = (object) array (
				'assigned_constituent_display' => '',
				'assigned_constituent'		=> '', 
				'attachments_display_line'  => '',
				'from_email'				=> '',
				'incoming_message_details' 	=> '',
				'incoming_message' 			=> $message_output,
				'issue_title'				=> '',
				'issue'						=> '',
				'parse_quality'				=> '',
				'pro_con'					=> '',
				'recipients_display_line'	=> '',
				'sender_display_line'		=> '',
				'template'					=> '', 
			);

			return array ( 'response_code' => true, 'output' => $response ); 

		} else { 
			/*
			*
			* first set up all the incoming message content
			*
			*
			*/
			$message_output = WIC_Entity_Email_Attachment::replace_cids_with_display_url( $message->raw_html_body, $message->inbox_image_id, 0 ); // raw_html_body is always present -- created in object from text, if absent.
			$sender_display_line		= self::setup_sender_display_line ( $message );
			$recipients_display_line 	= self::setup_recipients_display_line( $message );
			$attachments_display_line 	= self::setup_attachments_display_line( $message->inbox_image_id, 0 ); 
			$reply_transition_line		= self::get_transition_line ( $message );
			/*
			*
			* second assemble parsed results
			*
			*/
			// get label for parse quality
			global $wic_db_dictionary;
			$parse_label = 
				WIC_Function_Utilities::value_label_lookup ( $message->parse_quality, $wic_db_dictionary->lookup_option_values( 'email_parse_options' ) ) . 
				' (' . $message->parse_quality .')';

			// parse message results 
			$message_details = 
				'<table class = "inbox_message_details wp-issues-crm-stats">
					<tr><td>Date:</td><td>' . $message->activity_date . '</td></tr>
					<tr><td>Email Address:</td><td>' . $message->email_address . '</td></tr>
					<tr><td>Phone:</td><td>' . $message->phone_number . '</td></tr>
					<tr><td>First Name:</td><td>' . $message->first_name . '</td></tr>
					<tr><td>Middle Name:</td><td>' . $message->middle_name . '</td></tr>
					<tr><td>Last Name</td><td>' . $message->last_name . '</td></tr>
					<tr><td>Address:</td><td>' . $message->address_line . '</td></tr>
					<tr><td>Apartment:</td><td>' . $message->apartment_only_line . '</td></tr>
					<tr><td>City:</td><td>' . $message->city . '</td></tr>
					<tr><td>State:</td><td>' . $message->state . '</td></tr>
					<tr><td>Zip:</td><td>' . $message->zip . '</td></tr>
					<tr><td>Parse Quality:</td><td>' . $parse_label .'</td></tr>
				</table>';
			/*
			*
			* third assemble findings about issue mapping
			*
			* if switching subject lines, always start fresh, otherwise, just carry through user's defined values
			* for issue/pro_con/template as scroll through messages within group
			*/
			if ( $data->switching ) {
				// reset all to blank
				$data = self::reset_issue ( $data );
				// use available data from message object only under conditions:
				if ( 
					$message->mapped_issue > 0 && 									// there is a mapped issue
					$message->mapped_issue == $message->guess_mapped_issue && 		// content also matches
					$message->guess_mapped_issue_confidence >= $mapped_threshold && // guess map is strong -- percentage of match
					$message->non_address_word_count > $word_minimum_threshold 		// message is long enough to legitimately be counted
					) {
					$data->issue 		= $message->mapped_issue;
					$data->pro_con 		= $message->mapped_pro_con;	
					$data->template 	= self::get_reply_template( $message->mapped_issue, $message->mapped_pro_con )['output'];			
				} else {
					$data->template		= '<br/>' . WIC_Entity_User::get_current_user_sig();
				}
				// always switching when go to a line with inbox defined values because load_inbox will not group if user defined values from inbox
				// override with these values if they exist
				$data->issue 	= 	$message->inbox_defined_issue 		? $message->inbox_defined_issue 		: $data->issue;
				$data->pro_con 	=	( $message->inbox_defined_pro_con  || $message->inbox_defined_issue )	? $message->inbox_defined_pro_con : $data->pro_con;
				$data->template = 	$message->inbox_defined_reply_text 	? $message->inbox_defined_reply_text 	: $data->template;
				
			} 
			// validate finalized issue -- still not trashed? -- parsed email object would not know that
			// note that issue should never be falsey at this stage, but testing anyway for legacy
			// will not process second half of test if first true
			if ( ! $data->issue || ! WIC_DB_Access_WP::fast_id_validation ( $data->issue ) ) {
				$data = self::reset_issue ( $data );
			}
			/*
			*
			* return all to client
			*
			*/ 

			$response = (object) array (
				'assigned_constituent_display' 	=> $message->assigned_constituent ? self::get_constituent_title ($message->assigned_constituent) : '',
				'assigned_constituent'			=> $message->assigned_constituent, 
				'assigned_staff'				=> $message->inbox_defined_staff,
				'reply_is_final' 				=> $message->inbox_defined_reply_is_final,
				'attachments_display_line' 	 	=> $attachments_display_line,
				'from_email'					=> $message->from_email,
				'incoming_message_details' 		=> $message_details,
				'incoming_message' 				=> $message_output,
				'issue_title'					=> $data->issue > 0 ? WIC_DB_Access_WP::fast_title_lookup_by_id ( $data->issue ) : '',
				'issue'							=> $data->issue,
				'parse_quality'					=> $message->parse_quality,
				'pro_con'						=> $data->pro_con,
				'recipients_display_line'		=> $recipients_display_line,
				'sender_display_line'			=> $sender_display_line,
				'template'						=> $data->template, 
				'to_array'						=> $message->to_array,
				'cc_array'						=> $message->cc_array,
				'clean_all_array'				=> self::construct_all_address_list( $message->to_array, $message->cc_array ),
				'reply_array'					=> array ( array ( trim( $message->first_name . ' ' . $message->last_name ), $message->email_address, $message->assigned_constituent ) ),
				'reply_transition_line'			=> $reply_transition_line,
			);
			return array ( 'response_code' => true, 'output' => $response ); 
			
		} // else have a valid message object
	}
	
	private static function reset_issue ( $data ) {
		// reset all to blank
		$data->issue 		= WIC_Entity_Activity::get_unclassified_post_array()['value']; // default to unclassified value
		$data->pro_con 		= ''; // pro con select control contains '' as unset value	
		$data->template 	= ''; // template is textarea
		return $data;
	}
	
	public static function get_transition_line ( &$message ) {
			$sent = isset ( $message->display_date ) ? ' sent ' . $message->display_date : '';
			return '<hr/>Replying to message from &lt;' . $message->email_address . '&gt;' . $sent . ':<br/><br/>'; 
	}
	
	public static function load_full_message ( $ID, $selected_page ) {
		if ( ! $message = WIC_DB_Email_Message_Object::build_from_id ( $selected_page, $ID ) ) {
			return array ( 'response_code' => false, 'output' => "Could not find $selected_page message $ID on server." ); 
		}
		// check for issue link
		global $wpdb;
		$activity_table = $wpdb->prefix . 'wic_activity';
		$link_field = 'done' == $selected_page ? ' related_inbox_image_record ' : ' related_outbox_record ';
		$results = $wpdb->get_results ( "SELECT constituent_id, issue FROM $activity_table WHERE $link_field = $ID" );
		if ( isset ( $results[0] ) ) {
			$constituent	= $results[0]->constituent_id;
			$issue 		 	= $results[0]->issue;
		} else {
			$constituent = 0;
			$issue = 0;
		}
		$response = (object) array (
			'attachments_display_line' 	 	=> self::setup_attachments_display_line( $message->message_id, $message->outbox ), 
			'message' 						=> WIC_Entity_Email_Attachment::replace_cids_with_display_url( 'done' == $selected_page ? $message->raw_html_body : $message->html_body, $message->message_id, $message->outbox ),
			'recipients_display_line'		=> self::setup_recipients_display_line( $message ), // includes from if present
			'constituent_link'				=> ( $constituent ?	'<a target = "_blank" title="Open constituent in new window." 	href="' . admin_url() . '/admin.php?page=wp-issues-crm-main&entity=constituent&action=id_search&id_requested=' . $constituent . '">View constituent</a>' : 'Constituent not classified.' ),
			'issue_link'					=> ( $issue ? 		'<a target = "_blank" title="Open issue in new window." 		href="' . admin_url() . '/admin.php?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . $issue . '">View Issue</a>' : 'Issue not classified.' ),
		);
		return array ( 'response_code' => true, 'output' => $response ); 
		
	}
	
	// construct unique list excluding own email addresses (incoming from and and reply to) from two arrays
	public static function construct_all_address_list ( $to_array, $cc_array ) {

		$merged_clean_array = array();
		$used_addresses = array();
		$wic_option_array = get_option('wp_issues_crm_plugin_options_array');
		$my_mails = array ( 
			isset( $wic_option_array['user_name_for_email_imap_interface'] ) ?  strtolower($wic_option_array['user_name_for_email_imap_interface']) : 'no_user_name_for_email_imap_interface', 
			isset( $wic_option_array['from_email'] ) ?  strtolower($wic_option_array['from_email']) : 'no_from_email', 
			isset( $wic_option_array['smtp_reply'] ) ?  strtolower($wic_option_array['smtp_reply']) : 'no_smtp_reply', 
			isset( $wic_option_array['activesync_email_address'] ) ?  strtolower($wic_option_array['activesync_email_address']) : 'no_activesync_email_address', 
		);	

		foreach ( $to_array as $to_address ) {
			if ( !in_array ( $to_address[1], $used_addresses ) &&  !in_array ( strtolower($to_address[1]), $my_mails) ) {
				$to_address[0] = self::clean_name( $to_address[0]);
				array_push ( $merged_clean_array, $to_address );
			}
			array_push( $used_addresses, $to_address[1] );
		}
	
		foreach ( $cc_array as $cc_address ) { 
			if ( !in_array ( $cc_address[1], $used_addresses ) &&  !in_array ( strtolower($cc_address[1]), $my_mails) ) {
				$cc_address[0] = self::clean_name( $cc_address[0]);
				array_push ( $merged_clean_array, $cc_address );
			}
			array_push( $used_addresses, $cc_address[1] );
		}
		return $merged_clean_array; 
	}

	
	private static function clean_name ( $name_line ) {
		$working_block_object = new WIC_DB_Address_Block_Object;
		$name_array = $working_block_object->parse_name( $name_line );
		$clean_name = trim ( $name_array['first'] . ' ' . $name_array['middle'] . ( $name_array['middle'] ? ' ' : '') . $name_array['last'] );
		return $clean_name;
	}
	/*
	*
	* save/load reply templates
	*
	*/
	// always call this function for save or update of template -- existence check built in
	public static function save_update_reply_template ( $issue, $data ) {
		/*
		* $data object has three properties
		*	pro_con_value ( OK if empty );
		*	template_title
		*   template_content
		*
		*/
		// sanitize data
		$pro_con_value = sanitize_text_field ( $data->pro_con_value );
		$title = sanitize_text_field ( $data->template_title );
		$content = wp_kses_post ( $data->template_content );
		// does the template already exist for the issue pro_con value combination
		$meta_key = self::WIC_REPLY_METAKEY_STUB . $pro_con_value;
		$meta_value = get_post_meta ( $issue, $meta_key, true );
		// if not, add the template, get the id and save meta_key_stub
		if ( $content ) {
			if ( '' === $meta_value ) {
			   $args = array(
					'post_content' 	=> $content,
					'post_title' 	=> $title,
					'post_status' 	=> 'private',
					'post_type' 	=> 'wic_reply_tempate',
					'post_parent' 	=> $issue,
				);
				$new_template_id = wp_insert_post ( $args );
				if ( $new_template_id && ! is_wp_error(  $new_template_id ) ) {
					if ( ! add_post_meta( $issue, $meta_key, $new_template_id, true ) ) {
						return array ( 'response_code' => false, 'output' => 'Error saving meta record for template.'  );
					}
				} else {
					return array ( 'response_code' => false, 'output' => 'Error saving template.'  );
				}
			// if so, just update it
			} else {
				$args = array (
					'ID'			=> $meta_value,
					'post_content' 	=> $content,
					'post_title' 	=> $title,			
				);
				wp_update_post ( $args );
				// not doing change checking, so can't test return for false -- continue -- catchable error by user 
			}
			return array ( 'response_code' => true, 'output' => 'Template saved/update OK.'  );
		// sent a blank template, delete post if it exists
		} else {
			if ( '' !== $meta_value ) {
				wp_trash_post ( $meta_value );
				delete_post_meta ( $issue, $meta_key );
			}
			return array ( 'response_code' => true, 'output' => 'Template deleted.'  );
		}	

	}
	
	/*
	*
	* retrieve set template for issue/pro_con
	*
	*/
	public static function get_reply_template ( $issue, $pro_con_value ) {
		$pro_con_value = sanitize_text_field ( $pro_con_value );
		$meta_key = self::WIC_REPLY_METAKEY_STUB . $pro_con_value;
		$meta_value = get_post_meta ( $issue, $meta_key, true );
		$template = '';
		if ( $meta_value ) {
			$template = get_post_field( 'post_content', $meta_value);
		}
		// return a standardized empty if false, null or 0;
		if ( ! $template ) {
			$template = '';
		}
		return array ( 'response_code' => true, 'output' => $template  );
	}

	/*
	*
	* delete set template for issue/pro_con
	*
	*/
	public static function delete_reply_template ( $issue, $pro_con_value ) {
		$pro_con_value = sanitize_text_field ( $pro_con_value );
		$meta_key = self::WIC_REPLY_METAKEY_STUB . $pro_con_value;
		$meta_value = get_post_meta ( $issue, $meta_key, true );
		$template = '';
		if ( ! $meta_value ) {
			return array ( 'response_code' => true, 'output' => array ( 'success' => false, 'message'=>'Reply already deleted; cannot be restored.' )  );
		}
		wp_trash_post( $meta_value );
		delete_post_meta( $issue, $meta_key );
		return array ( 'response_code' => true,'output' => array ( 'success' => true, 'message'=>'Reply trashed.  Restore?' )    );
	}

	/*
	*
	* restore set template for issue/pro_con
	*
	*/
	public static function restore_reply_template ( $issue, $data ) {
		$post = wp_untrash_post ( $data->reply_id );
		if ( false !== $post ) {
			$pro_con_value = sanitize_text_field ( $data->pro_con_value );
			$meta_key = self::WIC_REPLY_METAKEY_STUB . $pro_con_value;
			update_post_meta ( $issue, $meta_key, $data->reply_id );
			return array ( 'response_code' => true, 'output' => array ( 'success' => true, 'message'=> $post->content ) );

		}
		return array ( 'response_code' => true, 'output' => array ( 'success' => false, 'message'=> 'Could not restore' ) );
	}


	/*
	*
	* new issue from message content
	*
	*/
	public static function new_issue_from_message ( $uid, $data ) {
		$notice = '';
		$message = WIC_DB_Email_Message_Object::build_from_image_uid( $data->fullFolderString, $uid);
		if ( false === $message ) {
			return array ( 'response_code' => false, 'output' => 'Message was deleted from server since last Inbox refresh' ); 
		} else {
			if ( $insert_id = WIC_DB_Access_WP::fast_id_lookup_by_title ( $message->subject ) ) {
				$notice = '<p><strong>Issue already exists with title:</strong></p><p>"' . $message->subject . '".</p><p>No new issue created.</p>'; 
			} else {
				$args = array(
					'post_content' 	=> $message->raw_html_body,
					'post_title' 	=> $message->subject,
					'post_status' 	=> 'private',
					'post_type' 	=> 'post',
				);
				$insert_id = wp_insert_post ( $args );
				if ( ! $insert_id ) {
					return array ( 'response_code' => false, 'output' => 'Error creating new issue' ); 
				}	
				$notice = '<p><strong>New private issue created with title:</strong></p><p>"' . $message->subject . '".</p>'; 
			}
			$notice .= '<p><a target="_blank" href="' . get_edit_post_link( $insert_id ) .'">Edit issue</a></p>';
			// set update status as open whether or not new
			update_post_meta ( $insert_id, WIC_DB_Access_WP::WIC_METAKEY . 'wic_live_issue', 'open' );
			return array ( 'response_code' => true, 'output' => array ( 'value' => $insert_id, 'label' => $message->subject, 'notice' => $notice )  ); 
			
		} // else have a valid message object
	}
	
	// use straight wordpress call to populate issue peek popup
	public static function get_post_info ( $issue, $dummy ) {
		$post_object = get_post( $issue ); // self::WIC_REPLY_METAKEY_STUB
		$post_meta = get_post_meta ( $issue );
		$templated_pro_con_array = array();
		foreach ( $post_meta as $meta_key => $meta_value ){
			if ( substr( $meta_key, 0, 24) == self::WIC_REPLY_METAKEY_STUB ) {
				$pro_con = ( false === substr( $meta_key, 24 ) || '' === substr( $meta_key, 24 ) ) ? 'blank' : substr( $meta_key, 24 ); // substr return values changing in php versions and want to allow value 0
				$templated_pro_con_array[] = $pro_con;
			}
		}
		if ( current_user_can ( WIC_Admin_Access::check_required_capability('view_edit_unassigned') ) || current_user_can ( WIC_Admin_Access::check_required_capability('email') ) ) {
			$content = isset ( $post_object->post_content  ) ? apply_filters( 'the_content', $post_object->post_content ) : '<h3>Issue trashed or deleted since assignment.</h3>' ;
		} else {
			$content = '<p>Consult your supervisor for access to the full content of this issue.  Your current user role does not have access to issues not assigned to you.</p>';
		}
		return array ( 'response_code' => true, 'output' => (object) array ( 'content' => $content, 'templated_pro_con_array' => $templated_pro_con_array ) ); 
	}


	private static function setup_sender_display_line( $message ) {
		// message sender line for display
		$sender_display_line = 
			isset ( $message->reply_to_personal ) ? 
				(
				$message->reply_to_personal ? 
					$message->reply_to_personal : 
					( isset ( $message->reply_to_email ) ? $message->reply_to_email : '' )
				) :
				''
			; 
		$sender_display_line = $sender_display_line ? $sender_display_line : 
			(
			isset ( $message->from_personal ) ? 
				(
				$message->from_personal ? 
					$message->from_personal : 
					( isset ( $message->from_email ) ? $message->from_email : '' )
				) :
				''
			)
			; 
		return $sender_display_line;
	}
		
	public static function setup_recipients_display_line( &$message ) {
		$recipients_display_line = '<table id="to-from-reply-table" >';
		$recipients_display_line .= '<tr><td>Subject: </td><td>' . $message->subject . '</td></tr>';
		$recipients_display_line .= '<tr><td>Dated: </td><td>' . $message->display_date . '</td></tr>';
		if ( !empty(  $message->from_email  )  ) {
			$recipients_display_line .= self::address_array_to_table_row ( array ( array( $message->from_personal,  $message->from_email ) ), 'From' );
		} 
		if ( !empty( $message->reply_to_email ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( array ( array( $message->reply_to_personal,  $message->reply_to_email ) ), 'Reply' );
		}
		if ( !empty( $message->to_array ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( $message->to_array, 'To' );
		} else {
			$recipients_display_line .= '<tr><td>To: </td><td></td></tr>'; // always include a to line
		}
		if ( !empty ($message->cc_array ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( $message->cc_array, 'Cc' );
		}
		if ( !empty( $message->bcc_array ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( $message->bcc_array, 'Bcc' );
		}
		$recipients_display_line .= '</table>';
		return $recipients_display_line;
	}

	public static function setup_attachments_display_line( $message_id, $message_in_outbox ) {
	
		$attachments = WIC_Entity_Email_Attachment::get_message_attachments ( $message_id, $message_in_outbox );
		// this function can be called without knowing if have attachments
		if ( ! $attachments ) {
			return '';
		} 
		// now subselect attachments with disposition == attachment
		$attachments_disposed_as_attachments = array();
		foreach ( $attachments as $attachment ) {
			if ( $attachment->message_attachment_disposition != 'inline' ) {
				$attachments_disposed_as_attachments[] = $attachment;
			}
		}
		if ( ! $attachments_disposed_as_attachments ) {
			return '';
		} 		

		// now construct list of real attahments
		$attachments_display_line = '<p>Attachments:</p><ol id="msg_attachments_list">';
		foreach ( $attachments_disposed_as_attachments as $attachment ) {
			$attachments_display_line .= 
				$attachment->attachment_saved ?
				(
				'<li><a href="' . WIC_Entity_Email_Attachment::construct_attachment_url( $message_id, $attachment->attachment_id, $message_in_outbox ) . '" target = "_blank">' .
					$attachment->message_attachment_filename . ' (' . $attachment->attachment_size . ' bytes)' .
				'</a></li>' 
				):(
				'<li>' . $attachment->attachment_filename . ' (' . $attachment->attachment_size . ' bytes)  
				 -- rejected due to suspect file name or file size over ' . WIC_Entity_Email_Inbox_Parse::get_max_packet_size()  . 
				' (the MySQL max_allowed_packet setting for your server )</li>' 
				);
		}	
		$attachments_display_line .= '</ol>';
		return $attachments_display_line; 
	}
	
	private static function address_array_to_table_row ( $address_array, $type ) {
		$string = '';
		$first = true;
		foreach ( $address_array as $address_line ) {
				$string .= '<tr>
					<td>' . ( $first ? $type .': ' : '' ) . '</td>
					<td>' . ( $address_line[0] ? ( $address_line[0] . ' ' ) : '' ) . '&lt;' . $address_line[1] . '&gt;</td>
				</tr>';	
				$first = false;
		}
		return $string;
	}

	private static function get_constituent_title ( $constituent_id ) {
		global $wpdb;
		$title_phrase_array = $wpdb->get_results ( WIC_Entity_Search_Box::get_constituent_autocomplete_base_sql() . " WHERE c.ID = $constituent_id GROUP BY c.ID" );
		return $title_phrase_array ? $title_phrase_array[0]->name_address : '';
	}

	public static function quick_update_constituent_id ( $folder_uid, $data ) {
		global $wpdb;
		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';
		$sql = $wpdb->prepare (
			"UPDATE $inbox_image_table SET assigned_constituent = %d WHERE full_folder_string = %s AND folder_uid = %d ",
			array ( $data->assigned_constituent, $data->fullFolderString, $folder_uid )
		);
		$result = $wpdb->query ( $sql );
		$response_code = ( $result !== false );
		$output = (object) array ( 
			'output' => $response_code ? 'Assigned constituent update OK.': 'Database error on assigned constituent update.',
			'constituent_name' => ( $response_code && $data->assigned_constituent ) ? WIC_DB_Access_WIC::get_constituent_name( $data->assigned_constituent ) : ''
		); 
		return array ( 'response_code' => $response_code, 'output' => $output ); 
	} 

	public static function quick_update_inbox_defined_item( $folder_uid, $data ) {
	
		global $wpdb;
		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';
		$field_to_update = 'inbox_defined_' . $data->field_to_update;
		$sql = $wpdb->prepare (
			"UPDATE $inbox_image_table SET $field_to_update = %s WHERE full_folder_string = %s AND folder_uid = %d ",
			array ( $data->field_value, $data->fullFolderString, $folder_uid )
		);
		$result = $wpdb->query ( $sql );
		$response_code = ( $result !== false );
		$output = (object) array ( 
			'output' => $response_code ? 'Assigned constituent update OK.': "Database error on $field_to_update update.",
		); 
		return array ( 'response_code' => $response_code, 'output' => $output ); 	
	
	}

	
}