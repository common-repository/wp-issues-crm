<?php
/*
*
*	wic-entity-list.php
*
*  this is a shell to allow definition of controls for lists
*/

class WIC_Entity_List extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'list';
	} 


}