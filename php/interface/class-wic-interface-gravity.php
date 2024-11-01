<?php
/*
*
*	wic-interface-gravity.php
*
*/

class WIC_Interface_Gravity extends WIC_Interface_Parent {

	const INTERFACE_TYPE = 'gravity';

	public function activate_interface() { 
		add_action( 'gform_after_submission', array ( $this, 'load_posted_data'), 10, 2 );
	}

	// in this case, a filter, passing $posted_data through;
	public function load_posted_data ( $posted_data, $form_data = array() ) {

		$current_form = $posted_data['form_id'];
		// if listening to this form . . .
		if ( ! in_array( $current_form, $this->identifiers ) ) {
			return false;
		}
		$this->current_form = $current_form;

		// find and convert state to abbreviated state -- standard gravity address field uses full name of state
		foreach ( $form_data['fields'] as $field ) {
			if ( 'address' == $field['type'] ) {
				foreach ( $field->inputs as $input ) {
					if ( isset ( $posted_data[$input['id']] ) ) {
						if ( stripos ( $input['label'], 'state' )  > -1 ) {
							$posted_data[$input['id']] = self::abbreviate_state ( $posted_data[$input['id']] );						
							break 2;
						}
					}
				}
			} elseif ( 'list' == $field['type'] ) {
				if ( !empty ( $posted_data[$field['id']] ) ) {
					$list_array = unserialize ( $posted_data[$field['id']] );
					$posted_data[$field['id']] = implode ( '<br/>', $list_array ) ;			
				}
			}
		}

		$this->posted_data = $posted_data;

		$this->do_interface(); 
	} 
	
	private function standardize_string ( $string ) {
		return preg_replace ( '/[\s\/.,-]+/','_', strtolower( $string ) ); ;
	
	}
	
	public static function get_field_list ( $form_id  ) {
		$form = GFAPI::get_form ( $form_id ); 
		$field_list = array();
		foreach ( $form['fields'] as $field ) { 
			// don't include fields that do not return data
			if ( in_array( $field['type'], array( 'html', 'captcha', 'section', 'page' ) ) ) {
				continue;
			}
			// complex fields have an array of inputs (each an array), each with their own id
			if ( ! empty ( $field->inputs ) && $field['type'] != 'time' ) { // time appears to be the unique compound field that gets recombined in post data
				foreach ( $field->inputs as $input ) {
					if ( ! empty ( $input['isHidden'] ) ){
						continue;
					}
					$field_id = $input['id'];
					$combined_label = $field->label . ', ' . $input['label'];
					$field_name = $input['label'] > '' ? $input['label'] : $field->label . '_' . $input['id'];
					$field_list[] = array ( $field_id, $field_name); 
				}
			} else {
				$field_id = $field->id;
				if ( ! empty ( $field->adminLabel ) ) {
					$field_name = $field->adminLabel;
				} else {
					$field_name =  ! empty( $field->label ) ? $field->label : $field->id ;
				}
				$field_list[] = array ( $field_id, $field_name); 
			} 
		}
		return $field_list; 	
	}
	
	
	public static function abbreviate_state ( $string ) {
		$states_array = array (
			array('Alaska','Alaska', 'AK'),
			array('Arizona','Ariz.', 'AZ'),
			array('Arkansas','Ark.', 'AR'),
			array('California','Calif.', 'CA'),
			array('Colorado','Colo.', 'CO'),
			array('Connecticut','Conn.', 'CT'),
			array('Delaware','Del.', 'DE'),
			array('Florida','Fla.', 'FL'),
			array('Georgia','Ga.', 'GA'),
			array('Hawaii','Hawaii', 'HI'),
			array('Idaho','Idaho', 'ID'),
			array('Illinois','Ill.', 'IL'),
			array('Indiana','Ind.', 'IN'),
			array('Iowa','Iowa', 'IA'),
			array('Kansas','Kans.', 'KS'),
			array('Kentucky','Ky.', 'KY'),
			array('Louisiana','La.', 'LA'),
			array('Maine','Maine', 'ME'),
			array('Maryland','Md.', 'MD'),
			array('Massachusetts','Mass.', 'MA'),
			array('Michigan','Mich.', 'MI'),
			array('Minnesota','Minn.', 'MN'),
			array('Mississippi','Miss.', 'MS'),
			array('Missouri','Mo.', 'MO'),
			array('Montana','Mont.', 'MT'),
			array('Nebraska','Nebr.', 'NE'),
			array('Nevada','Nev.', 'NV'),
			array('New Hampshire','N.H.', 'NH'),
			array('New Jersey','N.J.', 'NJ'),
			array('New Mexico','N.M.', 'NM'),
			array('New York','N.Y.', 'NY'),
			array('North Carolina','N.C.', 'NC'),
			array('North Dakota','N.D.', 'ND'),
			array('Ohio','Ohio', 'OH'),
			array('Oklahoma','Okla.', 'OK'),
			array('Oregon','Ore.', 'OR'),
			array('Pennsylvania','Pa.', 'PA'),
			array('Rhode Island','R.I.', 'RI'),
			array('South Carolina','S.C.', 'SC'),
			array('South Dakota','S.D.', 'SD'),
			array('Tennessee','Tenn.', 'TN'),
			array('Texas','Tex.', 'TX'),
			array('Utah','Utah', 'UT'),
			array('Vermont','Vt.', 'VT'),
			array('Virginia','Va.', 'VA'),
			array('Washington','Wash.', 'WA'),
			array('West Virginia','W.Va.', 'WV'),
			array('Wisconsin','Wis.', 'WI'),
			array('Wyoming','Wyo.', 'WY'),
		);
		// test values
		foreach ( $states_array as $state ) {
			if ( strtolower( $string ) == strtolower ( $state[0] ) || strtolower( $string ) == strtolower ( $state[1] ) )  {
				$string = $state[2];
				break;
			}
		}
		// if found, return postal abbreviation, if not, return as was
		return $string;
	}
	
}


