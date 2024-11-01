<?php
/*
*
*	class-wic-entity-external.php
*
* Each external interface needs the following data/functions (all in or called from this class):
*  (1) An entry in the external_type_options array 
*  (2) An identifier option array generator named as type_identifier_options (which should include a blank/unselected option)
*  (3) Add filter or action hook in self::activate_interfaces
*  (4) The class that the hook calls: WIC_Entity_External_{type}
*/

class WIC_Entity_External extends WIC_Entity_Parent {
	
	// standard setup
	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'external';
	} 
	
	// standard list function
	protected function list_external_interfaces () {
		// table entry in the access factory will make this a standard WIC DB object
		$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		$meta_query_array = array ( );
		$wic_query->search ( $meta_query_array, array( 'retrieve_limit' => 9999 ) );
		$lister_class = 'WIC_List_' . $this->entity ;
		$lister = new $lister_class;
		$list = $lister->format_entity_list( $wic_query, '' ); 
		echo $list;
	}

	// external_type_options array -- option list here for consolidation of maintenance
	public static function external_type_options () {
		$type_array = array( 
			array( 'value' => '', 'label' => 'Select an interface type' )
		);

		if ( class_exists('WPCF7') ) {
			$type_array[] = array( 'value' => 'wpcf7', 'label' => 'Form -- Contact Form 7' );
		}
// 		Deprecating support for Ninja Forms
//		if ( function_exists( 'ninja_forms_display_form' ) ){
//			$type_array[] = array( 'value' => 'ninja_form', 'label' => 'Form -- Ninja Form' );
//		}
		if ( class_exists('GFAPI') ){
			$type_array[] =	array( 'value' => 'gravity', 'label' => 'Form -- Gravity Form' );
		}

		return $type_array;
	}
	// list formatter for type options
	public static function list_external_type( $type ) {
		$options = self::external_type_options();
		foreach ( $options as $option ) {
			if ( $option['value'] == $type ) {
				return $option['label'];
			}
		}
		return $type;
	}

	// degenerate default option list before type is selected
	public static function _identifier_options () {
		return array ( array ( 'value' => '', 'label' => 'Select interface type before identifier' ) );
	}

	// switcher for js -- called on change type -- always dropping value when switch type
	// can be called with blank type
	public static function get_external_identifier_options( $type, $dummy ) {
		$html = '';
		$options_array = self::{ $type . '_identifier_options' }(); 
		foreach ( $options_array as $option ) {
			$html .= WIC_Control_Selectmenu::format_list_entry ( $option, '--' );
		}
		return array( 'response_code' => true, 'output' => $html );
	}

	// generic inverter for all type specific identifier sets
	public static function list_interface_title ( $type, $identifier ) {
		$options_array = self::{ $type . '_identifier_options' }(); 
		foreach ( $options_array as $option ) {
			if ( $option['value'] == $identifier ) {
				return ( $option['label'] );
			}
		}
		return $identifier;
	}

	// pass through to activity for form options
	public static function get_issue_options( $value ) {
		return WIC_Entity_Activity::get_issue_options ( $value );
	}

	// use this hook -- post population of object from form or found record
	protected function main_form_field_interaction_rules() { 
		// swap in the correct option set based on type; populate the autocomplete field
		$type =  $this->data_object_array['external_type']->get_value(); 
		if ( $type > '' ) {
			$this->data_object_array['external_identifier']->set_options ( $type . '_identifier_options' );
		}
	}

	public static function activate_interfaces() { 
		global $wpdb;
		$external_table = $wpdb->prefix . 'wic_external';
		// test for existence of table to avoid generating error in installation process
		if ( ! count ( $wpdb->get_results( "show tables like '$external_table'" ) ) ) {
			return false;
		}
		// get list of active external links and set them up
		$externals = $wpdb->get_results ( "SELECT external_type as type, GROUP_CONCAT(external_identifier SEPARATOR '|||___|||') as identifiers FROM $external_table WHERE enabled = 1 group by external_type" );
		if ( count ( $externals ) > 0 ) {
			foreach ( $externals as $external ) {
				$identifiers = explode ( '|||___|||' , $external->identifiers ); 
				$interface_class = 'WIC_Interface_' . $external->type;
				new $interface_class ( $identifiers );
			}
			return true;
		}
		// if none found, return false (true sets up dictionary early);
		return false;
	}

	/*
	*
	* form set up function for field map
	*
	*
	*/
	public static function setup_field_map ( $id, $dummy ) {
		// get interface details
		global $wpdb;
		$external_table = $wpdb->prefix . 'wic_external';
		$results = $wpdb->get_results ( $wpdb->prepare ( "SELECT external_type as type, external_identifier as eid, serialized_field_map FROM $external_table WHERE ID = %s", $id ) );
		$field_map = json_decode ( $results[0]->serialized_field_map );
		$type = $results[0]->type;
		$eid  = $results[0]->eid;
		// get fields and pull form
		$interface_class = 'WIC_Interface_' . $type;
		$field_list = $interface_class::get_field_list( $eid );
		$map_elements = WIC_Form_External_Map::get_form_elements( $field_list ); 
		// return form and interface
		return array ( 'response_code' => true,	'output' =>
			(object) array (
				'dialog_content' => $map_elements,
				'dialog_title'	 => 'Map Fields from `'. self::list_interface_title ( $type, $eid ) . '` into WP Issues CRM'
			)
		);
	}


	public static function get_field_map ( $ID, $dummy ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wic_external';
		$sql = "SELECT serialized_field_map FROM $table where ID = $ID";
		$result = $wpdb->get_results( $sql );
		$response_code = ( count ( $result ) === 1 );
		$serialized = trim( $result[0]->serialized_field_map );
		$map = json_decode ( $serialized );
		return array ( 'response_code' => $response_code, 'output' => $response_code ? $map : 'Database error in field map retrieval.' );
	}	

	public static function update_field_map ( $ID, $map ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wic_external';
		$serialized = json_encode ( $map );
		$sql = $wpdb->prepare ( "UPDATE $table set serialized_field_map = %s where ID = $ID", array ( $serialized ) );
		$result = $wpdb->get_results( $sql );
		return array ( 'response_code' => $result !== false, 'output' => $result === 1 ?  'Field map saved.' : 'Database error in field map update.' );
	}

	/*
	*
	* functions for the wpcf7 interface set up
	*
	*/
	public static function wpcf7_identifier_options () { 
		global $wpdb;
		$post_table = $wpdb->posts;		
		$results = $wpdb->get_results ( "SELECT ID, post_title from $post_table WHERE post_type = 'wpcf7_contact_form' ORDER BY post_title " );
		$options_array = array();
		if ( count ( $results ) > 0 ){
			$options_array[] = array ( 'value' => '', 'label' => 'Select a Contact Form 7 Form' );
			foreach ( $results as $form ) {
				$options_array[] = array ( 'value' => $form->ID, 'label' => $form->post_title  );
			}
		} else {
			$options_array[] = array ( 'value' => '', 'label' => 'No Contact Form 7 Forms Found' );
		}	
		return ( $options_array );
	}

	public static function ninja_form_identifier_options () {
		$options_array = array();
		if ( function_exists( 'ninja_forms_display_form' ) ){
			global $wpdb;
			$ninja_form_table = $wpdb->prefix . 'nf3_forms';
			// note that on delete, ninja hard deletes from this table
			$results = $wpdb->get_results ( "SELECT id, title from $ninja_form_table ORDER BY title " );
			if ( ! empty ( $results ) ) {
				$options_array[] = array ( 'value' => '', 'label' => 'Select a Ninja Form' );
				foreach ( $results as $form ) {
					$options_array[] = array ( 'value' => $form->id, 'label' => $form->title  );
				}
				return $options_array;
			}
		} 
		// reach here if ninja not installed or no forms found
		$options_array[] = array ( 'value' => '', 'label' => 'No Ninja forms yet or Ninja plugin deactivated' );
		return ( $options_array );
	}

	public static function gravity_identifier_options () {
		$options_array = array();
		if ( class_exists ( 'GFAPI' ) ) {
			$forms = GFAPI::get_forms();		
			if ( ! empty ( $forms ) ) {
				$options_array[] = array ( 'value' => '', 'label' => 'Select a Gravity Form' );
				foreach ( $forms as $form ) {
					$options_array[] = array ( 'value' => $form['id'], 'label' => $form['title']  );
				}
				return $options_array;
			}
		}
		$options_array[] = array ( 'value' => '', 'label' => 'No Gravity forms yet or Gravity plugin deactivated' );
		return ( $options_array );
	}

	public static function hard_delete ( $id, $dummy ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wic_external';
		$result = $wpdb->query ( " DELETE from $table WHERE ID = $id " );
		$deleted = false !== $result;
		return array ( 'response_code' => $deleted, 'output' => $deleted ? (object) array ( 
			'message' 			 => 'This interface has been deleted. The form itself and any data created through were NOT deleted. Returning to interface list.',
			'list_page'			 => site_url() . '/wp-admin/admin.php?page=wp-issues-crm-externals',
			) :  'Database error on attempted delete.' 
		);
	}
}