<?php
/*
*
*	class-wic-entity-geocode.php
*
*/
// https://github.com/Geocodio/geocodio-php
require	 dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'geocodio' . DIRECTORY_SEPARATOR .  'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
use Stanley\Geocodio\Client;

class WIC_Entity_Geocode {

	const WIC_GEOCODE_OPTION_ARRAY = 'wic-geocode-option-array';
	const WIC_GEOCODE_META_KEY = 'wic_geocode_meta_key';

	public static function get_google_maps_api_key() {

		if ( defined( 'WIC_GOOGLE_MAPS_API_KEY' ) && WIC_GOOGLE_MAPS_API_KEY ) {
			return WIC_GOOGLE_MAPS_API_KEY; 
		}
	
		$wic_settings = get_option( 'wp_issues_crm_plugin_options_array' );
		if ( isset ( $wic_settings['google_maps_api_key'] ) && $wic_settings['google_maps_api_key'] ) {
			return $wic_settings['google_maps_api_key'];
		}

		return false;		
	}
	
	public static function get_geocodio_api_key() {

		if ( defined( 'WIC_GEOCODIO_API_KEY' ) && WIC_GEOCODIO_API_KEY ) {
			return WIC_GEOCODIO_API_KEY; 
		}
	
		$wic_settings = get_option( 'wp_issues_crm_plugin_options_array' );
		if ( isset ( $wic_settings['geocodio_api_key'] ) && $wic_settings['geocodio_api_key'] ) {
			return $wic_settings['geocodio_api_key'];
		}

		return false;		
	}



	/*
	*
	* whether in single site or multisite, this runs as cron task every five minutes -- relates site address table to cache
	*
	* cache is central in multisite where central api key is configured
	*
	* cache doubles as queue for lookups
	*
	*/
	
	public static function update_geocode_address_cache() { 
	
		// no point if not using geocoding
		if ( ! self::get_google_maps_api_key() ) {
			return;
		}

		global $wpdb;
		$address_table = $wpdb->prefix . 'wic_address';
		// using central value in multisite?
		if ( defined ( 'WIC_GEOCODIO_API_KEY') ) {
			switch_to_blog( BLOG_ID_CURRENT_SITE );
			$cache_table = $wpdb->prefix . 'wic_address_geocode_cache'; 
			restore_current_blog();	
		} else {
			$cache_table = $wpdb->prefix . 'wic_address_geocode_cache'; 
		}
		// check for available geocodes
		$sql_find_geocodes = "
			UPDATE $address_table a LEFT JOIN $cache_table c ON
				address_line = address_raw AND city = city_raw AND state = state_raw AND zip = zip_raw
			SET a.lat = c.lat, a.lon = c.lon 
			WHERE a.lat = 0 AND a.lon = 0 and c.lat != 0 AND c.lon != 0
		";
		$wpdb->query ( $sql_find_geocodes );

		// update midpoint for maps
		$sql_midpoints = "SELECT AVG(lat) as mid_lat, AVG(lon) as mid_lon FROM $address_table WHERE lat != 0 and lat !=99";
		$midpoints = $wpdb->get_results ( $sql_midpoints );
		if ( $midpoints ) {
			self::set_geocode_option ( 'computed-map-midpoints', array ( $midpoints[0]->mid_lat, $midpoints[0]->mid_lon ) );
		}
	
		// store records needing geocoding in cache if not already there waiting
		$sql_save_new_cache_records = "
			INSERT INTO $cache_table ( address_raw, city_raw, state_raw, zip_raw )
			SELECT address_line, city, state, zip FROM $address_table a LEFT JOIN $cache_table c ON
				address_line = address_raw AND city = city_raw AND state = state_raw AND zip = zip_raw
				WHERE a.lat = 0 AND a.lon = 0 and c.ID IS NULL and city > '' and state > ''
				GROUP BY address_line, city, state, zip
			";
		$wpdb->query ( $sql_save_new_cache_records );	
	
	}

	
	public static function get_single_geocode( $address_string ) {
	
		// hook to plugin alternative geocode lookup routines
		if ( function_exists ( 'wp_wic_get_single_geocode_local') ) {
			return wp_wic_get_single_geocode_local( $address_string );
		}
	
		if ( ! $geocodio_api_key = WIC_Entity_Geocode::get_geocodio_api_key() ) {
			return false;
		}	
		// Create the new Client object by passing in  api key
		try {
			$client = new Client( $geocodio_api_key );
			$location = $client->geocode( $address_string );
			if ( ! $location->response->results || $location->response->results[0]->accuracy < .8 ){
				return array( (object) array ( "lat" => 99, "lng"=> 999 ), false );
			}
			$zip = isset (  $location->response->results[0]->address_components->zip ) ?  $location->response->results[0]->address_components->zip : '' ;
			return array ( $location->response->results[0]->location, $zip );	
		} catch (Exception $e) {
			self::log_geo ( 'WIC_Entity_Geocode::get_single_geocode ----------------------->>>> error:' . $e->getMessage() ) ; 	
			return false;
		}
		
	
	}
	
	
	
	public static function lookup_geocodes() { 
		
		// hook to plugin alternative geocode lookup routines
		if ( function_exists ( 'wp_wic_lookup_geocodes_local') ) {
			wp_wic_lookup_geocodes_local();
			return;
		}

		self::log_geo ( 'Starting lookup_geocodes.');

		if ( ! $geocodio_api_key = self::get_geocodio_api_key() ) {
			self::log_geo ( 'Terminating lookup_geocodes -- no API key.');
			return;
		}
	
		global $wpdb;
		// if multisite, maintaining a single cache, regardless -- use definition of WIC_GEOCODIO_API_KEY to indicate central service
		if ( is_multisite() && BLOG_ID_CURRENT_SITE != get_current_blog_id() && defined( 'WIC_GEOCODIO_API_KEY') ) {
			self::log_geo ( 'Terminating lookup_geocodes -- run from non_primary blog while WIC_GEOCODIO_API_KEY is defined.');
			return;
		} else {
			$cache_table = $wpdb->prefix . 'wic_address_geocode_cache'; 
		}

		$column_sql = "
			SELECT ID, CONCAT( address_raw, if(address_raw > '',', ', '' ), city_raw, ', ', state_raw, ' ', zip_raw ) as address_query
			FROM $cache_table
			WHERE lat = 0 AND LON = 0 AND  city_raw > '' and state_raw > ''
			LIMIT 0, 10000
		";

		if ( ! $cache_results = $wpdb->get_results ( $column_sql ) ) {
			self::log_geo ( 'Terminating lookup_geocodes -- no addresses found to geocode.');
			return;
		}; 
		
		$data = array();
		$cache_count = 0;
		foreach ( $cache_results as $result ) {
			$data[] = $result->address_query;
			$cache_count++;
		}
	
		// Create the new Client object by passing in  api key
		try {
			$client = new Client( $geocodio_api_key );
			$locations = $client->geocode( $data );	
		} catch (Exception $e) {
			self::log_geo ( 'WIC_Entity_Geocode::lookup_geocodes ----------------------->>>> error:' . $e->getMessage() ) ; 	
			return;
		}

		$good_results = 0;
		$bad_results = 0;
		$update_errors = 0;
		// record lat lon results
		foreach ( $locations->response->results as $i => $result ) {

			// empty array, or best guess (first) has low accuracy
			if ( ! $result->response->results || $result->response->results[0]->accuracy < .8 ) {
				self::log_geo( 'Unsuccessful address query for: ' . $cache_results[$i]->address_query );
				$lat = 99;
				$lon = 999;
				$bad_results++;
			} else {
				$lat = $result->response->results[0]->location->lat;
				$lon = $result->response->results[0]->location->lng;
				$good_results++;
			}

			$sql = "UPDATE $cache_table SET lat = $lat, lon = $lon WHERE ID = {$cache_results[$i]->ID}";
			$update_result = $wpdb->query ( $sql );
			// note errors, but do not handle them
			if ( 1 != $update_result ) {
				$update_errors++;
				self::log_geo ( "Return code of $update_result received on execution of sql: $sql" );
			}
		}
		self::log_geo ( "Completed lookup_geocodes for $cache_count addresses; $good_results lookups successful and $bad_results unsuccessful.  $update_errors update errors.");

	}

	// create log mail entry in the content directory ( one above plugin directory)
	private static function log_geo( $message ) {

		$wp_content_directory = plugin_dir_path( __FILE__  ) . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR  . '..' . DIRECTORY_SEPARATOR ;
		$message_wrap = "\n" . '[' . date ( DATE_RSS ) . '] ' . $message;
		
		if ( ! file_put_contents ( $wp_content_directory . 'wp_issues_crm_geocoding_log', $message_wrap, FILE_APPEND ) ) {
			error_log ( "WIC_Entity_Email_Cron::log_geo attempted to write to geo log this message: $message ");
			error_log ( 'Location of geo log should be: ' . $wp_content_directory . DIRECTORY_SEPARATOR . 'wp_issues_crm_geocoding_log -- check permissions.' );	
		};
	
	}

	public static function set_geocode_option ( $option_name, $option_value ) {
		
		$array = get_option( self::WIC_GEOCODE_OPTION_ARRAY );
		if ( ! is_array ( $array ) ) {
			$array = array();
		}
		$array[$option_name] = $option_value;
		update_option( self::WIC_GEOCODE_OPTION_ARRAY, $array );
		$array = get_option( self::WIC_GEOCODE_OPTION_ARRAY );
		return array ( 'response_code' => true, 'output' => ''  );
	}


	public static function get_geocode_option ( $option_name ) {
		
		$array = get_option( self::WIC_GEOCODE_OPTION_ARRAY );
		if ( ! is_array ( $array ) ) {
			$return_val = false;
		} else {
			$return_val = isset ( $array[$option_name] ) ? $array[$option_name] : false;
		}	
		return array ( 'response_code' => true, 'output' =>  $return_val );

	}

	// called once table already defined in WIC_List_Constituent_Export::do_constituent_download() -- list of selected constituent ids
	public static function filter_temp_table ( $download_type, $search_id ) {
	
		// get shape_array
		$shape_array = self::get_shape_array ( $download_type, $search_id );	
		// shape exclusion sql
		$not_in_shapes = '';
		if ( $shape_array && count( $shape_array ) ) {

			// presumes longitudes on same side of date line
			foreach ( $shape_array as $shape ) {
				$not_in_shapes .= " AND NOT ( ";
				switch ($shape->type) {
					case 'circle':
						
						/*
						* ALT 1using haversine formula to get great circle degrees and then multiplying by meters per degree of latitude (or degrees on any great circle)  
						*	https://en.wikipedia.org/wiki/Haversine_formula
						*	https://stackoverflow.com/questions/24370975/find-distance-between-two-points-using-latitude-and-longitude-in-mysql
						*	goal is not perfect accuracy, rather good consistency with google; not perfect
						*/
						$not_in_shapes .=
							" {$shape->geometry->radius}  > 
							111111.11111111111 * 
							DEGREES(
								ACOS(
									LEAST(
										COS(
											RADIANS({$shape->geometry->center->lat})
										)
									  * COS(
											RADIANS( lat )
										)
									  * COS(
											RADIANS({$shape->geometry->center->lng} - lon)
										)
										+ SIN(RADIANS({$shape->geometry->center->lat}))
										* SIN(RADIANS(lat)
									  ), 
									  1.0
									 )
								 )
							)
							";
						/*
						*
						* https://www.govinfo.gov/content/pkg/CFR-2016-title47-vol4/pdf/CFR-2016-title47-vol4-sec73-208.pdf
						* ALT 2 -- approach makes good local adjustments for elliptical
						*
						*
						$lat = " ( if( lat = 0 or lat = 99, 42.5, lat ) ) ";
						$ml = " ( RADIANS( ( {$shape->geometry->center->lat} + $lat )/2 ) )";
						$kpd_lat = " ( 111.13209 - 0.56605 * cos( 2 * $ml ) )";
						$kpd_lon = " ( 111.41513 * cos( $ml ) - 0.094455 * cos( 3 * $ml ) + 0.00120 * cos ( 4*$ml) ) ";
						$nsd =  " ( $kpd_lat * ( {$shape->geometry->center->lat} - $lat ) )";
						$ewd =  " ( $kpd_lon * ( {$shape->geometry->center->lng} - lon ) )";
						$dist = " ( pow( pow($nsd,2) + pow($ewd,2), 0.5 ) ) ";
						$not_in_shapes .= "  {$shape->geometry->radius}  > 1000 * $dist ";
						*/	
						break;
					case 'rectangle':
						$not_in_shapes .= "
							lat > {$shape->geometry->south } AND 
							lat < {$shape->geometry->north } AND 
							lon > {$shape->geometry->west } AND 
							lon < {$shape->geometry->east }  
						";
						break;
					case 'polygon':
						$not_in_shapes .= "
							ST_CONTAINS(
								ST_GEOMFROMTEXT('POLYGON((    
								";
									$first_point = true;
									foreach ( $shape->geometry->path as $point ){ 
										if ( !$first_point ) {
											$not_in_shapes .= ',';
										}
										$not_in_shapes .= "{$point->lat} {$point->lng}";
										$first_point = false;
									} 
									$not_in_shapes .= ", {$shape->geometry->path[0]->lat} {$shape->geometry->path[0]->lng}"; // google path self close, but not wkt, must repeat last
								$not_in_shapes .= 
								"))'),
						  	POINT ( lat, lon )
						  	)";
						break;
				}
				$not_in_shapes .= " ) ";		
			}
		}
		/*
		*
		* delete from temp table those entries that have no address, have ungeocoded or not geocodable address, or do not meet the geo screen
		*
		*/
		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();
		global $wpdb;
		$address_table = $wpdb->prefix . 'wic_address';
		$sql = "
			DELETE t
			FROM $temp_table t 
			LEFT JOIN $address_table a on a.constituent_id = t.id 
			WHERE 
				a.constituent_id IS NULL OR
				lat = 0 OR lat = 99
				OR ( 
				1=1 $not_in_shapes
				)
			";
		// do the deletes
		$wpdb->query ( $sql);
	
	}

	private static function get_shape_array ( $type, $search_id ) {
			
		if ( WIC_List_Constituent_Export::download_rule( $type, 'is_issue_only') ) {
			$shape_array =  get_post_meta ( $search_id, self::WIC_GEOCODE_META_KEY, true ); // true -> return single value instead of array with single element
		} else {
			global $wpdb;
			$search_log = $wpdb->prefix . 'wic_search_log';
			$sql = $wpdb->prepare ( "SELECT serialized_shape_array FROM $search_log WHERE ID = %d", array ( $search_id ) );
			$result = $wpdb->get_results ( $sql );
			if ( is_array ( $result ) ) {
				$shape_array = json_decode ( $result[0]->serialized_shape_array );
			} else {
				$shape_array = false;
			}	
		} 
		
		return $shape_array;
	}


	// takes sql that will generate points list from sql based on current list 
	public static function prepare_list_points ( $type, $search_id ) { 

		// get shape_array
		$shape_array = self::get_shape_array ( $type, $search_id );
		
		// get points
		global $wpdb;
		$sql = WIC_List_Constituent_Export::do_constituent_download( $type, $search_id );

		$points = $wpdb->get_results ( $sql );

		// return the array of objects or an error
		if ( $points ) {
			return array ( 'response_code' => true, 
				'output' => array( 
					'points' => $points, 
					'countPoints' => count ( $points ), 
					'constituentSearchUrl' => admin_url() . '/admin.php?page=wp-issues-crm-main&entity=constituent&action=id_search&id_requested=', 
					'shapeArray' => $shape_array
				)
			); 		
		} elseif ( $shape_array ) {
			return array ( 'response_code' => true, 
				'output' => array( 
					'search_id' => $search_id, 
					'points' => false, 
					'countPoints' => 0, 
					'constituentSearchUrl' => '', 
					'shapeArray' => $shape_array,
				)
			); 		
		} else {
			return array ( 'response_code' => false, 'output' => 'None of selected points had geocode coordinates or there was a database error.'  );
		}
	}
	
	public static function save_shapes ( $map_request, $shape_array ) { 
		
		if ( 'show_issue_map' == $map_request['context'] ) {
			$result = update_post_meta ( $map_request['id'], self::WIC_GEOCODE_META_KEY, $shape_array );
		} elseif ( 'show_map' == $map_request['context'] ) {
			global $wpdb;
			$search_log = $wpdb->prefix . 'wic_search_log';
			$serialized_shape_array = json_encode ( $shape_array );
			$sql = $wpdb->prepare ( "UPDATE $search_log SET serialized_shape_array = %s WHERE ID = %d", array ( $serialized_shape_array, $map_request['id'] ));
			$result = $wpdb->query ( $sql );
		}

		return array ( 'response_code' => true, 'output' => $result ? 'Shape save successful.' : 'Shape save was unsuccessful or unnecessary.' ); 
	}
}