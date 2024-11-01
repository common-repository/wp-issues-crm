<?php
/*
* class-wic-control-textarea.php
*
*
*/

class WIC_Control_Textarea extends WIC_Control_Parent {

	public static function create_control ( $control_args ) {
		
		extract ( $control_args, EXTR_SKIP ); 
	
		$readonly = $readonly ? ' readonly ' : '';
		$hidden	 = $hidden ? 'hidden' : ''; 
		 
		$control = ( $field_label > '' ) ? '<label class="' . $label_class . ' ' . esc_attr( $field_slug_css ) . '" ' .
			 'for="' . esc_attr( $field_slug ) . '">' . esc_attr( $field_label ) . '</label>' : '' ;
		$control .= '<textarea ' .  $hidden . ' class="' . $input_class . ' ' .  esc_attr( $field_slug_css ) . '" id="' . esc_attr( $field_slug ) . '" name="' . esc_attr( $field_slug ) . '" type="text" placeholder = "' . 
			esc_attr( $placeholder ) . '" ' . $readonly  . '>' . esc_textarea( $value ) . '</textarea>';
			
		return ( $control );

	}	

	// text area cannot be sanitized with sanitize_text -- loses formatting
	// kses_post strips scripts; preg_replace strips high plane not usable utf8
	public function sanitize () { 
		$this->value = wp_kses_post(  preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", stripslashes( $this->value ) ) );
	}	
	
}

