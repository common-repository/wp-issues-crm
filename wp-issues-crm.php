<?php 
/**
 * Plugin Name: WP Issues CRM
 * Plugin URI: http://wp-issues-crm.com 
 * Description: Constituent Relationship Management for organizations that respond to constituents.  Organizes constituent contacts ( calls, etc. ) around Wordpress posts and categories. 
 * Version: 4.5.5
 * Author: Will Brownsberger
 * Author URI: http://willbrownsberger.com
 * Text Domain: wp-issues-crm
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html 
 *	Text Domain: wp-issues-crm
 *
 *  Copyright 2015, 2016, 2017, 2018, 2019, 2020  WILL BROWNSBERGER  (email : will@brownsberger.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
*
*	This file invokes WIC_Admin_Setup, the main setup class, after registering an autoloader for WP_Issues_CRM classes.
*
*	WP_Issues_CRM classes are organized under subdirectories within the plugin directory like so: 
*			<path to plugin>/php/class-category/class-identifier -- for example WIC_Entity_Issues is in /php/entity/class-wic-entity-issue.php
*
*/

// set database version global;
global $wp_issues_crm_db_version;
$wp_issues_crm_db_version = '4.5.5'; 
/*
* set js_css version global -- three possibilities:
*	+ specific value > '' (if turning over a version without raising the database version)
*	+ the current time to force reloads in a local host development environment
*	+ the database version
* this variable is used in version strings in wic_admin_setup class
*/
global $wp_issues_crm_js_css_version;
$wp_issues_crm_js_css_version = '4.5.5'; // may or not be set at release time
if ( '' == $wp_issues_crm_js_css_version ) {
	if ( strpos ( site_url(), 'localhost' ) > 0 ) {
		$wp_issues_crm_js_css_version = time();
	} else {
		$wp_issues_crm_js_css_version = $wp_issues_crm_db_version;
	}
}


// check for database install or updates -- note that the 'plugins_loaded' hook fires before is_admin is set, so too late if put in admin_setup
include_once dirname( __FILE__ ) . '/php/db/class-wic-db-setup.php';
register_activation_hook ( __FILE__, 'WIC_DB_Setup::update_db_check' ); // check will trigger install
/*
* re add_action below: originally added action on plugins_loaded because upgrade does not trigger activation hook
* collateral benefit is that in network install, individual installations will upgrade when they are used
* NOTE: update_db_check DOES NOTE GET EXECUTED BEFORE CRON RUN, SO UNTIL USER HAS TRIGGERED THIS BY USING SITE, CRON RUN WILL GENERATE TABLE NOT FOUND ERRORS
*/
add_action( 'plugins_loaded', 'WIC_DB_Setup::update_db_check' ); // 

/*
* register autoloader for all class loading
*/
if ( ! spl_autoload_register('wp_issues_crm_autoloader' ) ) {
	die ( __( 'Fatal Error: Unable to register wp_issues_crm_autoloader in wp-issues-crm.php', 'wp-issues-crm' ) );	
};

/*
* handle scheduling of mailer
*	-- define two minute scheduling
*	-- set hooks to point to mailing routines
*	-- check settings and put schedule in place
* either route mail through single cron control or use wp cron scheduler
*/

// define optional two minute scheduling
function wp_issues_crm_additional_schedules($schedules) {
	// interval in seconds
	$schedules['every2min'] = array('interval' => 2*60, 'display' => 'Every two minutes');
	$schedules['every5min'] = array('interval' => 5*60, 'display' => 'Every five minutes');
	return $schedules;
}
add_filter('cron_schedules', 'wp_issues_crm_additional_schedules');

if ( defined( 'WP_ISSUES_CRM_USING_CENTRAL_CRON_CONTROL' ) && WP_ISSUES_CRM_USING_CENTRAL_CRON_CONTROL ) {

	// if the cron_key is supplied run the mail cron program
	if ( isset ( $_GET['wp_issues_crm_run_cron_key'] ) ) {
		if ( WP_ISSUES_CRM_RUN_CRON_KEY == $_GET['wp_issues_crm_run_cron_key'] ) {
			WIC_Entity_Email_Cron::log_mail ( '+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ WIC_Entity_Email_Cron::mail_call()+++++++++++++++++++++++++++++++++++++++++++++++++++' );
			WIC_Entity_Email_Cron::mail_call();
			die; // proceed no further
		}
	}
	
	// remove any scheduled values for synch/parse and send -- checking every time, to minimize probability that run standard cron if intending to use central
	$mail_events = array (
		'wp_issues_crm_send_mail',
		'wp_issues_crm_synch_inbox',
		'wp_issues_crm_parse_inbox',
	);
	foreach ( $mail_events as $mail_event ) {
		$timestamp = wp_next_scheduled( $mail_event );
		wp_unschedule_event( $timestamp, $mail_event );
	}
	
} else {
	/*
	* handle possible conversion from hourly to every2min (can deprecate this at some point; 4.1 is first with always 2 min)
	* check current schedule term (false if not scheduled)
	*/
	$current_scheduled = wp_get_schedule ( 'wp_issues_crm_send_mail' ); 
	if ( $current_scheduled && 'every2min' != $current_scheduled ) {
		$timestamp = wp_next_scheduled( 'wp_issues_crm_send_mail' );
		wp_unschedule_event( $timestamp, 'wp_issues_crm_send_mail' );
	}

	/*
	*
	* schedule mailing routines -- will run regardless of settings; action depends on settings
	*
	*/
	add_action ( 'wp_issues_crm_synch_inbox', 'WIC_Entity_Email_Account::route_sync', 10, 1 );
	if ( ! wp_get_schedule ( 'wp_issues_crm_synch_inbox' ) ) {
		wp_schedule_event( current_time( 'timestamp' ), 'every2min', 'wp_issues_crm_synch_inbox' );
	}
	add_action ( 'wp_issues_crm_parse_inbox', 'WIC_Entity_Email_Account::route_parse', 10, 1 );
	if ( ! wp_get_schedule ( 'wp_issues_crm_parse_inbox' ) ) {
		wp_schedule_event( current_time( 'timestamp' ) +60 , 'every2min', 'wp_issues_crm_parse_inbox' );
	}
	add_action ( 'wp_issues_crm_send_mail', 'WIC_Entity_Email_Account::route_deliver', 10, 0 );
	if ( ! wp_get_schedule ( 'wp_issues_crm_send_mail' ) ) {
		wp_schedule_event( current_time( 'timestamp' ) +90 , 'every2min', 'wp_issues_crm_send_mail' );
	}
} 

// set up access framework . . note that if alter this routine, must add rerun in db_setup to cover upgrades and network activations 
// -- activation_hook not automatically triggered on upgrades or network activations;  on first network activation, the 2.4 upgrade hook in db_setup will cover
register_activation_hook ( __FILE__, array ( 'WIC_Admin_Setup', 'wic_set_up_roles_and_capabilities' ) );

/*
* set global to reflect database collation level -- increased for post version 4 installations, but not automatically upgraded
* global dictionary used in all admin functions, but loaded later for them; need here for cron mail processing (wp or cron tab invoked) 
* Added ALTERNATE_WP_CRON as an always  early load condition to handle alt cron routing even though may not actually be doing cron;
*   basically assuming that load will be worth it since if need alternate cron due to site sign-in mechanisms, 
*       likely to be doing primarily administrative work on site and so likely to loading dictionary later anyway
*/
if ( is_admin() || wp_doing_cron() || ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) ) {
	WIC_DB_Setup::check_high_plane_collation();
	WIC_Admin_Navigation::dictionary_setup(); // need this for cron routing as well as admin
}

/*
* if is_admin, load necessary ( and only necessary ) components in admin
*
* all entry points to WP Issues CRM code are via the WIC_Admin_Navigation constructed in WIC_Admin_Setup
*    EXCEPT via cron jobs and as permitted by activate_interfaces (which allows other form plugins to save constituent/activity records after sanitization)
*	 See notes in WIC_Admin_Navigation
*
* start by checking plugin options
*/

$plugin_options = get_option( 'wp_issues_crm_plugin_options_array' );

if ( is_admin() ) { 
	$wic_admin_setup = new WIC_Admin_Setup;
} else { 
	// if hiding all private posts, even for admin, hook for front end main queries
	if ( isset ( $plugin_options['hide_private_posts'] ) ) { 
		// optionally control display of private posts
		add_action( 'pre_get_posts', 'keep_private_posts_off_front_end_even_for_administrators' );
	}
}

// activate filter/action hooks -- unconditional as to is_admin()
if ( WIC_Entity_External::activate_interfaces() ) {
	// dictionary must be setup for interfaces to work, will not double set up.
	WIC_Admin_Navigation::dictionary_setup();
};

// ajax version of post hider is added whether or not is_admin, but only executes in theme widgets and ajax add-ons corresponding to main queries 
if ( isset ( $plugin_options['hide_private_posts'] ) ) { 
	// special hook for compatibility with responsive tabs theme and other infinite scroll themes-- pass query args, not the query
	add_filter( 'responsive_tabs_ajax_pre_get_posts', 'keep_private_posts_off_front_end_even_for_administrators_ajax', 10, 1 );
}

/*
*
* schedule caching of addresses to look up -- will run regardless of settings; action depends on settings
*
*/
add_action ( 'wp_issues_crm_address_cache', 'WIC_Entity_Geocode::update_geocode_address_cache', 10, 0 );
if ( ! wp_get_schedule ( 'wp_issues_crm_address_cache' ) ) {
	wp_schedule_event( current_time( 'timestamp' ), 'every5min', 'wp_issues_crm_address_cache' );
}

/*
*
* scheduling of geocoding of cache entries either by wp cron or by cron tab setting of WIC_GEOCODING_CRON_RUN_KEY (arbitrary string)
*
* configuring cron run key will work in single site or for the primary site (which should serve all others) in multisite
*/
if ( defined( 'WIC_GEOCODING_CRON_RUN_KEY' ) && WIC_GEOCODING_CRON_RUN_KEY ) {
	// remove any scheduled values for synch/parse and send
	$timestamp = wp_next_scheduled( 'wp_issues_crm_do_geocode' );
	wp_unschedule_event( $timestamp, 'wp_issues_crm_do_geocode' );
	// if the cron_key is supplied (use same cron key as for mail program)
	if ( isset ( $_GET['wic_geocoding_cron_run_key'] ) ) {
		if ( WIC_GEOCODING_CRON_RUN_KEY == $_GET['wic_geocoding_cron_run_key'] ) {
			WIC_Entity_Geocode::lookup_geocodes();
		}
	}
} else {
	add_action ( 'wp_issues_crm_do_geocodes', 'WIC_Entity_Geocode::lookup_geocodes', 10, 0 );
	if ( ! wp_get_schedule ( 'wp_issues_crm_do_geocodes' ) ) {
		wp_schedule_event( current_time( 'timestamp' ) +90 , 'every5min', 'wp_issues_crm_do_geocodes' );
	}
}



/*
*
* schedule comment synch -- will run regardless of settings; action depends on settings
*
*/
add_action ( 'wp_issues_crm_synch_comments', 'WIC_Entity_Comment::synch_comments', 10, 1 );
if ( ! wp_get_schedule ( 'wp_issues_crm_synch_comments' ) ) {
	wp_schedule_event( current_time( 'timestamp' ), 'daily', 'wp_issues_crm_synch_comments' );
}
if ( isset ( $plugin_options['synch_comments_on'] ) ) {
	if ( 1 == $plugin_options['synch_comments_on'] ) {
		add_action ( 'wp_insert_comment', 'WIC_Entity_Comment::link_comment_to_constituent', 10, 2 ); 
		require_once plugin_dir_path( __FILE__ ) . 'php/entity/class-wic-entity-comment.php';
	};
}

//functions placed here so will be accessible on front end.
function keep_private_posts_off_front_end_even_for_administrators( $query ) { 
	if ( ! is_admin() ) { 
		// note that this does not prevent this plugin or widgets from showing private posts to which logged in user has access
   	$query->set( 'post_status', array( 'publish' ) );			
	}
}
function keep_private_posts_off_front_end_even_for_administrators_ajax( $query_args ) {  
		// note that this does not prevent this plugin or widgets from showing private posts to which logged in user has access
   	$query_args['post_status'] = 'publish';
   	return ( $query_args ); 			
}

// class autoloader is case insensitive, except that it requires WIC_ (sic) as a prefix.
// always register to support not only in admin, but on front facing forms and in cron runs
function wp_issues_crm_autoloader( $class ) {
	if ( 'WIC_' == substr ($class, 0, 4 ) ) {
		$subdirectory = 'php'. DIRECTORY_SEPARATOR . strtolower( substr( $class, 4, ( strpos ( $class, '_', 4  ) - 4 )  ) ) . DIRECTORY_SEPARATOR ;
		$class = strtolower( str_replace( '_', '-', $class ) );
		$class_file = plugin_dir_path( __FILE__ ) . $subdirectory .  'class-' . str_replace ( '_', '-', $class ) . '.php';
		if ( file_exists ( $class_file ) ) {  
   		require_once $class_file;
   	} else {
	   	wic_generate_call_trace();
			die ( '<h3>' . sprintf(  __( 'Fatal configuration error -- missing file %s; failed in autoload in wp-issues-crm.php, line 43.', 'wp_issues_crm' ), $class_file ) . '</h3>' );   
	   } 
	}	
}

// stack trace function for locating bad class definitions; 
function wic_generate_call_trace( $stifle = false ) { // from http://php.net/manual/en/function.debug-backtrace.php

	$e = new Exception();
	$trace = explode("\n", $e->getTraceAsString());
	// reverse array to make steps line up chronologically
	$trace = array_reverse($trace);
	array_shift($trace); // remove {main}
	array_pop($trace); // remove call to this method
	$length = count($trace);
	$result = array();
	for ($i = 0; $i < $length; $i++) {
		$result[] = ($i + 1) . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
	}
	if ( ! $stifle ) {
		echo "\t" . implode("<br/>\n\t", $result);
	} else {
		return "\t" . implode("<br/>\n\t", $result);
	}
}