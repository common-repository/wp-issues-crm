<?php
/*
*
*	wic-entity-email-deliver.php
*
*/
// this is the location of the copy phpMailer copied with WP Issues CRM -- higher version than WP to support oauth
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'phpmailer6' . DIRECTORY_SEPARATOR .  'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\OAuth;
// Alias the League Google OAuth2 provider class
use League\OAuth2\Client\Provider\Google;

class WIC_Entity_Email_Deliver {
	
	/*
	* Notes re queuing model for email sends -- replies and new sends
	*
	* The act of replying to a message (or sending new) creates a new outbox record with a serialized email object, processed below.
	* 	Additionally, it creates an activity record of type wic_reserved_99999999.
	*
	* Mailer sends single messages with attachments. Single transaction per message.
	*
	* Control protocol designed to support running on near continuous two minute cycle, with pacing.
	*	(1) Time stamp a block of messages -- not recently time stamped; size reasonable to expect within two minutes (but if not, no harm)
	*	(2) Count messages = (100 seconds / ( delay + 1 ) ) -- should get all done within two minutes
	*	(3) Loop through those with time stamp in ascending ID order 1 x 1 doing sends and marking as sent
	*	(4) Select messages for block based on any existing time stamp 2 minutes old.
	*	(5) Job is time limited to 2 minutes, so anything over 120 seconds old was a failure and/or won't be reached and be safely restamped by next run > 
	*
	* FAVORING RELIABILITY OVER SPEED, NO SPEED LOST ASSUMING MOST SEND CYCLES ARE < $message_page_size
	*
	*/
	private static function set_up_mailer() {
        // https://github.com/PHPMailer/PHPMailer/blob/master/examples/mailing_list.phps
        // https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting
		try { // try catch example https://github.com/PHPMailer/PHPMailer/blob/master/examples/exceptions.phps
			ob_start();

			// use wordpress set zone unless not set to a city
			date_default_timezone_set( get_option('timezone_string') > '' ? get_option('timezone_string') : 'Etc/UTC' );

			$mail = new PHPMailer( true ); // true means throw exceptions
		
			// get settings
			$wic_settings = get_option( 'wp_issues_crm_plugin_options_array' );
			$send_account = WIC_Entity_Email_Account::get_send_account();
			$mail_tool = 'legacy' == $send_account ? $wic_settings['email_send_tool'] : $send_account; 
		
			// apply settings relevant to mail send tool
			switch ( $mail_tool ) {
				case 'smtp':
					$mail->setFrom 	  ( $wic_settings['from_email'] ? $wic_settings['from_email'] : $wic_settings['smtp_user'], $wic_settings['from_name'] );
					$mail->isSMTP();
					$mail->Host 		= $wic_settings['email_smtp_server']; // mail.example.com
					$mail->Port 		= $wic_settings['smtp_port']; 		// 587 or 25 with tls or 465 for ssl
					$mail->SMTPSecure 	= $wic_settings['smtp_secure']; 		// tls or ssl or not; (use tls)
					$mail->SMTPAutoTLS 	= false; // always -- don't jump into TLS mode by accident
					$mail->SMTPAuth 	= true; 							// always
					$mail->Username 	= $wic_settings['smtp_user']; 		// mail_user@example.com;
					$mail->Password 	= WIC_Entity_Email_Settings::get_parms()->o;
					if ( 'no' == $wic_settings['require_good_ssl_certificate'] ) {
						$mail->SMTPOptions = array(							// accept unsigned certificates
							'ssl' => array(
								'verify_peer' => false,
								'verify_peer_name' => false,
								'allow_self_signed' => true
							)
						);		
					} elseif ('selfOK' == $wic_settings['require_good_ssl_certificate'] ){
						$mail->SMTPOptions = array(
							'ssl' => array(
								'allow_self_signed' => true,
								'peer_name' => ( $wic_settings['peer_name'] > '' ? $wic_settings['peer_name'] : $wic_settings['email_smtp_server'] ) ,
							)
						);
					} // if not either of the lower level security options, go with defaults 

					if ( 'yes' == $wic_settings['use_IPV4'] ) {
						$mail->Host = gethostbyname ( $wic_settings['email_smtp_server'] );
					}

					break;
				case 'mail':
					$mail->setFrom 	  ( $wic_settings['from_email'] ? $wic_settings['from_email'] : $wic_settings['smtp_user'], $wic_settings['from_name'] );
					$mail->IsMail();
					break;
				case 'sendmail':
					$mail->setFrom 	  ( $wic_settings['from_email'] ? $wic_settings['from_email'] : $wic_settings['smtp_user'], $wic_settings['from_name'] );
					$mail->isSendmail();
					break;
				case 'gmail':
					// the user that gave consent
					$gmail_parms = get_option ( 'wp-issues-crm-gmail-connect' );
					if (  ! $gmail_parms || !isset ( $gmail_parms->email_address ) ) {
						throw new Exception( 'Missing gmail parms or no gmail address.  Go to inbox mail settings and reconnect to gmail.' );
					}
					$email = $gmail_parms->email_address;
					$mail->setFrom 	  ( $email, isset (  $wic_settings['from_name'] ) ?  $wic_settings['from_name'] : '' );

					//Tell PHPMailer to use SMTP
					$mail->isSMTP();
					//Set the hostname of the mail server
					$mail->Host = 'smtp.gmail.com';
					//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
					$mail->Port = 587;
					//Set the encryption system to use - ssl (deprecated) or tls
					$mail->SMTPSecure = 'tls';
					//Whether to use SMTP authentication
					$mail->SMTPAuth = true;
					//Set AuthType to use XOAUTH2
					$mail->AuthType = 'XOAUTH2';
					//Get credentials
					$credentials = json_decode ( file_get_contents ( WIC_Entity_Email_OAUTH::get_oauth_credentials_path() ) );
					$clientId = $credentials->web->client_id;
					$clientSecret = $credentials->web->client_secret;
					$refreshTokenPackage = WIC_Entity_Email_OAUTH::check_user_auth();
					if ( ! $refreshTokenPackage['response_code'] ) {
						throw new Exception(  $refreshTokenPackage['output'] );
					}
					//Create a new OAuth2 provider instance
					$provider = new Google(
						[
							'clientId' => $clientId,
							'clientSecret' => $clientSecret,
						]
					);
					//Pass the OAuth provider instance to PHPMailer
					$mail->setOAuth(
						new OAuth(
							[
								'provider' => $provider,
								'clientId' => $clientId,
								'clientSecret' => $clientSecret,
								'refreshToken' => isset ( $refreshTokenPackage['output']['refresh_token'] ) ? $refreshTokenPackage['output']['refresh_token'] : $refreshTokenPackage['output']['access_token'],
								'userName' => $email,
							]
						)
					);
					break;
			}
		
			// settings applicable to all tools
			$mail->SMTPDebug= $wic_settings['smtp_debug_level']; 	
			$mail->Debugoutput = 'html';
			$mail->CharSet = 'utf-8'; // this is wishful thinking -- after layers of phpmailer and smtp server, this can come out as ascii or possibly iso-8859								
			if ( $wic_settings['smtp_reply'] ) {
				$mail->addReplyTo( $wic_settings['smtp_reply'], $wic_settings['reply_name'] );
			}
			ob_end_clean();
			return ( $mail );

		}  catch (phpmailerException $e) {
			ob_end_clean();
    		return ( $e->errorMessage() ); 	// error messages from PHPMailer
		} catch (Exception $e) {
			ob_end_clean();
    		return ( $e->getMessage() ); 	// error messages from anything else!
		}

	}
	
	
	// send test email from settings screen
	public static function test_settings ( $dummy, $email_address ) {

		// get a mailer
		$mail = self::set_up_mailer();
		
		$output			= 'success';
		if ( ! is_object( $mail ) ) { 
			return array ( 'response_code' => true, 'output' => '<br/><strong>Error in mailer setup.</strong><br/>' . $mail );
		} else {
			// set up output buffer to capture debug dialog 
			ob_start();
			try {
				// set up generic test settings
				$mail->Subject = 'Test Email from WP Issues CRM';
				$body = 
					'<h3>Great job!</h3><p>Your settings are good.</p><br/>' . 
					'<p>You have successfully sent a test email from WP Issues CRM.</p>' .
					'<p>Reply to this message to test your reply email setting.</p>';
				$mail->msgHTML($body);	
				$mail->addAddress( $email_address, $email_address ); 	

				// send mail	 
				$mail->send();  
			} catch ( phpmailerException $e ) { 
				$output = '<br/><strong>Not successful. </strong><br/><br/>'. $e->errorMessage() .  ob_get_contents() ; 	
			} catch (Exception $e) {
				$account = WIC_Entity_Email_Account::get_send_account();
				$output = '<br/>Not successful -- general error.'; 
				if ( 'legacy' != $account && 'gmail' != $account ) {
					$output .= '<br/><br/>Please set either Gmail or "Any account" as "Send Mail via" in mail Controls (from the inbox, not from these Configure tabs) in order to make this test applicable.';
				}
				$output .= '<br/><br/>'. $e->getMessage() .  ob_get_contents() ; 	
			}
			ob_end_clean();
		}
		
		return array ( 'response_code' => true, 'output' => $output );
	}


	/*
	*
	*  Mailer control functions
	*/	
	public static function get_mailer_status( $dummy1, $dummy2 ) {
		return array ( 'response_code' => true, 'output' => get_option ( 'wp-issues-crm-mailer-ok-to-go', 1 ) );  // start with mailer released if have not set option
	} 

	public static function set_mailer_status( $dummy, $new_status ) {
		// use 1/0 -- note that in update_option if value = false and no option https://core.trac.wordpress.org/ticket/40007
		update_option ( 'wp-issues-crm-mailer-ok-to-go', $new_status ? 1 : 0 ); 
		return self::get_mailer_status( '', '' ); // return truth, not theory, about value!
	} 

	// initiated only as scheduled cron -- see wp-issues-crm.php
	public static function process_message_queue () {
	
		WIC_Entity_Email_Cron::log_mail ( 'WP Issues CRM cron run: Starting process_message_queue.' );

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
			$start_time_stamp = $time_stamp - 120; // if next job starts < 120 seconds later, will bypass this job's stamped messages; if > 120 seconds, this job already stopped b/c time limited at 100
		}

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
			WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver::process_message_queue -- found no messages to process.' );
			return true; // done
		} else {
			$finish_this_round = ( $result < $message_page_size );
		}
		
		// get mailer 
		$mail = self::set_up_mailer();
		if ( !is_object ( $mail ) ) {
    		WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver::process_message_queue -- could not setup mailer. set_up_mailer returned: ' . $mail );
    		return true; // do not continue to look for a client
    	} 

		// use persistent setting unless elect not to (close at end based on option too)
		$mail->SMTPKeepAlive = true;
		// generate limited debugging
		$mail->SMTPDebug = 0;
		
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
				WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver::process_message_queue -- mailer suspended.');
				$mail->smtpClose();
				return true; // don't retry
			}
			
			// attempt to send it
			try {
				// set up output buffer to capture debug dialog 
				ob_start();
				/*
				* set addresses
				* note that mailer will bounce an unset to address and will capture below
				*/
				foreach ( $outbox_message->to_array as $address ) {
					$mail->addAddress( $address[1], $address[0] ); // adds TO address
				}
				foreach ( $outbox_message->cc_array as $address ) {
					$mail->addCC( $address[1], $address[0] );
				}
				foreach ( $outbox_message->bcc_array as $address ) {
					$mail->addBCC( $address[1], $address[0] );
				}
				/*
				*
				* subject and body
				*
				*/				
				$mail->Subject = $outbox_message->subject;
				$mail->msgHTML( $outbox_message->html_body );
				$mail->AltBody = $outbox_message->text_body;  // For short notes, this is better than phpMailer html to text conversion	
				/*
				*
				* add any attachments 
				*
				* note that if inbox has been purged, will not find attachments; no risk of wrong attachments -- links deleted on purge
				*
				* http://phpmailer.github.io/PHPMailer/classes/PHPMailer.PHPMailer.PHPMailer.html#method_addStringAttachment
				*/
				if ( $attachments = WIC_Entity_Email_Attachment::get_message_attachments ( $outbox_message->ID, 1, true ) ) { // message in out box, fetch whole body
					foreach ( $attachments as $attachment ) {
						if ( $attachment->attachment_saved ) { // just by pass any attachments for which size limits were exceeded or file name was bad at time of save
							if ( 'inline' == $attachment->message_attachment_disposition ) {
								$mail->addStringEmbeddedImage( 
									$attachment->attachment, // BLOB content
									$attachment->message_attachment_cid, // original -- unaltered in html_body
									$attachment->message_attachment_filename, // as parsed from image
									'base64', // default transfer encoding
									$attachment->attachment_type . ( $attachment->attachment_subtype ?  '/' . $attachment->attachment_subtype : '' ), // type/subtype
									'inline' // disposition
								);	
							} else {
								$mail->addStringAttachment( 
									$attachment->attachment, // BLOB content
									$attachment->message_attachment_filename, 
									'base64', // default transfer encoding
									$attachment->attachment_type . ( $attachment->attachment_subtype ?  '/' . $attachment->attachment_subtype : '' ), // type/subtype
									'attachment' // disposition
								);
							}
						}					
					}
				}			

	
				// send mail	 
				$mail->send();  
				$response = 'success';
			} catch ( phpmailerException $e ) { 
				$response = 'Not successful.' . $e->errorMessage() .  ob_get_contents() ; 	
			} catch (Exception $e) {
				$response = 'Not successful.' . $e->getMessage() .  ob_get_contents() ; 	
			}
			ob_end_clean();
			
			// if successful, take it off the queue by changing activity_type
			if ( 'success' == $response ) {
				self::unqueue_message ( $current_id ); 
			} else {
				// note -- if fail, just log and continue -- will not touch this id again in this cycle
				WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_Deliver::process_message_queue error: Message re {$outbox_message->subject} -- could not send saved draft.  Reported error: $response" );
			}
			
			// clear addresses, https://phpmailer.github.io/PHPMailer/classes/PHPMailer.html#method_clearAddresses 
			$mail->clearBCCs(); // bcc addresses
			$mail->clearCCs(); // cc addresses
			$mail->clearAddresses(); // To Addressses
			$mail->clearAttachments(); 
			

			// delay at end of loop for pacing 
			if ( defined( 'WP_ISSUES_CRM_MESSAGE_SEND_DELAY' ) && WP_ISSUES_CRM_MESSAGE_SEND_DELAY ) {
				usleep ( WP_ISSUES_CRM_MESSAGE_SEND_DELAY * 1000 );
			} else {
				usleep ( $delay * 1000 ); //  usleep argument is in microseconds -- setting labeled in milliseconds
			}
		}				

		WIC_Entity_Email_Cron::log_mail ( 'Ending WIC_Entity_Email_Deliver::process_message_queue -- normal termination.' );
		// close connection
		$mail->smtpClose();
	
		// if did not find a full page of records ( i.e., $finish_this_round ), return telling cron process that done; otw tell cron process there is more
		return $finish_this_round; 
		// note could reach this return because of time out on a short page and return true (done) when should come back,
		// . . . but that is not likely and probably a throttle condition anyway	

	} // close process_message_queue()


	// retrieve next queued message and stamp it as attempted.
	protected static function get_next_queued_message( $time_stamp, $current_id ) {
		global $wpdb;
		$outbox_table = $wpdb->prefix . "wic_outbox";
		// get next queued among the page of stamped messages
		$sql_next = "
			SELECT ID, serialized_email_object from $outbox_table  
			WHERE ID > $current_id 
			AND attempted_send_time_stamp = $time_stamp 
			ORDER BY ID
			LIMIT 0, 1
			";
		$results = $wpdb->get_results ( $sql_next );
		if ( ! $results ) {
			return ( false );
		} else {
			$message = unserialize ( $results[0]->serialized_email_object );
			$message->ID = $results[0]->ID;
			return $message;
		}
	}

	// move message from send queue to sent record 
	protected static function unqueue_message ( $id ) {
		global $wpdb;
		$outbox_table = $wpdb->prefix . "wic_outbox";
		$sql = $wpdb->prepare ( "UPDATE $outbox_table SET sent_ok = 1, sent_date_time = %s WHERE ID = %d", array( current_time( 'mysql'), $id ) ); 
		$wpdb->query ( $sql );
		return true;
	}
	
	
}