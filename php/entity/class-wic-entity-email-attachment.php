<?php
/*
*
*	wic-entity-email-attachment.php
*
*   this class collects attachment related functions
*
*	
*/

// this is the location of the copy of the php ews client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'activesync'  . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
use  \jamesiarmes\PhpEws\Request\GetAttachmentType;
use  \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfRequestAttachmentIdsType;
use  \jamesiarmes\PhpEws\Type\FileAttachmentType;
use  \jamesiarmes\PhpEws\Type\RequestAttachmentIdType;
use  \jamesiarmes\PhpEws\Enumeration\ResponseClassType;

class WIC_Entity_Email_Attachment {

	public static function handle_activesync_attachments ( $id, $attachments ) {

		// bad call to attachments
		if ( ! isset( $attachments->FileAttachment ) || ! is_array (  $attachments->FileAttachment ) || ! $attachments->FileAttachment ) {
			error_log ( 'WIC_Entity_Email_Attachment:handle_activesync_attachments was asked to parse attachments, but there were none found.' );
			return;
		}

		// get client
		$response = WIC_Entity_Email_ActiveSync::get_connection();
		if ( ! $response['response_code'] ) {
			error_log ( 'WIC_Entity_Email_Attachment:handle_activesync_attachments could not connect. ' . $response['output'] );
			return; // do not die -- just continue -- user will have to notice that attachments missing and seek resend
		} else {
			$client = $response['output'];
		}	

		// loop through the attachments one at a time, making individual requests to avoid oversize payload
		foreach ( $attachments->FileAttachment as $attachment ) {
			// build request base
			$request = new GetAttachmentType();
			$request->AttachmentIds = new NonEmptyArrayOfRequestAttachmentIdsType;
			$attachment_request_id  = new RequestAttachmentIdType();
			$attachment_request_id->Id = $attachment->AttachmentId->Id;
			$request->AttachmentIds->AttachmentId[] = $attachment_request_id;
			$response = $client->GetAttachment( $request );
			$response_messages = $response->ResponseMessages->GetAttachmentResponseMessage;
			foreach ($response_messages as $order => $response_message) { 
				// Make sure the request succeeded.
				if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
					$code = $response_message->ResponseCode;
					$message = $response_message->MessageText;
					$errors++;
					if ( WP_DEBUG ) {
						error_log ( "WIC_Entity_Email_ActiveSync_Parse:parse_inbox could not execute getAttachment for message: $id -- server said $message."  );
					}
				// if so save it
				} else {
					$content_type = $attachment->ContentType;
					$content_type_array = explode ( '/', $content_type );
					self::save_attachment (   
						0, 		// this an inbox attachment 
						$id, 	// passed through 
						'',  	// not tracking $message_attachment_number, 
						WIC_Function_Utilities::wic_sanitize_file_name( $attachment->Name ),
						isset ( $content_type_array[0] ) ? trim( $content_type_array[0] ) : '',  // $attachment_type, 
						isset ( $content_type_array[1] ) ? trim( $content_type_array[1] ) : '',  // $attachment_subtype, 
						$response_message->Attachments->FileAttachment[0]->Content,  	// the attachment
						$attachment->ContentId, 	//$message_attachment_cid,
						$attachment->IsInline ? 'inline' :  'attachment'  
					);
				} // saving attachment branch		
			} // loop on response messages
		} // outer attachments loop
	} // close function


	public static function handle_gmail_attachment( $bytes, $cid, $decoded_data, $disposition, $filename, $gmail_attachment_id, $extended_message_id, $id, $subtype, $type ) {
		// need to use inline if spec inline
		if ( ! $disposition ) {
			$disposition = 'attachment';
		}

		$filename = WIC_Function_Utilities::wic_sanitize_file_name( $filename );


		if ( $gmail_attachment_id ) {
			// set up for API Access	
			$check_return = WIC_Entity_Email_OAUTH::check_user_auth();
			if ( !$check_return['response_code'] ) {
				if ( WP_DEBUG ) {
					error_log ( 'Failed to connect to Gmail in WIC_Entity_Email_Attachment::handle_gmail_attachment -- check_connect said: ' . $check_return['output'] );
				}
				die;
			} 
			// get access token 
			$access_token = $check_return['output'];
			// try to set it up in client -- should be perfect;
			try  { 
				$client = WIC_Entity_Email_OAUTH::get_client();
				$client->setAccessToken( $access_token );
			} catch ( Exception $e ) {
				if ( WP_DEBUG ) {
					error_log ( 'On setAccessToken,Gmail Client said in WIC_Entity_Email_Attachment::handle_gmail_attachment : ' . $e->getMessage );
				}
				die;
			}
			$service = new Google_Service_Gmail( $client );
			$attachment_response = $service->users_messages_attachments->get( 'me', $extended_message_id, $gmail_attachment_id );
			$decoded_data = WIC_DB_Email_Message_Object_Gmail::url_safe_base64_decode ( $attachment_response->data );
		}
		self::save_attachment ( 0, $id, '', $filename, $type, $subtype, $decoded_data, $cid, $disposition );
	}
	
	// called only by email-message-object (incoming object set up) in structure walker -- $p and $partno are imap message structure elements
	public static function handle_incoming_attachment(  $id, $p, $partno, &$decoded_data, $cid = '', $disposition = 'attachment' ) { 
	
		if ( ! $disposition ) {
			$disposition = 'attachment';
		}

		// array of type codes -- http://php.net/manual/en/function.imap-fetchstructure.php
		$attachment_types = array ( 'TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'MODEL', 'OTHER' );

		/*
		* first check if have a proper filename -- check in dparameters (s/b as in content-disposition MIME header) and parameters
		* https://tools.ietf.org/html/rfc2183 -- disposition header
		* https://en.wikipedia.org/wiki/MIME -- " Many mail user agents also send messages with the file name in the name parameter of the content-type header instead of the filename parameter of the content-disposition header."
    	*/
    	$file_name = '';
        if ( $p->ifdparameters ) {
        	if ( is_array ( $p->dparameters ) ) {
            	foreach ( $p->dparameters as $pair ) {
                	if ( strtolower ( $pair->attribute ) == 'filename' ) {
                    	$file_name = strtolower ( $pair->value );
                    	break;
              	  	}
            	}        	
        	}
        } 
        if ( $p->parameters && ! $file_name ) {	
        	if ( is_array ( $p->parameters ) ) {
            	foreach ( $p->parameters as $pair ) {
                	if ( strtolower ( $pair->attribute ) == 'name' ) {
                    	$file_name = strtolower ( $pair->value );
                    	break;
              	  	}
            	}        	
        	}
        } 

		// strip to safe ascii characters and enforce length restrictions; no mime_type checking
		$file_name = WIC_Function_Utilities::wic_sanitize_file_name( $file_name );

		self::save_attachment ( 0, $id, $partno, $file_name, strtolower( $attachment_types[$p->type] ), $p->ifsubtype ? sanitize_text_field( strtolower($p->subtype) ) : '', $decoded_data, $cid, $disposition   );

	}

	


	// called only on upload of attachments for draft
	public static function handle_outgoing_attachment ( &$file_content, $file_name, $draft_id ) {
	
		// rely on user integrity in not obfuscating uploaded file type
		$mime_content_type =  WIC_Function_Utilities::wic_mime_content_type_from_filename( $file_name );
		$split = explode ( '/', $mime_content_type );
		$attachment_type = trim( $split[0] );
		$attachment_subtype = trim( $split[1] );

		$attachment_id = self::save_attachment (
			1, 					// message is in the outbox, even as draft
			$draft_id,			// tested for in uploader
			'',					// number is vistigial and not reliable
			$file_name,			// has already been sanitized
			$attachment_type, 
			$attachment_subtype,
			$file_content,
			'',					// no cid
			'attachment' 		// not supporting inline images
		);
	
		if ( $attachment_id ) {
			return self::construct_attachment_list_item  ( $draft_id, $attachment_id, $file_name );
		} else {
			return false;
		}
	}

	// called by self and externally
	public static function save_attachment (   
		$message_in_outbox, 
		$message_id, 
		$message_attachment_number, 
		$message_attachment_filename, 
		$attachment_type, 
		$attachment_subtype, 
		&$attachment, 
		$message_attachment_cid,
		$message_attachment_disposition = 'attachment'  
		) {
	
		// set vars
		global $wpdb;
		$attachments_table = $wpdb->prefix . 'wic_inbox_image_attachments';	// this table includes all attachments, not just inbox attachments	
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';
		$attachment_md5 = md5 ( $attachment );	
		$attachment_size = strlen ( $attachment );
		$max_packet_size = WIC_Entity_Email_Inbox_Parse::get_max_packet_size();
		$saveable = ( $max_packet_size - 1000 < $attachment_size ); // saveable translates as true = unsaveable, too big!
		$attachment_id = false;	
				
		// does an identical attachment exist?
		$sql = $wpdb->prepare (
			"SELECT attachment_id FROM $attachments_xref_table WHERE attachment_md5 = %s LIMIT 0, 1",
			array( $attachment_md5)
		);
		$attachment_found_result = $wpdb->get_results ( $sql );
		if ( $attachment_found_result ) {
			$attachment_id =  $attachment_found_result[0]->attachment_id;
		} 

		// if none found, save it
		if ( ! $attachment_id ) {
		
			$sql = $wpdb->prepare ( 
				"
				INSERT INTO $attachments_table  
				( 
					attachment_size,
					attachment_type,
					attachment_subtype,
					attachment,
					attachment_saved
				) VALUES
				( %d,%s,%s,%s,%d )
				",
				array ( 
					$attachment_size,
					$attachment_type, 
					$attachment_subtype,					
					$saveable ? '' : $attachment, // do not actually save item if too big
					$saveable ? 0 : 1
				)
			);
			$result_main = $wpdb->query ( $sql );
			$attachment_id = $wpdb->insert_id;
			// return false if could not save
			if ( ! $result_main ) {
				return false;
			}
		}	
		
		// regardless, insert an XREF record
		
		$sql = $wpdb->prepare ( 
			"
			INSERT INTO $attachments_xref_table  
			( 
				attachment_id,
				attachment_md5,
				message_in_outbox,
				message_id,
				message_attachment_cid,
				message_attachment_number,
				message_attachment_filename,
				message_attachment_disposition 
			) VALUES
			( %d,%s,%d,%d,%s,%d,%s,%s )
			",
			array ( 
				$attachment_id,
				$attachment_md5,
				$message_in_outbox,
				$message_id,
				$message_attachment_cid,
				$message_attachment_number,
				$message_attachment_filename,
				$message_attachment_disposition
			)
		);
		$result_xref = $wpdb->query ( $sql );
		// return false if could not save
		if ( ! $result_xref ) {
			return false;
		}
		
		return $attachment_id;
		
	}

	// serves images and attachments (returns nothing if not found)
	public static function emit_stored_file ( $attachment_id, $message_id, $message_in_outbox ) {  

		// set vars
		global $wpdb;
		$attachments_table = $wpdb->prefix . 'wic_inbox_image_attachments';	// this table includes all attachments, not just inbox attachments	
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';	
		
		// retrieve file name for attachment used in current message	
		$sql_xref = $wpdb->prepare ( "SELECT message_attachment_filename, message_attachment_disposition FROM $attachments_xref_table WHERE attachment_id = %d AND message_id = %d AND message_in_outbox = %d", array ( $attachment_id, $message_id, $message_in_outbox ) );
		$result_xref = $wpdb->get_results ( $sql_xref );

		// retrieve the attachment
		$sql = $wpdb->prepare ( "SELECT attachment_type,  attachment_subtype, attachment FROM $attachments_table WHERE ID = %d", array ( $attachment_id ) );
		$result = $wpdb->get_results( $sql ); 
		// send the headrrs and attachment
		if ( $result && $result_xref ) {
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-type: {$result[0]->attachment_type}/{$result[0]->attachment_subtype}");
			header("Content-Disposition: {$result_xref[0]->message_attachment_disposition}; filename=\"{$result_xref[0]->message_attachment_filename}\"");
			header("Expires: 0");
			header("Pragma: public");

			$fh = fopen( 'php://output', 'w' ); 
			fwrite( $fh, $result[0]->attachment );
			fclose ( $fh );	
		} else {
			header('HTTP/1.0 404 Not Found');
		}
		exit;
	}
	
	public static function replace_cids_with_display_url( $html_body, $message_id, $message_in_outbox = 0 ) { 
	
		$attachments = self::get_message_attachments ( $message_id, $message_in_outbox );
		// replace cid or other matching source with url -- handling html body recursively
		foreach ( $attachments as $attachment ) {
			if ( $attachment->message_attachment_cid )  {
				$src_cid_string = '#src\s*=\s*(\'|")(cid:)?' . $attachment->message_attachment_cid . '\1#';
				$src_url = 'src="' . self::construct_attachment_url ( $message_id,  $attachment->attachment_id, $message_in_outbox ) . '"';
				$html_body = preg_replace ( $src_cid_string, $src_url, $html_body );
			}		
		}
		return $html_body;	
	}

	public static function construct_attachment_url ( $message_id, $attachment_id, $message_in_outbox ) {
		$bare_url = admin_url() . 'admin.php?page=wp-issues-crm-main&entity=email_attachment&message_id=' . $message_id . '&attachment_id=' . $attachment_id . '&message_in_outbox=' . $message_in_outbox;
		return wp_nonce_url( $bare_url, 'attachment_' . $attachment_id, 'attachment_nonce' );
	}

	// always invoked in outbox context
	private static function construct_attachment_list_item  ( $message_id, $attachment_id, $file_name )  {
		return '<li class="wic-attachment-list-item">
					<span title = "Delete attachment" class="dashicons dashicons-dismiss"></span>
					<span class="wic-attachment-list-item-message_id">' . $message_id . '</span> ' .
					'<span class="wic-attachment-list-item-attachment_id">' . $attachment_id . '</span> ' .
					'<a target = "_blank" href="' . self::construct_attachment_url ( $message_id, $attachment_id, 1 ) . '" >' 
						. $file_name . 
					'</a>
				</li>';
	}

	public static function generate_attachment_list( $draft_id ) {
		$list = '';
		$attachments = self::get_message_attachments ( $draft_id, 1, false );
		foreach ( $attachments as $attachment ) {
			$list .= self::construct_attachment_list_item ( $draft_id, $attachment->attachment_id, $attachment->message_attachment_filename );		
		} 
		return ( $list );
	}
	
	public static function get_message_attachments ( $message_id, $message_in_outbox, $get_attachment_body = false ) {
		// set vars
		global $wpdb;
		$attachments_table = $wpdb->prefix . 'wic_inbox_image_attachments';	// this table includes all attachments, not just inbox attachments	
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';
		$include_body = $get_attachment_body ? " attachment, " : '';	
		$sql = $wpdb->prepare (
			"
			SELECT x.*, attachment_size, attachment_type, attachment_subtype, $include_body attachment_saved 
			FROM $attachments_xref_table x INNER JOIN $attachments_table a ON x.attachment_id = a.ID 
			WHERE x.message_id = %d and x.message_in_outbox = %d
			",
			array ( $message_id, $message_in_outbox )
		);
		return $wpdb->get_results ( $sql );			
	}
	
	public static function link_original_attachments ( $old_message, $new_message ){

		// set vars
		global $wpdb;
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';
	
		$sql = $wpdb->prepare(
			"
				INSERT INTO $attachments_xref_table( attachment_id,attachment_md5,message_in_outbox,message_id,message_attachment_cid,message_attachment_number,message_attachment_filename, message_attachment_disposition )
				SELECT attachment_id,attachment_md5,1,%d,message_attachment_cid,message_attachment_number,message_attachment_filename, message_attachment_disposition FROM $attachments_xref_table WHERE message_id = %d
			",
			array ( $new_message, $old_message )
		);
		$wpdb->query ( $sql );
	
	}
	
	// from online request to delete an attachment from a message -- delete only the xref records if other messages if have the same attachment
	public static function delete_message_attachments ( $dummy, $xref ) {
		// set vars
		global $wpdb;
		$attachments_table = $wpdb->prefix . 'wic_inbox_image_attachments';	// this table includes all attachments, not just inbox attachments	
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';
		// delete the xref record; LIMIT to 1 in case user has uploaded same attachment twice and is deduping
		$sql = $wpdb->prepare ( 
			"DELETE FROM $attachments_xref_table WHERE attachment_id = %d AND message_ID = %d and message_in_outbox = 1 LIMIT 1", 
			array ( $xref->attachment_id, $xref->message_id ) 
		); 
		$wpdb->query ( $sql );		
		// check if there are any other xref records for the attachment
		$sql = $wpdb->prepare ( 
			"SELECT ID FROM $attachments_xref_table WHERE attachment_id = %d LIMIT 0,1", 
			array ( $xref->attachment_id ) 
		); 
		$result = $wpdb->get_results ( $sql );	
		// if not, delete the attachment
		if ( ! $result ) {
			$sql = $wpdb->prepare ( 
				"DELETE FROM $attachments_table WHERE ID = %d", 
				array ( $xref->attachment_id ) 
			);		
			$wpdb->query ( $sql );		
		}	
	}
	
	
} // close class