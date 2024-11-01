<?php
/*
*
*	wic-entity-issue.php
*
*
*/

class WIC_Entity_Issue extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a top level entity does not process them -- no instance arg
		$this->entity = 'issue';
	} 
	
	protected function special_entity_value_hook ( &$wic_access_object ) {
		$control = $this->data_object_array['post_date'];
		$post_date = $control->get_value();
		if ( '' == $post_date ) { 
			// these values specially prepared for this hook by wic-db-access-wic->process_save_update_array
			$this->data_object_array['post_author']->set_value( $wic_access_object->post_author );
			$this->data_object_array['post_date']->set_value( $wic_access_object->post_date );
			$this->data_object_array['post_status']->set_value( $wic_access_object->post_status );
		}		
	}	
	
	public function get_the_title () {
		if ( isset ( $this->data_object_array['post_title'] ) ) { 
			return ( $this->data_object_array['post_title']->get_value() );	
		}
		return ( '' );
	}	
	
	
	/***************************************************************************
	*
	* Functions related to issue properties
	*
	****************************************************************************/ 	

	// this is special purpose option array generator -- uses recursive function below
	// use globals rather than object properties so that function can be static. 
	// function being static allows table driven calls to it.
	public static function get_post_category_options() {
		global $wic_category_select_array;
		global $wic_category_array_depth;
		$wic_category_select_array = array();
		$wic_category_array_depth = 0;
		return ( self::wic_get_category_list(0) );
	} 		

	// this is recursive to traverse the category list -- initiated by get_post_category_options
	private static function wic_get_category_list ( $parent ) {

		global $wic_category_select_array;
		global $wic_category_array_depth;
		
		$wic_category_array_depth++;		
		// echo " depth is now $wic_category_array_depth";
		$args = array(
			'orderby'                  => 'name',
			'order'                    => 'ASC',
			'hide_empty'               => 0,
			'taxonomy'                 => 'category',
			'pad_counts'               => false, 
			'parent'							=> $parent,
		); 

		$categories = get_categories( $args );
		if ( 0 < count ( $categories ) ) {		
			foreach ( $categories as $category ) {
				$temp_array = array (
					'value' => $category->term_id,
					'label' => $category->name,
					'class' => 'wic-multi-select-depth-' . $wic_category_array_depth,
				);			
				$wic_category_select_array[] = $temp_array;
				self::wic_get_category_list ($category->term_id);	
			}
		}
		$wic_category_array_depth--;
		return ( $wic_category_select_array );
	} 	


	// next two functions use same ideas as above two functions, 
	// but create a count tree, pruned to include only branches with positive counts
	public static function get_post_category_count_tree( &$category_count_array ) { 
		global $wic_category_count_source;	// a key value array term_id=>count (count of whatever based on some previous query)	
		global $wic_category_count_array;
		global $wic_category_count_array_depth;
		global $wic_category_count_array_pointer;
		$wic_category_count_source = $category_count_array;
		$wic_category_count_array = array();
		$wic_category_count_array_depth = 0;
		$wic_category_count_array_pointer = -1; 	// increment before store, so first will be 0
		self::build_category_count_array( 0, 0 ); // initiate iteration
		return ( $wic_category_count_array ); 		// return results of iteration (is in global variable)
	}

	// function gets the subcategories of the current $category_id and puts them in the category_count_array and iterates for each
	// returns the count total for itself and its subcategories (and an array of the contributors to the count)
	private static function build_category_count_array ( $category_id, $category_array_pointer ) {

		global $wic_category_count_source;		
		global $wic_category_count_array;
		global $wic_category_count_array_depth;
		global $wic_category_count_array_pointer;
		
		// increment array depth -- this is just for css class
		$wic_category_count_array_depth++;		

		// if already have a category in this iteration start the branch count with its own count (if any)
		// note that while accumulating count, also accumulating an array of contributors to the count 
		$branch_count = 0;
		$branch_twigs_array = array();
		// test for 0 category id for startup pass		
		if ( 0 < $category_id ) { 
			// category may have its own count; if so, add it in		
			if ( isset ( $wic_category_count_source[$category_id] ) ) {
				$branch_count = $wic_category_count_source[$category_id];
				$branch_twigs_array[] = $category_id;
			}
		} 

		// get the list of child categories
		$args = array(
			'orderby'                  => 'name',
			'order'                    => 'ASC',
			'hide_empty'               => 0,
			'taxonomy'                 => 'category',
			'pad_counts'               => false, 
			'parent'							=> $category_id,
		); 

		$subcategories = get_categories( $args );

		// run through the subcategories if any
		if ( is_array ( $subcategories ) && 0 < count ( $subcategories ) ) {		
			foreach ( $subcategories as $category ) {
				// prepare array for entry into larger array
				$temp_array = array (
					'value' => $category->term_id,
					'label' => $category->name,
					'class' => 'wic-multi-select-depth-' . $wic_category_count_array_depth,
				);
				// increment the array pointer (started at -1, increment before adding)			
				$wic_category_count_array_pointer++;
				// safety language if bugs creep in!
				if ( $wic_category_count_array_pointer > 9999 ) {
					WIC_Function_Utilities::wic_error ( __( 'Unanticipated loop condition.  Counted 10,000 Categories.' ), __FILE__, __LINE__, __METHOD__, true );
				}
				// put the array into the larger array
				$wic_category_count_array[$wic_category_count_array_pointer] = $temp_array;
				// get the count and children for the subcategory
				$return_array = self::build_category_count_array ( $category->term_id, $wic_category_count_array_pointer );
				// add it to the branch count if it is a contributor (and track source)
				if ( $return_array['count'] > 0 ) {
					$branch_count = $branch_count + $return_array['count'];
					$branch_twigs_array = array_merge( $branch_twigs_array, $return_array['twigs'] );
				}
				// note that in iterating, the passed parameters point to the same category in two parallel data structures --
				// the taxonomy table and the current array	
			}
		}

		// decrement array depth for css class
		$wic_category_count_array_depth--;
		
		// having traversed all child categories and added their counts to the branch count, call this the parent count and save it
		if ( $category_id > 0 ) {
			if ( $branch_count > 0 ) {
				$wic_category_count_array[$category_array_pointer]['count'] = $branch_count;
				$wic_category_count_array[$category_array_pointer]['twigs'] = $branch_twigs_array;
			// for elements with no count of their own and now child count, drop them
			} else {
				 unset ( $wic_category_count_array[$category_array_pointer] );
			}
		}
		
		// report this outcome to be included upwards in the hierarchy		
		return ( array ( 'count'=>$branch_count, 'twigs' => $branch_twigs_array ) );

	} 

	// for issue list: retrieve the values as well as formatting
	public static function get_post_categories ( $post_id ) {
		$categories = get_the_category ( $post_id );
		$return_list = '';
		foreach ( $categories as $category ) {
			$return_list .= ( '' == $return_list ) ? $category->cat_name : ', ' . $category->cat_name;		
				}
		return ( $return_list ) ;	
	}	
	
	// for author search, author drop down 
	public static function get_post_author_options () {
	
		global $wpdb;
		
		$query_args = array(
			'orderby' => 'name',
			'order' => 'ASC',
			'number' => '',
			'fields' => array ( 'ID' , 'display_name' ),
		);
		$authors = get_users( $query_args );
		
		$author_options = array(
			array (
				'value' => '',
				'label' => '',			
			)		
		);
		foreach ( $authors as $author ) {
			$author_options[] = array (
				'value' => $author->ID,
				'label' => $author->display_name,			
			); 
		}
		return ( $author_options ) ;
	}
	
	// for tag input -- sanitize to csv
	public static function tags_input_sanitizor ( $value ) {
		return WIC_Function_Utilities::sanitize_textcsv( $value );	
	}	
}