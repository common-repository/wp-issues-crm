<?php
/**
*
* class-wic-admin-navigation.php
*
*/


class WIC_Admin_Navigation {

	/* 
	*
	*	All user/form requests to the plugin are routed through this navigation class
	*		GET Requests 
	*			Wordpress menu and any page request get screened by wordpress at security levels defined in menu_setup
	*				Page gets with additional parameters further controlled as to what parameters OK through do_page referencing $nav_array
	*			If page request includes attachment ID, it will get served off admin_init to emit_stored_file before do_page -- check_security checks capability and nonce
	*
	*		AJAX Requests to Entities  run through check_security for both capability and nonce checking		
	*			AJAX Form Posts -- all forms are submitted via AJAX to lower Wordpress Admin overhead
	*			AJAX Requests -- specific actions below the entity level
	*			Only Entity Classes/methods are invoked by the AJAX endpoints -- check_security covers all entities, 
	*
	*		Special requests -- limited functions -- check security and nonce	
	*			Download Button submits(2)
	*			Gmail API 
	*			Upload requests (3)
	*
	*	THIS IS THE ONLY ROUTE TO EXECUTE PLUG-IN CODE EXCEPT FOR:
	* 		(0)	DB setup (invoked in wp-issues-crm.php ) 
	*		(1)	Cron tasks for email and geocoding, protected by cron keys in config
	*		(2) Interface transactions (which accept and sanitize external form records for constituents and activities)
	*		(3) The metabox for post set up in WIC_Admin_Setup and other minor functions invoked wp-issues-crm.php
	*		(4) ANY OTHER WORDPRESS PLUGIN THAT CHOSE TO EXECUTE PLUG-IN CODE!
	*
	*	Since 4.5, WIC_Admin_Access contains check_security which now also enforces access limits to assigned records -- all accesses hit this (ex the above exceptions)
	*			Client side access controls are all spoofable; so test all ajax calls here on the server side, although various buttons are hidden or disabled to avoid annoyance of failed access
	*				Ultimately dependent on Wordpress login security
	*			Default in all tests is to disallow without administrator access.
	*		
	*/

	// set up routing responses for each access type
	public function __construct() {

		// needed for post metabox, so load always on admin (two quick db calls)	 
		add_action ( '_admin_menu', 'WIC_Admin_Navigation::dictionary_setup' ); 	

		// set up menu -- display menu items and define route for get page requests to plugin 
		add_action( 'admin_menu', array ( $this, 'menu_setup' ) ); // precedes admin_init

		/*
		*
		* wp_ajax_ entry point; run check_security on each
		*
		*/
		// AJAX Form posts
		add_action( 'wp_ajax_wp_issues_crm_form', array ( $this, 'route_ajax_form' ) );
		// AJAX Requests
		add_action( 'wp_ajax_wp_issues_crm', array ( $this, 'route_ajax' ) );
		// PL Upload Requests
		add_action( 'wp_ajax_wp_issues_crm_upload', array ( $this, 'route_ajax_upload' ) );
		// PL Upload Document Uploader 
		add_action( 'wp_ajax_wp_issues_crm_document_upload', array ( $this, 'route_ajax_document_upload' ));
		// PL Upload Attachment Uploader 
		add_action( 'wp_ajax_wp_issues_crm_attachment_upload', array ( $this, 'route_ajax_attachment_upload' ));

		/*
		*
		* downloads -- 
		*   fire at admin_init before fire any WP headers
		* 	emit appropriate download file headers and then die
		*	run check_security on each
		*/
		// download button posts
		add_action( 'admin_init', array( $this, 'do_download' ) );			
		// fire to serve inline images
		add_action( 'admin_init', array( $this, 'emit_stored_file' ) );
	
		// fire to intercept google redirects -- need to be before admin menu is configured and security is applied to page routing.
		add_action( 'admin_menu', array( $this, 'route_google_oauth' ) ); 

	}	

	// dictionary necessary for almost all classes WIC_Admin_Navigation::dictionary_setup ()
	public static function dictionary_setup () {
		// set up dictionary
 		global $wic_db_dictionary;
		if ( empty ( $wic_db_dictionary ) ) {
	 		$wic_db_dictionary = new WIC_DB_Dictionary;
	 	} 	
	}

	/*
	*	All $_GET processing is mediated through Wordpress -- the menu and submenu page structure (partial exceptions gets for attachments)
	*	Two main functions -- menu_setup and do_page control this flow. 
	*		$nav_array is used in both and limits valid GET requests (otherwise, nav logic could attempt route to arbitrary class functions)	
 	* 			keys are pages as in add_menu_page or add_sub_menu_page; values are parameters governing the page display
 	*	
	*/
	private $nav_array = array (
		'wp-issues-crm-main' =>	array (
			'name'		=>  'WP Issues CRM', 
			'default'	=>	array ( 'dashboard', 'dashboard' ),// default entity/action to invoke if $_GET does not contain permitted entity and actions
			'permitted'	=>	array ( 							// permissible entity/action pairs for the page on a GET request
								array (	'constituent',		'id_search'		),
								array (	'constituent',		'new_blank_form'),
								array (	'email_inbox',		'new_blank_form'),
								array (	'issue',			'id_search'		),
								array (	'issue',			'new_blank_form'),
								array (	'search_log',		'id_search'		),
								array (	'search_log',		'id_search_to_form'	),
								array (	'advanced_search',	'new_blank_form' ),
							),
			'mobile'	=>	true,								// works on small screens
			'security'	=>	false								// no special security level
			),
		'wp-issues-crm-options'	=> array (
			'name'		=>  'Options', 
			'default'	=>	array ( 'option_group', 'list_option_groups' ),
			'permitted'	=>	array ( 
								array ( 'option_group', 'new_blank_form' ),
								array ( 'option_group', 'id_search' ),
							),
			'mobile'	=>	false,
			'security'	=>	'edit_theme_options'
			),
		'wp-issues-crm-fields'	=> array (
			'name'		=>  'Fields', 
			'default'	=>	array ( 'data_dictionary', 'list_custom_fields' ),
			'permitted'	=>	array ( 
								array ( 'data_dictionary', 'new_blank_form' ),
								array ( 'data_dictionary', 'id_search' ),
							),
			'mobile'	=>	false,
			'security'	=>	'edit_theme_options'
			),
		'wp-issues-crm-externals'	=> array (
			'name'		=>  'Interfaces', 
			'default'	=>	array ( 'external', 'list_external_interfaces' ),
			'permitted'	=>	array ( 
								array ( 'external', 'new_blank_form' ),
								array ( 'external', 'id_search' ),
							),
			'mobile'	=>	false,
			'security'	=>	'edit_theme_options'
			),
		'wp-issues-crm-owners' => array (
			'name'		=>  'Data Owners', 
			'default'	=>	array ( 'owner', 'list_owners' ),
			'permitted'	=>	array ( 
								array ( 'owner', 'id_search' ),
							),			
			'mobile'	=>	false,
			'security'	=>	'create_sites'
			),
		'wp-issues-crm-settings'	=> array (
			'name'		=>  'Configure', 
			'default'	=>	array ( false, array( 'WIC_Admin_Settings', 'wp_issues_crm_settings' ) ),
			'permitted'	=>	array (),
			'mobile'	=>	false,
			'security'	=>	'edit_theme_options'
			),
		'wp-issues-crm-synch' => array (
			'name'		=>  'Synch Data', 
			'default'	=>	array ( 'synch', 'show_synch_status' ),
			'permitted'	=>	array (), 
			'mobile'	=>	false,
			'security'	=>	'edit_theme_options'
			),		
		'wp-issues-crm-storage'	=> array (
			'name'		=>  'Manage Storage', 
			'default'	=>	array ( 'manage_storage', 'form_manage_storage' ),
			'permitted'	=>	array (),
			'mobile'	=>	false,
			'security'	=>	'edit_theme_options'
			),
		);
	
	
	

	private $unassigned_message = 
		'<div id="wic-unassigned-message">
			<h3>You requested a record to which you have not been assigned or a function to which your role does not give you access.</h3>
			<p>If you need access, consult your supervisor and request that the record be assigned to you or that your role be upgraded to a level that has access to unassigned constituents and issues and/or to more functions.</p>
			<p>Since Version 4.5 (August 2019), <strong>WP Issues CRM &raquo; Configure &raquo; Security</strong> determines which user roles have access to unassigned constituents and issues.</p>
		</div>';
	
	// set up menu and submenus with required capability levels defined from $nav_array -- WP is checking security on the page access
	public function menu_setup () {  
			
		$menu_position = '4.19544595294'; // pick arbitrary decimal number to avoid possibility of conflicts
		$main_security_setting = WIC_Admin_Access::check_required_capability( '' );
		// need to run add setting  before add page -- too late to register if try not to do the work until on the page 		
		$wic_admin_settings = new WIC_Admin_Settings; 

		// add menu page
		add_menu_page( 'WP Issues CRM', 'WP Issues CRM', $main_security_setting, 'wp-issues-crm-main', array ( $this, 'do_page' ), 'dashicons-smiley', $menu_position ); 		
		// add submenu pages
		foreach ( $this->nav_array as $page => $parms ) {
			// regardless of  current security level, do not show synch page  if is central (synch to) blog or if synch not configured 
			if( 'wp-issues-crm-synch' == $page && ( !is_multisite() || BLOG_ID_CURRENT_SITE == get_current_blog_id() || !get_option ( 'wic_owner_type') ) ) {
				continue;
			}
			add_submenu_page( 
				'wp-issues-crm-main', 		// submenu of $parent_slug
				$parms['name'], 			// The text to be displayed in the title tags of the page when the menu is selected.
				$parms['name'], 			// The text to be used for the menu.
				$parms['security'] ? $parms['security'] : $main_security_setting, // The capability required for this menu to be displayed to the user.
				$page,						// The slug name to refer to this menu by (should be unique for this menu).
				array ( $this, 'do_page' )  // ) The function to be called to output the content for this page.
			);
		}
	}

	// format a page from menu ( or direct $_GET in which case, invoke entity/action based on $_GET; if not permitted entity/action pair route to default )
	// wordpress is doing the security checking on a GET
	public function do_page (){ 
	
		// get page parms from nav_array (page will always be in array since it defines all the pages that access the plugin)	
		$page 	= $_GET['page'];
		$parms  = $this->nav_array[$page];
		
		// determine framing for responsive and non-responsive forms
		if ( ! $parms['mobile'] ) {
			$small_screen_plug 	= '<h3 id = "wp-issues-crm-small-screen-size-plug">' . $parms['name'] . ' not available on screens below 960px in width.' . '</h3>';
			$full_screen		= 'full-screen';
		} else {
			$small_screen_plug 	= '';
			$full_screen		= '';
		}

		// emit page
		echo $small_screen_plug;
		echo '<div class="wrap ' . $full_screen .'" id="wp-issues-crm" ><div id="wp-issues-crm-google-map-slot"></div>';
			echo '<h1 id="wic-main-header">' . $parms['name'] . '</h1>';
			echo WIC_DB_Setup::check_database_upgraded_ok();
			self::is_explorer();
						
			// processing allowed get strings or defaults for pages
			if ( 0 < count ( $_GET ) ) { 
				// if fully defined and OK string for page
				if ( isset ( $_GET['entity'] ) && isset ( $_GET['action'] ) && isset ( $_GET['id_requested'] )  ) {
					// filter allowed get actions. here is the page security check from $nav_array
					if ( in_array ( array ( $_GET['entity'] , $_GET['action'] ), $parms['permitted'] ) ) { 
						$class_short = $_GET['entity'];
						$action = $_GET['action'];
						$id = $_GET['id_requested'];
						$args   = array( 'id_requested' => $id ); // removed get path to user
					}
				} 
				// if not fully defined or not OK
				if ( !isset ( $class_short ) ) {
					$class_short = $parms['default'][0];
					$action = $parms['default'][1];
					$args	= array(); 
					$id		= '';
				}
				
				// cosmetics
				if ( 'wp-issues-crm-main' == $page ) { 
					$this->show_top_menu ( $class_short, $action );
				} 
				$showing_email = '';
				if ( isset( $_GET['entity'] ) ) {
					if ( 'email_inbox' == $_GET['entity'] ) {
						$showing_email = 'showing-email';
					}
				}
				
				// main action
				echo '<div id="wic-main-form-html-wrapper" class="' . $showing_email . '">';	
					if ( $class_short ) {  
						if ( WIC_Admin_Access::check_security ( $class_short, $action, $id, '', false ) ) { // false means no nonce to check
							$class 	= 'WIC_Entity_' . $class_short; 
							$class_action = new $class ( $action, $args );
						} else {
							echo $this->unassigned_message;
						}
					} else {
						$action[0]::{$action[1]}(); // static function in settings case -- php 7.1{}
					}
				echo '</div>';
			}

		echo '</div>';
	}

	private function is_explorer() {

		$ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
		if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0') !== false && strpos($ua, 'rv:11.0') !== false) ) {
			echo '<p><em>It appears you are using Internet Explorer.  Internet Explorer does not support all modern javascript language constructs.
				WP Issues CRM performs best using Chrome or Firefox.</em></p>';
			return;
		}			

	}

	// button navigation for wp-issues-crm-main page
	private function show_top_menu ( $class_requested, $action_requested ) {  

		echo '<form id = "wic-top-level-form" method="POST" autocomplete = "off">';
			// go to home
			$this->a2b ( 	array ( 'dashboard', 	'dashboard',	'<span class="dashicons dashicons-admin-home"></span></span>', __( 'Dashboard.', 'wp-issues-crm' ), '', false ) );
			// search box
			$search_box = WIC_Control_Factory::make_a_control ( 'autocomplete' );
			$search_box->initialize_default_values(  'list', 'wic-main-search-box', '' );
			echo ( $search_box->form_control() );

			// go to email processing
			$this->a2b ( array ( 'email_inbox', 	'new_blank_form',	'<span class="dashicons dashicons-email-alt"></span>', 		__( 'Process Email', 'wp-issues-crm' ), 	'wic_email_access_button', false ) );		
			// go to map link
			$this->a2b ( array ( 'main_map',		'main_map',				'<span class="dashicons dashicons-location-alt"></span>', 	__( 'Main Map', 'wp-issues-crm' ),	 			'show_main_map_button', false ) );			
			// go to constituent add
			$this->a2b ( array ( 'constituent', 	'new_blank_form',	'<span class="dashicons dashicons-smiley"></span>' ,		__( 'New Constituent', 'wp-issues-crm' ), '', false ) ); // new
			// go to issue add
			$this->a2b ( array ( 'issue', 			'new_blank_form',	'<span class="dashicons dashicons-format-aside"></span>', 	__( 'New Issue', 'wp-issues-crm' ), 		'', false ) );
			// complex search
			$this->a2b ( array ( 'advanced_search', 'new_blank_form',	'<span class="dashicons dashicons-search"></span>', 		__( 'Advanced Search', 'wp-issues-crm' ), 	'', false ) );
			// go to uploads ( but wrap in div)
			echo '<div class="wic-upload-button-wrapper">';
			$this->a2b ( array ( 'upload', 			'new_blank_form',	'<span class="dashicons dashicons-upload"></span>', 		__( 'Upload Files', 'wp-issues-crm' ), 		'wic_upload_select_button', false ) );		
			echo '</div>';
			// go to documentation link
			$this->a2b ( array ( 'help', 			'link',				'<span class="dashicons dashicons-book"></span>', 	__( 'Documentation/Contact', 'wp-issues-crm' ),	 			'wic_manual_button', false ) );		
			// nonce field
			wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, true ); 
			// hidden button populated as needed by jQuery to post as a top form using the routine wpIssuesCRM.mainFormButtonPost 
			echo WIC_Form_Parent::create_wic_form_button( array ( 'id' => 'wic_hidden_top_level_form_button' ) );
			// hidden input control used to pass values for exports -- note that all submittable buttons in the top level form have name wic_form_button and so go through wpIssuesCRM.mainFormButtonPost
			// the download method is via a submit (so don't have to parse attachment headers out of an AJAX response ) -- do_download runs on every post it filters out wic_form_button
			$export_parameters = WIC_Control_Factory::make_a_control ( 'text' );
			$export_parameters->initialize_default_values(  'list', 'wic-export-parameters', '' );
			echo ( $export_parameters->form_control() );
		echo '</form>';		
	}

	// a2b (array to button) 
	private function a2b ( $top_menu_button ) {
	
		if ( !current_user_can (WIC_Admin_Access::check_required_capability ('view_edit_unassigned') ) ) {
			if ( in_array ( $top_menu_button[0], array ( 'issue', 'advanced_search', 'upload') ) ) {
				return false;
			}
		}
	
	
		$button_args = array (
				'entity_requested'		=>  $top_menu_button[0],
				'action_requested'		=>  $top_menu_button[1],
				'button_class'			=>  'wic-form-button wic-top-menu-button ', 	
				'button_label'			=>	$top_menu_button[2],
				'title'					=>	$top_menu_button[3],
				'id'					=>	$top_menu_button[4],
				'name'					=>  $top_menu_button[4] > '' &&  $top_menu_button[4] != 'wic_email_access_button'  ? $top_menu_button[4] : 'wic_form_button',
				'type'					=>	$top_menu_button[4] > '' ? 'button' : 'submit',
				'disabled'				=>	$top_menu_button[5],
			);
		echo WIC_Form_Parent::create_wic_form_button( $button_args );
	}

	/*
	*	AJAX Routing functions -- 
	*		FORM
	*		Plain request
	*
	*   Both are limited to Entity Classes
	*	Check security requires raised security for admin classes (identified in nav_array)
	*/

	public function route_ajax_form() {
	
		$this->dictionary_setup(); 
	
		$control_array = explode( ',', $_POST['wic_form_button'] );
		
		// define terms		
		$entity = $control_array[0];
		$action = $control_array[1];
		$id_requested = $control_array[2];
		$class 	= 'WIC_Entity_' . $entity;
		$args = array (
			'id_requested'			=>	$id_requested,
		);

		// check_security and die or do the request
		ob_start();
		if ( WIC_Admin_Access::check_security ( $entity, $action, $id_requested, '' ) ) {
			$new_entity = new $class ( $action, $args );
		} else {
			wp_die( $this->unassigned_message ); // no further state processing -- leave client page unaltered
		}
		$output = ob_get_clean();

		/*
		* response is a state instruction to the client
		*	return_type 	= full_form or error_only
		*	state_action	= push or replace
		*	state			= new URL to show_source
		*	state_data		= new form response to show
		*/

		// set up default push state instructions
		$response				= new StdClass();
		$response->return_type 	= 'full_form'; // not returning validation errors alone, but allowing for future;
		$response->state_action = 'pushState';
		$response->state_data  	= $output;
		$resource				= '&entity=' . $entity . '&action=' . $action . '&id_requested=' . $id_requested; 

		// adjust the push state instructions for special cases
		if ( 'form_save_update' == $action ) {
			// do not change state on error messages
			if ( false === $new_entity->get_outcome() ) {
				$response->state_action = ''; 
			// on success shift resource to updated id search -- back goes to the result but not the pre-update form
			} else {
				$response->state_action = 'replaceState';
				$resource				= '&entity=' . $entity . '&action=id_search&id_requested=' . $new_entity->get_ID();
			}
		} elseif ( 'form_search' == $action ) {
			$resource 					= '&entity=search_log&action=id_search&id_requested=' . $new_entity->get_search_log_id ();
		}
		
		// complete the push state url by adding in requesting page
		$requesting_page = false;
		foreach ( $this->nav_array as $page => $parms ) {
			foreach ( $parms['permitted'] as $permitted ) {
				if ( $entity == $permitted[0] ) {
					$requesting_page = $page;
					break(2);
				}
			}
		}
		if ( false === $requesting_page ) {
			$requesting_page = 'wp-issues-crm-main';
		} 
		$response->state = admin_url ( 'admin.php?page=' . $requesting_page . $resource );

		// send AJAX response
		wp_die( json_encode ( $response ) );
	}


	public function route_ajax () { 
	
		$this->dictionary_setup();

		/**
		*	
		*
		* on client side, sending:
		*	var postData = {
		*		action: 'wp_issues_crm', 
		*		wic_ajax_nonce: wic_ajax_object.wic_ajax_nonce,
		*		entity: entity,
		*		sub_action: action,
		*		id_requested: idRequested,
		*		wic_data: JSON.stringify( data )
		*		};
		*		 
		*/	
		$entity = $_POST['entity'];
		$class = 'WIC_Entity_' . $entity;
		$method = $_POST['sub_action'];
		$id 	=  $_POST['id_requested'];
		$data	= json_decode ( stripslashes ( $_POST['wic_data'] ) );

		// check seccurity 
		if ( !WIC_Admin_Access::check_security ( $entity, $method, $id, $data ) ) {
			wp_die ( $this->unassigned_message );
		} else {
			$method_response = $class::$method( $id, $data  );
			wp_die ( json_encode ( (object)  $method_response ) );
		}				
	}	


	/* handler for calls from plupload */
	public function route_ajax_upload() {
		$this->dictionary_setup();
		if( !WIC_Admin_Access::check_security( 'upload','','','' ) ) {
			wp_die ( $this->unassigned_message );
		} else {
			WIC_Entity_Upload_Upload::handle_upload();
		}
	}
	public function route_ajax_document_upload() {

		$this->dictionary_setup();
		// set values
		$constituent_id = isset ( $_REQUEST['constituent_id'] ) ? $_REQUEST['constituent_id'] : 0 ;
		$issue = isset ( $_REQUEST['issue'] ) ? $_REQUEST['issue'] : 0;
		
		if ( !WIC_Admin_Access::check_security ( $constituent_id ? 'constituent' : 'issue', 'id_search', $constituent_id ? $constituent_id : $issue , '' ) )	{
			wp_die ( $this->unassigned_message );
		} else {
			WIC_Entity_Upload_Upload::handle_document_upload( $constituent_id, $issue );	
		}
	}
	public function route_ajax_attachment_upload() {
		$this->dictionary_setup();
		if ( !WIC_Admin_Access::check_security( 'email_send', 'update_draft','','' ) ) {
			wp_die ( $this->unassigned_message );
		} else {
			$draft_id = $_REQUEST['draft_id']; 	
			WIC_Entity_Upload_Upload::handle_attachment_upload( $draft_id ); // may not know constituent	
		}
	}
	
	// for to_gmail, do nonce checking on both directions
	public function route_google_oauth() {
		if ( !isset(  $_GET['page'] ) || 'wp-issues-crm-main' != $_GET['page'] ) {
			return;
		}
		// nonce is in the get string where it is expected by check_security
		if ( isset( $_GET['entity'] ) && $_GET['entity'] == 'email_oauth' && $_GET['action'] == 'redirect_to_gmail' ) {
			if( !WIC_Admin_Access::check_security ( 'email_oauth', 'oauth','','' ) ) { // includes nonce checking in get string
				wp_die ( $this->unassigned_message );
			} else {
				WIC_Entity_Email_OAUTH::redirect_to_gmail();
			} 
		// nonce, along with blog ID is in the state returned
		} elseif ( isset( $_GET['entity'] ) && $_GET['entity'] == 'email_oauth' && $_GET['action'] == 'redirect_from_gmail' ) {
			$security_array =  json_decode ( WIC_DB_Email_Message_Object_Gmail::url_safe_base64_decode ( $_GET['state'] ) );
			// might be coming from another blog back to primary blog on redirect from gmail . . . SO:
			if ( is_multisite() && $security_array[0] != BLOG_ID_CURRENT_SITE ) {
				switch_to_blog ( $security_array[0] );
			}
			// pack the nonce value for checking by check_surecity
			$_GET['oauth_nonce'] = $security_array[1];
			WIC_Entity_Email_OAUTH::redirect_from_gmail();
			if( !WIC_Admin_Access::check_security ( 'email_oauth', 'oauth','','' ) ) { // includes nonce checking in get string
				wp_die ( $this->unassigned_message );
			} else {
				WIC_Entity_Email_OAUTH::redirect_from_gmail();
			} 
		}
	}
	
	/*
	*
	* action to intercept press of download button before any headers sent 
	* 
	* intended to fire whenever top level form is posted, but not if posted (through wpIssuesCRM.mainFormButtonPost) with a wic_form_button button in $_POST
	* 
	* supports activity, constituent and document downloads; does not support email attachments which are supported by emit_stored file
	*/ 
	public function do_download () {
	
		// don't fire if doing a routine button submission or if not actually the top level form 
		if ( isset ( $_POST['wic_form_button'] ) || ! isset ( $_POST['wic-export-parameters'] ) ) {
			return;
		}

		// check access level to function
		$parameters = explode (  ',', $_POST['wic-export-parameters'] );
		$class 		= 'WIC_List_' . $parameters[0] . '_Export';
		$method 	= 'do_' 	  . $parameters[1] . '_download';
		$type		= $parameters[2];
		$id_data	= $parameters[3];

		if (! WIC_Admin_Access::check_security ( 'document' == $parameters[0] ? 'activity' : 'download', 'document', $id_data,'' ) ) { // will not see $method in check_security for download (no array)
			wp_die( $this->unassigned_message ) ;
		} else {
			$class::$method ( $type, $id_data );
		}

 	}
	/*
	*
	* action to capture get requests for image and email attachment downloads
	* 
	*/
	public function emit_stored_file () {
		// check get string for applicability and completeness 
		if ( 
			!isset ( $_GET['page'] ) || 
			!( 'wp-issues-crm-main' == $_GET['page'] ) || 
			!isset( $_GET['entity'] ) || 
			!( 'email_attachment' == $_GET['entity'] ) || 
			!isset( $_GET['attachment_id'] ) || 
			!isset ( $_GET['message_id'] ) 
			) {   
			return;
		}

		$message_in_outbox = isset( $_GET['message_in_outbox'] ) ? $_GET['message_in_outbox'] : 0;

		if (! WIC_Admin_Access::check_security ( 'email_message','load_full_message', $_GET['message_id'], $message_in_outbox ) ) {
			wp_die( $this->unassigned_message ) ;
		} else {
			WIC_Entity_Email_Attachment::emit_stored_file ( $_GET['attachment_id'],  $_GET['message_id'], $message_in_outbox );
		}
	
				
	}

	

}