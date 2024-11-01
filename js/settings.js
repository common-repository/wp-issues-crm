/*
*
* settings.js
*
*/
jQuery(document).ready(function($) { 
	
	$( "#wic-settings-tabs" ).tabs()
	
	.on( "click", "#wic-email-send-test-button", function () { 
		wpIssuesCRM.doSendEmailDialog();
	})

	.on( "click", "#wic-email-receive-test-button", function () { 
		wpIssuesCRM.doReceiveCheck();
	})

	.on( "click", "#synch-comments-button", function () {
		wpIssuesCRM.doCommentsSync();
	})

	.on ( "change", function () {
    	flashInterval = setInterval(function () {
			$( "#wp-issues-crm-settings #submit" ).toggleClass("wic-alert-class");	
    	}, 1000);
	})

	.on( "change", "#from_email, #smtp_reply", function(event) { 	 
		if ( $ ( this ).val() > '' ) { 
			var patt =  new RegExp ( '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,20}$' ); 
			if ( ! patt.test( jQuery ( this ).val() ) ) {
				wpIssuesCRM.alert ('Email address entered appears to be invalid.' ) ;
			};
		}
	})

	.on( "change", "#smtp_user", function() {	
		if ( $ ( this ).val() > '' ) { 
			var patt =  new RegExp ( '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,20}$' ); 
			if ( ! patt.test( jQuery ( this ).val() ) ) {
				wpIssuesCRM.alert ('<p>Entered value for "User" appears not to be an Email address.</p>' +   
				'<p>For some SMTP servers, this may be correct, but you will need to specify a "from" email address.</p>' ) ;
			};
		}
	});	


	// set up inbox options asynchronously so as not to delay form for other tabs imap.gmail.com
	// is initially set up as control with only the previously selected option.
	if ( ! $( "#email_imap_server" ).val() ) { 
		$( "#imap_inbox_select_wrapper" ).html( 'Must configure mail connection options before configuring inbox.' );
	} else { 
		wpIssuesCRM.ajaxPost( 'email_connect', 'imap_inbox_callback_ajax', 0, $( "#wp_issues_crm_plugin_options_array\\[imap_inbox\\]").val(), function ( response ) {
			if ( 'WARN' == response ) { 
				settingWarning = $.parseHTML('<p class = "wic-form-errors-found">Save correct connection settings --  <em>then come back</em> to set Inbox. Cannot display folder options with incorrect connection settings.</p> ')
				$( "#imap_inbox_select_wrapper" ).append( settingWarning  );
			} else { 
				$( "#imap_inbox_select_wrapper" ).html( response );
			}
		});
	}
	
});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {
	/*
	*
	* doSendEmailDialog -- popup email to test settings
	*
	*/
	wpIssuesCRM.doSendEmailDialog = function() {

		// define dialog box
		var divOpen = '<div id="email-test-send" title="Use saved mailer settings to send test email">';
		var emailInput = '<label for="test-email-address">Send test email to:&nbsp;&nbsp; </label><input id="test-email-address" placeholder="valid email address" type="email" width="500" required></input>' ;
		var divClose = '<div id="test-email-response"></div></div>';
		dialog = jQuery.parseHTML( divOpen + emailInput + divClose );
		dialogObject = jQuery( dialog );
  		dialogObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				dialogObject.remove();						// cleanup object
  				},
			width: 600,
			buttons: [
				{	width: 100,
					text: "Send Test",
					click: function() {
						elem = document.getElementById( "test-email-address" );
						jQuery( "#test-email-response" ).html( '<br/>Sending a test email . . .' );
						if ( true === elem.checkValidity() ) {
							var testEmail = dialogObject.find ( "#test-email-address" ).val();
							wpIssuesCRM.ajaxPost( 'email_deliver', 'test_settings', 0, testEmail, function ( response ) {
								if ( 'success' == response ) {
									response = '<h4>Test message sent OK to ' +  elem.value + ' -- check that inbox!</h4>';
								}
								jQuery( "#test-email-response" ).html( response );
							});
						} else {
							jQuery( "#test-email-response" ).html( '<br/>Please enter a valid email address to send a test to.' );
						}
					}
				},
				{
					width: 100,
					text: "Close",
					click: function() {
						dialogObject.dialog( "close" ); 
					}
				}
			],
  			modal: true,
  		});
	}


	wpIssuesCRM.doReceiveCheck = function() {
		$( "#wic-email-receive-test-button" ).text( "Testing . . ." );
		wpIssuesCRM.ajaxPost( 'email_connect', 'test_settings', 0, '', function ( response ) {
			wpIssuesCRM.alert ( response );
			$( "#wic-email-receive-test-button" ).text( "Test Saved Settings" );
		});
		
	}

	wpIssuesCRM.doCommentsSync = function(){
		$( "#synch-comments-button" ).text( "Synching . . ." );
		wpIssuesCRM.ajaxPost( 'comment', 'synch_now', 0, '', function ( response ) {
			wpIssuesCRM.alert ( response );
			$( "#synch-comments-button" ).text( "Synch Now" );
		});
	};

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	


