<?php
/*
* class-wic-form.php
* creates a generic form layout for wic entities coupled to field group list
* entity classes may use different forms
*
*/

abstract class WIC_Form_Parent  {
	
	/* 
	*
	*	This class standardizes all the forms that accept input in wp-issues-crm.
	*
	*  One public function -- layout form
	*	Abstract protected functions for child forms to fill in form details 
	*
	*	Also includes public static function for standard button creation
	*
	*/


	// associate form with entity in data dictionary
	protected function get_the_entity() {
		$entity = strtolower ( substr ( get_called_class(), 9 ) );
		return ( $entity );
	}
	// get the field groups for the entity from the data dictionary	
	protected function get_the_groups () {
		global $wic_db_dictionary;
		$groups = $wic_db_dictionary->get_form_field_groups( $this->get_the_entity() );
		return ($groups );
	}
	// add attributes to the form ( e.g. onsubmit parameter )
	abstract protected function supplemental_attributes();
	// define the top row of buttons (return a row of wic_form_buttons)
	protected function get_the_buttons( &$data_array ) {
		$button_args_main = array(
			'entity_requested'			=> $this->get_the_entity(),
			'action_requested'			=> 'form_save_update',
			'button_label'				=> $this->get_the_verb( $data_array ),
		);	
		return ( $this->create_wic_form_button ( $button_args_main ) );
	}
	// define the form message (return a message)
	abstract protected function format_message ( &$data_array, $message );
	// coloring of message box
	protected $message_level_to_css_convert = array(
		'guidance' 	=> 'wic-form-routine-guidance',
		'error' 		=> 'wic-form-errors-found',	
		'good_news'	=> 'wic-form-good-news',
	);
	// form_control is standard, present in all control classes
	protected function get_the_formatted_control ( $control ) { 
		return ( $control->form_control() ); 
	}

	// legends
	protected function get_the_legends( $sql = '' ) {
		if ( '' < $sql ) {
			return(	'<p class = "wic-form-legend">' . __('Search SQL was:', 'wp-issues-crm' ) . $sql . '</p>' );	
		}
	}

	// screen out some groups of fields (return true or false)
 	protected function group_screen( $group ){
 		return true;
 	}
	// screen groups for special handling
	protected function group_special ( $group ) {
		return false;	
	}
	
	// add material before last row of buttons	
	abstract	protected function pre_button_messaging ( &$data_array );
	// add material ( e.g., list ) after the form (not presently implemented in any child form)
	abstract protected function post_form_hook ( &$data_array ); 


	/* 
	* layout_form -- main form function
	* 	references data_dictionary for grouping of variables, but gets all values from passed array of controls $data_array
	*  
	*	arguments expected:
	*		$data_array, formatted as field_slug => control object
	*		$message, to be appended to generic form header
	*		$message_level -- determines color of message box -- according to $message_level_to_css_convert property of the form
	*			default options set in this abstract class are: guidance, error, good_news
	*		$sql = search sql to display in legend area as info 
	*/
	public function layout_form ( &$data_array, $message, $message_level, $sql = '' ) {
	
		global $wic_db_dictionary;		
		
		?><div id='wic-forms'> 
		
		<form id = "<?php echo $this->get_the_form_id(); ?>" <?php $this->supplemental_attributes(); ?> class="wic-post-form" method="POST" autocomplete = "on">

			<?php // child class must define message, possibly using the $message in calling parameters of layout form	
			$message = $this->format_message ( $data_array, $message );	?>
			<div id="post-form-message-box" class = "<?php echo $this->message_level_to_css_convert[$message_level]; ?>" ><?php echo esc_html( $message ); ?></div>

			<?php // child class must define buttons, without receiving the calling parameters of layout form  
		   $buttons = $this->get_the_buttons( $data_array ); 
		   		echo $buttons;	?>			   
		
			<?php	
			$group_array = $this->generate_group_content_for_entity( $data_array );
			extract ( $group_array );
		
			// output form
			echo 	'<div id="wic-form-body">' . '<div id="wic-form-main-groups">' . $main_groups . '</div>' .
					'<div id="wic-form-sidebar-groups">' . $sidebar_groups . '</div>' . '</div>';		
		
			// child class may insert material here
			$this->pre_button_messaging( $data_array );		
		
			// final button group div
			echo '<div class = "wic-form-field-group" id = "bottom-button-group">';?>
				<?php	echo $buttons; // output second instance of buttons ?> 
			 	<?php wp_nonce_field( 'wp_issues_crm_post', 'wp_issues_crm_post_form_nonce_field', true, true ); ?>
				<?php echo $this->get_the_legends( $sql ); ?>
			</div>								
		</form>
		<?php // child class may insert messaging here 
		$this->post_form_hook( $data_array ); ?>
		</div>
		
		<?php 
		
	}

	protected function generate_group_content_for_entity( &$data_array ) {
		
		global $wic_db_dictionary;
		// set up buffers for field output[.=] in two areas of the screen
		$main_groups = '';
		$sidebar_groups = '';

		// go to the data dictionary and get the list of groups for the entity			
		$groups = $this->get_the_groups();

	   foreach ( $groups as $group ) { 

	   	// set up buffer for all group content
			$group_output = '';
			
			// child class MUST define a group screen that returns true to show any groups
			if ( $this->group_screen( $group ) ) {	
			
				$group_output .= '<div class = "wic-form-field-group" id = "wic-field-group-' . esc_attr( $group->group_slug  ) . '">';				
				
					// show-hide button for group
					$button_args = array (
						'class'			=> 	'field-group-show-hide-button',		
						'name_base'		=> 	'wic-inner-field-group-',
						'name_variable' => 	$group->group_slug ,
						'label' 			=> 	$group->group_label ,
						'show_initial' => 	$group->initial_open,
					);
					$group_output .= $this->output_show_hide_toggle_button( $button_args );			
				
					// wrapper for group to show-hide 
					$show_class = $group->initial_open ? 'visible-template' : 'hidden-template';
					$group_output .= '<div class="' . $show_class . '" id = "wic-inner-field-group-' . esc_attr( $group->group_slug ) . '">' .					
					'<p class = "wic-form-field-group-legend">' . $group->group_legend . '</p>'; // group legend originates in dictionary, not updateable, do not escape -- allow html, note that pre-enclosed in p marks.
					
					// here is the main content -- either   . . .
					if ( $this->group_special ( $group->group_slug ) ) { 			// if implemented returns true -- run special function to output a group
						$special_function = 'group_special_' . $group->group_slug; 	// must define the special function too 
						$group_output .= $this->$special_function( $data_array );
					} else {	// standard main form logic 	
						$group_fields =  $wic_db_dictionary->get_fields_for_group ( $this->get_the_entity(), $group->group_slug ); 
						$group_output .= $this->the_controls ( $group_fields, $data_array );
					}
						
				$group_output .= '</div></div>';	
				
			} 
			
			// put group output into either side or main buffer
			if ( $group->sidebar_location ) {
				$sidebar_groups .= $group_output;  				
			} else {
				$main_groups .= $group_output;
			} 
	   } // close foreach group

		return ( array( 
			'sidebar_groups' => $sidebar_groups, 
			'main_groups' => $main_groups,				
			) 
		);
	
	}



	// return form identity in css form
	protected function get_the_form_id() {
		return( strtolower( str_replace( '_', '-', get_class( $this ) ) ) ); 
	}

	// prepare controls allowing child form to define arguments selected for control ( save, update, search ) 
	protected function the_controls ( $fields, &$data_array ) {
		$controls_output = '';
		foreach ( $fields as $field ) {
			$controls_output .= '<div class = "wic-control" id = "wic-control-' . str_replace( '_', '-' , $field ) . '">' . $this->get_the_formatted_control ( $data_array[$field] ) . '</div>';
		}	
		return ( $controls_output );
	}

	/*
	*
	*	output show-hide-button ( field group headers are set up as buttons that show/hide field groups)
	*  	calls togglePostFormSection in wic-utilities.js
	*
	*/
	public static function output_show_hide_toggle_button( $args ) {

		$class 			= 'field-group-show-hide-button';		
		$name_base 		= 'wic-inner-field-group-'  ;
		$name_variable = ''; // group['name']
		$label = ''; // $group['label']
		$show_initial = true;
		
		extract( $args, EXTR_OVERWRITE );

		$show_legend = $show_initial ? '<span class="dashicons dashicons-arrow-up"></span>' : '<span class="dashicons dashicons-arrow-down"></span>';

		
		$button =  '<button ' . 
		' class = "' . $class . '" ' .
		' id = "' . $name_base . esc_attr( $name_variable ) . '-toggle-button" ' .
		' type = "button" ' .
		' >' . esc_html ( $label ) . '<span class="show-hide-legend" id="' . $name_base . esc_attr( $name_variable ) .
		'-show-hide-legend">' . $show_legend . '</span>' . '</button>';

		return ($button);
	}

	protected function get_the_verb ( &$doa ) {
		return ( ( isset ( $doa['ID'] ) && $doa['ID']->get_value() ) ? __( 'Update', 'wp-issues-crm' ) : __( 'Save', 'wp-issues-crm' ) );
	}

	/*
	*
	*	All buttons named wic_form_button are answered by the button handler in WIC_Dashboard_Main
	*	This function is the exclusive creator of submit buttons in the system.  
	*	It defines the interface to the button handler.  
	*
	*/
	static public function create_wic_form_button ( $control_array_plus_class ) { 
	
		$entity_requested			= '';
		$action_requested			= '';
		$id_requested				= 0 ;
		$button_class				= 'wic-form-button';
		$button_label				= '';
		$title						= '';
		$name						= 'wic_form_button';
		$id							= '';
		$value						= '';
		$formaction					= '';
		$type						= 'submit';
		$disabled					= false;

		extract ( $control_array_plus_class, EXTR_OVERWRITE );

		// supports the standard WIC string button value or can be overridden by a set value as in list export buttons
		$button_value = $value > '' ? $value : $entity_requested . ',' . $action_requested  . ',' . $id_requested;
		$id_phrase = $id > '' ? ' id = "' . $id . '" ' : ' ';
		$formaction_phrase = $formaction > '' ? ' formaction = "' . $formaction . '" ' : ' ';
		$disabled_phrase =  $disabled ? ' disabled ' : '';
	
		$button =  '<button ' . $disabled_phrase . ' class = "' . $button_class . '" title = "' . $title . '" type="'. $type . '" name = "' . $name . '"' . $id_phrase . $formaction_phrase . ' value = "' . $button_value . '">' . $button_label . '</button>';		
		return ( $button );
	}



}