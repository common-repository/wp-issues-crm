<?php
/*
* 
* class-wic-control-email.php
*
*
*/

class WIC_Control_Email extends WIC_Control_Text {

	public function initialize_default_values ( $entity, $field_slug, $instance ) {
		parent::initialize_default_values ( $entity, $field_slug, $instance );
		$this->default_control_args['type'] = 'email';	// field type is not directly a dictionary arg -- it comes from the control extension	
	}



}

