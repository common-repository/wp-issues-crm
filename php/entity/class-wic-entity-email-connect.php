<?php
/*
*
*	wic-entity-email-connect.php
*
*/
Class WIC_Entity_Email_Connect {

	const PROCESSED_FOLDER = 'WP_ISSUES_CRM_PROCESSED';

	public static function check_connection () {
	
		global $wp_issues_crm_IMAP_stream;
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 

		// check that user has done set up at all and/or has not removed server setting		
		$server = '';
		if ( isset ( $wic_plugin_options['email_imap_server'] ) ) {
			$server = $wic_plugin_options['email_imap_server'];
		}		
		if ( '' == $server ) {
			return array ( 'response_code' => false , 'output' => 'Check settings -- no inbox server configured.' ) ;
		}

		// get folder selection -- error if blank
		$inbox_string = '';
		if ( isset ( $wic_plugin_options['imap_inbox'] ) ) {
			$inbox_string = $wic_plugin_options['imap_inbox'];
		}
		
		if ( '' == $inbox_string ) {
			return array ( 'response_code' => false , 'output' => 'Check settings -- no inbox folder selected.' ) ;
		}

		if ( ! self::check_connection_failure_count() ) {
			return array ( 'response_code' => false , 'output' => 'Max retries exceeded.  Visit Configure &raquo; Email In and Test Settings to reset retry counter.' ) ;
		}
		
		// attempt open stream  
		$inbox_error_string = self::open_mail_stream( $inbox_string ); 
		if ( '' < $inbox_error_string ) {
			self::increment_connection_failure_count();
			return array ( 'response_code' => false , 'output' => $inbox_error_string ) ;
		}

		// at this point, connection is good -- reset counter for consecutive failures
		self::reset_connection_failure_count();
	
		// the global $wp_issues_crm_IMAP_stream contains the inbox stream; need only return outcome
		return array ( 'response_code' => true , 'output' => 'Connected OK' ) ;

	}

	public static function check_connection_failure_count() {

		// get setting for max retries
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		if ( defined ( 'WP_ISSUES_CRM_MAX_POLLING_RETRIES' ) ) {
			$imap_max_retries = WP_ISSUES_CRM_MAX_POLLING_RETRIES;
		} else {
			$imap_max_retries = isset ( $wic_plugin_options['imap_max_retries'] ) ? $wic_plugin_options['imap_max_retries'] : 3 ;
		}
		// get count of failures
		$count = get_option ( self::choose_connection_failure_counter() );
		$count = $count ? $count : 0;
		
		// return comparison
		return ( $count <= $imap_max_retries );
	}

	public static function increment_connection_failure_count() {
		$count = get_option ( self::choose_connection_failure_counter() );
		$count = $count ? $count : 0;
		$count++;
		update_option( self::choose_connection_failure_counter(), $count );
	}

	public static function reset_connection_failure_count() {
		update_option( self::choose_connection_failure_counter(), 0 );
	}

	private static function choose_connection_failure_counter() {
		switch ( WIC_Entity_Email_Account::get_send_account() ) {
			case 'exchange':
				return 'wp_issues_crm_activesync_failure_count';
			case 'legacy':
				return 'wp_issues_crm_connection_failure_count';
			default:
				return 'wp_issues_crm_connection_failure_count';
		}
	}



	// check connection and load error strings -- done onload of settings page
	private static function load_connection_strings ( $dummy_id = '', $dummy_data = '') {
		
		global $wp_issues_crm_IMAP_stream;
		$errorString = '';
		$response_code = false;
		
		// open imap stream	
		$errorString .= WIC_Entity_email_connect::open_mail_stream( WIC_Entity_email_connect::create_open_string( '' ) ); // test open the top level mailbox 

		// if open successful, collect options for read_folder array
		if ( '' == $errorString ) {
			$mailbox_list =  imap_list( $wp_issues_crm_IMAP_stream, WIC_Entity_email_connect::create_open_string( '' ), '*' );		
			$folder_options = array();
			// run through mailboxes -- build options array
			foreach ( $mailbox_list as $mailbox ) {
				$response_code = true;
				$mailbox_label = substr( strrchr( $mailbox, "}"), 1);
				$folder_options[] = array ( 'value' => $mailbox, 'label' => esc_html( $mailbox_label ) );
			}
		}		

		if ( false === $response_code && '' === $errorString ) {
			$errorString == 'Mail stream opened OK, but no mailbox folders found by imap_list.';
		}

		return array ( 'response_code' => $response_code, 'output' => $response_code ? $folder_options : $errorString  ) ;
	}

	// this fills in the full option set after WIC_Admin_Settings::imap_inbox_callback does initial set up (called from settings.js)
	public static function imap_inbox_callback_ajax( $dummy, $value ) {

		// first see if other settings appear to be in place
		$response = self::load_connection_strings ( '','' );
		if ( ! $response['response_code'] ) {
			return array ( 'response_code' => true, 'output' => 'WARN' );
		}

		// discard options not appropriate for reading as inbox
		$filtered_response = array( array ( 'value' => '', 'label' => 'Not selected') );
		$filtered_discards = array();
		$inbox_string = '';
		foreach ( $response['output'] as $option ) {
			if ( self::identify_forbidden_folders( $option['label'] ) ) {
 				$filtered_discards[] = $option['label']; 
			} else {
				$filtered_response[] = $option;			
			}
		}

		// create legend of discarded options
		$excluded_string = count ( $filtered_discards ) ? ( __( ' folders excluded: ', 'wp-issues-crm' ) . implode ( ', ', $filtered_discards ) ) : '' ;

		$args = array (
			'field_label'	 	=> '',
			'option_array'    => $filtered_response,
			'input_class' 	   => '',
			'field_slug_css'	=> '',
			'hidden'			=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[imap_inbox]',
			'value'				=> $value ,		
		);		
		$control =  WIC_Control_Select::create_control( $args ) . $excluded_string;

		return array ( 'response_code' => true, 'output' => $control );

	}

	private static function identify_forbidden_folders ( $folder_label ) {
		$non_inbox_folders = array(
			'calendar',
			'contacts',
			'deleted',
			'deleted items',
			'trash',
			'drafts',
			'journal',
			'junk e-mail',
			'important',
			'notes',
			'outbox',
			'rss feeds',
			'sent',
			'sent items',
			'sent mail',
			'all mail',
			'spam',
			'starred',
			'trash',
			self::PROCESSED_FOLDER
		);
		
		// see if any forbidden strings in the label
		foreach ( $non_inbox_folders as $forbidden ) {
			if ( false !== stripos( $folder_label, $forbidden ) ) {
				return true;
			} 
		}
		return false;
	}

	private static function open_mail_stream( $open_string ) {
		// open the connection (suppressing immediate errors with @, but handling the error stack below)
		global $wp_issues_crm_IMAP_stream;
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$disable_gssapi = false;
		if ( isset( $wic_plugin_options['suppress_gssapi'] ) ) {
			$disable_gssapi = $wic_plugin_options['suppress_gssapi'] ? true : false;
		}

		$wp_issues_crm_IMAP_stream = @imap_open ( 
						$open_string, 
						$wic_plugin_options['user_name_for_email_imap_interface'],
						WIC_Entity_Email_Settings::get_parms()->i,
						0, // not setting OP_READONLY, -- do need for read access
						0, // don't retry until next cycle 
						$disable_gssapi ? array( 'DISABLE_AUTHENTICATOR' => 'GSSAPI' ) : array()  // suppress messages on exchange server -- http://stackoverflow.com/questions/5815183/php-imap-exchange-issue
						);
						
		// construct error string -- clearing error stack
		$imap_error_string = 
			self::punctuate_error_array( imap_errors() ) . 
			self::punctuate_error_array( imap_alerts() ); 
		
		// return error only if open unsuccessful ( alerts will not be returned on successful open )
		if ( false === $wp_issues_crm_IMAP_stream ) {
			if ( '' == $imap_error_string ) {
				$imap_error_string == __( ' Open fail with no explanation offered by php function imap_open. ', 'wp-issues-crm' );
			}
			return( __( 'Check settings -- could not establish connection. ', 'wp-issues-crm' ) . $imap_error_string  );
		} else {
			return ( '' );
		} 

	}
	
	// return server specification for open and ref
	private static function create_open_string( $folder ) {
		
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 

		return (
			'{' . 
				$wic_plugin_options['email_imap_server'] . // add some error prevention logic here?
				':' . 
				$wic_plugin_options['port_for_email_imap_interface'] .
				'/imap' .
				( isset ( $wic_plugin_options['use_ssl_for_email_imap_interface'] ) ? '/ssl/novalidate-cert' : '' ) .
			'}' . 
			$folder // note that blank folder should return top level of IMAP hierarchy
		);
	}
	
	// returns empty string if $error_array is false as in no errors
	private static function punctuate_error_array ( $error_array ) {
		$return_string = '';
		if ( is_array( $error_array ) )  {
			$error_array = array_unique ( $error_array );
			$i = 0;
			foreach ( $error_array as $error ) {
				$return_string .= rtrim( $error, ",. ");
				$i++;
				$return_string .= $i < count ( $error_array ) ? ', ' : '. ';
			}
		} 
		
		return ( $return_string );
	}


	// check for processed folder and create if not present; return processed folder name or false if unsucessful
	public static function check_create_processed_folder () {

		// open the mail stream without specifying a mailbox folder
		$open_string = self::create_open_string ( '' ); 
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$no_folder_stream = @imap_open ( 
						$open_string, 
						$wic_plugin_options['user_name_for_email_imap_interface'],
						WIC_Entity_Email_Settings::get_parms()->i,
						OP_HALFOPEN, // open stream, don't open a particular box
						0 // not really necessary to complete this call in most cases 
						);	
		
		// return false if cannot connect to check folder
		if ( ! $no_folder_stream ) {
			return array ( 'response_code' => false, 'output' => 'Unable to open connection to check for moved messages on server.' );
		}
		// first try processed folder straight up -- server may or may not have this folder or allow direct creation of it
		$processed_folder = self::PROCESSED_FOLDER; 
		$processed_folder_open_string = self::create_open_string ( $processed_folder );
		if ( false !== imap_status ( $no_folder_stream, $processed_folder_open_string, SA_MESSAGES ) ) {
			imap_close ( $no_folder_stream );
			return array( 'response_code' => true, 'output' => $processed_folder );
		// next try one level down within INBOX
		} else {
			$second_level_processed_folder = 'INBOX.' . self::PROCESSED_FOLDER; 
			$second_level_processed_folder_open_string = self::create_open_string ( $second_level_processed_folder );		
			if ( false !== imap_status ( $no_folder_stream, $second_level_processed_folder_open_string, SA_MESSAGES ) ) {
				imap_close ( $no_folder_stream );
				return array( 'response_code' => true, 'output' => $second_level_processed_folder );
			}
		}
		// didn't find either way, attempt to create it at top level
		imap_createmailbox ( $no_folder_stream, $processed_folder_open_string );
		if ( false !== imap_status ( $no_folder_stream, $processed_folder_open_string, SA_MESSAGES ) ) {
			imap_close ( $no_folder_stream );
			return array( 'response_code' => true, 'output' => $processed_folder );
		// if unsuccessful, attempt to create it at next level down
		} else {
			imap_createmailbox ( $no_folder_stream, $second_level_processed_folder_open_string );
		 	if ( imap_status ( $no_folder_stream, $second_level_processed_folder_open_string, SA_MESSAGES ) ) {
				imap_close ( $no_folder_stream );
				return array( 'response_code' => true, 'output' => $second_level_processed_folder );
		 	}
		}		
		// struck out!  
		imap_close ( $no_folder_stream );
		return array( 'response_code' => false , 'output' => imap_last_error() );
	}

	public static function test_settings ( $dummy1, $dummy2 ) {

		// reset the failure counter
		self::reset_connection_failure_count();

		// check connection
		$error = WIC_Entity_email_connect::open_mail_stream( WIC_Entity_email_connect::create_open_string( '' ) );
		$error2 = WIC_Entity_email_connect::check_connection();
		$folder_message = $error2['response_code'] ? '!' : ', but Inbox selection missing or bad.';
		return array ( 'response_code' => true, 'output' => '' == $error ? ( 'Connection settings good' . $folder_message ) : $error ); 
	}



}