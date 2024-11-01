<?php
/**
*
* class-wic-admin-settings.php
*
*/


class WIC_Admin_Settings {
	/* 
	*
	*/

	// for wp admin settings (not the main fields and field options)
	private $plugin_options;

	// sets up WP settings interface	
	public function __construct() { // class instantiated in plugin main 
		add_action( 'admin_init', array ( $this, 'settings_setup') );
		$this->plugin_options = get_option( 'wp_issues_crm_plugin_options_array' );
	}	
	
	// define setting
	public function settings_setup() {
		
		// registering only one setting, which will be an array -- will set up nonces when called
		register_setting(
			'wp_issues_crm_plugin_options', // Option Group (have only one option)
			'wp_issues_crm_plugin_options_array', // Option Name
			array ( $this, 'sanitize' ) // Sanitize call back
		);

		// settings sections and fields dictate what is output when do_settings_sections is called passing the page ID
		// here 'page' is collection of settings, and can, but need not, equal a menu registered page (but needs to be invoked on one)	


	  // Requirements information
	  add_settings_section(
		'requirements', // setting ID
		'Requirements', // Title
		array( $this, 'requirements_legend' ), // Callback
		'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
	); 

	  // Multisite information
	  add_settings_section(
		'multisite', // setting ID
		'Multisite', // Title
		array( $this, 'multisite_legend' ), // Callback
		'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
	); 

       // Security Settings
      add_settings_section(
            'security_settings', // setting ID
            'Security', // Title
            array( $this, 'security_settings_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

		// naming of the callback with array elements (in the callbacks) is what ties the option array together 		
      add_settings_field(
            'access_level_required', // field id
            'WP Issues CRM', // field label
            array( $this, 'access_level_required_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'security_settings' // settings section within page
       ); 

      add_settings_field(
            'access_level_required_view_edit_unassigned', // field id
            'Access unassigned', // field label
            array( $this, 'access_level_required_view_edit_unassigned_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'security_settings' // settings section within page
       ); 
			
      add_settings_field(
            'access_level_required_downloads', // field id
            'Bulk downloads/deletes', // field label
            array( $this, 'access_level_required_downloads_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'security_settings' // settings section within page
       ); 

      add_settings_field(
            'access_level_required_email', // field id
            'Access all email', // field label
            array( $this, 'access_level_required_email_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'security_settings' // settings section within page
       ); 

      add_settings_field(
            'access_level_required_send_email', // field id
            'Send email', // field label
            array( $this, 'access_level_required_send_email_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'security_settings' // settings section within page
       ); 

      add_settings_field(
            'access_level_required_list_send', // field id
            'Send email to lists', // field label
            array( $this, 'access_level_required_list_send_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'security_settings' // settings section within page
       ); 

       // Privacy Settings
      add_settings_section(
            'privacy_settings', // setting ID
            'Privacy', // Title
            array( $this, 'privacy_settings_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

		// naming of the callback with array elements (in the callbacks) is what ties the option array together 		
      add_settings_field(
            'all_posts_private', // field id
            'Make "Private" the default', // field label
            array( $this, 'all_posts_private_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'privacy_settings' // settings section within page
       ); 
			
      add_settings_field(
            'hide_private_posts', // field id
            'Always hide private posts.', // field label
            array( $this, 'hide_private_posts_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'privacy_settings' // settings section within page
       ); 

	  // Postal Interface Settings
      add_settings_section(
            'postal_address_interface', // setting ID
            'Zip Lookup', // Title
            array( $this, 'postal_address_interface_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'use_postal_address_interface', // field id
            'Enable USPS Web Interface', // field label
            array( $this, 'use_postal_address_interface_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'postal_address_interface' // settings section within page
       ); 
			
      add_settings_field(
            'user_name_for_postal_address_interface', // field id
            'USPS Web Tools User Name', // field label
            array( $this, 'user_name_for_postal_address_interface_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'postal_address_interface' // settings section within page
       ); 

      add_settings_field(
            'do_zip_code_format_check', // field id
            'Verify USPS zip format', // field label
            array( $this, 'do_zip_code_format_check_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'postal_address_interface' // settings section within page
       ); 

	  /*
	  *
	  * geocoding settings
	  *
	  */
      add_settings_section(
            'enable_geocoding', // setting ID
            'Geocoding', // Title
            array( $this, 'enable_geocoding_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'google_maps_api_key', // field id
            'Google Maps API Key', // field label
            array( $this, 'google_maps_api_key_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'enable_geocoding' // settings section within page
       ); 

      add_settings_field(
            'geocodio_api_key', // field id
            'Geocodio API Key', // field label
            array( $this, 'geocodio_api_key_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'enable_geocoding' // settings section within page
       ); 

	
	/*
	*
	* Email IMAP Access Settings
    *
    */ 
      add_settings_section(
            'email_imap_interface', // setting ID
            'Email In', // Title
            array( $this, 'email_imap_interface_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'email_imap_server', // field id
            'IMAP Server', // field label
            array( $this, 'email_imap_server_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_imap_interface' // settings section within page
       ); 
			
      add_settings_field(
            'user_name_for_email_imap_interface', // field id
            'User Name', // field label
            array( $this, 'user_name_for_email_imap_interface_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_imap_interface' // settings section within page
       ); 

	  // dummy field and setting
      add_settings_field(
            'password_for_email_imap_interface', // field id
            'Password', // field label
            array( $this, 'password_for_email_imap_interface_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_imap_interface' // settings section within page
       ); 

      add_settings_field(
            'use_ssl_for_email_imap_interface', // field id
            'Use SSL Security', // field label
            array( $this, 'use_ssl_for_email_imap_interface_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_imap_interface' // settings section within page
       ); 
       
      add_settings_field(
            'port_for_email_imap_interface', // field id
            'Server port', // field label
            array( $this, 'port_for_email_imap_interface_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_imap_interface' // settings section within page
       );
  
    add_settings_field(
            'imap_inbox', // field id
            'Inbox', // field label
            array( $this, 'imap_inbox_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_imap_interface' // settings section within page
       );
   
  add_settings_field(
		'imap_max_retries', // field id
		'Max Connection Failures', // field label
		array( $this, 'imap_max_retries_callback' ), // field call back 
		'wp_issues_crm_settings_page', // page 
		'email_imap_interface' // settings section within page
   );
       
	/*
	*
	* Email Reply Settings Settings
    *
    */ 
       add_settings_section(
            'email_smtp_interface', // setting ID
            'Email Out', // Title
            array( $this, 'email_smtp_interface_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

 
       add_settings_field(
         'max_send_email', // field id
         'Max count for send', // field label
          array( $this, 'max_send_email_callback' ), // field call back 
          'wp_issues_crm_settings_page', // page 
          'email_smtp_interface' // settings section within page
       );

       add_settings_field(
            'smtp_user', // field id
            'User', // field label
            array( $this, 'smtp_user_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 

       add_settings_field(
            'from_email', // field id
            'From Email Address', // field label
            array( $this, 'from_email_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 
 
       add_settings_field(
            'from_name', // field id
            'From Email Name', // field label
            array( $this, 'from_name_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 
 
        add_settings_field(
            'smtp_reply', // field id
            'Reply To Email', // field label
            array( $this, 'smtp_reply_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 

       add_settings_field(
            'reply_name', // field id
            'Reply To Name', // field label
            array( $this, 'reply_name_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 

      add_settings_field(
            'email_send_tool', // field id
            'Email Send Tool', // field label
            array( $this, 'email_send_tool_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 
      add_settings_field(
            'email_smtp_server', // field id
            'SMTP Server', // field label
            array( $this, 'email_smtp_server_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       );
        
       // dummy field and setting
       add_settings_field(
            'smtp_password', // field id
            'SMTP Password', // field label
            array( $this, 'smtp_password_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 
       add_settings_field(
            'smtp_secure', // field id
            'SMTP Security', // field label
            array( $this, 'smtp_secure_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 
       add_settings_field(
            'smtp_port', // field id
            'SMTP Port', // field label
            array( $this, 'smtp_port_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 

        add_settings_field(
            'require_good_ssl_certificate', // field id
            'Enforce Certificate?', // field label
            array( $this, 'require_good_ssl_certificate_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 

       add_settings_field(
            'peer_name', // field id
            'Alternative Certificate Name', // field label
            array( $this, 'peer_name_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       );

       add_settings_field(
            'suppress_gssapi', // field id
            'Disable GSSAPI', // field label
            array( $this, 'suppress_gssapi_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       );

       add_settings_field(
            'use_IPV4', // field id
            'Force IPV4', // field label
            array( $this, 'use_IPV4_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 


       add_settings_field(
            'smtp_debug_level', // field id
            'Details on error', // field label
            array( $this, 'smtp_debug_level_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 
  
      
     	add_settings_field(
            'send_mail_sleep_time', // field id
            'Wait Time between Sends', // field label
            array( $this, 'send_mail_sleep_time_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'email_smtp_interface' // settings section within page
       ); 

	/* 
	*
	* financial transactions settings
	*
	*/
      add_settings_section(
            'enable_financial_activities', // setting ID
            'Financial', // Title
            array( $this, 'enable_financial_activities_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'financial_activity_types', // field id
            'Financial Activity Type Codes', // field label
            array( $this, 'financial_activity_types_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'enable_financial_activities' // settings section within page
       ); 
 
 		// activity freeze 
      add_settings_section(
            'freeze_older_activities', // setting ID
            'Freeze', // Title
            array( $this, 'freeze_older_activities_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'freeze_older_activities', // field id
            'Activity Freeze Cutoff', // field label
            array( $this, 'freeze_older_activities_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'freeze_older_activities' // settings section within page
       ); 
   
   	/* 
	*
	* synch comments settings
	*
	*/
      add_settings_section(
            'synch_comments', // setting ID
            'Comments', // Title
            array( $this, 'comment_synch_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'synch_comments_on', // field id
            'Synch Comments', // field label
            array( $this, 'synch_comments_on_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'synch_comments' // settings section within page
       ); 
     add_settings_field(
            'report_missing_emails', // field id
            'Report Missing Emails', // field label
            array( $this, 'report_missing_emails_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'synch_comments' // settings section within page
       );  
  
    /* 
	*
	* custom css input
	*
	*/
      add_settings_section(
            'wic_override_css', // setting ID
            'Custom CSS', // Title
            array( $this, 'wic_override_css_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

      add_settings_field(
            'wic_override_css', // field id
            'Custom CSS', // field label
            array( $this, 'wic_override_css_callback' ), // field call back 
            'wp_issues_crm_settings_page', // page 
            'wic_override_css' // settings section within page     
     );
         
	  // Uninstall Settings (legend only)
      add_settings_section(
            'uninstall', // setting ID
            'Uninstall', // Title
            array( $this, 'uninstall_legend' ), // Callback
            'wp_issues_crm_settings_page' // page ID ( a group of settings sections)
        ); 

	}

	/*
	* Requirements legend callback ( no fields )
	*
	*
	*/
	public function requirements_legend () {
		
		?>
		<h3>Browser</h3>
		<p>WP Issues CRM is a client-server application and makes heavy use of javascript.  It requires an up-to-date browser, preferably Chrome.</p>
			<table class="wp-issues-crm-stats">
				<tbody>
					<tr><td>Chrome -- performs best.</td></tr>
					<tr><td>Firefox -- also recommended, and supports better privacy, but may generate errors when accessing browser history due to internal limits.</td></tr>
					<tr><td>Safari -- not well supported; the open source editor we use for email composition, tinymce, does not perform perfectly in Safari.</td></tr>
					<tr><td>Explorer/Edge -- NOT supported;  Explorer does not support all modern Javascript language constructs that WP Issues CRM requires.  Edge does not handle formatting consistently.</td></tr>
			</table>
		<p><em>The basic WP Issues CRM forms and lists are responsive for mobile browsers, but look better on a desktop. 
		The more complex functions (email processing) do require desktop size screens.</em></p>
			
		<h3>Server Software</h3>
		<p>WP Issues CRM does not require any server software other than <a href="https://wordpress.org/about/requirements/">what is recommended for Wordpress</a> and/or routinely installed on most Web Hosts.</p>
		<table class="wp-issues-crm-stats">
			<colgroup>
				<col style="width:20%">
				<col style="width:10%">
				<col style="width:10%">
				<col style="width:10%">
				<col style="width:50%">
			</colgroup>
			<tbody>
				<tr><th class = "wic-statistic-text">Software Element</th><th class = "wic-statistic-text">Best</th><th class = "wic-statistic-text">Required</th><th class = "wic-statistic-text">Installed</th><th class = "wic-statistic-text">Recommendation</th></tr>
				<tr><td>PHP Version</td><td>7.3</td><td>5.6</td><td><?php echo phpversion() ?></td>
					<?php 
						if ( version_compare ( phpversion(), 5.6, '>='  ) ) {
							echo '<td class="requirements-ok">Good to go.</td>'; 
						} else {
							echo '<td class="requirements-not-ok">You should upgrade -- <a href="https://wordpress.org/about/requirements/">see recommendations here.</a>';
						} 
				?></tr>
				<tr><td>Character type checking</td><td>ctype</td><td>ctype</td><td><?php echo ( function_exists ('ctype_alnum') ? 'OK' : 'Missing' ) ?></td>
					<?php 
						if (  function_exists ('ctype_alnum') ) {
							echo '<td class="requirements-ok">Good to go.</td>'; 
						} else {
							echo '<td class="requirements-not-ok">Ask your hosting provider to recompile php with the <a href="http://php.net/manual/en/book.ctype.php">ctype extension</a>.</td>';
						} 
				?></tr>
				<tr><td>Character set converter</td><td>iconv</td><td>iconv</td><td><?php echo ( function_exists ('iconv') ? 'OK' : 'Missing' ) ?></td>
					<?php 
						if (  function_exists ('iconv') ) {
							echo '<td class="requirements-ok">Good to go.</td>'; 
						} else {
							echo '<td class="requirements-not-ok">Ask your hosting provider to recompile php with the <a href="http://php.net/manual/en/book.iconv.php">iconv extension</a>.</td>';
						} 
				?></tr>
				<tr><td>Incoming email reader</td><td>imap</td><td>imap</td><td><?php echo ( function_exists ('imap_open') ? 'OK' : 'Missing' ) ?></td>
					<?php 
						if (  function_exists ('imap_open') ) {
							echo '<td class="requirements-ok">Good to go.</td>'; 
						} else {
							echo '<td class="requirements-not-ok">Ask your hosting provider to recompile php with the <a href="http://php.net/manual/en/book.imap.php">imap extension</a>.</td>';
						} 
				?></tr>
				<tr><td>Secure connection</td><td>openssl</td><td>openssl</td><td><?php echo ( function_exists ('openssl_decrypt') ? 'OK' : 'Missing' ) ?></td>
					<?php 
						if (  function_exists ('openssl_decrypt') ) {
							echo '<td class="requirements-ok">Good to go.</td>'; 
						} else {
							echo '<td class="requirements-not-ok">Ask your hosting provider to recompile php with the <a href="http://php.net/manual/en/book.openssl.php">imap extension</a>.</td>';
						}
				?></tr>
				<tr><td>MySQL Version</td><td>5.6.4</td><td>5.6.4</td><td><?php $version = self::get_mysql_version(); echo $version; ?></td>
					<?php 
						if ( version_compare ( $version, 5.6, '>=' ) ) {
							echo '<td class="requirements-ok">Good to go.</td>'; 
						} elseif ( version_compare ( $version, '5.2', '>=' ) ) {
							echo '<td class="requirements-probably-ok">WP Issues CRM should work OK, but upgrade is <a href="https://wordpress.org/about/requirements/">recommended for Wordpress.</a> WP Issues CRM has been most heavily tested with MySQL Version 5.6.</td>';
						} else {
							echo '<td class="requirements-not-ok">You should upgrade -- <a href="https://wordpress.org/about/requirements/">see recommendations here.</a></td>';
						}  
				?></tr>
		</table>
		<p><em>WP Issues CRM also requires PHPMailer and common javascript libraries -- JQuery and JQuery UI -- but these are packaged with Wordpress.</em></p>

		<h3>Server Resources</h3>
		<p>WP Issues CRM generally performs well on inexpensive web hosts with low resource limits.  Tasks are chunked to minimize execution time and memory use. However, some hosting companies have aggressive usage
		monitoring and will kill longer or more intensive tasks without notice.  If a task is not running to completion ask your hosting provider, about process killers and time limits -- the only likely long task is the very first email inbox synch on a big inbox.</p>
		<p>Low PHP Settings can also be a barrier occasionally.  WP Issues CRM is optimized to stay within low limits and these settings should not be a problem, but this is another 
		thing to check with your hosting provider if initial synch runs for an email inbox with thousands of messages are not completing.</p>
		<table class="wp-issues-crm-stats">
			<colgroup>
				<col style="width:20%">
				<col style="width:10%">
				<col style="width:10%">
				<col style="width:50%">
			</colgroup>
			<tbody>
				<tr><th class = "wic-statistic-text">PHP Parameter</th><th class = "wic-statistic-text">Adequate</th><th class = "wic-statistic-text">Installed</th><th class = "wic-statistic-text">Comment</th></tr>
				<tr><td>Max Execution Time</td><td>120</td><td><?php echo ini_get('max_execution_time') ?></td><td>The mail inbox synch function will run up to 120 seconds if it has work to do.  It will request this time from the server at run 
				time, but your hosting company's policies may or may not give it the requested time if the installed limit shown here is lower.  All other processes happen in much smaller chunks.</td></tr>
				<tr><td>Memory Limit</td><td>256M</td><td><?php echo ini_get('memory_limit') ?></td><td>More memory always feels safer, but WP Issues CRM generally does not require memory proportional to data volume.</td></tr>
				<tr><td>Max Input Time</td><td>--</td><td><?php echo ini_get('max_input_time') ?></td><td>This is unlikely to matter -- uploading is chunked.</td></tr>
				<tr><td>Upload Max Filesize</td><td>--</td><td><?php echo ini_get('upload_max_filesize') ?></td><td>This is unlikely to matter -- uploading is chunked.</td></tr>
				<tr><td>Post Max Size</td><td>--</td><td><?php echo ini_get('post_max_size') ?></td><td>This is unlikely to matter -- WP Issues CRM breaks most form post transactions into smaller AJAX transactions.</td></tr>
		</table>




		<?php
	} 

	private static function get_mysql_version () {
		global $wpdb;
		$results = $wpdb->get_results ( "show variables like 'version'" );
		return $results[0]->Value;
	}

	/*
	*
	* multisite call back -- legend only
	*
	*/
	public function multisite_legend() {
		
	?>
		<h5>Is Multisite Installed?</h5>
		<p>
			<?php if ( is_multisite() )  { echo 'Yes.  This site is part of a multisite network.'; } else { echo 'No. This site is configured as a standalone site.'; } ?>
		</p>
		<h5>Is this site enabled to synchronize with a central database?</h5>
		<p>
			<?php 
			$owner_id = get_option ( 'wic_owner_id' );
			$owner_type = get_option ( 'wic_owner_type' );
			if ( is_multisite() && $owner_type )  { echo "Yes.  This site is authorized to synchronize to a central database using the following segment owner_type and owner_id: <em>$owner_type</em> -- <em>$owner_id</em> ."; } 
			else { echo 'No. This site is not configured to synchronize with a central database.  An owner_id can be configured by a network administrator for secondary sites under the Data Owners submenu which appears 
			for them when they are viewing WP Issues CRM on the primary site.'; } ?>
		</p>

		<h5>Background</h5>
		<p>In a multisite network, WP Issues CRM supports synchronization between a central database maintained under WP Issues CRM on the primary site 
			<?php if ( is_multisite() && BLOG_ID_CURRENT_SITE != get_current_blog_id() ) { 
				switch_to_blog ( BLOG_ID_CURRENT_SITE ); 
				$site_title = get_option ( 'blogname '); 
				restore_current_blog(); 
				echo "(in this network, <em>$site_title</em>)";  
			} elseif ( is_multisite() && BLOG_ID_CURRENT_SITE == get_current_blog_id() ) {
				echo "(this site)";
			} else { echo ""; } ?> 		
		and copies on secondary sites.  Synchronization is controlled by the owner_id that network administrators assign to the secondary site -- the secondary site will only synchronize with constituent records
		having that same owner_id in the owner_id field on the primary site.  

		</p>
			
		<p>If multisite and synchronization are both enabled for this site a Synchronization option should be showing on the WP Issues CRM submenu for administrators.</p>

		<?php	
	
	
	}


	/*
	*
	* Menu Position Callbacks
	*
	*/
	// section legend call back
	public function menu_position_legend() {
		echo '<p>' . __('By default WP Issues CRM will appear at the bottom of the left sidebar menu in the Wordpress Admin Screen.  Use this setting
		to promote it to the position just above Posts.', 'wp-issues-crm' ) . '</p>';
	}

	/*
	*
	* Security Callbacks
	*
	*/
	// section legend call back
	public function security_settings_legend() {
		echo'<p>Only logged-in users can access WP Issues CRM.</p>' . 
			'<p>You can control who among your logged-in users can access:</p>  
				<ul>
				<li>any WP Issues CRM functions</li>
				<li>constituents and issues that have not been assigned to them</li>
				<li>bulk constituent and activity downloads and deletion</li>
				<li>unassigned emails and general email capability, other than sending</li>
				<li>email archiving and sending</li>
				<li>sending emails to lists</li>
			</ul>
			<p>Note that all users have access to the "Assigned" and "Ready" tabs of the email inbox, but only those with sending authority can see emails that are not specifically assigned to them.  
			Administrators always have access to WP Issues CRM and all four specific capabilities.  Constituents created or last updated by a user may always be accessed by the user, even if not formally assigned to them.</p>
			<p>You assign users to roles ("Administrator", "Editor" . . . ) as part of their user profiles.  Here, you grant access to user roles with particular <a href="https://codex.wordpress.org/Roles_and_Capabilities" target = "_blank">Wordpress capabilities</a>. 
			Access is hierarchical.  For example, if you grant access to "Authors", then those with additional capabilities, "Editors" and "Administators", will also have access, but "Contributors" and "Subscribers" will not.</p>
			<p>WP Issues CRM adds a special role possibility. You can assign to some users the role "Constituent Managers".   
			They will have no Wordpress editing capabilities and will have access to WP Issues CRM only if you choose
			"Only Constituent Managers and Administrators" here.</p><p>Regardless of these settings, only administrators have access to this settings interface and the structural management functions of WP Issues CRM
			-- Fields, Options, Interfaces, Storage Management.</p>';
	}

	// setting field call back	
	public function access_level_required_callback() { 
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'capability_levels' );		
		
		$value = isset ( $this->plugin_options['access_level_required'] ) ? $this->plugin_options['access_level_required'] : 'edit_theme_options';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    	=> $option_array,
			'input_class' 	   	=> '',
			'field_slug_css'	=> '',
			'hidden'			=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[access_level_required]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}

	// setting field call back	
	public function access_level_required_downloads_callback() {
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'capability_levels' );		
		
		$value = isset ( $this->plugin_options['access_level_required_downloads'] ) ? $this->plugin_options['access_level_required_downloads'] : 'edit_theme_options';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    	=> $option_array,
			'input_class' 	   	=> '',
			'field_slug_css'	=> '',
			'hidden'			=> '',
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[access_level_required_downloads]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}	

	// setting field call back	
	public function access_level_required_email_callback() {
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'capability_levels' );		
		
		$value = isset ( $this->plugin_options['access_level_required_email'] ) ? $this->plugin_options['access_level_required_email'] : 'edit_theme_options';

		$args = array (
			'field_label'	 	=> '',
			'option_array'    	=> $option_array,
			'input_class' 	   	=> '',
			'field_slug_css'	=> '',
			'hidden'			=> '',
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[access_level_required_email]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}	

	// setting field call back	
	public function access_level_required_send_email_callback() {
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'capability_levels' );		
		
		$value = isset ( $this->plugin_options['access_level_required_send_email'] ) ? $this->plugin_options['access_level_required_send_email'] : 'edit_theme_options';

		$args = array (
			'field_label'	 	=> '',
			'option_array'    	=> $option_array,
			'input_class' 	   	=> '',
			'field_slug_css'	=> '',
			'hidden'			=> '',
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[access_level_required_send_email]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}	


	// setting field call back	
	public function access_level_required_view_edit_unassigned_callback() {
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'capability_levels' );		
		
		$value = isset ( $this->plugin_options['access_level_required_view_edit_unassigned'] ) ? $this->plugin_options['access_level_required_view_edit_unassigned'] : 'edit_theme_options';

		$args = array (
			'field_label'	 	=> '',
			'option_array'    	=> $option_array,
			'input_class' 	   	=> '',
			'field_slug_css'	=> '',
			'hidden'			=> '',
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[access_level_required_view_edit_unassigned]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}

	// setting field call back	
	public function access_level_required_list_send_callback() {
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'capability_levels' );		
		
		$value = isset ( $this->plugin_options['access_level_required_list_send'] ) ? $this->plugin_options['access_level_required_list_send'] : 'edit_theme_options';

		$args = array (
			'field_label'	 	=> '',
			'option_array'    	=> $option_array,
			'input_class' 	   	=> '',
			'field_slug_css'	=> '',
			'hidden'			=> '',
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[access_level_required_list_send]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}

	/*
	*
	* Privacy Callbacks
	*
	*/
	// section legend call back
	public function privacy_settings_legend() {
		echo '<p>' . __('The "Issues" created within WP Issues CRM are just Wordpress posts that are automatically created as private. 
		Public posts cannot be created, nor their content altered, in WP Issues CRM. (Public posts
		can, however, be searched for as issues and viewed through WP Issues CRM, and they can be used to classify activities. 
		Additionally, one can change the title and categories of pubic posts through WP Issues CRM.)', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __( 'From time to time, you may prefer to use the main Wordpress post editor, which has more features, to create or edit private issues.  
		To minimize risk of accidentally publicizing private issues through the Wordpress post editor, check the box below to 
		make "private" the default setting for all Wordpress posts.  Either way, you an always override the default visibility 
		setting in the "Publish" metabox in the Wordpress post editor.', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __('Private issues and posts are not visible on the front end of your website except 
		to administrators and possibly the post authors.  So, there is no risk of disclosing private issues/posts,
		but if they are cluttering the administrator view of the front end, you can exclude them from front end queries using the setting here.', 'wp-issues-crm' ) . '</p>';
	}

	// setting field call back	
	public function all_posts_private_callback() {
		printf( '<input type="checkbox" id="all_posts_private" name="wp_issues_crm_plugin_options_array[all_posts_private]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['all_posts_private'] ), false ) );
	}

	// setting field call back	
	public function hide_private_posts_callback() {
		printf( '<input type="checkbox" id="hide_private_posts" name="wp_issues_crm_plugin_options_array[hide_private_posts]" value="%s" %s />',
            1, checked( '1', isset( $this->plugin_options['hide_private_posts'] ), false ) );
	}	
	
	/*
	*
	* Postal Address Interface Callbacks
	*
	*/
	// section legend call back
	public function postal_address_interface_legend() {
		echo '<div id="usps"><p>' . __( 'WP Issues CRM supports the <code>USPS Web Interface</code> to the ', 'wp-issues-crm' ) . '<a href="https://www.usps.com/business/web-tools-apis/address-information.htm">United States Postal Service Address Information API.</a>' .  
		__( ' This service will standardize and add zip codes to addresses entered for constituents.', 'wp-issues-crm' ) . '</p>'; 
		if ( ! defined ( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE') )  {
			echo '<p>' . __(' To use it, you need to get a User Name from the USPS:', 'wp-issues-crm' ) . '</p>' .
			'<ol><li>' . __('Register for USPS Web Tools by filling out', 'wp-issues-crm' ) . ' <a href="https://registration.shippingapis.com/">' . __( 'an online form.', 'wp-issues-crm' ) . '</a></li>' .
				'<li>' . __( 'After completing this form, you will receive an email from the USPS.  Forward that email back to ', 'wp-issues-crm' ) . '
				<a href="mailto:uspstechnicalsupport@mailps.custhelp.com">uspstechnicalsupport@mailps.custhelp.com</a> ' . __( 'with the subject line "Web Tools API Access"
				and content simply asking for access.', 'wp-issues-crm' ) . '</li>' .
				'<li>' . __( 'The USPS will reply seeking confirmation essentially that the access is not for bulk processing and will promptly grant you access.', 'wp-issues-crm' ) . '</li>' .
				'<li>' . __( 'Once they have sent an email granting access to the API, enter Username that they give you below and enable the Interface.  Note that you do not need to
				enter the password that they give you.', 'wp-issues-crm' ) . '</li>' .
			'</ol>';
		}
		echo '</div>
		<p>' . __( 'You can also select <code>Verify USPS zip format</code> without using the address interface.  This will just check that the zip code looks like a zip code without comparing it to the address entered.', 'wp-issues-crm' ) . '</p>';
				
	}

	// setting field call back	
	public function use_postal_address_interface_callback() {
		printf( '<input type="checkbox" id="use_postal_address_interface" name="wp_issues_crm_plugin_options_array[use_postal_address_interface]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['use_postal_address_interface'] ), false ) );
	}

	// setting field call back
	public function user_name_for_postal_address_interface_callback() {
		$value = isset( $this->plugin_options['user_name_for_postal_address_interface'] ) ? $this->plugin_options['user_name_for_postal_address_interface']: '';
		if ( defined ( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE') ) {
			printf( 'This parameter has been predefined for you in your wordpress configuration.');
		} else {
			printf( '<input type="text" id="user_name_for_postal_address_interface" name="wp_issues_crm_plugin_options_array[user_name_for_postal_address_interface]"
					value ="%s" />', $value );
			}
		}
	public function do_zip_code_format_check_callback() {
		printf( '<input type="checkbox" id="do_zip_code_format_check" name="wp_issues_crm_plugin_options_array[do_zip_code_format_check]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['do_zip_code_format_check'] ), false ) );
	}

	/*
	*
	* geocoding callbacks
	*
	*/

	// section legend call back
	public function enable_geocoding_legend() {
		echo '<div id = "geocode_settings"><p>' . __( 'WP Issues CRM can present individual addresses and lists of addresses on Google Maps using latitude and longitude data from Geocodio or your preferred source.', 'wp-issues-crm' ) . '</p>'; 
		if ( ! defined ( 'WIC_GOOGLE_MAPS_API_KEY') )  {
			echo '<p>' . __(' To enable this you need to get an API Key from Google and enter it here. ', 'wp-issues-crm' ) . '</p>' .
			'<ol><li>' . '<a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target = "_blank">' . __( 'Get a google maps API Key here.', 'wp-issues-crm' ) . '</a></li>' .
				'<li>' . __( 'Make sure that you enable all the capacities of the Platform -- Maps, Routes and Places -- for your key.', 'wp-issues-crm' ) . '</li>' .
				'<li>' . __( 'Enter and save the key below.', 'wp-issues-crm' ) . '</li>' .
				'<li>' . __( 'Note that depending on your volume, you may incur costs with Google.', 'wp-issues-crm' ) . '</li>' .

			'</ol>';
		}
		if ( ! defined ( 'WIC_GEOCODIO_API_KEY') )  {
			echo '<p>' . __('To present addresses on a map, you need to also have the latitude and longitude for each address. ', 'wp-issues-crm' ) . '</p>' .
			'<ol><li>' . '<a href="https://wp-issues-crm.com/mapping-addresses/" target ="_blank">' .  __('Build your own custom lookup mechanism', 'wp-issues-crm' ) . '</a>' .  
						__(' or use the Geocodio lookup service.', 'wp-issues-crm' ) . ' <a href="https://dash.geocod.io/usage" target = "_blank">' . __( 'Get a Geocodio API Key here.', 'wp-issues-crm' ) . '</a></li>' .
				'<li>' . __( 'Enter and save the Geocodio API key below', 'wp-issues-crm' ) . '</li>' .
				'<li>' . __( 'Note that depending on your volume, you may incur costs with Geocodio.', 'wp-issues-crm' ) . '</li>' .
				'<li>' . __( 'If you upload many new addresses, they will be geocoded at a rate of approximately 100,000 per hour through Geocodio.', 'wp-issues-crm' ) . '</li>' .

			'</ol>';
		}	
		echo '</div>';	
	}            

	// setting field call back
	public function google_maps_api_key_callback() {

		$value = isset( $this->plugin_options['google_maps_api_key'] ) ? $this->plugin_options['google_maps_api_key']: '';
		if ( ! defined ( 'WIC_GOOGLE_MAPS_API_KEY') )  {
			printf( '<input type="text" id="google_maps_api_key" name="wp_issues_crm_plugin_options_array[google_maps_api_key]"
				value ="%s" />', $value );
		} else {
			echo 'Google Maps API Key has been pre-defined for you in this installation.';
		}
	}	

	// setting field call back
	public function geocodio_api_key_callback() {
		$value = isset( $this->plugin_options['geocodio_api_key'] ) ? $this->plugin_options['geocodio_api_key']: '';
		if ( ! defined ( 'WIC_GEOCODIO_API_KEY') )  { 
			printf( '<input type="text" id="geocodio_api_key" name="wp_issues_crm_plugin_options_array[geocodio_api_key]"
				value ="%s" />', $value );
		} else {
			echo 'Geocodio API Key has been pre-defined for you in this installation.';
		}
	}	

	
	/*
	*
	* Email IMAP Interface Callbacks
	*
	*/
	// section legend call back
	public function email_imap_interface_legend() {
		
		if( 'legacy' != WIC_Entity_Email_Account::get_read_account() ) {
			echo '<h4>The legacy connection settings below will be disregarded because you have selected an incoming email provider in the email settings in the inbox.</h4>';
		}
		
		self::check_for_imap_extension();
				
		echo '<div id="imap"><p>' . __( 'The WP Issues CRM Process Email function supports direct access to a standard Inbox on your email server.', 'wp-issues-crm' ) . 
		__( ' This allows you to easily record incoming email traffic into WP Issues CRM.  You can read the emails in the Inbox ( or other designated folder ) on your server and create new constituent and activity records from them.', 'wp-issues-crm' ) .
		__( ' If you also configure outgoing email, you can automatically turn around templated replies to your incoming emails.' , 'wp-issues-crm' ) .
		 '</p>' .  
		 '<p>' . __(' You may need to check IMAP settings with your email administrator, but the suggestions below are the most common settings:', 'wp-issues-crm' ) . '</p></div>';
	
		$test_button_args = array(
			'button_class'				=> 'wic-form-button wic-form-button test-button',
			'button_label'				=> __('Test Saved Settings', 'wp-issues-crm'),
			'type'						=> 'button',
			'name'						=> 'wic-email-receive-test-button',
			'id'						=> 'wic-email-receive-test-button',
			);	
		echo WIC_Form_Parent::create_wic_form_button ( $test_button_args );
				
	}


	public static function check_for_imap_extension () {
		if ( ! function_exists ( 'imap_open' ) ) {
			echo '<div class="wic-missing-function"><p>The mail functions of WP Issues CRM require the IMAP extension of PHP, which appears to be missing in your PHP installation. 
			You can share the following link with your hosting provider when you ask them to install it for you:</p>
			
			<p>PHP Manual: <a href="http://php.net/manual/en/book.imap.php">http://php.net/manual/en/book.imap.php</a></p></div>';
		}
		if ( ! function_exists ( 'iconv' ) ) {
			echo '<div class="wic-missing-function"><p>The mail functions of WP Issues CRM require the ICONV extension of PHP, which appears to be missing in your PHP installation. 
			You can share the following link with your hosting provider when you ask them to install it for you:</p>
			
			<p>PHP Manual: <a href="http://php.net/manual/en/book.iconv.php">http://php.net/manual/en/book.iconv.php</a></p></div>';
		}	
	}
	// setting field call back
	public function email_imap_server_callback() {
		$value = isset( $this->plugin_options['email_imap_server'] ) ? $this->plugin_options['email_imap_server']: '';
		printf( '<input type="text" id="email_imap_server" name="wp_issues_crm_plugin_options_array[email_imap_server]"
				value ="%s" />', $value );
		echo __( ' e.g., example.com or www.example.com' , 'wp-issues-crm' );
		}	
	// setting field call back
	public function user_name_for_email_imap_interface_callback() {
		$value = isset( $this->plugin_options['user_name_for_email_imap_interface'] ) ? $this->plugin_options['user_name_for_email_imap_interface']: '';
		printf( '<input type="text" id="user_name_for_email_imap_interface" name="wp_issues_crm_plugin_options_array[user_name_for_email_imap_interface]"
				value ="%s" />', $value );
		echo __( ' usually full email ID ', 'wp-issues-crm' );
		}
	// setting field call back -- dummy variable replaced
	public function password_for_email_imap_interface_callback() {
		printf( '<input id="password_for_email_imap_interface" name="wp_issues_crm_plugin_options_array[password_for_email_imap_interface]"
				value ="s" />', '' );
		}				
	// setting field call back
	public function use_ssl_for_email_imap_interface_callback() {
		printf( '<input type="checkbox" id="use_ssl_for_email_imap_interface" name="wp_issues_crm_plugin_options_array[use_ssl_for_email_imap_interface]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['use_ssl_for_email_imap_interface'] ), false ) );
        echo __( ' recommended  protocol, but may not be supported by your email server' );
	}
	// setting field call back
	public function port_for_email_imap_interface_callback() {
		$value = isset( $this->plugin_options['port_for_email_imap_interface'] ) ? $this->plugin_options['port_for_email_imap_interface']: '';
		printf( '<input type="text" size="3" id="port_for_email_imap_interface" name="wp_issues_crm_plugin_options_array[port_for_email_imap_interface]"
				value ="%s" />', $value );
		echo __( ' usually 143 for unsecure email server access or 993 for SSL', 'wp-issues-crm' );
	}

	// set up a select with on option initially ( so immediately in the $_POST array in case of quick save), 
	// and then fill it in on document ready through WIC_Entity_Email_Connect::imap_inbox_callback_ajax 
	public function imap_inbox_callback () {
		echo '<div id="imap_inbox_select_wrapper">';
			$value = isset ( $this->plugin_options['imap_inbox'] ) ? $this->plugin_options['imap_inbox'] : '';
			$args = array (
				'field_label'	 	=> '',
				'option_array'    	=> array (
					array ( 
						'value' => $value, 
						'label' => ( $value > '' ? substr( strrchr( $value, "}"), 1) : '' ) 
					 ),
				),
				'input_class' 	   	=> '',
				'field_slug_css'	=> '',
				'hidden'			=> 0,
				'field_slug'		=> 'wp_issues_crm_plugin_options_array[imap_inbox]',
				'value'				=> $value ,		
			);		
			echo WIC_Control_Select::create_control( $args );		
		echo '</div>';
	}

	// set up a select with on option initially, 
	public function imap_max_retries_callback () {
		if ( defined ( 'WP_ISSUES_CRM_MAX_POLLING_RETRIES' ) ) {
			echo WP_ISSUES_CRM_MAX_POLLING_RETRIES . ' consecutive polling runs ending in a connection failure will be accepted before suspending polling
				 and asking user to check settings when they next access inbox; this is a network setting and can only be altered by a network administrator';
		} else {
			$value = isset ( $this->plugin_options['imap_max_retries'] ) ? $this->plugin_options['imap_max_retries'] : '2';
			$args = array (
				'field_label'	 	=> '',
				'option_array'    	=> array (
					array ( 'value' => 1,'label' => 1 ),
					array ( 'value' => 2,'label' => 2 ),
					array ( 'value' => 3,'label' => 3 ),
					array ( 'value' => 5,'label' => 5 ),
					array ( 'value' => 10,'label' => 10 ),
				),
				'input_class' 	   	=> '',
				'field_slug_css'	=> '',
				'hidden'			=> 0,
				'field_slug'		=> 'wp_issues_crm_plugin_options_array[imap_max_retries]',
				'value'				=> $value ,		
			);		
			echo WIC_Control_Select::create_control( $args );
			echo (' consecutive polling runs ending in a connection failure should be accepted before suspending polling and asking user to check settings when they next access inbox' );		
		}
	}


	/*
	*
	* Email Send Callbacks
	*
	*/
	// section legend call back
	public function email_smtp_interface_legend() {
		
		$send_account =  WIC_Entity_Email_Account::get_send_account();
		$send_account_label = WIC_Function_Utilities::value_label_lookup_cold ( $send_account, 'send_account_options' );
	
		if( 'legacy' != $send_account ) {
			echo '<h4>Most of the legacy connection settings below will be disregarded because you have selected <code>' . $send_account_label .  '</code> in the email settings in the inbox.' .
				 ( 'gmail' == $send_account ? ' The <code>From Email Name</code>, <code>Reply To Email</code> and <code>Reply To Name</code> settings will be used in your sent emails. ' : '' ) .
				 ( in_array( $send_account, array ( 'gmail', 'exchange' ) ) && ( ! defined('WP_ISSUES_CRM_USING_CENTRAL_CRON_CONTROL') || ! WP_ISSUES_CRM_USING_CENTRAL_CRON_CONTROL ) ? 
				 	' The <code>Wait Time between Sends</code> setting will be used to control send pacing.' : ''
				 ) . '</h4>';
		}
		
		self::check_for_imap_extension();
	
		echo '<div id="smtp"><p>' . __( 'You can use the settings here to send email from WP Issues CRM instead of configuring a particular provider through the inbox settings:', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __( 'Here, you can set WP Issues CRM to use one of three alternative tools:', 'wp-issues-crm' ) . '</p>' .
		'<ol><li>' . __( 'SMTP -- this requires that you have another server that you can direct WP Issues CRM to log in to and use as if it were an email app or webmail client.  This approach is the most flexible.' . 
					' and, depending on your server may offer superior deliverability, but it is slower and requires getting settings right below.', 'wp-issues-crm' ) . '</li>' .
			'<li>' . __( 'Generic PHP mail.  This is only recommended for very low volume sending. 
			 You will likely run into deliverability problems if you send more than a few emails this way. May not detect failed sends. ', 'wp-issues-crm' ) .'</li>' .
			'<li>' . __( 'Sendmail -- faster than PHP mail, but still likely to have deliverability issues, especially at higher volumes. ', 'wp-issues-crm' ) .  '</li>' .
		'</ol> </p>  <p>' . __(' Configure for SMTP as if configuring an email client like Outlook. ', 'wp-issues-crm' ) .
			 __( 'For help in troubleshooting, consult your email administrator.  Refer also to the ', 'wp-issues-crm' ) . '<a href="https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting" target="_blank"> PHPMailer troubleshooting guide.</a>' .
				__( ' WP Issues CRM uses the PHPMailer version packaged in your standard Wordpress installation.', 'wp-issues-crm' ) . '</p>' .
			'<p>' . __( 'When starting out, it is a good idea to set a Wait time between sends -- 2000 (2 seconds) is probably a safe starting value.  The necessary delay depends entirely on your mail server.', 'wp-issues-crm' ) . '</p>' .
				
		'</div>';
				
		// the following material is from phpmailer
		$warnings = '';
		if( version_compare( PHP_VERSION, '5.4', '<')) {
			$warnings = '<li>' . sprintf ( __( 'WP Issues CRM uses PHPMailer which requires PHP 5.4, but you are using %s. ', 'wp-issues-crm' ), PHP_VERSION ) . '</li>';
		}
		if( !extension_loaded('openssl') && !defined('OPENSSL_ALGO_SHA1') ){
			$warnings .= '<li>' . __( 'You need the openssl extension of PHP to run a secure SMTP connection.', 'wp-issues-crm' ) . '</li>';
		}     
		if( false == ini_get('allow_url_fopen') ) {
			$warnings .= '<li>' . __( 'You must enable allow_url_fopen in your php.ini.', 'wp-issues-crm' ) . '</li>';
		}
		if( !function_exists('stream_socket_client') && !function_exists('fsockopen') ){
			$warnings .= '<li>' . __( 'For an SMTP connection, your server needs to have either stream_socket_client or fsockopen.', 'wp-issues-crm') . '</li>';
		}
		if ( $warnings > '' ) {
			echo '<div class="wic-missing-function">'; 
			echo '<h4>' . __( 'Before using reply mail, you will probably need to attend to the following configuration issues:', 'wp-issues-crm' ) . '</h4>';
			echo '<ul>' . $warnings . '</ul></div>';
		}
		$test_button_args = array(
			'button_class'				=> 'wic-form-button wic-form-button test-button',
			'button_label'				=> __('Test Saved Settings', 'wp-issues-crm'),
			'type'						=> 'button',
			'name'						=> 'wic-email-send-test-button',
			'id'						=> 'wic-email-send-test-button',
			);	
		echo WIC_Form_Parent::create_wic_form_button ( $test_button_args );
	
	}	

	public function max_send_email_callback() {
		$value = isset( $this->plugin_options['max_send_email'] ) ? $this->plugin_options['max_send_email']: '';
		if ( defined ( 'WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND' ) ) {
			printf ( '<span id = "max_send_email">' . WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND . '</span>' . 
			 '  -- max number of emails permitted when sending to a list; can only be changed by network administrator');
		} else {
			printf( '<input type="text" id="max_send_email" name="wp_issues_crm_plugin_options_array[max_send_email]"
					value ="%s" />', $value );
			echo __( ' max number of emails permitted when sending to a list; if unset, defaults to 100 ', 'wp-issues-crm' );
		}
	}	

	// setting field call back
	public function smtp_user_callback() {
		$value = isset( $this->plugin_options['smtp_user'] ) ? $this->plugin_options['smtp_user']: '';
		printf( '<input type="text" id="smtp_user" name="wp_issues_crm_plugin_options_array[smtp_user]"
				value ="%s" />', $value );
		echo __( ' default "from" email and SMTP user (if SMTP, may or may not be an email address)', 'wp-issues-crm' );
		}

	// setting field call back
	public function from_email_callback() {
		$value = isset( $this->plugin_options['from_email'] ) ? $this->plugin_options['from_email']: '';
		printf( '<input type="text" id="from_email" name="wp_issues_crm_plugin_options_array[from_email]"
				value ="%s" />', $value );
		echo __( ' avoid rejection by setting server SPF and DKIM records', 'wp-issues-crm' );
		}	

	// setting field call back
	public function from_name_callback() {
		$value = isset( $this->plugin_options['from_name'] ) ? $this->plugin_options['from_name']: '';
		printf( '<input type="text" id="from_name" name="wp_issues_crm_plugin_options_array[from_name]"
				value ="%s" />', $value );
		}	

	// setting field call back
	public function smtp_reply_callback() {
		$value = isset( $this->plugin_options['smtp_reply'] ) ? $this->plugin_options['smtp_reply']: '';
		printf( '<input type="text" id="smtp_reply" name="wp_issues_crm_plugin_options_array[smtp_reply]"
				value ="%s" />', $value );
		echo __( ' replies to your emails will go to this address (defaults to User/from)', 'wp-issues-crm' );
		}
	// setting field call back
	public function reply_name_callback() {
		$value = isset( $this->plugin_options['reply_name'] ) ? $this->plugin_options['reply_name']: '';
		printf( '<input type="text" id="reply_name" name="wp_issues_crm_plugin_options_array[reply_name]"
				value ="%s" />', $value );
		}	

	// setting field call back	
	public function email_send_tool_callback() { 
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'smtp_send_tool_options' );		
		
		$value = isset ( $this->plugin_options['email_send_tool'] ) ? $this->plugin_options['email_send_tool'] : '';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    => $option_array,
			'input_class' 	   => '',
			'field_slug_css'	=> '',
			'hidden'			=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[email_send_tool]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}
	// setting field call back
	public function email_smtp_server_callback() {
		$value = isset( $this->plugin_options['email_smtp_server'] ) ? $this->plugin_options['email_smtp_server']: '';
		printf( '<input type="text" id="email_smtp_server" name="wp_issues_crm_plugin_options_array[email_smtp_server]"
				value ="%s" />', $value );
		echo __( ' e.g., smtp.gmail.com or mail.example.com' , 'wp-issues-crm' );
	}	
	// setting field call back -- dummy variable
	public function smtp_password_callback() {
		printf( '<input id="smtp_password" name="wp_issues_crm_plugin_options_array[smtp_password]"
				value ="%s" />', '' );
	}		
	// setting field call back
	public function smtp_secure_callback() { 
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'smtp_secure_options' );		
		
		$value = isset ( $this->plugin_options['smtp_secure'] ) ? $this->plugin_options['smtp_secure'] : '';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    => $option_array,
			'input_class' 	   => '',
			'field_slug_css'	=> '',
			'hidden'				=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[smtp_secure]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}	
	// setting field call back
	public function smtp_port_callback() {
		$value = isset( $this->plugin_options['smtp_port'] ) ? $this->plugin_options['smtp_port']: '';
		printf( '<input type="text" size="3" id="smtp_port" name="wp_issues_crm_plugin_options_array[smtp_port]"
				value ="%s" />', $value );
		echo __( ' usually 587 for TLS, 465 for SSL or 25 for unsecure', 'wp-issues-crm' );
	}	
	// setting field call back
	public function require_good_ssl_certificate_callback() { 
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'require_good_ssl_certificate_options' );		
		
		$value = isset ( $this->plugin_options['require_good_ssl_certificate'] ) ? $this->plugin_options['require_good_ssl_certificate'] : '';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    => $option_array,
			'input_class' 	   => '',
			'field_slug_css'	=> '',
			'hidden'				=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[require_good_ssl_certificate]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}

	// setting field call back
	public function peer_name_callback() {
		$value = isset( $this->plugin_options['peer_name'] ) ? $this->plugin_options['peer_name']: '';
		printf( '<input type="text" id="peer_name" name="wp_issues_crm_plugin_options_array[peer_name]"
				value ="%s" />', $value );
		echo __( ' may be needed for a self-signed certificate' , 'wp-issues-crm' );
	}

	// setting field call back	
	public function use_IPV4_callback() {
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'use_IPV4_options' );		
		
		$value = isset ( $this->plugin_options['use_IPV4'] ) ? $this->plugin_options['use_IPV4'] : '';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    => $option_array,
			'input_class' 	   => '',
			'field_slug_css'	=> '',
			'hidden'				=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[use_IPV4]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}	

	public function suppress_gssapi_callback() {
		printf( '<input type="checkbox" id="suppress_gssapi" name="wp_issues_crm_plugin_options_array[suppress_gssapi]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['suppress_gssapi'] ), false ) );
        echo __( ' probably no need to ever disable, but may suppress spurious errors when connecting with Exchange servers' );
	}

	// setting field call back
	public function smtp_debug_level_callback() { 
		global $wic_db_dictionary; 
		$option_array = $wic_db_dictionary->lookup_option_values( 'smtp_debug_level_options' );		
		
		$value = isset ( $this->plugin_options['smtp_debug_level'] ) ? $this->plugin_options['smtp_debug_level'] : '';
	
		$args = array (
			'field_label'	 	=> '',
			'option_array'    => $option_array,
			'input_class' 	   => '',
			'field_slug_css'	=> '',
			'hidden'				=> 0,
			'field_slug'		=> 'wp_issues_crm_plugin_options_array[smtp_debug_level]',
			'value'				=> $value ,		
		);		
		echo WIC_Control_Select::create_control( $args );
	}


	public function send_mail_sleep_time_callback() {
		$value = isset( $this->plugin_options['send_mail_sleep_time'] ) ? $this->plugin_options['send_mail_sleep_time']: '';
		printf( '<input type="text" id="send_mail_sleep_time" name="wp_issues_crm_plugin_options_array[send_mail_sleep_time]"
				value ="%s" />', $value );
		echo __( ' milliseconds  ( if your server is bouncing emails try 2000 and go up as needed ) ', 'wp-issues-crm' );
	}	

	/*
	*
	* Financial Activity Types Callback
	*
	*/
	// section legend call back
	public function enable_financial_activities_legend() {
		echo 
		'<p>' . __( 'WP Issues CRM can be used to track financial transactions. Simply enter below the Activity Type codes <em>separated by commas</em> for which amounts should be recorded.' , 'wp-issues-crm' ) . '</p>' . 
		'<p>' . __( 'For example, if you defined <a href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-options">Activity Type Options</a> <code>Check Contribution</code> coded as <code>CH</code> and <code>Online Contribution</code> coded as <code>OC</code>, you would enter them below like so:' , 'wp-issues-crm' ) . ' <code>CH,OC</code>' . '</p>' . 
		'<p>' . __( 'Activities of these types will then be stored and displayed with an amount field formatted with two decimal points.' , 'wp-issues-crm' ) .  '</p>' .
		'<p>' . __( 'Tip: The matching of activity type codes is case sensitive -- "CH" as an Activity Type code will <em>not</em> match "ch" as a financial activity setting.' , 'wp-issues-crm' ) .  '</p>' ;
	}
	
	// setting field call back
	public function financial_activity_types_callback() { 
		$value = isset ( $this->plugin_options['financial_activity_types'] ) ? $this->plugin_options['financial_activity_types'] : '';
		printf( '<input type="text" id="financial_activity_types" name="wp_issues_crm_plugin_options_array[financial_activity_types]"
				value ="%s" />', $value );
	}

	/*
	*
	* Freeze Older Activities Callback
	*
	*/
	// section legend call back
	public function freeze_older_activities_legend() {
		
		// get and parse date value if available
		$wic_option_array = get_option('wp_issues_crm_plugin_options_array'); 
		if ( isset ( $wic_option_array['freeze_older_activities'] ) ) {
			$date_value = $wic_option_array['freeze_older_activities'] > '' ? 
				WIC_Control_Date::sanitize_date( $wic_option_array['freeze_older_activities'] ) :
				'';
		} else {
			$date_value = '';		
		}
		// if date_value is unset, blank or unparseable, so report
		$show_date  = ( '' < $date_value ) ? $date_value : __( 'Blank -- not set or not parseable', 'wp-issues-crm' );
		
		echo 
		'<p>' . __( 'WP Issues CRM allows you to freeze older activities, leaving them viewable, but not updateable, online. You might
					especially wish to do this if you have closed a financial records period, but also just to limit the possibility
					of data entry errors.  You can always change the cutoff date, or eliminate it, if for some reason you need to go back to update older activities.' , 'wp-issues-crm' ) . '</p>' . 
		'<p>' . __( 'Activities dated earlier than the cutoff date below cannot be updated and dates
					 earlier than the cutoff date cannot be set when adding new activities.  Note that you can still dedup a constituent and reassign activities
					 even if they have activities dated before the cutoff.  Also, the storage management purge function does not check freeze date. 
					 ', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __( 'You can enter the cutoff date in almost any English language format, including variable formats like <code>3 days ago</code>.
					 This example would freeze activities more than 3 days old on a rolling basis. Enter a phrase and save it to test it.' , 'wp-issues-crm' ) .  '</p>' .
		'<p><strong><em>' . __( 'As of today, the last saved cutoff value evaluates to: ' , 'wp-issues-crm' ) . '</em></strong><code>' . $show_date . '.</code></p>' ;
	}
	// setting field call back
	public function freeze_older_activities_callback() { 
		$value = isset ( $this->plugin_options['freeze_older_activities'] ) ? $this->plugin_options['freeze_older_activities'] : '';
		printf( '<input type="text" id="freeze_older_activities" name="wp_issues_crm_plugin_options_array[freeze_older_activities]"
				value ="%s" />', $value );
	}
	/*
	*
	* Comment Synch Callbacks
	*
	*/
	// section legend call back
	public function comment_synch_legend() {
	
		echo '<div id="comment_synch"><p>' . __( 'WP Issues CRM can present comments on your front-facing web posts as constituent activities.  It does so by creating parallel activity records at the time of comment addition.  Because both comment records and WP Issues CRM constituent records can and do change, it also runs a daily resynchronization.', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __( 'WP Issues CRM links comments to constituents through their email address, so to use this feature:', 'wp-issues-crm' ) . '</p>' .
		'<ol><li>' . __( 'You must configure Wordpress ( through Wordpress Settings &raquo; Discussion Settings ) to require commenters to provide email addresses (by logging in or as part of the comment submission).', 'wp-issues-crm' ) . '</li>' .
			'<li>' . __( 'You must maintain email addresses for your constituents in WP Issues CRM.', 'wp-issues-crm' ) .'</li>' .
		'</ol> </p>' .
		 '<p>' . __(' If you check Report Missing Emails below, the daily scan will generate an email to site administrators listing comments with email addresses that do not exist in the WP Issues CRM database. ', 'wp-issues-crm' ) .'</p>' .
		 '<p>' . __(' If you have turned this feature on, but then turn it off, on the next daily scan, WP Issues CRM will clear all parallel records.  Use the button below to synch or clear activity records for comments immediately -- <em>according to your last saved settings</em>. ', 'wp-issues-crm' ) .'</p>' .
				
		'</div>';

		$test_button_args = array(
			'button_class'				=> 'wic-form-button wic-form-button',
			'button_label'				=> __('Synch Now', 'wp-issues-crm'),
			'title'						=> 'Run the nightly process now to synch or clear comment activity records per setting.',
			'type'						=> 'button',
			'name'						=> 'synch-comments-button',
			'id'						=> 'synch-comments-button',
			);	
		echo WIC_Form_Parent::create_wic_form_button ( $test_button_args );
	
	}

	// setting field call back	
	public function synch_comments_on_callback() {
		printf( '<input type="checkbox" id="synch_comments_on" name="wp_issues_crm_plugin_options_array[synch_comments_on]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['synch_comments_on'] ), false ) );
	}
	// setting field call back	
	public function report_missing_emails_callback() {
		printf( '<input type="checkbox" id="report_missing_emails" name="wp_issues_crm_plugin_options_array[report_missing_emails]" value="%s" %s />',
            1, checked( '1', isset ( $this->plugin_options['report_missing_emails'] ), false ) );
	}

	/*
	* custom css
	*
	*/
	public function wic_override_css_legend() {
	
		echo '<div id="custom_css"><p>' . __( 'You can put CSS codes in the box further below to change the layout of any WP Issues CRM form or report.  The CSS you put here will override the installed CSS and will carry over across upgrades.', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __( 'The most common use of custom CSS is to alter the standard constituent and activity lists that are retrieved by Advanced Search.  On the constituent list, the fields have order numbers from left to right ( first-name 10, middle-name 20, etc. ).', 'wp-issues-crm' ) . '</p>' .
		'<p>' . __(  'Here is an example of the CSS you would use to cause your first custom field to show in constituent lists immediately after the constituent name -- note that you need separate statements for the header and the line items.
					  All custom fields are actually present on lists, but are set not to display by the base CSS.  The CSS you add here causes them to show.', 'wp-issues-crm' ) . '</p>' .
		'<ul>' . 
			'<li>' .  '<code>.wic-post-list-field.pl-constituent-custom_field_001.constituent-custom-field { display: block; order: 35; } </code></li>' .
			'<li>' .  '<code>.wic-post-list-header.pl-constituent-custom_field_001.constituent-custom-field { display: block; order: 35; } </code></li>' .
		'</ul>' .
		'<p>' . __(  'If your field tends to have many characters, you might want make the field wider (20% instead of the default 10%) and eliminate one of the other fields on the report to make room for it.', 'wp-issues-crm' ) . '</p>' .
		'<ul>' . 
			'<li>' .  '<code>.wic-post-list-field.pl-constituent-custom_field_001.constituent-custom-field { display: block; order: 35; width: 20%; } </code></li>' .
			'<li>' .  '<code>.wic-post-list-header.pl-constituent-custom_field_001.constituent-custom-field { display: block; order: 35; width: 20%;} </code></li>' .		
			'<li>' .  '<code>.wic-post-list-field.pl-constituent-phone { display: none;} </code></li>' .		
			'<li>' .  '<code>.wic-post-list-header.pl-constituent-phone { display: none;} </code></li>' .		
		'</ul>' .
		'<p>' . __(  'To show the same field on activity lists, you would use the following CSS.  Note that on activity lists all constituent information is packed in a single column, so there is no header css needed.', 'wp-issues-crm' ) . '</p>' .
		'<ul>' . 
			'<li>' .  '<code>.activity-custom-field.pl-activity-custom_field_001 { display: block;} </code></li>' .
		'</ul>' .
		'<p>' . __(  'If you wanted to make the constituent column wider (from 20% to 32%) on the activity list, narrow the type column and drop the pro_con column to make room, you would use the following statements.', 'wp-issues-crm' ) . '</p>' .
		'<ul>' . 
			'<li>' .  '<code>.wic-post-list-field.pl-activity-constituent_id  { width: 32%} </code></li>' .
			'<li>' .  '<code>.wic-post-list-header.pl-activity-constituent_id  { width: 32%} </code></li>' .
			'<li>' .  '<code>.wic-post-list-field.pl-activity-activity_type  { width: 8%;} </code></li>' .
			'<li>' .  '<code>.wic-post-list-header.pl-activity-activity_type  { width: 8%;} </code></li>' .
			'<li>' .  '<code>.wic-post-list-field.pl-activity-pro_con  { display: none;} </code></li>' .
			'<li>' .  '<code>.wic-post-list-header.pl-activity-pro_con  { display: none;} </code></li>' .
		'</ul>' .
		'<p>' . __(  'You can cut and paste these examples and use them as templates below or you can use any valid CSS statement to create the look you want.', 'wp-issues-crm' ) . '</p>' .
			'<ul>' . 
				'<li>' .   __(  'Note that custom fields are automatically ordered into the constituent save/update form.  You only need css to show them on the list.', 'wp-issues-crm' ) . '</li>' .
				'<li>' .  __(  'The visually compressed lists of activities that appear on the constituent and issue forms are also customizable through css, but they do not include custom fields.', 'wp-issues-crm' ) . '</li>' .
			'</ul>' .
		'</div>';
	
	}			

	public function wic_override_css_callback() {
		$value = isset ( $this->plugin_options['wic_override_css'] ) ? $this->plugin_options['wic_override_css'] : '';
		echo '<textarea id="wic_override_css" name="wp_issues_crm_plugin_options_array[wic_override_css]" type="text" placeholder = "Enter css codes here to format reports.">' . ( $value ) . '</textarea>';
	}
	
	/*
	*
	* Uninstall Legend
	*
	*/
	// section legend call back
	public function uninstall_legend() {
		echo '<div id="uninstall">' .
			'<p>' . __( 'If you want to reduce storage usage or as a first step in deinstallation, go to the <a href="' . site_url() . '/admin.php?page=wp-issues-crm-storage">Manage Storage menu</a>.  There you 
			can discard entries in the search log and drop temporary upload tables.', 'wp-issues-crm' ) . '</p>' .
			'<p>' . __( 'If you simply wish to refresh original options settings, you can safely deactivate and delete WP Issues CRM and then reinstall it on
			the <a href="' . site_url() . '/wp-admin/plugins.php">plugins menu</a>.  WP Issues CRM will come back up	with all of your data.', 'wp-issues-crm' ) . '</p>' 
			. '<p>' . __( 'WP Issues CRM does a partial uninstall of its own data if you "delete" it through the <a href="' . site_url() . '/wp-admin/plugins.php">plugins menu</a>.  It removes its entries
			in the Wordpress options table -- which include the plugin options and database version. 
			It also removes entries in the Wordpress user meta table for individual preference settings for the plugin.
			Finally, it removes its control and audit trail tables, with the exception of the dictionary (which may include user configured fields).', 'wp-issues-crm') . '</p><p>' .  
			__( 'However, for safety, it does not automatically remove the core user built tables -- the risk of data loss in a busy office is just too great. 
			To completely deinstall WP Issues CRM, access the Wordpress database through phpmyadmin or through the mysql console and delete the following tables (usually prefixed with "wp_wic_" ) :', 'wp-issues-crm' ) . '</p>' . 
		'<ol>' . 
			'<li>activity</li>' .
			'<li>address</li>' .
			'<li>constituent</li>' .
			'<li>data_dictionary</li>' .
			'<li>email</li>' .
			'<li>phone</li>' .
			'<li>subject_issue_map</li>' . 
		'</ol>' .		
		'<p>' . __( 'Finally, run this command to delete post_meta data created by WP Issues CRM (this will not affect issue posts themselves):', 'wp-issues-crm' ) . '</p>' .
		'<pre>DELETE FROM wp_postmeta WHERE meta_key LIKE \'wic_data_%\'</pre></div>' .
		'<p>' . __( 'Take note: These steps should all be taken AFTER the plugin is deactivated -- otherwise it will automatically restore missing tables. ', 'wp-issues-crm' ) . '</p>';
				
	}
	// call back for the option array (used by options.php in handling the form on return)
	public function sanitize ( $input ) {
		$new_input = array();

      // security settings
      if( isset( $input['access_level_required'] ) ) {
            $new_input['access_level_required'] = sanitize_text_field( $input['access_level_required'] );
      }
	  if( isset( $input['access_level_required_downloads'] ) ) {
            $new_input['access_level_required_downloads'] = sanitize_text_field( $input['access_level_required_downloads'] );
      }
	  if( isset( $input['access_level_required_email'] ) ) {
            $new_input['access_level_required_email'] = sanitize_text_field( $input['access_level_required_email'] );
      } 
	  if( isset( $input['access_level_required_send_email'] ) ) {
            $new_input['access_level_required_send_email'] = sanitize_text_field( $input['access_level_required_send_email'] );
      }           
	  if( isset( $input['access_level_required_list_send'] ) ) {
            $new_input['access_level_required_list_send'] = sanitize_text_field( $input['access_level_required_list_send'] );
      }  
	  if( isset( $input['access_level_required_view_edit_unassigned'] ) ) {
            $new_input['access_level_required_view_edit_unassigned'] = sanitize_text_field( $input['access_level_required_view_edit_unassigned'] );
      }        
           
      // privacy settings 
	  if( isset( $input['all_posts_private'] ) ) {
            $new_input['all_posts_private'] = absint( $input['all_posts_private'] );
      } 
  	  if( isset( $input['hide_private_posts'] ) ) {
            $new_input['hide_private_posts'] = absint( $input['hide_private_posts'] );
      } 
      // postal interface
		if( isset( $input['use_postal_address_interface'] ) ) {
            $new_input['use_postal_address_interface'] = absint( $input['use_postal_address_interface'] );
      } 
		if( isset( $input['user_name_for_postal_address_interface'] ) ) {
            $new_input['user_name_for_postal_address_interface'] = sanitize_text_field( $input['user_name_for_postal_address_interface'] );
      } 
	  if( isset( $input['do_zip_code_format_check'] ) ) {
            $new_input['do_zip_code_format_check'] = absint( $input['do_zip_code_format_check'] );
      } 
	  if( isset( $input['google_maps_api_key'] ) ) {
            $new_input['google_maps_api_key'] = sanitize_text_field( $input['google_maps_api_key'] );
      }
	  if( isset( $input['geocodio_api_key'] ) ) {
            $new_input['geocodio_api_key'] = sanitize_text_field( $input['geocodio_api_key'] );
      }      
      // imap interface
      if( isset( $input['email_imap_server'] ) ) {
            $new_input['email_imap_server'] = sanitize_text_field( $input['email_imap_server'] );
      } 
	  if( isset( $input['user_name_for_email_imap_interface'] ) ) {
            $new_input['user_name_for_email_imap_interface'] = sanitize_text_field( $input['user_name_for_email_imap_interface'] );
      } 
	  if( isset( $input['password_for_email_imap_interface'] ) ) {
            $new_input['password_for_email_imap_interface'] = sanitize_text_field( $input['password_for_email_imap_interface'] );
      }       
   	  if( isset( $input['use_ssl_for_email_imap_interface'] ) ) {
            $new_input['use_ssl_for_email_imap_interface'] = absint( $input['use_ssl_for_email_imap_interface'] );
      } 
      if( isset( $input['port_for_email_imap_interface'] ) ) {
            $new_input['port_for_email_imap_interface'] = absint( $input['port_for_email_imap_interface'] );
      } 
      if( isset( $input['imap_inbox'] ) ) {
            $new_input['imap_inbox'] = sanitize_text_field( $input['imap_inbox'] );
      }
      if( isset( $input['imap_max_retries'] ) ) {
            $new_input['imap_max_retries'] = sanitize_text_field( $input['imap_max_retries'] );
      }  

 	  // reply interface
      if( isset( $input['email_send_tool'] ) ) {
            $new_input['email_send_tool'] = sanitize_text_field( $input['email_send_tool'] );
      } 
      if( isset( $input['email_smtp_server'] ) ) {
            $new_input['email_smtp_server'] = sanitize_text_field( $input['email_smtp_server'] );
      } 
      if( isset( $input['smtp_user'] ) ) {
            $new_input['smtp_user'] = sanitize_text_field( $input['smtp_user'] );
      } 
      if( isset( $input['from_name'] ) ) {
            $new_input['from_name'] = sanitize_text_field( $input['from_name'] );
      } 
      if( isset( $input['from_email'] ) ) {
            $new_input['from_email'] = sanitize_text_field( $input['from_email'] );
      } 
      if( isset( $input['smtp_reply'] ) ) {
            $new_input['smtp_reply'] = sanitize_text_field( $input['smtp_reply'] );
      } 
      if( isset( $input['reply_name'] ) ) {
            $new_input['reply_name'] = sanitize_text_field( $input['reply_name'] );
      } 
      if( isset( $input['smtp_password'] ) ) {
            $new_input['smtp_password'] = sanitize_text_field( $input['smtp_password'] );
      } 
      if( isset( $input['smtp_secure'] ) ) {
            $new_input['smtp_secure'] = sanitize_text_field( $input['smtp_secure'] );
      } 
      if( isset( $input['smtp_port'] ) ) {
            $new_input['smtp_port'] = absint( $input['smtp_port'] );
      } 
      if( isset( $input['require_good_ssl_certificate'] ) ) {
            $new_input['require_good_ssl_certificate'] = sanitize_text_field( $input['require_good_ssl_certificate'] );
      } 
      if( isset( $input['peer_name'] ) ) {
            $new_input['peer_name'] = sanitize_text_field( $input['peer_name'] );
      } 
      if( isset( $input['use_IPV4'] ) ) {
            $new_input['use_IPV4'] = sanitize_text_field( $input['use_IPV4'] );
      } 
     if( isset( $input['suppress_gssapi'] ) ) {
            $new_input['suppress_gssapi'] = absint( $input['suppress_gssapi'] );
      } 
      if( isset( $input['smtp_debug_level'] ) ) {
            $new_input['smtp_debug_level'] = sanitize_text_field( $input['smtp_debug_level'] );
      } 
		
      if( isset( $input['send_mail_sleep_time'] ) ) {
            $new_input['send_mail_sleep_time'] = absint( $input['send_mail_sleep_time'] );
      } 
     
	 if( isset( $input['max_send_email'] ) ) {
            $new_input['max_send_email'] = absint( $input['max_send_email'] );
     }
                     
   	// financial activities
    if( isset( $input['financial_activity_types'] ) ) {
      	   $type_array = explode ( ',', $input['financial_activity_types'] );
      	   $clean_type_array = array();
      	   foreach ( $type_array as $type ) {
      	   	$clean_type = sanitize_text_field( $type );
					if ( $clean_type > '' ) { 
						$clean_type_array[] = $clean_type;
					}     	   
      	   } 
      	   $new_input['financial_activity_types'] = implode (',', $clean_type_array );
      } 
      // freeze activities
      if( isset( $input['freeze_older_activities'] ) ) {
      		// accept only values that can be processed to a date by php, but store the value, not the date
      		$date_value = WIC_Control_Date::sanitize_date( $input['freeze_older_activities'] );
            $new_input['freeze_older_activities'] = $date_value > '' ? sanitize_text_field ( $input['freeze_older_activities'] ) : '';
      }  

	 if( isset( $input['synch_comments_on'] ) ) {
            $new_input['synch_comments_on'] = absint( $input['synch_comments_on'] );
     }  
     
     if( isset( $input['report_missing_emails'] ) ) {
            $new_input['report_missing_emails'] = absint( $input['report_missing_emails'] );
     }     

     if( isset( $input['wic_override_css'] ) ) {
            $new_input['wic_override_css'] = wp_kses_post( $input['wic_override_css'] );
     }

     return ( $new_input );      
	}

	// menu page with form
	public static function wp_issues_crm_settings () {
		?>
      <div class="wrap">
 	 	<?php settings_errors(); ?>
         <form id="wp-issues-crm-settings" method="post" action="options.php">
         <?php
				submit_button( __( 'Save All Settings', 'wp-issues-crm' ) );         	
         	// set up nonce-checking for the single field we have put in this option group
				settings_fields ( 'wp_issues_crm_plugin_options'); 
			/*
			* instead of calling do_settings_sections here, use the concepts from underlying function
			* do_settings_sections( 'wp_issues_crm_settings_page' );
			* https://developer.wordpress.org/reference/functions/do_settings_sections/
		    */
		    global $wp_settings_sections, $wp_settings_fields;
			$page = 'wp_issues_crm_settings_page';  
			
			// first output tab headers
			echo '<div id="wic-settings-tabs"><ul>';
			foreach ( (array) $wp_settings_sections[$page] as $section ) {
				echo '<li>' .
					'<a href="#' . $section['id'] . '">' . $section['title'] . '</a>' .
				'</li>';
    		} 
    		echo '</ul>';
			
			// now output settings sections
			foreach ( (array) $wp_settings_sections[$page] as $section ) {
				echo '<div class = "wic-settings" id="' . $section['id'] . '">';
				echo '<h2>' . $section['title'] . '</h2>';
				call_user_func( $section['callback'], $section );
				echo '<table class="form-table">';
				do_settings_fields( $page, $section['id'] );
				echo '</table></div>';
			}
			echo '</div>'; // close tabs div;
			submit_button( __( 'Save All Settings', 'wp-issues-crm' ) );         	

		?>
		</form>
	</div>
	<?php

}




















}