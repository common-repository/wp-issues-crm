<?php
/*
*
* class-wic-db-access-wp.php
*		intended as wraparound for $wpdb 
*
* search function serves id searches directly and more complex searches through advanced search in 3.0 
*   handles some cases no longer generated ( formerly generated from issue search form -- combined term query and single queries with custom_fields, author, post_status, etc. );
*   could add these into advanced search if later requested ( add as issue comparison terms and add code to handle in make_blank_control and db_access_advanced_search::search )
*/

class WIC_DB_Access_WP Extends WIC_DB_Access {

	const WIC_METAKEY =  'wic_data_';
	
	// results reporting fields
	public $post_author;
	public $post_date;
	public $post_status;
	
	public function search( $meta_query_array, $search_parameters ) {

		// default search parameters supplied -- not all apply in the WP context
		$select_mode 		= 'id'; 		// not used
		$sort_order 		= false; 		// false is post_date desc; true is title asc
		$compute_total 		= true; 		// not used
		$retrieve_limit 	= -1;			// USED
		
		extract ( $search_parameters, EXTR_OVERWRITE ); 
		
		$allowed_statuses = array(
			'publish',
			'private',
		);		
		
		$allowed_types = array(
			'post',	
		);		
		
		$query_args = array (
 			'posts_per_page' => $retrieve_limit,
 			'post_status'	=> $allowed_statuses,
 			'ignore_sticky_posts' => true,
 			'post_type' => $allowed_types,
	 	);

	   $meta_query_args = array( // will be inserted into $query_args below
	     		'relation'=> 'AND',
	   );
	   
	   
	   foreach ( $meta_query_array as $search_clause ) {
	   	switch ( $search_clause['wp_query_parameter'] ) {
				case 'p':
					$query_args = array ( // start clean array in case other search values persist 
						'p' => $search_clause['value'],
						'post_type' => $allowed_types, // reiterate these parameters because may broaden, rather than narrow
						'post_status'	=> $allowed_statuses,
					);
					break( 2 ); // exit switch and foreach with just the ID search array
									// supports call from ID search
				case '':
					if ( isset ($search_clause['key'] ) ) {
						$search_clause['key'] = self::WIC_METAKEY . $search_clause['key'];
					}
					$meta_query_args[] = $search_clause;
					break;
				case 'author':
				case 'post_status':
				case 's':
					$query_args[$search_clause['wp_query_parameter']] = $search_clause['value'];
					break;
				case 'tag' :
					$tag_name_array = explode ( ',' , $search_clause['value'] );
					$tag_id_array = array();
					foreach ( $tag_name_array as $tag_name ) {
						$tag_object = get_term_by( 'name', esc_html( $tag_name ), 'post_tag' );
						if ( false === $tag_object ) { 
							$tag_id_array[] = 999999;
						} else {
							$tag_id_array[] = $tag_object->term_id;
						} 			
					}
					$query_args['tag__in'] = $tag_id_array;
					break;
				case 'post_title': 
							// note -- hiding post_content as a search field by css 
						 	// on not found going to save form, the search term will come up in title
						 	// the alternative approach would be to add a column to dictionary to suppress a field on search
						 	// treating this as a css issue -- if it were shown, it would do nothing anyway, since
						 	// post_content is not in this switch list 
					$query_args['s'] = $search_clause['value'];
					break;
				case 'cat':
					if ( 'cat' == $search_clause['compare'] ) {
						$query_args['cat'] = implode( ',' , self::reformat_cat_array_form_to_db ( $search_clause['value'] ) );
					} else {
						$query_args[$search_clause['compare']] = self::reformat_cat_array_form_to_db ( $search_clause['value'] );
					}  
					break;
				case 'date':
					$date_array = array();
					if ( 'BETWEEN' == $search_clause['compare'] ) {
						$date_array = array (
							array(
								'after' => $search_clause['value'][0],									
								'before' => $search_clause['value'][1],
								),
							'inclusive' => true,	
						);
					} elseif ( '<=' == $search_clause['compare'] ) {
						$date_array = array (
							array(
								'before' => $search_clause['value'],
								),
							'inclusive' => true,	
						);
					} elseif ( '>=' == $search_clause['compare'] ) {
						$date_array = array (
							array(
								'after' => $search_clause['value'],
								),
							'inclusive' => true,	
						);
												
					}
					$query_args['date_query'] = $date_array;
					break;
			}	// end switch   
	   } // end foreach  	

		if ( count ( $meta_query_args ) > 1 && ! isset ( $query_args['p'] ) ) { // if have ID, go only for that
			$query_args['meta_query'] 	= $meta_query_args;
		}
		// sort by title;
		if ( $sort_order ) {
			$query_args['orderby'] = array( 'title' => 'ASC' );
		}
		
		$wp_query = new WP_Query($query_args);
		$this->sql = ' ' . __(' ( serialized WP_Query->query output ) ', 'wp-issues-crm' ) . serialize ( $wp_query->query );
		$this->result = $wp_query->posts;
	 	$this->showing_count = $wp_query->post_count;
		// only do sql_calc_found_rows on id searches; in other searches, found count will always equal showing count
		// note well:  found_posts is not affected by posts_per_page; it is the total matching search criteria!
		$this->found_count = $wp_query->found_posts;
		// set value to say whether found_count is known
		$this->found_count_real = true;
		$this->retrieve_limit = $retrieve_limit;
		$this->outcome = true;  // wpdb get_results does not return errors for searches, so assume zero return is just a none found condition (not an error)
										// codex.wordpress.org/Class_Reference/wpdb#SELECT_Generic_Results 
		$this->explanation = ''; 
		if ( 0 < $this->found_count ) { // let the list function do some wp database access directly 
			foreach ( $this->result as $row ) {			
				$this->complete_wp_record( $row, $this->entity );
			}	
		}
 	}

	public static function complete_wp_record ( &$post_object, $entity ) {
		
		global $wic_db_dictionary;		
		
		$entity_fields = $wic_db_dictionary->get_field_list_with_wp_query_parameters( $entity ); 	
		foreach ( $entity_fields as $entity_field => $wp_query_parameter ) {
			if ( $wp_query_parameter > '' ) { // otherwise, field slugs in data dictionary should match wp field table names
				if ( 'cat' == $wp_query_parameter ) {
				 	$post_object->$entity_field = self::get_the_categories_ids ( $post_object->ID );
				} elseif ( 'tag' == $wp_query_parameter ) {
					$post_object->$entity_field = self::get_the_tags_list ( $post_object->ID ); 
				}
			} else {
				$return_val = get_post_meta ( $post_object->ID, self::WIC_METAKEY . $entity_field, true );
				// '' database meta value has to translate to 0 for form value (see get_administrator_array) 
				$post_object->$entity_field = ( ( 'issue_staff' == $entity_field && '' == $return_val ) ? 0 : $return_val ) ;
				// true is return string as opposed to array -- not planning any array values;
			}	
		}
	}

	// converts array in screen form -- array( $value1=>1, $value2=>2 ) 
	// to array in db form ($value1, $value2)
	private static function reformat_cat_array_form_to_db ( $form_array_of_cats ) {
		$cats = array();		
		foreach ( $form_array_of_cats as $cat => $value ) {
			$cats[] = $cat;				
		}
		return ( $cats ); 
	}


	private static function get_the_categories_ids ( $id ) {
		$category_object_array = get_the_category ( $id );
		$categories_ids = array();
		foreach ( $category_object_array as $category_object ) {
		//	$categories_ids[] = $category_object->term_id;		
		$categories_ids[$category_object->term_id] = 1;
		}
		return ( $categories_ids );
	}

	private static function get_the_tags_list ( $id ) {
		$tag_object_array = get_the_tags ( $id );
		$tags_list = '';	
		if ( $tag_object_array ) {	
			foreach ( $tag_object_array as $tag_object ) {
				$tags_list .= ( '' == $tags_list ) ? $tag_object->name : ', ' . $tag_object->name; 		
			}
		}
		return ( $tags_list );
	}

	protected function db_update ( &$save_update_array ) {
		$this->process_save_update_array ( $save_update_array );
	}

	protected function db_save ( &$save_update_array ) {
		$this->process_save_update_array ( $save_update_array );
	}



	/*
	*
	* Save_update_array process -- format array as necessary for interface with WP Objects
	*
	*/
	private function process_save_update_array ( &$save_update_array ) {
		
		$id = '';
		$post_args = array();
		$meta_args = array();		

		foreach ( $save_update_array as $update_clause ) {

			// break array into necessary id, post and meta segments
			if ( 'ID' == $update_clause['key'] ) {
				$id = $update_clause['value'];			
			} elseif ( '' < $update_clause ['wp_query_parameter'] ) {
				// fields are appropriately named to allow the following without qualification
				// note, as a policy decision, the following fields are treated as always readonly and ignored on update:
				// post_author, post_date, post_status -- these can be updated through the backend, but no good reason to update here
				// ( on save, use wp defaults for post_author, post_date but set post_status = 'private')
				if ( 'post_category' == $update_clause['key'] ) {
					$cat_array = self::reformat_cat_array_form_to_db ( $update_clause['value'] );
					$cat_array = ( 0 == count ( $cat_array ) ) ? array ( 1 ) : $cat_array; // always save at least 1 cat ( cat 1 ) 
					$post_args[ $update_clause['key'] ] = $cat_array; 				
				} else {
					$post_args[ $update_clause['key'] ] = $update_clause['value'];
				}				
			} else {
				// 0 form value for issue staff has to translate to blank for meta value -- see get_administrator_array
				$update_clause['value'] = ( ( 'issue_staff' == $update_clause['key'] && 0 == $update_clause['value'] ) ? '' : $update_clause['value'] );				
				$meta_args[] = $update_clause;			
			} 	
		}
		
		if ( $id > 0 ) {
			$post_args['ID'] = $id;
			$check_id = wp_update_post ( $post_args );
		} else {
			$post_args['post_status'] = 'private';
			$check_id = wp_insert_post ( $post_args );
			// set values from save that are not in form -- picked up in entity class
			$this->insert_id = $check_id;
			$current_user = wp_get_current_user();
			// values below set for use by wic-entity-issue->special_entity_value_hook
			$this->post_author = $current_user->ID;
			$this->post_date = current_time ('Y-m-d H:i:s' ); // date('Y-m-d H:i:s') ;
			$this->post_status = 'private';
		}
		if ( 0 == $check_id ) {
			$this->outcome = false;
			$this->explanation =  __( 'Unknown error. Could not save/update record.  
				Do new search on same record to check for possible partial results.', 'wp-issues-crm' );
		} else {
			$this->outcome = true;
			// proceed to update meta values -- convention is blank value represented by absence of meta record
			$result = true; // start with true so that this is the result in no action case;
			foreach ( $meta_args as $meta_arg ) {
				$meta_key = self::WIC_METAKEY . $meta_arg['key'];
				if ( '' < $id ) { // update post, possible previous meta_values
					$test_meta = get_post_meta ( $check_id, $meta_key, true ); // true says get single string value
				} else {
					$test_meta = ''; // note that get_post_meta with true set returns empty string on absence of record				
				}
				if ( '' < $test_meta && '' < $meta_arg['value'] && $test_meta != $meta_arg['value'] )  { // changed meta value
					$result = update_post_meta ( $check_id, $meta_key, $meta_arg['value'] ); // false means failure, since know value changed
				} elseif ( '' == $test_meta && '' < $meta_arg['value'] ) {// new meta value	
					$result = add_post_meta ( $check_id, $meta_key, $meta_arg['value'] );
				} elseif ( '' < $test_meta  && '' == $meta_arg['value'] ) { // deleted meta value
					$result = delete_post_meta ( $check_id, $meta_key );
				} // note no action on empty/empty case
			}
			if ( false == $result ) {
				$this->outcome = false;
				$this->explanation = __( 'Unknown error. Could not save metadata (details) for record.  
					Do new search on same record to check for possible partial results.', 'wp-issues-crm' );
			}
		}
	}

	// delete function not implemented for posts -- use the WP Backend
	protected function db_delete_by_id ($id){ 
	}

	/*
	*
	* Used to populate activity issue drop down with open issues
	*
	*/
	public static function get_wic_live_issues () {

		// using direct wpdb query here because wp_query documentation is not consistent with  		
		//	wp_query behavior on showing private posts to logged in users (shows only to author, even if admin)
		
		global $wpdb;		
		
		$metakey = 	self::WIC_METAKEY . 'wic_live_issue';	
		
		$sql = "
			SELECT p.ID, p.post_title  
			FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm on p.ID = pm.post_id
			WHERE pm.meta_key = '$metakey' and pm.meta_value = 'open' AND
			( p.post_status = 'publish' or p.post_status = 'private' ) AND
			( p.post_type = 'post' or p.post_type = 'page' )
			ORDER BY p.post_title 
			";

		$open_posts = $wpdb->get_results( $sql );
		
		return $open_posts; 
	}

	/*
	*
	* Used to populate activity issue drop down with open issues
	*
	*/
	public static function have_wic_live_issues () {

		// using direct wpdb query here because wp_query documentation is not consistent with  		
		//	wp_query behavior on showing private posts to logged in users (shows only to author, even if admin)
		
		global $wpdb;		
		
		$metakey = 	self::WIC_METAKEY . 'wic_live_issue';	
		
		$sql = "
			SELECT p.ID, p.post_title  
			FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm on p.ID = pm.post_id
			WHERE pm.meta_key = '$metakey' and pm.meta_value = 'open' AND
			( p.post_status = 'publish' or p.post_status = 'private' ) AND
			( p.post_type = 'post' or p.post_type = 'page' )
			LIMIT 0, 1 
			";

		$open_posts = $wpdb->get_results( $sql );
		
		if ( ! is_array ( $open_posts ) ) {
			return false;
		}
		
		return count( $open_posts ) == 1; 
	}



	// option counts for use in displaying existing option usage in options update screens
	protected function db_get_option_value_counts( $field_slug ) {
			
		global $wpdb;
	
		$meta_key  = self::WIC_METAKEY . $field_slug;	
	
		$table1 = $wpdb->posts;
		$table2 = $wpdb->postmeta;

		$sql = "SELECT if( null = meta_value, '', meta_value ) as field_value, count(p.ID) as value_count 
		FROM $table1 p left join 
			( SELECT ID, meta_value FROM $table1 inner join $table2 on post_id = ID WHERE meta_key = '$meta_key' ) AS marked_posts 
			ON marked_posts.ID = p.id  
		WHERE ( post_status = 'private' or post_status = 'publish' )
		GROUP BY if( null = meta_value, '', meta_value ) 
		ORDER BY if( null = meta_value, '', meta_value )
		";

		$field_value_counts = $wpdb->get_results( $sql );
		
		return ( $field_value_counts );	
	
	} 

	// this turns out to be 10x-100x faster than running through the metaquery object
	// necessary in bulk uploads where using title to get issue id
	// wp metaquery seemed to be doing table scans despite presence of index
	// add binary equality in 4.3.2.5 to be consistent with change in upload-set-defaults
	public static function fast_id_lookup_by_title( $title ) {
		global $wpdb;
		$post_table = $wpdb->posts;
		$sql = $wpdb->prepare ( "SELECT ID from $post_table 
			WHERE ( post_status = 'private' or post_status = 'publish' )
			AND post_type = 'post' 
			AND BINARY post_title = BINARY %s
			LIMIT 0, 1
			", 
			array ( $title )
		);
		$results = $wpdb->get_results( $sql );
		return  ( isset ( $results[0] ) ? $results[0]->ID : false );
	}

	public static function fast_title_lookup_by_id( $id ) {
		global $wpdb;
		$post_table = $wpdb->posts;
		$sql = $wpdb->prepare ( "SELECT post_title from $post_table 
			WHERE id = %s 
			", 
			array ( $id )
		);
		$results = $wpdb->get_results( $sql );
		return  ( isset ( $results[0] ) ? $results[0]->post_title : false );
	}

	// version handles cases
	public static function format_title_by_id( $id ) {
	
		global $wpdb;
		$post_table = $wpdb->posts;
		$sql = $wpdb->prepare ( "SELECT post_title, post_status from $post_table 
			WHERE id = %s 
			", 
			array ( $id )
		);
		$results = $wpdb->get_results( $sql );
		
		if ( ! isset ( $results[0] ) ) {
			return "Hard deleted issue ( Issue $id )";
		} elseif ( $results[0]->post_status != 'publish' and $results[0]->post_status !='private' ) {
			return "Trashed or unpublished issue: " . $results[0]->post_title;
		} else {
			return $results[0]->post_title;
		}

	}


	public static function fast_id_validation( $id ) {
		global $wpdb;
		$post_table = $wpdb->posts;
		$sql = $wpdb->prepare ( "SELECT ID from $post_table 
			WHERE id = %s AND ( post_status = 'private' or post_status = 'publish' )
			", 
			array ( $id )
		);
		$results = $wpdb->get_results( $sql );
		return  ( isset ( $results[0] ) );
	}

	public static function fast_staff_lookup_by_id( $id ) {
		return get_post_meta( $id, self::WIC_METAKEY . 'issue_staff', true );
	}
	
	/* not implemented for wp type objects */
	public function db_get_time_stamp ( $id ) {}
	protected function db_do_time_stamp ( $table, $id ){}


}


