<?php
/*
*
*	wic-entity-email-compose.php
*
*/
Class WIC_Entity_Email_Compose extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) {
		$this->entity = 'email_compose';
		$this->entity_instance = '';
	} 

	// alternative construct because only supporting action_requested = 'new_blank_form' which does not pass first parameter to layout_form; using "guidance"
	public function __construct ( $action_requested, $args ) { 
		$this->set_entity_parms( $args );
		$this->$action_requested( '' , $args );
	}


	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}

}