<?php
/*
*
*	class-wic-interface-new-post.php
*
*	(1) accepts array of data describing one constituent and one activity with respect to that constituent
*	(2) checks for possibly matching constituent
*   (3) if necessary adds constituent
*   (4) adds activity
*
*/
class WIC_Interface_New_Post {

	public $response_code;
	public $output;

	public function __construct( $key_value_array, $front_end_post_settings  ) {
		$result = $this->save_new_post ( $key_value_array, $front_end_post_settings );	
		$this->response_code = $result['response_code'];
		$this->output = $result['output'];
	}
	
	private function save_new_post ( $key_value_array, $front_end_post_settings ) {
		$front_end_posts = 'never';
		$front_end_post_initial_status = 'pending';
		extract ( $front_end_post_settings );
		// are front end posts authorized to be created?
		if ( ! in_array( $front_end_posts, array ( 'match', 'add' ) ) ) {
			return array ( 'response_code' => false, 'output' => 'Front end post creation not authorized in WP Issues CRM Interface Settings for this form.' );
		}
		
		// are minimally necessary fields presented in submitted array
		$necessary_fields = array ( 'post_title', 'post_content', 'email_address' );
		$missing_fields = array();
		foreach ( $necessary_fields as $necessary_field ) {
			if ( empty ( $key_value_array[$necessary_field] ) ) {
				$missing_fields[] = $necessary_field;
			}
		}
		if ( ! empty ( $missing_fields ) ) {
			$error =  sprintf( "Cannot $front_end_posts user and add post because following fields empty or not present in form: %s.", implode ( ', ', $missing_fields ) );
			return array ( 'response_code' => false, 'output' => $error );
		}
		
		// if have necessary data sanitize them
		$sanitized_data = array();
		foreach ( $key_value_array as $key => $value ) {
			$sanitized_data[$key] = in_array ( $key, array ( 'post_title', 'post_content' ) ) ?  
				$value : // handle sanitization of these fields elsewhere
  				sanitize_text_field( $value );
		}
				 				
		// match or create user 
		$response = $this->get_user_id( $sanitized_data, $front_end_posts );
		if ( $response['response_code'] === false ) {
			return $response;
		} else {		 					
			$user_id = $response['output'];
		}
		
		// now have user_id, create post for user
		$post_args = array(
			'post_author'	=> $user_id,	
			'post_title' 	=> wp_strip_all_tags( $sanitized_data['post_title'] ), // https://developer.wordpress.org/reference/functions/wp_insert_post/
			'post_content' 	=> $sanitized_data['post_content'], // kses done by wp
			'post_status'	=> $front_end_post_initial_status, // known valid
		);

		$result = wp_insert_post ( $post_args, true );
		
		// post result handle
		if( ! is_wp_error( $result ) ) {
			return array ( 'response_code' => true, 'output' => 'New post created successfully.' );
		} else {
			return array ( 'response_code' => false, 'output' => $result->get_error_message() );
		}
	
	} 

	private function get_user_id ( $sanitized_data, $front_end_posts ) {
		
		// is the user email a valid email
		$error = WIC_Entity_Email::email_address_validator( $sanitized_data['email_address'] );
		if ( $error > '' ) {
			return array ( 'response_code' => false, 'output' => $error );
		}
	
		// does the email already exist on the wordpress database
		$user_id = email_exists(  $sanitized_data['email_address'] );
		if ( ! $user_id ) {
			if ( 'match' == $front_end_posts ) {
				return array ( 'response_code' => false, 'output' => 'User Email is not already registered and interface not configured as "Add", so new post creation failed.' );
			} 
		} else {
			return array ( 'response_code' => true, 'output' => $user_id);
		}

		// naming new user -- first honor user choices on display name, but sanitize fully for inclusion in user name
		$first_name = empty ( $sanitized_data['first_name'] ) ? '' : $sanitized_data['first_name'] ;
		$last_name = empty ( $sanitized_data['last_name'] ) ? '' : $sanitized_data['last_name'] ;
		$display_name = empty ( $sanitized_data['nick_name'] ) ? ( $first_name . ' ' . $last_name ) :  $sanitized_data['nick_name']; 
		$display_name = sanitize_user ( substr ( $display_name, 0, 49 ) ); // user_nicename may not be over 50 characters
		$display_name = empty ( $display_name ) ? 'Anonymous' : $display_name; // form can prevent this by requiring name
		
		// now derive unique/hard-to-hack user name from display name
		$tries = 0;
		while ( ! $new_user_login = $this->try_user_name( 'guest_' . trim( substr ( $display_name, 0, 20 ) ) . '_' . wp_generate_password ( 15, false, false ) ) ) {
			$tries++;
			if ( $tries > 3 )   {
				return array ( 'response_code' => false, 'output' => 'Unanticipated error in user name generation.' );
			}
		} 
		
		// have new valid user name and new valid email
		// https://developer.wordpress.org/plugins/users/creating-and-managing-users/
		$userdata = array(
			'user_login'  	=>  $new_user_login,
			'user_pass'  	=>  wp_generate_password( 30, true, true ), // generate long password with special characters
			'user_url'	 	=>  isset ( $sanitized_data['user_url'] ) ?  $sanitized_data['user_url'] : '', // text field sanitized 
			'user_email' 	=>  $sanitized_data['email_address'], // passed filter
			'display_name'	=>  $display_name,
			'nickname'	  	=>  $display_name,
			'user_nicename'	=>  $display_name,
			'first_name'  	=>  $first_name, // text field sanitized
			'last_name'   	=>  $last_name,
			'role'		  	=>  'subscriber',
			'user_registered' => current_time ( 'Y-m-d H:i:s' ),
		);
 
		$user_id = wp_insert_user( $userdata ) ;
 
		//On success
		if( !is_wp_error($user_id) ) {
			return array ( 'response_code' => true, 'output' => $user_id);
		} else {
			return array ( 'response_code' => false, 'output' => $user_id->get_error_message() );
		}	
	
	}
	
	// applying tests as in https://developer.wordpress.org/reference/functions/register_new_user/
	private function try_user_name ( $user_name ) {
		$user_name = sanitize_user( $user_name, true );
		if ( ! empty ( $user_name  ) ) {
			if ( validate_username( $user_name  ) ) {
				if ( ! username_exists ( $user_name ) ) {
					$illegal_user_logins = array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) );
					if ( ! in_array( strtolower( $user_name ), $illegal_user_logins ) ) {
						return $user_name;
					} 					
				}
			}
		}
		return false;
	}

	private function proper_case ( $bar ) {
		$ucfirst = ucfirst(strtolower($bar));
		return ucfirst(strtolower($bar));
	}
}



