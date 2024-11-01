<?php
/*
*
* class-wic-db-access-factory.php
*
* supports multiple approaches to data access to be further implemented in extensions of WP_DB_Access
*
* all extensions support an access model that looks to the entity seeking access like the Wordpress $wpdb object
* this allows compatibility across forms accessing wordpress posts and forms accessing wp_issues_crm custom tables
*
* 
*/

class WIC_DB_Access_Factory {

	static private $entity_model_array = array (
		'activity' 		=> 'WIC_DB_Access_WIC', 		// multivalue save update activity
		'address'		=> 'WIC_DB_Access_WIC',
		'advanced_search' => 'WIC_DB_Access_Advanced_Search',
		'comment' 		=> 'WIC_DB_Access_WIC',
		'constituent' 	=> 'WIC_DB_Access_WIC',	
		'data_dictionary' => 'WIC_DB_Access_Dictionary',
		'email'			=> 'WIC_DB_Access_WIC',
		'external'			=> 'WIC_DB_Access_WIC',
		'external_field'	=> 'WIC_DB_Access_WIC',
		'issue' 			=> 'WIC_DB_Access_WP',
		'option_group'	=> 'WIC_DB_Access_WIC',
		'option_value'	=> 'WIC_DB_Access_WIC',
		'owner'			=> 'WIC_DB_Access_Owner',
		'phone'			=> 'WIC_DB_Access_WIC',
		'search_log'	=> 'WIC_DB_Access_WIC',	
		'subject'		=>	'WIC_DB_Access_Subject',
		'upload'			=> 'WIC_DB_Access_WIC',
	);

	// available to support up reach to parent entity where necessary 
	// initially implemented to support time_stamping in multi-value control set_value function --
	//   when doing deletes of child entity (e.g. email) need to timestamp parent (constituent)
	static private $entity_parent_array = array (
		'activity' 		=> 'constituent', 
		'address'		=> 'constituent',
		'advanced_search' => '', 
		'comment' 		=> '',
		'constituent' 	=> '',	
		'data_dictionary' => '',
		'email'			=> 'constituent',
		'external'		=> '',
		'external_field' => 'external',
		'issue' 		=> '',
		'option_group'	=> '',
		'option_value'	=> 'option_group',
		'owner'			=> '',
		'phone'			=> 'constituent',
		'search_log'	=> '',	
		'subject'		=> '',
		'upload'		=> '',
	);

	public static function make_a_db_access_object ( $entity ) { 
		$right_db_class = self::$entity_model_array[$entity];
		$new_db_access_object = new $right_db_class ( $entity );
		if ( self::$entity_parent_array[$entity] > '' ) {
			$new_db_access_object->parent = self::$entity_parent_array[$entity]; // set entity_parent for new entity if exists
		}
		return ( $new_db_access_object );	
	}

	public static function is_entity_instantiated ( $entity ) {
		return isset ( self::$entity_model_array[$entity] );
	}

	
}

