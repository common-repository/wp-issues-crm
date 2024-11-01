<?php
/*
* class-wic-list-issue.php
*
*
*/ 

class WIC_List_Issue extends WIC_List_Parent {
	/*
	* return from wp_query actually has the full post content already, so not two-stepping through lists
	*
	*/

protected function format_rows( &$wic_query, &$fields ) {

		$output = '';
		$line_count = 1;

		// check current user so can highlight assigned cases
		$current_user_id = get_current_user_id();

		foreach ( $wic_query->result as $row_array ) {

			$row= '';
			$line_count++;
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			if ( 'open' == $row_array->follow_up_status ) {
				$row_class .= " case-open ";
				if ( '' == $row_array->review_date ) {	
					$review_date = new DateTime ( '1900-01-01' );
				} else {
					$review_date = new DateTime ( $row_array->review_date );					
				}
				$today = new DateTime( current_time ( 'Y-m-d') );
				$interval = date_diff ( $review_date, $today );
				if ( 0 == $interval->invert ) {
					$row_class .= " overdue ";				
				}
			}	

			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) { 
					if ( 'ID' != $field->field_slug && 0 < $field->listing_order ) {
						if ( 'post_category' == $field->field_slug ) {
							$display_value =  esc_html( WIC_Entity_Issue::get_post_categories( $row_array->ID ) );		
						} else {
							// eliminated closed option value in version 3.5, but some issues may have this value set 
							if ( 'wic_live_issue' == $field->field_slug && 'closed' == $row_array->{$field->field_slug} ) {
								 $row_array->{$field->field_slug} = '';
							}
							$display_value = $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;		
						}
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							$row .=  $display_value ;
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
	} // close function 

	protected function format_message( &$wic_query, $header='' ) {
		return $header . sprintf ( __( 'Found %1$s issues with activities meeting activity/constituent/issue search criteria.', 'wp-issues-crm'), $wic_query->found_count );		
	}
 
   // the top row of buttons over the list -- down load button and change search criteria button
  	protected function get_the_buttons( &$wic_query ) { 
		$user_id = get_current_user_id();
		$buttons = '';

		if ( isset ( $wic_query->search_id ) ) { 
			$buttons .= WIC_List_Parent::back_to_search_form_button ( $wic_query, true );
			$buttons .= WIC_List_Parent::hidden_search_id_control( $wic_query );
			$buttons .= WIC_List_Parent::search_inspection_button( $wic_query );
			$buttons .= WIC_List_Parent::search_name_control ( $wic_query );
		}
		
		return ( $buttons );
	}
 
 
 }	

