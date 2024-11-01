<?php
/*
* class-wic-form-settings.php
*
*
*/

class WIC_Form_Email_Settings extends WIC_Form_Parent  {

	
	// note that some of the variables in this form are presented in WIC_Form_Email_Inbox::layout_inbox ( group inbox_options )
	// all variables are swept into js option save function the scope of which is not bounded by the form
	public function prepare_settings_form( &$data_array, $guidance ) { 
		
		global $wic_db_dictionary;	
		ob_start();
		?>
		
		<form id = "<?php echo $this->get_the_form_id(); ?>" <?php $this->supplemental_attributes(); ?> class="wic-post-form" method="POST" autocomplete = "on">

			<?php	
			// set up buffer for all group content and buffer for tabs
			$group_output = '';
			$group_headers = '';

			// setup save options button
			$button_args = array (
				'title'				=>  __( 'Save settings for email processing', 'wp-issues-crm' ),
				'name'				=> 'wic_save_email_settings',
				'type'				=> 'button',
				'button_class'		=> 'wic-form-button wic_save_email_settings',
				'button_label'		=>	'Saved',
			);	
			$save_options_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;

			// go to the data dictionary and get the list of groups for the entity			
			$groups = $this->get_the_groups(); 
	   		foreach ( $groups as $group ) { 
				$group_headers .= '<li class = "wic-form-field-group-' . esc_attr( $group->group_slug  ) . '"><a href="#wic-field-group-' . esc_attr( $group->group_slug  ) . '">' . $group->group_label  . '</a></li>';		
				$group_output .= '<div class = "wic-form-field-group" id = "wic-field-group-' . esc_attr( $group->group_slug  ) . '">';				
			
				$group_output .= '<div id = "wic-inner-field-group-' . esc_attr( $group->group_slug ) . '">';					
				if ( esc_html ( $group->group_legend ) > '' ) {
					$group_output .= '<p class = "wic-form-field-group-legend">' . esc_html ( $group->group_legend )  . '</p>';
				}
				// here is the main content -- either   . . .
				if ( $this->group_special ( $group->group_slug ) ) { 			// if implemented returns true -- run special function to output a group
					$special_function = 'group_special_' . $group->group_slug; 	// must define the special function too 
					$group_output .= $this->$special_function( $data_array );
				} else {	// standard main form logic 	
					$group_fields =  $wic_db_dictionary->get_fields_for_group ( $this->get_the_entity(), $group->group_slug ); 
					$group_output .= $this->the_controls ( $group_fields, $data_array );
					$group_output .= $save_options_button;
				}
				$group_output .= '</div></div>';	
	  		} // close foreach group		
		
			// assemble tabbable output
			echo '<div id = "wic-form-tabbed">'  .
				'<ul>' .
					$group_headers .
				'</ul>' .		
				$group_output .
			'</div>';
		
			// final button group div
			echo '<div class = "wic-form-field-group" id = "bottom-button-group">'; 	
		 		wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, true ); 
				echo $this->get_the_legends();
			echo '</div></form>';								
		
		$this->post_form_hook( $data_array ); 

		return ob_get_clean();
	}

	protected  function group_special_user_sig () {

		$current_user = wp_get_current_user();
		$button_args = array (
			'title'				=>  __( 'Save signature for emails from ', 'wp-issues-crm' ) . $current_user->display_name,
			'name'				=> 'wic_save_current_user_sig',
			'id'				=> 'wic_save_current_user_sig',
			'type'				=> 'button',
			'button_class'		=> 'wic-form-button',
			'button_label'		=>	'Saved Signature',
		);	
		$save_sig_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;
		$settings_form_entity = new WIC_Entity_User ( 'no_action', '' );
		return $settings_form_entity->new_blank_form_noecho() . $save_sig_button;
	}

	protected function group_special ( $slug ){
		$special_groups = array ( 'user_sig', 'gmail_oauth', 'activesync', 'imap_smtp' );
		return in_array ( $slug, $special_groups );
	}
	
	protected function group_special_activesync ( &$data_array ) {
	
		
			$settings_url = admin_url() . 'admin.php?page=wp-issues-crm-main&entity=email_inbox&action=new_blank_form&id_requested=settings';


			$control = $data_array['activesync_email_address']->form_control();
			$name_control = $data_array['activesync_sender_name']->form_control();
			// saves all options in addition to email
			$button_args = array (
				'title'				=>  __( 'Save settings for email processing', 'wp-issues-crm' ),
				'name'				=> 'wic_save_email_settings',
				'type'				=> 'button',
				'button_class'		=> 'wic-form-button wic_save_email_settings',
				'button_label'		=>	'Saved',
			);	
			$save_options_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;

			$explanation =  '<p class="wic-oauth-background">To connect via ActiveSync:</p>
			<ol>
				<li class="wic-oauth-background">Save an email address above.</li>	
				<li class="wic-oauth-background">Save the password below.</li>	
			</ol>';
				
			$button_args = array (
				'title'				=>  __( 'Save activesynch password', 'wp-issues-crm' ),
				'name'				=> 'wic-set-activesync-password-button',
				'id'				=> 'wic-set-activesync-password-button',
				'type'				=> 'button',
				'button_class'		=> 'wic-form-button wic-set-activesync-password-button',
				'button_label'		=>	'Set Password',
			);	
			$save_password_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;	

			$button_args = array (
				'title'				=>  __( 'Check activesync connection', 'wp-issues-crm' ),
				'name'				=> 'wic-activesync-test-button',
				'id'				=> 'wic-activesync-test-button',
				'type'				=> 'button',
				'button_class'		=> 'wic-form-button wic-activesync-test-button',
				'button_label'		=>	'Test Settings',
			);	
			$check_activesync_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;	

			return  $control . $name_control . $save_options_button . '<br/>' . $save_password_button . $check_activesync_button;
	}


	protected function group_special_gmail_oauth ( &$data_array ) {

		$error_template = '
			<p class="wic-oauth-background">This installation is not ready to get email using <a target="_blank" href="https://developers.google.com/gmail/api/">the Gmail API</a>. The server says:</p>
			<p class="wic-oauth-warning">%s</p>
			<p class="wic-oauth-background">Check <a target="_blank" href="https://wp-issues-crm.com/configuring-gmail-access-in-wp-issues-crm/">Gmail oauth configuration instructions here.</a></p>
			<p class="wic-oauth-background">You can always access Gmail or any other email account using traditional connection methods configurable at <a  target="_blank" href="' . admin_url() . 'admin.php?page=wp-issues-crm-settings">the WP Issues CRM Configure page.</a></p>
			';
		// is the credentials path defined?
		if ( false ===  WIC_Entity_Email_OAUTH::get_oauth_credentials_path() ) {
			return sprintf ( $error_template, 'WIC_GMAIL_OAUTH_CREDENTIALS_FULL_PATH has not been defined in wp_config.php' . ( is_multisite() ? ' for primary site.' : '.' ) );
		}	
		// does a file exist at that location
		if ( ! file_exists (  WIC_Entity_Email_OAUTH::get_oauth_credentials_path() ) ) {
			return sprintf ( $error_template, 'No credential file found at ' .  WIC_Entity_Email_OAUTH::get_oauth_credentials_path() . ' -- the location defined as WIC_GMAIL_OAUTH_CREDENTIALS_FULL_PATH in wp_config.php' . ( is_multisite() ? ' for primary site.' : '.' ));
		}
		// is the file readable -- correct permissions
		if ( ! is_readable ( WIC_Entity_Email_OAUTH::get_oauth_credentials_path() ) ) {
			return sprintf ( $error_template,  WIC_Entity_Email_OAUTH::get_oauth_credentials_path()  . ' exists but cannot be read.  Likely file/directory permissions problem.');
		}
		$file_contents = ( file_get_contents (  WIC_Entity_Email_OAUTH::get_oauth_credentials_path() ) );
		$credentials_object = json_decode ( $file_contents );
		// is the file readable -- correct permissions
		if ( ! isset  ( $credentials_object->web->client_id ) ) {
			return sprintf ( $error_template,  WIC_Entity_Email_OAUTH::get_oauth_credentials_path()  . ' exists but appears to be corrupted.  It appears to contain no client_id.  Reinstall the client credentials.') .  print_r ( $credentials_object, true );
		}
		
		// client credentials OK -- display connection button 
		$button_args = array (
			'title'				=>  __( 'Establish or re-establish access to a Gmail Account from WP Issues CRM.', 'wp-issues-crm' ),
			'name'				=> 'wic_connect_to_gmail',
			'id'				=> 'wic_connect_to_gmail',
			'type'				=> 'button',
			'value'				=> admin_url() . 'admin.php?page=wp-issues-crm-main&entity=email_oauth&action=redirect_to_gmail&oauth_nonce=' . wp_create_nonce( 'wic_oauth' ),
			'button_class'		=> 'wic-form-button',
			'button_label'		=>	'Connect to Gmail',
		);	
		$connect_button= WIC_Form_Parent::create_wic_form_button( $button_args ) ;

		$disconnect_button_args = array (
			'title'				=>  __( 'Disconnect Gmail account; revert to IMAP connection settings.', 'wp-issues-crm' ),
			'name'				=> 'wic_disconnect_from_gmail',
			'id'				=> 'wic_disconnect_from_gmail',
			'type'				=> 'button',
			'button_class'		=> 'wic-form-button',
			'button_label'		=>	'Disconnect',
		);	
		
		$disconnect_button_prepared =  '<p>' . WIC_Form_Parent::create_wic_form_button( $disconnect_button_args ) . '</p>'; 

		
		// check results of last connect
		$option_def = get_option ( 'wp-issues-crm-gmail-connect' );
		$status_message = '';
		$disconnect_button = '';

		// no previous connect
		if ( ! $option_def ) {
			$status_message = '<p class="wic-oauth-background">Ready to attempt connection.</p>';

		// error on last connect
		} elseif ( $option_def->connect_error ) {
			// not sure what error looks like . . . attempt it as a google error return
			try {
				if ( ! isset ( json_decode ( $option_def->connect_error )->error) ) {
					throw new Exception ( ' -- possibly a server configuration or security error');
				}
				$error = json_decode ( $option_def->connect_error )->error;
				// main error message
				$connect_error = $error->message . '(' . $error->code . ')';
				// parse details too
				$connect_error_details = '';
				foreach ( (array) $error->errors as $index => $error ) {
					$connect_error_details .= ( '<li class = "wic-oath-connect-error-detail-item">Error ' . $index . ':<ul class="wic-oath-connect-error-subdetails">' );
					foreach ( (array) $error as $type=>$value) {
						$connect_error_details .=  ( '<li class = "wic-oath-connect-error-subdetail-item">' . "$type=>$value" . '</li>' );
					}
					$connect_error_details .= '</ul></li>';
				} 
				$error_explanation =  '<p class="wic-oauth-background">Details:</p>' .
							  '<ul class="wic-oath-connect-error-details">' . $connect_error_details . '</ul>';
			} catch ( Exception $e ) {
				$connect_error = print_r ( $option_def->connect_error, true );
				$error_explanation = $e->getMessage();
			}
			
			$status_message = '<p class="wic-oauth-background">Last connection attempt had an error' .  $error_explanation . ':</p>' .
				'<p class="wic-oauth-warning">' .  $connect_error . '</p>'  ;  

		// connection is stored but expired or revoke
		} elseif ( !WIC_Entity_Email_OAUTH::check_user_auth()['response_code'] ) {
			$status_message = '<p class="wic-oauth-warning">Gmail API connection expired or revoked for ' .  $option_def->email_address . '.</p>
			<p class="wic-oauth-background">You can connect again to change accounts or reauthorize the connection. Or you can fully disconnect to revert to 
			<a  target="_blank" href="' . admin_url() . 'admin.php?page=wp-issues-crm-settings">the IMAP connection defined on the main WP Issues CRM settings page.</a></p>';
			$disconnect_button = $disconnect_button_prepared;			
		// good connection 
		} else {
			
			$status_message = '<p class="wic-oauth-gtg">Connected to ' .  $option_def->email_address . ' through <a target="_blank" href="https://developers.google.com/gmail/api/">the Gmail API</a>.</p>
			<p class="wic-oauth-background">You can connect again to change accounts or reauthorize the connection.   If you configure Gmail as your "Send Email Via" Account, the <code>From Email Name</code>, <code>Reply To Email</code> and <code>Reply To Name</code> settings from 
			<a  target="_blank" href="' . admin_url() . 'admin.php?page=wp-issues-crm-settings">Configure</a> will be used.</p>';
			$disconnect_button = $disconnect_button_prepared;
		}
	
		return  $connect_button . $status_message . $disconnect_button;
	}

	protected function group_special_imap_smtp () {
		$section_notes = '
			<p class="wic-oauth-background" >If an ActiveSync email is supplied, both IMAP (incoming email) and SMTP (outgoing email) settings will be disregarded.</p>
			<p class="wic-oauth-background">If Gmail is connected, IMAP (incoming) settings will be disregarded, but you can configure SMTP for sending.</p>
			<p class="wic-oauth-background">For IMAP and SMTP settings go to <a href="' . admin_url() . 'admin.php?page=wp-issues-crm-settings">the WP Issues CRM Configure page.</a></strong>';
		return $section_notes;
	}

	// message
	protected function format_message ( &$data_array, $message ) {
		$formatted_message =  __('Email processing settings.' , 'wp-issues-crm') . $message;
		return $formatted_message; 
	}
	
	protected function group_screen( $group ){
 		return $group != 'inbox_options';
 	}
	
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ){}

}