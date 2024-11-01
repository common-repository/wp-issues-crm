<?php
/*
* class-wic-list-constituent-export.php
*
* 
*/ 

class WIC_List_Activity_Export extends WIC_List_Constituent_Export {

	public static function do_activity_download ( $download_type, $search_id ) { 
		
		// naming the outfile
		$current_user = wp_get_current_user();	
		$file_name = 'wic-activity-export-' . $current_user->user_firstname . '-' .  current_time( 'Y-m-d-H-i-s' )  .  '.csv' ;
		// create temp table
		self::create_temp_activity_list ( $download_type, $search_id );

		// do the export off the previously created temp table of activity id's	
		$sql = self::assemble_activity_export_sql( $download_type ); 
		self::do_the_export( $file_name, $sql );

		if ( 'activities' == $download_type  ) { 
			WIC_Entity_Search_Log::mark_search_as_downloaded ( $search_id );
		}

		exit;
	} 
	
	/*
	*
	* create temporary table with activity ids
	*
	*/
	public static function create_temp_activity_list ( $download_type, $search_id ) {
			
		if ( 'activities' == $download_type ) { // coming from logged advanced search
			$search = WIC_Entity_Search_Log::get_search_from_search_log ( array( 'id_requested' => $search_id ) );	
			$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( $search['entity'] );
			$meta_query_array = $search['unserialized_search_array'];
			$search_parameters = array (
				'select_mode' 		=> 'download', // with this setting, object will create the temp table that export sql assembler is looking for
				'sort_order' 		=> true,
				'compute_total' 	=> false,
				'retrieve_limit' 	=> 999999999,
				'redo_search'		=> true,
				'old_search_id'		=> $search_id,				
			);
			$wic_query->search ( $meta_query_array, $search_parameters );
		} else { // from activity list on constituent, issue or email form
			global $wpdb;
			$join =	$wpdb->prefix . "wic_activity activity ";
			if ( 'issue' == $download_type ) {
				$join .= " left join " . $wpdb->prefix . "wic_constituent c on c.id = activity.constituent_id"; 
				$where = " activity.issue = $search_id "; 
			} elseif ( 'constituent' == $download_type ) {
				$where =  " activity.constituent_id = $search_id ";
			} else {
				exit;
			}				 
			// structure sql with the where clause
			$sql = "
					SELECT activity.ID  from $join 
					WHERE $where
					ORDER BY activity_date desc, activity.last_updated_time 
					";
			$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();			
			$sql = "CREATE temporary table $temp_table " . $sql . " LIMIT 0, 999999999";
			$temp_result = $wpdb->query  ( $sql );
			if ( false === $temp_result ) {
				WIC_Function_Utilities::wic_error ( sprintf( 'Error in download, likely permission error.' ), __FILE__, __LINE__, __METHOD__, true );
			}			
		} 	
	
	}
	
	
	/*
	*	based on temporary table created early in transaction, creates second temporary table
	*  returns sql to read second table
	*  
	*/
	public static function assemble_activity_export_sql ( $download_type ) { 
		
		// reference global wp query object	
		global $wpdb;	
		$prefix = $wpdb->prefix . 'wic_';
		
		$activity		= $prefix . 'activity';
		$constituent 	= $prefix . 'constituent';
		$email 			= $prefix . 'email';
		$phone			= $prefix . 'phone';
		$address		= $prefix . 'address';
		$post			= $wpdb->posts;
		
		// id list passed through user's temporary file wp_wic_temporary_id_list, lasts only through the server transaction (multiple db transactions)
		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();
		
		// pass activity list with full data to export through second temp table
		$temp_activity_table = $wpdb->prefix . 'wic_temporary_activity_list_' . time();		

		// strings include leading comma
		$custom_fields_string = WIC_List_Constituent_Export::custom_fields_string();
		$registration_fields_string = WIC_List_Constituent_Export::registration_fields_string();

		// initialize download sql -- if remains blank, will bypass download
		$download_sql = '';
		switch ( $download_type ) { // using switch to add possible formats later
			case 'constituent':
			case 'issue':
			case 'activities':		
				$download_sql = " 		
					SELECT  if( count(ac.ID) > 1,'yes','not necessary' ) as 'constituent_data_consolidated',
						activity_date, activity_type, activity_amount, pro_con, file_name, file_size,
						if (wp.ID IS NOT NULL, wp.post_title, concat('Hard Deleted Post ( ID was ',ac.issue,' )' ) ) as post_title, 
						first_name as fn, last_name as ln, middle_name as mn, 
						city, 
						email_address, 
						phone_number,
						address_line as address_line_1,
						concat ( city, ', ', state, ' ',  zip ) as address_line_2,
						state, zip, is_deceased as deceased, gender, date_of_birth as dob, year_of_birth as yob, occupation, employer $custom_fields_string $registration_fields_string,
						ac.issue, left(activity_note, 1000) as `First 1000 Characters of Activity Note`
					FROM $temp_table t inner join $activity ac on ac.ID = t.ID 
					LEFT JOIN $constituent c on c.ID = ac.constituent_id
					LEFT JOIN $post wp on wp.ID = ac.issue
					left join $email e on e.constituent_id = c.ID
					left join $phone p on p.constituent_id = c.ID
					left join $address a on a.constituent_id = c.ID	
					GROUP BY ac.ID";	
				break;
		} 	
	
   	// go direct to database and do customized search and write temp table
		$sql = 	"CREATE TEMPORARY TABLE $temp_activity_table
					$download_sql
					"; 
		
		$result = $wpdb->query ( $sql );

		// pass back sql to retrieve the temp table
		$sql = "SELECT * FROM $temp_activity_table ";

		return ( $sql);
	}

}	

