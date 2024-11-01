<?php
/*
*
*	wic-entity-comment.php
*
*
*/
class WIC_Entity_Comment {

	public static function synch_comments () {
		if ( WP_DEBUG ) {
			error_log ( 'Starting WP Issues CRM synch_comments run.' );
		}
		global $wpdb;
		
		// clear existing comment activity records -- always regardless of setting
		$activity_table = $wpdb->prefix . 'wic_activity';
		$sql = "DELETE FROM $activity_table where activity_type = 'wic_reserved_88888888'";		
		$result = $wpdb->query ( $sql );
		
		// if synch setting is not set or not on, then stop here, otherwise continue
		$wic_settings = get_option( 'wp_issues_crm_plugin_options_array' );
		$do_synch = false;
		if ( isset ( $wic_settings['synch_comments_on'] ) ) {
			if ( 1 == $wic_settings['synch_comments_on'] ) {
				$do_synch = true;
			}
		} 
		if ( ! $do_synch ) {
			if ( WP_DEBUG ) {
				error_log ( 'Finishing WP Issues CRM synch_comments run -- deletes only.' );
			}
			return $result;
		}
		/*
		* create/recreate comments records based on email link -- only here if synch setting is on
		*
		* starting from scratch because email address could be manually changed on either side and no change tracking on WP side
		*
		*/
		$comments_table = $wpdb->comments;
		$email_table = $wpdb->prefix . 'wic_email';
		$sql =  "
				INSERT INTO $activity_table ( constituent_id, activity_date, activity_type, issue, activity_note, last_updated_time ) 
				SELECT e.constituent_id, LEFT(comment_date,10), 'wic_reserved_88888888', comment_post_ID, comment_content, NOW() 
				FROM $email_table e INNER JOIN $comments_table c on c.comment_author_email COLLATE utf8mb4_general_ci = e.email_address COLLATE utf8mb4_general_ci
				WHERE comment_author_email > '' AND comment_approved = 1
				GROUP BY comment_ID
				";
		$result = $wpdb->query ( $sql );

		// if set to, reporting missing emails
		if ( isset ( $wic_settings['report_missing_emails'] ) ) {
			if ( 1 == $wic_settings['report_missing_emails'] ) {
				self::report_missing_emails();
			}
		} 
		if ( WP_DEBUG ) {
			error_log ( 'Finishing WP Issues CRM synch_comments run -- comment activity records added.' );
		}
		return ( $result );
		
	}

	public static function synch_now() {
		$result = self::synch_comments();
		$OK = ( $result !== false );
		return array ( 'response_code' => $OK, 'output' => $OK ? 'Synch OK' : 'Synch Error -- unknown' );
	}
	
	public static function report_missing_emails () {
		// retrieve comments with missing emails
		global $wpdb;
		$comments_table = $wpdb->comments;
		$posts_table = $wpdb->posts;
		$email_table = $wpdb->prefix . 'wic_email';
		$sql = "
			SELECT comment_date, comment_author, comment_author_email, comment_content, comment_post_ID
	 		FROM $comments_table inner join $posts_table p on p.id = comment_post_ID 
	 		LEFT JOIN $email_table on email_address COLLATE utf8mb4_general_ci = comment_author_email COLLATE utf8mb4_general_ci
	    	WHERE comment_approved = 1 AND comment_author_email > '' AND email_address is NULL 
	    	ORDER BY comment_author_email, comment_date
	    	";
		$results = $wpdb->get_results ( $sql );
		
		// construct message content
		$list_print = "<h3>Online Commenters on " . get_bloginfo ('name') .  " with emails not recorded in WP Issues CRM as of " . current_time( 'Y-m-d') . 	"</h3>";
		if ( count ( $results ) > 0 ) { 
			
			// table header
			$list_print .= 	"<table border='1' cellpadding='5'><tr><th>Comment_date</th><th>Comment Author</th><th>Comment Author Email</th><th>Comment Content</th></tr>";

			$last_email = '';
			$user_count = 0;
			foreach ( $results as $result ) {
				$comment_content = sanitize_text_field ( WIC_Entity_Email_Process::first_n1_chars_or_first_n2_words( $result->comment_content, 500, 50 ) );
					if ( $result->comment_author_email != $last_email ) {
						$user_count++; 
						$last_email = $result->comment_author_email; 
					}
				// do layout
				$list_line = sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s -- <a href="%s">View Post</a></td></tr>',  
					$result->comment_date, $result->comment_author, $result->comment_author_email, $comment_content, get_permalink ( $result->comment_post_ID ) );    
				$list_print .= $list_line;

			} // close foreach
			
			$list_print .= sprintf("</table><h4>Unduplicated count of commenters with emails not recorded in WP Issues CRM = %d. </h4></table><h1>", $user_count);
		} else {
			$list_print .= '<h3>No missing emails.</h3>';
		}
 
 		$list_print .= "<p>You are receiving this email because you are an administrator at " . get_bloginfo ('name') .  " and WP Issues CRM &raquo; Configure &raquo; Comments are configured to send this email.</p>";
	
		// get administrators
		$user_query_args = 	array (
			'role' => 'administrator',
			'fields' => array ( 'user_email'),
		);						
		$user_list = new WP_User_query ( $user_query_args );
		$subject = 'Commenters with unrecorded emails at ' . get_bloginfo( 'name' );
	
		add_filter( 'wp_mail_content_type', 'WIC_Entity_Comment::set_content_to_html' ); 
		// https://developer.wordpress.org/reference/functions/wp_mail/	
		foreach ( $user_list->results as $user ) {
			$return_code = wp_mail( $user->user_email, $subject, $list_print );
		}
		remove_filter( 'wp_mail_content_type', 'WIC_Entity_Comment::set_content_to_html' );	
	} // close report missing_emails
	
	public static function set_content_to_html () { 
		return 'text/html';
	}

	
	public static function link_comment_to_constituent ( $comment_id, $comment_object ) {
		global $wpdb;
		$activity_table = $wpdb->prefix . 'wic_activity';
		$comments_table = $wpdb->comments;
		$email_table = $wpdb->prefix . 'wic_email';
		$sql =  $wpdb->prepare (
			"
				INSERT INTO $activity_table ( constituent_id, activity_date, activity_type, issue, activity_note, last_updated_time ) 
				SELECT e.constituent_id, '%s', 'wic_reserved_88888888', %d, '%s', NOW() 
				FROM $email_table e 
				WHERE e.email_address = '%s' AND e.email_address > '' AND %d = 1
				GROUP BY e.email_address
			",
			array ( 
				substr( $comment_object->comment_date,0,10 ),
				$comment_object->comment_post_ID,
				$comment_object->comment_content,
				$comment_object->comment_author_email,
				$comment_object->comment_approved
			)
		);
		// note that comment object has already been filtered before the wp_insert_comment hook to this function is activated
		$wpdb->query ( $sql );		
	}	
}

