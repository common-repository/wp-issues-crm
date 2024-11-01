<?php
/*
*
* class-wic-db-access-owner.php
*
*/

class WIC_DB_Access_Owner Extends WIC_DB_Access {


	// function updates fields through get_option standard function
	public function save_update ( &$data_object_array ) { 
		switch_to_blog( $data_object_array['ID']->get_value() );
		foreach ( $data_object_array as $field => $control ) {
			if ( !$control->is_read_only() && !$control->is_transient() ) {
				update_option( $control->get_field_slug() , $control->get_value() );
			}
		}	
		restore_current_blog();
	}	

	// spoofs the more complex wic_query->search functions built around search clause arrays
	public function search ( $meta_query_array, $parms ) {

		global $wic_db_dictionary;

		if ( 'list' == $parms ) { // called only in list
			$blog_list = get_sites ( array( 'orderby' => array ('domain', 'path'), 'deleted' => 0, 'archived' => 0) ); 
			$this->found_count = count( $blog_list );
	  		$this->fields =  $wic_db_dictionary->get_list_fields_for_entity( $this->entity );	
		} else { // called only in form display
			$blog_list = array ( (object) array ( 'blog_id' => $meta_query_array[0]['value'] ) ); // from single search
			$this->found_count = get_sites( array( 'site__in' => array( $blog_list[0]->blog_id ), 'count' =>true  ) );
	  		$this->fields = $wic_db_dictionary->get_form_fields( $this->entity );	
		}

		// build return array
		foreach ( $blog_list as $blog ) { 
			$this->result[] = $this->get_settings ( $blog->blog_id ); 
		}

	}

	
	private function get_settings ( $id ) {  
		
		switch_to_blog( $id );
		$temp_object = (object) array();
		foreach ( $this->fields as $field ) {
			$temp_object->{$field->field_slug} = ( 'ID' == $field->field_slug ? $id : get_option ( $field->field_slug ) );
		}
		restore_current_blog();	
		return $temp_object;
	}



	// functions not implemented for owner db access
	protected function db_get_option_value_counts( $field_slug ) {} 
	public function db_get_time_stamp ( $id ) {} 
	protected function db_do_time_stamp ( $table, $id ) {} 
	protected function db_save ( &$meta_query_array ) {}
	protected function db_update( &$meta_query_array ) {}
	protected function db_delete_by_id( $id ){}

}

