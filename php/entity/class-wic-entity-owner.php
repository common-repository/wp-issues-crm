<?php
/*
*
*	wic-entity-owner.php
*
*/

class WIC_Entity_Owner extends WIC_Entity_Parent {
	
	/*
	*
	* Request handlers
	*
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition
		$this->entity = 'owner';
	} 

	protected function list_owners () {  
		$wic_query 	= WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		$wic_query->search ( array(), 'list'); // list dictates get the blog list
		$lister 	= new WIC_List_Owner;
		$list 		= $lister->format_entity_list( $wic_query, $wic_query->fields ); 
		echo $list;
	}
	
	public static function valid_owner_types () {
		global $wic_db_dictionary; 
		$registration_fields = $wic_db_dictionary->get_fields_for_group_with_labels ( 'constituent', 'registration' );
		$option_array = array( 
			array ( 
				'value' => '',				
				'label' => __( 'Not defined', 'wp-issues-crm' ),
			),
			array ( 
				'value' => 'city',				
				'label' => __( 'City', 'wp-issues-crm' ),
			),
			array ( 
				'value' => 'state',				
				'label' => __( 'State', 'wp-issues-crm' ),
				),
			array ( 
				'value' => 'zip',				
				'label' => __( 'Zip', 'wp-issues-crm' ),
			)
		);	
		foreach ( $registration_fields as $order => $field ) {
			if ( in_array( $field['field_slug'], array ( 'registration_id', 'registration_synch_status', 'registration_date' ) ) ) { continue; }
			$option_array[] = array (
				'value' => $field['field_slug'],
				'label' => $field['field_label']
			);
		}
		return $option_array;
	
	}
	
	public static function get_owner_type_label( $field_slug ) {
		$label = '';
		foreach ( self::valid_owner_types() as $option ) {
			if ( $option['value'] == $field_slug ) {
				$label = $option['label'];
				break;
			}
		}
		return ( $label );
	}
	
	protected function special_entity_value_hook ( &$wic_access_object ) {}
		
	
	
}
