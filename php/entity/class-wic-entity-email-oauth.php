<?php
/*
*
*	wic-entity-email-ouath.php
*
*   this class collects oauth related functions
*
*/
// this is the location of the copy of the google client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor'  . DIRECTORY_SEPARATOR . 'autoload.php';

class WIC_Entity_Email_OAUTH extends WIC_Entity_Parent {


	protected function set_entity_parms( $args ) {
		$this->entity = 'email_auth';
		$this->entity_instance = '';
	} 

	public static function redirect_to_gmail( ) {
		// generate URL to allow user to grant permission to access emails
		try {
			$state = array ( get_current_blog_id(),$_GET['oauth_nonce'] );
			$state_url_safe = WIC_DB_Email_Message_Object_Gmail::url_safe_base64_encode( json_encode ( $state ) );
			$client = self::get_client( $state_url_safe );
			$auth_url = $client->createAuthUrl();
		} catch ( Exception $e ) {
			print ( '<h3>Unanticipated Gmail API connection error -- credential file possibly corrupted or deleted.  Try reinstalling client credentials.</h3>
			<p> Error was reported as: ' . $e->getMessage() . '</p></p><p>See: https://wp-issues-crm.com/configuring-gmail-access-in-wp-issues-crm/</p>' );
			die;
		}		
		header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
		die;
	}
	
	// note: before calling this function, nav checks state and switches to current blog
	public static function redirect_from_gmail () { 

		// try to process redirected code into an access token
		try {
			$client = self::get_client();
			$code = $_GET['code'];
			// Exchange authorization code for an access token.
			$access_token = $client->fetchAccessTokenWithAuthCode($code);
			$client->setAccessToken( $access_token );
			// prepare to access gmail services
			$service = new Google_Service_Gmail( $client );
			$profile = $service->users->getProfile('me');
			// save the auth token and profile results
			$option_def = (object) array ( 
				'email_address' => $profile->emailAddress, 
				'access_token'	=> $access_token,
				'connect_error' => '',
			);
		} catch ( Exception $e ){	
			error_log ( print_r ( $e, true ))	;	
			$option_def = (object) array ( 
				'email_address' => '', 
				'access_token'	=> '',
				'connect_error' => $e->getMessage(),
			);
		}
		/*
		* whether or not successful, record option and return to settings page
		*/
		update_option ( 'wp-issues-crm-gmail-connect',  $option_def  );
		$settings_url = admin_url() . 'admin.php?page=wp-issues-crm-main&entity=email_inbox&action=new_blank_form&id_requested=settings';
		header('Location: ' . $settings_url );
		die; // do not continue processing -- just switch
	}

	/* 
	* manage access to path credentials 
	*/
	public static function get_oauth_credentials_path() {
		return defined ( 'WIC_GMAIL_OAUTH_CREDENTIALS_FULL_PATH' ) ? WIC_GMAIL_OAUTH_CREDENTIALS_FULL_PATH : false;
	}

	public static function get_oauth_admin_url () {
		if ( !is_multisite() || BLOG_ID_CURRENT_SITE == get_current_blog_id() ) {
			return admin_url();
		} else {
			switch_to_blog( BLOG_ID_CURRENT_SITE );
			$admin_url = admin_url();
			restore_current_blog();	
			return $admin_url;	
		}	
	
	}

	public static function get_client ( $state = false ) {
			
		/*
		* https://developers.google.com/api-client-library/php/auth/web-app
		* set up user's Wordpress server as a google client
		*
		*/
		$client = new Google_Client();
		$client->setAuthConfig( WIC_Entity_Email_OAUTH::get_oauth_credentials_path() );
		$client->setAccessType("offline");        // offline access
		$client->setIncludeGrantedScopes(true);   // incremental auth
		// scopes: https://developers.google.com/identity/protocols/googlescopes
		$client->addScope( Google_Service_Gmail::GMAIL_READONLY ); // read email and settings -- 'https://www.googleapis.com/auth/gmail.readonly'
		$client->addScope( Google_Service_Gmail::GMAIL_LABELS ); // manage mailbox labels -- 'https://www.googleapis.com/auth/gmail.labels'
		$client->addScope( Google_Service_Gmail::GMAIL_MODIFY ); // manage messages ( for us, change labels )-- 'https://www.googleapis.com/auth/gmail.modify'
		$client->addScope( Google_Service_Gmail::GMAIL_SEND ); // manage messages ( for us, the actually relevant line is the next one re SMTP );
		$client->addScope( 'https://mail.google.com/' ); // SMTP FOR PHPMAILER		
		if ( $state ) {
			$client->setState( $state );
		}
		$client->setRedirectUri( self::get_oauth_admin_url() . 'admin.php?page=wp-issues-crm-main&entity=email_oauth&action=redirect_from_gmail&id_requested=0');	
		return $client;	

	}
	
	private static function get_stored_access_token() {
		// get the stored token
		$stored_values = get_option ( 'wp-issues-crm-gmail-connect' );
		if ( ! $stored_values || !isset ( $stored_values->access_token ) ) {
			return false;
		} else {
			return $stored_values->access_token;
		}	
	}
	
	
	public static function check_user_auth() {
		
		// get the stored token
		$stored_values = get_option ( 'wp-issues-crm-gmail-connect' );
		if ( ! $stored_values || !isset ( $stored_values->access_token ) ) {
			return array ( 'response_code' => false , 'output' => 'Missing access token -- reconnect.' );
		} else {
			$access_token = $stored_values->access_token;
		}
		// try to set it up in client
		try  { 
			$client = self::get_client();
			$client->setAccessToken( $access_token );
		} catch ( Exception $e ) {
			return array ( 'response_code' => false , 'output' => 'Google client said:' . $e->getMessage() );
		}
		// attempt to refresh it
		if ( $client->isAccessTokenExpired() ) {
			// Refresh the token if possible, else fetch a new one.
			if ( $client->getRefreshToken()) {
				$client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );
				$stored_values->access_token = $client->getAccessToken();	
				update_option ( 'wp-issues-crm-gmail-connect',  $stored_values );
			} else {
				return array ( 'response_code' => false , 'output' => 'Could not refresh token -- reconnect.' );
			}
		}
		$service = new Google_Service_Gmail( $client );
		try {
			$profile = $service->users->getProfile('me');
		} catch ( Exception $e ) {
			return array ( 'response_code' => false , 'output' => 'Google profile service said:' . $e->getMessage() );
		}
		// if have gotten to here, access token is good
		return array ( 'response_code' => true , 'output' => $stored_values->access_token );
	}
	
	
	public static function disconnect() {

		if ( $access_token = self::get_stored_access_token() ) {
			$client = self::get_client();
			$client->revokeToken( $access_token );
		}
		
		// regardless of $client outcome, delete option
		delete_option ( 'wp-issues-crm-gmail-connect' );
		return array ( 'response_code' => true, 'output' =>  '' );
	}


} // close class