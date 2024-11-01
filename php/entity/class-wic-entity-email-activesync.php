<?php
/*
*
*	wic-entity-email-activesync.php
*
*   this class collects oauth related functions
*
*/
// this is the location of the copy of the EWS client library packaged with WP Issues CRM
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'activesync'  . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
use jamesiarmes\PhpEws\Autodiscover; 		// autodiscover

class WIC_Entity_Email_ActiveSync extends WIC_Entity_Parent {


	protected function set_entity_parms( $args ) {
		$this->entity = 'email_activesync';
		$this->entity_instance = '';
	} 


	// here returning true to online regardless of status -- response_code false is processing error
	public static function activesync_status () {

		// is email > ''
		$email_address = WIC_Entity_Email_Process::get_processing_options()['output']->activesync_email_address;
		if ( ! $email_address ) {
			return array ( 'response_code' => true, 'output' => 'ActiveSync email_address is not set.') ;
		} 

		// is email valid > ''
		if ( ! filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
			return array ( 'response_code' => true, 'output' => 'ActiveSync email_address appears to be invalid.') ;
		}

		// is password > ''
		$password = WIC_Entity_Email_Settings::get_parms()->a;
		if ( ! $password ) {
			return array ( 'response_code' => true, 'output' =>  'ActiveSync password is not set.' );
		} 		

		// do the password and email work?
		// Simplest usage, no special options.
		try {
			$client = Autodiscover::getEWS( $email_address, $password );
			WIC_Entity_Email_Connect::reset_connection_failure_count();
			return array ( 'response_code' => true, 'output' => '<h4>Successful</h4><p>Active synch email and password are correct and Exchange Server is autodiscovering connection details.</p>' );	
		} catch ( Exception $e ) {
			return array ( 'response_code' => true, 'output' =>   '<h4>Unsuccessful</h4><p>Check address and password and retest.</p><p>If you believe your settings are correct but connection remains unsuccessful, ask administrator 
			to check Autodiscovery configuration for Exchange Server.</p><p>Server said: ' . $e->getMessage() . '</p>');	
		}
	}

	//  get/check connection
	public static function get_connection () {

		// is email > ''
		$email_address = WIC_Entity_Email_Process::get_processing_options()['output']->activesync_email_address;
		if ( ! $email_address ) {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because ActiveSync email_address is not set.' );	
			return array ( 'response_code' => false, 'output' => 'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because ActiveSync email_address is not set.') ;
		}

		// knowing that email is set, check batch connection cache
		global $wp_issues_crm_activesync_connection_cache;
		if ( is_array ( $wp_issues_crm_activesync_connection_cache ) ) {
			if ( isset ( $wp_issues_crm_activesync_connection_cache [ $email_address ] ) ) {
				return array ( 'response_code' => true , 'output' =>  $wp_issues_crm_activesync_connection_cache [ $email_address ] ); 		
			}
		}
		
		// check that email valid
		if ( ! filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because ActiveSync email_address is not valid.' );	
			return array ( 'response_code' => false, 'output' => 'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because ActiveSync email_address is not valid.') ;
		}
		
		// is password > ''
		$password = WIC_Entity_Email_Settings::get_parms()->a;
		if ( ! $password ) {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because ActiveSync password is not set.' );	
			return array ( 'response_code' => false, 'output' =>  'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because ActiveSync password is not set.' );
		} 	

		if ( ! WIC_Entity_Email_Connect::check_connection_failure_count() ) {
			WIC_Entity_Email_Cron::log_mail ( 'WIC_Entity_Email_ActiveSync::get_connection declined to attempt connection because failure count exceeded.' );	
			return array ( 'response_code' => false , 'output' => 'Max connection retries exceeded.  Visit Mail Control and Test Settings to reset retry counter.' ) ;
		}
	
		try {
			$client = Autodiscover::getEWS( $email_address, $password );
			WIC_Entity_Email_Connect::reset_connection_failure_count();
			// update batch connection cache
			if ( is_array ( $wp_issues_crm_activesync_connection_cache ) ) {
				$wp_issues_crm_activesync_connection_cache [ $email_address ] = $client;
			}
			return array ( 'response_code' => true , 'output' => $client ) ;
		} catch ( Exception $e ) {
			WIC_Entity_Email_Connect::increment_connection_failure_count();
			$message = 'Batch activesync connection failure.  Server said: ' . $e->getMessage(); 
			WIC_Entity_Email_Cron::log_mail  ( $message );	
			return array ( 'response_code' => false , 'output' => $message ) ;
		}	

	}


} // close class