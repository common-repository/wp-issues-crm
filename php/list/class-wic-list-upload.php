<?php
/*
* class-wic-list-upload.php
*
*
*/ 

class WIC_List_Upload extends WIC_List_Parent {
	/*
	*
	*
	*
	*/

	public function format_entity_list( &$wic_query, $header ) { 
	// set up slimmer form with no headers
		$output = '<div id="wic-post-list"><form id="wic_constituent_list_form" method="POST">';
			$output .= $this->set_up_rows ( $wic_query, true ); 
			$output .= 	wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, false ) .
		'</form></div>'; 
		return $output;
   } // close function


	protected function format_message( &$wic_query, $header = '' ) {}

	protected function get_the_buttons ( &$wic_query ) {}
	
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
		
		// loop through the rows and output a list item for each
		foreach ( $wic_query2->result as $row_array ) {

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			// $control_array['id_requested'] =  $wic_query->post->ID;
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && 'purged' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				

			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'go_to_current_upload_stage',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> $row,
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		return ( $output );		
	}
	
	
 }	

