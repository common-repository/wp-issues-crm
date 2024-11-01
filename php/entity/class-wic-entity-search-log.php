<?php
/*
*
*	wic-entity-search-log.php
*
*   Note: In versions 2.7 and up, only advanced searches are logged.
*
*	Class logs searchs and supports retrieval of searches
*
*/

class WIC_Entity_Search_Log extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'search_log';
	} 

	/**************************************************************************************************************************************
	*
	*	Search Log Request Handlers
	*
	*
	*	Search log retrieval can take two approaches -- either to the search form or to the found list
	*	Need to carry old search ID back if showing the found list, so that export and redo search buttons can work, so
	*	   maintain old search ID in array returned from ID retrieval 
	*
	***************************************************************************************************************************************/

	// request handler for search log list -- re-executes query 
	public function id_search( $args ) {
		
		$search = false;
		if ( $args['id_requested'] == absint ( $args['id_requested'] ) ) {
			$search = $this->get_search_from_search_log(  $args );
		}

		if ( $search ) {
			$class_name = 'WIC_Entity_'. $search['entity']; 
			// returning from search log, go to found item(s) if any, or redisplay search form
			if ( $search['result_count'] > 0) {
				${ $class_name } = new $class_name ( 'redo_search_from_meta_query', $search  ) ;
			} else {
				${ $class_name } = new $class_name ( 'search_form_from_search_array', $search ) ;		
			}
		} else {
			printf ( '<h3>Bad search log request -- no search in search log with with ID "%1$s".</h3>' , $args['id_requested'] );	
		}		
	}	
	
	// request handler for back to search button -- brings back to filled search form, but does not reexecute the query
	public function id_search_to_form( $args ) {
		$search = $this->get_search_from_search_log(  $args );
		$class_name = 'WIC_Entity_'. $search['entity'];
		${ $class_name } = new $class_name ( 'search_form_from_search_array', $search ) ;		
	}

	/**************************************************************************************************************************************
	*
	*	Formatters for search log list
	*
	***************************************************************************************************************************************/
	public static function favorite_formatter ( $favorite ) {
		$dashicon = $favorite ? '<span class="dashicons dashicons-star-filled"></span>' : '<span class="dashicons dashicons-star-empty"></span>';
		return ( $dashicon );	
	}	
	
	// update the favorite setting in AJAX call
	public static function set_favorite ( $id, $data ) { 
		global $wpdb;
		$favorite = $data->favorite ? 1 : 0;		
		$search_log_table = $wpdb->prefix . 'wic_search_log';
		$is_named_phrase = ( 1 == $favorite ) ? '' : ' and is_named = 0 ';
		// don't unfavorite a named search -- named searches are always favorited
		$sql = "UPDATE $search_log_table SET favorite = $favorite WHERE ID = $id $is_named_phrase";
		$result = $wpdb->query( $sql );
		return array ( 'response_code' => $result, 'output' => $result ? __( 'Favorite set OK.', 'wp-issues-crm' ) : __( 'Favorite not set -- DB error.', 'wp-issues-crm' ) ); 
	}
	
	public static function share_name_formatter ( $name ) {
		return ( $name > '' ? $name : __( 'private', 'wp-issues-crm' ) );
	}
	
	public static function update_name ( $id, $name ) {

		global $wpdb;
		$search_log_table = $wpdb->prefix . 'wic_search_log';
		$user_id = get_current_user_id();
		$user_id_phrase = current_user_can ( 'edit_theme_options' ) ? '' : " and user_id = $user_id";
		$sanitized_name = sanitize_text_field ( $name );
		// favorite named searches, but don't unfavorite unnamed searches
		if ( $sanitized_name  > '' ) {
			$is_named = 1;			
			$favorite_phrase = ", favorite = 1 ";
		} else {
			$is_named = 0;
			$favorite_phrase = '';
		}

		$sql = $wpdb->prepare( 
			"UPDATE $search_log_table SET share_name = %s, is_named = $is_named $favorite_phrase where ID = %s $user_id_phrase ",
			array ( $sanitized_name, $id ) 
		);
		$result = $wpdb->query ( $sql );
		if ( false !== $result ) {
			$share_phrase = $name > '' ? __( 'Search will be shared', 'wp-issues-crm' ) : __( 'Search will be visible only to you.', 'wp-issues-crm' );
			return array ( 'response_code' => true, 'output' =>  __( 'Name update successful. ', 'wp-issues-crm' ) . $share_phrase );  
		} else {
			return array ( 'response_code' => false, 'output' => __( 'Name update not successful.  Probable security error -- if you are not an administrator, you can only name your own searches.', 'wp-issues-crm' ) ); 
		}
	}
	
	public static function serialized_search_array_formatter ( $serialized ) {
		
		global $wic_db_dictionary;
		$search_array = unserialize ( $serialized );
		$search_phrase = '';

		// first repack search array, exploding any items that are row arrays
		// two components are labeled as in advanced search array 
		$unpacked_search_array_definitions = array();
		$unpacked_search_array_terms = array();
		foreach ( $search_array as $search_clause ) {
			if ( isset ( $search_clause[0] ) ) { 
				$new_clause = array();
				$row_type = substr( $search_clause[1][0]['table'], 16 );
				foreach ( $search_clause[1] as $clause_component ) {
					if ( $row_type . '_field' == $clause_component['key'] ) {
						// advanced_search array not repacked
						if ( is_array ( $clause_component['value'] ) ) {
							$new_clause['key']		=  $clause_component['value']['field_slug'];
							$new_clause['table']		=  $clause_component['value']['entity_slug'];
						} else {
						// if don't have array then old format, can't display or retrieve search
						return ( __( 'Advanced search from before Version 2.3.5 cannot be retrieved after 
							upgrade. No data has been lost.  Just redo search.', 'wp-issues-crm' ) );			
						}
					} elseif ( $row_type . '_comparison' == $clause_component['key']  ) {
						$new_clause['compare'] = $clause_component['value']; 				
					} elseif ( $row_type . '_value' == $clause_component['key']  ) { 
						$new_clause['value']  = $clause_component['value']; 
					} elseif ( $row_type . '_type' == $clause_component['key']  ) { 
						$new_clause['type'] =  $clause_component['value']; 
					} elseif ( $row_type . '_aggregator' == $clause_component['key']  ) { 
						$new_clause['aggregator'] =  $clause_component['value'];
					} elseif ( $row_type . '_issue_cat' == $clause_component['key']  ) { 
						$new_clause['issue_cat'] =  $clause_component['value']; 
					}										
				}
				$unpacked_search_array_terms[] = $new_clause;			
			} else {
				$unpacked_search_array_definitions[] = $search_clause;			
			}		
		}
		$search_array = array_merge ( $unpacked_search_array_terms, $unpacked_search_array_definitions );

		if ( count ( $search_array ) > 0 ) { 
			foreach ( $search_array as $search_clause ) {
	
					
				$value =  isset ( $search_clause['value'] ) ? $search_clause['value'] : '' ; // default
				$show_item = true; 

				// look up categories if any for post_category ( from either regular or advanced search format array )			
				if ( 'post_category' == $search_clause['key'] || strpos( $search_clause['compare'], 'cat' ) > -1  ) { 
					if ( 0 < count ( $value ) ) {
						$value_string = '';
						foreach ( $value as $key => $selected ) {
							$value_string .= ( $value_string > '' ) ? ', ': '';
							$value_string .= get_the_category_by_ID ( $key );				
						}
						$value = $value_string;
					} else {
						$value = '';
						$show_item = false;				
					}
				} elseif ( is_array( $value ) ) {
					$value = implode ( ',', $value );		
				} else {
					if ( 'advanced_search' != $search_clause['table'] ) { // don't unpack connector terms for advanced search
						$label = $wic_db_dictionary->get_option_label( $search_clause['table'], $search_clause['key'], $value );
						$value = ( $label > '' ) ? $label : $value;
					}
				}
			
				// unpack advanced search having cats
				$cat_string = ''; 
				$count_cats = 0;
				if ( isset ( $search_clause['issue_cat'] ) ) {
					if ( count( $search_clause['issue_cat'] )  > 0 ) {
						foreach ( $search_clause['issue_cat'] as $cat => $selected ) {
							$cats_comma = $count_cats > 0 ? ', ' : '';
							$cat_string .= get_the_category_by_ID ($cat );
							$count_cats++;						
						}
						$cat_string = ' cat(s) ' . $cat_string . ' '; 	
					}
				}							
			
				
				if ( $show_item )	{
					if ( 'ID' == $search_clause['key']  ) { // only accessible for issues and constituents and overrides all other criteria
						if ( 'issue' == $search_clause['table'] ) {
						 	$search_phrase = __( 'Issue -- ', 'wp-issues-crm' ) . WIC_DB_Access_WP::format_title_by_id( $value );
						} else {
							$search_phrase = __( ' Constituent -- ', 'wp-issues-crm' ) . esc_html( WIC_DB_Access_WIC::get_constituent_name ( $value ) );
						}				
					} else {  		
						$search_phrase .= ( 'advanced_search' == $search_clause['table'] ? '' : $search_clause['table'] . ': ' ). 
							( isset ( $search_clause['aggregator'] ) 	? ' ' . $search_clause['aggregator'] . ' of ' : '' ) .
							( isset ( $search_clause['type'] )  		? ' type ' . $search_clause['type'] . ' ' : '' ) .
							$cat_string .
							$search_clause['key'] . ' ' . 
							$search_clause['compare'] . ' ' . 
							esc_html( $value ) . '<br />';
					}
				}		
			} 
		}
		return ( $search_phrase );	
	}	

	// log a search entry
	public static function search_log ( $args ) {
		
		extract ( $args );
		// on redo_search_from_meta_query, do not log, instead retain original search ID
		if ( isset ( $search_parameters['redo_search'] ) ) {
			return ( $search_parameters['old_search_id'] );
		}

		global $wpdb;
		$search_log_table = $wpdb->prefix . 'wic_search_log';
		$user_id = get_current_user_id();

		$meta_query_array = self::replace_field_id_with_entity_and_field_slugs ( $meta_query_array );
		$search = serialize( $meta_query_array );
		$parameters = serialize ( $search_parameters );
		$sql = $wpdb->prepare(
			"
			INSERT INTO $search_log_table
			( user_id, search_time, entity, serialized_search_array,  serialized_search_parameters, result_count  )
			VALUES ( $user_id, %s, 'advanced_search', %s, %s, %s)
			", 
			array ( current_time('mysql'), $search, $parameters, $found_count ) ); 

		$save_result = $wpdb->query( $sql );

		if ( 1 == $save_result ) {
			return (  $wpdb->insert_id );
		} else {
			echo '<h3>Unknown database error prevented logging of search.</h3>'; // unlikely error and not warranting an error box; 		
		}
	}
	
	// needed to make search log data invariant across dictionary replacements in upgrades
	private static function replace_field_id_with_entity_and_field_slugs ( $meta_query_array ) { 
		global $wic_db_dictionary;
		$invariant_meta_query_array = array();
		foreach ( $meta_query_array as $search_term ){
			if ( isset ( $search_term[0] ) ) {
				if ( 'row' == $search_term[0] ) { 
					$invariant_row = array();
					foreach ( $search_term[1] as $query_clause ) { 
						if (isset ( $query_clause['key'] ) ) { 
							if ( '_field' == substr ( $query_clause['key'], -6 ) ) { 
								$field_rules = $wic_db_dictionary->get_field_rules_by_id( $query_clause['value'] ); 
								$invariant_field_id = array ( 
									'entity_slug' 	=> $field_rules['entity_slug'],
									'field_slug' 	=> $field_rules['field_slug'],
								);
								$query_clause['value'] = $invariant_field_id;
							}
						}
						$invariant_row[] = $query_clause; 
					}
					$search_term[1] = $invariant_row;
				}
			}
			$invariant_meta_query_array[] = $search_term;
		};
		return $invariant_meta_query_array;
	}
	

	// find an ID search in a serialized array and return the id searched for
	private static function extract_id_search_from_array( $serialized_search_array ) {
		$latest_search_array = unserialize ( $serialized_search_array );
		$latest_searched_for = '';
		foreach ( $latest_search_array as $search_clause ) {
			if ( 'ID' == $search_clause['key']  ) {
				$latest_searched_for = $search_clause['value'];	
			}		
		} 
		return ( $latest_searched_for );
	}
	
	
	// pull the specified search off the search log by search id 
	// (for constituent export, does not pass search parameters, only search terms)
	public static function get_search_from_search_log ( $args ) { 
		
		$id = $args ['id_requested'];
		global $wpdb;
		$search_log_table = $wpdb->prefix . 'wic_search_log';
		
		$search_object = $wpdb->get_row ( "SELECT * from $search_log_table where id = $id " );
		
		if ( $search_object ) {
			$unserialized_search_array = unserialize ( $search_object->serialized_search_array );
			if ( 'advanced_search' == $search_object->entity ) {
				$unserialized_search_array = self::replace_entity_and_field_slugs_with_id ( $unserialized_search_array );  	
			} 
		
			$return = array (
				'search_id' => $id,
				'share_name' => $search_object->share_name,
				'user_id' => $search_object->user_id,
				'entity' =>  $search_object->entity, 
				'unserialized_search_array' =>  $unserialized_search_array,
				'unserialized_search_parameters' => unserialize( $search_object->serialized_search_parameters ),
				'result_count' =>$search_object->result_count
				);
		} else {
			$return = false;
		}
		return ( $return );		
	}
	
	private static function replace_entity_and_field_slugs_with_id ( $invariant_meta_query_array ) {
		global $wic_db_dictionary;
		$meta_query_array = array();
		foreach ( $invariant_meta_query_array as $search_term ){
			if ( isset ( $search_term[0] ) ) {
				if ( 'row' == $search_term[0] ) { 
					$standard_row = array();
					foreach ( $search_term[1] as $query_clause ) {  
						if (isset ( $query_clause['key'] ) ) { 
							if ( '_field' == substr ( $query_clause['key'], -6 ) ) { 
								$field_rules = $wic_db_dictionary->get_field_rules( $query_clause['value']['entity_slug'], $query_clause['value']['field_slug'] );
								$query_clause['value'] = $field_rules->ID;
							}
						}
						$standard_row[] = $query_clause;
					}
					$search_term[1] = $standard_row;
				}
			}
			$meta_query_array[] = $search_term;
		};
		return $meta_query_array;
	}

	/*
	*
	* mark a search as having been downloaded
	*
	*/
	public static function mark_search_as_downloaded ( $id ) {
		global $wpdb;
		$search_log_table = $wpdb->prefix . 'wic_search_log';
		
		$sql = $wpdb->prepare (
			"
			UPDATE $search_log_table
			SET download_time = %s
			WHERE id = %s
			",
			array( current_time( 'Y-m-d-H-i-s' ), $id ) );
		
		$update_result = $wpdb->query( $sql );
			
		if ( 1 != $update_result ) {
			WIC_Function_Utilities::wic_error ( 'Unknown database error in posting download event to search log.', __FILE__, __LINE__, __METHOD__, false );
		}	
	}	
	
  	public static function time_formatter( $value ) {
		$date_part = substr ( $value, 0, 10 );
		$time_part = substr ( $value, 11, 10 ); 		
		// return ( $date_part . '<br/>' . $time_part ); 
		return ( $value );
	} 

	public static function download_time_formatter ( $value ) {
		return ( self::time_formatter ( $value ) );	
	}

	public static function user_id_formatter ( $user_id ) {

		$display_name = '';		
		if ( isset ( $user_id ) ) { 
			if ( $user_id > 0 ) {
				$user =  get_users( array( 'fields' => array( 'display_name' ), 'include' => array ( $user_id ) ) );
				$display_name = esc_html ( $user[0]->display_name ); // best to generate an error here if this is not set on non-zero user_id
			}
		}
		return ( $display_name );
	}

}