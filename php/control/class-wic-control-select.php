<?php
/*
* wic-control-select.php
*
*/
class WIC_Control_Select extends WIC_Control_Parent {

	public function form_control () {
		$final_control_args = $this->default_control_args;
		$final_control_args['value'] = $this->value;
		if ( $final_control_args['readonly'] ) {	
			$final_control_args['readonly_update'] = 1 ; // lets control know to only show the already set value if readonly
																		// (readonly control will not show at all on save, so need not cover that case)
		} 
		$final_control_args['option_array'] =  $this->create_options_array ( $final_control_args );
		$control =  $this->create_control( $final_control_args ) ;
		return ( $control );
	}	
	
	public function set_options ( $option_group ) {
		// note: important not to set $this->field->option_group as this points straight back to field_rules_cache
		// which in turn is a pointer to apparently public $wpdb objects and so is modifiable through layers 
		$this->default_control_args['option_group'] = $option_group;
	}	
	
	protected function create_options_array ( $control_args ) {
		
		global $wic_db_dictionary;
		extract ( $control_args, EXTR_SKIP );
				
		$entity_class = 'WIC_Entity_' . $this->field->entity_slug;
		$function_class = 'WIC_Function_Utilities';
		// if available, take from control arguments which may be  modified by set options
		$getter = isset ( $option_group ) ? $option_group : $this->field->option_group; 
		// look for option array in a sequence of possible sources
		$option_array = $wic_db_dictionary->lookup_option_values( $getter );
		// look first for getter as an option_group value in option values cache
		if ( $option_array > '' ) {
			// if found, then already done -- look no further
		} elseif ( method_exists ( $entity_class, $getter ) ) { 
			// look second for getter as a static function built in to the current entity
			$option_array = $entity_class::$getter ( $value );
			// note: including the value parameter to allow the getter to inject the value into the array if needed			
		} elseif ( method_exists ( $function_class, $getter ) ) {
			// look third for getter as a static function in the utility class
			$option_array = $function_class::$getter( $value );			
		} elseif ( function_exists ( $getter ) ) {
			// look finally for getter as a function in the global name space
			$option_array = $getter( $value );			
		} else {
			$option_array = array( array ( 
				'value' => '',				
				'label' => __( 'No options defined or field pointed to non-existent or disabled option group.', 'wp-issues-crm' ),
				)
			);
		}
		
		if ( isset ( $readonly_update ) ) { 
			// if readonly on update, extract just the already set option if a readonly field, but in update mode 
			// (if were to show as a readonly text, would lose the variable for later use)
			$option_array = array( array ( 
				'value' => $value,				
				'label' => WIC_Function_Utilities::value_label_lookup ( $value,  $option_array ),
				)
			);
		} 	
		return ( $option_array );	
	}	
	
	public static function create_control ( $control_args ) { 

		extract ( $control_args, EXTR_SKIP ); 

		$control = '';
		
		// $hidden_class = 1 == $hidden ? 'hidden-template' : '';
		$hidden = 1 == $hidden ? "hidden" : '';		
		
		$control = ( $field_label > '' ) ? '<label ' . $hidden . ' class="' . $label_class . ' ' .  esc_attr( $field_slug_css ) . '" for="' . esc_attr( $field_slug ) . '">' . 
				esc_html( $field_label ) . '</label>' : '';
		$control .= '<select  ' . $hidden . ' class="' . esc_attr( $input_class ) . ' '  .  esc_attr( $field_slug_css ) .'" id="' . esc_attr( $field_slug ) . '" name="' . esc_attr( $field_slug ) 
				. '" >' ;
		$p = '';
		$r = '';
		foreach ( $option_array as $option ) {
			$label = $option['label'];
			if ( $value == $option['value'] ) { // Make selected first in list
				$p = '<option selected="selected" value="' . esc_attr( $option['value'] ) . '">' . esc_html ( $label ) . '</option>';
			} else { // in this not selected branch, do not include system reserved values
				if ( isset ( $option['is_system_reserved'] ) ) {  
					if ( 1 == $option['is_system_reserved'] ) {
						continue;
					}
				}
				$r .= '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $label ) . '</option>';
			}
		}
		$control .=	$p . $r .	'</select>';
		return ( $control );
	
	}
	
	/*********************************************************************************
	*
	* function to support bulk validation in upload process
	*
	*********************************************************************************/

	public function valid_values() {
		$args = array ( 'value' => '' );  
		$options_array = $this->create_options_array( $args );
		$valid_values = array();
		foreach ( $options_array as $option ) {
			$valid_values[] = $option['value'];		
		}
		return ( $valid_values );
	}	
		
}


