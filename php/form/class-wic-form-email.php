<?php
/*
*
*  class-wic-form-email.php
*
*
*/

class WIC_Form_Email extends WIC_Form_Multivalue  {
	
	public function layout_form ( &$data_array, $message, $message_level, $sql = '' ) { 
	
		global $wic_db_dictionary;

		$groups = $this->get_the_groups();
		$class = ( 'row-template' == $this->entity_instance ) ? 'hidden-template' : 'visible-templated-row';
		$search_row = '<div class = "'. $class . '" id="' . $this->entity . '[' . $this->entity_instance . ']">';
		$search_row .= '<div class="wic-multivalue-block ' .  $this->entity . '">';
			foreach ( $groups as $group ) { 
				 $search_row .= '<div class = "wic-multivalue-field-subgroup wic-field-subgroup-' . esc_attr( $group->group_slug ) . '">';
						$group_fields = $wic_db_dictionary->get_fields_for_group ( $this->get_the_entity(), $group->group_slug );
						$search_row .= $this->the_controls ( $group_fields, $data_array );
						if ( $message_level == $group->group_slug ) { // using message_level to identify the privileged row
							$search_row .= $message; // here message is icon with email address in it, coming from WIC_Entity_Email	
						}
				$search_row .= '</div>';
			} 
		$search_row .= '</div></div>';
		return $search_row;
	}

}