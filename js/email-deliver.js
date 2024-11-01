/*
*
* email-deliver.js
*
* supports queue tab functions
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {
	
	wpIssuesCRM.loadOutboxListeners = function() {
		// 	bind listener for queue hold button (already loaded );
		$( "#control-process-email-button" ).on ( "click", function() { 
			var newMailerStatus = $( this ).hasClass ( "mailer_held" ) ? 1 : 0; // if held, new status is true, mailer_ok
			wpIssuesCRM.ajaxPost( 'email_deliver', 'set_mailer_status',  0, newMailerStatus,  function( response ) {
				if ( response > 0 ) {
					$ ( "#control-process-email-button" ).removeClass ( "mailer_held" ).addClass( "mailer_ok" );
				} else { 
					$ ( "#control-process-email-button" ).removeClass ( "mailer_ok" ).addClass( "mailer_held" );
				} 
				setUpControlProcessEmailButton()
			});	
		});	
		
		$( "#purge-queue-email-button" ).on( "click", function() {
			wpIssuesCRM.confirm ( 
				purgeQueue,			
				false,
				'<p>Made a big mistake? Immediately purge all unsent messages and their related activity records.</p>' +
				'<p>Best practice: <i>Suspend Mailer</i> first so you can take your time and browse through the Outbox to ' + 
					'make sure you know what messages you are going to lose.  The mailer normally runs hourly, so ' +
					'it is possible that there are earlier good messages sitting in the Outbox as well.</p>'

			);
		});		
	}
	
	wpIssuesCRM.getMailerStatus = function () {
		wpIssuesCRM.ajaxPost( 'email_deliver', 'get_mailer_status',  0, '',  function( response ) {
			if ( response > 0 ) {
				$( "#control-process-email-button").removeClass ( "mailer_held" ).addClass( "mailer_ok" );
			} else {
				$( "#control-process-email-button").removeClass ( "mailer_ok" ).addClass( "mailer_held" );
			}				
			setUpControlProcessEmailButton();
	
		});
	}

	// respond to the button for controlling the queue
	function setUpControlProcessEmailButton() {
		controlProcessEmailButton = $ ( "#control-process-email-button" );
		if ( controlProcessEmailButton.hasClass ("mailer_ok" ) ) {
			controlProcessEmailButton.text("Suspend Mailer").attr("title", "Immediately interrupt and hold mailer.");
		} else { 
			controlProcessEmailButton.text("Release Mailer").attr("title", "Release mailer to run when next scheduled.");
		}
		controlProcessEmailButton.tooltip( {show: false, hide: false } );
		controlProcessEmailButton.tooltip("option", "content", controlProcessEmailButton.attr("title") );
	}

	function purgeQueue () {
		$( "#outbox-ajax-loader" ).show();
		$( "#wic-load-outbox-inner-wrapper" ).html('');
		wpIssuesCRM.ajaxPost( 'email_send', 'purge_mail_queue',  0, '',  function( response ) {
			wpIssuesCRM.loadSelectedPage();
	
		});	
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
