/*
*
* password.js
*
*/
jQuery(document).ready(function($) { 

	// replace password inputs with save buttons 
	$( "#smtp_password" ).replaceWith ( '<input type="button" name="wic-set-outgoing-password-button" id="wic-set-outgoing-password-button" class="button button-primary" value="Change">')
	$( "#password_for_email_imap_interface" ).replaceWith ( '<input type="button" name="wic-set-incoming-password-button" id="wic-set-incoming-password-button" class="button button-primary" value="Change">')

	// set listeners for the new buttons
	$( "#wic-settings-tabs" ).on( "click", "#wic-set-outgoing-password-button", function ( ) { 
		wpIssuesCRM.doParmsPopup(  'out' );
	});

	$( "#wic-settings-tabs" ).on( "click", "#wic-set-incoming-password-button", function ( ) { 
		wpIssuesCRM.doParmsPopup(  'in' );
	});

	
});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {
	/*
	*
	* password popup
	*
	* note: parmsObject structured to support capture of multiple passwords in same form, although function not fully configured to support that
	*  -- possible TODO: abandon this possibility and simplify logic
	*
	*/
	wpIssuesCRM.doParmsPopup = function ( whichParm ) {

		// assemble html based on whichParm
		var popupHTML = 
				'<div id="wic_save_new_parms_dialog" title="Enter email password" class="ui-front">' + '<p></p>' +
					( 'activesync' == whichParm ? '<p>ActiveSync Password:  <input type="password" value = "" id="wic_new_a_parm" name="wic_new_a_parm" /></p>' : '' ) +
					( 'in' == whichParm ? '<p>Incoming:  <input type="password" value = "" id="wic_new_i_parm" name="wic_new_i_parm" /></p>' : '' ) +
					( 'out' == whichParm ? '<p>Outgoing:  <input type="password" value =  "" id="wic_new_o_parm" name="wic_new_o_parm" /></p>' : ''  )+
					'<p>A blank entry will be disregarded and will not overwrite a previously saved password.</p>' +  
					( 'activesync' == whichParm ? '<p>Enter the password that you use to access the email you have entered.' : '' ) +
				'</div>' +
			'</div>';	
	
		var passwordPopupObject  = $ ( $.parseHTML ( popupHTML) );
	
		passwordPopupObject.dialog({
			appendTo:  ( 'activesync' == whichParm ? "#wp-issues-crm" :  "#wic-settings-tabs" ),
			closeOnEscape: true,
			close: function ( event, ui ) {
				passwordPopupObject.dialog( "destroy" );
				},
			width:  480,
			height: 480,
			show: { effect: "fadeIn", duration: 300 },
			buttons: [
				{
					width: 300,
					id: 'savePasswordsButton',
					text: 'Encrypt and Save Password',
					click: function() {
						var parmsObject = {
							a		: 'activesync' == whichParm ? $( "#wic_save_new_parms_dialog #wic_new_a_parm" ).val() : '',
							i		: 'in' == whichParm ? $( "#wic_save_new_parms_dialog #wic_new_i_parm" ).val() : '',
							o		: 'out' == whichParm ? $( "#wic_save_new_parms_dialog #wic_new_o_parm" ).val() : '',
						};
						passwordCharFilter = new RegExp (/[^\^~$,;@$!%*#?&\ \+[\]|A-Za-z0-9_-]/, 'g' )
						cleanedI = parmsObject.i.replace ( passwordCharFilter, ' ' );
						cleanedO = parmsObject.o.replace ( passwordCharFilter, ' ' );
						cleanedA = parmsObject.a.replace ( passwordCharFilter, ' ' );
						if ( ! parmsObject.o && ! parmsObject.i && ! parmsObject.a ) {
							wpIssuesCRM.alert ( 'No password supplied.')
						} else if ( parmsObject.o != cleanedO || parmsObject.i != cleanedI || parmsObject.a != cleanedA ) {
							wpIssuesCRM.alert ( 'Password may not include characters other than numbers, letters and special characters (^~$,;@$!%*#?&|_-).')
						} else if ( parmsObject.o.length > 256 || parmsObject.i.length > 256 ||  parmsObject.a.length > 256 ) {
							wpIssuesCRM.alert ( 'Password may not be longer than 256 characters.')
						} else {
							wpIssuesCRM.ajaxPost( 'email_settings', 'save_parms',  0, parmsObject,  function( response ) {
								passwordPopupObject.html( '<h4><strong>' + ( response.saved ? 'Successful' : 'Unsuccessful' ) + ':</strong></h4><p>' + response.message + '</p>' );
								$( "#savePasswordsButton" ).remove();
								if ( response.saved ) { 
									$( "#cancelSavePasswordsButton .ui-button-text").text( "Close" );
								}
							});
						}
					}
				},
				{
					width: 100,
					id: 'cancelSavePasswordsButton',
					text: "Cancel",
					click: function() { 
						passwordPopupObject.dialog( "close" );
					}
				}
			],
			modal: true,
		});
	
	
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	


