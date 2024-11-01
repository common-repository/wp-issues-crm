<?php
/*
*
*	wic-entity-external-field.php
*
*/



class WIC_Entity_External_Field extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'external_field';
		$this->entity_instance = $instance;
	} 
	
	public static function uploadable_fields_as_options ( $value ) {
		global $wic_db_dictionary;
		/* get array of field arrays -- want only entity and field within each field array */
		$fields_array = $wic_db_dictionary->get_uploadable_fields (); 
		$options_set = array();
		foreach ( $fields_array as $field ) {
			if (  ! in_array( $field['field'], array ( 'ID' ) ) && false === stripos(  $field['field'], 'address_line_part_') ) {
				$options_set[] = array ( 'value' => $field['field'], 'label' => $field['label'] > '' ?  ( $field['field'] . ' ( ' .$field['label'] . ' )' ) : $field['field'] );
			}
		}
		return $options_set;
	}
}