<?php
/*
* class-wic-list-external.php
*
*
*/ 

class WIC_List_External extends WIC_List_Constituent {
	/*
	*
	*
	*
	*/

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

			// add special row class to reflect case assigned status
			if ( ! $row_array->enabled ) {
				$row_class .= " option-group-disabled ";
			}			
			
			// $control_array['id_requested'] =  $wic_query->post->ID;
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							// special handling to look up full name of interface form
							if ( 'external_identifier' == $field->field_slug ) {
								$row .= WIC_Entity_External::list_interface_title ( $row_array->external_type, $row_array->external_identifier );
							} else {
								$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} );
							}
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


	protected function format_message( &$wic_query, $header = '' ) {
		$header_message = sprintf ( __( 'Found %1$s External Interfaces.', 'wp-issues-crm'), $wic_query->found_count );		
		return $header_message;
	}

	protected function get_the_buttons ( &$wic_query ) {

		$buttons =  '<div id = "wic-list-button-row">'; 
		
			$button_args_main = array(
				'entity_requested'			=> 'external', // entity_requested is not processed, since whole page is for option_group
				'action_requested'			=> 'new_blank_form',
				'button_label'					=> __('Add New', 'wp-issues-crm')
			);	
			$buttons .= WIC_Form_Parent::create_wic_form_button ( $button_args_main );

		$buttons .= '</div>';

		return $buttons;
		
	}
 }	

