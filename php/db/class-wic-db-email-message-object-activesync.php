<?php
/*
* 	class-wic-db-email-message-object-activesync.php
*
*	takes an email (identified by imap_stream and UID) and extracts and sanitizes results
*	
*	see classification of outcome codes	
*
*   this object is saved as serialized by the parse process and is retrieved by the inbox detail view and by the email processor
*
*/

class WIC_DB_Email_Message_Object_Activesync extends WIC_DB_Email_Message_Object {
	
	// additional properties for classification
	public $extended_message_id;
	public $internet_headers = array();
	public $sender_personal;
	public $sender_email;
	public $sender_domain;
	
	public function build_from_activesync_payload ( $id, $extended_message_id, $payload, $max_packet_size ) {

		$this->extended_message_id = $extended_message_id;
		$this->inbox_image_id = $id; 	// just for reference -- invariant
		$this->account_thread_id = $payload->ConversationId->Id;
		$this->uid_still_valid = true; 	// no need to UID still valid checking as in IMAP since already cleaned up messages
		$this->max_packet_size = $max_packet_size < 16000000 ? $max_packet_size : 16000000; // waste no room for overhead -- better to downsize in parse_messages if necessary 
	
		// scan headers for key elements and repack sanitized 
		$charset = '';
		if ( isset ( $payload->InternetMessageHeaders ) ) {
			foreach ( $payload->InternetMessageHeaders->InternetMessageHeader as $header ) {
				// sanitize the headers -- the values, at least, can come in with mysql unsafe high plane characters
				$safe_key 	= strtolower( self::sanitize_incoming($header->HeaderName ) );
				$safe_value = self::sanitize_incoming( $header->_ );
				$this->internet_headers[$safe_key] = $safe_value;
				switch ( $safe_key ) {
					case 'subject':
						$this->subject = apply_filters ( 'wp_issues_crm_local_subject_pre_filter', $safe_value );	
						break;
					case 'date':
						$this->raw_date = $safe_value;
					// not sure this ever gets charset . . . MAY NEED TO PARSE FROM HTML
					case 'content-type':
						$matches = array();
						if ( preg_match ( '#charset= {0,3}("|\')?([\w-]{3,20})\1#', $header->_, $matches ) ) {
							$charset = strtolower( $matches[2] );
						}
						break;
				}
			}
		} else {
			$this->subject = '';
			$this->raw_date = '';
		}
		
		// populate addressee info
		if ( is_object ($payload->ToRecipients ) ) {
			$this->to_personal			= self::exchange_mail_address_to_clean_array( $payload->ToRecipients->Mailbox[0] )[0];
			$this->to_email				= self::exchange_mail_address_to_clean_array( $payload->ToRecipients->Mailbox[0] )[1];		
			$this->to_array = self::repack_exchange_email_address_array ( $payload->ToRecipients->Mailbox );
		}
		if ( is_object ($payload->From ) ) {
			$this->from_personal		= self::exchange_mail_address_to_clean_array( $payload->From->Mailbox )[0];
			$this->from_email			= self::exchange_mail_address_to_clean_array( $payload->From->Mailbox )[1];
			$this->from_domain			= $this->domain_only ( $this->from_email );
		}
		if ( is_object ($payload->ReplyTo ) ) {
			$this->reply_to_personal	= self::exchange_mail_address_to_clean_array( $payload->ReplyTo->Mailbox[0] )[0];
			$this->reply_to_email		= self::exchange_mail_address_to_clean_array( $payload->ReplyTo->Mailbox[0] )[1];
		}
		if ( is_object ($payload->Sender ) ) {		
			$this->sender_personal		= self::exchange_mail_address_to_clean_array( $payload->Sender->Mailbox )[0];
			$this->sender_email			= self::exchange_mail_address_to_clean_array( $payload->Sender->Mailbox )[1];	
			$this->sender_domain		= $this->domain_only ( $this->sender_email );
		}
		if ( is_object ($payload->CcRecipients ) ) {		
			$this->cc_array = self::repack_exchange_email_address_array ( $payload->CcRecipients->Mailbox );
		}
		// sanitize_date ( will be blank if cannot process )
		$this->activity_date = WIC_Control_Date::sanitize_date( $this->raw_date );		
		$this->email_date_time = self::sanitize_date_time( $this->raw_date );				// now extract body parts	

		// before stripping body of headers, etc., check for charset header -- note that even a dom based approach to identifying meta tags requires regex to extract charset from Content header
		// alt approach would be : https://stackoverflow.com/questions/3711357/getting-title-and-meta-tags-from-external-website, but this is much more processing and adds little value
		$matches = array();
		if ( ! $charset ) {
			if ( preg_match ( '/(?i)<meta[^>]+charset=[\'\" ]{0,3}([\w-]{3,20})\b/', $payload->Body->_, $matches ) ) {
				$charset = strtolower( $matches[1] ); 
			}
		}	
		// identify body type
		$subtype = trim ( strtolower ( $payload->Body->BodyType ) );
		$subtype = 'text' == $subtype ? 'plain' : $subtype; // exchange uses text to refer plain text
		if ( in_array( $subtype, array ( 'html', 'plain' ) ) ) {
			 $decoded_data = $payload->Body->_;
		} else {
			$decoded_data = "MESSAGE FORMAT '$subtype' NOT SUPPORTED\N\N" . $payload->Body->_;
			$subtype = 'plain';
		}

		// populate body info 
		self::handle_retrieved_body ( $charset, $subtype, $decoded_data, false ); // false says will take first text body as text_body, but only one anyway
		$this->populate_body_info();
		
		// handle attachments
		if ( $payload->HasAttachments || ( is_object ( $payload->Attachments ) && $payload->Attachments->FileAttachment ) ) { // the first field is not set if all attachments are dispo inline, so have to directly check the second (which is not always set )
			WIC_Entity_Email_Attachment::handle_activesync_attachments( $this->inbox_image_id, $payload->Attachments );		
		}
		
		// do address extraction and build non_address_block md5 map
		$this->extract_address();	
		unset ( $this->text_body ); // no downstream use.
	}

	// assuming all decoded
	private function exchange_mail_address_to_clean_array ( $exchange_mail_object ) {
		$sanitized_email_address = sanitize_text_field ( $exchange_mail_object->EmailAddress );
		return array (
			sanitize_text_field ( $exchange_mail_object->Name ),
			filter_var( $sanitized_email_address, FILTER_VALIDATE_EMAIL ) ? $sanitized_email_address : '',
			self::quick_check_email_address( $sanitized_email_address )
		);
	}

	private function repack_exchange_email_address_array ( $exchange_mail_object_array ) {
		$repacked = array();
		if ( $exchange_mail_object_array ) {
			foreach ( $exchange_mail_object_array as $exchange_mail_object ) {
				$repacked[] =  self::exchange_mail_address_to_clean_array ( $exchange_mail_object );
			}
		}
		return $repacked;
	}	
} // class 