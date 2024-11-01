<?php
/*
*
*  class-wic-form-data-dictionary.php
*
*/

class WIC_Form_Data_Dictionary extends WIC_Form_Parent  {

	// no header tabs
	


	// define the top row of buttons 
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons ( $data_array ) .  '<a href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-fields">' . __( 'Back to Fields List', 'wp-issues-crm' ) . '</a>');
	
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		return ( $this->get_the_verb( $data_array ) . ' ' . __('Custom Field. ', 'wp-issues-crm') . $message );
	}

	protected function group_special( $group_slug ) {
		return ( 'current_field_config' == $group_slug );
	}

	protected function group_special_current_field_config () {

		// get form fields
		$form_fields = WIC_DB_Access_Dictionary::get_current_customizable_form_field_layout( 'constituent' );

				
		//layout table 		 
		$output = '<table class="wp-issues-crm-stats"><tr>' .
			'<th class = "wic-statistic-text">' . __( 'Screen Group', 'wp-issues-crm' ) . '</th>' .
			'<th class = "wic-statistic-text">' . __( 'Visible Name of Field', 'wp-issues-crm' ) . '</th>'	.	
			'<th class = "wic-statistic">' . __( 'Order', 'wp-issues-crm' ) . '</th>'	.								
			'<th class = "wic-statistic-text">' . __( 'System Name of Group', 'wp-issues-crm' ) . '</th>'	.								
			'<th class = "wic-statistic-text">' . __( 'System Name of Field', 'wp-issues-crm' ) . '</th>'	.								
		'</tr>';
		
		// create rows for table
		foreach ( $form_fields as $row ) { 
			$output .= '<tr class="field-table-row">' .
			'<td class = "wic-statistic-table-name field-table-field-group">' . $row->group_label . '</td>' .
			'<td class = "wic-statistic-text field-table-field-label" >' . $row->field_label . '</td>' .
			'<td class = "wic-statistic field-table-field-order" >' . $row->field_order . '</td>' .
			'<td class = "wic-statistic-text field-table-group-slug" >' . $row->group_slug . '</td>' .
			'<td class = "wic-statistic-text field-table-field-slug" >' . $row->field_slug . '</td>' .
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