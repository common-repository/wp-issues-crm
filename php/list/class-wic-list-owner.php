<?php
/*
* class-wic-list-owner.php
*
*
*/ 

class WIC_List_Owner extends WIC_List_Parent {
	/*
	*
	*/
	protected function format_rows( &$wic_query, &$fields ) {

		// handle null list
		if ( ! $wic_query->found_count ) {
			return '<p><em> . . . empty list . . .</em></>';
		}

		$output = '';
		$line_count = 1;

		// loop through the rows and output a list item for each

		foreach ( $wic_query->result as $row_object ) {

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			$row_class .= ( BLOG_ID_CURRENT_SITE == $row_object->ID  ? ' top-blog ' : '' );

			$temp_id = '';
			$row .= ( '<ul class = "wic-post-list-line ' . $row_class . '">' );			
				foreach ( $wic_query->fields as $field ) {
					switch ( $field->field_slug )  {
						case 'ID':
							$temp_id = $row_object->{$field->field_slug};
							continue 2;
						case 'siteurl': 
							$row_content =  $row_object->{$field->field_slug} . ' (' . $temp_id .')';
							break;
						case 'wic_owner_type':
							$row_content = WIC_Entity_Owner::get_owner_type_label( $row_object->{$field->field_slug}  );
							break;
						default: 
							$row_content = $row_object->{$field->field_slug};
							break;
					
					}					
					$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
						$row .=  $row_content;
					$row .= '</li>';			
				}
			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'		=> $row_object->ID,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		
		return ( $output );	
			
	}


	protected function format_message( &$wic_query, $header = '' ) {
		if ( ! $wic_query->found_count ) {
			$header_message = 'When additional sites are configured, they will appear in this list.';
		} else {
			$header_message = sprintf ( __( 'Found %1$s Sites.', 'wp-issues-crm'), $wic_query->found_count );
		}		
		return $header_message;
	}

	// no buttons
	protected function get_the_buttons ( &$wic_query ) {}
 }	

