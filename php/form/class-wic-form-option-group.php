<?php
/*
*
*  class-wic-form-option-group.php
*
*/

class WIC_Form_Option_Group extends WIC_Form_Parent  {
	
	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons ( $data_array ) . '<a href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-options">' . __( 'Back to Options List', 'wp-issues-crm' ) . '</a>');
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		return ( $this->get_the_verb( $data_array ) . __(' Option Group. ', 'wp-issues-crm') . $message );
	}

	protected function group_special( $group_slug ) { 
		return ( 'current_option_group_usage' == $group_slug );
	}

	protected function group_special_current_option_group_usage ( &$data_array ) { 

		// if this is a save form, no output
		if ( ! $data_array['option_group_slug']->get_value() ) {
			return ('');
		}

		// get list of tables and fields using the option
		$fields_using_option = WIC_DB_Access_Dictionary::get_current_fields_using_option_group( $data_array['option_group_slug']->get_value() );
		// for each of them, run a query to get counts -- make a database object so know where to look
		$entity_field_value_count_array = array ();

		if ( is_array ( $fields_using_option ) && count ( $fields_using_option ) > 0 ) {
			foreach ( $fields_using_option as $field_using_option ) {
				if ( WIC_DB_Access_Factory::is_entity_instantiated( $field_using_option->entity_slug ) ) {
					$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( $field_using_option->entity_slug );
				} else {
					continue;
				}
				$value_count_array = $wic_query->get_option_value_counts ( $field_using_option->field_slug );
				if ( is_array ( $value_count_array ) && count ( $value_count_array ) > 0 ) {
					foreach ($value_count_array as $value_count ) {		
						$entity_field_value_count_array[] = array (
							'entity' 		=> $field_using_option->entity_slug,
							'field_slug'	=>	$field_using_option->field_slug,
							'field_label'	=>	$field_using_option->field_label,
							'value'			=> $value_count->field_value,
							'count'			=> $value_count->value_count,  			
						);
					}
				} else {
					$entity_field_value_count_array[] = array (
						'entity' 		=> $field_using_option->entity_slug,
						'field_slug'	=>	$field_using_option->field_slug,
						'field_label'	=>	$field_using_option->field_label,
						'value'			=> 'No Values Found',
						'count'			=> 'N/A',  			
					);
				
				}
			}
		}		
		
			//layout table 		 
		$output = '<table class="wp-issues-crm-stats"><tr>' .
			'<th class = "wic-statistic-text">' . __( 'Table/Entity', 'wp-issues-crm' ) . '</th>' .
			'<th class = "wic-statistic-text">' . __( 'Database Field Name', 'wp-issues-crm' ) . '</th>'	.
			'<th class = "wic-statistic-text">' . __( 'Field Label', 'wp-issues-crm' ) . '</th>'	.		
			'<th class = "wic-statistic-text">' . __( 'Database Value', 'wp-issues-crm' ) . '</th>'	.
			'<th class = "wic-statistic">' . __( 'Count', 'wp-issues-crm' ) . '</th>'	.								
		'</tr>';
		
		// create rows for table
		foreach ( $entity_field_value_count_array as $row ) { 
			$output .= '<tr>' .
			'<td class = "wic-statistic-table-name">' . $row['entity'] . '</td>' .
			'<td class = "wic-statistic-text" >' . $row['field_slug'] . '</td>' .
			'<td class = "wic-statistic-text" >' . $row['field_label'] . '</td>' .
			'<td class = "wic-statistic-text" >' . $row['value'] . '</td>' .			
			'<td class = "wic-statistic" >' . $row['count'] . '</td>' .
			'</tr>';
		} 
		$output .= '</table>';
		
		return ( $output );
			
	} 



	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 

}