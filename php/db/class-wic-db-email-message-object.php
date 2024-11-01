<?php
/*
* 	class-wic-db-email-message-object.php
*
*	takes an email (identified by imap_stream and UID) and extracts and sanitizes results
*	
*	see classification of outcome codes	
*
*   this object is saved as serialized by the parse process and is retrieved by the inbox detail view and by the email processor
*
*/

class WIC_DB_Email_Message_Object {
	
	/*
	* this is the master template 
	*   upload tables derived directly from it for maintainability
	*	-- see wic_db_access_upload_email.php
	*
	* use utf-8 options on for preg_match where pattern may contain utf-8
	*/
	// final product properties based on all information
	public $email_address	= '';
	public $phone_number 	= ''; 
	public $first_name	= '';	
	public $middle_name	= '';
	public $last_name	= '';
	public $address_line = '';
	public $city		= '';
	public $state 		= '';
	public $zip 		= '';
	public $activity_date = '';	
	public $apartment_only_line = '';
	public $street_address_only_line = '';
	
	// summary statement of quality -- see discussion below
	public $outcome;
	/*	
	*	OUTCOME IS CLASSIFICATION (NOT RANKING) OF CASES 
	*	(NAME PARSEABLE FROM BODY X ADDRESS PARSEABLE FROM BODY -- 3 VALID PERMUTATIONS) 
	*		
	*	Outcome is sum of points as follows:
	*		8 points for a successful address parse with city > '';
	*		4 points for a successful name parsed from body text;
	*		2 points for an email extracted from an address block containing city
	*		1 point for a name from reply_ or from_ email personal found in body text 
	*
	*   See parse coding in WIC_Entity_Email_Inbox_Parse::evaluate_parse_quality
	*/
	
	// object properties corresponding to parsed object from headerinfo()
	public $to_personal 		= '';	// extracted from to object
	public $to_email 			= '';	// extracted from to object
	public $from_personal 		= '';	// extracted from from object
	public $from_email 			= '';	// extracted from from object	
	public $from_domain 		= ''; 	// domain portion of $from_email
	public $reply_to_personal 	= '';	// extracted from reply_to object
	public $reply_to_email	 	= '';	// extracted from reply_to object
	public $to_array 			= array(); 	// processed from to object;
	public $cc_array 			= array(); 	// processed from from object	
	public $raw_date 			= '';	// The message date as found in its headers 
	public $email_date_time 	= '';	// The message date_time in blog local timze zone in mysql format (for display and sorting only)	
	public $subject 			= ''; 	// The message subject 
	public $message_id 			= ''; 	// Unique identifier (permanent unique identifier -- retained only for research purposes)
	// key value header array for use only by locally-defined tab functions
	public $internet_headers;
	// structure parts accessed through imap body fetch functions
	protected $text_body;  		// stripped for parsing -- prefer to generate from raw text body but can strip from raw html body
	public $raw_html_body;		// only used for display in message viewer
	public $sentence_md5_array; // used to lookup previous similar mappings
	// size control variables
	protected $max_packet_size;
	// error properties
	public $uid_still_valid; // was uid available at start of process -- not captured by a process_email running ahead of this one
	// identifier -- need for attachment links
	public $inbox_image_id;
	// presentation variables -- loaded in object for transport across parse steps, but also saved on image record . . . never changed aftre parse
	public $category = '';
	public $snippet = '';
	public $account_thread_id = '';
	/* 
	* 
	* main message object build function for IMAP
	*
	* ID is inbox image message ID, UID is the server UID
	*/
	public function build_from_stream_uid ( $ID, &$IMAP_stream, $UID, $max_packet_size ) {
		$this->inbox_image_id = $ID;
	
		// set max_packet_size for use in walker (also test vs 16m reasonable memory usage)
		$this->max_packet_size = $max_packet_size < 16000000 ? $max_packet_size : 16000000; // waste no room for overhead -- better to downsize in parse_messages if necessary 
	
		// get header info object, fail if not found uid
		$this->uid_still_valid = true; 
		$header = false;
		$header = @imap_fetchheader ( $IMAP_stream, $UID, FT_UID );

		if ( ! $header ) {
			$this->uid_still_valid = false;
			return;		
		}
		
		$this->internet_headers = self::convert_internet_headers_to_key_value_array ( $header );
		self::populate_header_info( imap_rfc822_parse_headers( $header ) );
		/*
		*
		* now extract body parts
		*
		*/
		// begin structure walk by fetching top level structure
		$imap_structure = imap_fetchstructure( $IMAP_stream, $UID, FT_UID );
		if ( ! isset ( $imap_structure->parts ) ) { // simple
        	$this->message_structure_walker( $IMAP_stream, $UID, $imap_structure, 0, false );  // pass 0 as part-number
   		} else {  
			/*
			* multipart: cycle through each part
			* assumption is that 1 + partno0 ( the index in the array ) corresponds to the numbering of the parts 
			* $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple (see recursion below)
			*/
			$subtype = $imap_structure->ifsubtype ? strtolower( $imap_structure->subtype ) : '';
			foreach ( $imap_structure->parts as $partno0=>$p ) {
				$this->message_structure_walker( $IMAP_stream, $UID, $p, $partno0 + 1, 'alternative' == $subtype );
			}
        }

		$this->populate_body_info();

		// do address extraction and build non_address_block md5 map
		$this->extract_address();	
		unset ( $this->text_body ); // no downstream use.
	}

	// reinstatiate object from database
	public static function build_from_image_uid ( $folder, $UID ) {  
		global $wpdb;
		$inbox_table = $wpdb->prefix . 'wic_inbox_image';

		$sql = "
			SELECT * 
				FROM $inbox_table
				WHERE 
					full_folder_string = '$folder' AND
					folder_uid = $UID AND
					no_longer_in_server_folder = 0 AND
					to_be_moved_on_server = 0 AND
					serialized_email_object > ''	
				";
		$message_array = $wpdb->get_results( $sql );

		// unserialize the main object
		if ( $message_array ) {
			$message_object = unserialize( $message_array[0]->serialized_email_object );
		} else {
			return false;
		}
		if ( $message_object ) {
			// add in properties maintained unserialized on record
			$message_object->inbox_image_id				=  $message_array[0]->ID;
			$message_object->mapped_issue 				=  $message_array[0]->mapped_issue;
			$message_object->mapped_pro_con 			=  $message_array[0]->mapped_pro_con;
			$message_object->guess_mapped_issue 		=  $message_array[0]->guess_mapped_issue;
			$message_object->guess_mapped_issue_confidence = $message_array[0]->guess_mapped_issue_confidence;
			$message_object->guess_mapped_pro_con 		=  $message_array[0]->guess_mapped_pro_con;
			$message_object->non_address_word_count 	=  $message_array[0]->non_address_word_count;
			$message_object->assigned_constituent	 	=  $message_array[0]->assigned_constituent;
			$message_object->parse_quality	 			=  $message_array[0]->parse_quality;
			$message_object->is_my_constituent			=  $message_array[0]->is_my_constituent_guess; // translating from guess to non guess for save purposes
			$message_object->display_date				=  $message_object->email_date_time;
			$message_object->inbox_defined_staff		=  $message_array[0]->inbox_defined_staff;
			$message_object->inbox_defined_issue		=  $message_array[0]->inbox_defined_issue;
			$message_object->inbox_defined_pro_con		=  $message_array[0]->inbox_defined_pro_con;
			$message_object->inbox_defined_reply_text	=  $message_array[0]->inbox_defined_reply_text;
			$message_object->inbox_defined_reply_is_final	=  $message_array[0]->inbox_defined_reply_is_final;

			// at time of parse, attempted to find matching constituent
			if ( $message_array[0]->assigned_constituent ) {
				$constituent_object = WIC_DB_Access_WIC::get_constituent_names( $message_array[0]->assigned_constituent );
				if ( $constituent_object ) {
					$message_object->first_name 	= $constituent_object->first_name 	? $constituent_object->first_name 	: $message_object->first_name;
					$message_object->middle_name 	= $constituent_object->middle_name 	? $constituent_object->middle_name 	: $message_object->middle_name;
					$message_object->last_name 		= $constituent_object->last_name 	? $constituent_object->last_name 	: $message_object->last_name;
				}
			} 

			return ( $message_object );
		} else {
			return false;
		}
		
	}

	// reinstatiate object from database by ID -- streamlined for message viewer
	public static function build_from_id ( $selected_page, $ID ) { // call with $selected_page = done to get an inbox image record
	
		global $wpdb;
		$table = 'done' == $selected_page ? $wpdb->prefix . 'wic_inbox_image' : $wpdb->prefix . 'wic_outbox' ;

		$sql = "
			SELECT * 
				FROM $table
				WHERE ID = $ID
				";
		$message_array = $wpdb->get_results( $sql );

		// unserialize the main object if found
		if ( $message_array ) {
			$message_object = unserialize( $message_array[0]->serialized_email_object );
		} else {
			return false;
		}
		
		// return the unserialized object if successful
		if ( $message_object ) {
			$message_object->message_id = $ID;
			$message_object->outbox =  ( $selected_page == 'done' ? 0 : 1 );
			switch ( $selected_page ) {
				case 'done':
					$message_object->assigned_constituent = $message_array[0]->assigned_constituent; // not present on outbox record
					$message_object->display_date =  $message_object->email_date_time;
					break;
				case 'outbox':
				case 'draft':
					$message_object->display_date =  $message_array[0]->queued_date_time;
					break;
				case 'sent':
					$message_object->display_date =  $message_array[0]->sent_date_time;
					break;			
			}
			return $message_object;
		} else {
			return false;
		}
		
	}
	

	/*
	* support main build functions -- imap and gmail
	*
	*/
	protected function populate_header_info ( $imap_headerinfo ) { 
	
		// load properties from header info -- note that all the non-address fields can be encoded, so decode them all
		$this->to_personal			= isset ( $imap_headerinfo->to ) ? $this->email_array_to_display_safe_personal  ( $imap_headerinfo->to ) : '';
		$this->to_email				= isset ( $imap_headerinfo->to ) ? $this->email_array_to_display_safe_address  ( $imap_headerinfo->to ) : '';
		$this->from_personal		= isset ( $imap_headerinfo->from ) ? $this->email_array_to_display_safe_personal ( $imap_headerinfo->from ) : '';
		$this->from_email			= isset ( $imap_headerinfo->from ) ?  $this->email_array_to_display_safe_address  ( $imap_headerinfo->from ) : '';
		$this->from_domain			= $this->domain_only ( $this->from_email );
		$this->reply_to_personal	= isset ( $imap_headerinfo->reply_to ) ? $this->email_array_to_display_safe_personal ( $imap_headerinfo->reply_to) : '';
		$this->reply_to_email		= isset ( $imap_headerinfo->reply_to ) ? $this->email_array_to_display_safe_address  ( $imap_headerinfo->reply_to) : '';	
		$this->raw_date		 		= isset ( $imap_headerinfo->date ) 		?  	self::sanitize_incoming ( $imap_headerinfo->date ) : '' ;			
		$this->subject				= isset ( $imap_headerinfo->subject ) 	?	 apply_filters ( 'wp_issues_crm_local_subject_pre_filter', self::sanitize_incoming( $imap_headerinfo->subject ) ) : '' ;
		// repack arrays of from/to information
		$this->to_array = self::repack_address_array( isset ( $imap_headerinfo->to ) ? $imap_headerinfo->to : false );
		$this->cc_array = self::repack_address_array( isset ( $imap_headerinfo->cc ) ? $imap_headerinfo->cc : false );
		// sanitize_date ( will be blank if cannot process )
		$this->activity_date = WIC_Control_Date::sanitize_date( $this->raw_date );		
		$this->email_date_time = self::sanitize_date_time( $this->raw_date );		
	
	}

	/*
	* support main build functions -- imap and gmail
	*
	*/
	protected function populate_body_info () {
		/*
		*
		* html body, if present at this stage is safe html; otherwise use text, but with p's inserted and tags balanced
		*
		*/
		$this->raw_html_body = $this->raw_html_body ? 
			$this->raw_html_body  : 
			wpautop( balanceTags( $this->text_body ) ) ;	
		/*
		*
		* retained text_body is hard strip of text ( or html body ), basically preserving words and breaks, no tags 
		* prefer text version for parsing -- does better in MS styled submissions; also complex html may read as false line breaks)
		*
		* no real use for original text body downstream -- always converting some html anyway
		*
		*/
		$this->text_body = $this->text_body ? $this->utf8_to_sanitized_text( $this->text_body ) : $this->utf8_to_sanitized_text( self::pre_sanitize_html_to_text( $this->raw_html_body ) ) ;

		// gmail supplies own snippet
		if  ( ! $this->snippet ) {
			$word_array = preg_split ( '#\s+#', $this->text_body, 51, PREG_SPLIT_NO_EMPTY ); // not just doing substr to avoid encoding complexities on character boundaries
			array_pop ( $word_array );
			$this->snippet = implode ( ' ', $word_array ); // first 50 words -- long enough to always be truncated . . . not being smart about phrase selection
		}

	}
		
	/*
	* regular IMAP
	* regular IMAP
	* regular IMAP
	*/
	// using function originally modeled on user comment at http://php.net/manual/en/function.imap-fetchstructure.php, but substantial mods necessary to better handle multipart
	private function message_structure_walker ( $IMAP_stream, $UID, $p, $partno, $parent_is_multipart_alternative = false ) {
	
		/* 
		* Strategy notes: 
		* 	- Want to assemble as much content as possible in html body to fully present message
		*	- For text version worry only about capturing the first presented text body for parsing (and return of plain text)
		* 		-- first text part has all that matters for content/address parsing; more likely to confuse
		* 	- So, translate text to html and append except in multipart/alternative
		*	- Handle CID URL's appropriately, but do not worry about MID -- complex threads
		*/
	
		// set up type, disposition, size and id variables
		$subtype 		= $p->ifsubtype ?  strtolower( $p->subtype ) : '';
		$disposition 	= $p->ifdisposition ? strtolower( $p->disposition ) : '';
		$id 			= $p->ifid ? $p->id : '';
		$bytes 			= isset ( $p->bytes ) ? $p->bytes : 0;
		$is_multipart_alternative = ( TYPEMULTIPART == $p->type && 'alternative' == $subtype );
		$no_brackets_id	= $id ? preg_replace( '#(^<|>$)#', '', $id ) : '';
		
		// MULTIPART SUBPART RECURSION -- begin with this do nothing more if subparts are present
		if ( isset ( $p->parts ) ) {
			foreach ( $p->parts as $partno0=>$p2 ) {
				$this->message_structure_walker( $IMAP_stream, $UID, $p2, $partno . '.' . ( $partno0 + 1), $is_multipart_alternative ); // 1.2, 1.2.1, etc.
			}
			return;
		}

		/*
		*  NOT MULTIPART
		* fetch body and clean it up and put it in the right place
		*/
		$data = ( 0 < $partno ) ?
			imap_fetchbody( $IMAP_stream, $UID, $partno, FT_UID ) : // multipart initiation of function
			imap_body( $IMAP_stream, $UID, FT_UID ); // simple initiation (branch here only on initiation -- partno never 0 as descend multipart tree)

		// Any part may be encoded, even plain text messages, so check everything.
		if ( $p->encoding == ENCQUOTEDPRINTABLE ) {
			$decoded_data = quoted_printable_decode( $data );
		} elseif ( $p->encoding == ENCBASE64 ) {
			$decoded_data = base64_decode($data);
		} else {
			$decoded_data = $data;
		}
		/*
		*
		* save transfer-decoded data to attachments if not to use for inline presentation
		* only inlining text, html and base64 images that are not too large
		*
		*/
		if ( 
			// send anything that is not inline text to attachment (multipart already handled)
			( TYPETEXT != $p->type ) ||
			// if disposition is not provided, treat as inline; if disposition is provided, treat anything other than inline as attachment
			( $disposition && 'inline' != $disposition ) ||
			// send any text other than plain or html to attachment
			( !in_array ( $subtype, array('', 'plain', 'html'))) 
		) {  
			WIC_Entity_Email_Attachment::handle_incoming_attachment( $this->inbox_image_id, $p, $partno, $decoded_data, $no_brackets_id, $disposition );
			return;
		}

		/* 
		* handle retrieved body	
		* 
		*/
		// text attachments -- use $decoded_data (only handling plain or html, other is attachment)
		if ( TYPETEXT == $p->type  && $decoded_data ) {

			// attempt to extract charset parameter
			$charset = '';
			if ( isset ( $p->parameters ) ) {
	        	if ( is_array ( $p->parameters ) ) {
					foreach ( $p->parameters as $parameter ) {
						if ( 'charset' == strtolower( $parameter->attribute ) ) {
							$charset = $parameter->value;
							break;
						}
					}
				}
			}	
			self::handle_retrieved_body ( $charset, $subtype, $decoded_data, $parent_is_multipart_alternative );
		} 
	} // message_structure walker

	/*
	*
	* supports both gmail and IMAP structure walkers
	*
	*/
	protected function handle_retrieved_body ( $charset, $subtype, &$decoded_data, $parent_is_multipart_alternative ) {
			global $wic_inbox_image_collation_takes_high_plane;
			/* 
			* if a found charset other than utf-8, do conversion -- if blank, assuming UTF-8  (this happens often)
			*
			*/
			if ( $charset > '' ) {
				if ( strtolower( $charset ) != 'utf-8' )  {
					$decoded_data =  iconv ( $charset, "UTF-8//IGNORE", $decoded_data );
				}
				// want a legible but UTF-8 sanitized copy for display as message text
				// strip high plane, if necessary for nonmb4 collation
				$temp_body = $wic_inbox_image_collation_takes_high_plane ? $decoded_data : preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $decoded_data );

			} else {
				// otherwise just make sure it is ASCII ( with a UTF-8 place holder character ) if not accepting high plane
                $temp_body = $wic_inbox_image_collation_takes_high_plane ? $decoded_data : preg_replace("/[^\x01-\x7F]/","\xEF\xBF\xBD",$decoded_data ) ;			
			}
			/*
			* always strip possible toxics
			*/
			$temp_body = self::strip_html_head( $temp_body );
			// always put the first found plain text body ( and no others ) into raw_text_body for parsing
			if ( ( ''  == $subtype || 'plain' == $subtype ) && '' == $this->text_body ) {
				$this->text_body = $temp_body; 
			} 
			// now constructing html body -- add all html in order encountered
			$separator = $this->raw_html_body ? '<hr/>' : '';
			if ( 'html' == $subtype ) {
				$this->raw_html_body .= ( $separator . $temp_body );
			// add plain text if not an alternative, but format it as html
			} elseif ( !$parent_is_multipart_alternative ) {
				$temp_body = wpautop( balanceTags( $temp_body ) );
				$this->raw_html_body .= ( $separator . $temp_body );
			}	

	}





	/*
	*
	*
	* Address extraction functions
	*
	*
	*
	*/
	protected function extract_address() {
		
		// get variables
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		$disqualifiers = $form_variables_object->disqualifiers;

		/*
		*	Process text body into blocks containing closing lines, names, emails or physical address_line
		*	Block separation is by at least two vertical carriage movements interspersed with horizontal movements (possibly followed by an arbitrary amount of white space)
		*		-- \r (CR and/or CRLF) has already been replaced with LF
		*		-- \t and all horizontal white space has been replaced with space \x20.
		*		-- \f form feed has already been replaced by \n\n
		*		-- \v includes form feed (already replaced), vertical tab and  all line break characters -- any vertical whitespace character (since PHP 5.2.4) http://php.net/manual/en/regexp.reference.escape.php
		*		-- \s includes remaining spaces and vertical elements 
		*	Split body string into array of blocks using preg split 
		*		note that originally wrote this as capturing groups rather than character classes 
		*		(results in php Segmentation fault if too many white spaces).
		*	Use nonaddress blocks for md5 characterization of message
		*
		*/
   		$block_array = preg_split ( '#\h*\v\h*\v\s*#', $this->text_body, NULL, PREG_SPLIT_NO_EMPTY ); 
		$address_block_object_array = array(); 
		$non_address_block_array = array();
		// now parse and narrow down text blocks for examination
		foreach ( $block_array as $i => $block ) {
			// parse block using the block object
			$block_object = new WIC_DB_Address_Block_Object ();
			$block_object_OK = $block_object->populate( $block, $i );
			// if have email_address or physical address or closing or possible name keep it in play
			if ( $block_object_OK ) {
				// but discard any OK processed address blocks containing disqualifying phrases as to address, so can't be the from address
				if ( $disqualifiers > '' ) {
					$disqualifier_pattern = '#(?i)\b(?:' . $disqualifiers . ')\b#u';
					if ( 1 == preg_match ( $disqualifier_pattern, $block ) ) {
						continue;
					}
				}				
				$address_block_object_array[] = $block_object;
				if ( $block_object->non_address_part ) {
					$non_address_block_array[] = $block_object->non_address_part; // handle stripped address from last sentence 
				}
			// otherwise save it for md5 processing
			} else {
				$non_address_block_array[] = $block;
			}
		}
		
		/*
		*
		*  First choose email_address to consider primary.  Hierarchy is:
		*		1A) In body, != to email (on its own line in a full address block) ( meets email_source 1, 2, 3 standards )
		*		1B) In body, != to email and user chose usebody soft ( meets email_source 2 and 3 standard )
		*		2) 	Reply_to email ( meets all standards, 1-4 )
		*		3)	From email (meets all standards, 1-4 )
		*
		*	Email line can be standalone, in a block without address.
		*	Email bears no necessary relationship to name or address.
		*	So, handle this choice first, separately from address choice.
		*
		*/
		if ( 
				// auto -- take email from body, but only if at start of a line (enforced in block object) and only if not a reply or forward email
				(
					'1' == $form_variables_object->email_source && 
					're:' != strtolower( substr( $this->subject, 0, 3 ) ) &&
					'fw'  != strtolower( substr( $this->subject, 0, 2 ) ) 

				) ||
				// auto soft -- taking email from body if available 
				'2' == $form_variables_object->email_source || 
				// force body -- prefer body email, will not substitute reply_to or from below
				'3' == $form_variables_object->email_source 
				// if 4, then forcing use of reply or from
			) {
			foreach ( $address_block_object_array as $block_object ) {
				// skip blocks if email blank or equal to_email
				if ( 
					'' == $block_object->email ||
					$this->to_email ==	$block_object->email 
					) {
						continue;
				} else {
					// note that not setting $block_object->email unless it should be used (i.e., start of line and have city/zip if 1 = email_source )
					// not in this branch unless at all unless it is non-blank and not the to e-mail
					if ( '' == $this->email_address && '1' == $form_variables_object->email_source ) {
							$this->outcome += 2; // strong body email is coded as two points in the outcome scale; don't want to credit it twice
					}
					$this->email_address = $block_object->email;
					if (
						( $this->reply_to_email && $this->email_address == $this->reply_to_email ) ||
						( $this->from_email && $this->email_address == $this->from_email ) 
					) {
						$this->outcome++; // add another point for using a body email that matches from or reply email
						break; // if have found that, done, otherwise keep looping through blocks and use the last email found
					}
				}
			}
		}

		// if not found in body or force ignored (email_source 4) and not forcing body preference ( email_source 3 ) go to reply to, then from
		if ( '' == $this->email_address && 3 !=  $form_variables_object->email_source ) {
			if ( $this->reply_to_email > '' ) {
				$this->email_address = $this->reply_to_email;
			} else {
				$this->email_address = $this->from_email;			
			}
		}
		/*
		*
		*  Now choose name and address -- process array to be sequence aware
		*
		*
		*  Considering possible blocking of name address and closing in email text_body
		*
		*	------------  A, single block (common)
		*	Sincerely
		*	John Doe
		*	Address . . .
		*	------------  B, closing block with name, followed by address
		*	Sincerely
		*	John Doe
		*
		*   Address . . .
		*	------------  C, closing followed by name/address block (common)
		*	Sincerely
		*	
		*	John Doe
		*   Address . . .		
		*	------------  D, all three separate
		*	Sincerely
		*
		*	John Doe
		*
		*	Address . . .
		*	------------  E, no closing, single block
		*
		*	John Doe
		*	Address . . .
		*	------------  F, no closing, two blocks
		*	John Doe
		*
		*	Address . . .
		*	------------ 
		*
		*	========================================================
		*   first pass to learn positions and discard now irrelevant blocks
		*/
		$second_pass_block_object_array = array();
		$first_closing_block = false;
		$to_email_parts = preg_split( '#@#', $this->to_email );
		// pass 1
		foreach ( $address_block_object_array as $block_object ) {

			// set $first_closing_block -- this is really the last closing block in the first group of closing blocks -- "thanks for your consideration<br>Yours truly"
			// repetitive groups of closing blocks will occur in email strings -- top block is the best to take 
			if ( $block_object->has_closing ) {
				if ( false===$first_closing_block || $block_object->block_sequence_number == $first_closing_block + 1 ) {
					$first_closing_block = $block_object->block_sequence_number;
				}
				if ( 1 == $block_object->num_lines ) {
					continue; // discard closing only blocks
				}
			}

			// discard blocks that returned true only because had email
			// note that city always present if zip populated, but not reverse if setting allow no-zip addresses
			if ( !$block_object->city && !$block_object->last_name ) {
				continue; // never see this block again
			}
			/*
			* discard blocks that look like they are to blocks based on email
			*
			* address block includes email and it matches the to email OR
			* first and last from address block match the to email
			*
			* user should have set up disqualifiers already, but help them
			* down side is might exclude a good address block from person with 
			* same name as to person
			*/
			if ( $block_object->email == $this->to_email ||
				// email or names in address block match to to_email
				( $block_object->first_name > '' && $block_object->last_name > '' &&
					1 == preg_match ( 
						'#(?i)^'  // case insensitive 
						 . $block_object->first_name 
						 . '[._%+-]?'
						 . $block_object->last_name
						 . '$#u'
						 , $to_email_parts[0]
					) ) ) { 
				continue;  // never see this block again
			}


			// expose sequence data in next pass array
			$second_pass_block_object_array[$block_object->block_sequence_number] = $block_object;
		
		} // close pass 1
		/*
		*   Two versions of second pass -- with closing block (Cases A-D) and without (Cases E, F)
		*
		*   pass 2a, analyze blocks based on closing block, if found
		*   rely on positioning relative to closing block to choose block and exclude all others
		*   this is not fully reliable as real address block can be at top with personal close at the bottoms
		*   
		*/
		if ( false !== $first_closing_block ) {
			// pass 2a
			foreach ( $second_pass_block_object_array as $seq_no => $block_object ) {

				// Handle Case A and Begin Case B 
				if ( $first_closing_block == $seq_no  )  {
					// Begin cases A,B
					if ( $block_object->last_name ) {
						$this->use_this_block_name ($block_object ); 
						// Finish Case A (only use address if have name -- name must follow closing before address)
						if ( $block_object->city ) {
							$this->use_this_block_address ( $block_object ); 
							break;
						} 
					}
				// Finish Case B and Handle Case C and Begin Case D
				} elseif ( $first_closing_block + 1 == $seq_no ) {
					//Begin Cases C, D
					if ( $block_object->last_name && !$this->last_name ) {
						$this->use_this_block_name ($block_object ); 
					}
					// Finish B and C (if possible and have previously seen name )
					if ( $block_object->city && $this->last_name && !$this->city ) {
						$this->use_this_block_address( $block_object );
						break; 
					}
				// Finish Case D (if possible and have previously seen name )
				} elseif ( $first_closing_block + 2 == $seq_no ) {
					if ( $block_object->city && $this->last_name && !$this->city ) {
						$this->use_this_block_address( $block_object );
						break; 
					}
				}
			} // close pass 2a
		} // close closing block
		/*
		* pass 2b, analyze without closing block (only real choice variables if multiple blocks OK are name and email)
		*
		* score all blocks containing addresses based on name match -- if none particularly good just use last
		*/
		if ( false === $first_closing_block || !$this->city  ) { // don't do this branch just for last name -- should have found after closing; otherwise doubtful
			$unresolved_address_block_object_array = array(); // for residual after next two stage
			$max_seq_no = 0;
			$max_seq_no_with_street = 0;
			// pass 2b
			foreach ( $second_pass_block_object_array as $seq_no => $block_object ) {
	
				// reset score for each block
				$score = 0;

				/*
				*
				* Only want blocks with city -- at least partial address
				* Will merge names just above city blocks
				* Other name blocks not likely good
				*
				*/
				if ( ! $block_object->city ) {
					continue;
				}


				// identify last
				$max_seq_no = $seq_no;
				if ( $block_object->street_address_line ) {
					$max_seq_no_with_street = $seq_no;
				}
				/*
				*
				* Merge cases E&F
				*
				* Pull down name from prior block if available and none in current block
				*
				*/
				if ( ! $block_object->last_name && 						   			// only borrow if needed
					isset( $second_pass_block_object_array[$seq_no - 1] ) &&		// and some place to borrow from 
					!$second_pass_block_object_array[$seq_no - 1]->city &&  		// don't borrow name from a good block
					$second_pass_block_object_array[$seq_no - 1]->last_name			// don't borrow it if it isn't there 
					) 
					{ 
					$block_object->last_name = $second_pass_block_object_array[$seq_no - 1]->last_name;	
					$block_object->first_name = $second_pass_block_object_array[$seq_no - 1]->first_name;	
					$block_object->middle_name = $second_pass_block_object_array[$seq_no - 1]->middle_name;	
				}	


			
				// compute name scores 
				$name_scores = $this->score_name_match( $block_object );
				
				// score based on direct match or computed
				if 	( $block_object->email == $this->email_address ) {
					$score = 100;				
				} elseif ( $block_object->first_name && $block_object->last_name &&
						1 == preg_match ( 
							'#(?i)^'  // case insensitive 
							 . $block_object->first_name 
							 . '[._%+-]?'
							 . $block_object->last_name
							 . '#u'
							 , $this->email_address
						) 
					) { 
					$score = 90;				
				} elseif (  $name_scores['reply_to'] > 15 && '3' != $form_variables_object->email_source ) {
					$score = 80;
				} elseif ( $name_scores['from'] > 15 && '3' != $form_variables_object->email_source ) {
					$score = 70;
				} elseif ( $name_scores['to'] > 15 ) {
					$score = -100;
				} else {
					$score = $name_scores['reply_to'] + $name_scores['from'] - $name_scores['to'];
					// note that empty name gets a zero score
				}
		 
				// load scored block into new array				
				$unresolved_address_block_object_array[$seq_no] = array(
					'block' 	=> $block_object,
					'score' 	=> $score
				);
			} // pass 2b	
			/*
			* now choose block based on score (or use last if none better than 50 )
			*/
			if ( count ( $unresolved_address_block_object_array ) > 0 ) { 
				$max_score = -100;
				$max_scoring_block = -1;
				foreach ( $unresolved_address_block_object_array as $seq_no => $unresolved_address_block_object ) {
					if ( $unresolved_address_block_object['score'] > $max_score ) {
						$max_score = $unresolved_address_block_object['score'];
						$max_scoring_block = $seq_no;
					}
				}
				// use_this functions will not overlay if previously found city or last_name
				if ( $max_score > 50 ) {
					$this->use_this_block_address ( $unresolved_address_block_object_array[$max_scoring_block]['block'] );
					$this->use_this_block_name ( $unresolved_address_block_object_array[$max_scoring_block]['block'] );
				} else { 
					$best_remaining = $max_seq_no_with_street ? $max_seq_no_with_street : $max_seq_no;
					$this->use_this_block_address ( $unresolved_address_block_object_array[$best_remaining]['block'] );
					$this->use_this_block_name ( $unresolved_address_block_object_array[$best_remaining]['block'] );			
				}
			} // have some blocks to work with 
		} // no closing block

		/*
		*	Finally, attempt to complete name from emails if still missing.
		*
		*	May have taken email_address from one level (body/reply_to/from), but matched
		*		address block name at another level.  This is not necessarily wrong.  
		*		Could have name in from or reply_to with an "on behalf of", with true
		*		email in body.
		*
		*	But may not have set name yet, so look at options -- nothing left in address_blocks.
		*		Try parse reply to and from (may require adding more phrases like 'on behalf of'
		*			to pre_title setting to be successful).
		*		Insist that name be found in body text and fail ( leave name blank if not )
		*
		*	This step structured to provide information about quality of match, even if name found already
		*/
		if ( !$this->last_name ) {
			// try parse the reply or from address in consistent with selected email
			$name_array = false;
			$working_block_object = new WIC_DB_Address_Block_Object;
			if ( $this->email_address == $this->reply_to_email ) {
				$name_array =$working_block_object->parse_name( $this->reply_to_personal );
			} elseif ( $this->email_address == $this->from_email ) {
				$name_array = $working_block_object->parse_name( $this->from_personal );
			}

			// if have success on one or the other, test it vs. the body text 
			// there better be a text block that contains it; if so, use it.
			if ( false !== $name_array ) {
				$name_test_pattern = '#(?i)' 
					. '\b' . $name_array['first'] . '\b' 
					. '[\. ]{1,3}'
					. '(?:\b' . $name_array['middle'] . '[\. ]{1,3})?' 
					. '\b' . $name_array['last'] . '\b'
					. '#u';
				if ( 1 == preg_match ( $name_test_pattern, $this->text_body  ) ){
					$this->outcome +=4;
					$this->first_name = $name_array['first'];
					$this->last_name = $name_array['last'];
					$this->middle_name = $name_array['middle'];
				} // match?
			} // have name_array?
		}
		/* 
		* done using address blocks, now use the non-address blocks to create the md5 representation of the non-address text
		*/
		$this->sentence_md5_array = WIC_Entity_Email_MD5::get_md5_array_from_block_array (  $non_address_block_array );

	} // close function extract_address
	
	private function use_this_block_address ( $address_block_object ) {
		if ( ! $address_block_object->city || $this->city ) {
			return; // only load a valid parse and do not overlay found city
		}
		$this->phone_number		= $address_block_object->phone; 
		$this->apartment_only_line 		= $address_block_object->apartment_line;
		$this->street_address_only_line = $address_block_object->street_address_line;
		if ( $address_block_object->street_address_line > '' ) {
			$this->address_line 	= $address_block_object->street_address_line .
									(  
										( $address_block_object->apartment_line > '' ) ?  
										( ', ' . $address_block_object->apartment_line ) : ''
									);
		}
		$this->city				= $address_block_object->city;
		$this->state 			= $address_block_object->state;
		$this->zip 				= $address_block_object->zip;
		// do scoring
		if ( $this->city > '' ) {
			$this->outcome +=8;

		}	
	}

	
	private function use_this_block_name ( $address_block_object ) {
		if ( ! $address_block_object->last_name || $this->last_name ) {
			return; // only load a valid name and do not overlay found name
		}
		$this->first_name		= $address_block_object->first_name;	
		$this->middle_name		= $address_block_object->middle_name;
		$this->last_name		= $address_block_object->last_name;			
		if ( $this->last_name > '' ) { // if single name is treated as last; never have name before having address
			$this->outcome +=4;
		}
	}
	
	private function score_name_match ( $block_object ) {
		// array of name type_strings
		$test_name_types = array ( 'from', 'to', 'reply_to' );
		$name_score_array = array(); // will be 'type'=>score
		$block_object_name_array = array( 
			'first' 	=> $block_object->first_name, 
			'middle' 	=> $block_object->middle_name, 
			'last' 		=> $block_object->last_name 
		);

		// create new block object for access to method that needs non-static values
		$working_block_object = new WIC_DB_Address_Block_Object;
		foreach ( $test_name_types as $name_type ) {
			$name_array = $working_block_object->parse_name( $this->{ $name_type . '_personal' } );
			$name_score_array[$name_type] = $this->compare_arrays_of_names ( $name_array, $block_object_name_array ); 
		}
		return  ( $name_score_array );
	}
	
	private function compare_arrays_of_names ( $name_array_1, $name_array_2 ) {
		if ( false === $name_array_1 ) {
			return ( 0 ); // handling bad parse return 
		}
		/*
		* no negative if name component missing in one or both names
		* middle mismatch does not subtract but full disagreement on either fn or ln will
		* disqualify if set minimum match score to 16.
		*/
		$name_component_array = array('first', 'middle', 'last' );
		$name_score = 0;
		foreach ( $name_component_array as $name_component ) {
			if ( $name_array_1[$name_component] > '' && $name_array_2[$name_component] > '' ) {
				if ( $name_array_1[$name_component] == $name_array_2[$name_component] ) {
					$name_score += 10;
				} elseif ( substr( $name_array_1[$name_component], 0, 4 ) == substr ( $name_array_2[$name_component], 0, 4) ) {
					$name_score += 8;
				} elseif ( $name_component != 'middle' ) {
					$name_score -= 5;
				}
			}
		}
		return ( $name_score );
	}

	/*
	*
	* sanitization functions
	*
	*/
	// translate RFC2822 header sent time to blog local MYSQL format date time
	protected static function sanitize_date_time ( $possible_date_time ) { 
		// first discard possible string literal describing time zone in otherwise RFC2822 form -- Wed, 28 Jun 2017 08:43:21 +0000 (UTC)  . . . drop the `(UTC)`
		$possible_date_time = implode( ' ', array_slice( explode ( ' ', $possible_date_time ), 0 , 6 ) );
		// second -- get time_zone string for blog if available in either format, otherwise default to GMT
		if ( ! $time_zone_string = get_option ( 'timezone_string' ) ) {
			$time_zone_offset = get_option ( 'gmt_offset' );
			if ( is_numeric( $time_zone_offset ) ) {
				$time_zone_string = sprintf( "%+'.05d\n", 100 *  intval( $time_zone_offset ) + 60 * ( $time_zone_offset - intval( $time_zone_offset ) ) ); 
			} else {
				$time_zone_string = "+0000";
			}
		}
		// third attempt php timezone object from string
		try {
			$tz =  new DateTimeZone( $time_zone_string );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone ( "+0000" ); // had option setting and used it, but it was bad, default again to GMT
		}
		// now try to parse as RFC2822 which includes the sender's time zone -- first normalize to GMT, then adjust to blog local
		if ( $date = DateTime::createFromFormat ( DateTime::RFC2822, $possible_date_time ) ) { 
			if( $date->setTimezone( $tz ) ) {
				return $date->format('Y-m-d H:i:s');
			} 
		}
		// if failed as RFC2822, try without assuming proper RFC2822 formatting to catch bad most bad dates	
		try {
			$date = new DateTime( $possible_date_time );
			$date->setTimezone( $tz );
	 		return $date->format('Y-m-d H:i:s');
		}	catch ( Exception $e ) {
			return ( '' );
		}	   			
	}


	public static function sanitize_incoming ( $raw_incoming ) {
		global $wic_inbox_image_collation_takes_high_plane;
		/*
		* WIC_Entity_Email_Subject::sanitize_incoming
		*
		* (1) Remove transfer encoding and convert to UTF8
		* (2) Strip any 4 byte (high plane) UTF-8 characters like emoticons 
		*		-- these will not store properly in MySQL ( unless upgraded https://mathiasbynens.be/notes/mysql-utf8mb4 )
		*		-- replace with characters to avoid creating unsafe strings
		*			http://stackoverflow.com/questions/8491431/how-to-replace-remove-4-byte-characters-from-a-utf-8-string-in-php
		*			http://unicode.org/reports/tr36/#Deletion_of_Noncharacters
		* (3) Do a sanitize text field to strip any tags
		*
		* NOTE: function imap_utf8 does NOT work as advertized -- iconv_mime_decode is much more reliable		
		*/
		$first_pass = iconv_mime_decode ( 
				$raw_incoming, 
				ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 
				'UTF-8' 
			);
		if ( $wic_inbox_image_collation_takes_high_plane ) {
			return sanitize_text_field ( $first_pass );
		} else {
			return sanitize_text_field( preg_replace( '/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $first_pass ) );
		}
	}

	
	private function email_array_to_display_safe_address ( $mail_object_array ) {
		$mailbox = isset( $mail_object_array[0]->mailbox ) 	? $mail_object_array[0]->mailbox : '';
		$host 	 = isset( $mail_object_array[0]->host ) 	? $mail_object_array[0]->host  	 : '';
		$test_email = sanitize_text_field( $mailbox . '@' . $host ); // sanitize to avoid any unsafe that might pass filter https://tools.ietf.org/html/rfc2822#section-3.4
		return filter_var( $test_email, FILTER_VALIDATE_EMAIL ) ? $test_email : '';
	}

	protected function domain_only ( $email_address ) {
		$from_array = preg_split( '#@#', $email_address );
		return isset ( $from_array[1] ) ? $from_array[1] : '';
	}
	
	private function email_array_to_display_safe_personal ( $mail_object_array ) {
		return isset ( $mail_object_array[0]->personal ) ? self::sanitize_incoming( $mail_object_array[0]->personal ) : '';
	}


	// hard strip, but preserves paragraph breaks as double LF
	private function utf8_to_sanitized_text ( $string ) {
		// html break filters to be applied in succession by preg_replace 
		$break_patterns = array (
			 // (1) visual paragraph boundaries -- div, p, h tags (open or close ) and any surrounding whitepace (not read in html)
			'#(?i)\s*<\s*/?\s*(?:div|p|blockquote|address|center|table|h\d)\b[^>]*>\s*#',
			//  (2) visual line break patterns -- br tag and any surrounding whitespace
			'#(?i)<\s*/?\s*br\s*/?\s*>\s*#', 
			//  (3) treat a form feed as a paragraph boundary
			'#\f#',
			//  (4) treat a pipe separator as a line break
			'#\|#', 
		);
		$break_patterns_replacements =array( 
			"<br/><br/>", // these will be replaced in the second step of the array processing
			"\n", // repeated line breaks will be treated as a paragraph break by address parser
			"\n\n",
			"\n" 
		);
		// character filters to be applied in succession by preg_replace 
		$filters = array ( 
			// (1) horizontal spaces filter -- standardize to single blank --  https://www.compart.com/en/unicode/category/Zs  http://php.net/manual/en/regexp.reference.unicode.php
			'#[\t\p{Zs}]+#u',  
			// (2) character filter -- pass only unicode letters, digits, email characters, plus slash and white spaces, NO COMMAS OR PARENS OR TAG CHARACTERS  -- http://php.net/manual/en/regexp.reference.character-classes.php
			// white space included is vertical ( LF,FF ), but not the horizontal spaces other than the plain space; CR passed for replacement at next step
			'#[^\pL0-9 .@_%+/\-\r\v]#u', 
			// (3) get rid of \r carriage returns http://php.net/manual/en/regexp.reference.escape.php
			'#\r(\n)?#', 
			// (4) simplify debugging by eliminating excess LF's
			'#\n{2,}#',
		);
		$filters_replacements = array ( 
			 ' ', // single blank
			 '',  // empty string
			 "\n", // single line feed
			 "\n\n", // double line feed	
		);
		$clean_string = preg_replace ( $filters, $filters_replacements,		 // standardize characters
			html_entity_decode (											 // decode entities
				strip_tags( 												 // get rid of all other tags
					preg_replace( $break_patterns, $break_patterns_replacements, // put in double LF for block tags
						$string 
					)
				),
				0, // no flags
				'UTF-8'
			)
		);
		return ( $clean_string );
	}

	/* 
 	* strip head, script and styles for security; (would like to also run wp_kses_post, but it strips cid and potentially other valid email elements)
	*
	* the old approach in this function was regex based:
	*	$patterns_to_strip = array( 
	*		'#(?i)<(head|style|script|embed|applet|noscript|noframes|noembed)\b[^>]*>(?:[\s\S]*?)</\1\s*>#', // strip head, style and script tag (AND, unlike KSES, the content between them)
	*		'#(?i)<!--(?:[\s\S]*?)-->#', // strip comments, including some mso conditional comments
	*		'#(?i)<[^>]*doctype[^>]*>#', // strip docttype tags
	*		'#(?i)<!\[if([^\[])*\]-?-?>#', // hacky specific solution to common conditional comment formats
	*			'#(?i)<!\[endif\]-?-?>#', // 
	*
	*	);	
	* 	return 	balanceTags ( preg_replace (  $patterns_to_strip, '', $html ), true );
	*
	* the old approach above choked on long head elements, returning empty string ("catastrophic backtracking" when tested in regex101 ).
	*    -- this result predicted here: https://stackoverflow.com/questions/7130867/remove-script-tag-from-html-content
	*	 -- see also https://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
	*	 -- similar post: https://techsparx.com/software-development/wordpress/dom-document-pitfalls.html
	* the approach below derives from the first mentioned comment;
	*   if this approach shows weaknesses, consder htmlpurifier: http://htmlpurifier.org/
	*   in taking the html into a dom and spitting it back out, the function also removes all comments including outlook conditional comments
	*/
	public static function strip_html_head( $html ) {
		/*
		* see comments to http://php.net/manual/en/domdocument.construct.php -- optional declaration of character code in the constructor does not control loadHTML; unsure on version declaration, so go with defaults
		*/
		$dom = new DOMDocument(); // http://php.net/manual/en/class.domdocument.php
		/*
		*
		* https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
		*
		* loadHTML interprets incoming as ISO-8859 and garbles UTF-8 Characters; use html-entities instead
		*
		* alternative is to add a metatag in front, but this may not work if there is a conflicting meta tag 
		*
		* note -- upstream from this function, $html was converted UTF-8 if charset detected; if none defined, effectively assuming UTF-8 here
		*   -- will flatten unidentified charset to ascii if not using mb4 encoding, since will high plane will cause errors;
		*   -- if not flattened, might not be UTF-8/ascii so some risk remains . . . 
		*   -- most likely no charset case is plain text which in my universe of correspondents is likely ascii or utf-8 so risk is low
		*/
		$converted_html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
		if ( ! @$dom->loadHTML( $converted_html  ) ) { // suppress complaints about the validity of the html, but act on full error
			return '<h3>Malformed HTML message. Could not safely present.</h3>';
		} 

		$forbidden_tags = array (
			'head', 'style', 'script', 'embed', 'applet', 'noscript', 'noframes', 'noembed'
		);
		
		foreach ( $forbidden_tags  as $forbidden_tag ) {
			$forbidden = $dom->getElementsByTagName( $forbidden_tag );
			$remove = [];
			foreach($forbidden as $item) {
				$remove[] = $item;
			}
			foreach ($remove as $item){
				$item->parentNode->removeChild( $item ); 
			}
		}

		// while we are here, make sure that links open in a new window
		$links = $dom->getElementsByTagName( 'a' );
		foreach ( $links as $link ) {
			$link->setAttribute( 'target' , '_blank'  );
			$link->setAttribute( 'rel' , 'noopener noreferrer'  ); // contra tabnabbing https://www.jitbit.com/alexblog/256-targetblank---the-most-underestimated-vulnerability-ever/
		}

		$body_elements = $dom->getElementsByTagName( 'body' );
		// taking the first, hopefully only
		foreach ( $body_elements as $body ) {
			// have UTF-8 characters, but re-encode them for safe presentation in other charsets
			// does not encode html control tags <>'"
			$clean_html =  mb_convert_encoding( $dom->saveHTML( $body ), 'HTML-ENTITIES', 'UTF-8');
			// convert any php tag opening/closing to entities -- avoid saving executable php
			$clean_html = str_replace (  array ('<?', '?>'), array ('&lt;?', '?&gt;'), $clean_html );

			return $clean_html;		
		}
		
	}

	public static function pre_sanitize_html_to_text ( $html ) {
		// white space in html has no effect beyond first space, so strip it to a space
		return preg_replace ( '#\s+#', ' ', $html );
	}

	
	// make an array of address that is safe and always an array from unreliable incoming address information
	private function repack_address_array ( $address_array ) { 
		$clean_array = array();
		if ( !$address_array || !is_array( $address_array ) ) {
			return $clean_array;
		} else  {
			foreach ( $address_array as $address ) {
				$test_email = isset ( $address->mailbox ) && isset( $address->host ) ? self::sanitize_incoming ( $address->mailbox . '@' . $address->host ) : '';
				$clean_array[] = array( 
					isset ( $address->personal ) ? self::sanitize_incoming( $address->personal ) : '', 
					filter_var( $test_email, FILTER_VALIDATE_EMAIL ) ? $test_email : '',
					self::quick_check_email_address ( $test_email )
				);
			}
		}
		return ( $clean_array );		
	}
	
	public static function quick_check_email_address ( $email_address ) {
		if ( !filter_var( $email_address, FILTER_VALIDATE_EMAIL )  ) {
			return -1;
		}
		global $wpdb;
		$email_table = $wpdb->prefix . 'wic_email';
		$sql = $wpdb->prepare ( 
			"SELECT constituent_id FROM $email_table WHERE email_address = %s LIMIT 0, 1",
			$email_address
		);
		$result = $wpdb->get_results ( $sql );
		if ( $result ) {
			return $result[0]->constituent_id;
		} else {
			return 0;
		}
	}
	
	private  function convert_internet_headers_to_key_value_array( $raw_header ) {
	
		$unfolded_header = preg_replace( '#\r\n( |\t)+#', ' ', $raw_header);
		$header_array = preg_split ( '#\r\n#', $unfolded_header );
		$header_key_value = array();
		foreach ( $header_array as $header ) {
			$key_value = preg_split ('#:( |\t)*#', $header, 2);
			if ( isset ( $key_value[0] ) && isset ( $key_value[1] ) ) {
				$header_key_value[$key_value[0]] = trim($key_value[1]);
			}
		}
		return $header_key_value;
	 }
	
} // class 