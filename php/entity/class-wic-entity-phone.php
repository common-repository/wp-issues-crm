<?php
/*
*
*	wic-entity-phone.php
*
*/



class WIC_Entity_Phone extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'phone';
		$this->entity_instance = $instance;
	} 

	public static function phone_number_sanitizor ( $raw_phone ) { 
		return ( preg_replace("/[^0-9]/", '', $raw_phone) ) ;
	}
	
	public static function phone_number_formatter ( $raw_phone ) {
		   	
		$phone = preg_replace( "/[^0-9]/", '', $raw_phone );
   	
		if ( 7 == strlen($phone) ) {
			return ( substr ( $phone, 0, 3 ) . '-' . substr($phone,3,4) );		
		} elseif ( 10  == strlen($phone) ) {
			return ( '(' . substr ( $phone, 0, 3 ) . ') ' . substr($phone, 3, 3) . '-' . substr($phone,6,4) );	
		} else {
			return ($phone);		
		}

	}

}