<?php
/*
*
* class-wic-db-setup.php
*		accesses sql to create or update database and dictionary  
*/



class WIC_DB_Setup {

	/*********************************************************************************************
	*	
	*	database_setup()	
	*
	*	runs db delta to install/upgrade database
	*
	*  then runs routines to install/upgrade dictionary and options tables
	*
	*	note: all wic_issues_crm tables use the utf8 character set and utf8_general_ci collation  
	*
	*	note: version globals set in wp-issues-crm.php
	*
	*********************************************************************************************/
	private static function database_setup() { 

		global $wp_issues_crm_db_version; // see wp-issues-crm.php 
		$installed_version = get_option ( 'wp_issues_crm_db_version' ); 		
		

		// always START by marking version change to prevent conflicting runs of dbDelta in slow updates
		update_option( 'wp_issues_crm_db_version', $wp_issues_crm_db_version );		


		global $wpdb;		

		// drop unnecessary index in version 3.3 -- do this before dbdelta so field lengths will take
		// but don't know what version upgrading from or if new, so check for tables and index before action
		if ( $installed_version < '3.3' ) { 
			$inbox_table = $wpdb->prefix . 'wic_inbox_image';
			$tables = $wpdb->get_results( "SHOW TABLES LIKE '$inbox_table'");
			if ( $tables ) {
				$indexes = $wpdb->get_results( "SHOW INDEX FROM $inbox_table");
				if ( $indexes ) {
					$do_delete = false;
					foreach ( $indexes as $index ) {
						if ( 'inbox_image' == $index->Key_name ) {
							$do_delete = true; 
							break;
						}
					}	
					if ( $do_delete ) {
						$wpdb->query ( "ALTER TABLE $inbox_table DROP INDEX inbox_image" );
					} // if do_delete
				} // if $indexes
			} // if $tables
		} // if < 3.3


		/*
		* 
		* dbdelta is inefficient and unsafe in adding multiple fields
		* do direct add before db delta for multi field add in version 3.5 upgrade
		* do a delta like check anyway
		*
		*/
		if ( $installed_version < '3.5' ) { 
			$v_3_5_added_fields = array ( 
				 'year_of_birth',
				 'occupation',
				 'employer',
				 'registration_id',
				 'registration_synch_status',
				 'registration_date',
				 'registration_status',
				 'party',
				 'ward',
				 'precinct',
				 'council_district',
				 'state_rep_district',
				 'state_senate_district',
				 'congressional_district',
				 'councilor_district',
				 'county',
				 'other_district_1',
				 'other_district_2',
			);
			$special_field_types = array ( 
				 'year_of_birth' => 'varchar(4) NOT NULL',
				 'registration_synch_status' => 'char(1) NOT NULL',
				 'registration_date' => 'varchar(10) NOT NULL'
			);
			$constituent_table = $wpdb->prefix . 'wic_constituent';
			$tables = $wpdb->get_results( "SHOW TABLES LIKE '$constituent_table'");
			if ( $tables ) { // is table installed
				$fields = $wpdb->get_results( "SHOW FIELDS FROM $constituent_table");
				// construct field names array 
				$existing_fields = array();
				foreach ($fields as $field ) {
					$existing_fields[] = $field->Field;
				
				}
				$missing_fields = array_diff ( $v_3_5_added_fields, $existing_fields );
				if ( $missing_fields ) {
					$sql = "ALTER TABLE $constituent_table";
					$comma = '';
					foreach ( $missing_fields as $missing_field ) {
						$field_type = isset ( $special_field_types[$missing_field] ) ? $special_field_types[$missing_field] : "varchar(255) NOT NULL";
						$sql .= "$comma ADD $missing_field $field_type";
						$comma = ',';
					}
					$wpdb->query ( "LOCK TABLES $constituent_table WRITE" );
					$wpdb->query ( $sql );
					$wpdb->query ( "UNLOCK TABLES ");
				} // if $missing_fields
			} // if $tables
		} // if < 3.5




		
		// load the sql for table set up  
		$sql = file_get_contents( plugin_dir_path ( __FILE__ ) . '../../sql/wic_structures.sql' );

		// add site specific db prefixing
		$sql = self::site_table_names_in_sql ( $sql );

		// do the table creation/upgrades
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$result = dbDelta( $sql, true ); // execute = true

		// if not present add a full text index to post_title on wp_post table
		$table = $wpdb->posts;		
		$sql = "SHOW INDEXES IN $table";
		$result = $wpdb->get_results ( $sql );
		$ok_index = false;
		foreach ( $result as $index_component ) {
			if ( 	'post_title' 	== $index_component->Column_name &&
					'FULLTEXT' 		== $index_component->Index_type &&
					1 				== $index_component->Seq_in_index	
				) {
					$ok_index = true;
					break;				
				}		
		}
		if ( ! $ok_index )  {
			$sql = "CREATE FULLTEXT INDEX wp_issues_crm_post_title ON $table ( post_title )";		
			$wpdb->query ( $sql );
		}
		
		/*
		* install or upgrade data dictionary and field groups
		* wic_data_dictionary_and_field_groups.sql executes the following steps:
		*   - lock dictionary and option tables
		* 	- first purge dictionary all except custom fields
		* 	- second truncate field groups		
		* 	- insert dictionary records 
		*	- insert field group records
		*	- unlock tables
		*/
		self::execute_file_sql ( 'wic_data_dictionary_and_field_groups' );

		// populate interface table if not populated, otherwise don't touch it
		$interface = $wpdb->prefix . 'wic_interface'; 
		$sql = "SELECT upload_field_name from $interface LIMIT 0, 1";
		$results = $wpdb->get_results ( $sql );
		if ( 0 == count ( $results ) ) {
			self::execute_file_sql ( 'wic_interface_table' );
		}

		// install base version of option groups if first install ( updated through 2.4 )
		if ( false === $installed_version ) {
			self::execute_file_sql ( 'wic_option_groups_and_options' );	
		}

		// add ons in version 2.5
		if ( false === $installed_version || $installed_version < '2.5' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_005' );					
		}


		// add ons in version 2.6
		if ( false === $installed_version || $installed_version < '2.6' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_006' );					
		}	

		// add ons in version 2.7
		if ( false === $installed_version || $installed_version < '2.7' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_007' );					
		}
		
		// add ons in version 2.8
		if ( false === $installed_version || $installed_version < '2.8' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_008' );					
		}
		// add ons in version 2.9
		if ( false === $installed_version || $installed_version < '2.9' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_009' );					
		}
		// add ons in version 3.1
		if ( false === $installed_version || $installed_version < '3.1' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_010' );					
		}

		// select becomes selectmenu in version 3.3
		if ( false === $installed_version || $installed_version < '3.3' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_011' );					
		}

		// standardize autorecorded email type
		if ( false === $installed_version || $installed_version < '3.3.1' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_012' );					
		}

		if ( false === $installed_version || $installed_version < '3.4' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_013' );					
		}
		
		if ( false === $installed_version || $installed_version < '3.4.0.5000' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_014' );					
		}

		if ( false === $installed_version || $installed_version < '3.4.3' ) {
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_015' );					
		}		

		if ( false === $installed_version || $installed_version < '3.5' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_016' );					
		}
		if ( false === $installed_version || $installed_version < '3.5.2' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_017' );					
		}
		if ( false === $installed_version || $installed_version < '3.7' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_018' );					
		}		
		if ( false === $installed_version || $installed_version < '3.8.4' ) {
			// unnecessary to serialize this option because update_option serializes itself; standarding option access on safer get_processing_options function
			$processing_options = get_option ( 'wp-issues-crm-email-processing-options' ); 
			if ( is_serialized ( $processing_options ) ) {
				@update_option( 'wp-issues-crm-email-processing-options', unserialize ( $processing_options ) );					
			}
		}
		if ( false === $installed_version || $installed_version < '3.8.5' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_019' );					
		}
		if ( false === $installed_version || $installed_version < '3.8.62' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_020' );					
		}
		if ( false === $installed_version || $installed_version < '4.0.5' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_021' );					
		}		
		if ( false === $installed_version || $installed_version < '4.0.6' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_022' );					
		}
		if ( false === $installed_version || $installed_version < '4.2.0.1' ) { 
			self::execute_file_sql ( 'wic_option_groups_and_options_upgrade_023' );					
		}	
		/*
		* back fill option_group_id based on parent_option_group_slug for upgrades to option table 
		*  -- note that this query will include any groups user added between upgrades, since parent_option_group_slug not maintained elsewhere 
		*		
		* parent_option_group_slug is used only in the upgrade process (and one hard_coded reference in class-wic-db-access-dictionary.php)
		*
		* this step must run after all upgrades/additions to option_value and option_group
		*
		* it maintains integrity of multivalue logic (which relies on ID) while allowing upgrade 
		* to make additions to both option_group table and option_value table with indeterminate option_group_id's
		*
		* insert statements to option_value in upgrades should include parent_option_group_slug, but not option_group_id
		*/
		$option_group = $wpdb->prefix . 'wic_option_group'; 
		$option_value = $wpdb->prefix . 'wic_option_value';
		$sql = "UPDATE $option_value v INNER JOIN $option_group g ON v.parent_option_group_slug = g.option_group_slug 
			SET v.option_group_id = g.ID where option_group_id = '' ";
		$wpdb->query ( $sql );

		// force a run of role set up attached to activation hook (to reflect role definition change)
		if (  $installed_version < '2.4.2' ) {
			WIC_Admin_Setup::wic_set_up_roles_and_capabilities();			
		}

		// always finish by marking version change completed
		update_option( 'wp_issues_crm_db_version_completed', $wp_issues_crm_db_version );		

	}
	
	public static function update_db_check () { 

		global $wp_issues_crm_db_version; // see wp-issues-crm.php

		// check if database up to date; if not, run setup 
		// single version check covers database and dictionary -- unlikely that increase churn by combining
		$installed_version = get_option( 'wp_issues_crm_db_version');
		if ( $wp_issues_crm_db_version != $installed_version ) { // returns false if absent, so also triggered on first run
			self::database_setup();
		}

	}	

	public static function check_database_upgraded_ok() {
		if ( ! get_option ( 'wp_issues_crm_db_version_completed' ) || (  get_option ( 'wp_issues_crm_db_version_completed ') <   get_option ( 'wp_issues_crm_db_version' ) ) ){
			return 
				'<div class = "wp-issues-crm-non-fatal-error">	
					<h3>The last database upgrade has not completed successfully.</h3>
					<p>This condition should resolve shortly.  Refresh screen to check progress.</p>
					<p>If this condition does not resolve in minutes, it means that the last upgrade was interrupted by a system outage or hosting company resource limitation.
					Your database may be in a partially upgraded condition.</p><p>Do not panic:  No data has been lost and you may be able to continue to use WP Issues CRM without major difficulty.  But you should resolve this message.</p>
					<p>You have the following options:</p><ol>
						<li>If you have access to phpMyAdmin, go to the wp_option table, find the record with name option_name = "wp_issues_crm_db_version"	and update the option value on that record to equal "' .
						 ( get_option ( 'wp_issues_crm_db_version_completed ') ? get_option ( 'wp_issues_crm_db_version_completed ') : '3.4.3' ) .'"
						This will cause  WP Issues CRM to reattempt the upgrade when you next access it.</li>
						<li>Restore your database and then let WP Issues CRM reattempt the upgrade when you next access it.  This is a heavier handed way of reattempting the upgrade, but avoids the possibility of minor continuing annoyances like duplicate entries in drop-down option lists.</li>
						<li>Contact WP Issues CRM help -- <a href="mailto:help@wp-issues-crm.com">help@wp-issues-crm.com</a> for a more fine tuned solution.</li></ol></div>';
		} else {
			return '';
		}
	}

	
	public static function execute_file_sql ( $file_name ) {		
		global $wpdb;		
		
		// load the table set up sql 
		$sql = file_get_contents( plugin_dir_path ( __FILE__ ) . '../../sql/' . $file_name . '.sql' );
		// add local file prefixes
		$sql = self::site_table_names_in_sql ( $sql );
		
		// execute statements one by one 
		$sql_array = explode ( ';', $sql );
		$outcome = true;
		foreach ( $sql_array as $sql_statement ) {
			if ( $sql_statement > '' ) {
				$result = $wpdb->query ( $sql_statement );
				if ( false === $result ) {
					$outcome = false;			
				}
			}
		}
		return ( $outcome );
		
	}
	
	// replace standard prefix with possible site table prefix
	private static function site_table_names_in_sql ( $sql ) {
		global $wpdb;
		$sql = str_replace ( ' wp_wic_', ' ' . $wpdb->prefix . 'wic_' , $sql );		
		return ( $sql );
	}

	// global tested in admin functions ( send/upload ) and cron mail processing (wp or cron tab invoked) 
	// set global to reflect database collation level -- increased for post version 4 installations, but not automatically upgraded
	public static function check_high_plane_collation() {
		global $wic_inbox_image_collation_takes_high_plane;
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';
		$wic_inbox_image_collation_takes_high_plane = false;
		$results = $wpdb->get_results ( "SHOW FULL FIELDS FROM $inbox_table");
		foreach ( $results as $field ) {
			if ( 'subject' == $field->Field ) {
				$wic_inbox_image_collation_takes_high_plane = ('utf8mb4' == substr($field->Collation,0,7));
				break;
			}
		}
	}

}

