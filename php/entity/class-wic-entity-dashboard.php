<?php
/**
*
* class-wic-entity-dashboard.php
*/


class WIC_Entity_Dashboard extends WIC_Entity_Parent {
	
	/**************************************************************************************************
	*
	* Dashboard display and dashboard action functions	
	*
	***************************************************************************************************/	
	
	protected function set_entity_parms ( $args ) {}
	
	
	protected function dashboard () { 

		// get sort order and what is open 
		$config = WIC_Entity_User::get_wic_user_preference ( 'wic_dashboard_config' );
		if ( $config ) {
			$sort_list = array_flip ( $config->sort ); // e.g., $sort_list['dashboard_activity'] = 0 . . .
		} else {
			$sort_list = array();
		}	
		if ( current_user_can (WIC_Admin_Access::check_required_capability ('view_edit_unassigned') ) )	{
			// inventory of dashboard widgets and titles
			$dashboard_divs = array (
				'dashboard_overview'		=> 'Staff Work Status',
				'dashboard_issues'			=> 'Assigned Issues', 
				'dashboard_cases' 			=> 'Assigned Cases', 
				'dashboard_activity'		=> 'Constituents with Activity by Issue',
				'dashboard_activity_type'	=> 'Activities by Issue and Type',
				'dashboard_recent' 			=> 'Recently Updated',
				'dashboard_searches' 		=> 'Search Log',
				'dashboard_uploads' 		=> 'Uploads', 
			);
		} else {
			// inventory of dashboard widgets and titles
			$dashboard_divs = array (
				'dashboard_overview'		=> 'Staff Work Status',
				'dashboard_myissues'		=> 'Assigned Issues', 
				'dashboard_mycases' 		=> 'Assigned Cases', 
				'dashboard_recent' 			=> 'Recently Updated', 
			);
		}
		// sort the inventory
		$sorted_dashboard_divs = array();
		$unconfigged_div_counter = 100;
		foreach ( $dashboard_divs as $id => $title ) {
			if ( isset ( $sort_list[$id] ) ) {
				$new_index =  $sort_list[$id] ;
			} else {
				$new_index = $unconfigged_div_counter;
				$unconfigged_div_counter++;
			}
			$sorted_dashboard_divs[$new_index] = array( $id, $title );	
		} 
		ksort ( $sorted_dashboard_divs );
		// identify which entity to start as open
		$open_div = isset ( $config->tall ) ? ( isset ( $config->tall[0] ) ?  $config->tall[0] : 'dashboard_overview' ) : 'dashboard_overview';
		echo '<ul id="dashboard-sortables" >';
		foreach ( $sorted_dashboard_divs as $key => $dashboard_div ) {
			$tall_class = $open_div == $dashboard_div[0] ? ' wic-dashboard-tall ' : ''; 
			echo 
				'<li id = "' . $dashboard_div[0] . '" class = "ui-state-default wic-dashboard  wic-dashboard-full  ' . $tall_class . '">' .
					'<div class="wic-dashboard-title wic-dashboard-drag-handle" title="Drag to reorder dashboard widgets"><span class="dashicons dashicons-move"></span>' . $dashboard_div[1] . '</div>' .
					$this->special_buttons ( $dashboard_div[0], $config ) .
					'<button class="wic-dashboard-title wic-dashboard-refresh-button" type="button" title="Refresh"><span class="dashicons dashicons-update"></span></button>' .
					'<div class = "wic-inner-dashboard" id="wic-inner-dashboard-' . $dashboard_div[0] . '">' . 
						'<img src="' . plugins_url(  '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) . '">' .
					'</div>' . 
				'</li>' 
			;			
		}
		echo '</ul>';
	}


	public static function save_dashboard_preferences ( $dummy_id, $data ) { 
		return WIC_Entity_User::set_wic_user_preference ( 'wic_dashboard_config', $data );
	}

	public static function dashboard_overview ( $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		global $wpdb;
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$post_meta_table = $wpdb->postmeta;
		$post_table = $wpdb->posts;
		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';
		$user_table = $wpdb->users;
		// get current folder
		$folder = WIC_Entity_Email_Account::get_folder();
		
		$constituent_sql = "
			 SELECT case_assigned as user_id, count(id) as cases_open, sum(if( case_review_date < NOW(), 1, 0)) as cases_overdue FROM $constituent_table WHERE case_status = 1 GROUP BY case_assigned
		";
	
		// inner select returns unique record foreach post, grouped to avoid double counting if somehow dup meta_keys exist
		$issue_sql = "
			SELECT user_id, count(post_id) as issues_open, sum(if(review_date < NOW(), 1, 0)) as issues_overdue 
			FROM 
				(
				SELECT  
					p.id as post_id,
					min(if(m2.meta_value IS NULL, '', m2.meta_value)) as review_date, 
					max(if(m3.meta_value is null,0,m3.meta_value)) as user_id
				FROM $post_table p
				INNER JOIN $post_meta_table m1 on p.id = m1.post_id and m1.meta_key = 'wic_data_follow_up_status'
				LEFT JOIN $post_meta_table m2 on m2.post_id = m1.post_id and m2.meta_key = 'wic_data_review_date'
				LEFT JOIN $post_meta_table m3 on m3.post_id = m1.post_id and m3.meta_key = 'wic_data_issue_staff' 
				WHERE m1.meta_value = 'open' AND ( post_status = 'publish' OR post_status = 'private' )
				GROUP BY p.ID
				) open_posts
			GROUP BY user_id
		";

		$message_sql = "
			SELECT 
				inbox_defined_staff as user_id, 
				count(id) as count_assigned_messages, 
				sum(if( 0 = inbox_defined_reply_is_final, 1, 0 )) as unfinalized_messages,  
				left( min(if( 0 = inbox_defined_reply_is_final, email_date_time, '9999-99-99' )), 10) as oldest_unanswered
			FROM $inbox_image_table WHERE inbox_defined_staff > 0 AND
				full_folder_string = '$folder' AND
				no_longer_in_server_folder = 0 AND
				to_be_moved_on_server = 0 AND
				serialized_email_object > ''
				GROUP BY inbox_defined_staff
			";	
		// get values
		$case_assigned_array 		= $wpdb->get_results ( $constituent_sql );
		$issue_assigned_array 		= $wpdb->get_results ( $issue_sql );
		$message_assigned_array 	= $wpdb->get_results ( $message_sql );
		// construct array of user ids to report on
		$user_ids = array();
		foreach ( $case_assigned_array as $line ) {
			if ( !array_key_exists ( $line->user_id, $user_ids ) ) {
				$user_ids[$line->user_id] = array();
			}
			$user_ids[$line->user_id]['cases_open'] = $line->cases_open;
			$user_ids[$line->user_id]['cases_overdue'] = $line->cases_overdue;
		}
		foreach ( $issue_assigned_array as $line ) {
			if ( !array_key_exists (  $line->user_id, $user_ids ) ) {
				$user_ids[$line->user_id] = array();
			}
			$user_ids[$line->user_id]['issues_open'] = $line->issues_open;
			$user_ids[$line->user_id]['issues_overdue'] = $line->issues_overdue;
		}
		foreach ( $message_assigned_array as $line ) {
			if ( !array_key_exists ( $line->user_id, $user_ids ) ) {
				$user_ids[$line->user_id] = array();
			}
			$user_ids[$line->user_id]['count_assigned_messages'] = $line->count_assigned_messages;
			$user_ids[$line->user_id]['unfinalized_messages'] = $line->unfinalized_messages;
			$user_ids[$line->user_id]['oldest_unanswered'] = $line->oldest_unanswered;

		}				
		
		if ( !count ( $user_ids ) ) {
			return array ( 'response_code' => true, 'output' => '<div class="dashboard-not-found">No work assignments found.</div>' );	
		}
		
		$user_login_array = array();
		$deleted_user_counter = 0;
		foreach ($user_ids as $user_id => $values ) {
			$user_display_name = false;
			if ( $user_id > 0 ) {
				$user_data = get_userdata($user_id);
				if ( $user_data ) {
					$user_display_name = isset( $user_data->display_name ) &&  $user_data->display_name  ? $user_data->display_name : $user_data->user_login;
				} else {
					$deleted_user_counter++;
					$user_display_name = 'Deleted_User_' . $deleted_user_counter;
				}
			} else {
				$user_display_name = 'Unassigned';
			}
			$user_login_array[$user_display_name] = $values;
		}
		
		ksort ( $user_login_array );
		
		$output = '<table id="wic-work-flow-status">
			<tr>
				<th>User Id</th>
				<th>Open Cases</th>
				<th>Overdue Cases</th>
				<th>Open Issues</th>
				<th>Overdue Issues</th>
				<th>Assigned Messages</th>
				<th>Unanswered Messages</th>
				<th>Oldest Unanswered</th>
			</tr>';
		foreach ( $user_login_array as $user_login => $values ) {
			$output .= '<tr>' .
				'<td>' . $user_login . '</td>' .
				'<td>' . ( isset( $values['cases_open'] ) ? $values['cases_open']: 0 ) . '</td>' .
				'<td class="' . ( isset( $values['cases_overdue'] ) && $values['cases_overdue'] > 0 ? 'wic-staff-overdue-assignment' : '' ) . '">' . ( isset( $values['cases_overdue'] ) ? $values['cases_overdue']: 0 ) . '</td>' .
				'<td>' . ( isset( $values['issues_open'] ) ? $values['issues_open']: 0 ) . '</td>' .
				'<td class="' . ( isset( $values['issues_overdue'] ) && $values['issues_overdue'] > 0 ? 'wic-staff-overdue-assignment' : '' ) . '">' . ( isset( $values['issues_overdue'] ) ? $values['issues_overdue']: 0 ) . '</td>' .
				'<td>' . ( isset( $values['count_assigned_messages'] ) ? $values['count_assigned_messages']: 0 ) . '</td>' .
				'<td class="' . ( isset( $values['unfinalized_messages'] ) && $values['unfinalized_messages'] > 0 ? 'wic-staff-overdue-assignment' : '' ) . '">'  . ( isset( $values['unfinalized_messages'] ) ? $values['unfinalized_messages']: 0 ) . '</td>' .
				'<td class="wic-email-dashboard-date">'  . ( isset( $values['oldest_unanswered'] ) &&  $values['oldest_unanswered'] != '9999-99-99' ? $values['oldest_unanswered']: '--' ) . '</td>' .
			'</tr>';
		}
		$output .= "</table>";

		return array ( 'response_code' => true, 'output' => $output );	
	}
	
	// display a list of CASES assigned to user --  (SEE ONLY OWN, NO OPTIONS)	
	public static function dashboard_mycases( $dummy_id, $data ) {

		self::save_dashboard_preferences ( $dummy_id, $data );

		$user_ID = get_current_user_id();	
		
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 99999999,
			'select_mode'		=> 'id',
		);

		$search_array = array (
			array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_assigned',
				 'value'	=>  $user_ID, 
				 'compare'	=> '=', 
				 'wp_query_parameter' => '',
			),
			array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_status',
				 'value'	=> '0', 
				 'compare'	=> '!=', 
				 'wp_query_parameter' => '',
			), 
		);

		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No cases assigned.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Constituent' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, __( 'My Cases: ', 'wp-issues-crm' ) );
			return array ( 'response_code' => true, 'output' => $list);			
		}
	}
		
	// display a list of issues assigned to user --  (SEE ONLY OWN, NO OPTIONS)	
	public static function dashboard_myissues( $dummy_id, $data ) { 
	
		self::save_dashboard_preferences ( $dummy_id, $data );
		
		$user_ID = get_current_user_id();	
		
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'issue' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 99999999,
			'select_mode'		=> 'id',
		);

		$search_array = array (
			array (
				 'table'	=> 'issue',
				 'key'	=> 'issue_staff',
				 'value'	=> $user_ID,
				 'compare'	=> '=', 
				 'wp_query_parameter' => '',
			),
			array (
				 'table'	=> 'issue',
				 'key'	=> 'follow_up_status',
				 'value'	=> 'open', 
				 'compare'	=> '=', 
				 'wp_query_parameter' => '',
			), 
		);

		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No issues assigned.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Issue' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query,  __( 'My Issues: ', 'wp-issues-crm' ) );
			return array ( 'response_code' => true, 'output' => $list);					
		} 
	}

	// display a list of assigned cases -- default is to current user
	public static function dashboard_cases( $dummy_id, $data) { 
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		extract ( (array) $data->dashboard_cases ); // case_assigned/case_review_date/case_status

		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 99999999,
			'select_mode'		=> 'id',
		);
		
		
		$search_array = array();
		if ( 'any' == $case_assigned ) {
			$assigned_term = array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_assigned',
				 'value'	=>  '', 
				 'compare'	=>  '>', 
				 'wp_query_parameter' => '',
			);
			array_push ( $search_array, $assigned_term );
		} elseif ( 'all' != $case_assigned ) { // blank or non-blank 
			$assigned_term = array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_assigned',
				 'value'	=>  $case_assigned, 
				 'compare'	=>  '=', 
				 'wp_query_parameter' => '',
			);
			array_push ( $search_array, $assigned_term );		
		} // no search term if all
		
		$status_term = array (
			 'table'	=> 'constituent',
			 'key'	=> 'case_status',
			 'value'	=> $case_status, 
			 'compare'	=> '=', 
			 'wp_query_parameter' => '',
		); 		
		array_push ( $search_array, $status_term );
		
		if ( $case_review_date != '' ) {
			$review_term = array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_review_date',
				 'value'	=> $case_review_date, 
				 'compare'	=> '<=', 
				 'wp_query_parameter' => '',
			); 		
			array_push ( $search_array, $review_term );		
		}
		
		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No cases found -- check search criteria.', 'wp-issues-crm' ) . '</div>' );		
		} elseif ( 200 < $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'Over 200 cases found -- use the advanced search function.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Constituent' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, __( 'My Cases: ', 'wp-issues-crm' ) );
			return array ( 'response_code' => true, 'output' => $list);			
		}
	} 
		
	// display a list of assigned issues -- default is to current user	
	public static function dashboard_issues( $dummy_id, $data ) { 
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		extract ( (array) $data->dashboard_issues ); // issue_staff/review_date/follow_up_status:		
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'issue' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 99999999,
			'select_mode'		=> 'id',
		);

		$search_array = array();
		if ( 'any' == $issue_staff ) {
			$assigned_term = array (
					 'table'	=> 'issue',
					 'key'	=> 'issue_staff',
					 'value'	=> '',
					 'compare'	=> '>',
					 'wp_query_parameter' => '',
				);
			array_push ( $search_array, $assigned_term );
		} elseif ( '0' === $issue_staff ) { // blank  
			$assigned_term = array (
				'table'	=> 'issue',
				'wp_query_parameter' => '',
				'relation' => 'OR',
				array( // insurance possibility -- shouldn't happen
					'key'     => WIC_DB_Access_WP::WIC_METAKEY . 'issue_staff',
					'value'   => '',
					'compare' => '=',
				),
				array( // main branch
					'key'     =>  WIC_DB_Access_WP::WIC_METAKEY . 'issue_staff',
					'value'   => '',
					'compare' => 'NOT EXISTS',
				),
			);
			array_push ( $search_array, $assigned_term );		
		} elseif ( 'all' != $issue_staff ) { // blank or non-blank 
			$assigned_term = array (
					 'table'	=> 'issue',
					 'key'	=> 'issue_staff',
					 'value'	=> $issue_staff,
					 'compare'	=> '=',
					 'wp_query_parameter' => '',
			);
			array_push ( $search_array, $assigned_term );		
		} // if 'all', do not include a search term

		if ( $follow_up_status != '') {
			// $follow_up_status is 'closed' or 'open'
			$status_term = array (
				 'table'	=> 'issue',
				 'key'	=> 'follow_up_status',
				 'value'	=> $follow_up_status, 
				 'compare'	=> '=', 
				 'wp_query_parameter' => '',
			); 		
			array_push ( $search_array, $status_term ); 
		} else {
			// $follow_up_status is empty string 
			$status_term = array (
				'table'	=> 'issue',
				'wp_query_parameter' => '',
				'relation' => 'OR',
				array( // insurance possibility -- shouldn't happen
					'key'     => WIC_DB_Access_WP::WIC_METAKEY . 'follow_up_status',
					'value'   => '',
					'compare' => '=',
				),
				array( // main branch
					'key'     =>  WIC_DB_Access_WP::WIC_METAKEY . 'follow_up_status',
					'value'   => '',
					'compare' => 'NOT EXISTS',
				),
			);		
			array_push ( $search_array, $status_term ); 		
		}

		if ( $review_date != '' ) {
			
			$review_term = array(
				'table'	=> 'issue',
				'wp_query_parameter' => '',
				'relation' => 'OR',
				array(
					'key'     => WIC_DB_Access_WP::WIC_METAKEY . 'review_date',
					'value'   => $review_date,
					'compare' => '<=',
				),
				array(
					'key'     =>  WIC_DB_Access_WP::WIC_METAKEY . 'review_date',
					'value'   => $review_date,
					'compare' => 'NOT EXISTS',
				),
			);
			
			array_push ( $search_array, $review_term );		
		}

		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No issues found -- check search criteria.', 'wp-issues-crm' ) . '</div>' );		
		} elseif ( 200 < $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'Over 200 issues found -- use the advanced search function.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Issue' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query,  __( 'My Issues: ', 'wp-issues-crm' ) );
			return array ( 'response_code' => true, 'output' => $list);					
		} 
	}


	public static function dashboard_recent( $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		$user_ID = get_current_user_id();	
		
		// spoofing a query object and pass necessary values only
		$wic_query = (object ) array ( 'result' => '', 'entity' => 'constituent', 'found_count' => '', 'retrieve_limit' => 20 );
		global $wpdb;
		$constituent_table = $wpdb->prefix . 'wic_constituent';
		$sql = "SELECT ID from $constituent_table WHERE last_updated_by = $user_ID ORDER BY last_updated_time DESC LIMIT 0, 20";
		$wic_query->result = $wpdb->get_results( $sql );
		$wic_query->found_count = count ( $wic_query->result );
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No constituents updated.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Constituent' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query,'' );
			return array ( 'response_code' => true, 'output' => $list);			
		}
	
	}

	// display user's search log ( which includes form searches, items selected from lists and also items saved )
	public static function dashboard_searches(  $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'search_log' );
		$wic_query->retrieve_search_log_latest();
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' =>  '<div class="dashboard-not-found">' . __( 'Search log new or purged.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Search_Log' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, '' );
			return array ( 'response_code' => true, 'output' => $list);				
		}
	}

	public static function dashboard_uploads (  $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		// table entry in the access factory will make this a standard WIC DB object
		$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( 'upload' );
		// select uploads that are beyond copied stage
		$wic_query->search (  
				array (
					array (
						'table'	=> 'upload',
						'key'	=> 'upload_status',
						'value'	=> 'copied',
						'compare'	=> '!=', 
						'wp_query_parameter' => '',
					),
				),	
				array( 'retrieve_limit' => 9999, 'sort_order' => true ) 
			);
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' =>  '<div class="dashboard-not-found">' . __( 'Upload log new or purged.', 'wp-issues-crm' ) . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Upload' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, '' ); 
			return array ( 'response_code' => true, 'output' => $list);	
		}
	}
	
	
	
	private function special_buttons ( $dashboard_div, $config ) { 
		
		if ( 'dashboard_activity'  == $dashboard_div ) {
			$start_control = WIC_Control_Factory::make_a_control ( 'date' );
			$start_control->initialize_default_values( 'dashboard', 'date_range', 'start' );
			$end_control = WIC_Control_Factory::make_a_control ( 'date' );
			$end_control->initialize_default_values( 'dashboard', 'date_range', 'end' );
			if ( isset ( $config->dashboard_activity ) ) {
				$start_control->set_value( $config->dashboard_activity->start );
				$end_control->set_value( $config->dashboard_activity->end );
			}
			return 	$start_control->form_control() . $end_control->form_control() . WIC_Entity_Activity::make_activity_type_filter_button();
		} elseif ( 'dashboard_activity_type'  == $dashboard_div ) {
			$start_control = WIC_Control_Factory::make_a_control ( 'date' );
			$start_control->initialize_default_values( 'dashboard', 'date_range', 'start_t' );
			$end_control = WIC_Control_Factory::make_a_control ( 'date' );
			$end_control->initialize_default_values( 'dashboard', 'date_range', 'end_t' );
			if ( isset ( $config->dashboard_activity_type ) ) {
				$start_control->set_value( $config->dashboard_activity_type->start );
				$end_control->set_value( $config->dashboard_activity_type->end );
			}
			return $start_control->form_control() . $end_control->form_control();
		} elseif ( 'dashboard_cases'  == $dashboard_div ) {
			$due_control = WIC_Control_Factory::make_a_control ( 'date' );
			$due_control->initialize_default_values( 'dashboard', 'date_range', 'case_review_date' );
			$assigned_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$assigned_control->initialize_default_values( 'dashboard', 'case_assigned', 'case_assigned' );
			$assigned_control->set_value ( get_current_user_id() );
			$status_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$status_control->initialize_default_values( 'dashboard', 'case_status', 'case_status' );
			$status_control->set_value ( '1' );
			if ( isset ( $config->dashboard_cases ) ) {
				$due_control->set_value( $config->dashboard_cases->case_review_date );
				$assigned_control->set_value( $config->dashboard_cases->case_assigned );
				$status_control->set_value( $config->dashboard_cases->case_status );
			}		
			return  $assigned_control->form_control() . $status_control->form_control() . $due_control->form_control();
		} elseif ( 'dashboard_issues'  == $dashboard_div ) {
			$due_control = WIC_Control_Factory::make_a_control ( 'date' );
			$due_control->initialize_default_values( 'dashboard', 'date_range', 'review_date' );
			$assigned_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$assigned_control->initialize_default_values( 'dashboard', 'issue_staff', 'issue_staff' );
			$assigned_control->set_value ( get_current_user_id() );
			$status_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$status_control->initialize_default_values( 'dashboard', 'follow_up_status', 'follow_up_status' );
			$status_control->set_value ( 'open' );
			if ( isset ( $config->dashboard_issues ) ) {
				$due_control->set_value( $config->dashboard_issues->review_date );
				$assigned_control->set_value( $config->dashboard_issues->issue_staff );
				$status_control->set_value( $config->dashboard_issues->follow_up_status );
			}
			return  $assigned_control->form_control() . $status_control->form_control() . $due_control->form_control();
		} else {
			return '';
		}
	}



	public static function dashboard_activity ( $dummy_id, $data ) {

		self::save_dashboard_preferences ( $dummy_id, $data );

		extract ( (array) $data->dashboard_activity );
		$start = $start ? $start : '1900-01-01';
		$end = $end ? $end : '2100-01-01';
		$type_string = '';
		$first = ''; 
		foreach ( $included as $type ) {
			$type_string .= "$first'" . $type . "'"; 
			$first = ',';
		}
		$in_clause = count ( $included ) > 0 ? " activity_type IN ( $type_string ) " : "  activity_type IS NULL "; // no null values

		// set global access object 
		global $wpdb;

		$join = $wpdb->prefix . 'wic_activity activity inner join ' . $wpdb->prefix . 'wic_constituent c on c.id = activity.constituent_id';
		$activity_sql = "
				SELECT constituent_id, issue, max(pro_con) as pro_con
				FROM $join
				WHERE activity_date <= '$end' and activity_date >= '$start' and $in_clause 
				GROUP BY activity.constituent_ID, activity.issue
				LIMIT 0, 9999999
					";	
		// $sql group by always returns single row, even if multivalues for some records 
		$sql =  "
				SELECT issue as id, count(constituent_id) as total, sum( if (pro_con = '0', 1, 0) ) as pro,  sum( if (pro_con = '1', 1, 0) ) as con  
				FROM ( $activity_sql ) as a 
				GROUP BY issue
				ORDER BY count(constituent_id) DESC
				";

		$result = $wpdb->get_results( $sql );
		$count = count ( $result );
		$wic_query = (object) array ( 
			'result' =>$result,
			'entity' => 'trend',
			'showing_count' => $count,
		);

		if ( 0 == count ( $wic_query->result ) ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No activities found.', 'wp-issues-crm' ) . '</div>' );		
		} else { 
			$lister = new WIC_List_Trend;
			$list = $lister->format_entity_list( $wic_query,'' );
			return array ( 'response_code' => true, 'output' =>  $list);			
		}
		
	}


	public static function dashboard_activity_type ( $dummy_id, $data ) {

		self::save_dashboard_preferences ( $dummy_id, $data );

		extract ( (array) $data->dashboard_activity_type );
		$start = $start ? $start : '1900-01-01';
		$end = $end ? $end : '2100-01-01';
	
		// set global access object 
		global $wpdb;

		// get activity types
		$option_table = $wpdb->prefix  . 'wic_option_value';
		$sql = "SELECT option_value, option_label FROM $option_table WHERE parent_option_group_slug = 'activity_type_options' GROUP BY option_value, option_label"; // eliminate dups from upgrade reruns
		$option_list = $wpdb->get_results ( $sql );
		// if none, return			
		if ( 0 == count ( $option_list ) ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No activities types defined.', 'wp-issues-crm' ) . '</div>' );		
		}

		// pass type list to prepare select terms for each type, array of fields mapped to query values, and string of types
		$term_string = 'issue as id, count(activity_type) as total ';
		$fields = array ( array ( 'Issue', 'id' ), array ( 'Total', 'total') );
		$type_string = '';
		$first = '';
		foreach ( $option_list as $type ) {
			$value = $type->option_value;
			$label = 'type' . $value;
			$term_string .= ", sum( if( activity_type = '$value', 1, 0 ) ) as $label";
			$fields[] = array ( $type->option_label, $label );
			$type_string .= "$first'" . $value . "'"; 
			$first = ',';
		}
		// prepare extra term for not found type ( i.e., type code option was eliminated after start of search period );
		$term_string .= ", sum( if ( activity_type NOT IN( $type_string ), 1, 0 ) ) as nf";
		$fields[] = array ( 'NF type', 'nf' );
		
		$activity_table = $wpdb->prefix . 'wic_activity';
		$activity_sql = "
				SELECT $term_string 
				FROM  $activity_table a 
				WHERE activity_date <= '$end' and activity_date >= '$start' 
				GROUP BY issue
				ORDER BY count(a.ID) DESC
				";

		$result = $wpdb->get_results( $activity_sql );
		$count = count ( $result );
		$wic_query = (object) array ( 
			'result' =>$result,
			'entity' => 'trend',
			'showing_count' => $count,
			'fields' => $fields,
		);

		if ( 0 == count ( $wic_query->result ) ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . __( 'No activities found.', 'wp-issues-crm' ) . '</div>' );		
		} else { 
			$lister = new WIC_List_Activity_Type;
			$list = $lister->format_entity_list( $wic_query,'' );
			return array ( 'response_code' => true, 'output' => $list);			
		}
		
	}

}

