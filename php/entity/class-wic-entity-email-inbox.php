<?php
/*
*
*	wic-entity-email-inbox.php
*
*/
Class WIC_Entity_Email_Inbox extends WIC_Entity_Parent {


	/*
	*
	* basic entity functions
	*
	*
	*/
	protected function set_entity_parms( $args ) {
		$this->entity = 'email_inbox';
		$this->entity_instance = '';
	} 

	// special version of this function to allow checking of settings before form display 
	protected function new_blank_form( $args = '', $guidance = '' ) { 

		$wic_settings = get_option( 'wp_issues_crm_plugin_options_array' );
		
		// check if have basic imap settings in place
		if 	( ! WIC_Entity_Email_Account::get_folder() ){
			$this->display_email_process_not_configured_splash();
			$args = array ( 'id_requested' => 'settings' );
		} 
		global $wic_db_dictionary;
		$this->fields = $wic_db_dictionary->get_form_fields( $this->entity );
		$this->initialize_data_object_array();
		$new_form = new WIC_Form_Email_Inbox;
		// passing $args to layout_inbox -- entity_parent standard is to pass $guidance with third param styling info
		$new_form->layout_inbox( $this->data_object_array, $args, '' );
	}	

	private function display_email_process_not_configured_splash() {
		echo '
			<div id="unconfigured-message-for-process-email">
				<p>WP Issues CRM Email: Not fully configured -- check Controls.</p>
			</div>
		';
	}

   /*
   * functions supporting field definitions
   */
   // pass through from original entity
	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}
	public static function get_inbox_options ( $value ) {
		return array (
			array ( 'value' => 'inbox'    		,  'label' => 'Inbox'   ),
			array ( 'value' => 'draft'			,  'label' => 'Drafts'  ),
			array ( 'value' => 'done'			,  'label' => 'Archive'  ),
			array ( 'value' => 'outbox'			,  'label' => 'Outbox'   ),
			array ( 'value' => 'sent'			,  'label' => 'Sent'  ),
			array ( 'value' => 'saved'			,  'label' => 'Saved'  ),
			array ( 'value' => 'manage-subjects',  'label' => 'Subjects'  ),
			array ( 'value' => 'manage-blocks'	,  'label' => 'Blocked'   ),
			array ( 'value' => 'inbox-synch'  	,  'label' => 'Synch'   	 ),
			array ( 'value' => 'settings'  		,  'label' => 'Controls'   ),
		);
	}

	public static function load_inbox ( $dummy_id, $data ) { 

		// set up variables
		$current_user_id = get_current_user_id();
		// note that if !$user_can_see_all, then WIC_Admin_Access will have bounced a tab request other than for CATEGORY_ASSIGNED, CATEGORY_READY
		$user_can_see_all = current_user_can ( WIC_Admin_Access::check_required_capability( 'email' ) );
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		$parse_strictness = isset ( $form_variables_object->parse_strictness ) ? $form_variables_object->parse_strictness : '5'; // default to loosest standard (max strictness = 1; 6 = no email address)
		// get confidence thresholds
		$word_minimum_threshold = isset ( $form_variables_object->word_minimum_threshold ) ? $form_variables_object->word_minimum_threshold : 200;
		$mapped_threshold = isset ( $form_variables_object->mapped_threshold ) ? $form_variables_object->mapped_threshold : 85;
		// get pipe seperated list of team from emails
		$team_list = isset ( $form_variables_object->team_list ) ? $form_variables_object->team_list : '';
		$team_list_array = $team_list ? explode ( '|', $team_list ): array( 'zzz@zzz.zzz'); // put in a never found dummy email to avoid empty criterion
		// get current folder
		$folder = WIC_Entity_Email_Account::get_folder();
		// bounce if no folder selected
		if ( '' == $folder ) {
			return array ( 'response_code' => false , 'output' => 'Check Controls -- no inbox folder selected.' ) ;
		}
		$tab_display = ucfirst ( strtolower( substr ( $data->tab, 9 ) ) );
		/*
		* Compose sql statements to assemble inbox lines from constant and mode-dependent elements
		*
		* Note: the if statements in this query (and in load_message_detail ) implement the following conservative logic tree for choosing when to group issues:
		*
		* Group messages if and only if:
		*   (1) There is a subject map record that they share (so, same subject) (not expired);
		*	(2) They all have content mapped to the same issue/pro-con, which agrees with the subject mapped result
		*   (3) The content match meets both the required confidence percentage and the required word count percentage
		*   (4) HAVE NOT HAD ANY INDIVIDUAL MESSAGE INBOX DEFINITION ACTIVITY (since 4.5, added draft capacity)
		*   NOTE: There may or may not be a reply already assigned to the issue -- could be just recording
		*
		*   most of the inbox (as opposed to message detail view) is security and cosmetics: the only processing consequences of the inbox line content flow from 
		*		* "have_trained_issue" (which determines, by adding a class below, whether a line is grouped and so eligible for sweeps per above strict criterion) 
		*	 	*  and the folder_uid list (which is the basis of all line processing)
		*
		*   note that there is an overriding ungroup rule which is parse quality vs. strictness (lower is better/stricter, so ungroup if quality > strictness)
		*/
		global $wpdb;
		// start by attempting to set group_concat_max_len to handle long UID lists
		try {
			// wordpress should trap and report error if can't set it, but don't want to see the error
			ob_start();
			$wpdb->query( "SET SESSION group_concat_max_len = 100000" );
			ob_end_clean();
		} catch ( Exception $err ) {
			// catch is belt and suspenders in case wordpress fails to trap error
			error_log ( print_r ( $err, true ) );
		}
		// check setting of group_concat_max_len
		$shown_vars = $wpdb->get_results ( "SHOW VARIABLES LIKE 'group_concat_max_len'" );
		if ( is_array( $shown_vars  ) && ( 1 == count ( $shown_vars ) ) ){
			$group_concat_max_length = $shown_vars[0]->Value;
		} else {
			$group_concat_max_length = 1024; // assume default if not accessible
		}
		
		/*
		* sweep_definition is critical concept that enforces strictness of automated reply processing per notes above
		*
		* added 4 inbox_defined terms exclude items that have had individual attention from the inbox in any grouping
		*/
		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';

		$sweep_definition = " 
			mapped_issue > 0 AND 
			guess_mapped_issue = mapped_issue AND 
			guess_mapped_pro_con = mapped_pro_con AND
			guess_mapped_issue_confidence >= $mapped_threshold AND 
			non_address_word_count >= $word_minimum_threshold AND
			inbox_defined_staff = 0 AND
			inbox_defined_issue = 0 AND
			inbox_defined_pro_con = '' AND
			inbox_defined_reply_text = ''
			";

		// user supplied filter string from in box email -- $data->filter is not blank, from email, from personal (name), subject and snippet will be scanned for it
		// only emails with a positive scan will be returned
		$filter = sanitize_text_field ( $data->filter );
		$filter_where = self::filter_where ( $filter );

		/*
		*
		* this is a hook to allow substitution of category definitions without reparsing the inbox.  Do not user wild card like phrases; will conflict with prepare
		*
		* the following is an example that could be placed in a local plugin -- always include leading and trailing white spaces around the defined term
		define ('WP_ISSUES_CRM_LOCAL_CATEGORY_REWRITE', " 
			IF( category = 'CATEGORY_PROMOTIONS', 
				'CATEGORY_PERSONAL', 
				IF(
					category = 'CATEGORY_FORUMS', 
						'CATEGORY_SOCIAL', 
						category 
				) 
			) 
		");
		*/ 
		$category_phrase = defined ( 'WP_ISSUES_CRM_LOCAL_CATEGORY_REWRITE' ) ? WP_ISSUES_CRM_LOCAL_CATEGORY_REWRITE : ' category '; 
		/*
		* add tab selection terms -- CATEGORY_TEAM, CATEGORY_ADVOCACY, CATEGORY_ASSIGNED, CATEGORY_READY special, dynamically applied
		*
		* assigned_subject is the subject from $assigned_subject_join subselection query below 
		*	if assigned_subject is null, 
		*     	no email with that subject is currently in the inbox and assigned
		*		the email should be displayed in its parsed catogery or if mapped in CATEGORY_ADVOCACY
		*
		*   if assigned_subject is not null,
		*		the email is assigned or has a subject line that is assigned and should be displayed as
		*			CATEGORY_ASSIGNED or if a response has been drafted in CATEGORY_READY	
		*
		*    if ! $user_can_see_all, just choosing between two allowed tabs --
		*		CATEGORY_ASSIGNED or if a response has been drafted in CATEGORY_READY
		* 
		*   CATEGORY_TEAM is an overriding category -- it doesn't matter what the underlying category is; if from team, in team tab and not elsewhere
		*		EXCEPT that subject being assigned overrides email being team
		*/ 
		$team_list_criterion = '';
		if ( $team_list_array ) {
			$team_list_criterion = '( ';
			foreach ( $team_list_array as $email_criterion ) {
				// cannot use wpdb prepare -- % gets replaced with hash; so hard resanitize the $email_criterion directly
				// ?? https://stackoverflow.com/questions/53831586/using-like-statement-with-wpdb-prepare-showing-hashes-where-wildcard-character
				$email_criterion_resanitized = preg_replace ( '/[^%@.+_A-Za-z0-9-]/', '', $email_criterion );
				$team_list_criterion .=  " from_email LIKE '$email_criterion_resanitized' OR ";
			}
			$team_list_criterion .=  " $category_phrase = 'CATEGORY_TEAM' OR 1 = 0 )"; // (short) legacy of built in category_team
		}

		$category_where = $user_can_see_all ? 
 			(
 			'CATEGORY_TEAM' == $data->tab ? " $team_list_criterion AND assigned_subject is null AND " :
				(
					$wpdb->prepare (
						" IF( 
							assigned_subject is NULL, 
							IF( mapped_issue > 0, 'CATEGORY_ADVOCACY', $category_phrase ), 
							IF( subject_is_final, 'CATEGORY_READY', 'CATEGORY_ASSIGNED' )
						) = %s" ,
						array ( $data->tab )
					) 
					. 	
					" AND ( NOT $team_list_criterion OR assigned_subject IS NOT NULL ) AND "
				)
			)
			:
			// note that if ! $user_can_see_all, $absolute_user_where_limit also applies, limiting displayed messages to assigned
			$wpdb->prepare (
					" IF( inbox_defined_reply_is_final , 'CATEGORY_READY', 'CATEGORY_ASSIGNED' ) = %s AND ",
					array ( $data->tab )
				);		
		/*
		* limit selection to inbox content (selected folder, not deleted or intended to be deleted and already parsed)
		*
		* if not can see all absolutely limit to only assigned emails
		*/
		$absolute_user_where_limit = $user_can_see_all ? '' : ( " AND inbox_defined_staff " . $wpdb->prepare ( " = %s ", $current_user_id ) );
		$other_where_terms = 
			"full_folder_string = '$folder' AND
				no_longer_in_server_folder = 0 AND
				to_be_moved_on_server = 0 AND
				serialized_email_object > ''
				$absolute_user_where_limit 
			";	

		$sort_assigned_to_top = ( $data->tab == 'CATEGORY_ASSIGNED' || $data->tab == 'CATEGORY_READY' ) ? " if( inbox_defined_staff, 1, 0) DESC, " : '';
		// key implementing language for group options (note that higher parse_quality number is worse parse, up to 6 with no email address
		$group_lines =
			"GROUP BY BINARY
				IF ( parse_quality > '$parse_strictness', 
					folder_uid,  
					IF( $sweep_definition, subject, folder_uid )
				)
 			ORDER BY  $sort_assigned_to_top min( if ( account_thread_latest > '', account_thread_latest, email_date_time ) ) " . $data->sort . ',  min(email_date_time) ' . $data->sort . '';
		$ungroup_lines = 
			" ORDER BY $sort_assigned_to_top IF ( account_thread_latest > '', account_thread_latest, email_date_time )  " . $data->sort .  ',  email_date_time ' . $data->sort . '';

		/*
		*
		* join to support Assigned and Ready tabs
		*
		* $user_subject_where_limit does not limit the larger search, only the look up for the following subsidiary tables
		*
		* this join is only  necessary when showing assigned and ready tabs for user with Email capability -- serves to move 
		*	subjects that are identical to subjects of assigned emails into the ready and advocacy tabs
		*/
		$user_subject_where_limit =  
				" WHERE inbox_defined_staff " . 
					(
						$data->staff ? 
							$wpdb->prepare ( " = %s ", $data->staff ) :
							" > '' "
					)    
			;
		// join to identify assigned emails/subjects
		$assigned_subject_join = ! $user_can_see_all ? '' :
			"
			LEFT JOIN 
				(  
				  SELECT max( inbox_defined_reply_is_final ) as subject_is_final, subject as assigned_subject 
				  FROM $inbox_image_table 
				  $user_subject_where_limit AND $other_where_terms
				  GROUP BY subject
				) assigned_subjects 
			ON subject = assigned_subject 
			";
		/*
		*
		* first check counts for all tabs with only basic where terms -- show straight message count, not grouped -- if all counts = 0
		*
		* if ! $user_can_see all, only seeing ready and assigned tabs and only for current user is inbox_defined_staff, only first two cases apply
		*/
		$tabs_array = WIC_Entity_Email_Account::get_tabs(); 
		$tabs_summary_sql = '';
		foreach ( $tabs_array  as $tab ) { 
			$category = 'CATEGORY_' . strtoupper( $tab );
			if ( ! $user_can_see_all && ! in_array( $category, array( 'CATEGORY_READY', 'CATEGORY_ASSIGNED' ) ) ) {
				continue;				
			}
			// this logic covers all five possible combinations for counts, the default covering all tabs other than the synthetic team, ready, advocacy and assigned 
			$tabs_summary_sql .= ", SUM(IF(" ; 
			switch ( $category) {
				// in the first two cases, subject_is_final cannot be null because assigned_subject_is not null and inbox_defined_reply_is_final is not null field
				case 'CATEGORY_READY':
					$tabs_summary_sql .= (
						$user_can_see_all ?
						" assigned_subject IS NOT NULL AND subject_is_final > 0, ":
						" inbox_defined_reply_is_final > 0, "
					);
					break;
				case 'CATEGORY_ASSIGNED':
					$tabs_summary_sql .= (
						$user_can_see_all ?
						" assigned_subject IS NOT NULL AND subject_is_final = 0, ":
						" inbox_defined_reply_is_final = 0,  "
					);
					break;
				case 'CATEGORY_TEAM':
					$tabs_summary_sql .= (
						"  assigned_subject is null AND $team_list_criterion, "
					);
					break;				
				// in the latter two cases, mapped_issue cannot be null because it is a not null field
				case 'CATEGORY_ADVOCACY':
					$tabs_summary_sql .= 
						" assigned_subject IS NULL AND  NOT $team_list_criterion AND ( mapped_issue > 0 OR $category_phrase = '$category' ), ";
					break;
		    	default:
		    		$tabs_summary_sql .= 
						" assigned_subject IS NULL AND NOT $team_list_criterion AND ( mapped_issue = 0 AND $category_phrase = '$category' ), ";				
			} 
			$tabs_summary_sql .= "1, 0)) as $category";
			 	
		}
				
		$tabs_count_sql =		
			"
			SELECT count(ID) as all_inbox_messages_count $tabs_summary_sql
			FROM $inbox_image_table $assigned_subject_join 
			WHERE $other_where_terms			
			"
		; 		

		$tab_counts = $wpdb->get_results ( $tabs_count_sql ); 

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$subjects_array_sql = 
			(
			'grouped' != $data->mode ?
			" 
			SELECT SQL_CALC_FOUND_ROWS
				inbox_defined_staff,
				account_thread_latest,
				subject,
				snippet,
				account_thread_id,
				from_email,
				from_domain,
				is_my_constituent_guess as mine,
				if ( email_date_time > '', email_date_time, activity_date ) as oldest,
				1 as `count`,
				folder_uid as UIDs,
				if(from_personal > '', from_personal, from_email ) as `from`,
				IF( $sweep_definition, 1, 0 ) as have_trained_issue,
				0 as conversation,
				0 as many
			"
			:
			"
			SELECT SQL_CALC_FOUND_ROWS
				inbox_defined_staff,
				max(account_thread_latest) as account_thread_latest,
				subject,
				max(snippet) as snippet,
				max(account_thread_id) as account_thread_id,
				from_email,
				from_domain,
				max(is_my_constituent_guess) as mine,
				min( if ( email_date_time > '', email_date_time, activity_date ) ) as oldest, 
				count(folder_uid) as `count`, 
				group_concat( folder_uid ORDER BY folder_uid ASC SEPARATOR ',' ) as UIDs," . // this uid list becomes the array that drives processing when the user sweeps or shifts to inbox view
				"if(from_personal > '', from_personal, from_email ) as `from`, " . // used only in conjunction with many for display purposes
				"IF( $sweep_definition, 1, 0 ) as have_trained_issue," . // have_trained_issue is variable that determines eligibility for sweep by setting class 'trained-class' -- group by logic assures that all in group have same sweep definition
				"IF(LEFT(subject,3)='re:', 1, 0 ) as conversation,
				if(COUNT(DISTINCT from_personal) > 1, 1, 0 ) as many
			"
			) . 
			"
			FROM $inbox_image_table $assigned_subject_join
			WHERE 
				$filter_where
				$category_where
				$other_where_terms 
			" . 
			(
			'grouped' == $data->mode ?
				$group_lines :
				$ungroup_lines
			) .
			"
			LIMIT $page_base, $max_count 					
			"
			;
		// get subjects array
		$subjects_array = $wpdb->get_results( $subjects_array_sql );
		// get count total messages ( as filtered and/or grouped )
		$found_result = $wpdb->get_results( "SELECT FOUND_ROWS() AS found_count" );	  
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$sort_order = ( 'ASC' == $data->sort ) ? ' first-arrived ' : ' last-arrived ';
		$loaded_object = 'grouped' == $data->mode ? 'subject lines' :   'messages';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		// define user limit statement
		if ( ( ! $user_can_see_all || $data->staff  ) && ( $data->tab == 'CATEGORY_ASSIGNED' || $data->tab == 'CATEGORY_READY' ) ) {
			$user_data = get_userdata( $user_can_see_all ? $data->staff : $current_user_id );
			$user_display_name = $user_data->display_name ? $user_data->display_name : $user_data->user_login;		
			$user_limit_statement = ' Limited to messages assigned to ' . $user_display_name . '. ';
		} else {
			$user_limit_statement = '';
		}
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page. $user_limit_statement" ;

		$count_subjects = 0;
		if ( $subjects_array ) {
			/*
			* create inbox output -- consider this an interface from prior message analysis, but only some elements of each subject line have consequences down stream:
			* -- trained-subject class, driven by query analysis of message above (does the message in this subject line meet strict sweep criteria?);
			*	 . . . used in wpIssuesCRM.processEmail (email-process.js) to aggregate a list sweepable uids
			*	 . . . if NOT in sweep mode, processing runs off the issue/pro_con and template coming from the form
			* -- array (comma separated in each line) of UIDs defines which messages are acted on
			* -- count of messages is used in chrome for both inbox and inbox detail
			*/
			$output = '<ul id="inbox-subject-list">';
			$prior_account_thread_latest = '';
			$thread_child = '';
			foreach ( $subjects_array as $subject  ) {
				$from_summary = (  $subject->count > 1 ? ('(' . $subject->count . ') ') : ''   ) . $subject->from . ( $subject->many ? ' +' : '' );
				// regardless of count, if already mapped mark with class and show the already mapped legend
				if ( $subject->have_trained_issue > 0  ) {
					$trained_class = ' trained-subject ';
					$trained_legend = 'Trained: ';
				} else {
					$trained_class = '';
					$trained_legend = '';
				}
				// mark as assigned
				$assigned_staff_class =  $subject->inbox_defined_staff ? ' inbox-assigned-staff ' : '';
				/*
				* manage truncated UID list -- can't count on ability of user to set session value for group_concat_max_len over 1024
				*/
				// if apparently truncated (OK if wrong, just unnecessarily discard a UID)
				if ( $group_concat_max_length == strlen ( $subject->UIDs ) ) {
					$UIDs = explode( ',',  $subject->UIDs );
					array_pop( $UIDs ); // discard last element, possibly truncated
					$uid_count = count ( $UIDs );
					$uid_list = implode ( ',', $UIDs );
					$from_summary = "Many -- over group_concat_max_length";
				} else {
					$uid_count = $subject->count;
					$uid_list = $subject->UIDs;
				}
				// manage account thread delineation
				if ( $subject->account_thread_latest != $prior_account_thread_latest ) {
					$prior_account_thread_latest = $subject->account_thread_latest; 
					$thread_child = '';
				} else {
					$thread_child = '<span class="light-dashicon"> &raquo; </span> ';
				}
				// format an li for the inbox
				$output .= 
				'<li class="inbox-subject-line '. $trained_class .'">' .
					'<div class="inbox-subject-line-checkbox-wrapper">' .
						'<input class="inbox-subject-line-checkbox"  type="checkbox" value="1"/>' .
					'</div>' .
					'<ul class="inbox-subject-line-inner-list">' . // *class determines sweepability*
						'<li class = "subject-line-item from-email">' . $subject->from_email . '</li>' . // hidden, used to filter out blocked
						'<li class = "subject-line-item from-domain">' . $subject->from_domain . '</li>' . // hidden, used to filter out blocked
						'<li class = "subject-line-item from-summary' . ( 'Y' == $subject->mine ? ' includes-constituents ' : '' ) . '">' . $from_summary. '</li>' . // just display
						'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . $uid_count . '</span></li>' . // *supports multiple UI elements*
						'<li class = "subject-line-item subject' . $trained_class . $assigned_staff_class . '">' . $thread_child .  $trained_legend . '<span class="actual-email-subject">' . $subject->subject . '</span><span class="wic-email-snippet">' . ( $subject->snippet ? ' -- ' : '' ).$subject->snippet .'</span></li>' . // just display
						'<li class = "subject-line-item oldest" title="Date of oldest">' . $subject->oldest . '</li>' . // just display
						'<li class = "subject-line-item UIDs">' . $uid_list . '</li>' . // *pass through for all processing*
						'<li class = "subject-line-item account_thread_id">' . $subject->account_thread_id . '</li>' . // *pass through to inbox for presentation
					'</ul>
				</li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . $view_statement . '</div>' . 
				'</div>';
		// no messages found
		} else {
			if ( !$tab_counts[0]->all_inbox_messages_count ) {
				$output = '<h3 class="all-clear-inbox-message">All clear -- done for now! ' . $user_limit_statement  . '</h3>';
			} else {
				if ( ! in_array( $tab_display, WIC_Entity_Email_Account::get_tabs() ) ) {  
					$output = '<div id = "filtered-all-warning">Tab configuration changed? Refresh page to reload tabs.</div>';
				} else {
					$output = $filter  ? 
						( '<div id = "filtered-all-warning">No from email address or subject line containing "' . $filter . '" ' . ' in ' . $tab_display  . '.</div>' ) : 
						('<div id="inbox-congrats">All clear ' .  ' in ' . $tab_display . '.' .$user_limit_statement .  '</div>');
				}
			}
		}

		$connection_failures = WIC_Entity_Email_Connect::check_connection_failure_count() ? '' : 
			( 
			  '<p>WP Issues CRM encountered connection failures while polling your inbox.</p>' .
			  '<p>Perhaps you changed your email password or it has expired?</p>' .
			  '<p>Please update your password and "Test Settings </p>' .
			  '<p>Testing settings will reset the failure counter.</p>' 
			);

		// construct inbox title
		$inbox_header = 
			WIC_Entity_Email_Account::get_folder_label() . 
			(
				$found_count ?				
				( ': ' .  ( $page_base + 1 ) . '-' . ( $page_base + $count_subjects ) . ' of ' . $found_count . ' ' . $loaded_object . ' in ' . $tab_display) :
				''
			);
		if ( $filter ) {
			$inbox_header .= " filtered by `$filter`";
		}
		
		$load_parms = (object) array ( 
			'folder'				=>   $folder,
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		


		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => WIC_Entity_Email_UID_Reservation::check_old_uid_reservations(),
			'connection' => $connection_failures,
			'last_load_parms' => $load_parms,
			'tab_counts' => $tab_counts[0]
		);
		
		return array ( 'response_code' => true, 'output' => $return_array ); 
	}

	public static function load_sent ( $dummy_id, $data ) {
		return self::load_sent_outbox ( 1, 0, $data );
	}

	public static function load_outbox ( $dummy_id, $data ) {
		return self::load_sent_outbox ( 0, 0, $data );
	}

	public static function load_draft ( $dummy_id, $data ) {
		return self::load_sent_outbox ( 0, 1, $data );
	}


	private static function load_sent_outbox ( $sent_ok, $is_draft, $data ) { 
	
		/*
		* Return looks like inbox to js
		*
		*/
		global $wpdb;
		$outbox = $wpdb->prefix . 'wic_outbox';
		$filter = sanitize_text_field ( $data->filter );
		if ( $filter > '' ) {
			$filter_where = $wpdb->prepare ( "
				( 
					LOCATE( %s, subject ) > 0 OR 
					LOCATE( %s, to_address_concat ) > 0 
				) AND ", 
				array( $filter, $filter ) );
		} else {
			$filter_where = '';
		}
		// limit selection to sent/draft content
		$other_where_terms = 
			" sent_ok = $sent_ok AND is_draft = $is_draft";	
			
		$oldest = $sent_ok ? "sent_date_time" : "queued_date_time"; // this correct in draft mode -- sent_ok = 0

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$subjects_array_sql = 
			(
			( 'grouped' != $data->mode || $is_draft ) ?
			" 
			SELECT SQL_CALC_FOUND_ROWS
				ID,
				subject,
				serialized_email_object,
				$oldest as oldest,
				1 as `count`
			"
			:
			"
			SELECT SQL_CALC_FOUND_ROWS
				ID,
				subject,
				serialized_email_object,
				min( $oldest ) as oldest, 
				count(ID) as `count`
			"
			) . 
			"
			FROM $outbox
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				( 'grouped' == $data->mode && !$is_draft ) ? 
					( " GROUP BY SUBJECT ORDER BY MIN( $oldest ) " . $data->sort . ' ' ) : 
					( " ORDER BY $oldest " . $data->sort . ' ')
			) .
			"
			LIMIT $page_base, $max_count 					
			"
			;
		// get subjects array
		$subjects_array = $wpdb->get_results( $subjects_array_sql );
		// get count total messages ( as filtered and/or grouped )
		$found_result = $wpdb->get_results( "SELECT FOUND_ROWS() AS found_count" );	
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$past_tense = $is_draft ? 'drafted' : ( $sent_ok ? 'sent' : 'queued' );
		$sort_order = ( 'ASC' == $data->sort ) ? " first-$past_tense " : " last-$past_tense ";
		$loaded_object = ( 'grouped' == $data->mode && !$is_draft ) ? 'subject lines' :   'messages';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page." ;

		$count_subjects = 0;
		if ( $subjects_array ) {
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$email_object = unserialize ( $subject->serialized_email_object );
				$to_summary = (  $subject->count > 1 ? ('(' . $subject->count . ') ') : ''   );
				$to_summary .= isset ( $email_object->to_array[0] ) ?
					( ( trim($email_object->to_array[0][0]) ? trim( $email_object->to_array[0][0] ) : $email_object->to_array[0][1] ) . ( $subject->count > 1 ? '+' : '' ) ) :
					'Addressee not specified yet';
				// format an li for the inbox
				$output .= '<li class="inbox-subject-line "><ul class="inbox-subject-line-inner-list">' .
					'<li class = "subject-line-item message-ID">' . $subject->ID . '</li>' .
					'<li class = "subject-line-item from-summary">' . $to_summary. '</li>' .
					'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . $subject->count . '</span></li>' .
					'<li class = "subject-line-item subject"><span class="actual-email-subject">' . $subject->subject . '</span></li>' . 
					'<li class = "subject-line-item oldest" title="Date of oldest">' . $subject->oldest . '</li>' .
				'</ul></li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . $view_statement . '</div>' . 
				'</div>';
		// no messages found
		} else {
			$output = $filter  ? 
				( '<div id = "filtered-all-warning">No to/cc email address or subject line containing "' . $filter . '".</div>' ) : 
				('<div id="inbox-congrats"><h1>No ' . ( $is_draft ? 'draft' : ( $sent_ok ? 'sent' : 'unsent' ) ). ' messages.</h1>' );
		}

		// construct inbox title
		$inbox_header = 
			( $is_draft ? 'Draft: ' : ( $sent_ok ? 'Sent: ' : 'Outbox: ' ) ). 
			'<span id="outbox-lower-range">' . ( $found_count ? $page_base + 1 : 0 ) . '</span>-<span id="outbox-upper-range">' . ( $page_base + $count_subjects ) . '</span> of <span id="outbox-total-count">' . $found_count . '</span> ' . $loaded_object; 
		
		$load_parms = (object) array ( 
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		

		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => false,
			'connection' => false,
			'last_load_parms' => $load_parms,
		);
		
		return array ( 'response_code' => true, 'output' => $return_array  ); 
	}

	// $filter_where = self::filter_where ( $filter );
	private static function filter_where ( $filter ) {
		global $wpdb;
		if ( $filter > '' ) {
			$filter_where = $wpdb->prepare ( "
				( 
					LOCATE( %s, subject ) > 0 OR 
					LOCATE( %s, from_email ) > 0 OR 
					LOCATE( %s, from_personal ) > 0 OR
					LOCATE( %s, snippet ) > 0
				) AND ", 
				array( $filter, $filter, $filter, $filter ) );
		} else {
			$filter_where = '';
		}
		return $filter_where; 
	}

	public static function load_done ( $dummy_id, $data ) { 

		/*
		* looks like inbox to js and css
		* 
		*/
		// get current folder
		$folder = WIC_Entity_Email_Account::get_folder();
		// bounce if no folder selected
		if ( '' == $folder ) {
			return array ( 'response_code' => false , 'output' => 'Check Controls -- no inbox folder selected.' ) ;
		}
		// done messages remain in the inbox		
		global $wpdb;
		$inbox_image_table = $wpdb->prefix . 'wic_inbox_image';

		$filter = sanitize_text_field ( $data->filter );
		$filter_where = self::filter_where ( $filter );

		// limit selection to inbox content
		$other_where_terms = 
			"full_folder_string = '$folder' AND
				to_be_moved_on_server = 1 
			";	

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$subjects_array_sql = 
			(
			'grouped' != $data->mode ?
			" 
			SELECT SQL_CALC_FOUND_ROWS
				ID,
				subject,
				from_personal as `from`,
				if ( email_date_time > '', email_date_time, activity_date ) as oldest,
				1 as `count`,
				0 as many
			"
			:
			"
			SELECT SQL_CALC_FOUND_ROWS
				ID,
				subject,
				from_personal as `from`,
				min( if ( email_date_time > '', email_date_time, activity_date ) ) as oldest,
				count(ID) as `count`, 
				if(COUNT(DISTINCT from_personal) > 1, 1, 0 ) as many
			"
			) . 
			"
			FROM $inbox_image_table
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				'grouped' == $data->mode ? 
					( " GROUP BY SUBJECT ORDER BY MIN( if ( email_date_time > '', email_date_time, activity_date ) ) " . $data->sort . ' ' ) : 
					( " ORDER BY if ( email_date_time > '', email_date_time, activity_date )  " . $data->sort . ' ' )
			) .
			"
			LIMIT $page_base, $max_count 					
			"
			;

		// get subjects array
		$subjects_array = $wpdb->get_results( $subjects_array_sql );
		// get count total messages ( as filtered and/or grouped )
		$found_result = $wpdb->get_results( "SELECT FOUND_ROWS() AS found_count" );	
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$sort_order = ( 'ASC' == $data->sort ) ? ' first-arrived ' : ' last-arrived ';
		$loaded_object = 'grouped' == $data->mode ? 'subject lines' :   'messages';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page." ;

		$count_subjects = 0;
		if ( $subjects_array ) {
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$from_summary = (  $subject->count > 1 ? ('(' . $subject->count . ') ') : ''   ) . $subject->from . ( $subject->many ? ' +' : '' );
				// format an li for the inbox
				$output .= '<li class="inbox-subject-line "><ul class="inbox-subject-line-inner-list">' . // *class determines sweepability*
					'<li class = "subject-line-item message-ID">' . $subject->ID . '</li>' .
					'<li class = "subject-line-item from-summary">' . $from_summary. '</li>' . // just display
					'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . $subject->count . '</span></li>' . // *supports multiple UI elements*
					'<li class = "subject-line-item subject"><span class="actual-email-subject">' . $subject->subject . '</span></li>' . // just display
					'<li class = "subject-line-item oldest" title="Date of oldest">' . $subject->oldest . '</li>' . // just display
				'</ul></li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . $view_statement . '</div>' . 
				'</div>';
		// no messages found
		} else {
			$output = $filter  ? 
				( '<div id = "filtered-all-warning">No archived message with from email address or subject line containing "' . $filter . '".</div>' ) : 
				('<div id="inbox-congrats"><h1>No archived messages.</h1>');
		}


		// construct inbox title
		$inbox_header = 
			'Archived: ' . 
			( $page_base + 1 ) . '-' . ( $page_base + $count_subjects ) . ' of ' . $found_count . ' ' . $loaded_object; 
		
		$load_parms = (object) array ( 
			'folder'				=>   $folder,
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		

		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => false,
			'connection' => false,
			'last_load_parms' => $load_parms,
		);
		
		return array ( 'response_code' => true, 'output' => $return_array  ); 
	}
	
	public static function load_saved ( $dummy_id, $data ) { 

		/*
		* looks like inbox to js and css
		* 
		*/
		global $wpdb;
		$posts = $wpdb->posts;
		$meta  = $wpdb->postmeta;


		$filter = sanitize_text_field ( $data->filter );
		if ( $filter > '' ) {
			$filter_where = $wpdb->prepare ( " LOCATE( %s, p2.post_title ) > 0 AND ", array( $filter ) );
		} else {
			$filter_where = '';
		}

		// limit selection to inbox content
		$other_where_terms = 
			" m.meta_key LIKE '" . WIC_Entity_Email_Message::WIC_REPLY_METAKEY_STUB . "%'";	

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$subjects_array_sql = 
			(
			'grouped' != $data->mode ?
			" 
			SELECT SQL_CALC_FOUND_ROWS
				p.ID,
				p.post_title as subject,
				meta_key,
				meta_value,
				p2.post_modified as oldest,
				1 as count

			"
			:
			"
			SELECT SQL_CALC_FOUND_ROWS
				p.ID,
				p.post_title as subject,
				GROUP_CONCAT( meta_key ORDER BY meta_key SEPARATOR ','  ) as meta_key,
				meta_value,
				min( p2.post_modified )  as oldest,
				count( p.ID ) as count
			"
			) . 
			"
			FROM $posts p INNER JOIN $meta m ON p.ID = m.post_id inner join $posts p2 on p2.ID = meta_value
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				'grouped' == $data->mode ? 
					( " GROUP BY p.post_title ORDER BY MIN( p2.post_modified )  " . $data->sort . ' ' ) : 
					( " ORDER BY p2.post_modified  " . $data->sort . ' ' )
			) .
			"
			LIMIT $page_base, $max_count 					
			"
			;

		// get subjects array
		$subjects_array = $wpdb->get_results( $subjects_array_sql );
		// get count total messages ( as filtered and/or grouped )
		$found_result = $wpdb->get_results( "SELECT FOUND_ROWS() AS found_count" );	
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$sort_order = ( 'ASC' == $data->sort ) ? ' first-modified ' : ' last-modified ';
		$loaded_object = 'grouped' == $data->mode ? 'issues' :   'issue/pro-con';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page." ;

		// get pro_con options
		global $wic_db_dictionary;
		$option_array = $wic_db_dictionary->lookup_option_values( 'pro_con_options' );
		

		// process subject array
		$count_subjects = 0;
		if ( $subjects_array ) {
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$meta_keys = explode ( ',' , $subject->meta_key );
				$pro_con_label = ''; 
				$later = false;
				foreach ( $meta_keys as $meta_key ) {
					if ( false === $later  ) {
						$later = true;
					} else {
						$pro_con_label .= '|';
					}
					$pro_con_label .=  WIC_Function_Utilities::value_label_lookup ( substr( $meta_key, 24), $option_array ) ;
				}
				// format an li for the inbox
				$output .= '<li class="inbox-subject-line "><ul class="inbox-subject-line-inner-list">' . // *class determines sweepability*
					'<li class = "subject-line-item message-ID">' . $subject->ID . '</li>' .
					'<li class = "subject-line-item reply-ID">' . $subject->meta_value . '</li>' .
					'<li class = "subject-line-item subject"><span class="actual-email-subject">' . $subject->subject .  '</span>' . ( $pro_con_label ? ' (' : '') .  // passed through to display if click
					'<span class = "from-summary">' . $pro_con_label . '</span>' . ( $pro_con_label ? ')' : '') . '</li>' . // passed through to display if clicked
					'<li class = "subject-line-item pro-con-value">' . substr( $subject->meta_key, 24) . '</li>' . // used in reply retrieval
					'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . $subject->count . '</span></li>' . // *supports multiple UI elements*
					'<li class = "subject-line-item oldest" title="Date of oldest">' . $subject->oldest . '</li>' . // just display
				'</ul></li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . $view_statement . '</div>' . 
				'</div>';
		// no messages found
		} else {
			$output = $filter  ? 
				( '<div id = "filtered-all-warning">No saved reply standards with titles containing "' . $filter . '".</div>' ) : 
				('<div id="inbox-congrats"><h1>No saved reply standards.</h1>');
		}


		// construct inbox title
		$inbox_header = 
			'Saved Reply Standards: ' . 
			( $page_base + 1 ) . '-' . ( $page_base + $count_subjects ) . ' of ' . $found_count . ' ' . $loaded_object; 
		
		$load_parms = (object) array ( 
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		

		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => false,
			'connection' => false,
			'last_load_parms' => $load_parms,
		);
		
		return array ( 'response_code' => true, 'output' => $return_array  ); 
	}

}