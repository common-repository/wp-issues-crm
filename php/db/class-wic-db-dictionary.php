<?php
/*
* class-wic-db-dictionary.php
*
* All access to dictionary for general processing purposes should be through this script
* See also db-access-dictionary for updates to dictionary on field adds
*  This script provides access to the WIC data dictionary.
* 
*  Conversion to using field_rules_cache, instead of sql queries for the repetitive queries did cut page assembly times.
*
*/

class WIC_DB_Dictionary {
	
	/*
	*	dictionary is initialized on start up by plug in
	*	construct initializes the rules and options caches
	*	almost all rules lookups are to these caches
	*
	*/
	private $field_rules_cache;
	private $option_values_cache = array();	
	
	public function __construct() {
		$this->initialize_field_rules_cache();
	}	

	// read field rules into class property that functions as cache
	private function initialize_field_rules_cache () {
		global $wpdb;

		$table = $wpdb->prefix . 'wic_data_dictionary';
		$this->field_rules_cache = $wpdb->get_results( 
				"
				SELECT * 
				FROM $table
				where enabled
				"				
			, OBJECT );
	}	
	
	/*
	*
	* method supporting option value groups -- return an array of arrays of option value/label/is_reserved
	*	if hasn't already been looked up, look up and add to cache
	* 	store/return empty string if no enabled options
	*   -- note, for main forms, more efficient to cache all on single lookup, but this identified method used group_concat, which can run into system limits
	*   -- for many calls, this single group caching approach more efficient, since often don't use option values.
	*/
	public function lookup_option_values ( $option_group ) { 
		if ( isset ( $this->option_values_cache[$option_group] ) ) {
			// if have cached values already, return the cached values (or empty string indicating no cached values found on previous try)
			return $this->option_values_cache[$option_group]; 
		} else {
			// look to see if any such values
			global $wpdb;
			$table1 = $wpdb->prefix . 'wic_option_group';
			$table2 = $wpdb->prefix . 'wic_option_value';
			$option_sql = $wpdb->prepare( 
				"
				SELECT option_value, option_label, v.is_system_reserved
				FROM $table1 g inner join $table2 v on g.ID = v.option_group_id
				WHERE v.enabled AND g.enabled
				AND g.option_group_slug = %s
				GROUP BY option_value, option_label, v.is_system_reserved
				ORDER BY value_order
				"				
				, 
				array ( $option_group )
				);		
			$options = $wpdb->get_results ( $option_sql );
			// if some found, add to cache and return the found array
			if ( is_array ( $options ) && count ( $options ) > 0 ) {
				$this->option_values_cache[$option_group] = array();
				foreach( $options as $option ) { 
					array_push ( $this->option_values_cache[$option_group], 
						array ( 'value' => $option->option_value,
								'label' => $option->option_label,
								'is_system_reserved' => $option->is_system_reserved,
						)  
					);			
				}
			// if none found, store empty string
			} else {
				$this->option_values_cache[$option_group] = '';
			}
			return $this->option_values_cache[$option_group]; // if none found, returning empty string 
		}
	}
	
	/***********************************************************************
	*
	* method supporting option value groups -- return an array of only reserved values
	*
	************************************************************************/	
	public function get_reserved_option_values ( $option_group ) { 
		// get option_values (from cache or db )
		$options = $this->lookup_option_values ( $option_group );
		// initialized reserved values array
		$reserved_values = array();
		if ( is_array( $options ) ) {
			foreach ( $options as $option ) {
				if ( 1 == $option['is_system_reserved'] ) {
					array_push ( $reserved_values, $option['value'] );
				}
			}
		} 	
		return $reserved_values;
	}	
	
	/*************************************************************************
	*	
	* Basic methods supporting setup of data object array for entities
	*
	**************************************************************************/	
	
	// assemble fields for an entity -- n.b. as rewritten, limits the assembly to fields assigned to form field groups
	// does not force groups to be implemented though, since not joining to groups table 
	// similarly, assignment of field to non-existing or blank group means will not appear in forms, but could be used elsewhere
	// -- last_updated_by and last_updated_time for subsidiary entities handled with blank group, so can access in advanced search
	public  function get_form_fields ( $entity ) {
		// returns array of row objects, one for each field 
	
		$fields_array = array();
		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( $entity == $field_rule->entity_slug && $field_rule->group_slug > '' ) {
				$fields_array[] = ( new WIC_DB_Field_List_Object ( $field_rule->field_slug, $field_rule->field_type, $field_rule->field_label, $field_rule->listing_order, $field_rule->list_formatter ) );			
			}		
		}
		
		return ( $fields_array );		
		
	}	

	// expose the rules for all fields for an entity -- only called in control initialization;
	// rules are passed to each control that is in the data object array directly -- no processing;
	// the set retrieved by this method is not limited to group members and might support a data dump function, 
	// 	but in the online system, only the fields selected by get_form_fields are actually set up as controls  
	public  function get_field_rules ( $entity, $field_slug ) {
		// this is only called in the control object -- only the control object knows field details
		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( $entity == $field_rule->entity_slug && $field_slug == $field_rule->field_slug ) {
				return ( $field_rule );			
			}		
		}
		WIC_Function_Utilities::wic_error ( sprintf( ' Field rule table inconsistency -- entity (%1$s), field_slug (%2$s).',  $entity, $field_slug  ), __FILE__, __LINE__, __METHOD__, true );
	}

	/*************************************************************************
	*
	* Method supporting wp db interface
	*
	**************************************************************************/
	public  function get_field_list_with_wp_query_parameters( $entity ) {

		$entity_fields = array();
		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( $entity == $field_rule->entity_slug ) {
				$entity_fields[$field_rule->field_slug] = $field_rule->wp_query_parameter;	
			}
		}	
		
		return $entity_fields;
	}	
	
	/*************************************************************************
	*	
	* Methods supporting list display -- sort order and shortened field list 
	*
	**************************************************************************/	

	// return string of fields for inclusion in sort clause for lists
	public  function get_sort_order_for_entity ( $entity ) {

		$sort_string = array();
		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( $entity == $field_rule->entity_slug && $field_rule->sort_clause_order > 0 )  {
				$sort_clause_entry = $field_rule->field_slug . ' ' . ( $field_rule->reverse_sort ? 'DESC' : '' );
				$sort_string[$field_rule->sort_clause_order] = $sort_clause_entry;
			}		
		}
		// note that ksort drops elements with identical sort_clause_order
		ksort( $sort_string );
		$sort_string_scalar = implode ( ',', $sort_string );
		
		return ( $sort_string_scalar );

	}

	/*
	* return short list of fields for inclusion in display in lists (always include id) 
	* also used in assembly of shortened data object array for lists
	* note that select !=0 listing order -- this allows negative listing orders to be selected by query 
	* all lists show only positive listing values, but issue list uses negative listing order fields for highlighting due dates
	*  -- so, have to carry listing_order even though returned as sorted
	*/
	public  function get_list_fields_for_entity ( $entity ) {
		
		// note: negative values for listing order will be included in field list for data retrieval and will be available for formatting
		// but will not be displayed in list
		$list_fields = array();
		foreach ( $this->field_rules_cache as $field_rule ) {
			if  ( 
					$entity == $field_rule->entity_slug && 
					( 
						$field_rule->listing_order != 0 || 
						'ID' == $field_rule->field_slug || 
						'custom_field_' == substr ( $field_rule->field_slug, 0, 13 )
					) 
				) 
			{
				if ( 'custom_field_' == substr ( $field_rule->field_slug, 0, 13 ) ) {
					$listing_order = 1000 + substr ( $field_rule->field_slug, 13, 3 );
				} else {
					$listing_order = $field_rule->listing_order;
				} 
				$list_fields[ $listing_order ] = $field_rule;
			}		
		}
		
		// note that ksort drops elements with identical listing_order
		ksort ( $list_fields );
		
		$list_fields_sorted = array();
		foreach ( $list_fields as $key=>$field_rule ) {
			$list_fields_sorted[] = new WIC_DB_Field_List_Object ( $field_rule->field_slug, $field_rule->field_type, $field_rule->field_label, $key, $field_rule->list_formatter );  		
		} 
		return ( $list_fields_sorted );
		
	}	

	// used to return list of fields for consolidating constituent dup records
	public function list_non_multivalue_fields ( $entity ) {
		$non_multivalue_fields = array();
		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( 
				$entity == $field_rule->entity_slug &&
				! $field_rule->transient && 
				'multivalue' != $field_rule->field_type && 
				'registration' != $field_rule->group_slug ) { // ID and updated tracking fields for constituent 
					$non_multivalue_fields[] = $field_rule->field_slug;
			}
		}
		return $non_multivalue_fields;
	}

	public function get_option_label ( $entity_slug, $field_slug, $value ) {
		// used in search log to display labels
		$option_group = '';

		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( $entity_slug == $field_rule->entity_slug && $field_slug == $field_rule->field_slug ) {
				$option_group = $field_rule->option_group;
				break;			
			}		
		}
		
		$option_values = $this->lookup_option_values ( $option_group );
		if ( is_array ( $option_values ) ) {
			// could be empty if $option_group value is actually the string name of a lookup method or function
			return WIC_Function_Utilities::value_label_lookup( $value, $option_values );
		} else {
			return ( '' );		
		}
		 
	}
	
	
	
	

	/*************************************************************************
	*	
	* Basic methods supporting forms  
	*
	**************************************************************************/	
	
	// retrieve the groups for a form with their properties	
	public  function get_form_field_groups ( $entity ) {
		// this lists the form groups
		global $wpdb;
		$table = $wpdb->prefix . 'wic_form_field_groups';
		$groups = $wpdb->get_results( 
			$wpdb->prepare (
				"
				SELECT group_slug, group_label, group_legend, initial_open, sidebar_location
				FROM $table
				WHERE entity_slug = %s
				ORDER BY group_order
				"				
				, array ( $entity )
				)
			, OBJECT_K );
			
		return ( $groups );
	}

	// this just retrieves the list of fields in a form group 
	public  function get_fields_for_group ( $entity, $group ) {

		$fields = array();
		
		foreach ( $this->field_rules_cache as $field_rule ) {
			
			if ( $entity == $field_rule->entity_slug && $group == $field_rule->group_slug ) {
				$fields[$field_rule->field_order] = $field_rule->field_slug;			
			}
		}

		ksort( $fields, SORT_NUMERIC );
		
		return ( $fields );
	}

	// this just retrieves the list of fields in a form group 
	public  function get_fields_for_group_with_labels ( $entity, $group ) {

		$fields = array();
		
		foreach ( $this->field_rules_cache as $field_rule ) {
			
			if ( $entity == $field_rule->entity_slug && $group == $field_rule->group_slug ) {
				$fields[$field_rule->field_order] = array( 'field_slug' => $field_rule->field_slug, 'field_label' =>  $field_rule->field_label );			
			}
		}

		ksort( $fields, SORT_NUMERIC );
		
		return ( $fields );
	}
	
	/*************************************************************************
	*	
	* Special methods for assembling generic message strings across groups
	*   	-- these functions play no role in validation or any processing, 
	*		-- they only format info
	*
	**************************************************************************/

	// return string of dup check fields for inclusion in error message
	public  function get_dup_check_string ( $entity ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wic_data_dictionary';
		$dup_check_string = $wpdb->get_row( 
			$wpdb->prepare (
					"
					SELECT group_concat( field_label SEPARATOR ', ' ) AS dup_check_string
					FROM $table 
					WHERE entity_slug = %s and dedup = 1 and enabled
					"				
					, array ( $entity )
					)
				, OBJECT );
	
		return ( trim( $dup_check_string->dup_check_string, "," ) ); 
	}

	// return string of required fields for required error message
	public  function get_required_string ( $entity, $type ) {
		
		$required_string = array();
		foreach ( $this->field_rules_cache as $field_rule ) {
			if ( $entity == $field_rule->entity_slug && $type == $field_rule->required )  {
				$required_string[] = $field_rule->field_label;
			}		
		}
		$required_string_scalar = implode ( ', ', $required_string );
		
		return ( $required_string_scalar );	
		
	}	
	
	public function get_uploadable_fields () {
		
		$uploadable_fields = array();
		
		// custom field upload order can run from 800 to 999
		// 1000 and up goes to the activity division
		$custom_field_base_order = 800;
		foreach ( $this->field_rules_cache as $field ) {
			$custom 	=  ( false !== stripos( $field->field_slug, 'custom_field_' ) );
			$uploadable =  ( ( 0 < $field->uploadable ) || $custom );
			if ( $uploadable ) {
				$uploadable_fields[] = array ( 
					'entity' => $field->entity_slug, 
					'group' => $field->group_slug,
					'field'	=> $field->field_slug,
					'type'	=> $field->field_type,
					'label'	=> $field->field_label,
					'order'	=> $custom ? $custom_field_base_order : $field->uploadable,
					'wp_query_parameter' => $field->wp_query_parameter,
				 );	
				 if ( $custom ) {
				 	$custom_field_base_order++;	
				 }		
			}
		}	
		
		$test = usort ( $uploadable_fields, array ( $this, "uploadable_sort_order" ) );		

		return ( $uploadable_fields );
	}

	// support sorting of uploadable fields by uploadable order
	private function uploadable_sort_order ( $field1, $field2 ) {
		if ( $field1['order'] == $field2['order'] ) { 
			$compare = 0;		
		} else {
			$compare =  $field1['order'] < $field2['order'] ? -1 : 1; 
		}
		return ( $compare );		
	}
	
	// retrieve custom fields with labels (formatted for use in upload match process)
	// also used in export process
	public function custom_fields_match_array () {
		$custom_fields_match_array = array();
		foreach ( $this->field_rules_cache as $field ) {	
			if ( false !== stripos( $field->field_slug, 'custom_field_' ) ) {
				$custom_fields_match_array[$field->field_slug] = array(
					'label'			=>	$field->field_label,
					'link_fields'	=> array(
						array( 'constituent', $field->field_slug, 0 ),
					),
				);
			}
		} 
		return ( $custom_fields_match_array );
	}



	/*
	*
	* Field inventories for advanced search forms (handles entity constituent and activity)
	*
	*/
	private function get_search_fields_array( $entity ) {

		$search_fields_array = array();
		foreach ( $this->field_rules_cache as $field ) {	
			if ( $entity == $field->entity_slug ) {
				// this branch only relevant for $entity == 'constituent', no activity multivalue fields 
				// gathering address, phone and email fields
				if ( 'multivalue' == $field->field_type ) { 
					if ( 'activity' != $field->field_slug ) {					
						$search_fields_array = array_merge ( $search_fields_array, $this->get_search_fields_array ( $field->field_slug ) );
					}			
				} else {
					if ( 0 == $field->transient && 0 == $field->hidden ) {
							$search_fields_array[$field->entity_slug . $field->field_slug ] = array(
								'ID'					=>	$field->ID,
								'entity_slug'		=> $field->entity_slug,
								'field_slug'		=> $field->field_slug,
								'field_type'		=>	$field->field_type,
								'field_label'		=>	$field->field_label,
								'option_group'		=>	$field->option_group
							);
					}
				}
			}
		} 

		return ( $search_fields_array );
	} 

	private function get_sorted_search_fields_array ( $entity ) {
		
		$search_fields_array = $this->get_search_fields_array( $entity ); 
		ksort ( $search_fields_array );
		$sorted_return_array = array();
		foreach ( $search_fields_array as $key => $field_array ) {
			$sorted_return_array[] = $field_array;		
		} 
		return ( $sorted_return_array );		
	
	}

	public function get_search_field_options ( $entity ) {
		
		$financial_activity_types_activated = false;
		$wic_option_array = get_option('wp_issues_crm_plugin_options_array');
		if ( isset ( $wic_option_array['financial_activity_types'] ) ) {
			if ( trim($wic_option_array['financial_activity_types']) > ''  ) {
				$financial_activity_types_activated = true;			
			}		
		}		
		
		$entity_fields_array = $this->get_sorted_search_fields_array( $entity );

		// note: do not supply a blank value -- this obviates need for test blank field value
		$entity_fields_select_array = array(); 
		
		foreach ( $entity_fields_array as $entity_field ) {
			if ( $financial_activity_types_activated || 'activity_amount' != $entity_field['field_slug'] ) // filter amount from options retrieved if not financial
			$entity_fields_select_array[] = array (
					'value' => $entity_field['ID'],
					'label' => $entity_field['entity_slug'] . ':' . $entity_field['field_slug'] . ' ( "' . $entity_field['field_label'] . '" )'
				);
		}

		return ( $entity_fields_select_array );	
	
	}

	public function get_field_rules_by_id( $id ) {
		
		$field_rules_subset = array();
		// note that $id must exist in field rules cache since using select fields derived from cache		
		foreach ( $this->field_rules_cache as $field ) {
			if ( $id == $field->ID ) {
				return ( 
					array (
						'entity_slug'		=> $field->entity_slug,
						'field_slug'		=> $field->field_slug,
						'field_type'		=>	$field->field_type,
					)
				);
			}
		}
	}
	
	// fields with non blank defaults
	public function get_field_defaults_for_entity ( $entity ) {
		$fields = array();
		foreach ( $this->field_rules_cache as $field ) {
			if ( $entity == $field->entity_slug && $field->field_default > ''  ) {
				$fields[$field->field_slug] = $field->field_default;
			}
		}
		return $fields;
	}
	
	/*
	*
	* functions supporting synch sql construction 
	*
	*
	*/
	
	public function get_synch_fields_for_entity ( $entity ) {
		$fields = array();
		foreach ( $this->field_rules_cache as $field ) {
			if ( $entity == $field->entity_slug  && 
					!$field->transient && 
					!$field->hidden && // excludes constituent_id among other fields
					$field->field_type != 'multivalue' &&
					$field->enabled &&
					!in_array ( $field->field_slug, array ( 
						'ID', 
						'registration_synch_status', 
						'is_my_constituent', 
						'mark_deleted',
						'case_status',
						'case_assigned',
						'case_review_date',
						'address_type'
						) 
					) && 
					false === stripos( $field->field_slug, 'custom_field_' ) 
					) {
				$fields[] = $field->field_slug;
			}
		}
		return $fields;
	}

}