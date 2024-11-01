<?php
/*
*
*	wic-entity-email-block.php
*
*/
Class WIC_Entity_Email_Block {
		
	public static function apply_email_address_filter ( $from_email ) {
		$from_array = preg_split( '#@#', $from_email );
		$from_box = $from_array[0];
		$from_domain = isset ( $from_array[1] ) ? $from_array[1] : ''; // if a bad email address, second part may not exist
		global $wpdb;
		$filter_table = $wpdb->prefix . 'wic_inbox_incoming_filter';
		$sql = $wpdb->prepare ( "
			SELECT 
			SUM( 
				IF(from_email_box=%s OR block_whole_domain = 1, 1, 0) 
			) as blocked  
			FROM $filter_table 
			WHERE from_email_domain = %s 
			GROUP BY from_email_domain
			",
			array( $from_box, $from_domain)
			);
		$filter_row = $wpdb->get_results ( $sql );
		$filter_result = 0;
		if ( $filter_row ) {
			$filter_result = $filter_row[0]->blocked > 0 ? 1 : 0;
		}
		return $filter_result; // $filtered_row can be null if no filtered from domain or 0 if no fully matching filter from domain or 1 if matching filter.
	}


	public static function set_address_filter ( $folder, $current_uid, $whole_domain ) {
		// set up database calls
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		// get the from email from underlying message
		$result = $wpdb->get_results( "SELECT from_email, subject FROM $inbox_table WHERE full_folder_string = '$folder' AND folder_uid = $current_uid" );
		$from_email = $result[0]->from_email;
		$subject = $result[0]->subject;
		// split email into parts
		$from_array = preg_split( '#@#', $from_email );
		$from_box = $from_array[0];
		$from_domain = $from_array[1];
		// insert the filter record
		$filter_table = $wpdb->prefix . 'wic_inbox_incoming_filter';
		$whole_domain = $whole_domain ? 1 : 0; // assure a numeric value
		$sql = $wpdb->prepare ( 
			"
			INSERT INTO $filter_table 
				( 
				from_email_box,
				from_email_domain,
				subject_first_filtered,
				filtered_since,
				block_whole_domain 
				)
				VALUES ( %s, %s, %s, %s, %d )
			",
			array (
				$from_box,
				$from_domain,
				$subject,
				current_time( 'mysql'),
				$whole_domain
			)
		);
		$wpdb->query ( $sql );

	}

	// called in backout of deletes from transaction
	public static function unset_address_filter ( $folder, $current_uid, $whole_domain ) {
		// set up database calls
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		// get the from email from underlying message
		$result = $wpdb->get_results( "SELECT from_email, subject FROM $inbox_table WHERE full_folder_string = '$folder' AND folder_uid = $current_uid" );	
		$from_email = $result[0]->from_email;
		// split email into parts
		$from_array = preg_split( '#@#', $from_email );
		$from_box = $from_array[0];
		$from_domain = $from_array[1];	
		// set up delete from filter table
		$filter_table = $wpdb->prefix . 'wic_inbox_incoming_filter';
		/*
		* delete the filter(s)
		*/
		$sql = $wpdb->prepare ( 
			"
			DELETE FROM $filter_table
			WHERE 
				from_email_box = %s AND
				from_email_domain = %s
			",
			array ( $from_box, $from_domain )
		);
		$wpdb->query ( $sql );
	}

	public static function delete_address_filter ( $id, $dummy ) {
		global $wpdb;
		$filter_table = $wpdb->prefix . 'wic_inbox_incoming_filter';	
		$result = $wpdb->query ( "DELETE from $filter_table where ID = $id" );
		$response_code = ( 1 === $result );
		$output = $response_code ?  'Filter deleted OK.' : 'Database error on filter deletion or no such filter.  Refresh and retry.';
		return array ( 'response_code' => $response_code, 'output' => $output ); 
	}



	public static function load_block_list ( $dummy1, $dummy2 ) {
	
		global $wpdb;
		$filter_table = $wpdb->prefix . 'wic_inbox_incoming_filter';
		$results = $wpdb->get_results ( "SELECT * from $filter_table ORDER BY from_email_domain, from_email_box" );
		if ( $results ) {
			
			$delete_block_button_args = array(
				'button_class'				=> 'wic-form-button wic-delete-block-button',
				'button_label'				=> '<span class="dashicons dashicons-no"></span>',
				'type'						=> 'button',
				'name'						=> 'wic-email-delete-block-button',
				);	
			$output = '
				<table class="wp-issues-crm-stats" id="block-list-headers">
					<colgroup>
						<col style="width:20%">
						<col style="width:15%">
						<col style="width:15%">
						<col style="width:40%">
						<col style="width:10%">
					</colgroup>
					<tbody>
						<tr class = "wic-button-table-header" >
							<th class="wic-statistic-text">Sender Domain</th>
							<th class="wic-statistic-text">Sender Mailbox</th>
							<th class="wic-statistic-text">Blocked Since</th>
							<th class="wic-statistic-text">First Subject Blocked</th>
							<th class="wic-statistic-text">Remove Block</th>
						</tr>
					</tbody>
				</table>
				<div id="blocks-scroll-box">
					<table class="wp-issues-crm-stats">
						<colgroup>
							<col style="width:20%">
							<col style="width:15%">
							<col style="width:15%">
							<col style="width:40%">
							<col style="width:10%">
						 </colgroup>
						<tbody>
			';
			$some_blocked_whole_domain = false;
			foreach ( $results as $blocked ) {
				$blocked_class = $blocked->block_whole_domain ? ' class = "redrow" ' : '';
				$some_blocked_whole_domain = $blocked->block_whole_domain ? true : $some_blocked_whole_domain;
				$delete_block_button_args['value'] = $blocked->ID;
				$output .= 
				'<tr ' . $blocked_class . ' >' .
					'<td class="wic-statistic-text">' . $blocked->from_email_domain .'</td>' .
					'<td class="wic-statistic-text">' . $blocked->from_email_box. '</td>' .
					'<td class="wic-statistic-text">' . $blocked->filtered_since . '</td>' .
					'<td class="wic-statistic-text">' . $blocked->subject_first_filtered . '</td>' . 
					'<td>' . WIC_Form_Parent::create_wic_form_button ( $delete_block_button_args )  . '</td>' .
				'</tr>';
			}
			$output .= '</tbody></table></div>';
			if ( $some_blocked_whole_domain ) {
				$output .= '<p class="domain-blocked-legend">Red color of row indicates that whole domain blocked, not just the particular sender.</p>';
			}
		} else {
			$output = '<div id="inbox-congrats"><h1>No filters in place.</h1>' . 
				'<p><em>Set filters by choosing to block unhelpful messages as they arrive -- click the <span class="dashicons dashicons-warning"></span> button while viewing a message.</em></p></div>';
		}
		return array ( 'response_code' => true, 'output' => $output ); 

	
	}



}