<?php
/*
* class-wic-list-parent.php
*
* lists entities (posts or WIC entities) passed as query 
*
*/ 

abstract class WIC_List_Parent {


	// header message, e.g., for count	
	protected abstract function format_message( &$wic_query, $header = '' ); // $header is text that will lead the message.
	// actual row content
	protected abstract function format_rows ( &$wic_query, &$fields );
    // the top row of buttons over the list -- down load button and change search criteria button
  	abstract protected function get_the_buttons ( &$wic_query );	

	/*
	*
	* main function -- takes query result and sets up a list each row of which is a button
	*
	*/	
	public function format_entity_list( &$wic_query, $header ) { 

  		// set up form
		$output = '<div id="wic-post-list"><form id="wic_constituent_list_form" method="POST">';

		$message = $this->format_message ( $wic_query, $header ); 
		$output .= '<div id="post-form-message-box" class = "wic-form-routine-guidance" >' . esc_html( $message ) . '</div>';
		$output .= $this->get_the_buttons( $wic_query );	
		$output .= $this->set_up_rows ( $wic_query );
		$output .= 	wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, false ) .
		'</form></div>'; 
		$output .= WIC_List_Parent::list_terminator ( $wic_query );
		
		return $output;
	} // close function

	// this list terminator sits outide the scrollable list -- is updated at end of loading by js.
	public static function list_terminator( &$wic_query ) {
		if ( isset ( $wic_query->search_id ) )  {
			if ( $wic_query->found_count > $wic_query->retrieve_limit ) {
				return	'<p id = "list_terminator">' .
					'Scroll list to continue loading . . .  <span id="list-ajax-scroll" ><img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) . '"></span>' .
				'</p>';
			} else {
				return '<p id="list_terminator"><em>List fully loaded -- all '. $wic_query->found_count .' items viewable.</em></p>';
			}	
		} else {
			return '';
		}	
	}

	protected function set_up_rows ( &$wic_query, $do_header = true ) {
	
		$output = '';	
	
		// set up args for use in row buttons -- each row is a button
  		$list_button_args = array(
			'entity_requested'		=> $wic_query->entity,
			'action_requested'		=> 'id_search',
		);	


		// prepare the list fields for header set up and list formatting
		global $wic_db_dictionary;
  		$fields =  $wic_db_dictionary->get_list_fields_for_entity( $wic_query->entity );
	
		// filter to give lister ability to suppress header elements
		$fields = $this->list_entity_field_filter ( $fields, $wic_query );	
	
		// query entity used in class definition for most elements to support alternative search log styling
		$output .= '<ul id="search_item_list" class = "wic-post-list">';  				// open ul for the whole list
		if ( $do_header ) {
			$output .=
			'<li class = "pl-odd ' . $wic_query->entity  .'">' .	// header is a list item with a ul within it
				// insert spacer for use with search log
				'<div class = "wic-post-list-headers-spacer ' . $wic_query->entity  .'"></div>' . 				
				'<div class = "wic-post-list-headers ' . $wic_query->entity  .'">' . '
					<ul class = "wic-post-list-headers pl-odd ' . $wic_query->entity  .'">';				
						foreach ( $fields as $field ) {
							if ( $field->field_slug != 'ID' && $field->listing_order > 0 ) {
								$output .= '<li class = "wic-post-list-header pl-' . $wic_query->entity . '-' . $field->field_slug . $this-> get_custom_field_class ( $field->field_slug, $wic_query->entity ) . '">' . $field->field_label . '</li>';
							}			
						}
					$output .= '</ul>
				</div>' . // styling wrapper for the ul (used only in search log case)
			'</li>'; // header complete
		}
		$output .= $this->format_rows( $wic_query, $fields ); // format list item rows from child class	
		$output .= '</ul>'; // close ul for the whole list

		return $output;
	
	}

	public function rows_only ( &$wic_query ) {
		global $wic_db_dictionary;
  		$fields =  $wic_db_dictionary->get_list_fields_for_entity( $wic_query->entity );
		// filter to give lister ability to suppress header elements
		$fields = $this->list_entity_field_filter ( $fields, $wic_query );		
		return $this->format_rows ( $wic_query, $fields ); 
	}


	// default is do nothing
	protected function list_entity_field_filter ( $fields, &$wic_query ) {
		return ( $fields );	
	}
   
   // defines standard lookup hierarchy for formats (mirrors look up for dropdowns)
   protected function format_item ( $entity, $list_formatter, $value ) {
   	
		global $wic_db_dictionary;
   	
		// prepare to look for format in a sequence of possible sources
   		$class_name = 'WIC_Entity_' . $entity;
  	 	$function_class = 'WIC_Function_Utilities';

		// first point to an option array with list_formatter, in which case, just lookup and return the formatted value
		$option_array = $wic_db_dictionary->lookup_option_values( $list_formatter );

		if ( $option_array > '' ) {
			$display_value = WIC_Function_Utilities::value_label_lookup ( $value, $option_array );
	  	// second look for a method in the entity class (method must do own escaping of html b/c might add legit html)
		} elseif ( method_exists ( $class_name, $list_formatter ) ) { 	
			$display_value = $class_name::$list_formatter ( $value ) ;
		// third look for method in in the utility class 
		} elseif ( method_exists ( $function_class, $list_formatter ) ) {
			$display_value = $function_class::$list_formatter( $value );			
		// fourth look for a function in the global name space 
		} elseif ( function_exists ( $list_formatter ) ) {
			$display_value = $list_formatter( $value );
		// otherwise just display the value after esc_html 
		} else { 
			$display_value =  $value ;		
		}   
		return ( $display_value );
   }

	public static function make_send_email_to_found_button ( $type, $search_id, $constituent_count ) {
	
		// show no button if no constituents
		if ( 0 == $constituent_count ) { 
			return;
		}
		
		// show no button if does not have required capability
		$required_capability = WIC_Admin_Access::check_required_capability( 'list_send' ); 
		if ( ! current_user_can( $required_capability ) ) {
			return;
		}
	
		$button_args = array (
			'title'				=>  __( 'Send an email to found constituents.', 'wp-issues-crm' ),
			'name'				=> 'send_email_to_found_button',
			'id'				=> 'send_email_to_found_button',
			'type'				=> 'button',
			'value'				=> $type . ','  . $search_id . ',' . $constituent_count,
			'button_class'		=> 'wic-form-button email-compose-button',
			'button_label'		=>	'<span class="dashicons dashicons-email-alt send-button"></span>',
		);



		return  WIC_Form_Parent::create_wic_form_button( $button_args );
				
	}	


	public static function make_show_map_button ( $type, $search_id, $constituent_count ) {
	
		// show no button if no constituents
		if ( 0 == $constituent_count ) { 
			return;
		}

		// show no button if no google maps api
		if ( ! WIC_Entity_Geocode::get_google_maps_api_key() ) { 
			return;
		}

	
		$button_args = array (
			'title'				=>  __( 'Map found consituents.', 'wp-issues-crm' ),
			'name'				=> 'show_map_button',
			'id'				=> 'show_map_button',
			'type'				=> 'button',
			'value'				=> $type . ','  . $search_id . ',' . $constituent_count,
			'button_class'		=> 'wic-form-button show-map-button',
			'button_label'		=>'<span class="dashicons dashicons-location-alt"></span>',
		);

		return  WIC_Form_Parent::create_wic_form_button( $button_args );
				
	}



	public static function back_to_search_form_button( &$wic_query, $first_position ) {
		$button_args = array (
			'entity_requested'	=> 'search_log',
			'action_requested'	=> 'id_search_to_form', // will display form with search criteria
			'id_requested'	=> $wic_query->search_id,
			'id'			=> 'id_search_to_form',
			'button_class'	=> 'wic-form-button wic-top-menu-button ' . ( $first_position ? ' in-first-position ': '' ),
			'button_label'	=>	'<span class="dashicons dashicons-update"></span>',
			'title'	=>	__( 'Change search criteria', 'wp-issues-crm' ),
		);
		return WIC_Form_Parent::create_wic_form_button( $button_args );
	}

	public static function search_inspection_button ( &$wic_query ) {

		// inspection button -- show sql, etc.
		$inspection_text = 
		'<h4>Current Query Summary:</h4>' .
		'<p><b>Search SQL:</b></p><code>' . $wic_query->sql . '</code>' .
		'<p><b>Outcome:</b></p><code>' . ( $wic_query->outcome ? 'OK' : $wic_query->explanation ) . '</code>' .
		'<p><b>Found Count:</b></p><code>' . $wic_query->found_count . '</code>' ;
		$button_args = array (
			'name' 	=> 'search_inspection_button',
			'id'	=> 'search_inspection_button',
			'type'	=> 'button',
			'value' => $inspection_text,
			'button_class' => 'wic-form-button wic-top-menu-button',
			'button_label' => '<span class="dashicons dashicons-info"></span>',
			'title'	=>	__( 'View search definition', 'wp-issues-crm' ),
		);
		return WIC_Form_Parent::create_wic_form_button( $button_args );
	}

	// hidden parameter controls -- search_id . . .
	public static function hidden_search_id_control ( &$wic_query ) {
		$special_list_controls = array( 'search_id', 'list_page_offset', 'found_count', 'retrieve_limit' );
		$control_string = '';
		foreach ( $special_list_controls as $list_control_name ) {
			$control = WIC_Control_Factory::make_a_control( 'text' );
			$control->initialize_default_values ( 'list', $list_control_name, '' );
			$control->set_value( $wic_query->$list_control_name );
			$control_string .= $control->form_control();
		};
		return ( $control_string );
	}

	public static function search_name_control ( &$wic_query ) {
		$share_name_control = WIC_Control_Factory::make_a_control( 'text' );
		$share_name_control->initialize_default_values(  'list', 'share_name', '' );
		$share_name_control->set_value( isset ( $wic_query->share_name ) ? $wic_query->share_name : '' );
		return ( $share_name_control->form_control() . 	
				'<span id = "share_name_save_flag">' .
					'Saving share name . . . <img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) .
				'"></span>' );		
	}
	
	// this function really only used for entity constituent, but since constituent uses generic header, add it here
	protected function get_custom_field_class( $field_slug, $entity) {
		return ( 'custom_field_' == substr ( $field_slug, 0, 13 ) ? ' ' . $entity . '-custom-field '  : '');
	}
	
}	

