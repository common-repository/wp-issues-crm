<?php
/*
* class-wic-form-upload-download.php
*
*
*/

class WIC_Form_Upload_Download extends WIC_Form_Upload  {  			


	protected function format_message ( &$data_array, $message ) {

		$upload_status = $data_array['upload_status']->get_value();
		
		// set message based on status
		if 	( 'completed' == $upload_status || 'completed_express' == $upload_status ) {
			$status_phrase = 'is complete.';
			$message_level = 'good_news'; 
		} elseif ( 'reversed' == $upload_status ) {
			$status_phrase = 'has been reversed ( as to NEW constituents and NEW activities ).';
			$message_level = 'guidance';
		} else {
			$status_phrase = 'was interrupted -- you can safely restart.' ;					
			$message_level = 'error'; 
		}
		$message =  sprintf ( 'Upload of %s ' . $status_phrase , $data_array['upload_file']->get_value() );
		$css_message_level = $this->message_level_to_css_convert[$message_level];
		
		return array ( 'message' => $message, 'css_message_level' => $css_message_level );
	}


	protected function get_the_buttons( &$data_array ) {

		$upload_status = $data_array['upload_status']->get_value();
		$upload_parameters	= json_decode ( $data_array['serialized_upload_parameters']->get_value() );

		// define backout button based on status (always visible, disabled if reversed )
		$button_args_main = array(
			'entity_requested'			=> $upload_parameters->staging_table_name,
			'action_requested'			=> 'backout_new',
			'button_label'				=> '<span class="button-highlight">Alt:  </span>Backout Upload',
			'type'						=> 'button',
			'id'						=> 'wic-upload-backout-button',
			'name'						=> 'wic-upload-backout-button',
			'title'						=> 'Backout new constituents and new activities (if any). Cannot backout updates to existing constituents (if any).',
			// enable button consistently with message above button
			'disabled'					=> ( 'reversed' == $upload_status ),
		);	
		$buttons = $this->create_wic_form_button ( $button_args_main );
		
		// define restart button if not completed
		if ( 'started' == $upload_status || 'started_express' == $upload_status ) {
			$button_args_main = array(
				'name'						=> 'wic-upload-restart-button',
				'button_label'				=> '<span class="button-highlight">Alt:  </span><em>Restart</em>',
				'type'						=> 'button',
				'title'						=> 'Safely restart upload that was interrupted.',
				'id'						=> 'wic-upload-restart-button',
			);
			$buttons .= $this->create_wic_form_button ( $button_args_main );
		}
		
		return $buttons;
	}


	private function get_the_download_buttons ( &$data_array ) {
	
		$upload_status = $data_array['upload_status']->get_value();
		$upload_parameters	= json_decode ( $data_array['serialized_upload_parameters']->get_value() );

		// create array of downloads -- for each, button title, explanatory text and disabled -- true = disabled 
		$download_layout = 	array (
			 'not_loaded'	=> array (
				'Download Errors',
				__( 'Download csv file of all non-savable input records.', 'wp-issues-crm' ),
				'started_express' == $upload_status	|| 'completed_express' == $upload_status,  				 
			 ), 		
			 'dump'			=> array (
				'Download All',
				__( 'Download csv file of all input records. ', 'wp-issues-crm' ),
				false,				 
			 ), 			
		); 

		$download_button_group = '<div id = "upload-download-buttons">';
			foreach ( $download_layout as $button => $download ) {
				$button_args_main = array(
					'value'				=> 'constituent,staging_table,' . $button . ',' . $upload_parameters->staging_table_name,
					'button_class'		=> 'wic-form-button wic-download-button',
					'button_label'		=> __( $download[0], 'wp-issues-crm' ) ,
					'type'				=> 'submit',
					'id'				=> 'wic-staging-table-download-button',
					'name'				=> 'wic-staging-table-download-button',
					'title'				=> $download[1],
					'disabled'			=> $download[2]
				);	
				$button = $this->create_wic_form_button ( $button_args_main );
				$download_button_group .= $button;
			}
		$download_button_group .= '</div>'; 
	
		return $download_button_group;
	}

	private function set_up_info_table ( &$data_array ) {
		
		$upload_status 		= $data_array['upload_status']->get_value();
		$upload_parameters	= json_decode ( $data_array['serialized_upload_parameters']->get_value() );
		$final_results 		= json_decode ( $data_array['serialized_final_results']->get_value() );
		$match_results 		= json_decode ( $data_array['serialized_match_results']->get_value() );

		switch ( $upload_status ) {
			case 'started':
			case 'completed':
				return WIC_Form_Upload_Complete::summary_results ( $data_array );
			case 'started_express':
			case 'completed_express':
				// table headers				
				return  
				'<table class="wp-issues-crm-stats">
					<tr>' .
						'<th class = "wic-statistic-text">' . __( 'Upload Results', 'wp-issues-crm' ) . '</th>' .
						'<th class = "wic-statistic">' . __( 'Planned', 'wp-issues-crm' ) . '</th>' .					
						'<th class = "wic-statistic">' . __( 'Completed', 'wp-issues-crm' ) . '</th>' .
					'</tr>'.
					'<tr>' .
						'<td class = "wic-text">' . __( 'New constituents', 'wp-issues-crm' ) . '</td>' .
						'<td class = "wic-statistic" >' . $upload_parameters->insert_count . '</td>' .
						'<td class = "wic-statistic" id = "new_constituents_saved" >' . $final_results->new_constituents_saved . '</td>' .
					'</tr>' .
				'</table>';
			case 'reversed':
				// get totals
				if ( $match_results > '' ) {			
					$valid_matched = 0;
					$unmatched_records_with_valid_components = 0;
					foreach ( $match_results as $slug => $match_object  ) {
						$valid_matched += $match_object->matched_with_these_components;
						$unmatched_records_with_valid_components += $match_object->unmatched_records_with_valid_components;			
					}	
				}
				$new_constituents =  isset ( $unmatched_records_with_valid_components ) ? $unmatched_records_with_valid_components : $upload_parameters->insert_count;
				$updated_constituents =  isset ( $valid_matched ) ? $valid_matched : 'N/A' ;
				return
				'<table class="wp-issues-crm-stats">
					<tr>' .
						'<th class = "wic-statistic-text">' . __( 'Upload Results', 'wp-issues-crm' ) . '</th>' .
						'<th class = "wic-statistic">' . __( 'Input', 'wp-issues-crm' ) . '</th>' .					
						'<th class = "wic-statistic">' . __( 'Status', 'wp-issues-crm' ) . '</th>' .
					'</tr>'.
					'<tr>' .
						'<td class = "wic-text">' . __( 'New constituents', 'wp-issues-crm' ) . '</td>' .
						'<td class = "wic-statistic">' . ( $new_constituents > 0 ? $new_constituents : 'N/A' ) . '</td>' .
						'<td class = "wic-statistic">' . ( $new_constituents > 0 ? 'Reversed' : 'N/A' ) . '</td>' .
					'</tr>' .
					'<tr>' .
						'<td class = "wic-text">' . __( 'Updates to existing constituents', 'wp-issues-crm' ) . '</td>' .
						'<td class = "wic-statistic">' . ( $updated_constituents > 0 ? $updated_constituents : 'N/A' ) . '</td>' .
						'<td class = "wic-statistic">' . ( $updated_constituents > 0 ? 'Only actitivies reversed' : 'N/A' )  . '</td>' .
					'</tr>' .
				'</table>';
		}
	}

	
	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {
		
		global $wic_db_dictionary;		

		$upload_status = $data_array['upload_status']->get_value();

		// set up form
		$form =	'<form id = "' . $this->get_the_form_id() . '" class="wic-post-form" method="POST" autocomplete = "on">'; // start to frame form
	
			$form .= $this->get_the_buttons( $data_array ); 
			// append info section
			$form .= '<div id="upload_info">' .
				$this->set_up_info_table ( $data_array )  .
			'</div>';
			
			$form .= $this->get_the_download_buttons ( $data_array );		

			$form .= $data_array['ID']->form_control();
			$form .= $data_array['upload_file']->form_control();				
			$form .= $data_array['upload_status']->form_control();	// need this to initialize popup
			$form .= $data_array['serialized_upload_parameters']->form_control(); // need this to initialize popup
			$form .= $data_array['serialized_match_results']->form_control(); // need this to initialize popup
			$form .= wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, false );
	 
			// material for use in backout popup -- hidden on form display 
			$form .= '<div id = "backout_legend">' .
					'<h3>' . __( 'Backing out updates:', 'wp-issues-crm' ) . '</h3>' .
					'<ul class = "upload-status-summary" >' .
					'<li>' .
						__( 'This backout function deletes newly added activities and newly added constituents. ', 'wp-issues-crm' ) .
					'</li>' .
					'<li>' .
						__( 'Updates to address, phone or email of existing constituents generally cannot be reversed except by restoration from a Wordpress database backup.', 'wp-issues-crm' ) .
					'</li>' .
					'<li>' .
						__( 'The backout process runs much faster than the update process, but is not chunked and can take a few minutes for larger jobs.', 'wp-issues-crm' ) .
					'</li>' .					
					'<li>' .
						__( 'Since express uploads treat all records as new, they can always be fully backed out.  For express uploads, you will be returned to the mapping stage and can
						retry the upload with a different mapping.  For non-express uploads, after backout, the upload cannot be used further.', 'wp-issues-crm' ) .
					'</li>' .		

				'<ul>' .
			'</div>';

		$form .= '</form>';

		return  (object) array_merge ( array( 'form' => $form ), $this->format_message( $data_array, '' ) );
	}
}