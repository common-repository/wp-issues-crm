<?php
/*
*
*	wic-entity-email-md5.php
*
*   this class collects md5 related functions
*
*   goal is to support recognition of slightly changed messages as same
*	create md5 digests of compressed versions of each message sentences
*	compare to find common sentences and suggest mapping -- see usage of guesses in inbox load
*	
*/
class WIC_Entity_Email_MD5 {

	// called in email-message-object to populate the sentence_md5_array property
	public static function get_md5_array_from_block_array ( &$non_address_block_array ) {
		$md5_array = array();
		if ( $non_address_block_array ) {
			foreach ( $non_address_block_array as $non_address_block ) { 
				$block_sentences = preg_split ( '#\. #', $non_address_block, -1, PREG_SPLIT_NO_EMPTY );
				foreach ( $block_sentences as $sentence ) { 
					// strip period from last sentence and all white spaces -- reduce impact of format changes
					$stripped_sentence = preg_replace(  '#[\. \t\n\r\x00\v\f]#','',$sentence ); 
					$sentence_parms = array( md5( $stripped_sentence ), strlen ( $stripped_sentence ) );
					$md5_array[] = $sentence_parms;
				}
			}
		}	
		return $md5_array;
	}

	// called in parser to save the md5 digest of the message
	public static function save_md5_digest ( &$sentence_md5_array, $ID ) {
		global $wpdb;
		if ( $sentence_md5_array ) {
			$md5_table = $wpdb->prefix . 'wic_inbox_md5';
			$md5_sql = "INSERT INTO $md5_table ( inbox_message_id, message_sentence_md5, message_sentence_length ) VALUES ";
			foreach ( $sentence_md5_array as $sentence_md5 ) {
				$curr_md5 = $sentence_md5[0];
				$curr_length = $sentence_md5[1];
				$md5_sql .= "( $ID, '$curr_md5', $curr_length ),";
			}
			$md5_sql = trim ( $md5_sql,',' );
			$wpdb->query ( $md5_sql );
		}	
		return; // no response code checking
	}
	
	// called in processor to create md5 map entries for trained items
	public static function create_md5_map_entries (&$sentence_md5_array, $md5_mapped_issue, $md5_mapped_pro_con ) {
		global $wpdb;
		$md5_table = $wpdb->prefix . 'wic_inbox_md5_issue_map';
		
		$md5_sql = "INSERT INTO $md5_table ( sentence_md5, md5_mapped_issue, md5_mapped_pro_con, map_utc_time_stamp, sentence_length ) VALUES ";
		$map_utc_time_stamp = microtime(true);
		$new_md5_maps = array();
		foreach ( $sentence_md5_array as $sentence_md5 ) {
			$curr_md5 = $sentence_md5[0];
			$curr_length = $sentence_md5[1];
			// test to see if map already exists and is current -- if so, skip it
			$md5_test_results = $wpdb->get_results ( "SELECT md5_mapped_issue, md5_mapped_pro_con FROM $md5_table WHERE sentence_md5 = '$curr_md5' ORDER BY map_utc_time_stamp DESC LIMIT 0,1" );
			if ( $md5_test_results ) {
				if ( $md5_mapped_issue == $md5_test_results[0]->md5_mapped_issue &&
					$md5_mapped_pro_con == $md5_test_results[0]->md5_mapped_pro_con ) {
					continue;
				}
			}
			// if got to here, then have something to save -- prepare sql
			$new_md5_maps[] = $curr_md5;
			$md5_sql .= $wpdb->prepare (
			 "( %s, %d, %s, %d, %d ),",
			 array ( $curr_md5, $md5_mapped_issue, $md5_mapped_pro_con, $map_utc_time_stamp, $curr_length )
			);
		}
		// have batched the set of newly mapped sentences
		if ( $new_md5_maps ) {
			// save the new mappings
			$md5_sql = trim ( $md5_sql,',' );
			$wpdb->query ( $md5_sql );
			// update any guess_mappings that need to be updated
			self::update_guesses_with_new_mappings( $new_md5_maps );
		}
	}
	
	
	/*
	*
	* get most likely issue/pro-con from message md5 sentences previously mapped
	*
	* v2 replaces earlier version of this function which was vulnerable to 
	*	high sentence sentence counts (possibly exceeding max packet size for digest list)
	*   high issue map counts (possibly exceeding group_concat length ) for frequently used sentences
	*
	* arguably slow and over built, this version should be robust in the face of these potential problems
	*
	* 
	*
	*/

	public static function get_issue_and_pro_con_map_from_md5_v2 ( &$sentence_md5_array) {

		// must have elements in the array to proceed
		if ( ! $sentence_md5_array ) {
			return array ( 
				'guess_mapped_issue' => 0, 
				'guess_mapped_issue_confidence' => 0, 
				'guess_mapped_pro_con' => '',
				'non_address_word_count' => 0, 
			);
		}

		global $wpdb;
		$temporary_table = $wpdb->prefix . 'wic_md5_staging_table_' .  $sentence_md5_array[0][0] ;
		$result = $wpdb->query( "
			CREATE TEMPORARY TABLE $temporary_table (
			sentence_md5_temp varchar(50) NOT NULL, 
			KEY  (sentence_md5_temp(50))
			) DEFAULT CHARSET=utf8mb4;
			");
		$md5_table = $wpdb->prefix . 'wic_inbox_md5_issue_map';
		// construct list of md5s to search with and count total words in mappable sentences
		// don't actually know how the number of sentences at this stage relates to max packet size, so use temp table
		$total_characters = 0;
		foreach ( $sentence_md5_array as $sentence_md5 ) {
			$wpdb->query ( "INSERT into $temporary_table (sentence_md5_temp) VALUES ( '{$sentence_md5[0]}')");
			$total_characters += $sentence_md5[1];
		}
		/*
		*
		* for each sentence_md5, see if it has been mapped to an issue/pro-con combination -- use latest mapping
		*
		*/
		$sql_get_latest = "
			(	
				SELECT 
					sentence_md5, 
					max(map_utc_time_stamp) as most_recent_stamp
				FROM $md5_table md5_1
				INNER JOIN $temporary_table on sentence_md5_temp = sentence_md5 COLLATE utf8mb4_general_ci
				GROUP BY sentence_md5 
			) latest
		   ";   		

		$sql_issue_pro_con_with_votes = "
			(	
				SELECT 
					md5.sentence_md5, 
					md5.sentence_length,
					md5.md5_mapped_issue,
					md5.md5_mapped_pro_con
				FROM $md5_table md5
				INNER JOIN $sql_get_latest ON md5.map_utc_time_stamp = latest.most_recent_stamp AND latest.sentence_md5 = md5.sentence_md5
			) as v
		   ";   		

		$sql_sum_votes = "
			SELECT  v.md5_mapped_issue, v.md5_mapped_pro_con, sum(v.sentence_length) as character_votes
			FROM $sql_issue_pro_con_with_votes
			GROUP BY v.md5_mapped_issue, v.md5_mapped_pro_con
			ORDER BY sum(v.sentence_length) DESC
			LIMIT 0, 1
			";

		$vote_results = $wpdb->get_results ( $sql_sum_votes );
		$mapped_issue = 0;
		$mapped_pro_con = '';
		$mapping_confidence = 0;
		if ( $vote_results ) {
			$mapped_issue = $vote_results[0]->md5_mapped_issue;
			$mapped_pro_con = $vote_results[0]->md5_mapped_pro_con;
			$mapping_confidence = ceil( ( 100 * $vote_results[0]->character_votes ) / $total_characters );
		}

		$wpdb->query ( "DROP TABLE $temporary_table" );

		return array ( 
			'guess_mapped_issue' => $mapped_issue, 
			'guess_mapped_issue_confidence' => $mapping_confidence, 
			'guess_mapped_pro_con' => $mapped_pro_con,
			'non_address_word_count' => floor( $total_characters / 7 ), // assuming 7 characters per word 
		);

	}	
	
	

	
	// when mapping new md5s, see if affects any guesses and update if necessary
	private static function update_guesses_with_new_mappings ( $new_md5_maps ) {
	
		global $wpdb;
		$inbox_md5_table	= $wpdb->prefix . 'wic_inbox_md5';
		$inbox_image_table 	= $wpdb->prefix . 'wic_inbox_image';
		
		// first get set of messages (by database ID not UID) that may need updating
		$in_string = "'" . implode ( $new_md5_maps, "', '" ) . "'";	 
		$get_ids_sql = "
			SELECT i5.inbox_message_id, serialized_email_object, guess_mapped_issue, guess_mapped_issue_confidence, guess_mapped_pro_con, non_address_word_count 
			FROM $inbox_md5_table i5 INNER JOIN $inbox_image_table i ON i5.inbox_message_id = i.ID
			WHERE 
				message_sentence_md5 IN($in_string)
				AND no_longer_in_server_folder = 0 
				AND to_be_moved_on_server = 0
			GROUP BY i5.inbox_message_id
			";			
		 $messages = $wpdb->get_results( $get_ids_sql );

		// next pass array of ids and update each as necessary
		if ( $messages ) {
		 	foreach ( $messages as $message ) {
		 		// retrieve message object
		 		$curr_id = $message->inbox_message_id;
		 		$message_object = unserialize( $message->serialized_email_object );
		 		// check recommendation for message object
		 		$guesses = self::get_issue_and_pro_con_map_from_md5_v2 ( $message_object->sentence_md5_array );
		 		// if changed, save them
		 		if ( 
		 			$message->guess_mapped_issue != $guesses['guess_mapped_issue'] ||
		 			$message->guess_mapped_issue_confidence != $guesses['guess_mapped_issue_confidence'] ||
		 			$message->guess_mapped_pro_con != $guesses['guess_mapped_pro_con'] ||
		 			$message->non_address_word_count != $guesses['non_address_word_count'] 
					) {
					$update_message_sql = $wpdb->prepare (
						"UPDATE $inbox_image_table 
						SET  
							guess_mapped_issue = %d,
							guess_mapped_issue_confidence = %d,
							guess_mapped_pro_con = %s,
							non_address_word_count = %s
						WHERE ID = %d
						",
						array ( 
							$guesses['guess_mapped_issue'],
							$guesses['guess_mapped_issue_confidence'],
							$guesses['guess_mapped_pro_con'],
							$guesses['non_address_word_count'],
							$curr_id
						)
					);
					$wpdb->query ( $update_message_sql );
								
				} // close if changed
		 	} // close foreach messages
		}  // close if messages
	} // close function

	// note that two additional functions update the md5 table -- purges which simultaneously purge inbox:
	// +  WIC_Entity_Email_Inbox_Synch::purge_inbox
	
} // close class