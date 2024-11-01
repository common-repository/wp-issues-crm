<?php
/*
*
*	wic-entity-email-activesync-parse.php
*
*	(1) gets message details for message IDs via ActiveSync
*   (2) parses and stores messages in standard inbox object
*
*	Gets messages for steps 1 and 2 in groups of 10
*	Timed to run every two minutes; stops work before running out of time
*/

// this is the location of the copy of the php ews client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'activesync'  . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\Request\GetItemType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfPathsToElementType;
use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\MapiPropertyTypeType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use \jamesiarmes\PhpEws\Type\PathToExtendedFieldType;

Class WIC_Entity_Email_ActiveSync_Parse {


	public static function parse_inbox() { 
		
		WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync_Parse:parse_inbox -- starting.' );

		// set time limit for job
		// two minutes less some Wordpress set up time to get here and some time for last fetch of bodies and parse
		$max_allowed_time = 110; 
		/* 
		* not actually setting max_execution time at any level -- want as much time as possible in case trigger new synch.  
		* but don't want two of these jobs running at once -- they will compete for the same records are double work them ( no harm, but wasteful )
  		* stopping only the parsing -- job may continue to partial or full synch without time limit
  		*
  		* sequencing of synch after parse assures that synch will see
  		*/
  		$cut_off_time = time() + $max_allowed_time;

		// prepare for database access
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$max_packet_size = WIC_Entity_Email_Inbox_Parse::get_max_packet_size();
		$folder = WIC_Entity_Email_Account::get_folder();

		// define sql to get next short page of message stubs -- limiting to ten to minimize risk of memory issues; not sure of top limit -- 512 for sync
		$current_id = 0;
		// https://docs.microsoft.com/en-us/exchange/client-developer/exchange-web-services/mailbox-synchronization-and-ews-in-exchange -- "avoid getting throttled"
		if ( defined( 'WP_ISSUES_CRM_GET_REQUEST_PAGE_SIZE' ) && WP_ISSUES_CRM_GET_REQUEST_PAGE_SIZE ) {
			$page_size = WP_ISSUES_CRM_GET_REQUEST_PAGE_SIZE;
		} else {
			$page_size = 10;
		}
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
		$parsed_messages = 0;
		$errors = 0;	
		// do we have work to do at this stage? if not, return -- no connection check if nothing to do.
		if ( ! $wpdb->get_results ( $wpdb->prepare ( $sql_template, array( $current_id ) ) ) ) {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync_Parse:parse_inbox found no messages to parse.' );
			return true; // true, i.e., done, no need to check again since won't synch again until next
		// loop through messages to parse, getting in batches
		} else {
			// get client 
			$response = WIC_Entity_Email_ActiveSync::get_connection();
			if ( ! $response['response_code'] ) {
				WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync_Parse:parse_inbox could not connect. ' . $response['output'] );
				return true; // consider run done
			} else {
				$client = $response['output'];
			}		
		
			// look for blank serialized_email_object and parse them for as long as allowed (and there are any)
			while ( 
				time() < $cut_off_time &&
				$stubs =  $wpdb->get_results ( $wpdb->prepare ( $sql_template, array( $current_id ) ) )
				) {
				// Build the request for 10 messages to work with.
				$request = new GetItemType();
				$request->ItemShape = new ItemResponseShapeType();
				$request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
				$request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
				// Iterate over the message ids, setting each one on the request.
				foreach ($stubs as $stub) {
					$item = new ItemIdType();
					$item->Id = $stub->extended_message_id;
					$request->ItemIds->ItemId[] = $item;
				}
				$response = $client->GetItem($request);
				// Iterate over the results, handling errors and proceeding
				$response_messages = $response->ResponseMessages->GetItemResponseMessage; 
				foreach ($response_messages as $order => $response_message) {
					// back reference the record id for the request
					$working_id = $stubs[$order]->ID;
					// Make sure the request succeeded.
					if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
						$code = $response_message->ResponseCode;
						$message = $response_message->MessageText;
						$errors++;
						WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Parse:parse_inbox could not execute GetItem for message: {$stubs[$order]->extended_message_id} -- server said $message."  );
					// if so, parse the message
					} else {
						// the Items property has multiple properties correspondending to different variants of the message object, but only one will be instantiated
						foreach  ( $response_message->Items as $key => $item ) {
							// the item is an array with a single payload element
							if ( $item ) {
								$message = $item[0];
							}
						} 
						// now do the major build
						$extended_message_id = $stubs[$order]->extended_message_id;
						$email_object = new WIC_DB_Email_Message_Object_Activesync;
						$email_object->build_from_activesync_payload ( $working_id, $extended_message_id, $message, $max_packet_size ); 
						if ( WIC_Entity_Email_Inbox_Parse::save_mail_object  ( $working_id, $email_object, $folder, $working_id, $max_packet_size  )   ) {
							$parsed_messages++;
						// if bad outcome log error
						} else {
							WIC_Entity_Email_Cron::log_mail ( 'Non-fatal error in WIC_Entity_Email_ActiveSync_Parse -- could not save parsed message.  ' . ( $wpdb->last_error  ? ( ', the database said: ' . $wpdb->last_error ) : '' ) . "  Dumping message object:" );		
							WIC_Entity_Email_Cron::log_mail ( print_r ( $email_object, true ) );
						}
						unset ( $email_object ); // release memory for next round
					} // close non-error branch
				} // close loop handling ActiveSync response "messages" -- one per requested id
				// break if on shared connection
				if ( defined( 'WP_ISSUES_CRM_USING_CENTRAL_CRON_CONTROL' ) && WP_ISSUES_CRM_USING_CENTRAL_CRON_CONTROL ) {
					WIC_Entity_Email_Inbox_Parse::compile_thread_date_times( $folder );
					WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Parse:parse_inbox -- finished ActiveSync page, parsing $parsed_messages messages; $errors messages had errors and were not parsed." );
					return count( $stubs ) < $page_size; // getting one page per cycle in central -- false means we want another page
				}
				// advance start point for next loop
				$current_id = $working_id;
			}  // close while still found message to parse
			WIC_Entity_Email_Inbox_Parse::compile_thread_date_times( $folder );
		} // close any found messages to parse
		WIC_Entity_Email_Cron::log_mail ( "WIC_Entity_Email_ActiveSync_Parse:parse_inbox -- finished, parsing $parsed_messages messages; $errors messages had errors and were not parsed." );

		return true;
	}

}