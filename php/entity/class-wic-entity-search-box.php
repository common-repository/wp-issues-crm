<?php
/*
*	wic-entity-search_box
*	psuedo entity for fast (but complex) lookups for search box
*   note that autocomplete_object sanitizes_text_field for label output
*/

class WIC_Entity_Search_Box  {

	public static function search ( $look_up_mode, $term ) {
		
		global $wpdb, $wic_search_box_retrieve_limit;  
		$wic_search_box_retrieve_limit = 30; // applies only to constituents
					
		$sanitized_term = sanitize_text_field ( $term );
		
		if ( strlen ( $sanitized_term ) < 1 ) {
			return array ( 'response_code' => true, 'output' => array() );
		}
		
		/* 
		* look up mode may be both, constituent or issue
		*/
		$response = array();	
		if ( 'constituent' == $look_up_mode || 'both' == $look_up_mode ) {
			$response = self::constituent_search ( $sanitized_term, false );
			if ( ! isset ( $response[0] ) ) {
				$response = array( 
					new WIC_DB_Activity_Issue_Autocomplete_Object (  
						'No constituent name, email, street address or phone found using phrase "' . $sanitized_term . '".' ,
						 -1, 
						'constituent' 
					)
				);
			}
			if ( 'constituent' == $look_up_mode  ) { // if both, continue to issue regardless of returned count
				return array ( 'response_code' => true, 'output' => $response );
			} 	
		}
		
		if ( 'issue' == $look_up_mode || 'both' == $look_up_mode ) {
			$issue_response = WIC_Entity_Autocomplete::db_pass_through( 'activity_issue_all', $sanitized_term )['output'];
			// insert a separator if needed before returning
			if ( 'both' == $look_up_mode ) {
				$response[] = new WIC_DB_Activity_Issue_Autocomplete_Object ( str_repeat(" -", 20), -1, '' );
			}
			
			return array ( 'response_code' => true, 'output' => array_merge ( $response, $issue_response ) );
		}
		
		if ( 'constituent_email' == $look_up_mode ) {
							
			// ignore anything after an intermediate angle brackets in search -- too complicated to try to handle partial emails in the course of being entered
			// ( or, if it is an initial angle bracket, only take what is within the first bracket )
			// have to do this referring to $term b/c sanitize_text_field will convert the < to html-entity and it will behave like a word in the code below
			if ( preg_match( '/[<>]/', $term ) ) {
				$pieces = preg_split( '/ *[<>] */', $term, 2, PREG_SPLIT_NO_EMPTY );
				$sanitized_term = sanitize_text_field( $pieces[0] );
			}
		
			$response = self::constituent_search ( $sanitized_term, true ); // true says limit search to records having email address
			if ( ! isset ( $response[0] ) ) {
				if ( filter_var( $sanitized_term, FILTER_VALIDATE_EMAIL ) ) {
					$response = array( 
						new WIC_DB_Activity_Issue_Autocomplete_Object (  
							$sanitized_term ,
							0,  // valid not found email
							'constituent_email',
							$sanitized_term,
							$sanitized_term
						)
					);			
				}
			}			
			return array ( 'response_code' => true, 'output' => $response );
		}
	}


	public static function constituent_search( $term, $email_search = false ) { // email search true limits to records with email addresses
	
		$term = sanitize_text_field ( $term );
		global $wpdb, $wic_search_box_retrieve_limit;  
		
		/* split array on word boundaries -- discard white space, comma or period */
		$term_array = preg_split ( '#[\, ]+#',  $term, NULL, PREG_SPLIT_NO_EMPTY );
		
		
		$response = array();

		/* see if any terms contain @ -- plan to search by them only first as emails */
		$email_word_array = array();
		$non_email_word_array = array();
		foreach ( $term_array as $word ) {
			if ( false !== strpos( $word, '@') ) {
				$email_word_array[] = $word;
			} else {
				$non_email_word_array[] = $word;
			}
		}
		
		/* prioritize an email search */
		if ( count ( $email_word_array ) > 0 ) {
			$where_clause = ' WHERE 1 = 0 ';
			$where_values = array();
			foreach ( $email_word_array as $email_fragment ) {
				$where_clause .= " OR email_address LIKE %s ";
				$where_values[] = $email_fragment . '%';
			}
			$response = self::accumulate_constituent_results( $wpdb->prepare ( $where_clause, $where_values ), $response, $email_search );
		}
		
		$non_email_word_array_count = count ( $non_email_word_array );
		if ( count( $response ) > ( $wic_search_box_retrieve_limit - 1 ) || 0 == $non_email_word_array_count ) {
			return $response;
		}
		
		/* prioritize an number search -- look for a probable street number or phone number */
		$street_number_pattern = 
			'#(?i)' . // delimiter and case insensitive mode
			'^(?:one|two|three|four|five|six|seven|eight|nine|zero|' . // possible spelled numbers
			'\d{1,10}[A-Z]{0,5})' . // possible actiual digits combined with possible letters (as in 37A or 123456Rear)
			'$#';
		$counter = 0;
		$index 	 = 0;
		$number_found = false;
		$proper_names = array(); // for later use if not found with number
		// only handle case of a single number
		foreach ( $non_email_word_array as $poss_number ) {
			$counter++;
			// take the first street number, disregard any subsequent
			if ( preg_match ( $street_number_pattern, $poss_number ) ) {
				if ( ! $number_found ) {
					$number_found = $poss_number;
					$index = $counter;
				}
			} else {
				$proper_names[] = $poss_number;
			}
		}

		if ( $number_found ) {
			$where_clause = '';
			$raw_phone = preg_replace ( '#[^0-9]#', '', $number_found);
			if ( 4 < strlen ( $raw_phone ) &&  1 == $non_email_word_array_count ) {
				$where_clause = " WHERE phone_number LIKE %s ";
				$where_values = array ( $raw_phone . '%' );
			} elseif ( 1 == $non_email_word_array_count ) {
				$where_clause = " WHERE address_line LIKE %s OR email_address LIKE %s ";
				$where_values = array ( $number_found . '%', $number_found . '%' );
			} elseif ( $index < $non_email_word_array_count ) {
				$address_stub = $number_found . ' ' . $non_email_word_array[$index]; // the number and the word after it
				$where_clause = " WHERE address_line LIKE %s ";
				$where_values = array ( $address_stub . '%' );
			}
			if ( $where_clause ) {
				$response = self::accumulate_constituent_results( $wpdb->prepare ( $where_clause, $where_values ), $response, $email_search );
			}
		}

		$count_proper_names = count (  $proper_names  );
		if ( count( $response ) > $wic_search_box_retrieve_limit - 1 || 0 == $count_proper_names  ) {
			return $response;
		}
	
		// handle remaining terms as possible proper names -- don't use as street name because likely to swamp
		if ( 1 == $count_proper_names ) {
			$where_clause = " WHERE first_name LIKE %s OR last_name LIKE %s OR email_address LIKE %s";
			$where_values = array ( $proper_names[0] . '%',  $proper_names[0] . '%',  $proper_names[0] . '%' );
		} elseif ( 2 == $count_proper_names ) { 
			$where_clause = " WHERE ( first_name LIKE %s AND last_name LIKE %s ) ";
			$where_values = array ( $proper_names[0] . '%',  $proper_names[1] . '%' );
		} else {
			$where_clause = " WHERE ( first_name LIKE %s AND last_name LIKE %s ) OR  ( first_name LIKE %s AND last_name LIKE %s ) ";
			$where_values = array ( $proper_names[0] . '%',  $proper_names[ $count_proper_names - 1 ] . '%', $proper_names[1] . '%', $proper_names[0] . '%', );
		}
		$response = self::accumulate_constituent_results( $wpdb->prepare ( $where_clause, $where_values ), $response, $email_search );
	
		return $response;
	}
	
	private static function accumulate_constituent_results ( $where_clause, $response, $email_search ) { // email search true limits to records with email addresses

		global $wpdb, $wic_search_box_retrieve_limit;
		$base_constituent_sql 	= self::get_constituent_autocomplete_base_sql();	
		$group_by_clause		= " GROUP BY c.ID ";
		$having_clause			= $email_search ? " HAVING max(email_address) > '' " : '';
		$limit_clause = " LIMIT 0, $wic_search_box_retrieve_limit ";
		$sql = $base_constituent_sql . $where_clause . $group_by_clause . $having_clause . $limit_clause;
		$results = $wpdb->get_results ( $sql ); 
		foreach ( $results as $result ) {
			$response[] = new WIC_DB_Activity_Issue_Autocomplete_Object ( $result->name_address, $result->ID, 'constituent', $result->email_name, $result->latest_email_address );
		}	

		// do not check actual count -- OK if accumulated over 30; also OK not to alert user to more . . . don't slow down with a found count

		return ( $response );
	}

	public static function get_constituent_autocomplete_base_sql () {
		global $wpdb, $wic_search_box_retrieve_limit;
		$constituent_table		= $wpdb->prefix . 'wic_constituent';
		$address_table			= $wpdb->prefix . 'wic_address';
		$email_table			= $wpdb->prefix . 'wic_email';
		$phone_table			= $wpdb->prefix . 'wic_phone';
		$base_constituent_sql 	= "SELECT c.ID as ID, 
										concat( 
									   		first_name, ' ', last_name, 
									   		if ( max( e.constituent_id ) > 0, concat ( 
									   			if (first_name > '' OR last_name > '', ', ',' '), 
									   			SUBSTRING_INDEX( GROUP_CONCAT(DISTINCT email_address ORDER BY e.last_updated_time DESC SEPARATOR '|||' ), '|||', 1 ) 
									   			), ''
								   			),
								  	 		if ( max( a.constituent_id ) > 0, concat ( ' --  ', address_line, ' ' , city, ' ', state, ' ', zip ), ' ' ),
								   			if ( max( p.constituent_id ) > 0, concat ( ' -- ', max( phone_number ) ), '' )
								   		) as name_address,
								   		if ( first_name > '' AND last_name > '', concat( first_name, ' ', last_name  ), concat( first_name, last_name ) ) as email_name,
								   		SUBSTRING_INDEX( GROUP_CONCAT(DISTINCT email_address ORDER BY e.last_updated_time DESC SEPARATOR '|||' ), '|||', 1 ) as latest_email_address
								   	FROM $constituent_table c 
								   	LEFT JOIN $email_table e on c.ID = e.constituent_id
								   	LEFT JOIN $address_table a on c.ID = a.constituent_id 
								   	LEFT JOIN $phone_table p on c.ID = p.constituent_id 
								   ";	
		return $base_constituent_sql;
	}

} 
