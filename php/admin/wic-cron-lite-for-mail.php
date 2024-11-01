<?php
/*
* wic-cron-lite-for-mail.php
* 
* This script is intended to be invoked directly from the command line in an environment like Azure.
*
* Designed to be invoked as a continuously running web job as an include file.
*
* Runs all three legacy mail functions -- synch, parse, deliver -- in a continuous loop of minimum 2 minute duration.
*
* Calling job must define('WEB_JOB_BLOG_ID') = N -- numeric blog number
*
* Necessary configuration elements in config.php included the following
* - By pass final include line if WP_ISSUES_CRM_CRON_LITE
	if ( !defined( 'WP_ISSUES_CRM_CRON_LITE') || ! WP_ISSUES_CRM_CRON_LITE ) {
		require_once ABSPATH . 'wp-settings.php';
	}
*   WP_ISSUES_CRM_MESSAGE_SEND_DELAY = 2000 (to keep messages per minute under 30);
*   WP_ISSUES_CRM_MESSAGES_SENT_PER_ROTATION = 50 (consistent with 100 second processing limit)
*   WP_ISSUES_CRM_CRON_INTERVAL = 0 (will never be overruning, because only one copy started)
*
*   $table_prefix must be defined in wp_config
*
*  Loads no more of Wordpress than absolutely necessary to handle the mail routines that it invokes.
*/

// first define ABSPATH correctly with reference to __FILE__ which is located in /php/admin/
define( 'ABSPATH', dirname(__FILE__, 6) . '\\' );

// set up constant for use in bypassing line in wp-config that does full set up through wp-settings
define('WP_ISSUES_CRM_CRON_LITE', true);

// note again that wp-config.php must be altered to include by-pass of include wp-settings;
require_once ABSPATH . 'wp-config.php';

// set up vars from config.php
$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';

// load limited set of functions needed
define( 'WPINC', 'wp-includes' );
require_once ABSPATH . WPINC . '\load.php';
require_once ABSPATH . WPINC . '\plugin.php';
require_once ABSPATH . WPINC . '\functions.php';
require_once ABSPATH . WPINC . '\cache.php';
require_once ABSPATH . WPINC . '\formatting.php';
require_once ABSPATH . WPINC . '\kses.php';

// load object cache (used for options retrieval)
require_once ABSPATH . WPINC . '\class-wp-object-cache.php';
wp_cache_init();

// Set up the WordPress query object
global $wpdb;
require_once ABSPATH . WPINC . '\wp-db.php';
$wpdb = new wpdb( $dbuser, $dbpassword, $dbname, $dbhost );

// set table prefix from value in config.php (check that it is present )
 $wpdb->set_prefix( $table_prefix );
// set blog id from constant defined in invoking function
if ( ! defined( 'WEB_JOB_BLOG_ID' ) ||  ! WEB_JOB_BLOG_ID  ) {
	error_log ( 'WEB_JOB_BLOG_ID NOT SET. wic-cron-lite-for-mail.php will stop.');
	die;
} else { 
	$wpdb->set_blog_id( WEB_JOB_BLOG_ID ); 
}
/* set up autoloader for classes (with ref to file location) */
function wp_issues_crm_autoloader( $class ) {
	if ( 'WIC_' == substr ($class, 0, 4 ) ) {
		$subdirectory = 'php'. DIRECTORY_SEPARATOR . strtolower( substr( $class, 4, ( strpos ( $class, '_', 4  ) - 4 )  ) ) . DIRECTORY_SEPARATOR ;
		$class = strtolower( str_replace( '_', '-', $class ) );
		$class_file =  dirname(__FILE__,3) . '/' . $subdirectory .  'class-' . str_replace ( '_', '-', $class ) . '.php';
		if ( file_exists ( $class_file ) ) {  
   			require_once $class_file;
   		} else {
	  	 	wic_generate_call_trace();
			die ( '<h3>' . sprintf(  __( 'Fatal configuration error -- missing file %s; failed in autoload in wp-issues-crm.php, line 43.', 'wp_issues_crm' ), $class_file ) . '</h3>' );   
	   } 
	}	
}
if ( ! spl_autoload_register('wp_issues_crm_autoloader' ) ) {
	die ( __( 'Fatal Error: Unable to register wp_issues_crm_autoloader in wp-issues-crm.php', 'wp-issues-crm' ) );	
};

// invoke the dictionary
WIC_Admin_Navigation::dictionary_setup();
// include the category filter plug (local install; may not be present)
include dirname(__FILE__,4) . '/' . 'wp-issues-crm-local' . '/' . 'maleg-category-filter.php';

// attempt to assure non-interruption -- running from command line, so probably unnecessary
set_time_limit(0);
// create continuous loop of minimum 2 minutes length
while (true ) {
	$loop_start = time();
	// directly call synch_inbox (prefix is already set )
	WIC_Entity_Email_Inbox_Synch::synch_inbox( false );
	// directly call parse routine
	WIC_Entity_Email_Inbox_Parse::parse_messages();
	// directly call deliver routine
	WIC_Entity_Email_Deliver::process_message_queue();
	// sleep to create two minute cycle
	$loop_end = time();
	if ( ( $loop_end - $loop_start ) < 120 ) {
		usleep (  ( 120 - ($loop_end - $loop_start ) ) * 1000000 ); // usleep argument is in microseconds		
	}
}
