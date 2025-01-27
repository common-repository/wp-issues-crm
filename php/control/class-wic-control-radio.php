<?php
/*
* wic-control-radio.php
*
*/
class WIC_Control_Radio extends WIC_Control_Select {
	
	public static function create_control ( $control_args ) { 
		
		extract ( $control_args, EXTR_SKIP ); 

		$control = '';
		
		$control = ( $field_label > '' ) ? '<label class="' . $label_class . '" for="' . esc_attr( $field_slug ) . '">' . 
				esc_html( $field_label ) . '</label>' : '';

		foreach ( $option_array as $option ) {
		
			if ( '' == $value ) {
				// if value is blank and there is a default value, check the default value 
				if ( $field_default > '' ) {
					$selected = ( $field_default == $option['value'] ) ? ' checked ' : '';
				}
			} else {
				// otherwise, value is already chosen, so check that value
				$selected = ( $value == $option['value'] ) ?	' checked ' : '';
			}

			$control .= '<p class = "wic-radio-button" ><input ' . 
				'type 	= 	"radio" ' .
				'name		=	"' . esc_attr( $field_slug )  . '" ' .  
				'class	=	" wic-radio-button ' . esc_attr( $input_class ) . ' '  .  esc_attr( $field_slug_css ) .'" ' .
				'value	=	"' . $option['value'] 	 		. '" ' .
				$selected .
				'>';
			$control .= esc_html ( $option['label'] );
			$control .= '</p>';
		}

		return ( $control );
	
	}
		
}


