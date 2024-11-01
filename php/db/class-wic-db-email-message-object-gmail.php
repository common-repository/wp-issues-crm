<?php
/*
* 	class-wic-db-email-message-object-gmail.php
*
*	takes an email (identified by imap_stream and UID) and extracts and sanitizes results
*	
*	see classification of outcome codes	
*
*   this object is saved as serialized by the parse process and is retrieved by the inbox detail view and by the email processor
*
*/

class WIC_DB_Email_Message_Object_Gmail extends WIC_DB_Email_Message_Object {
	
	
	public function build_from_gmail_payload ( $id, $extended_message_id, $payload, $max_packet_size ) {
		
		$this->inbox_image_id = $id; 	// just for reference -- invariant
		$this->uid_still_valid = true; 	// no need to UID still valid checking as in IMAP since already cleaned up messages
		$this->max_packet_size = $max_packet_size < 16000000 ? $max_packet_size : 16000000; // waste no room for overhead -- better to downsize in parse_messages if necessary 

		// extract standard headers from payload
		$array_headers = array ( 'to', 'from', 'reply_to',  'cc' );
		$string_headers = array ( 'subject', 'date' );
		$standard_headers_payload = (object) array();
		foreach ( $payload->headers as $header ) {
			$lc_header_name = strtolower( $header->name );
			 // translate inconsistent usage -_ API vs IMAP
			$lc_header_name = ( 'reply-to' == $lc_header_name || 'reply to' == $lc_header_name) ? 'reply_to' : $lc_header_name ;
			if ( in_array( $lc_header_name, $string_headers ) ) {
				$standard_headers_payload->$lc_header_name = $header->value;	
			} elseif ( in_array( $lc_header_name, $array_headers ) ) {
				$standard_headers_payload->$lc_header_name =  imap_rfc822_parse_adrlist( $header->value, 'localhost'  ); // second parameter is default domain, but this is n/a	
			}
		}
		$this->populate_header_info( $standard_headers_payload );
		// sanitize snippet preloaded
		$this->snippet = self::sanitize_incoming ( $this->snippet );

		// populate body info 
		$this->gmail_payload_walker ( $payload, $extended_message_id, false );
		$this->populate_body_info();
		
		// do address extraction and build non_address_block md5 map
		$this->extract_address();	
		unset ( $this->text_body ); // no downstream use.
	}


	private function gmail_payload_walker ( $payload_part, $extended_message_id, $parent_is_multipart_alternative ) {
	
		/* 
		* Strategy notes: Mimicking IMAP walker
		* Top Object same has all other parts -- just has additional headers		*
		*
		*/
		// populate mime and determine if multipart alternative
		$ts = $payload_part->getMimeType();
		$ts_array = explode ( '/', $ts );
		$type = trim($ts_array[0]);
		$subtype = trim ($ts_array[1]);
		$is_multipart_alternative = ( $type == 'multipart' && $subtype == 'alternative' );
		/*
		* 
		* MULTIPART SUBPART RECURSION -- begin with this, do nothing more if subparts are present
		*
		*/
		$parts = $payload_part->getParts();
		if ( $parts ) {
			foreach ( $parts as $part ) {
				$this->gmail_payload_walker( $part, $extended_message_id, $is_multipart_alternative );
			}
			return;
		}
		
		/*
		*  NOT MULTIPART
		*  fetch body and clean it up and put it in the right place
		*
		*/	
		$filename = $payload_part->getFilename();
		
		$body = $payload_part->getBody();
		$bytes = $body->getSize();
		$gmail_attachment_id = $body->getAttachmentId( );	
		$data = $body->getData();

		// extract remaining necessary values from part headers 
		$encoding = '';
		$disposition = '';
		$charset = ''; // likely always utf-8 regardless of what header says coming from google
		$id = '';
		$no_brackets_id = '';
		foreach ( $payload_part->getHeaders() as $header ) {
			// find each needed element
			switch ( $header->name ) {
				case 'Content-Disposition':
					$disposition = 'inline' == substr( trim( $header->value, ', :;\"\'' ), 0, 6 ) ? 'inline' : 'attachment';
					break;
				/*
				* parsing and converting charset may be unnecessary in what is actually coming from gmail -- 
				* may always be utf-8, just as encoding is always base 64, but do it anyway -- no documentation 
				*/
				case 'Content-Type':
					$matches = array();
					if ( preg_match ( '#charset= {0,3}("|\')?([\w-]{3,20})\1#', $header->value, $matches ) ) {
						$charset = strtolower( $matches[2] );
					}
					break;
				case 'Content-ID':
					$id = $header->value;
					$no_brackets_id	= $id ? preg_replace( '#(^<|>$)#', '', $id ) : '';
					break;
			}
		}

		/*
		* in gmail api content transfer encoding is irrelevant -- all values are base 64 safe url encoded
		*  -- whether 'Content-Transfer-Encoding' is 7bit or blank or quoted-printable, the string is always and only encoded with base 64 safe url
		*  -- one might expect that google would be wrapping the base64 around the qp, but doing qp after unwrapping (decoding base 64) just replaces =xx as if hex code
		*     -- the text is already decoded after unwrap; 
		*/
		$decoded_data = self::url_safe_base64_decode( $data );
		/*
		*
		* save transfer-decoded data to attachments if not to use for inline presentation
		* only inlining text and html
		*
		*/
		if ( 
			// send anything that is not inline text to attachment (multipart already handled)
			( 'text' != $type ) ||
			// if disposition is not provided, treat as inline; if disposition is provided, treat anything other than inline as attachment
			( $disposition && 'inline' != $disposition ) ||
			// gmail designates some as attachments that must be fetched
			$gmail_attachment_id ||
			// send any text other than plain or html to attachment
			( !in_array ( $subtype, array( '', 'plain', 'html'))) 
		) {
			WIC_Entity_Email_Attachment::handle_gmail_attachment(  $bytes, $no_brackets_id, $decoded_data, $disposition, $filename, $gmail_attachment_id, $extended_message_id,  $this->inbox_image_id, $subtype, $type  );
			return;
		}
		/* 
		* handle retrieved body	
		*/
		// text attachments -- use $decoded_data (only handling plain or html, other is attachment)
		if ( 'text' == $type  && $decoded_data ) {
			self::handle_retrieved_body ( $charset, $subtype, $decoded_data, $parent_is_multipart_alternative );
		}  
	} // gmail message_structure walker

	/*
	*
	* decoding used for all gmail body parts and attachments -- other forms of decoding are not actually necessary, even when specified in content headers
	*
	*/
	public static function url_safe_base64_decode ( &$data ) {
		return base64_decode( str_replace( array('-','_'), array('+','/'), $data ) );
	}


	// for sending base64 as part of url -- assumes $data is a string
	public static function url_safe_base64_encode ( $data ) {
		return str_replace( array('+','/'), array('-','_'), base64_encode ( $data ) );
	}
		

	
} // class 