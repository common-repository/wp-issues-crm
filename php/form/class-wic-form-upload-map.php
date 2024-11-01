<?php
/*
* class-wic-form-upload-map.php
*
*
*/

class WIC_Form_Upload_Map extends WIC_Form_Upload  {

	protected function format_tab_titles( &$data_array ) {
		return ( WIC_Entity_Upload::format_tab_titles( $data_array['ID']->get_value() ) );	
	}


	// associate form with entity in data dictionary
	protected function get_the_entity() {
		return ( 'upload' );	
	}

	// no buttons in this form, ajax loader comes up as display: none -- available to display in an appropriate place
	protected function get_the_buttons ( &$data_array ) { 
		$buttons =  '<span id = "ajax-loader">' .
			'<img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) .
			'"></span>'; 
			// define the top row of buttons (return a row of wic_form_button s)
		$button_args_main = array(
			'name'						=> 'wic-upload-validate-button',
			'id'						=> 'wic-upload-validate-button',
			'type'						=> 'button',
			'button_label'				=> '<span class="button-highlight">Next:  </span>Validate Map',
		);	

		$buttons = $this->create_wic_form_button ( $button_args_main );

		// see upload-match.js for the on click for this button!
		$button_args_main = array(
			'button_class'				=> 'wic-form-button second-position',
			'name'						=> 'wic-upload-express-button',
			'button_label'				=> '<span class="button-highlight">Alt:  </span><em>Run Express!</em>',
			'title'						=> 'Load all records as new without dup checking, validation or setting of default values -- fully reversible; disabled if activities/issues mapped.',
			'type'						=> 'button',
			'id'						=> 'wic-upload-express-button',
		);	
		$buttons .= $this->create_wic_form_button ( $button_args_main );

	
		$button_args_main = array(
			'name'						=> 'wic_back_to_parse_button',
			'id'						=> 'wic_back_to_parse_button',
			'type'						=> 'button',
			'button_class'				=> 'wic-form-button second-position',
			'button_label'				=> 'Back:  Redo Parse',
		);	

		$buttons .= $this->create_wic_form_button ( $button_args_main );
	
	
		return ( $buttons ); 
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		$formatted_message =  sprintf ( __( 'Map fields from %s to WP Issues CRM fields. ' , 'wp-issues-crm' ), $data_array['upload_file']->get_value() )  . $message;
		return ( $formatted_message );
	}

	// legends
	protected function get_the_legends( $sql = '' ) {}
	// group screen
	protected function group_screen( $group ) { 
		return ( 'upload_parameters' == $group->group_slug  || 'mappable' == $group->group_slug ) ;	
	}
	
	// special use existing groups as special within this form
	protected function group_special ( $group ) { 
			return ( 'upload_parameters' == $group || 'mappable' == $group );
	}
	
	// function to be called for special group -- brute force layout of droppables for map
	protected function group_special_upload_parameters ( &$doa ) { 

		$output					 = '';
		$constituent_identifiers = '' ;
		$address_parts 			 = '';
		$address_main			 = '' ;
		$contact_info			 = '' ;			
		$demographic_info  		 = '' ;
		$type_codes				 = '' ;
		$status_info			 = '' ;
		$registration_info		 = '' ;
		$custom_info			 = '' ;
		$activity_info 			 = '' ;
		// get uploadable fields		
		global $wic_db_dictionary;
		$fields_array = $wic_db_dictionary->get_uploadable_fields (); 

		// assumes fields sorted by entity -- this is how they come from dictionary, but future edits could change
				
		foreach ( $fields_array as $field ) {
			$show_field = $field['label'] > '' ? $field['label'] : $field['field'];
			// hard-coding better labels for uploading activities
			$relabeling_array = array ( 
				'Issue' => 'Issue Number ( Post ID )',
				'Title' => 'Issue Title ( Post Title )',
				'Text'  => 'Issue Text ( Post Content )'
			);
			$show_field = isset ( $relabeling_array[$show_field] ) ? $relabeling_array[$show_field] : $show_field ;
			
			$unique_identifier = '___' . $field['entity'] . '___' . $field['field']; // three underscore characters before each slug
			$droppable_div = '<div class="wic-droppable" id = "wic-droppable' . $unique_identifier  . '">' 	. $show_field . '</div>';
			if ( $field['order'] < 44 ) {
				$constituent_identifiers .= $droppable_div;	
			} elseif ( $field['order'] < 52 ) {
				$address_parts .= $droppable_div;
			} elseif ( $field['order'] < 90 ) {
				$address_main .= $droppable_div;
			} elseif ( $field['order'] < 110 ) {	
				$contact_info .= $droppable_div;
			} elseif ( $field['order'] < 140 ) {
				$demographic_info .= $droppable_div;			
			} elseif ( $field['order'] < 160 ) {
				$type_codes .= $droppable_div;
			} elseif ( $field['order'] < 200 ) {
				$status_info .= $droppable_div;
			} elseif ( $field['order'] < 400 ) {
				$registration_info .= $droppable_div;
			} elseif ( $field['order'] < 1000 ) { // custom fields will be numbered as 800 + custom field number for upload display
				$custom_info .= $droppable_div;
			} else {
				$activity_info .= $droppable_div;		
			}
		}
		
		// assemble output
		$output .= '<div id = "wic-droppable-column">';
		$output .= '<h3>' . __( 'Drag upload fields into WP Issues CRM fields to map them <a href="http://wp-issues-crm.com/?page_id=213" target = "_blank">(see tips)</a>', 'wp-issue-crm' ) . '</h3>';
		$output .= '<div id = "constituent-targets"><h4>' . __( 'Identity' , 'wp-issues-crm' ) . '</h4>' . $constituent_identifiers . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "address-targets"><h4>' . __( 'Address' , 'wp-issues-crm' ) . '</h4>' . $address_main . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "address-part-targets"><h4>' . __( 'Street Address parts (if supplied will be combined to form Street Address -- <a href="http://wp-issues-crm.com/?page_id=213" target = "_blank">see tips)</a>' , 'wp-issues-crm' ) . '</h4>' . $address_parts . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "contact-targets"><h4>' . __( 'Contact info' , 'wp-issues-crm' ) . '</h4>' . $contact_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "demo-targets"><h4>' . __( 'Personal info' , 'wp-issues-crm' ) . '</h4>' . $demographic_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "type-targets"><h4>' . __( 'Case status' , 'wp-issues-crm' ) . '</h4>' . $status_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "case-targets"><h4>' . __( 'Type codes' , 'wp-issues-crm' ) . '</h4>' . $type_codes . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "registration-targets"><h4>' . __( 'Registration codes' , 'wp-issues-crm' ) . '</h4>' . $registration_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		if ( $custom_info > '' ) {
			$output .= '<div id = "custom-targets"><h4>' . __( 'Custom fields' , 'wp-issues-crm' ) . '</h4>' . $custom_info . '</div>';		
			$output .= '<div class = "horbar-clear-fix"></div>';
		}
		$output .= '<div id = "activity-targets"><h4>' . __( 'Activity fields' , 'wp-issues-crm' ) . '</h4>'. $activity_info . '</div>';
		$output .= '</div>';

		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= $doa['ID']->form_control();
		$output .= $doa['serialized_upload_parameters']->form_control();		


		return $output; 					
	}
	
	protected function group_special_mappable ( &$doa ) {
		
		$output = ''; 
		
				// list fields from upload file to be matched
		$output .= '<div id = "wic-draggable-column-wrapper">';
		$output .= '<h3>' . __( 'Upload fields to map.', 'wp-issue-crm' ) . '</h3>';
		$output .= '<div id = "wic-draggable-column">';
		
		// get the column map array				
		$column_map = json_decode ( $doa['serialized_column_map']->get_value() );
 
		// get an array of sample data to use as titles for the column divs
		$upload_parameters = json_decode ( $doa['serialized_upload_parameters']->get_value() );

		$staging_table_name = $upload_parameters->staging_table_name;
		$column_titles_as_samples = WIC_Entity_Upload_Map::get_sample_data ( $staging_table_name ); 

		foreach ( $column_map as $key=>$value ) {
			if ( $key != 'CONSTRUCTED_STREET_ADDRESS' ) {
				$output .= '<div id = "wic-draggable___' . $key . '" class="wic-draggable" title = "' . $column_titles_as_samples[$key] . '">' . $key . '</div>'; // column names are already unique
			}
		}
		$output .= '</div></div>';
		
		// put the upload status in here for reference (could be anywhere)
		$output .= '<div id="initial-upload-status">' . $doa['upload_status']->get_value() . '</div>';
		
		return $output;
		
	}
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {}
	 	
}