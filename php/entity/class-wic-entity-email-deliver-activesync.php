<?php
/*
*
*	wic-entity-email-deliver-activesync.php
*
*/
// this is the location of the copy of the php ews client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'activesync'  . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\Request\CreateItemType;
use \jamesiarmes\PhpEws\Request\CreateAttachmentType;
use \jamesiarmes\PhpEws\Request\SendItemType;
use \jamesiarmes\PhpEws\ArrayType\ArrayOfRecipientsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAllItemsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAttachmentsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\Enumeration\BodyTypeType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\MessageDispositionType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Type\BodyType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\EmailAddressType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\FileAttachmentType;
use \jamesiarmes\PhpEws\Type\MessageType;
use \jamesiarmes\PhpEws\Type\SingleRecipientType;
use \jamesiarmes\PhpEws\Type\TargetFolderIdType;

class WIC_Entity_Email_Deliver_Activesync extends WIC_Entity_Email_Deliver {

	/*
	* Mailer sends single messages with attachments. Single transaction per message.
	*
	* Control protocol designed to support running on near continuous two minute cycle, with pacing.
	*	(1) Time stamp a block of messages -- not recently time stamped 
	*	(2) Count messages = (100 seconds / ( delay + 1 ) ) -- should get all done within two minutes
	*	(3) Loop through those with time stamp in ascending ID order 1 x 1 doing sends and marking as sent
	*	(4) Select messages for block based on any existing time stamp 2 minutes old.
	*	(5) Job is time limited to 2 minutes, so anything over 120 seconds old was a failure and/or won't be reached.
	*
	* FAVORING RELIABILITY OVER SPEED, ASSUMING MOST SEND CYCLES ARE << $message_page_size
	*/
	// initiated only as scheduled cron -- see wp-issues-crm.php
	public static function process_message_queue () {

		WIC_Entity_Email_Cron::log_mail ( 'Starting WIC_Entity_Email_Deliver_Activesync::process_message_queue.' );

		// set variables
		$folder = WIC_Entity_Email_Account::get_folder();
		$time_stamp = time(); 									// epoc in whole numbers
		$end_time = time() + 100; 								// 120 seconds less overhead
		$wic_settings = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$delay = $wic_settings['send_mail_sleep_time']; 	 	// minimum inter message delay in milliseconds
		// set message page size from options delay or config -- defines set of messages that send will be attempted for
		if ( defined( 'WP_ISSUES_CRM_MESSAGES_SENT_PER_ROTATION' ) && WP_ISSUES_CRM_MESSAGES_SENT_PER_ROTATION ) {
			$message_page_size = WP_ISSUES_CRM_MESSAGES_SENT_PER_ROTATION;
			$start_time_stamp = $time_stamp - WP_ISSUES_CRM_CRON_INTERVAL;
		} else {
			$message_page_size = floor( 100 / ( 1 + ( $delay / 1000 ) ) ); 	// compute message page size -- number to be sent in this run
			$start_time_stamp = $time_stamp - 120;
		}
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];

		// reserve a page of messages for processing
	 	global $wpdb;
		$outbox_table = $wpdb->prefix . "wic_outbox";
		$sql = 
			"
			UPDATE $outbox_table 
			SET attempted_send_time_stamp = $time_stamp 
			WHERE 
				attempted_send_time_stamp < $start_time_stamp AND
				sent_ok = 0 AND 
				held = 0 AND 
				is_draft = 0
				ORDER BY ID
				LIMIT $message_page_size
			"		
		;
		$result = $wpdb->query( $sql );
		if ( ! $result ) {
			WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver_Activesync::process_message_queue -- found no messages to process.' );
			return true; // done
		} else {
			$finish_this_round = ( $result < $message_page_size );
		}
		
		// get client 
    	$response = WIC_Entity_Email_ActiveSync::get_connection();
    	if ( ! $response['response_code'] ) {
    		WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver_Activesync::process_message_queue -- could not connect. ' . $response['output'] );
    		return true; // do not continue to look for a client
    	} else {
    		$client = $response['output'];
    	}
		
		/*
		* loop while there are records in the selected page
		*/
		$current_id = 0;
		while ( 
			time() < $end_time &&
			$outbox_message = self::get_next_queued_message( $time_stamp, $current_id ) 
			) 
			{
			// set $current_id to move the queue along
			$current_id = $outbox_message->ID;		
			// test to see if OK to continue running
			// in php string 0 is false https://www.php.net/manual/en/language.types.boolean.php#language.types.boolean.casting
			if ( ! get_option ( 'wp-issues-crm-mailer-ok-to-go', 1 ) ) {
				WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver_Activesync::process_message_queue -- mailer suspended.');
				return true; // don't retry
			}

			/*
			*
			* STEP ONE, BUILD THE BASE MESSAGE AND SAVE IT AS A DRAFT
			*
			* CANNOT ONE STEP THE CREATION AND THE ADD OF ATTACHMENTS 
			* https://stackoverflow.com/questions/31828411/error-about-createitem-operation-with-attachment
			*
			*/

			// build the message request for sending
			$request = new CreateItemType();
			$request->Items = new NonEmptyArrayOfAllItemsType();
			// specify that will save the message, but not send it.
			$request->MessageDisposition = MessageDispositionType::SAVE_ONLY;
			// Create the message.
			$ews_message = new MessageType();
			// set the subject
			$ews_message->Subject = $outbox_message->subject;
			// Set the sender.
			$ews_message->From = new SingleRecipientType();
			$ews_message->From->Mailbox = new EmailAddressType();
			$ews_message->From->Mailbox->EmailAddress = $form_variables_object->activesync_email_address;
			$ews_message->From->Mailbox->Name = $form_variables_object->activesync_sender_name;

			// Set the recipients.
			$ews_message->ToRecipients = new ArrayOfRecipientsType();
			foreach ( $outbox_message->to_array as $address ) {
				$recipient = new EmailAddressType();
				$recipient->Name = $address[0];
				$recipient->EmailAddress = $address[1];
				$ews_message->ToRecipients->Mailbox[] = $recipient;
			}
			$ews_message->CcRecipients = new ArrayOfRecipientsType();
			foreach ( $outbox_message->cc_array as $address ) {
				$recipient = new EmailAddressType();
				$recipient->Name = $address[0];
				$recipient->EmailAddress = $address[1];
				$ews_message->CcRecipients->Mailbox[] = $recipient;
			}
			$ews_message->BccRecipients = new ArrayOfRecipientsType();
			foreach ( $outbox_message->bcc_array as $address ) {
				$recipient = new EmailAddressType();
				$recipient->Name = $address[0];
				$recipient->EmailAddress = $address[1];
				$ews_message->BccRecipients->Mailbox[] = $recipient;
			}

			// Set the message body.  No Alt body?
			$ews_message->Body = new BodyType();
			$ews_message->Body->BodyType = BodyTypeType::HTML;
			$ews_message->Body->_  = $outbox_message->html_body;

			// Add the message to the request.
			$request->Items->Message[] = $ews_message; 
			$response = $client->CreateItem($request);
			// Iterate over the results, printing any error messages.
			$response_messages = $response->ResponseMessages->CreateItemResponseMessage;
			$new_message_id = false;
			foreach ($response_messages as $response_message) {
				// Make sure the request succeeded.
				if ( $response_message->ResponseClass != ResponseClassType::SUCCESS ) {
					$code = $response_message->ResponseCode;
					$message = $response_message->MessageText;
					WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_Deliver_Activesync::process_message_queue error: Message re {$outbox_message->subject} failed to create with \"$code: $message\"\n" );
					continue 2; // NO MORE PROCESSING FOR THIS MESSAGE -- GO TO NEXT MESSAGE IN WHILE LOOP
				}
				// Iterate over the created messages capturing ID and change key . . . should be only one
				foreach ($response_message->Items->Message as $item) {
					$new_message_id = $item->ItemId->Id;
					$change_key = $item->ItemId->ChangeKey;
				}
			}
			/*
			*
			* STEP TWO, ADD ANY ATTACHMENTS TO THE KNOWN MESSAGGE
			*
			*/
			if ( ! $new_message_id ) {
				WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_Deliver_Activesync::process_message_queue error: Message re {$outbox_message->subject} failed to return a new message id on draft creation." );
				continue;
			}
			if ( $attachments = WIC_Entity_Email_Attachment::get_message_attachments ( $outbox_message->ID, 1, true ) ) { // message in out box, fetch whole body
				// if really have an attachment that was saved, not just xref, make a single, unbatched add request (just in case two attachments are large, don't wan't over large request body)
				foreach ( $attachments as $attachment ) {
					if ( $attachment->attachment_saved ) { // just by pass any attachments for which size limits were exceeded or file name was bad at time of save
						// Build the attachment add request,
						$request = new CreateAttachmentType();
						$request->ParentItemId = new ItemIdType();
						$request->ParentItemId->Id = $new_message_id;
						$request->Attachments = new NonEmptyArrayOfAttachmentsType();
						// Build the file attachment.
						$ews_attachment 			= new FileAttachmentType();
						$ews_attachment->IsInline 	= ( 'inline' == $attachment->message_attachment_disposition );
						$ews_attachment->Content 	= $attachment->attachment; // WIC_DB_Email_Message_Object_Gmail::url_safe_base64_encode( $attachment->attachment );
						$ews_attachment->Name 		= $attachment->message_attachment_filename;
						$ews_attachment->ContentType =$attachment->attachment_type . ( $attachment->attachment_subtype ?  ( '/' . $attachment->attachment_subtype ) : '' ); // type/subtype
						$ews_attachment->ContentId 	= $attachment->message_attachment_cid ? $attachment->message_attachment_cid : $attachment->message_attachment_filename;					
						$request->Attachments->FileAttachment[] = $ews_attachment; 
						// execute the request and check response					
						$response = $client->CreateAttachment($request);
						// Iterate over the results, logging any error
						$response_messages = $response->ResponseMessages->CreateAttachmentResponseMessage;
						foreach ($response_messages as $response_message) {
							// Make sure the request succeeded. If not, just log the failure, do not halt processing
							if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
								$code = $response_message->ResponseCode;
								$message = $response_message->MessageText;
								WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_Deliver_Activesync::process_message_queue error: Message re {$outbox_message->subject}, failed to add attachment with \"$code: $message\"\n" );
								continue;
							}
							// extract latest change ID -- keep overwriting the change id from the first step -- use last found
							// . . .  actually expecting only one value per attachment, but following the pattern . . .
							foreach ( $response_message->Attachments->FileAttachment as $attachment ) {
								$change_key = $attachment->AttachmentId->RootItemChangeKey;
							} // attachment id for
						} // response array for					
					} //attachment saved if					
				} // for attachments
			} // if any attachments
			/*
			*
			* STEP THREE, ACTUALLY SEND THE MESSAGE
			*
			*/				
			// Build the request.
			$request = new SendItemType();
			$request->SaveItemToFolder = true;
			$request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
			// Add the message to the request.
			$item = new ItemIdType();
			$item->Id = $new_message_id;
			$item->ChangeKey = $change_key;
			$request->ItemIds->ItemId[] = $item;
			// Configure the folder to save the sent message to.
			$send_folder = new TargetFolderIdType();
			$send_folder->DistinguishedFolderId = new DistinguishedFolderIdType();
			$send_folder->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::SENT;
			$request->SavedItemFolderId = $send_folder; 
			$response = $client->SendItem($request);
			// Iterate over the results, printing any error messages.
			$response_messages = $response->ResponseMessages->SendItemResponseMessage;
			foreach ($response_messages as $response_message) {
				// Make sure the request succeeded.
				if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
					$code = $response_message->ResponseCode;
					$message = $response_message->MessageText;
					WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_Deliver_Activesync::process_message_queue error: Message re {$outbox_message->subject} -- could not send saved draft with \"$code: $message\"\n", true );
					continue;
				}
				self::unqueue_message ( $current_id );
			}

			// delay at end of loop for pacing 
			if ( defined( 'WP_ISSUES_CRM_MESSAGE_SEND_DELAY' ) && WP_ISSUES_CRM_MESSAGE_SEND_DELAY ) {
				usleep ( WP_ISSUES_CRM_MESSAGE_SEND_DELAY * 1000 );
			} else {
				usleep ( $delay * 1000 ); //  usleep argument is in microseconds -- setting labeled in milliseconds
			}
		}	// close while loop			
	
		WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver_Activesync::process_message_queue -- normal termination.' );
		// if did not find a full page of records ( i.e., $finish_this_round os stire ), return telling cron process that done
		return $finish_this_round; 
		// note could reach this return because of time out on a short page and return true (done) when should come back,
		// . . . but that is not likely and probably a throttle condition anyway
		
	} // close process_message_queue()

}