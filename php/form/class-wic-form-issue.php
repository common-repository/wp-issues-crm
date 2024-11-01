<?php
/*
*
*  class-form-issue-update.php
*
*/

class WIC_Form_Issue extends WIC_Form_Parent  {

	// no header tabs
	


	// define form buttons
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons ( $data_array ) .  $this->get_the_wp_link ( $data_array ) );		
	}
	
	private function get_the_wp_link ( &$data_array ) {
		$link =  
				( isset ( $data_array['ID'] ) && $data_array['ID']->get_value()  ) 
			?
				(
				'<a href="' . site_url() . '/wp-admin/post.php?post=' . $data_array['ID']->get_value() .'&action=edit">' .
									__( 'Edit post in Wordpress editor.', 'wp-issues-crm' ) .
				'</a>' 
				)
			:
				(
				'<a href="' . site_url() . '/wp-admin/post-new.php">' .
									__( 'New Post in Wordpress editor.', 'wp-issues-crm' ) .
				'</a>'
				)
			;	
		return ( $link );	
	}
	
	
	// define form message
	protected function format_message ( &$data_array, $message ) {
		$title = $this->format_name_for_title ( $data_array );
		return ( $this->get_the_verb ( $data_array ) . ' ' . ( $title ? $title :  __( 'Issue/Post', 'wp_issues-crm' ) )  . '. ' . $message );
	}

	// overriding the parent function here to make special handling for public posts
	protected function the_controls ( $fields, &$data_array ) {
		// determine whether current issues is a public post
		$public_post = false;
		if ( isset ( $data_array['post_status'] ) ) {
			if ( 'publish' == $data_array['post_status']->get_value() ) {
				$public_post = true;
			}
		}
		// prepare controls normally but with an exception for the post content in a public post
		$controls_output = '';
		foreach ( $fields as $field ) { 
			if ( $field == 'post_content' && $public_post) { 
				// show as public posts as text output, but carry a hidden text area to preserve content on save
				$controls_output  .= '<div id="wic-post-content-visible">' . 
						/*
						* note that to be a public post, must have been saved as a public post in the Wordpress editor
						* yet, editor may allow scripts to be saved -- strip any head,style,script tag enclosed material
						*/
						WIC_DB_Email_Message_Object::strip_html_head( apply_filters ( 'the_content', $data_array['post_content']->get_value() ) ). 
					'</div>';
				$textarea_control_args = array (
					'readonly' 	=> true,
					'hidden'		=> true, // will not work in older browsers, so make it tolerably well formatted with css
					'field_label' => '',
					'label_class' => '',
					'field_slug' => 'post_content',
					'input_class' => 'wic-post-content-hidden',
					'field_slug_css' => '',
					'placeholder' =>'',
					'value' => $data_array['post_content']->get_value(), // will be escaped with esc_textarea
				);
				// note that text area control only does input sanitization of strip slashes
				$control = WIC_Control_Textarea::create_control ( $textarea_control_args ); 
			} else {
				$control = $this->get_the_formatted_control ( $data_array[$field] );			
			}
			$controls_output .= '<div class = "wic-control" id = "wic-control-' . str_replace( '_', '-' , $field ) . '">' . $control . '</div>';
		} 	
		return ( $controls_output );
		
	}

	protected function format_name_for_title ( &$data_array ) {
		$title = $data_array['post_title']->get_value();
		return  ( $title );
	}

	protected function pre_button_messaging( &$data_array ) {}	
	
	// special group handling for the comment group
	protected function group_special ( $group ) {
		return 'activity' == $group;	
	}
	
	// function to be called for special group
	protected function group_special_activity ( &$doa ) {
		return WIC_Form_Activity::create_wic_activity_area();
	}	

	protected function post_form_hook ( &$data_array )  {
		echo '<div id="hidden-blank-activity-form"></div>' ; 	
	}

	// hooks not implemented
	protected function supplemental_attributes() {}

}