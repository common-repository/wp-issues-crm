<?php
/*
*	wic-entity-autocomplete.php 
*	psuedo entity for fast lookups
*   note that autocomplete_object sanitizes_text_field for label
*/

class WIC_Entity_Autocomplete  {

	// note that look_up_mode is being passed as "id requested" in class-wic-admin-ajax.php -- term preserved
	public static function db_pass_through( $look_up_mode, $term ) { 
		global $wpdb;
		$term = sanitize_text_field ( $term );

		// strip look_up_mode out of $look_up_mode if encumbered by indexing
		$look_up_mode = strrchr ( $look_up_mode , '[') === false ? $look_up_mode : ltrim( rtrim( strrchr ( $look_up_mode , '['), ']' ), '[' );
	
		$response = array();
		if ( strlen ( $term ) > 0 ) { 		
			
			switch ( $look_up_mode ) {
				case 'activity_issue_all':  
					// do straight look up on title, including all posts -- match exact string within title without regard to word boundaries
					$where_clause = self::format_where_clause_from_term( $term );
					$response_titles = self::get_posts_by_title ( $where_clause ); 
					// set up return call for 
					if ( 0 == count ( $response_titles ) ) {
						$response[] = new WIC_DB_Activity_Issue_Autocomplete_Object ( 'No issues/posts found including "' . $term . '".', -1, 'issue' );					
					}
					return array ( 'response_code' => true, 'output' => $response_titles ) ;
				case 'first_name':
				case 'last_name':
				case 'middle_name':
					$table = $wpdb->prefix . 'wic_constituent';
					break;
				case 'email_address':
					$table = $wpdb->prefix . 'wic_email';
					break;
				case 'city':
				case 'zip':
				case 'address_line':	
					$table = $wpdb->prefix . 'wic_address';
					break;
				case 'phone_number':
					$table = $wpdb->prefix . 'wic_phone';
			}
				 
			// look up process applicable to all except activity ( responded and returned already )
			$response = array();
			$sql_template = "
				SELECT ID, $look_up_mode from $table
				WHERE $look_up_mode LIKE %s
				GROUP BY $look_up_mode
				LIMIT 0, 20
				";
			$values = array ( $term . '%' ); // right wild card only
			$sql = $wpdb->prepare ( $sql_template, $values );
			$results = $wpdb->get_results ( $sql ); 
			foreach ( $results as $result ) {
				$response[] = new WIC_DB_Activity_Issue_Autocomplete_Object ( $result->$look_up_mode, $result->ID, $look_up_mode );
			}
			if ( 20 == count ( $results ) ) {
				$response[] = new WIC_DB_Activity_Issue_Autocomplete_Object ( ' . . . ', -1,  $look_up_mode );			
			} 
		// if sanitized search string is underlength, just send back empty	 
		}
		return array ( 'response_code' => true, 'output' => $response ) ;
	}
	
	private static function format_where_clause_from_term ( $term ) {
		global $wpdb;
		$term_phrase = $term ? 
			$wpdb->prepare ( ' AND post_title like %s ', array ( '%' . $term . '%' ) ) : 
			''
			;
		return 
			" 
			WHERE post_type = 'post' AND
			( post_status = 'publish' or post_status = 'private' ) 
			$term_phrase 
			";
	}
	
	public static function get_posts_by_title ( $where_clause ) {
		// set wp global
		global $wpdb;
		// initialize response var
		$response 				= array();
		// set up tables
		$post_table 			= $wpdb->posts;
		$postmeta_table 		= $wpdb->postmeta;
		$activity_table 		= $wpdb->prefix . 'wic_activity';
		$limit_phrase			= " LIMIT 0, 30 ";
		$sql_template = "
			SELECT wic_matching_posts.ID, concat( post_title, ' (' , open_status, if( open_status > '', ' -- ', '' ) , count(a.ID), ' activities, ' , comment_count, ' comments)' )  as post_title from  
				( SELECT ID, post_title, comment_count, max( if( meta_key = 'wic_data_wic_live_issue', meta_value, '') ) as open_status   
				FROM $post_table p left join $postmeta_table pm on p.ID = pm.post_id
				$where_clause 
				GROUP BY p.ID
				) wic_matching_posts 
			LEFT JOIN $activity_table a on a.issue = wic_matching_posts.ID 
			GROUP BY wic_matching_posts.ID
			ORDER BY post_title 
			$limit_phrase
		"; 
		$results = $wpdb->get_results ( $sql_template );

		if ( $results ) {
			foreach ( $results as $result ) {
				$response[] = new WIC_DB_Activity_Issue_Autocomplete_Object (  $result->post_title, $result->ID, 'issue' );
			}
		}	
		return ( $response );
	}
} 
