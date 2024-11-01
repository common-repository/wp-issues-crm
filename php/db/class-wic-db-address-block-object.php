<?php
/*
* class-wic-db-email-message-object.php
*	supports retrieval of lists in format like $wpdb output
*
* use utf-8 on where pattern matching may include utf-8
*/

class WIC_DB_Address_Block_Object {

	public $email 		= ''; // address blocks often have an email
	public $phone 		= ''; // address blocks often have a phone
	public $first_name	= '';	
	public $middle_name	= '';
	public $last_name	= '';
	public $apartment_line = '';
	public $street_address_line		= '';
	public $city		= '';
	public $state 		= '';
	public $zip 		= '';
	public $has_closing = false;
	public $is_possible_name_only = false;
	public $block_sequence_number = 0;
	public $num_lines = 0;
	public $non_address_part = '';	
	private $form_variables_object;
	private $log_steps = false;  // should always be set to false after revisions tested and before commit

	public function __construct () {
		//load options with defaults if any set as blank (user can clear and refresh on settings page, but will not save until makes changes)
		$processing_options_response = WIC_Entity_Email_Process::get_processing_options();
		$this->form_variables_object = $processing_options_response['output'];
	}

	public function populate ( $text_block, $index ) { 
	
		if ( $this->log_steps ) {
			error_log ( print_r ( $text_block, true ) );
		}
	
		$this->block_sequence_number = $index;

		/*
		* set up address parsing pattern strings
		* state and territory abbreviations from http://pe.usps.gov/text/pub28/28apb.htm
		* street abbreviations from http://pe.usps.gov/text/pub28/28apc_002.htm
		* secondary unit designators from http://pe.usps.gov/text/pub28/28apc_003.htm#ep538629
		*/
		$states 			= $this->form_variables_object->states;
		$streets 			= $this->form_variables_object->streets;
		$special_streets 	= $this->form_variables_object->special_streets;
		$apartments 		= $this->form_variables_object->apartments;

   		$line_array = preg_split ( '#\v#', $text_block, NULL, PREG_SPLIT_NO_EMPTY );
		$this->num_lines = count ( $line_array );

		/*
		* read block lines in reverse -- city/state/zip line is most reliably identifiable
		* -- use it as screener for identifying other address lines positively
		* -- also, would like to nail down city/state/zip before name:
		* 	title in name can get interpreted as state abbrevations -- John Doe, MD looks city in Maryland
		* -- capture phone and additional email if included ( in any order )
		* 
		*/
		$found_city_state_line = -1;
		$unused_preaddress_line_index = -1;
		$used_lines = array();

		$this->stepwise_debugging_dump ( 'Pre-open', 'Before loop open ');
		for ( $i = ( $this->num_lines - 1 ); $i > -1; $i-- ) {
			// Note that lines are already filtered by text sanitizer to include only \pL0-9 .@_%+/\-\v
			// Blocks are trimmed, but could be white space at end of lines -- strip that
			$line = trim ( $line_array[$i] , ". " ); 
			$test = 0; // set test to zero for each line -- avoid unnecessary scanning
			// is this a closing line? -- "Yours sincerely", etc.?
			if ( self::is_a_closing ( $line ) ) { 
				$this->has_closing = true;
				// if have hit closing line, line below should have been name
				if ( ! in_array ( $i + 1, $used_lines ) &&  ( $i + 1 ) < $this->num_lines  ) {
					$this->parse_name_to_properties( trim ( $line_array[$i + 1] , ". " ) );
				}
				// regardless whether name found, if have hit a closing line, look no further above it;
				break;			
			}
			$this->stepwise_debugging_dump ( $line, 'After closing test');
	
			// if we don't have an email yet, see if this line includes one
			if ( '' == $this->email ) {
				$email_matches = array();
				// https://www.regular-expressions.info/email.html
				$test = preg_match ( '#(?i)\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b#', $line, $email_matches, PREG_OFFSET_CAPTURE, 0);
				if ( 1 == $test ) {
					if ( 
						// if email source selection set to auto, accept if at first position in line
						( 1 == $this->form_variables_object->email_source && 0 == $email_matches[0][1] ) ||
						// also accept email if allowing or forcing acceptance of body email regardless of line positioning
						2 == $this->form_variables_object->email_source ||
						3 == $this->form_variables_object->email_source
						) { 
							$this->email = $email_matches[0][0];
					}
				}
				
			}
			// if it isn't an email
			if ( 0 == $test ) {
				// and we don't have a phone, see if this line contains one
				$phone_matches = array();
				$phone_pattern = '#'.	// liberal matching approach . . . 
					'\b'			.	// string start or word boundary -- don't want to start this regex in the middle of a word
					'1?'			. 	// possible 1 (not capturing this) (if there are trailing spaces, hyphens or an open paren, the match will start with first digit of the first 3 group)
					'(\d{3})\s?\)?' .  	// capturing group for area code with an optional space and end paren
					'[.\-\s]{0,3}' 	. 	// up to three spaces dots or hyphens
					'(\d{3})'		.	// capturing group for next three positions
					'[.\-\s]{0,3}' 	. 	// up to three spaces dots or hyphens
					'(\d{4})'		.	// capturing group for last four positions
					'\b'			. 	// end -- punctuation, space or string end
					'#';
				if ( '' == $this->phone && preg_match ( $phone_pattern, $line, $phone_matches ) ) {
					$test = 1;
					foreach ( $phone_matches as $j => $segment ) {
						$this->phone .= ( $j ? $segment : '' ); 
					}
				}			
			}

			// if this isn't an email or a phone, look for true address elements
			if ( 0 == $test ) {
				// and we don't have a city/state line, see if it is one
				if ( '' == $this->city ) {
					// set up variables reflecting zip strictness options
					$zip_required 		 	= ( 3 == $this->form_variables_object->zip_required ) ? '?': '';
					$zip_connector_regex 	= ( 1 == $this->form_variables_object->zip_required ) ? '-' : '[ -]{0,3}';
					// set up variable reflecting capitalization strictness option
					$case_insensitive 		= ( 2 == $this->form_variables_object->capitalization ) ? '(?i)' : '';
					// set up variable for city word count
					$city_word_count 		= $this->form_variables_object->city_word_count;
					// assemble pattern
					$state_or_zip_last_pattern = 
						'#' . $case_insensitive // delimiter ( variable case sensitivity )
						// open first capturing group -- city words ( note that 0 position in match array is occupied by the full pattern )
						. '('
						.'^(?:[A-Z][A-Za-z\-]*[ .]{1,3}){1,' .	$city_word_count . '}' // one to four words (might be single letter) each followed by by spaces or punctuation
						. ')'
						// note that in the php regex, \b asserts the existence of a change rather than representing characters
						. '('  // open second capturing group -- state
						. $states
						. ')' // end capturing group (state)
						. '(?:[ .]{1,3}|\b)' // one to three word boundary characters punctuation or end of line-- 
						. '(' // optional zip code in capturing third group 
						. '\d{5}(?:' . $zip_connector_regex . '\d{4})?' // zip code pattern   
					 	. ')' . $zip_required	// close paren for zip code capture and marked optional or required
					 	. '$#u'; // end of string (already trimmed above) and delimiter
					$state_zip_matches = array(); 
					if 	( 1 == preg_match ( $state_or_zip_last_pattern, $line, $state_zip_matches, PREG_OFFSET_CAPTURE ) ) { 
						// if last token is zip code or 
						$this->city = trim ( $state_zip_matches[1][0], '. ' );
						$this->state = $state_zip_matches[2][0];
						$this->zip 	= isset ( $state_zip_matches[3] ) ? $state_zip_matches[3][0] : '';
						$found_city_state_line = $i;
						$test = 1;
					}
				} 
			}	
			// if haven't already used the line . . .
			if ( 0 == $test ) {
				/*
				* taking a loose approach to street address confirmation
				* have already narrowed block to one with a city/state/zip, so 
				* false positive rate should be low although many non-address phrases would screen in
				* 	-- e.g., "three dogs played on the beach"
				* deeming it better to capture sloppy user input in incoming emails
				*/
				$street_address_line_pattern = 
				'#(?i)' // delimiter and case insensitive mode
				// begin group of possible numbers
				. '^(?:one|two|three|four|five|six|seven|eight|nine|zero|'
				// continue possible numbers -- street number (up to six digits combined with up to five letters 
				. '\d{1,6}[A-Z]{0,5})'
				// initial number must be followed by space (not punctuation, up to three)
				. ' {1,3}' 
				// follow with one to four 1+ letter word/numbers followed by by spaces or punctuation
				// OR, a special street, standing alone (no street designator)
				. '(?:(?:' . $special_streets . ')|'
				. '(?:[A-Z0-9-]{1,20}[ .]{1,3}){1,4}' 
				. '(?:' // open a none-capturing group for street designator
				. $streets
				. ')' // end non-capturing group (street designator)
				. ')' // end wraparound group allowing special one word streets'
				. '(?:[ .]|\b){1,3}' // one to three word boundary characters or (boundary so end is OK) -- 
				. '(?:[A-Z0-9-]{1,10}(?:[ .]|\b){0,3}){0,4}' // post directional and apt designator "SW APT 1 REAR"  
				. '$#u';
				// . . . and we don't have a street_address line
				if ( '' == $this->street_address_line && 
					// and we have seen a city state line working up from the bottom
					$found_city_state_line > -1 &&
					// and we haven't gone more than two lines further up (could have apt line after street address)
					$found_city_state_line - $i < 3 ) {
					// look for street match pattern
					if 	( 1 == preg_match ( $street_address_line_pattern, $line ) ) { 
						$this->street_address_line = $line;
						$test = 1;
						// this flag may be set > -1 below in prior loops 
						// this is a reset of the flag
						$unused_preaddress_line_index = -1; 
					}
				} 
			}		
			$this->stepwise_debugging_dump ( $line, 'After email/phone/street/city/zip');
			// if haven't already used the line, and missing both street and city lines, try as a combined street, city, state zip line
			if ( 0 == $test &&  '' == $this->street_address_line  && -1 == $found_city_state_line  ) {
				$trailing_address_line_pattern = 
					'#(?i)' . // delimiter and case insensitive mode
					'^('	. // word boundary and open capturing group
					substr( $street_address_line_pattern, 6, -3 ) . // street pattern minus delimiters
					')'		. // close capturing group
					'[,\. ]{1,3}' . // space and possible intervening punctuation
					'(' . // replace truncated capturing group open
					substr( $state_or_zip_last_pattern, (( 2 == $this->form_variables_object->capitalization ) ? 7 : 3)); // zip
				$state_zip_matches = array(); 
				if 	( 1 == preg_match ( $trailing_address_line_pattern, $line, $state_zip_matches, PREG_OFFSET_CAPTURE ) ) {
					$this->street_address_line = trim ( $state_zip_matches[1][0], '. ' );
					$this->city = trim ( $state_zip_matches[2][0], '. ' );
					$this->state = $state_zip_matches[3][0];
					$this->zip 	= isset ( $state_zip_matches[4] ) ? $state_zip_matches[4][0] : '';
					$this->non_address_part = substr( $text_block, 0, - strlen( $state_zip_matches[0][0]) );
					$found_city_state_line = $i;
					$test = 1;
				}
			}

			// if haven't already used the line
			if ( 0 == $test ) {
				// and we don't have an apartment line, see if it is one
				if ( '' == $this->apartment_line && 
					// and we have seen a city state line working up from the bottom
					$found_city_state_line > -1 &&
					// and we haven't gone more than two lines further up (could have apt line before street address)
					$found_city_state_line - $i < 3 ) {
						/*
						* As above, taking a loose approach given narrow context
						* -- can imagine long strings before or after this type of designator, especially for departments
						* 
						* Just want to avoid initial numbers as in street
						*/
						$apt_line_pattern = 
						'#(?i)' // delimiter and case insensitive mode
						. '^(?:' 
						.  $apartments // starts with an apartment word
						. ')'
						. '([ .]|\b){1,3}'  // followed by space period or end
						. '(?:[A-Z0-9-]{1,10}([ .]|\b){0,3}){0,2}' // up to two trailing words not more than 10 chars, not more than 3 spaces after (.  )
						. '$#u'; // delimiter -- with end anchor							
					if 	( 1 == preg_match ( $apt_line_pattern, $line ) ) { 
						$this->apartment_line = $line;
						$test = 1;
					}
				} 
			}
			$this->stepwise_debugging_dump ( $line, 'After apartment line');

			// if on line before GOOD city/state/zip and haven't burned it 
			// and it isn't first line, take note -- may need to use it as plug later
			if ( 0 == $test && $i == $found_city_state_line - 1 && !$this->street_address_line && $i > 0 ) {
				$unused_preaddress_line_index = $i;			
			}
			
			// keep track of which lines were used
			if ( 0 != $test ) {
				$used_lines[] = $i;
			}
			
			// if on first line and haven't burned it otherwise
			//  . . .try it as a name line 
			if ( 0 == $test && 0 == $i && !$this->parse_name_to_properties( $line ) ) {
				// . . . if first line not a name, take a wild shot that  block is run on and finishes with a sentence including address -- if so extract address (no name)
				$text_block_array = explode('.', $text_block );
				$text_block_last_sentence = end ( $text_block_array );				
				if ( 0 ==  count( $used_lines ) && !$this->has_closing ) {
					$trailing_address_line_pattern = 
						'#(?i)' . // delimiter and case insensitive mode
						'\b('	. // word boundary and open capturing group
						substr( $street_address_line_pattern, 6, -3 ) . // street pattern minus delimiters
						')'		. // close capturing group
						'[,\. ]{1,3}' . // space and possible intervening punctuation
						'(' . // replace truncated capturing group open
						substr( $state_or_zip_last_pattern, (( 2 == $this->form_variables_object->capitalization ) ? 7 : 3)); // zip
					$state_zip_matches = array(); 
					if 	( 1 == preg_match ( $trailing_address_line_pattern, trim( $text_block_last_sentence, ', \t\n\r\0\x0B'), $state_zip_matches, PREG_OFFSET_CAPTURE ) ) {
						// if last token is zip code or 
						$this->street_address_line = trim ( $state_zip_matches[1][0], '. ' );
						$this->city = trim ( $state_zip_matches[2][0], '. ' );
						$this->state = $state_zip_matches[3][0];
						$this->zip 	= isset ( $state_zip_matches[4] ) ? $state_zip_matches[4][0] : '';
						$this->non_address_part = substr( $text_block, 0, - strlen( $state_zip_matches[0][0]) );
					}
				}				
			}	
			$this->stepwise_debugging_dump ( $line, 'After name line');
		}  // process line array 

		// also, since done, if didn't burn pre-address line and didn't find another good streetaddress
		// ( if find a good street address line, reset to -1 )
		// likely institutional address or well-known building name like State House
		if ( $unused_preaddress_line_index > -1  ) {
			$this->street_address_line = trim ( $line_array[$unused_preaddress_line_index] , ". " ); 
		}	
		$this->stepwise_debugging_dump ( 'after loop', 'After install pre-address');

		// consider integrity of a closing block -- address without a name makes no sense,probable error
		// do not discard block, but do discard address 
		if ( $this->has_closing && $this->city && !$this->last_name ) {
			$this->city 			= '';
			$this->state			= '';
			$this->zip 				= '';
			$this->street_address_line = '';
			$this->apartment_line 	= '';
		}
		$this->stepwise_debugging_dump ( 'after loop', 'After closing integrity check');


		// at this stage, do not want to discard the block . . . 
		if 	( 
			  // retain closing lines for analysis even if alone
			  $this->has_closing ||
			  // last_name populated ( following closing or has first, possibly, only line )
			  $this->last_name ||
			  // if it has an email that is part of an address block -- auto email_source
			  (
			  	$this->email && 
			  	1 == $this->form_variables_object->email_source &&
			  	( $this->zip > '' || $this->city > '' )
			  ) || 
			  // if it has an email at all -- soft
			  (
			  	$this->email  && 
			  	( 2 == $this->form_variables_object->email_source || 3 == $this->form_variables_object->email_source  )
			  ) ||			    
		 	  // if it has a zip code 
			  $this->zip ||  
			  // or if has address without zip and but zip not required in settings	
			  ( $this->city && $this->form_variables_object->zip_required == 3 ) ) {
			return ( true );
		} else {
			return ( false );
		}
	} // populate
	
	private function is_a_closing ( $line ) {
		$closings	 			= $this->form_variables_object->closings;
		return preg_match ( '#(?i)^(' . $closings . ')$#u', trim( $line, ',. ' ) );
	}
	
	
	private function parse_name_to_properties ( $line ) {
		$name = $this->parse_name( $line );
		if ( false !== $name ) {
			$this->first_name 	= $name['first'];
			$this->middle_name 	= $name['middle'];
			$this->last_name 	= $name['last'];
			return true;
		}
		return false;
	}
	
	
	public function parse_name ( $name_line ) {

		// set up parse variables
		$pre_titles 			= $this->form_variables_object->pre_titles;
		$post_titles 			= $this->form_variables_object->post_titles;
		$titles 				= $pre_titles . '|' . $post_titles;
		$minimum_name_length 	= $this->form_variables_object->minimum_name_length;
		$maximum_name_length 	= $this->form_variables_object->maximum_name_length;
		$non_names				= $this->form_variables_object->non_names;

		// strip parenthetic expressions
		$name_line = preg_replace( '#\s*\([\s\w,.]*\)\s*#u', '', $name_line );

		// make sure line as no padding 
		$name_line = trim ( $name_line, ',. ');
		
		// reject closing lines
		if ( self::is_a_closing ($name_line) ) {
			return false;
		}

		// reject non name lines
		if ( preg_match ( '#(?i)\b(' . $non_names . ')\b#u', trim( $name_line, ',. ' ) ) ) {
			return false;
		}

		// set up return array
		$name = array (
			'first' => '',
			'middle' => '',
			'last' => '',
			'found_title' => false
		);
		
		// match a single pre-title and any trailing punctuation -- must have boundary
		$pre_title_check_pattern = '#(?i)' 
			. '^(?:' . $pre_titles . ')[, \.]+' // start, a pretitle, some punctuation or space
			. '#u';
		
		// match a series of post titles to the end
		$post_title_check_pattern = '#(?i)' 
			. '([, \.]+(?:' . $post_titles . ')){1,4}[, \.]*$' // some punctuation, a post_title and more punctuation or end
			. '#u';
		
		// test for and strip pre and post titles
		$found_title = false;
		if ( 1 == preg_match ( $pre_title_check_pattern, $name_line ) ) {
			$name['found_title'] = true;	 
			$name_line = preg_replace ( $pre_title_check_pattern, '', $name_line ); 
		} 
		if ( 1 == preg_match ( $post_title_check_pattern, $name_line ) ){	
			$name['found_title'] = true;
			$name_line = preg_replace ( $post_title_check_pattern, '', $name_line );
		
		}							

		// having stripped titles, if have less than min or more than max words, probably not really a name -- quit
		$name_match_pattern = '#'  // delimiter
			. '^([\pL-]{1,20}\b[, \. ]{0,3}){' . $minimum_name_length . ',' . $maximum_name_length . '}' 
			. '$#u'; // end and delimiter
		if ( 0 == preg_match ( $name_match_pattern, $name_line ) ) {
			return ( false );
		}
		
		
		// if there is a remaining comma, it should be signalling that first word is last name
		// this will not occur normally when called from address processing because commas are filtered out
		// but this branch will be exercised when function is called from elsewhere
		$ln_matches = array();
		// if have a string of words followed by a comma (know that have 2-4 words), then assume that these are last names
		// commas have already been stripped from end (initially on trim and further on removal of any titles)
		// so, this implies have two names at least, no need to test minimum
		if ( 1 ==  preg_match( '#(^[\p{L}_\- ]{3,60}),#u', $name_line, $ln_matches ) ) {
			$name['last'] = $ln_matches[1];
			$remaining_names = preg_replace ( '#'. $ln_matches[0] . '#u', '', $name_line );
			$name_tokens = preg_split ( '#[\., ]+#',  $remaining_names, NULL, PREG_SPLIT_NO_EMPTY );
			$name['first'] = isset ( $name_tokens[0] ) ? trim($name_tokens[0]) : '' ;
			if ( count ( $name_tokens ) > 1 ) {
				array_shift ( $name_tokens );
				$name['middle'] = implode ( ' ', $name_tokens );
			}
		// if no comma, split into tokens and process in order -- assume last name is last token
		// assign extra names to middle name
		} else {
			$name_tokens = preg_split ( '#[\. ]+#', $name_line, NULL, PREG_SPLIT_NO_EMPTY );
			$num_names = count ( $name_tokens ); 
			if ( $num_names > 0 ) {
				$name['last'] = $name_tokens[ $num_names - 1 ];
				if ( $num_names > 1 ) {
					$name['first'] = $name_tokens[ 0 ];
					if ( $num_names > 2 ) {
						for ( $k = 1; $k < $num_names - 1; $k++ ) {
							$name['middle'] .= $name_tokens[$k] . ' ';
						}
						$name['middle'] = trim( $name['middle'] );
					} // > 2 tokens
				} // > 1 token 	
			} else { //  0 tokens
				return ( false );
			}	
		} // no comma else
		return ( $name );
	} // parse name		

	// for debugging
	private function stepwise_debugging_dump ( $line, $step, $include_form_variables = false) {
	
		if ( $this->log_steps ) {
			$dump = '';
			foreach ( $this as $property => $value ) {
				if ( 'form_variables_object' != $property || $include_form_variables ){
					$dump .= ( "property $property: " . print_r ( $value, true ) . "\n" );
				}
			}
			error_log ( "line $line " . $step . ":\n" . $dump );
		}
	}

} // class 
