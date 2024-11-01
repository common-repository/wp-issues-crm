<?php
/*
* class-wic-control-parent.php
*
* WIC_Control_Parent is extended by classes for each of the field types  
* 
* Multivalue is the most significant extension -- from the perspective of the top form,
* a multivalue field like address (which includes multiple rows with multiple fields in each)
* is just another control like first name.  
*
*
*
*/

/************************************************************************************
*
*  WIC Control Parent
*
************************************************************************************/
abstract class WIC_Control_Parent {
	protected $field;
	protected $default_control_args = array();
	protected $value = '';	


	/***
	*
	*	The control create functions are a little promiscuous in that they gather their control arguments from multiple places.
	*		Field rules from database on initialization  
	*		Rules specified in the named control function ( form_control) 
	*		In child controls, may allow direct passage of arguments -- see checked and multivalue.
	*		Note that have potential to get css specified to them based on their field slug
	*		Any special validation, sanitization, formatting and default values ( as opposed to default rule values ) are supplied from the relevant object and the dictionary
	*
	*	Note that $wic_db_dictionary->field_rules_cache, although itself private, points to public database results and therefore can be corrupted by
	*	overwriting $this->field which is just a pointer to one of its elements.  So, never update $this->field.  If wish to alter a rule value, 
	*   override $default_control_args (and check that downstream uses run off it or can be safely modified to run off it).
	*/


	public function initialize_default_values ( $entity, $field_slug, $instance ) {

		global $wic_db_dictionary;

	// initialize the default values of field RULES  
		$this->field = $wic_db_dictionary->get_field_rules( $entity, $field_slug );
		$this->default_control_args =  array_merge( $this->default_control_args, get_object_vars ( $this->field ) );
		$this->default_control_args['type'] 					= 'text';	// field type is not directly a dictionary arg -- it comes from the control extension	
		$this->default_control_args['input_class'] 			= 'wic-input';
		$this->default_control_args['label_class'] 			= 'wic-label';
		$this->default_control_args['field_slug_css'] 		= str_replace( '_', '-', $field_slug );
		$this->default_control_args['field_slug_search'] 	= $this->field->field_slug; // copy modifiable for advanced search field swapping
		$this->default_control_args['entity_slug_search'] 	= $this->field->entity_slug; // copy modifiable for advanced search field swapping
		// retain this value arg so don't need to parse out instance in static create control function where don't have $this->field->field_slug to refer to
		$this->default_control_args['field_slug'] = ( '' == $instance ) ? // ternary
				// if no instance supplied, this is just a field in a main form, and use field slug for field name and field id
				$field_slug :
				// if an instance is supplied prepare to output the field as an array element, i.e., a row in a multivalue field 
				// note that the entity name for a row object in a multivalue field is the same as the field_slug for the multivalue field
				// this is a trap for the unwary in setting up the dictionary table 
				$entity . '[' . $instance . ']['. $field_slug . ']';
		
		// initialize the value of the control ( if form is non-blank, value will be further set )
		if ( $this->field->field_default > '' ) {
			$this->value = $this->field->field_default; 
		} else {
			$this->reset_value(); // may reset as an array
		}
	}

	/*********************************************************************************
	*
	* setters_getters
	*
	***********************************************************************************/
	
	public function set_value ( $value ) {
		$this->value = $value;	
	}
	
	// display: none;
	public function set_input_class_to_hide_element() { 
		$this->default_control_args['input_class'] .= ' hidden-element ';
		$this->default_control_args['label_class'] .= ' hidden-element ';
	}	

	// visibility:hidden -- hidden,but takes space
	public function set_input_class_to_make_element_invisible() {
		$this->default_control_args['input_class'] .= ' invisible-element ';
		$this->default_control_args['label_class'] .= ' invisible-element ';
	}

	public function override_readonly( $true_or_false ) {
		$this->default_control_args['readonly'] = $true_or_false;	
	}

	public function get_value () {
		return $this->value;	
	}
	
	public function reset_value() {
		$this->value = '';	
	}

	public function get_wp_query_parameter() {
		return ( $this->field->wp_query_parameter );	
	}

	public function get_label() {
		return ( $this->field->field_label );	
	}

	public function get_control_type() {
		return ( $this->field->field_type );	
	}	

	public function is_upload_dedup(){
		return ( 1 == $this->field->upload_dedup );	
	}

	public function get_field_slug() {
		return ( $this->field->field_slug );	
	}
	
	public function is_read_only () {
		return ( $this->field->readonly );	
	}
	
	
	// used in advanced search when must create control with entity/field rules, but give it a new identity as a row element (do after init);
	public function set_default_control_slugs ( $entity, $field_slug, $instance ) {
		$this->default_control_args['field_slug'] = $entity . '[' . $instance . ']['. $field_slug . ']';
		$this->default_control_args['field_slug_css'] =  str_replace( '_', '-', $field_slug ) . ' ' . $this->default_control_args['field_slug_css'];
		$this->default_control_args['field_slug_search'] 	= $field_slug; 
		$this->default_control_args['entity_slug_search'] 	= $entity;

	}
	public function set_default_control_label ( $label ) {
		$this->default_control_args['field_label'] = $label;
	}

	/*********************************************************************************
	*
	* methods for basic forms -- single control type, since not working around readonly on search forms
	*
	***********************************************************************************/

	public function form_control () { 
		$final_control_args = $this->default_control_args;
		$final_control_args['value'] = $this->value;
		return ( static::create_control( $final_control_args )  );	
	}

	protected static function create_control ( $control_args ) { // basic create text control, accessed through control methodsabove

		extract ( $control_args, EXTR_OVERWRITE );  
		
		$value = ( '0000-00-00' == $value ) ? '' : $value; // don't show date fields with non values; 
		
     	$class_name = 'WIC_Entity_' . $entity_slug; 
		$formatter = $list_formatter; // ( field slug has instance args in it )
		if ( method_exists ( $class_name, $formatter ) ) { 
			$value = $class_name::$formatter ( $value );
		} elseif ( function_exists ( $formatter ) ) {
			$value = $formatter ( $value );		
		}

		$readonly = $readonly ? ' readonly ' : '';

		// allow extensions to set field type, but if hidden, is hidden		
		$type = ( 1 == $hidden ) ? 'hidden' : $type;
		 
		$control = ( $field_label > '' && ! ( 1 == $hidden ) ) ? '<label class="' . esc_attr ( $label_class ) .
				 ' ' . esc_attr( $field_slug_css ) . '" for="' . esc_attr( $field_slug ) . '">' . esc_html( $field_label ) . '</label>' : '' ;
		$control .= '<input autocomplete="off" class="' . esc_attr( $input_class ) . ' ' .  esc_attr( $field_slug_css ) . '" id="' . esc_attr( $field_slug )  . 
			'" name="' . esc_attr( $field_slug ) . '" type="' . $type . '" placeholder = "' .
			 esc_attr( $placeholder ) . '" value="' . esc_attr ( $value ) . '" ' . $readonly  . '/>'; 
			
		return ( $control );

	}


	/*********************************************************************************
	*
	* control sanitize -- will handle all including multiple values -- generic case is string
	*
	*********************************************************************************/

	public function sanitize() {  
		$class_name = 'WIC_Entity_' . $this->field->entity_slug;
		$sanitizor = $this->field->field_slug . '_sanitizor';
		if ( method_exists ( $class_name, $sanitizor ) ) { 
			$this->value = $class_name::$sanitizor ( $this->value );
		} else { 
			$this->value = sanitize_text_field ( stripslashes ( $this->value ) );		
		} 
	}

	/*********************************************************************************
	*
	* control validate -- will handle all including multiple values -- generic case is string
	* here, rather than directly in entity to support multiple values
	*
	*********************************************************************************/

	public function validate() { 
		$validation_error = '';
		$class_name = 'WIC_Entity_' . $this->field->entity_slug;
		$validator = $this->field->field_slug . '_validator';
		if ( method_exists ( $class_name, $validator ) ) { 
			$validation_error = $class_name::$validator ( $this->value );
		}
		return $validation_error;
	}

	/*********************************************************************************
	*
	* report whether field should be included in deduping.
	*
	*********************************************************************************/
	public function dup_check() {
		return $this->field->dedup;	
	}
	/*********************************************************************************
	*
	* report whether field is transient
	*
	*********************************************************************************/
	public function is_transient() {
		return ( $this->field->transient );	
	}
	/*********************************************************************************
	*
	* report whether field is multivalue
	*
	*********************************************************************************/
	public function is_multivalue() {
		return ( $this->field->field_type == 'multivalue' );	
	}
	/*********************************************************************************
	*
	* report whether field fails individual requirement
	*
	*********************************************************************************/
	public function required_check() { 
		if ( "individual" == $this->field->required && ! $this->is_present() ) {
			return ( sprintf ( __( ' %s is required. ', 'wp-issues-crm' ), $this->field->field_label ) ) ;		
		} else {
			return '';		
		}	
	}

	/*********************************************************************************
	*
	* report whether field is present as possibly required -- note that is not trivial for multivalued
	*
	*********************************************************************************/
	public function is_present() {
		$present = ( '' < $this->value ); 
		return $present;		
	}
	
	/*********************************************************************************
	*
	* report whether field is required in a group 
	*
	*********************************************************************************/
	public function is_group_required() {
		$group_required = ( 'group' ==  $this->field->required ); 
		return $group_required;		
	}


	/*********************************************************************************
	*
	* create where/join clause components for control elements in generic wp form 
	*
	*********************************************************************************/
	public function create_search_clause () {
		
		if ( '' == $this->value || 1 == $this->field->transient ) {
			return ('');		
		}

		$query_clause =  array ( // double layer array to standardize a return that allows multivalue fields
				array (
					'table'	=> $this->default_control_args['entity_slug_search'],
					'key' 	=> $this->default_control_args['field_slug_search'],
					'value'	=> $this->value,
					'compare'=> '=',
					'wp_query_parameter' => $this->field->wp_query_parameter,
				)
			);
		
		return ( $query_clause );
	}
	
	/*********************************************************************************
	*
	* create set array or sql statements for saves/updates 
	*
	*********************************************************************************/
	public function create_update_clause () { 
		if ( ( ( ! $this->field->transient ) && ( ! $this->field->readonly ) ) 
				|| 'ID' == $this->field->field_slug 
				|| 'custom_field_' == substr( $this->field->field_slug, 0, 13 ) 
				|| 'registration' == $this->field->group_slug ) {
			// exclude transient and readonly fields.   ID as readonly ( to allow search by ID), but need to pass it anyway.
			// ID is a where condition on an update in WIC_DB_Access_WIC::db_update
			// include custom fields since may be readonly but need to update in batch upload; 
			// -- no harm in including custom fields in form context since controls will be appropriately readonly 
			// add registration as like custom fields
			$update_clause = array (
					'key' 	=> $this->field->field_slug,
					'value'	=> $this->value,
					'wp_query_parameter' => $this->field->wp_query_parameter,
			);
			
			return ( $update_clause );
		}
	}


	
}
