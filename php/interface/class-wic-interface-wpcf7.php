<?php
/*
*
*	wic-interface-wpcf7.php
*
*/

class WIC_Interface_WPCF7 extends WIC_Interface_Parent {

	const INTERFACE_TYPE = 'wpcf7';

	public function activate_interface() {
		add_filter ( 'wpcf7_posted_data', array ( $this, 'load_posted_data'), 9999, 1 );
		add_action ( 'wpcf7_mail_sent', array ( $this, 'wpcf7_mail_sent'), 9999, 2 );
		add_action ( 'wpcf7_mail_failed', array ( $this, 'wpcf7_mail_failed'), 9999, 2 );			
	}

	// in this case, a filter, passing $posted_data through;
	public function load_posted_data ( $posted_data, $form_data = array() ) {
		$current_form = $posted_data['_wpcf7'];
		// if listening to this form . . .
		if ( in_array( $current_form, $this->identifiers ) ) {
			// set current_form for consistency check
			$this->current_form = $current_form;			
			$this->posted_data = array();
			// drop the wpcf7 parameters from the post_data
			foreach ( $posted_data as $key => $value ) {
				if ( '_wpcf7' != substr( $key, 0, 6 ) ) {
					$this->posted_data[$key] = $value;
				} 
			}
		}
		// sending $posted_data on untouched in the filter chain
		return $posted_data;
	} 

	public function wpcf7_mail_sent ( $contact_form ) {
		$this->check_transaction_consistency ( $contact_form, 'sent' );
	}

	public function wpcf7_mail_failed ( $contact_form ) {
		$this->check_transaction_consistency ( $contact_form, 'failed' );
	}

	private function check_transaction_consistency ( $contact_form, $mail_status ) {
		// if no current_form set, do nothing 
		if ( $this->current_form ) {
			// belt and suspenders check for transaction consistency
			if ( WP_DEBUG && $contact_form->id() != $this->current_form ) {
				error_log ( sprintf ( "Inconsistency between post form ID ( %s ) and mail form ID ( %s ) after mail %s.", 
					$this->current_form, $contact_form->id(), $mail_status ) ); 
				return;
			} else {
				$this->do_interface();
			}
		}
	}

	public static function get_field_list ( $form_id  ) {
		$manager = WPCF7_FormTagsManager::get_instance();
		$post_object = get_post( $form_id );
		$form = $post_object->post_content;
		$form = $manager->replace_all( $form );
		$tags = $manager->get_scanned_tags();
		$field_list = array();
		foreach ( $tags as $tag ) {
			if ( trim ( $tag['name'] > '' ) ) {
				$field_list[] = array ( $tag['name'], $tag['name'] ); // tag, label, but not extract labels from CF7 Forms;
			}
		}
		return $field_list; 	
	}
	
}