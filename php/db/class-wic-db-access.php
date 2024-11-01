<?php
/*
*
* class-wic-db-access.php
*
*		Parent for direct db access objects (implemented as extensions to this.)
*			Two major extension _WP for Issue access and _WIC for access to the constituent and subsidiary tables
* 
*		Directly maintains search log (which includes saves) 
*		
*		Mapping of object type to appropriate extension of this class occurs at instantiation via WIC_DB_Access_Factory 
*
*/



abstract class WIC_DB_Access {
	
	// these properties contain  the results of the db access
	public $entity;				// top level entity searched for or acted on ( e.g., constituents or issues )
	public $parent;				// parent entity in form context (not parent class) -- e.g., activity, phone, email, address have constituent as parent
	public $sql; 				// for search, the query executed;
	public $result; 			// entity_object_array -- as saved, update or found( possibly multiple ) (each row as object with field_names properties)
	public $outcome; 			// true or false if error
	public $explanation; 		// reason for outcome
	public $found_count; 		// integer save/update/search # records found or acted on
	public $insert_id;			// ID of newly saved entity
	public $search_id;   		// ID of search completed -- search log
	public $retrieve_limit;		// page length ( number of items retrieved per page or max retrieval if not paging );
	public $list_page_offset; 	// page offset in list

	// extensions must include these functions
	abstract public function db_get_time_stamp ( $id );
	abstract protected function db_do_time_stamp ( $table, $id );
	abstract protected function db_save ( &$meta_query_array );
	abstract protected function db_update ( &$meta_query_array );
	abstract protected function db_delete_by_id ( $id );
	abstract protected function db_get_option_value_counts ( $field_slug );
	abstract public function search ( $meta_query_array, $search_parameters );


	public function __construct ( $entity ) { 
		$this->entity = $entity;
	}		

	// used in search log history retrieval -- spoofs a return from a search -- too complex to run through standard option 
	public function retrieve_search_log_latest () {
		global $wpdb;
		$search_log_table = $wpdb->prefix . 'wic_search_log';
		$user_id = get_current_user_id();
		$sql = "SELECT ID from $search_log_table where user_id = $user_id or is_named = 1
				ORDER BY is_named DESC, share_name, favorite desc, search_time DESC
				LIMIT 0, 100";
		$this->sql = $sql; 
		$this->result = $wpdb->get_results ( $sql );
		$this->found_count = count ( $this->result );
	}		

	/**********************************************************************************************************
	*
	*	Pass through for delete function -- this is the only delete function and its usage differs from other 
	*		db access functions.  Most are invoked by entity classes.  However, constituents are only soft deleted
	*		by marking them with the DELETED value.  Issues are not deletable except through  Wordpress admin.
	*  
	*		The hard database delete function is used for sub-entities ( e.g. email or activity) and is invoked in 
	*		WIC_Control_Multivalue -- when a form is received with deleted/hidden elements in it, those are discarded
	*		as the control object is loaded in the data_object_array.  If they have an ID, they are also physically deleted
	*		by a call to this function.
	*
	**********************************************************************************************************/

	public function delete_by_id ( $id ) {
		$this->db_delete_by_id ( $id );	
	}


	/**********************************************************************************************************
	*
	*	Main save/update process for WP Issues CRM -- runs across multiple layers, but is controlled by the process below 
	*	Note that this process is invoked by a parent entity after the data_object_array has been assembled; deletes occur in the array 
	*	assembly process.  
	*	(1) Top level entity contains an array of controls -- see wic-entity-parent
	*			+ Basic controls each contain a value which is information about the top level entity like name (not an object property technically, but logically so)
	*			+ Multivalue controls contain arrays of entities that have a data relationship to the top level entity, like addresses for a constituent 
	*  (2) Each multivalue entity, e.g., each address, is an entity with the same logical structure as the parent entity -- as a class, their entity is
	*		an extension of the parent entity.
	*  (3) So when update is submitted for the parent entity . . .
	*		 (1) The parent entity (e.g. constituent) creates a new instance of this class (actually always an extension of this class ) 
	*				and passes it a pointer to its array of controls 
	*        (2) Second this object->save_update asks each of the basic controls to produce a set clause 
	*		 (3) The set clauses are applied to the database by this object's WIC extension WIC_DB_Access_WIC 
	*				(straightforward insert update for a single entity)
	*		 (4) This object->save_update then asks each of the multivalue controls in turn to do an update
	*		 (5) Each multivalue control object in turn asks each of the row entities in its row array to do a save_update 
	*		 (6) Each multivalue entities (e.g. address) issues a save update request which comes back through an new instance of this object 
	*				and does only steps (1) through (3) for that object ( no multivalue controls within multivalue controls supported)
	*	(4) Note that deletes of WIC multivalues happens before step 1 as the array is populated from the form.  The delete function timestamps
	*		 the parent entity at that stage.  . . .
	*  (5) Timestamping ( last_updated_time, last_updated_by ) is handled as follows.
	*		 - On client, parent is marked as changed if any child changed or deleted 
	*		 - Each record does timestamp whenever it does update and does update only if is_changed.
	*		 - Note that a child add marks is_changed once actually update some field on the record (which is also required to trigger a save)
	*
	*	Note that the assembly of the save update array occurs in this top level database access class because
	*  updates are handled for particular entities (and this object serves a particular entity).
	*
	*	By contrast, the search array assembly is handled at the entity level because it needs to be able to report up to
	*  a multivalue control and contribute to a join across multiple entities.   
	*
	**********************************************************************************************************/	
	public function save_update ( &$doa ) { 

		// note: at top level form_save_update, this function is not invoked and $this->outcome is not tested unless the top level change flag is set 
		//  -- if it is invoked, is_changed === '1' . . . so
		// we are doing this return here only for multivalue row invocation of this function -- this occurs in wic_entity_multivalue->do_save_update
		//  -- invoked by wic_control_multivalue->do_save_updates which is, itself, invoked below in the top level pass through this function.
		if ( isset ( $doa['is_changed'] ) && '0' === $doa['is_changed']->get_value() ) {
			$this->outcome = true; // return true -- bypass is a good outcome ( multivalue row would report error if === false ) 
			return;
		}		

		$save_update_array = $this->assemble_save_update_array( $doa );
		// each non-multivalue control reports an update clause into the assembly
		// next do a straight update or insert for the entity in question 
		if ( count ( $save_update_array ) > 0 ) {
			if ( $doa['ID']->get_value() > 0 ) {
				// note that if the only changes are on the multivalue level, this is just a time stamp
				$this->db_update ( $save_update_array );		
			} else { 
				$this->db_save ( $save_update_array );
			}
		}

		// at this stage, the main entity update has occurred and return values for the top level entity have been set (preliminary) 
		// if main update OK, pass the array again and do multivalue ( child ) updates
		if ( $this->outcome ) {  
			// set the parent entity ID in case of an insert 
			$id = ( $doa['ID']->get_value() > 0 ) ? $doa['ID']->get_value() : $this->insert_id;
			$errors = '';
			// now ask each multivalue control to do its save_updates 			
			foreach ( $doa as $field => $control ) {
				if ( $control->is_multivalue() ) {
					// id for the parent (e.g., constituent) becomes constituent_id for the rows in the multivalue control
					$errors .= $control->do_save_updates( $id );
					// in multi value control, do_save_updates will ask each row entity within it to do its own do_save_updates
					// do_save_updates for each row will come back through this same save_update method 
				}			
			}
			if ( $errors > '' ) {
				$this->outcome = false;
				$this->explanation .= $errors;
			}
						
		}
		return;	
	}

	
	// abbreviated version of save update for use in the upload context
	// no multi value processing necessary, since each entity handled as a top entity in the upload process
	// allow decision as to whether to protect blanks from overwrite.
	public function upload_save_update ( &$doa, $protect_blank_overwrite ) { 
		if ( $protect_blank_overwrite ) {
			$save_update_array = $this->upload_assemble_save_update_array( $doa ); // drops blank values from the update array
		} else {
			$save_update_array = $this->assemble_save_update_array( $doa );
		}

		if ( count ( $save_update_array ) > 0 ) {
			if ( $doa['ID']->get_value() > 0 ) {
				$this->db_update ( $save_update_array );		
			} else {
				$this->db_save ( $save_update_array );
			}
		}
		// don't bother to set the insert id -- no further processing.
	}
	
	// for interface processing with known intent to save and build array in calling class
	public function direct_save ( $save_update_array ) {
		$this->db_save ( $save_update_array );
	}

	// for interface processing with known intent to save and build array in calling class
	public function direct_update ( $save_update_array ) {
		$this->db_update ( $save_update_array );
	}
	/*
	*
	*	Assemble save_update array from controls.  
	*		-- each control creates the appropriate save update clause which is added to array
	*		-- multivalue fields are excluded at this stage
	*		-- when do_save_update is called, it is acting only on individual entities, no multivalue
	*
	*/
	protected function assemble_save_update_array ( &$doa ) { 
		$save_update_array = array();
		foreach ( $doa as $field => $control ) {
			if ( ! $control->is_multivalue() ) {
				$update_clause = $control->create_update_clause();
				if ( '' < $update_clause ) {
					$save_update_array[] = $update_clause;
				}
			}
		}	
		return ( $save_update_array );
	}

	// special version for uploads where want to skip blank values in update process
	protected function upload_assemble_save_update_array ( &$doa ) {
		$save_update_array = array();
		foreach ( $doa as $field => $control ) {
			if ( ! $control->is_multivalue() ) {
				if ( $control->is_present() ) { // only do save-updates when control value non-blank
					$update_clause = $control->create_update_clause();
					if ( '' < $update_clause ) {
						$save_update_array[] = $update_clause;
					}
				}
			}
		}	
		return ( $save_update_array );
	}


	/*
	*
	* pass through for lister functions
	*
	*/

	public function list_by_id ( $id_string ) { 
		$this->db_list_by_id ( $id_string );   
	}


	public function get_option_value_counts ( $field_slug ) {
		return ( $this->db_get_option_value_counts ( $field_slug ) );	
	}

	public static function table_count ( $table_name ) {
		global $wpdb;
		// expects fully qualified table name with prefix
		$result = $wpdb->get_results ( "
			SELECT COUNT(*) as table_count from $table_name
			"
			);
		return ( $result[0]->table_count );	
	}

	// used to remove the item itself from an array of dups
	public function strip_an_item ( $id ) { 
		foreach ( $this->result as $key => $item ) {
			if ( $id == $item->ID ) {
				unset ( $this->result[$key]);
				break;
			}
		}
	}

	/* time stamp -- do time stamp on table */ 
	protected function do_time_stamp ( $table, $id ) {
		$this->db_do_time_stamp ( $table, $id );
	}

	/* time stamp -- get time stamp from entity table for requested id */ 
	public function get_time_stamp ( $id ) {
		return ( $this->db_get_time_stamp ( $id ) );  		 
	}

}