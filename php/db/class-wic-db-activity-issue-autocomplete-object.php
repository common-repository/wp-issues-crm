<?php
/*
* class-wic-db-activity-issue-autocomplete-object.php
*	interface object
*
*/
class WIC_DB_Activity_Issue_Autocomplete_Object {
	
	public $label;
	public $value;
	public $entity_type;
	public $email_name;
	public $latest_email_address;
	
	// note -- order of parameters inconsistent withorder in selectmenu presentation -- value, label, entity_type
	public function __construct ( $label, $value, $entity_type = 'activity', $email_name = '', $latest_email_address = '' ) {
		$this->label = sanitize_text_field ( $label );
		$this->value = $value;
		$this->entity_type	 = $entity_type;
		$this->email_name = $email_name;
		$this->latest_email_address = $latest_email_address;
	}

}