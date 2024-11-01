<?php
/*
*
*	wic-entity-upload.php
*
*  File upload is handled in stages, with js modules and helper php classes for each
*  Some stage transitions ( parse parameter setting, mapping and default setting ) are just user input and can be redone on same form
*  Other stages that involves processing -- parsing, validation, matching, actual database update ( but not actual physical file upload) can be restarted
*		for parsing, validation, matching -- always start with a reset, so can move forward from there
*		for actual database update, not a reset, but a restart/rerun 
*			final_result counts will be correct on a restart/rerun
*	
*
*	STAGE NOTES		
*		-- copy to server 
*			+	status: initialized (when send first chunk) -- cannot return to this status
*				-- interrupt at this stage leaves chunks in temp file and upload stub
*			+	status: copied (when all chunks complete) -- show WIC_Form_Upload (as if from a top button press in upload-upload.js)
*				can return to this status -- parse process does reset before continuing -- so can cancel or return to set parameters screen
*			+   note that at this stage, charset of incoming file is irrelevant since stored in binary field and reconstituted as flat file before further processing
*		-- parse file into staging table of records
*			+   status: staged	( return to this stage is return to mapping screen with no fields mapped) 
*			+ 	character decoding is applied to values within records successfully by fgetcsv, so delimiter/enclosure/escape have to be ASCII, but values can be a supported charset 	
*		-- map fields (fully flexible -- the mapping is redoable and can return to this tage)
*			+   status: mapped (valid mapping, but individual record validation not yet passed)
*		-- validate data
*			+ status: validated -- validation is reset before proceeding from map screen to validation popup so can cancel process or return to map screen
*			+ IF the field is mapped, all form edits are applied -- sanitization and validation and individual required check; 
*			+ If mapped, a select field must have good values (validation implicit in form context)  
*			+ Additionally, IF the constituent identity field is mapped it is validated as a good ID
*		-- matching
*			+ status: matched -- mapping is reset before proceeding so can cancel process or return to match screen
*			+ user can select match of input to existing records from valid identity-specifying combinations or custom fields
*			+ hierarchy of matching order is suggested to user, but user can override
*			+ apart from constituent ID and any custom fields, all permitted matching modes include at least one of identity fields ( fn/ln/email ) 
*			+ this step builds a preupdate constituent stub table (which is dropped on reset)
*			+ MUST EITHER MATCH OR FAIL A MATCH ALTHOUGH HAVING THE NECESSARY FIELDS TO TRY THE MATCH TO GET UPDATED OR INSERTED IN FINAL UPLOAD
*		-- set defaults for constituent	
*			+ status: defaulted -- updated dynamically on and off according to user selections on this screen -- can return here
*			+ Determine basic add/update behavior for matched records
*			+ Allow option to not overwrite good names and addresses with possibly sloppy names and addresses -- set default do not overwrite
*			+ Only allow defaulting of unmapped fields -- if a column can be replaced in part with defaults, user should unmap it and replace it entirely
*			+ also set defaults for activities ( in same tab as constituents )
*			+ Only allow defaulting of unmapped fields -- if a column can be replaced in part with defaults, user should unmap it and replace it entirely
*			+ If issue number is mapped or is defaulted to non-blank, it controls; otherwise look to title
*		-- actual update
*			+ status is set to started on start -- cannot return earlier stages, but can pick up updates from that status 
*			+ on complete, get status completed 
*			+ if have matched OLD record, must make decision about what to update
*				* for fn/ln and other constituent record information, go by "protect primary identity" indicator
*				* for address, if new type add, if existing type, go by "protect identity" indicator
*				* for email/phone, if new type add, if old type, update if non-blank 
*				* use defaults consistent with these rules where fields unmapped, as if they were the original values
*				* note that can set (and default setting is) to prevent overwrite of data with blank data
*			+ for new records, EZ -- add all; use supplied default
*		-- express update: goes straight from mapped to upload without building intermediate tables has own restart status -- started_express, completed_express
*		
*	Enforcement of proper staging sequence by access sequence.
*
*   Enforcement of Validation and Field Required rules in the upload is explained at: http://wp-issues-crm.com/?page_id=220
*
*
*
*/



class WIC_Entity_Upload extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) {
		$this->entity = 'upload';
		$this->entity_instance = '';
	} 
	
	public static function load_form ( $id, $action ) {
		$upload_entity = new WIC_Entity_Upload ( 'nothing', array());
		$success_form = $action? 'WIC_Form_Upload_' . $action : 'WIC_Form_Upload';
		return array ( 	
			'response_code' => true, 
			'output' => $upload_entity->id_search (  array( 'id_requested' => $id, 'success_form' => $success_form, 'return_object' => true ) )
		);
	}
	// supports creation of this entity without taking an action in construct
	protected function nothing(){}

	
	protected function go_to_current_upload_stage( $args ) { 
	
		// get upload status
		$id = $args['id_requested']; 
		$status = self::get_upload_status($id)['output'];
		// translate status to correct form
		$status_to_form_array = array (
			'initiated'		 	=>	'_Inaccessible',
			'copied' 			=>	'',
			'staged'			=>	'_Map',
			'mapped'			=>	'_Map',
			'validated'			=>	'_Match',
			'matched'			=>	'_Set_Defaults',
			'defaulted' 		=>	'_Set_Defaults',
			'started'			=>	'_Download',
			'started_express' 	=>	'_Download',
			'completed'			=>	'_Download',
			'completed_express' =>	'_Download',
			'reversed'			=>	'_Download',
		);
		
		$success_form = 'WIC_Form_Upload' . $status_to_form_array[$status];
		
		$this->id_search (  array( 'id_requested' => $id, 'success_form' => $success_form ) ); // nb, return_object is treated as true by id_search if set
	}

	// quick update
	public static function update_upload_status ( $upload_id, $upload_status ) {
		global $wpdb;  
		$table = $wpdb->prefix . 'wic_upload';
		$sql = "UPDATE $table set upload_status = '$upload_status' WHERE ID = $upload_id";
		$result = $wpdb->query( $sql );
		return array( 'response_code' => false !== $result, 'output' => false !== $result ? 
			__( 'Status update OK.', 'wp-issues-crm' ) 
			: 
			__( 'Error setting upload status.', 'wp-issues-crm' ) 
		);
	}

	// quick status 
	public static function get_upload_status ( $upload_id ) {
		global $wpdb;  
		$table = $wpdb->prefix . 'wic_upload';
		$sql = "SELECT upload_status from  $table WHERE ID = $upload_id";
		$result = $wpdb->get_results( $sql );
		return array( 'response_code' => isset ( $result[0] ), 'output' => isset ( $result[0] ) ? 
			$result[0]->upload_status
			: 
			__( 'Error getting upload status.', 'wp-issues-crm' ) 
		);
	}

	/**
	* get staging table for column validation and matching
	*/	
	public static function get_staging_table_records( $staging_table, $offset, $limit, $field_list ) {
		global $wpdb;
		$field_list = ( '*' == $field_list ) ? $staging_table . '.' . $field_list : $field_list;
		$sql = "SELECT STAGING_TABLE_ID, VALIDATION_STATUS, VALIDATION_ERRORS, 
				MATCHED_CONSTITUENT_ID, MATCH_PASS, 
 				FIRST_NOT_FOUND_MATCH_PASS, NOT_FOUND_VALUES,				
				$field_list FROM $staging_table LIMIT $offset, $limit";
		$result = $wpdb->get_results( $sql );
		return ( $result ); 
	}

   	// pass through from original entity, consistent with option value process -- supports set_defaults phase of upload
	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}

}
