<?php
/*
* class-wic-list-constituent.php
* 
*
*/ 

class WIC_List_Constituent extends WIC_List_Parent {

	protected function format_rows( &$wic_query, &$fields ) { 
		$output = '';
		
				
		$line_count = 1;
		// convert the array objects from $wic_query into a string
  		$id_list = '(';
		foreach ( $wic_query->result as $result ) {
			$id_list .= $result->ID . ',';		
		} 	
  		$id_list = trim($id_list, ',') . ')';
   	
   	// create a new WIC access object and search for the id's
  		$wic_query2 = WIC_DB_Access_Factory::make_a_db_access_object( $wic_query->entity );
		$wic_query2->list_by_id ( $id_list ); 

		// check current user so can highlight assigned cases
		$current_user_id = get_current_user_id();
		
		// loop through the rows and output a list item for each
		foreach ( $wic_query2->result as $row_array ) {

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			// add special row class to reflect case assigned status
			if ( "0" < $row_array->case_status ) {
				$row_class .= " case-open ";	
				$review_date = new DateTime ( $row_array->case_review_date );
				$today = new DateTime( current_time ( 'Y-m-d') );
				$interval = date_diff ( $review_date, $today );
				if ( '' == $row_array->case_review_date || 0 == $interval->invert ) {
					$row_class .= " overdue ";				
				}
			} 	
			
			// $control_array['id_requested'] =  $wic_query->post->ID;
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . $this->get_custom_field_class ( $field->field_slug, $wic_query->entity ) . '"> ';
							$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		return ( $output );		
	}


  	protected function get_the_buttons( &$wic_query ) { 

		$buttons = '';

		// only show buttons on advanced search constituent result, not in dashboard list
		if ( isset ( $wic_query->search_id ) ) {
			
			// wic-post-export-button
			$download_type_control = WIC_Control_Factory::make_a_control( 'select' );
			$download_type_control->initialize_default_values(  'list', 'wic-post-export-button', '' );
			$buttons = $download_type_control->form_control();			
			// other buttons/controls			
			$buttons .= WIC_List_Parent::make_send_email_to_found_button ( 'email_send', $wic_query->search_id, $wic_query->found_count ); 
			$buttons .= WIC_List_Parent::make_show_map_button ( 'show_map', $wic_query->search_id, $wic_query->found_count ); 
			$buttons .= WIC_List_Parent::back_to_search_form_button ( $wic_query, false );
			$buttons .= WIC_List_Parent::hidden_search_id_control( $wic_query );
			$buttons .= WIC_List_Parent::search_inspection_button( $wic_query );
			// show delete button iff user has required capability
			$required_capability = WIC_Admin_Access::check_required_capability( 'downloads' ); // downloads
			if (current_user_can( $required_capability ) ) {
				$buttons .= $this->constituent_delete_button ( $wic_query );
			}
			$buttons .= WIC_List_Parent::search_name_control ( $wic_query );
		}
		
		return ( $buttons );
	}

	protected function format_message( &$wic_query, $header='' ) {
	
		$found_string = $wic_query->found_count > 1 ? sprintf ( __( 'Found %1$s constituents.', 'wp-issues-crm'), $wic_query->found_count ) :
			__( 'Found one constituent.', 'wp-issues-crm' );	
		$header_message = $header . $found_string;		
		$header_message = WIC_Entity_Advanced_Search::add_blank_rows_message ( $wic_query, $header_message ); // blank search rows disregarded 
		return $header_message;
	}
   
   
	private function constituent_delete_button ( &$wic_query ) {

		$button_args = array (
			'name'	=> 'delete_constituents_button',
			'id'	=> 'delete_constituents_button',
			'type'	=> 'button',
			'value'	=> $wic_query->search_id,
			'button_class'	=> 'wic-form-button wic-top-menu-button ',
			'button_label'	=>	'<span class="dashicons dashicons-trash"></span>',
			'title'	=>	__( 'Open delete constituent dialog', 'wp-issues-crm' ),
		);
		
		$button =  WIC_Form_Parent::create_wic_form_button( $button_args );
		
		$button.= '<div class="list-popup-wrapper">
				<div id="delete_constituent_dialog" title="Permanently delete '. self::constituent_plural_phrase ( $wic_query->found_count ) . '." class="ui-front">' . 
					'<p></p>'  .
					'<p>Type "CONFIRM CONSTITUENT PURGE" (all caps) to confirm permanent delete of found constituents and their emails, addresses, phones and activity records.</p>' .
					'<input id="confirm_constituent_action" name="confirm_constituent_action" placeholder="confirm . . ." value=""/>' .
					'<p><strong>Once in progress, this action cannot be cancelled or undone.</strong></p>
					<div class = "action-ajax-loader">' .
						'<em> . . . working . . .  </em>
						<img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) . '">' . 
					'</div>
				</div>
			</div>';
			
		return $button;

	}
   
   	private static function constituent_plural_phrase ( $count ) {
		return $count > 1 ? "all $count found constituents" : "one found constituent";
		
	}
   
 }	

