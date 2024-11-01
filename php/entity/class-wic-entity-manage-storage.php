<?php
/*
*
*	wic-manage-storage.php
*	does not correspond to a database entity, but supports transient dictionary entries
*
*/

class WIC_Entity_Manage_Storage extends WIC_Entity_Parent {
	
	
	/*
	*
	* Request handlers
	*
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'manage_storage';
	} 


	// handle a request for the form -- form always just shows defaults
	protected function form_manage_storage() {
		$this->new_blank_form ( 'WIC_Form_Manage_Storage' );	
	}

	protected function purge_storage() {
		// read form values and do purge;
		$this->populate_data_object_array_from_submitted_form();
		
		// purge staging tables if so chosen
		if ( 0 == $this->data_object_array['keep_staging']->get_value() ) {
			self::delete_staging_tables();
		}
		// purge logs if so chosen
		if ( 0 == $this->data_object_array['keep_search']->get_value() ) {
			$this->truncate_search_log();
			$this->truncate_synch_log();
		}		
		// purge constituents if fully authorized
		if ( 0 == $this->data_object_array['keep_all']->get_value() &&
			'PURGE CONSTITUENT DATA' == trim ( $this->data_object_array['confirm']->get_value() ) 		
			) {
			$this->purge_constituent_data();
		}

		// then show form
		$this->new_blank_form ( '', __( 'Previous purge completed. Results below.', 'wp-issues-crm' ) );
	}	
	
	public static function delete_staging_tables() {
		
		// do the staging table deletes	
		self::delete_tables_with_name_stub ( 'wic_staging' );

		global $wpdb;
				
		// truncate history table
		$upload_table = $wpdb->prefix . 'wic_upload';
		$sql =  "TRUNCATE $upload_table";
		$wpdb->query ( $sql );

		// truncate temp storage table ( not emptied at time of upload as can return from all stages except non-express completion )
		$upload_table = $wpdb->prefix . 'wic_upload_temp';
		$sql =  "TRUNCATE $upload_table";
		$wpdb->query ( $sql );		
	}	

	public static function delete_tables_with_name_stub ( $name_stub ) {
	
		global $wpdb;
		$full_stub = $wpdb->prefix . $name_stub .'%';
		$sql = "SHOW TABLES LIKE '$full_stub'";
		$stub_tables = $wpdb->get_results( $sql, ARRAY_A );

		// run through list of staging tables and delete all
		if ( is_array ( $stub_tables ) ) {
			foreach ( $stub_tables as $stub_table ) {
				foreach ( $stub_table as $key => $value ) {
					$sql = "DROP TABLE IF EXISTS $value";
					$wpdb->query( $sql );			
				}
			}
		}
	}


	private function truncate_search_log () {
		global $wpdb;
		$search_log = $wpdb->prefix . 'wic_search_log';
		$sql = "TRUNCATE $search_log";
		$wpdb->query( $sql );
	
	}

	private function truncate_synch_log () {
		global $wpdb;
		$synch_log = $wpdb->prefix . 'wic_inbox_synch_log';
		$sql = "TRUNCATE $synch_log";
		$wpdb->query( $sql );
	}

	// this function is not currently in use (v4.1.1)
	private function purge_deleted_inbox_messages () {
		global $wpdb;
		$wic_inbox_image = $wpdb->prefix . 'wic_inbox_image';
		$md5_table = $wpdb->prefix . 'wic_inbox_md5';
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';	// this table includes all message attachments, not just inbox attachments
		$sql = "DELETE m, m5, a 
			FROM $wic_inbox_image m LEFT JOIN 
				$md5_table m5 on m5.inbox_message_id = m.ID LEFT JOIN
				$attachments_xref_table a on a.message_id = m.ID
			WHERE no_longer_in_server_folder = 1 ";
		$wpdb->query( $sql );
		self::purge_orphan_attachments();
	}

	public static function purge_orphan_attachments() {
		global $wpdb;
		$attachments_table = $wpdb->prefix . 'wic_inbox_image_attachments';	// this table includes all message attachments, not just inbox attachments
		$attachments_xref_table = $wpdb->prefix . 'wic_inbox_image_attachments_xref';	// this table includes all message attachments, not just inbox attachments

		$sql = "DELETE a FROM 
			$attachments_table a LEFT JOIN 
			$attachments_xref_table x 
			ON a.id = x.attachment_id
			WHERE x.attachment_id is NULL
			";
		$wpdb->query ( $sql );
	}


	private function purge_constituent_data() {
		
		global $wpdb;
		
		$constituent= $wpdb->prefix . 'wic_constituent';
		$activity	= $wpdb->prefix . 'wic_activity';
		$phone 		= $wpdb->prefix . 'wic_phone';
		$email 		= $wpdb->prefix . 'wic_email';
		$address 	= $wpdb->prefix . 'wic_address';	
	
		$having = '';

		if ( 1 == $this->data_object_array['keep_activity']->get_value() ) {
			$having .= " max( if ( a.constituent_id is not null, 1, 0 ) ) = 0 ";
		}
		if ( 1 == $this->data_object_array['keep_phone']->get_value() ) {
			$having = ( '' < $having ) ? $having . ' AND ' : $having;
			$having .= " max( if ( p.constituent_id is not null, 1, 0 ) ) = 0 ";
		}
		if ( 1 == $this->data_object_array['keep_email']->get_value() ) {
			$having = ( '' < $having ) ? $having . ' AND ' : $having;
			$having .= " max( if ( e.constituent_id is not null, 1, 0 ) ) = 0 ";
		}
		if ( 1 == $this->data_object_array['keep_address']->get_value() ) {
			$having = ( '' < $having ) ? $having . ' AND ' : $having;
			$having .= " max( if ( ad.constituent_id is not null, 1, 0 ) ) = 0 ";
		}

		$having = ( '' < $having ) ? 'HAVING ' . $having : '' ;

		$purge_temp = $wpdb->prefix . 'wic_purge_temp';	
	
		$sql = 	" CREATE TEMPORARY TABLE $purge_temp 
					SELECT c.ID FROM 
					$constituent c LEFT JOIN 
					$activity a 	on a.constituent_id = c.id LEFT JOIN
					$phone p 		on p.constituent_id = c.id LEFT JOIN
					$email e 		on e.constituent_id = c.id LEFT JOIN  
					$address ad 	on ad.constituent_id = c.id
					GROUP BY c.id
					$having
					";
		$wpdb->query ( $sql );

		$wpdb->query ( "DELETE c from $purge_temp t LEFT JOIN $constituent c on c.ID = t.ID" );
		$wpdb->query ( "DELETE a from $purge_temp t LEFT JOIN $activity a on a.constituent_id = t.ID" );
		$wpdb->query ( "DELETE p from $purge_temp t LEFT JOIN $phone p on p.constituent_id = t.ID" );
		$wpdb->query ( "DELETE e from $purge_temp t LEFT JOIN $email e on e.constituent_id = t.ID" );
		$wpdb->query ( "DELETE ad from $purge_temp t LEFT JOIN $address ad on ad.constituent_id = t.ID" );
		
		$wpdb->query ( "OPTIMIZE TABLE $constituent" );
		$wpdb->query ( "OPTIMIZE TABLE $activity" );
		$wpdb->query ( "OPTIMIZE TABLE $phone" );
		$wpdb->query ( "OPTIMIZE TABLE $email" );
		$wpdb->query ( "OPTIMIZE TABLE $address" );
	}	
	
	
	public static function delete_deleted () {
		
		global $wpdb;
		
		$constituent= $wpdb->prefix . 'wic_constituent';
		$activity	= $wpdb->prefix . 'wic_activity';
		$phone 		= $wpdb->prefix . 'wic_phone';
		$email 		= $wpdb->prefix . 'wic_email';
		$address 	= $wpdb->prefix . 'wic_address';	
		
		$sql = "DELETE a, ad, p, e, c FROM 
				$constituent c LEFT JOIN 
				$activity a 	on a.constituent_id = c.id LEFT JOIN
				$phone p 		on p.constituent_id = c.id LEFT JOIN
				$email e 		on e.constituent_id = c.id LEFT JOIN  
				$address ad 	on ad.constituent_id = c.id
				WHERE c.mark_deleted = 'deleted'";
		$result = $wpdb->query ( $sql );
		$deleted = false !== $result;
		return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => $deleted, 'reason' => $deleted ? 'Any and all "Deleted" constituents physically deleted.' : 'Database error on attempted delete.' ) );
					
	}
	
	
	
	
}