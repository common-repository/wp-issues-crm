<?php
/*
*
* class-wic-entity-email-subject.php
*		fast access for subject map interface
*
* note that column collation is default, case insensitive, for incoming_email_subject, but 
* in most instances cast as binary to get case sensitive results
*
*
* email_batch_time_stamp is just a time stamp; name is a vestige of an earlier design, preserved for transition 
*
*
*/

class WIC_Entity_Email_Subject {

	public static function get_subject_line_mapping ( $incoming_email_subject ) {
	
		global $wpdb;
		$table = $wpdb->prefix . 'wic_subject_issue_map';
		$values = array (  $incoming_email_subject  ); // already sanitized before sending to client
		
		// check forget date
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		if ( isset ( $form_variables_object->forget_date_phrase ) ) {
			$forget_date  =  $form_variables_object->forget_date_phrase > '' ?
				WIC_Control_Date::sanitize_date (  $form_variables_object->forget_date_phrase ) :
			''; 
		} else {
			$forget_date = '';
		}
		// search sql -- return most recent learned association if there is one	not forgotten
		// note that if multiple matches due to wildcards, most recent taken
		$search_sql = $wpdb->prepare ( "
			SELECT * FROM $table 
			WHERE BINARY %s LIKE incoming_email_subject
			AND email_batch_time_stamp > '$forget_date' 
			ORDER BY email_batch_time_stamp DESC
			LIMIT 0, 1
			",
			$values	
		);

		// do search sql -- get latest mapped issue
		$found_array = false;
		$results = $wpdb->get_results( $search_sql );
		if ( isset ( $results[0]->mapped_issue ) ) { 	
			$found_array = array (
				'mapped_issue' 			=> $results[0]->mapped_issue, 
				'mapped_pro_con'		=> $results[0]->mapped_pro_con,
			);
		}

		return ( $found_array );

	}

	// on new map of subject line, just apply to all still pending messages with same subject line
	// when training from inbox, this will only be messages arrived since last inbox refresh, light
	// when adding in subject line manager this could take a few seconds
	private static function apply_new_subject_line_map ( $subject_line, $issue, $pro_con ) {
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$wpdb->query ( 
			$wpdb->prepare (
				"
				UPDATE $inbox_table 
				SET mapped_issue = %d, mapped_pro_con = %s
				WHERE 
				BINARY subject LIKE %s AND
					no_longer_in_server_folder = 0 AND
					to_be_moved_on_server = 0
				",
				array (
					$issue,
					$pro_con,
					$subject_line,
				)
			)
		 );
		return;
	}

	// invoked by -process.php, but takes pass through data object from client -- see email-process.js
	// always adds latest, without checking for any prior
	public static function save_new_subject_line_mapping ( &$data ) {		

		global $wpdb;
		$table = $wpdb->prefix . 'wic_subject_issue_map';

		$insert_sql = $wpdb->prepare (
			"INSERT INTO $table ( incoming_email_subject, email_batch_time_stamp, mapped_issue, 
				mapped_pro_con ) VALUES
			(%s,%s,%s,%s)",
			array (	
				$data->subject,
				current_time('mysql'), 
				$data->issue, 
				$data->pro_con, 
			)
		);

		$result = $wpdb->query ( $insert_sql );
		
		if ( false !== $result ) {
			self::apply_new_subject_line_map ( $data->subject, $data->issue, $data->pro_con );
		}
		
		return array ( 'response_code' => $result, 'output' => ( false === $result ?  $wpdb->last_error : '' ) );

	}
	
	// mapped subject list
	public static function show_subject_list ( $dummy, $data_object ) {

		// get variables
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		$forget_date_phrase = $form_variables_object->forget_date_phrase;

		if ( $forget_date_phrase > '' ) {
			$forget_date = WIC_Control_Date::sanitize_date (  $forget_date_phrase  );
		} else {
			$forget_date = '';
		}
		
		// look up subject->issue map entries where either contains the search phrase		
		global $wpdb;
		$subject_table = $wpdb->prefix . "wic_subject_issue_map";
		$post_table = $wpdb->posts;
		$option_value_table = $wpdb->prefix . 'wic_option_value';
		$constituent_table = $wpdb->prefix . "wic_constituent";
		$forget_date_phrase = $forget_date > '' ? " AND email_batch_time_stamp > '$forget_date' " : '' ;
		if ( sanitize_text_field( $data_object->search_string )  > '' ) {
			$like_term = '%' . $wpdb->esc_like ( sanitize_text_field ( $data_object->search_string ) ) . '%';
			$search_phrase = $wpdb->prepare (
				" WHERE os.incoming_email_subject LIKE %s OR post_title LIKE %s ", // for search, leave it case insensitve
				array ( $like_term, $like_term )
			);
		} else {
			$search_phrase = '';
		}
		$sql = 
			"
			SELECT os.email_batch_time_stamp, os.incoming_email_subject, os.mapped_issue, 
			IF( p.ID IS NULL, 
				CONCAT('Hard deleted issue ( ID was ', os.mapped_issue,' )' ), 
				CONCAT( IF( post_status != 'publish' AND post_status != 'private', 'Trashed or unpublished issue: ', ''), post_title )
			) as post_title, 
			option_label
			FROM
				( 
					SELECT incoming_email_subject, MAX(email_batch_time_stamp) as email_batch_time_stamp
					FROM $subject_table  
					WHERE 1=1 $forget_date_phrase 
					GROUP BY BINARY incoming_email_subject
				) s  
			INNER JOIN $subject_table os on BINARY os.incoming_email_subject = s.incoming_email_subject and os.email_batch_time_stamp = s.email_batch_time_stamp
			LEFT JOIN $post_table p on p.ID = os.mapped_issue
			LEFT JOIN $option_value_table ON parent_option_group_slug = 'pro_con_options' AND option_value = os.mapped_pro_con
			$search_phrase
			ORDER BY os.email_batch_time_stamp DESC	
			LIMIT 0, 500
			"; 
		$row_array = $wpdb->get_results ( $sql ); 
		
		// output table of results
		if ( count ( $row_array ) > 0 ) {		
			$output =	'<table class="wp-issues-crm-stats">' .
						'<colgroup>
							<col style="width:35%">
							<col style="width:45%">
							<col style="width:10%">
							<col style="width:10%">
						 </colgroup>' .  
						'<tbody>' .
						'<tr class = "wic-button-table-header">' .
						'<th class = "wic-statistic-text incoming-email-subject-list-item">' . __( 'Incoming Email Subject', 'wp-issues-crm' ) . '</th>' .
						'<th class = "wic-statistic-text">' . __( 'Mapped Issue', 'wp-issues-crm' ) . '</th>' .
						'<th class = "wic-statistic-text">' . __( 'Mapped Pro/Con', 'wp-issues-crm' ) . '</th>' .
						'<th class = "wic-table-buttons">' . __( 'Forget', 'wp-issues-crm' ) . '</th>' .
						'</tr>';
	
			$forget_button_args = array(
				'button_class'				=> 'wic-form-button wic-subject-delete-button incoming-email-subject-list-item', // style like the unqueue button
				'button_label'				=> '<span class="dashicons dashicons-no"></span>',
				'type'						=> 'button',
				'name'						=> 'wic-forget-subject-button',
				);	
		

			foreach ( $row_array as $i => $row ) { 
				$forget_button_args['value'] = sanitize_text_field ( $row->incoming_email_subject );
				$forget_button_args['title']	= 'Forget this subject line';
				$output .= '<tr>' . 
					'<td class = "wic-statistic-text incoming-email-subject-list-item" title="Subject last trained on: ' . $row->email_batch_time_stamp . '.">' . sanitize_text_field ( $row->incoming_email_subject ) . '</td>' .
					'<td class = "wic-statistic-text incoming-email-subject-list-item" ><a target = "_blank" title="Open issue in new window." href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . $row->mapped_issue . '">' . $row->post_title . '</a></td>' .
					'<td class = "wic-statistic-text" >' . $row->option_label . '</td>' .
					'<td>' . WIC_Form_Parent::create_wic_form_button ( $forget_button_args )  . '</td>' .
				'</tr>';
			} 
		 
		$output .= '</tbody></table>';
		} else  {
			$output = '<p><em>No subjects found -- either all subjects were learned before your forget date, your search phrase is not found, or you have not gotten started yet!</em></p>';
		}
		return array ( 'response_code' => true, 'output' => $output );
	}
	
	public static function delete_subject_from_list ( $dummy, $subject ) {
		// deletes all instances of exact subject, including any earlier superseded
		// however, since mappings can include wildcards, some of the affected messages could have other valid mappings
		global $wpdb;
		$subject_table = $wpdb->prefix . "wic_subject_issue_map";
		$sanitized_subject =  sanitize_text_field ( $subject );
		$sql = $wpdb->prepare ( "DELETE FROM $subject_table WHERE BINARY incoming_email_subject = %s", array ( $sanitized_subject ) );
		$response_code = $wpdb->query ( $sql ); 

		// now need to remap messages that might have other valid mappings
		if ( false !== $response_code ) {
			self::remap_after_subject_delete ( $sanitized_subject );
		}
		
		return array ( 	'response_code' => false !== $response_code, 
						'output' =>  
							false !== $response_code 
							? 
							"Subject deleted OK"
							: 
							"Database error in deletion of subject.",
		);
	}

	public static function remap_after_subject_delete ( $sanitized_subject ) {
		// find all (still pending) that have subject line like the deleted subject (which could include wildcards)
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$affected_messages_sql = $wpdb->prepare( 
			"
			SELECT ID, subject FROM $inbox_table 
			WHERE 			
				BINARY subject like %s AND
				no_longer_in_server_folder = 0 AND
				to_be_moved_on_server = 0
			",
			array ( $sanitized_subject )
		);
		$affected_messages = $wpdb->get_results( $affected_messages_sql );
		// if any, remap them each
		if ( $affected_messages ) {
			$sql_template = "
				UPDATE $inbox_table
				SET mapped_issue = %s, mapped_pro_con = %s
				WHERE ID = %d
				";
			foreach ( $affected_messages as $affected_message ) {
				$mapping = self::get_subject_line_mapping ( $affected_message->subject );				
				$fix_message_sql = $wpdb->prepare ( 
					$sql_template, 
					array(
						$mapping['mapped_issue'],
						$mapping['mapped_pro_con'],
						$affected_message->ID
					)
				);
				$wpdb->query ( $fix_message_sql );
			}
		}	
	}


	public static function manual_add_subject ( $dummy, $subjectLineObject ) {
		
		global $wpdb;
		$subject_table = $wpdb->prefix . "wic_subject_issue_map";
		$sql = $wpdb->prepare ("
			INSERT INTO $subject_table SET
				incoming_email_subject = %s, 
				email_batch_time_stamp =%s, 
				mapped_issue = %d, 
				mapped_pro_con = %s 
			",
			array(
				sanitize_text_field ( $subjectLineObject->subject ),
				current_time( 'mysql'),
				$subjectLineObject->issue,
				$subjectLineObject->proCon,
			)
		);
		
		$response_code =  $wpdb->query ( $sql );	

		if ( false !== $response_code ) {
			self::apply_new_subject_line_map ( sanitize_text_field ( $subjectLineObject->subject ), $subjectLineObject->issue, $subjectLineObject->proCon );
		}

		return array ( 	'response_code' => false !== $response_code, 
						'output' =>  
							false !== $response_code 
							? 
							"Subject added OK"
							: 
							"Database error in addition of subject.",
		);
		
	}


	
	// pass through for date parsing
	public static function parse_forget_date( $dummy, $date_phrase ) { 
		$date_decode = sanitize_text_field ( $date_phrase ); 
		$response = $date_decode > '' ?
			WIC_Control_Date::sanitize_date ( $date_decode ): 
			''; 
		return array ( 	'response_code' => true, 'output' => $response );
	}

	// unmap latest subject line -- going by latest
	// invoked only by unprocess, so latest for subject is latest for subject/pro_con
	public static function unmap_subject ( $subject ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wic_subject_issue_map';	
		$sql = $wpdb->prepare ( 
			"
			DELETE from $table 
			WHERE incoming_email_subject = %s
			ORDER BY email_batch_time_stamp DESC
			LIMIT 1
			",
			array ( $subject )
			);
		$wpdb->query ( $sql );	
	}



}

