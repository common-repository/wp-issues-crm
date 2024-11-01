<?php
/*
* class-wic-form-external-map.php
*
*
*/

class WIC_Form_External_Map extends WIC_Form_Upload  {

	public static function get_form_elements ( $field_list ) {
		return self::input_matchables ( $field_list ) . self::field_droppables();
	}

	// function to be called for special group -- brute force layout of droppables for map
	private static function field_droppables () { 

		$output					 = '';
		$constituent_identifiers = '' ;
		$address_parts 			 = '';
		$address_main			 = '' ;
		$contact_info			 = '' ;			
		$demographic_info  		 = '' ;
		$type_codes				 = '' ;
		$status_info			 = '' ;
		$custom_info			 = '' ;
		$activity_info 			 = '' ;
		// get uploadable fields		
		global $wic_db_dictionary;
		$fields_array = $wic_db_dictionary->get_uploadable_fields (); 

		// assumes fields sorted by entity -- this is how they come from dictionary, but future edits could change
				
		foreach ( $fields_array as $field ) {
			$show_field = $field['label'] > '' ? $field['label'] : $field['field'];
			if ( 'activity_date' == $field['field'] ) {
				$show_field .= ' (defaults to current date)';
			}
			// hard-coding better labels for uploading activities
			$relabeling_array = array ( 
				'Issue' => 'Issue Number ( Post ID )',
				'Title' => 'Issue Title ( Post Title )',
				'Text'  => 'Issue Text ( Post Content )'
			);
			$show_field = isset ( $relabeling_array[$show_field] ) ? $relabeling_array[$show_field] : $show_field ;
			
			$unique_identifier = '___' . $field['entity'] . '___' . $field['field']; // three underscore characters before each slug
			$droppable_div = '<div class="wic-droppable" id = "wic-droppable' . $unique_identifier  . '">' 	. $show_field . '</div>';
			if ( $field['order'] < 44 && 'ID' != $field['field'] ) {
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
			} elseif ( $field['order'] < 800 ) {
				$status_info .= $droppable_div;
			} elseif ( $field['order'] < 1000  ) {
				$custom_info .= $droppable_div;
			} elseif ( 'issue' != $field['field'] ) {
				$activity_info .= $droppable_div;		
			}
		}
		
		// assemble output
		$output .= '<div id="wic-form-sidebar-groups"><div id = "wic-droppable-column">';
		$output .= '<h3>' . __( 'Drag form fields into WP Issues CRM fields to map them', 'wp-issue-crm' ) . '</h3>';
		$output .= '<div id = "constituent-targets"><h4>' . __( 'Identity' , 'wp-issues-crm' ) . '</h4>' . $constituent_identifiers . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "address-targets"><h4>' . __( 'Address' , 'wp-issues-crm' ) . '</h4>' . $address_main . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "contact-targets"><h4>' . __( 'Contact info' , 'wp-issues-crm' ) . '</h4>' . $contact_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "demo-targets"><h4>' . __( 'Personal info' , 'wp-issues-crm' ) . '</h4>' . $demographic_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "case-targets"><h4>' . __( 'Type codes' , 'wp-issues-crm' ) . '</h4>' . $type_codes . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		if ( $custom_info > '' ) {
			$output .= '<div id = "custom-targets"><h4>' . __( 'Custom fields' , 'wp-issues-crm' ) . '</h4>' . $custom_info . '</div>';		
			$output .= '<div class = "horbar-clear-fix"></div>';
		}
		$output .= '<div id = "activity-targets"><h4>' . __( 'Activity fields' , 'wp-issues-crm' ) . '</h4>'. $activity_info . '</div>';
		$output .= '</div></div>';

		$output .= '<div class = "horbar-clear-fix"></div>';

		return $output; 					
	}
	
	private static function input_matchables ( $field_list ) {
		
		$output = ''; 
		
				// list fields from upload file to be matched
		$output .= '<div id="wic-form-main-groups"><div id = "wic-draggable-column-wrapper">';
		$output .= '<h3>' . __( 'Form fields', 'wp-issue-crm' ) . '</h3>';
		$output .= '<div id = "wic-draggable-column">';
 
		foreach ( $field_list as $key ) {
			$output .= '<div id = "wic-draggable___' . $key[0] . '" class="wic-draggable" >' . $key[1] . '</div>'; // column names are already unique
		}
		$output .= '</div></div></div>';
		
		return $output;
		
	}
	 	
}