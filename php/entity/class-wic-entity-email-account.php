<?php
/*
*
*	wic-entity-email-accouht.php
*
*   this class collects oauth related functions
*
*/

class WIC_Entity_Email_Account {

	public static function get_read_account () {
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		return $form_variables_object->read_account;
	}
	
	public static function get_send_account() {
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		return $form_variables_object->send_account;
	}


	public static function check_online_folder ( $dummy_id, $test_folder ) {
		// get the true full folder string
		$folder = self::get_folder();
		// check folder that inbox was loaded from -- error if different from main settings
		if ( $folder != $test_folder ) {
			return array ( 'response_code' => false, 'output' => 'Inbox folder changed while inbox open.  Refresh to reset.'  );
		} elseif ( ! $folder ) {
			return array ( 'response_code' => false, 'output' => 'Inbox folder setting missing.'  );
		} else {
			return array ( 'response_code' => true, 'output' => $folder );
		}
	}

		
	public static function get_folder() {

		$account = self::get_read_account();
		
		if ( 'exchange' == $account ) {
			// this value will always be set, although possibly blank
			$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
			return $form_variables_object->activesync_email_address;
		} elseif ( 'gmail' == $account ) {
			$gmail_parms = get_option ( 'wp-issues-crm-gmail-connect' );
			// using email address as folder name running gmail
			if ( ! $gmail_parms || !isset ( $gmail_parms->email_address ) ) {
				return '';
			} else {
				return $gmail_parms->email_address;
			}		
		} elseif ( 'legacy' == $account ) {
			$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
			$folder = isset ( $wic_plugin_options['imap_inbox'] ) ? $wic_plugin_options['imap_inbox'] : '';	
			return $folder;
		} else {
			return false;
		}
	}

	public static function get_server() {

		$account = self::get_read_account();
		
		if ( 'exchange' == $account ) {
			return 'Exchange';
		} elseif ( 'gmail' == $account ) {
			return 'Gmail';		
		} elseif ( 'legacy' == $account ) {
			$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
			$server = isset ( $wic_plugin_options['email_imap_server'] ) ? $wic_plugin_options['email_imap_server'] : '';	
			return $server;
		} else {
			return false;
		}
	}

	public static function get_folder_label() {

		$account = self::get_read_account();

		if ( 'exchange' == $account ) {
			return self::get_folder();
		} elseif ( 'gmail' == $account ) {
			return self::get_folder();
		} elseif ( 'legacy' == $account ) {
			$folder = self::get_folder();
			if ( $folder ) {
				$folder_label = substr( strrchr( $folder, "}"), 1);
				return $folder_label;
			} else {
				return '';
			}
		} else {
			return false;
		}		
	}


	// handles sync now button
	public static function email_inbox_synch_now ( $dummy_id, $data ) {
		$response = self::route_sync( true );
		if ( false === $response['response_code'] ) {
			return ( $response );
		}
		return ( WIC_Entity_Email_Inbox_Synch::load_staged_inbox ( '', '' ) );
	}

	// can be from online or from wp_cron or from email cron  (poss. if activesync)
	// if online must provide return code; otherwise unmonitored
	public static function route_sync ( $online = false ) {
		$account = self::get_read_account();
		if ( 'exchange' == $account ) {
			$response =  WIC_Entity_Email_ActiveSync_Synch::synch_inbox( $online );
			if ( ! $online ) {
				if ( ! $response['response_code'] ) {
					WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync_Synch::synch_inbox reported: ' . $response['output'] );
				}
 			}
			return $response;
		} elseif ( 'gmail' == $account ) {
			$response =  WIC_Entity_Email_OAUTH_Synch::synch_inbox( $online );
			if ( ! $online ) {
				if ( ! $response['response_code'] ) {
					WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_OAUTH_Synch::synch_inbox reported: ' . $response['output'] );
				}
			}
			return $response;
		} elseif ( 'legacy' == $account ) {
			$response =  WIC_Entity_Email_Inbox_Synch::synch_inbox( $online );
			if ( ! $online ) {
				if ( ! $response['response_code'] ) { 
					WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Inbox_Synch::synch_inbox reported: ' . $response['output'] );
				}
			} 
			return $response;
		} else { 				
			if ( ! $online ) {
				WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Account::route_sync reported bad read_account setting:' . $account . '.' );
			}
			return array ( 'response_code' => false, 'output' =>  'WIC_Entity_Email_Account::route_sync reported bad read_account setting:' . $account . '.'  );	
		}
	}

	// parse is never online -- is invoked by either wp_cron or email_cron (poss. if activesync)
	public static function route_parse () {
		$account = self::get_read_account();
		if ( 'exchange' == $account ) {
			return WIC_Entity_Email_ActiveSync_Parse::parse_inbox();
		} elseif ( 'gmail' == $account ) {
			return WIC_Entity_Email_OAUTH_Update::update_inbox();
		} elseif ( 'legacy' == $account ) {
			return WIC_Entity_Email_Inbox_Parse::parse_messages();
		} else { 				
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Account::route_parse reported bad read_account setting:' . $account . '.' );
			return true; // no more processing in cron
		}
	}

	// deliver is never online -- invoked by either wp_cron or email_cron (poss. if activesync)
	public static function route_deliver () {
		$account = self::get_send_account();
		if ( 'exchange' == $account ) {
			return WIC_Entity_Email_Deliver_Activesync::process_message_queue();
		} elseif ( 'legacy' == $account || 'gmail' == $account ) {
			return WIC_Entity_Email_Deliver::process_message_queue();
		} else { 				
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_Account::route_deliver reported bad send_account setting:' . $account . '.' );
			return true; // no more processing in cron
		}	
	}

	public static function get_tabs () {
		/*
		* local_tab_definition should be installed in a local plugin together with filters hooked to wp_issues_crm_local_category_filter
		*
		* should return an array of tab titles in preferred case that correspond to upper case _suffixes in CATEGORY filter e.g. Advocacy for CATEGORY_ADVOCACY
		*
		* the array should always include 'Advocacy' as this special tab is used dynamically to group emails with subject lines that are mapped to issues
		* the array should always include 'Team' as this special tab is used dynamically to group emails from users having access to WP Issues CRM
		* the array should always include 'Assigned' as this special tab is used dynamically to group emails assigned for response to current user
		* the array should always include 'Ready' as this special tab is used dynamically to group emails that have drafts ready for response
		*
		* the category filter should catch every message -- messages with blank categories will not be displayed
		*/
		if ( function_exists ( 'local_tab_definition') ) {
			// local tab definition should be aware of selected read account and perhaps return an empty array if the read account is not supported
			if ( local_tab_definition() ) {
				return local_tab_definition();
			}
		}

		if ( 'gmail' == self::get_read_account() ) {
			return array( 
				'Personal',
				'Advocacy',
				'Social',
				'Promotions',
				'Updates',
				'Forums',
				'Team',
				'Assigned',
				'Ready'
			);
		}
 
		return array (  'General', 'Advocacy', 'Team', 'Assigned', 'Ready' );
		
	}

} // close class