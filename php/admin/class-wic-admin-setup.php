<?php
/**
*
* class-wic-admin-setup.php
*
*
*
*
* main set up class -- constructor adds basic actions and instantiates main navigation
*
* includes functions involved in setup/configuration
*
*/
class WIC_Admin_Setup {

	// does all registration and action adds ( except activation and settings )	
	public function __construct() {  

		add_action( 'init', array( $this, 'disable_emojis' ) );
		add_action( 'init', array( $this, 'reply_post_type' ), 0 );

		// load navigation 
		$wp_issues_crm_admin = new WIC_Admin_Navigation;  // small pass through, so minimal load on non-WIC pages	

		// add metabox -- fires on all save/updates of posts; 
		$wic_issue_open_metabox = new WIC_Entity_Issue_Open_Metabox;
		
		//	enqueue styles and scripts ( only acts on own pages )	
		add_action( 'admin_enqueue_scripts', array( $this, 'add_wic_scripts' ) );

		// optionally set default display of posts to private 		
		$plugin_options = get_option( 'wp_issues_crm_plugin_options_array' );		
		if ( isset( $plugin_options['all_posts_private'] ) ) {
	 		add_action( 'post_submitbox_misc_actions' , array ( $this, 'default_post_visibility' ) );
	 	}
	 	// add override css to header
	 	add_action( 'admin_head',  array( $this, 'add_wic_override_css' ) );
	}

	// add custom css
	public static function add_wic_override_css( $hook ) {
		if ( isset(  $_GET['page'] ) ) {
			if ( -1 < strpos( $_GET['page'], 'wp-issues-crm' ) ) { 
				$plugin_options = get_option( 'wp_issues_crm_plugin_options_array' );
				if ( isset ( $plugin_options['wic_override_css'] ) ) {
					echo '<style>' . $plugin_options['wic_override_css'] . '</style>';	
				}
			}
		}
	}

	// emojis
	public function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	}
	
	// load scripts and styles only for this plugin's pages
	public function add_wic_scripts ( $hook ) {
	
		// version string set in wp-issue-crm.php
		global $wp_issues_crm_js_css_version;
	
		/*
		*	each javascript module loaded for main contains own document.ready adding delegated listeners to a single container
		*  	+ no modules use the public javascript namespace for any variables
		*	+ all used in main build on object wpIssuesCRM; non-main use anonymous namespaces
		*	+ loading of js modules guided by arrays below
		*/
		if ( -1 < strpos( $hook, 'wp-issues-crm' ) ) { 

			$page_modules_map = array(
				'wp-issues-crm-main' => array ( 
					'activity', 
					'advanced-search',
					'ajax', 
					'autocomplete', 
					'constituent', 
					'dashboard',
					'email-deliver',
					'email-blocks', 
					'email-inbox', 
					'email-inbox-synch',
					'email-message',
					'email-process',
					'email-send',
					'email-settings',
					'email-subject',
					'google-maps',					
					'help',
					'issue', 
					'main',
					'map-actions',
					'shape-transfers',
					'multi-email',
					'multivalue',
					'oauth',
					'parms',
					'search', 
					'search-log',
					'selectmenu', 
					'upload-complete',
					'upload-details',
					'upload-download',
					'upload-map',
					'upload-match',
					'upload-regrets', 
					'upload-set-defaults',
					'upload-upload',
					'upload-validate', 
				),
				'wp-issues-crm-options' => array ( 
					'ajax',
					'option-group',
					'multivalue',
					'main',
					'selectmenu', 
				),
				'wp-issues-crm-fields'	=> array (
					'ajax',
					'data-dictionary',
					'main',
					'selectmenu', 
				),
				'wp-issues-crm-externals'	=> array (
					'ajax',
					'autocomplete', 
					'external',
					'main',
					'multivalue',
					'selectmenu', 
					'upload-map',					
				),
				'wp-issues-crm-owners'	=> array (
					'ajax',
					'main',
					'owner',
					'selectmenu', 
				),
				'wp-issues-crm-settings' => array (
					'ajax',
					'main',
					'parms',
					'settings',
				),
				'wp-issues-crm-storage'	=> array (
					'ajax',
					'manage-storage',
					'main',
					'selectmenu', 
				),
				'wp-issues-crm-synch' => array (
					'ajax',
					'main',
					'synch',
				),
			);

			$module_dependencies	= array( 
				'activity'			=> 	array ( 'jquery', 'jquery-ui-datepicker' ),
				'advanced-search'	=>  array ( 'jquery' ),
				'ajax'				=>	array ( 'jquery' ),
				'autocomplete' 		=>  array ( 'jquery', 'wp-issues-crm-selectmenu' ),
				'constituent' 		=>  array ( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-selectmenu', 'jquery-ui-tooltip' ),
				'dashboard'			=>  array ( 'jquery', 'jquery-ui-sortable', 'jquery-ui-tooltip' ),
				'data-dictionary'	=> 	array ( 'jquery', 'jquery-ui-spinner' ),
				'email-deliver'		=>	array ( 'jquery' ),
				'email-blocks'		=>	array ( 'jquery' ),
				'email-inbox-synch'	=>	array ( 'jquery' ),
				'email-inbox'		=>	array ( 'jquery', 'jquery-ui-tooltip', 'jquery-ui-selectmenu' ),
				'email-message'		=>	array ( 'jquery' ),
				'email-process'		=>	array ( 'jquery-ui-selectmenu', 'jquery-ui-datepicker', 'jquery-ui-progressbar', 'jquery-ui-tabs', 'jquery-ui-tooltip', 'jquery-ui-dialog'  ),
				'email-send' 		=>  array ( 'jquery', 'jquery-ui-dialog', 'jquery-ui-tooltip', ),
				'email-settings'	=>	array ( 'jquery', 'jquery-ui-spinner',),
				'email-subject'		=>	array ( 'jquery' ),
				'external'			=>	array ( 'jquery' ),
				'google-maps' 		=>  array ( 'jquery' ),
				'help'				=>	array ( 'jquery' ),
				'issue'				=>  array ( 'jquery' ),
				'main'				=>  array ( 'jquery',  'jquery-ui-tooltip', 'jquery-ui-dialog', 'jquery-ui-progressbar' ),
				'manage-storage'	=>	array ( 'jquery', 'jquery-ui-progressbar'), 
				'map-actions'		=>	array ( 'jquery' ), 
				'multi-email'		=>	array ( 'jquery', 'jquery-ui-sortable', 'wp-issues-crm-autocomplete' ),
				'multivalue' 		=>  array ( 'jquery' ),
				'oauth'				=>  array ( 'jquery' ),
				'option-group'		=>	array ( 'jquery', 'jquery-ui-spinner' ),
				'owner'				=>	array ( 'jquery' ),
				'parms'				=>  array ( 'jquery' ),
				'search' 			=>  array ( 'jquery', ),
				'search-log' 		=>  array ( 'jquery', 'jquery-ui-dialog', ),
				'selectmenu' 		=>  array ( 'jquery' ),
				'settings'			=>  array ( 'jquery', 'jquery-ui-tabs', 'jquery-ui-dialog' ),
				'shape-transfers'	=>  array ( 'jquery' ),
				'synch'				=>  array ( 'jquery' ),
				'upload-complete'	=>  array ( 'jquery-ui-progressbar' ),
				'upload-details'	=>  array ( 'jquery-ui-selectmenu', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-button' ),
				'upload-download'	=>  array ( 'jquery-ui-tooltip' ),
				'upload-map' 		=>  array ( 'jquery-ui-droppable', 'jquery-ui-draggable' ),
				'upload-match' 		=>  array ( 'jquery-ui-progressbar', 'jquery-ui-sortable' ),
				'upload-regrets' 	=>  array ( 'jquery-ui-progressbar' ),
				'upload-set-defaults'=> array ( 'jquery-ui-progressbar','jquery-ui-datepicker' ),
				'upload-upload'		=>  array ( 'plupload-html5', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-button' ),
				'upload-validate' 	=>  array ( 'jquery-ui-progressbar', 'jquery-ui-dialog' ),
			);

			/* enqueue scripts for page with specified dependencies */
			foreach ( $page_modules_map[$_GET['page']] as $module ) {
				wp_enqueue_script(
						'wp-issues-crm-' . $module,
						plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $module . '.js' , __FILE__ ), 
						$module_dependencies[$module],
						$wp_issues_crm_js_css_version,
						false 
				);
			} 

			if ( 'wp-issues-crm-main' == $_GET['page'] ) {
				/*
				*
				* load tinymce for the main page
				* -- downloaded from https://www.tinymce.com/download/
				* -- this is cleaner than the kludge here: https://wordpress.stackexchange.com/questions/219741/how-to-display-tinymce-without-wp-editor
				*		wp_enqueue_script( 'tinymce_js', includes_url( 'js/tinymce/' ) . 'wp-tinymce.php', array( 'jquery' ), false, true );
				* -- general public use license and can use all standard plugins, rather than wp plugins ( esp. wp_link ) which depend on the wp editor integration.
				* -- use for reply editing
				*
				* could also load from cloud, but need domain specific api key <script src="https://cloud.tinymce.com/stable/tinymce.min.js?apiKey=your_API_key"></script>
				*
				*/
				wp_enqueue_script( 
					'tinymce_direct',
					plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . 
						DIRECTORY_SEPARATOR . 'js'.
						DIRECTORY_SEPARATOR . 'tinymce'.
						DIRECTORY_SEPARATOR . 'js'.
						DIRECTORY_SEPARATOR . 'tinymce' .
						DIRECTORY_SEPARATOR . 'tinymce.min.js', __FILE__ ),
					array( 'jquery' ),
					$wp_issues_crm_js_css_version,
					false
				);
			}

			// set nonce and pass with pass ajax url
			wp_localize_script( 'wp-issues-crm-ajax', 'wic_ajax_object',
				array( 
					'ajax_url' 			=> admin_url( 'admin-ajax.php' ),
					'wic_ajax_nonce' 	=> wp_create_nonce ( 'wic_ajax_nonce' ), 
				) 
			);	


			// pass API key and starting map midpoints
			if ( WIC_Entity_Geocode::get_google_maps_api_key() ) {
				$set_midpoints = WIC_Entity_Geocode::get_geocode_option( 'set-map-midpoints' )['output'];
				$computed_midpoints = WIC_Entity_Geocode::get_geocode_option( 'computed-map-midpoints' )['output'];
				$midpoints = $set_midpoints ? $set_midpoints : $computed_midpoints;
				if ( ! $midpoints ) {
					$midpoints = array ( 42.353, -71.1 ); // arbitrary
				}
				wp_localize_script( 'wp-issues-crm-google-maps', 'wicGoogleMaps',
					array( 
						'apiKey' =>  WIC_Entity_Geocode::get_google_maps_api_key(),
						'latCenter' => $midpoints[0],
						'lngCenter' => $midpoints[1],
						'localLayers' => defined('WP_ISSUES_CRM_MAP_DATA_LAYERS') ? WP_ISSUES_CRM_MAP_DATA_LAYERS : false,
						'localCredit' => defined('WP_ISSUES_CRM_MAP_DATA_CREDIT') ? WP_ISSUES_CRM_MAP_DATA_CREDIT : false
					) 
				);	
			} else {
				wp_localize_script( 'wp-issues-crm-google-maps', 'wicGoogleMaps',
					array( 
						'settingLink' => '<a  target="_blank" href="' . admin_url() . 'admin.php?page=wp-issues-crm-settings">Configure</a>',
					) 
				);						
			}


			// get option array and pass options 
			$wic_option_array = get_option('wp_issues_crm_plugin_options_array');
			wp_localize_script( 'wp-issues-crm-main', 'wpIssuesCRMSettings',
				array( 
					'settings_url'		=> admin_url( 'admin.php?page=wp-issues-crm-settings' ),
					'activitiesFrozenDate' 			=> WIC_Entity_Activity::get_freeze_date(),
					'financialCodesArray'			=> ( isset ( $wic_option_array['financial_activity_types'] ) && trim( $wic_option_array['financial_activity_types'] ) > '' )  ? explode (',' , $wic_option_array['financial_activity_types'] ) : array(),
					'maxFileSize' => WIC_Entity_Upload_Upload::get_safe_file_size(),
					'dearToken' => WIC_Entity_Email_Send::dear_token,
					'canViewAllEmail' => current_user_can (WIC_Admin_Access::check_required_capability ('email')),
					'canSendEmail' => current_user_can (WIC_Admin_Access::check_required_capability ('send_email')),
					'canViewOthersAssigned' => current_user_can (WIC_Admin_Access::check_required_capability ('view_edit_unassigned')),
				) 
			);				

			// pass loader for repeated use
			wp_localize_script( 'wp-issues-crm-main', 'wpIssuesCRMLoader',
				'<div class = "wic-loader-image">' .
					'<em>Loading . . .  </em>
					<img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) . '">' . 
				'</div>' 
			);

			// enqueue jquery ui theme roller style
			wp_enqueue_style(
				'wic-theme-roller-style',
				plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'jquery-ui-1.11.4.custom'  . DIRECTORY_SEPARATOR .   'jquery-ui.min.css' , __FILE__ ),
				array(),
				$wp_issues_crm_js_css_version,
				'all'
				);

			// enqueue wp issues crm styles
			$page_css_map = array(
				'wp-issues-crm-main' => array ( 
					'activity', 
					'advanced-search',
					'buttons',
					'constituent',
					'dashboard', 
					'email',
					'google-maps',
					'issue', 
					'list',
					'main',
					'multi-email',
					'search', 
					'selectmenu',
					'upload',
				),
				'wp-issues-crm-options' => array ( 
					'buttons', 
					'list',
					'main', 
					'option',
					'selectmenu',
				),
				'wp-issues-crm-fields'	=> array (
					'buttons',
					'list', 
					'main', 
					'option',
					'selectmenu',
				),
				'wp-issues-crm-externals'	=> array (
					'buttons',
					'list', 
					'main', 
					'external',
					'upload',
					'selectmenu',
				),
				'wp-issues-crm-owners'	=> array (
					'buttons',
					'list', 
					'owner',
					'main', 
					'selectmenu',
				),
				'wp-issues-crm-settings' => array (
					'buttons', 
					'main', 
					'settings',
				),
				'wp-issues-crm-storage'	=> array (
					'buttons', 
					'main', 
					'manage-storage',
					'selectmenu',
				),
				'wp-issues-crm-synch'	=> array (
					'buttons',
					'main',
					'synch' 
				),
			);

			foreach (  $page_css_map[$_GET['page']] as $style ) {
				wp_enqueue_style(
					'wic-styles-' . $style,
					plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . $style . '.css' , __FILE__ ),
					array(),
					$wp_issues_crm_js_css_version,
					'all'
				);		
			}
		} // close conditional for using wp-issues-crm					
	} // close add scripts function

	// Register Custom Post Type
	public static function reply_post_type() {

		$labels = array(
			'name'                  => _x( 'Reply Templates', 'Post Type General Name', 'wp-issues-crm' ),
			'singular_name'         => _x( 'Reply Template', 'Post Type Singular Name', 'wp-issues-crm' ),
			'menu_name'             => __( 'Email Reply Templates', 'wp-issues-crm' ),
			'name_admin_bar'        => __( 'Email Reply Templates', 'wp-issues-crm' ),
			'archives'              => __( 'Reply Template Archives', 'wp-issues-crm' ),
			'attributes'            => __( 'Reply Template Attributes', 'wp-issues-crm' ),
			'parent_item_colon'     => __( 'Parent Reply Template:', 'wp-issues-crm' ),
			'all_items'             => __( 'All Reply Templates', 'wp-issues-crm' ),
			'add_new_item'          => __( 'Add New Reply Template', 'wp-issues-crm' ),
			'add_new'               => __( 'Add New', 'wp-issues-crm' ),
			'new_item'              => __( 'New Reply Template', 'wp-issues-crm' ),
			'edit_item'             => __( 'Edit Reply Template', 'wp-issues-crm' ),
			'update_item'           => __( 'Update Reply Template', 'wp-issues-crm' ),
			'view_item'             => __( 'View Reply Template', 'wp-issues-crm' ),
			'view_items'            => __( 'View Reply Templates', 'wp-issues-crm' ),
			'search_items'          => __( 'Search Reply Templates', 'wp-issues-crm' ),
			'not_found'             => __( 'Not found', 'wp-issues-crm' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'wp-issues-crm' ),
			'featured_image'        => __( 'Featured Image', 'wp-issues-crm' ),
			'set_featured_image'    => __( 'Set featured image', 'wp-issues-crm' ),
			'remove_featured_image' => __( 'Remove featured image', 'wp-issues-crm' ),
			'use_featured_image'    => __( 'Use as featured image', 'wp-issues-crm' ),
			'insert_into_item'      => __( 'Insert into item', 'wp-issues-crm' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'wp-issues-crm' ),
			'items_list'            => __( 'Reply Templates list', 'wp-issues-crm' ),
			'items_list_navigation' => __( 'Reply Templates list navigation', 'wp-issues-crm' ),
			'filter_items_list'     => __( 'Filter items list', 'wp-issues-crm' ),
		);
		$args = array(
			'label'                 => __( 'Reply Template', 'wp-issues-crm' ),
			'description'           => __( 'WP Issues CRM Email Reply Templates', 'wp-issues-crm' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', ),
			'taxonomies'            => array(),
			'hierarchical'          => false,
			'show_ui'               => true, // http://examplehost/wp-admin/edit.php?post_type=wic_reply_tempate
			'show_in_menu'			=> false,
			'can_export'            => true,
			'has_archive'           => true,		
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'delete_with_user'		=> false,
		);

		register_post_type( 'wic_reply_tempate', $args );
	}


	public static function wic_set_up_roles_and_capabilities() {

		// give administrators the manage constituents capacity	
	   $role = get_role( 'administrator' );
	   $role->add_cap( 'manage_wic_constituents' ); 
	   // deny/remove it from editors 
	   // ( cleaning up legacy entries in database; may grant editors access through Settings panel )	
	   $role = get_role( 'editor' );
	   $role->remove_cap( 'manage_wic_constituents' );	

		// define a role that has limited author privileges and access to the plugin
		
		// first remove the role in case the capabilities array below has been revised  	
		remove_role('wic_constituent_manager');
	
		// now add the role
		$result = add_role(
	   	'wic_constituent_manager',
	    	__( 'Constituent Manager', 'wp-issues-crm' ),
		   array(
		   		// capacities to add
				'manage_wic_constituents' 	=> true, // grants access to plugin and all constituent functions
		        'read_private_posts' 		=> true, // necessary for viewing (and so editing) individual private issues through wic interface
		        'read'						=> true, // allows access to dashboard
		        // capacities explicitly (and perhaps unnecessarily) denied
	            'edit_posts'  					=> false, // limits wp backend access -- can still edit private issues through the wic interface
		        'edit_others_posts'  			=> false, // limits wp backend access -- can still edit private issues through the wic interface 
		        'delete_posts'					=> false,
	            'delete_published_posts' 		=> false,
		        'edit_published_posts' 			=> false,
		        'publish_posts'					=> false,
		        'read_private_pages' 			=> false,
		        'edit_private_posts' 			=> false,
		        'edit_private_pages' 			=> false, 
		        'upload_files'					=> false,
		    )
		);
		
	}
	  
	/**
	 * https://wordpress.org/support/topic/how-to-set-new-post-visibility-to-private-by-default?replies=14#post-2074408 
	 *
	 * It reverses the role of public and private in the logic of what visibility is assigned in the misc publishing metabox.
	 * Compare /wp-admin/includes/meta-boxes.php, lines 121-133.   
	 * It then includes jquery script to write the correct values in after the fact.
	 * Since core functions are doing the output as they go, there is no good pre or post hook, so client side jquery is only surgical solution 
	 * 
	*/
	 function default_post_visibility(){
		global $post;
		
		if ( 'publish' == $post->post_status ) {
			$visibility = 'public';
			$visibility_trans = __('Public');
		} elseif ( !empty( $post->post_password ) ) {
			$visibility = 'password';
			$visibility_trans = __('Password protected');
		} elseif ( $post->post_type == 'post' && is_sticky( $post->ID ) ) {
			$visibility = 'public';
			$visibility_trans = __('Public, Sticky');
		} else {
			$post->post_password = '';
			$visibility = 'private';
			$visibility_trans = __('Private');
		} ?>
		
	 	<script type="text/javascript">
	 		(function($){
	 			try {
	 				$('#post-visibility-display').text('<?php echo $visibility_trans; ?>');
	 				$('#hidden-post-visibility').val('<?php echo $visibility; ?>');
	 				$('#visibility-radio-<?php echo $visibility; ?>').attr('checked', true);
	 			} catch(err){}
	 		}) (jQuery);
	 	</script>
	 	<?php
	 }
// close class WIC_Admin_Setup	 
}