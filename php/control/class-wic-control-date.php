<?php
/*
* class-wic-control-range.php
*
*/ 

class WIC_Control_Date extends WIC_Control_Parent {

	public function sanitize() {  
		$this->value 		= $this->value 	> '' ? self::sanitize_date ( $this->value ) 	: '';
	}

	/*
	* no error message for bad date, but will fail a required test 
	*/   
	public static function sanitize_date ( $possible_date ) {
		try {
			$test = new DateTime( $possible_date );
		}	catch ( Exception $e ) {
			return ( '' );
		}	   			
 		return ( date_format( $test, 'Y-m-d' ) );
	}
	
	protected static function create_control ( $control_args ) { 
		$control_args['input_class'] .= ' datepicker ';
		$control = parent::create_control( $control_args);  
		return ( $control );
	}
	
	
}

