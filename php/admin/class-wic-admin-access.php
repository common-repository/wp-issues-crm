<?php
/**
*
* class-wic-admin-access.php
*
*/


class WIC_Admin_Access {
	/*
	* This module checks capability levels of the current user's role, as defined in >Config>Security setting, against an array of required capability levels for particular class/modules.
	*
	* In addition, if the capability level is check_record, then the module tests whether the particular record is assigned to the current_user or current_user_can (view_edit_unassigned) 
	*
	* Records are assigned in the case management field group for issues and constituents or from the inbox for messages.
	*
	* GET security is mediated by Wordpress and $nav_array in WIC_Admin_Navigation, but this module is also called on GETS to verify record level access rules
	*
	* This module is primarily for authorizing calls to wpIssuesCRM ajax endpoints (both of which route only to classes in /php/entity) and the 3 upload and 2 download functions
	*
	* If ajax_class_method_to_auth_required[class] is not an array, string value is the capability level and applies to all methods within that class
	*    where ajax_class_method_to_auth_required[class] is an array, the capability level is method specific
	*
	* check_record means function is accessible to any with wp_issues_crm_access, but must check specific record
	*
	* function returns true or false -- calling navigation method must die on false
	*
	* check_security is a public function, but is called only from WIC_Admin_Access (9 methods) 
	*
	* check_required_capability is called from a few other modules to make UI changes consistent with the user's capability level.  The users capabilities as to view_edit_unassigned and email are also
	*    used for UI changes on the client side
	*
	* the list_send capability is not enforced by this module but in email_send/search_link 
	*
	* email batch cron and geocode are secured by cron keys
	*/
	public static function check_security ( $entity, $action, $id, $data, $nonce = true ) { 
		/*
		*
		*
		* preliminary check in case misconfigured hierarchy of role capabilities
		*
		*/
		if ( ! current_user_can (  WIC_Admin_Access::check_required_capability( '' ) ) ) {
			return false;
		}
		/*
		*
		* EXCLUSIVE list of allowed GETS and ajax calls and their required capabilities
		*	also applied to get calls to do check_record level of security
		*	any GET or ajax requests not in this list will be rejected (return false from this method and die in calling method) 
		*/
	
		$ajax_class_method_to_auth_required = array (
			'activity' => array (
				'set_up_activity_area' 			=> 'check_record',
				'popup_save_update'				=> 'check_record',
				'popup_delete'					=> 'check_record',
				'reassign_delete_activities'	=> 'downloads',
				'document'						=> 'check_record', // for document attachments -- coming from do_download 
			),
			// 'address' 						not taking ajax calls,
			// 'address_usps' 					not taking ajax calls,
			'advanced_search' 					=> 'view_edit_unassigned',
			//'advanced_search_activity' 		not taking ajax calls,
			//'advanced_search_constituent' 	not taking ajax calls,
			//'advanced_search_constituent_having'=> not taking ajax calls,
			//'advanced_search_row'				not taking ajax calls,
			'autocomplete' 						=> '',
			'comment' 							=> 'edit_theme_options',
			'constituent' => array (
				'new_blank_form'				=> '',
				'id_search'						=> 'check_record',
				'hard_delete'					=> 'check_record',
				'list_delete_constituents'		=> 'downloads',
				'form_save_update'				=> 'check_record'
			),
			'dashboard' => array(
				'dashboard'						=> '',
				'dashboard_overview'			=> '',
				'dashboard_mycases'				=> '',
				'dashboard_myissues'			=> '',
				'dashboard_recent'				=> '',
				'save_dashboard_preferences'	=> '',
				'dashboard_issues'				=> 'view_edit_unassigned',
				'dashboard_cases'			 	=> 'view_edit_unassigned',
				'dashboard_activity'		 	=> 'view_edit_unassigned',
				'dashboard_activity_type'	 	=> 'view_edit_unassigned',
				'dashboard_searches'		 	=> 'view_edit_unassigned',
				'dashboard_uploads'			 	=> 'view_edit_unassigned',
			),
			'data_dictionary' 					=> 'edit_theme_options',
			'download'							=> 'downloads', // not really an ajax call -- allows this function to be used by do_download (note the 's')
			// 'email'							not taking ajax calls ( email address on constituent record, called within constituent function )
			'email_account' 					=> 'email', 
			'email_activesync' 					=> 'email',
			//'email_activesync_parse'			=> not taking ajax calls,
			//'email_activesync_synch'			=> not taking ajax calls,
			'email_attachment' 					=> 'email',
			'email_block' 						=> 'email',
			'email_compose' 					=> 'send_email',
			'email_connect' 					=> 'email',
			// 'email_cron' 					secured with cron key
			'email_deliver' 					=> 'email',
			//'email_deliver_activesync'		not taking ajax calls,
			'email_inbox' => array (
				'new_blank_form'				=> '',
				//'get_issue_options'			//not taking ajax calls
				//'get_inbox_options'			//not taking ajax calls
				'load_inbox'					=> 'check_record',
				'load_sent'						=> 'view_edit_unassigned',
				'load_outbox'					=> 'view_edit_unassigned',
				'load_draft'					=> 'view_edit_unassigned',
				'load_done'						=> 'view_edit_unassigned',
				'load_saved'					=> 'view_edit_unassigned',
			),
			'email_inbox_parse' 				=> 'email',
			'email_inbox_synch'					=> 'email',
			// 'email_md5' 						not taking ajax calls,
			'email_message' => array (			
				'load_message_detail'			=> 'check_record',
				'load_full_message'				=> 'check_record',
				'save_update_reply_template' 	=> 'check_record', // if have unassigned can use template buttons
				'delete_reply_template' 		=> 'email',
				'restore_reply_template' 		=> 'email',
				'get_reply_template' 			=> 'check_record', // if have unassigned can use template buttons
				'quick_update_inbox_defined_item' => 'check_record',
				'quick_update_constituent_id' 	=> 'check_record',
				'get_post_info'					=> '', // need this to load any email, but limit to title in function if not authorized 
				'new_issue_from_message'		=> 'check_record', // if have unassigned can use template buttons
			),	
			'email_oauth'						=> '', // not adding beyond general access requirement and gmail acc
			//'email_oauth_synch' 				not taking ajax calls,
			//'email_oauth_update'				not taking ajax calls,
			'email_process'						=> 'send_email',
			'email_send' 						=> 'send_email', // note: that list_send capability is checked within the send function
			'email_settings' 					=> 'email',	
			'email_subject' => array(
				'show_subject_list'				=> 'email',
				'delete_subject_from_list'		=> 'email',
				'manual_add_subject'			=> 'email',
			),
			'email_uid_reservation' 			=> 'email',
			'email_unprocess'					=> 'email',
			'external' 							=> 'edit_theme_options',
			// 'external_field'					not taking ajax calls
			'geocode'							=> 'view_edit_unassigned', // also accessed with cron key
			'issue'						 		=> 'check_record',
			// 'issue_open_metabox'				not taking ajax calls
			// 'list'						 	not taking ajax calls
			'manage_storage'					=> 'edit_theme_options',
			// 'multivalue'						not taking ajax calls
			'option_group'						=> 'edit_theme_options',
			// 'option_value'					not taking ajax calls
			'owner'								=> 'create_sites',
			//'parent'						 	not taking ajax calls
			//'phone'							not taking ajax calls
			'search_box'						=> '',
			'search_log'						=> 'view_edit_unassigned',
			'synch'								=> 'edit_theme_options',
			'upload'						 	=> 'view_edit_unassigned',
			'upload_complete'					=> 'view_edit_unassigned',
			'upload_map'						=> 'view_edit_unassigned',
			'upload_match'						=> 'view_edit_unassigned',
			'upload_match_strategies'			=> 'view_edit_unassigned',
			'upload_regrets'					=> 'view_edit_unassigned',
			'upload_set_defaults'				=> 'view_edit_unassigned',
			'upload_upload'						=> 'view_edit_unassigned',
			'upload_validate'					=> 'view_edit_unassigned',
			'user'						 		=> 'email',
		);
	
		// uncomment dump for debugging
		// error_log ( "check_security for >$entity<, >$action<, >$id<, with data count or data string:" . ( ( is_array( $data ) || is_object ( $data ) )? print_r($data, true) : $data  ) );
		// end of debugging dump
		/*
		*
		* does this user have authority to access the requested $entity and $action
		*
		*/
		// entity must be in array or fail security -- force entry by developer ( if calling from ajax )
		if ( !array_key_exists ( $entity, $ajax_class_method_to_auth_required ) ) {
			return false;
		// string value is the level
		} elseif ( !is_array( $ajax_class_method_to_auth_required[$entity] ) ) {
			$security_level = $ajax_class_method_to_auth_required[$entity];
		// array means entity has functions at multi levels -- force entry by developer ( if calling from ajax )
		} elseif ( !array_key_exists( $action, $ajax_class_method_to_auth_required[$entity] ) ) {
			return false;
		} else {
			$security_level = $ajax_class_method_to_auth_required[$entity][$action];
		}

		$required_capability = self::check_required_capability ( 'check_record' == $security_level ? '' : $security_level );

		if ( ! current_user_can ( $required_capability ) ) {
			return false;	
		}	
		/*
		*
		* does this user have authority to access THIS RECORD via the requested $entity and $action
		*
		* view_edit_unassigned gives accesss to all constituents, activities, issues, but not to all email
		*/
		$current_user_can_view_unassigned = current_user_can( self::check_required_capability( 'view_edit_unassigned' ) );		
		if ( 'check_record' == $security_level  ) {
			// record level checking requires different functions
			switch ( $entity ) {
				case 'activity':
					if ( ! $current_user_can_view_unassigned && ! self::current_user_can_access_this_activity_record ( $action, $id, $data ) ) {
						return false;
					}
					break;	
				case 'constituent':
					if (  !$current_user_can_view_unassigned && ! self::current_user_can_access_this_constituent_record ( $id ) ) {
						return false;
					}
					break;	
				case 'email_message':
					$current_user_can_view_all_email = current_user_can( self::check_required_capability( 'email' ) );
					//  issue creation and template assignment available if can view assigned or all email 
					if ( in_array ( $action, array ( 'save_update_reply_template', 'get_reply_template', 'new_issue_from_message' ) ) ) {
						if (
							!$current_user_can_view_unassigned && 
							!$current_user_can_view_all_email 
							) {
							return false;
						}
					} elseif (  !$current_user_can_view_all_email && ! self::current_user_can_access_this_email_message ( $action, $id, $data ) ) {
						return false;
					}
					break;	
				case 'issue':
					if (  !$current_user_can_view_unassigned &&! self::current_user_can_access_this_issue_record ( $id ) ) {
						return false;
					}
					break;			
			}
		}
		
		/*
		* at this point have passed all functional and record level screens or have already returned false
		*
		* try to prevent cross site scripting by checking nonce
		*
		* either of four possible nonces are OK -- nonce controls cross-site, but not access level
		*
		* but no nonce on get page
		*/
		// $nonce is passed parameter defaulted to true; only false on menu GET ( WIC_Admin_Navigation::do_page() )
		if ( $nonce ) {
			$request_nonce 	= isset ( $_POST['wic_ajax_nonce'] ) ?  $_POST['wic_ajax_nonce'] : '';
			$form_nonce 	= isset ( $_REQUEST['wp_issues_crm_post_form_nonce_field'] ) ? $_REQUEST['wp_issues_crm_post_form_nonce_field'] : '' ; 
			$oauth_nonce	= isset ( $_GET['oauth_nonce'] ) ?  $_GET['oauth_nonce'] : '';
			$attachment_nonce = isset( $_GET['attachment_nonce'] ) ?  $_GET['attachment_nonce'] : '';
			$attachment_id = isset ( $_GET['attachment_id'] ) ? $_GET['attachment_id'] : '';
			if ( ! wp_verify_nonce ( $request_nonce, 'wic_ajax_nonce' )  && 
				 ! wp_verify_nonce ( $form_nonce, 'wp_issues_crm_post' ) &&
				 ! wp_verify_nonce ( $oauth_nonce, 'wic_oauth' ) &&
				 ! wp_verify_nonce ( $attachment_nonce, 'attachment_' . $attachment_id )
				) { 
				 die ( __( 'Bad or expired security code.  Try refreshing page.', 'wp_issues_crm' ) );		
			}		
		}		
		// passed all function and record level screen and, if doing nonce checking, passed nonce 
		return true; 	
				
	}

	private static function current_user_can_access_this_constituent_record ( $id ) { 

		if ( !$id ) {
			return true; // need this possibility for form_save_update on new record
		}		
		// now check assignment
		global $wpdb;
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$user_id = get_current_user_id();
		
		$constituent_vals = $wpdb->get_results ( $wpdb->prepare ( "SELECT case_assigned, last_updated_by FROM $constituent_table WHERE ID = %s ", array ( $id ) ) );
		// if user without general access is requesting an id and it is not valid, no need to disclose that, just say unassigned
		if ( ! $constituent_vals ) {
			return false;
		} else {
			return in_array( $user_id, array( $constituent_vals[0]->case_assigned, $constituent_vals[0]->last_updated_by ) ); 
		}

	}

	private static function current_user_can_access_this_issue_record ( $id ) { 
		
		if ( ! $id ) {
			return false; // not supposed to be saving new
		}
		// now check assignment
		global $wpdb;
		$postmeta = $wpdb->postmeta;
		$user_id = get_current_user_id();
		
		$issue_vals = $wpdb->get_results ( $wpdb->prepare ( " SELECT meta_value from $postmeta WHERE post_id = %s && meta_key = 'wic_data_issue_staff'", array ( $id ) ) ); 
		if ( ! $issue_vals ) {
			return false;
		} else {
			return $user_id == $issue_vals[0]->meta_value;
		}
	}

	// supports checking of document downloads and activity deletes
	private static function current_user_can_access_this_activity_record ( $action, $id, $data )  { // activity id

		switch ( $action ) {
			case 'set_up_activity_area':
				if ( 'constituent' == $data->parentForm && !$id ) {
					return true; // new constituent form -- blank area;
				}
				$activity_area_function = "current_user_can_access_this_{$data->parentForm}_record";
				return self::$activity_area_function( $id );
			case 'popup_save_update':
				if ( self::current_user_can_access_this_constituent_record ( $id ) ) {
					return true;
				} else {
					return self::current_user_can_access_this_issue_record( $id ); 
				}
			case 'popup_delete':
				return self::can_current_user_access_this_particular_activity_record( $id );
			case 'document': 
				return self::can_current_user_access_this_particular_activity_record($id);
		}

	}

	private static function can_current_user_access_this_particular_activity_record ( $id ) {

		// must have id		
		if ( ! $id ) {
			return false;
		}

		// now check assignemnt
		global $wpdb;
		$activity_table = $wpdb->prefix . 'wic_activity';
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$postmeta = $wpdb->postmeta;
		$user_id = get_current_user_id();

		// check if assigned to constituent -- if so OK		
		$constituent_vals = $wpdb->get_results ( $wpdb->prepare ( "SELECT case_assigned, c.last_updated_by FROM $constituent_table c INNER JOIN $activity_table a on a.constituent_id = c.id WHERE a.ID = %s ", array ( $id ) ) );
		// if user without general access is requesting an id and it is not valid, no need to disclose that, just say unassigned
		if ( $constituent_vals  &&  in_array( $user_id, array( $constituent_vals[0]->case_assigned, $constituent_vals[0]->last_updated_by ) ) ) {
			return true;
		} 
		
		// not constituent, try issue
		$issue_vals = $wpdb->get_results ( $wpdb->prepare ( " SELECT meta_value from $postmeta INNER JOIN $activity_table a on a.issue = post_id WHERE a.ID = %s AND meta_key = 'wic_data_issue_staff'", array ( $id ) ) ); 
		if ( ! $issue_vals ) {
			return false;
		} else {
			return $user_id == $issue_vals[0]->meta_value;
		}
	}


	private static function current_user_can_access_this_email_message 	( $action, $id, $data )	{ // for email, function is false only if people cannot access email ( could have email without otherwise accessing unassigned )
		switch ( $action ) {
			case 'load_inbox':
				return self::can_user_access_this_inbox_page ( $data );
			case 'load_message_detail': // from inbox, loading for reply
				return self::can_user_access_this_folder_message ( $data->fullFolderString, $id );
			case 'load_full_message': // from activity records
				return self::can_user_access_this_page_message( $id, $data ); // $data is page as 0/1 or 'done'/'sent'
			case 'quick_update_inbox_defined_item': // from inbox reply
				return self::can_user_access_this_folder_message ( $data->fullFolderString, $id );
			case 'quick_update_constituent_id' : // from inbox reply
				return self::can_user_access_this_folder_message ( $data->fullFolderString, $id );
		}
	}
	
	private static function can_user_access_this_inbox_page ( $data) {
		if ( in_array( $data->tab, array( 'CATEGORY_ASSIGNED', 'CATEGORY_READY') ) ) {
			return true;
		} else {
			return current_user_can ( WIC_Admin_Access::check_required_capability( 'email' ) );
		}
	}
	

	private static function can_user_access_this_folder_message( $folder, $uid ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';	
		$message_vals = $wpdb->get_results ( $wpdb->prepare ( "SELECT inbox_defined_staff FROM $inbox_image_table WHERE full_folder_string = %s and folder_uid = %d" , array( $folder, $uid ) ) );
		if ( $message_vals && $user_id == $message_vals[0]->inbox_defined_staff ) {
			return true;
		} else {
			return false;	
		}	
	}

	private static function can_user_access_this_page_message( $message_id, $message_in_outbox ) {
	
		// translate 'page' to binary message in outbox if necessary
		if ( 0 != $message_in_outbox && 1 != $message_in_outbox ) {
			$message_in_outbox = 'done' == $message_in_outbox ? 0 : 1;
		}
	
	
		global $wpdb;
		$user_id = get_current_user_id();

		// different logic for inbox and outbox messages
		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';
		$activity_table = $wpdb->prefix . 'wic_activity';
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		
		// if is an inbox attachment check for assigned message or assigned constituent
		if ( ! $message_in_outbox ) {
			$message_vals = $wpdb->get_results ( $wpdb->prepare ( "SELECT inbox_defined_staff FROM $inbox_image_table WHERE ID = %d" , array( $message_id ) ) ); // supports opening of attachments from inbox (treated as if from load_full_message)
			if ( $message_vals && $user_id == $message_vals[0]->inbox_defined_staff ) {
				return true;
			} else {
				$message_vals = $wpdb->get_results ( $wpdb->prepare ( "SELECT case_assigned, c.last_updated_by FROM $constituent_table c INNER JOIN $activity_table a on a.constituent_id = c.id WHERE related_inbox_record = %d" , array( $message_id ) ) );
			}
		// if outbox, check only assigned constituent
		} else {
			$message_vals = $wpdb->get_results ( $wpdb->prepare ( "SELECT case_assigned, c.last_updated_by FROM $constituent_table c INNER JOIN $activity_table a on a.constituent_id = c.id WHERE related_outbox_record = %s", array( $message_id ) ) );				
		}

		// constituent based return if did not return true on message
		if ( ! $message_vals ) {
			return false;
		} else {				
			return in_array( $user_id, array( $message_vals[0]->case_assigned, $message_vals[0]->last_updated_by ) );
		}
	}

	
	// check download or general security level setting
	public static function check_required_capability ( $type = '' ) { 
		// get security level setting
		$wic_plugin_options = get_option( 'wp_issues_crm_plugin_options_array' ); 
		$option = $type > '' ? ( 'access_level_required_' . $type ) : 'access_level_required';
		$required_capability = isset( $wic_plugin_options[$option] ) ? $wic_plugin_options[$option] : 'edit_theme_options';
		// handle upgrade to 3.5 case in network setting, where old option selected was 'activate_plugins'
		if ( 'activate_plugins' == $required_capability ) {
			$required_capability = 'edit_theme_options';
		}	
		return $required_capability;
	}
	

}