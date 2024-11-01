/*
*
* email-settings.js
*
*
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	// enclosed variable storing all the set values of the processing settings form 
	var processingOptionsObject = {};

	// form initialization -- 
	wpIssuesCRM.loadSettingsForm = function () {
		// load settings form fresh 
		var $loader = $( '#settings-ajax-loader' );
		$loader.show();
		wpIssuesCRM.ajaxPost( 'email_process', 'setup_settings_form',  0, '',  function( response ) {

			$( "#wic-load-settings-inner-wrapper").html( response );
			$loader.hide();
			
			$( "#mapped_threshold" ).spinner( {
					min: 0,
					max: 100
			})
			.on( "keydown", function( event ) {
				 event.preventDefault()
			});

			$( "#word_minimum_threshold" ).spinner( {
					min: 0,
					step: 5,
					max: 200
			})
			.on( "keydown", function( event ) {
				 event.preventDefault()
			});

			// change tracker for form fields
            $(" #wic-form-email-settings ").on( "keydown change spinchange", ":input", function ( event ) {
 				if ( 'signature' != $( this).attr("id")  ) { 
 					$(".wic_save_email_settings").text( "Save unsaved options").addClass( "wic-button-unsaved");
 				}            
 			})
			
			$(".wic_save_email_settings").on( "click", function () {
				saveProcessingOptions();
			})
			
			$( "#wic-set-activesync-password-button").on( "click",  function ( ) { 
				wpIssuesCRM.doParmsPopup(  'activesync' );
			});
			
			$( "#wic-activesync-test-button").on ( "click", function ( event ) {
				$( event.target ).text( 'Testing . . .' );
				wpIssuesCRM.ajaxPost( 'email_activesync', 'activesync_status',  '', '',  function( response ) {
					wpIssuesCRM.alert ( response );
					$( event.target ).text( 'Test Settings' );
				});			
			});

			// save button for signature
			$( "#wic_save_current_user_sig" ).on( "click",  function ( event ) {
				$( event.target ).text( "Saving . . .").attr( "disabled", true);
				wpIssuesCRM.ajaxPost( 'user', 'set_wic_user_preference',  'signature', $( "#signature" ).val(),  function( response ) {
					$( event.target ).text( "Saved signature").attr( "disabled", false).removeClass( "wic-button-unsaved");
				});
			});

			$("#wic-form-tabbed").tabs({
				heightStyle: "content",
			});	
			// remove any extant tinymce instances -- even when form area is reloaded, a conflicting instance object persists -- note: this prior code did not accomplish same: tinymce.EditorManager.editors = []
			tinymce.remove();
			// new tinymce instance for the signature field 
			wpIssuesCRM.tinyMCEInit ( "signature", false, false, false, function() {
				$( "#wic_save_current_user_sig" ).text( "Save unsaved signature").addClass( "wic-button-unsaved"); // change function
			});

			// tinymce instance for the autoreply field	
			wpIssuesCRM.tinyMCEInit ( "non_constituent_response_message", false, false, false, function() { // change function
				$("#non_constituent_response_message").trigger("change");
			});	
		
 		});		

		
	}


	// for saving processing options on change (excluding signature which is user specific and updated on startup
	function saveProcessingOptions (  ) {

		// logic to protect against unintended auto replies
		var responder = $( "#use_non_constituent_responder" );
		var rules = $( "#use_is_my_constituent_rules" );

		// check that at least a two character geography is supplied (one state) and if not, disable my constituent logic
		if ( rules.val() == 'Y' ) {
			if ( $ ( "#imc_qualifiers").val().length < 2 ) {
				wpIssuesCRM.alert ( '<p>"Is My Constituent" logic cannot be enabled without criteria.</p>') ;	
				return;				
			}			
		}
		// if do not have a reasonable subject line and reply or if no constituent logic, disable reply
		if ( responder.val() > 1 ) {
			if ( 
				$ ( "#non_constituent_response_subject_line" ).val().length < 5 ||
				$ ( 
					 $.parseHTML(
						$( "#non_constituent_response_message" ).val()
					 ) 
				   )
					.text()
					.length < 20
			   ) {
				wpIssuesCRM.alert ( '<p>Reply cannot be enabled without at least 5 characters of subject line and 20 characters of message content.</p>')
				return; 
			}
			if ( rules.val() == 'N') {
				wpIssuesCRM.alert ( '<p>Reply cannot be enabled while "Is My Constituent" logic is not enabled.</p>') 
				return;
			}
		}
	
		// force disqualifiers setting
		if( '' == $( "#wic-form-email-settings #disqualifiers" ).val() ) { 
			wpIssuesCRM.alert(
				'<p>Disqualifiers Email Processing Setting blank</p>'  +
				'<p>Please set disqualifiers.</p> '
			);
			return;
		 }

		// validate email
		$( "#wic-form-email-settings #activesync_email_address" ).val( $( "#wic-form-email-settings #activesync_email_address" ).val().trim() );
		if( 
			'' < $( "#wic-form-email-settings #activesync_email_address" ).val() &&
			! wpIssuesCRM.validateEmail ( $( "#wic-form-email-settings #activesync_email_address" ).val(), false )
			) { 
			wpIssuesCRM.alert(
				'<p>ActiveSync Email Address appears to be invalid.</p>'  +
				'<p>Cannot save invalid email address -- remove or correct.</p> '
			);
			return;
		 }
		 
		// loop through inputs, pack them into object
		$ ( "#wic-form-email-settings :input:not(:button)" ).not( "#wp_issues_crm_post_form_nonce_field, .wic-selectmenu-input-display, .signature" ).each( function () {
			// sanitize pipe separated		
			sanitizePipeSeparated ( this );

			inputElement = $ ( this );
			if ( 'checkbox' == inputElement.attr('type')  ) {
				processingOptionsObject[inputElement.attr("id")] = inputElement.prop("checked" ) // attr is initial value only
			} else {
				processingOptionsObject[inputElement.attr("id")] = inputElement.val();
			}
		});

		// do the save
		$(".wic_save_email_settings").text( "Saving . . .").attr( "disabled", true);
		wpIssuesCRM.ajaxPost( 'email_process', 'save_processing_options',  0, processingOptionsObject,  function( response ) {
			$(".wic_save_email_settings").text( "Saved").attr( "disabled", false).removeClass( "wic-button-unsaved");
		});	
	}

	/*
	* Bullet proof pipe separated fields.
	*
	*/
	function sanitizePipeSeparated ( element ) {
		var pipeSeparated = [ 'streets', 'states', 'apartments', 'special_streets', 'post_titles', 'pre_titles', 'disqualifiers', 'imc_qualifiers', 'closings', 'non_names', 'team_list' ];
		elementObject = $ ( element )
		if ( pipeSeparated.indexOf ( elementObject.attr( "id") ) > -1 ) {
			var uncleanString = elementObject.val();
			var cleanString = 'team_list' == elementObject.attr( "id") ? 
				 uncleanString.replace (new RegExp ( /[^|%@.+_A-Za-z0-9-]/, 'g' ), '' ):
				 uncleanString.replace (new RegExp ( /[^|A-Za-z0-9 -]/, 'g' ), '' );
			var termsArray = cleanString.split ( '|' );
			var trimmedArray = []; 
			var trimmedTerm = '';
			for ( i = 0; i < termsArray.length; i++ ) {
				trimmedTerm = termsArray[i].trim()
				if ( trimmedTerm > '' ) {
					trimmedArray.push ( trimmedTerm );
				}
			}
			cleanString = trimmedArray.join( '|' );
			elementObject.val( cleanString );
		}
	}




}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
