/*
*
* wic-manage-storage.js
*
*/

jQuery( document ).ready( function($) { 

	wpIssuesCRM.initializeStorage()

	// set up listener to trigger form reinitialization -- using ajax form submission
	$( "#wp-issues-crm") .on ( "initializeWICForm", function () { 
		wpIssuesCRM.initializeStorage();
	});


});


// self-executing anonymous namespace
(function( wpIssuesCRM, $, undefined ) {
	
	var constituentSubFields;

	wpIssuesCRM.initializeStorage = function() { 

		// all the check boxes should change messages
		$( ":input" ).not( ":button, :hidden, #keep_all" ).change ( function() {
			decideWhatToShow();
		});
		
		// keep all button for constituents will override staging and search setting
		$( "#keep_all" ).change ( function () {
			$ ( "#keep_search, #keep_staging" ).prop( "checked" , $ ( "#keep_all" ).prop( "checked" ) );
			$ ( "#keep_search, #keep_staging" ).prop( "disabled" , ! $ ( "#keep_all" ).prop( "checked" ) );
			decideWhatToShow();
		});
		
		// continue to show the section buttons, but knockout their onclick show/hide toggle function
		$( ".field-group-show-hide-button" ).prop( "disabled", true );
		
		constituentSubFields = $ ( "#keep_activity, #keep_phone, #keep_email, #keep_address" );
		constituentSubFields.prop( "disabled", true );		
		
		// button will bubble to ajax handler, but this will execute first
		$( "#manage_storage_button" ).click( function( event ){ 
			// do not bubble to main form submit handler in ajax.js
			event.stopPropagation();	
			// if nothing unchecked, alert and go no further
			if ( "none" != $( ".wp-issues-crm-stats" ).css( "display" ) ) { // prevent dup submissions before return
				if ( $ ( "#keep_all" ).prop( "checked" ) && 
					$ ( "#keep_staging" ).prop( "checked" ) &&
					$ ( "#keep_search" ).prop( "checked" ) ) {
					wpIssuesCRM.alert ( 'Nothing selected to purge.' );
				} else { 
					// if hasn't confirmed constituent purge, go no further
					if ( ! $ ( "#keep_all" ).prop ( "checked" ) && 'PURGE CONSTITUENT DATA' != $( "#confirm" ).val().trim() ) {
						wpIssuesCRM.alert ( 'To delete constituent data, you must type out "PURGE CONSTITUENT DATA" in the confirmation field. Use all caps.' )
					} else { 
						// require confirmation to go further
						wpIssuesCRM.confirm ( 
							function () {
								$( "#manage_storage_button" ).text( "Purging . . ." );
								wpIssuesCRM.mainFormButtonPost( $( "#manage_storage_button" )[0], event )							
							},
							false,
							"<p>Click OK to purge data.  This action cannot be undone.</p><p>" + $( "#post-form-message-box" ).text() + "</p>"  
						)
					}
				}
			}
		}); 

		$( "#wic-delete-deleted-button" ).click ( function ( event ) {
			wpIssuesCRM.confirm(
				function () {
					wpIssuesCRM.ajaxPost( 'manage_storage', 'delete_deleted', 0, '',  function( response ) {
						$( "#post-form-message-box" ).text ( response.reason );
						if ( ! response.deleted ) {
							$( "#post-form-message-box" ).addClass ( 'wic-form-errors-found' )
						}
					});
				},
				false,
				'<h4>' + 'Permanently delete records previously marked as "Deleted"?' + '</h4>' +
				'<p>'  + 'You will also delete all the activities for those records.' + '</p>' +
				'<p>'  + 'You should do this when you convert to WP Issues CRM 3.0 or higher from a pre-3.0 version, otherwise "Deleted" items will reappear on lists.' + '</p>' +
				'<p><em>' + 'This cannot be undone.' + '</em></p>'
			)
		});
	}

	
	function decideWhatToShow() { 
	
		var uploadMessage, searchMessage, allMessage, subFieldsMessage, fullMessage;

		if ( $ ( "#keep_all" ).prop( "checked" ) ) {
			// always show subfields as kept if keep all checked
			constituentSubFields.prop( "disabled", true );	
			constituentSubFields.prop( "checked", true );
			constituentMessage = 'No constituents will be purged.';	
			$ ( "#post-form-message-box" ).removeClass( 'wic-form-errors-found' )
		} else {
			// allow setting of constituent purge criteria and format message
			constituentSubFields.prop( "disabled", false );
			$ ( "#post-form-message-box" ).addClass( 'wic-form-errors-found' )
			subFieldsMessage = '';
			if ( $ ( "#keep_activity" ).prop( "checked" ) ) {
				subFieldsMessage	= " activity history"		
			}	
			if ( $ ( "#keep_email" ).prop( "checked" ) ) {
				if ( '' != subFieldsMessage ) {
					subFieldsMessage = subFieldsMessage + ' OR ';				
				} 
				subFieldsMessage	= subFieldsMessage  + " an email address";		
			}		
			if ( $ ( "#keep_phone" ).prop( "checked" ) ) {
				if ( '' != subFieldsMessage ) {
					subFieldsMessage = subFieldsMessage + ' OR ';				
				} 
				subFieldsMessage	= subFieldsMessage  + " a phone number";		
			}		
			if ( $ ( "#keep_address" ).prop( "checked" ) ) {
				if ( '' != subFieldsMessage ) {
					subFieldsMessage = subFieldsMessage + ' OR ';				
				} 
				subFieldsMessage	= subFieldsMessage  + "  some physical address information";		
			}
			
			if ( '' != subFieldsMessage ) {
				constituentMessage= 'Purge will keep constituents that have ' + subFieldsMessage + ', but will purge ALL other constituents.';
			} else {
				constituentMessage = 'All constituents are selected and will be purged.';			
			} 
					
		}

		uploadMessage = $ ( "#keep_staging" ).prop( "checked" ) ? " keep upload history " : " purge upload history "
		searchMessage = $ ( "#keep_search" ).prop( "checked" ) ? " and keep logs. " : " and purge logs. "
		fullMessage = constituentMessage + ' Purge will ' + uploadMessage + searchMessage ;
		
		$ ( "#post-form-message-box" ).text( fullMessage );
	}		

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
