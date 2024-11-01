<?php
/*
*
*	class-wic-entity-issue-open-metabox.php
*
*/

Class WIC_Entity_Issue_Open_Metabox {

	const WIC_METAKEY = 'wic_data_wic_live_issue';

	public function __construct() {
		add_action('add_meta_boxes', array ( $this, 'wic_call_live_issue_meta_box' ), 10, 2);
		add_action('save_post', array( $this, 'wic_save_live_issue_meta_box' ), 10, 2);
	}
	
	/* following http://www.wproots.com/complex-meta-boxes-in-wordpress/ */	
	function wic_call_live_issue_meta_box($post_type, $post)
	{
	   add_meta_box(
	       'wic_live_issue_setting_box',
	       __( 'Open Issue for WP Issues CRM?', 'wp-issues-crm' ),
	       array( $this, 'wic_live_issue_meta_box' ),
	       'post',
	       'side',
	       'high'
	   );
	}
	
	
	function wic_live_issue_meta_box( $post, $args ) {
		
		global $wic_form_utilities;
		global $wic_db_dictionary;	
		global $post;	
		
	   wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, true ); 
	
      $wic_live_issue_options =  $wic_db_dictionary->lookup_option_values( 'wic_live_issue_options' );
	   
		$value = ( null !== get_post_meta($post->ID, self::WIC_METAKEY, true) ) ? esc_attr( get_post_meta($post->ID, self::WIC_METAKEY, true)) : '';	   
	   
		$args = array (
			'field_slug' => self::WIC_METAKEY,
			'field_label'	=>	'',
			'label_class'	=>	'',
			'input_class'	=>	'',
			'field_slug_css' 	=>	'',
			'value'	=> $value,
			'field_label_suffix'	=> '',
			'option_array' => $wic_live_issue_options, 
			'hidden' => 0,
		);	  

		echo '<p>' . WIC_Control_Select::create_control ( $args) . '</p>';
		
		if ( 'private' == $post->post_status || 'publish' == $post->post_status ) {
			// if post has been saved in a post status that WP Issues CRM sees, offer a link to the post
			echo '<a href=" '.
					admin_url ( 'admin.php?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . 
				 		$post->ID ) .
				'">' . __( 'View Issue in WP Issues CRM', 'wp-issues-crm' ) . '</a>'	;
		}			

	}
	
	public static function wic_save_live_issue_meta_box($post_id, $post) { 
	   if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
	   	return;
		}
	       
	   if( ! current_user_can( 'edit_post', $post_id ) ) {
	           return;
	   }
			// using the same nonce identifier here as in main wic form functions -- this allows the button 
			// action to go to view an issue be accepted by the nonce checking in the main form (capability will still be tested);
			// however, don't want this update to always fire when saving issues -- processing will pass through 
			// here because it is hooked to save post and save post fires when we save issues.
			// so, to prevent this, test whether a form button was submitted and if so, do not act
  			if ( isset( $_POST['wp_issues_crm_post_form_nonce_field'] ) &&  ! isset ( $_POST['wic_form_button'] ) && 
				check_admin_referer( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field')) { 
				// the convention in wp_issues_crm is store no record for a blank value
				if ( '' < $_POST[self::WIC_METAKEY] ) {
           		update_post_meta($post_id, self::WIC_METAKEY, $_POST[self::WIC_METAKEY] );
           	} else {
           		// will just return false if no record
           		delete_post_meta($post_id, self::WIC_METAKEY, $_POST[self::WIC_METAKEY] );
           	}
		   } else {}
   
	   return;
	}

	public function title_callback( &$next_form_output ) {
		
		// for title, use group email if have it, otherwise use individual email 
		$title = isset ( $next_form_output['post_title'] ) ? $next_form_output['post_title'] : 'untitled';  
		
		return  ( $title );
	}
	
}

