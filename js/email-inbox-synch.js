/*
*
* email-inbox-synch.js
*
*/
// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.loadSynchListeners = function() {
		// bind listeners for synch buttons ( already loaded) 
		$( "#inbox-synch-button" ).on ( "click",  function () {
			wpIssuesCRM.synchInbox( true )
		})
		$( "#inbox-reparse-button" ).on ( "click", inboxReparse );
		$( "#inbox-purge-button" ).on ( "click", inboxPurge );
	}
	
	// regular box load
	wpIssuesCRM.synchInbox = function ( resynch ) {
		var synchClass = resynch ? 'email_account' : 'email_inbox_synch';
		var synchFunction = resynch ? 'email_inbox_synch_now' : 'load_staged_inbox';
		$( "#inbox-synch-ajax-loader" ).show();
		disableSynchButtons();
		wpIssuesCRM.ajaxPost( synchClass, synchFunction,  '', '',  function( response ) {
			$ ( "#inbox-synch-ajax-loader" ).hide();
			$ ( "#inbox-synch-inner-wrapper" ).html( response );
			enableSynchButtons();
 		});		
	}

	// reparse
	function inboxReparse() {
		wpIssuesCRM.confirm(
			function () {
				$( "#inbox-synch-ajax-loader" ).show();
				disableSynchButtons();
				wpIssuesCRM.ajaxPost( 'email_inbox_parse', 'unparse_inbox',  0, '',  function( response ) {
					// return is as if from inbox load
					$ ( "#inbox-synch-ajax-loader" ).hide();
					$ ( "#inbox-synch-inner-wrapper" ).html( response ); // returns inbox synch load
					enableSynchButtons();
 				});
 			},
			false,
			'<div id="purge-resynch-dialog"><h4>Reset parsing of unprocessed messages.</h4>' +
				'<p>Messages will disappear from Inbox and reappear as they are reparsed over the next few minutes.' + '</p>' +
				'<p>Reset of parsing is safe and only affects your currently selected Inbox folder, not any recorded activities or outgoing messages.</p>' +
				'<p>This function is intended to support experimentation with changed address parsing rules (in email Controls).</p>' +
				'<p>This function does not resynchronize the full message list.</p>' +
			'</div>'
		);
	}



	// purge resynch
	function inboxPurge() {
		wpIssuesCRM.confirm(
			function () {
				$( "#inbox-synch-ajax-loader" ).show();
				disableSynchButtons();
				wpIssuesCRM.ajaxPost( 'email_inbox_synch', 'purge_inbox',  0, '',  function( response ) {
					$ ( "#inbox-synch-ajax-loader" ).hide();
					$ ( "#inbox-synch-inner-wrapper" ).html( response );
					wpIssuesCRM.synchInbox ( true );
 				});
 			},
			false,
			'<div id="purge-resynch-dialog"><h4>' + 'Purge WP Issues CRM image of current folder and resynchronize.' + '</h4>' +
				'<p>Purge is safe and only affects your currently selected Inbox folder and related Archived messages, not any recorded activities corresponding to those messages, but please read the following notes before proceeding.</p>' +
				'<ol>' +
					'<li>Although deleting Archived messages will not affect the activity records corresponding to constituent messages, it will make any attachments to the messages unavailable, even from the activity record.</li>' +
					'<li>Caution: If any messages are marked to be moved on the folder (as after a send or record action), the purge will prevent that action, and the messages will reappear in the inbox.  Wait until after a resynch cycle.</li>' +
					'<li>Caution: A purge can, in theory, conflict with the background synch process in extremely rare timing conditions.  This conflict would result in only most recent messages being loaded.  Just resynch if that happens.</li>' +
					'<li>Messages missing from the main (subject line) inbox are likely be due to the parse process running behind -- unparsed messages cannot be shown in the main subject line inbox.' +
					' They WILL resolve as the message parse process catches up.  Purge does not accelerate the parse catch up.  It starts it over.</li>' +
				'</ol>' +
			'</div>'
		);
	}

	function enableSynchButtons() {
		$( "#inbox-synch-button" ).prop( "disabled", false );		
		$( "#inbox-reparse-button" ).prop( "disabled", false );		
		$( "#inbox-purge-button" ).prop( "disabled", false );		
	}
	
	function disableSynchButtons() {
		$( "#inbox-synch-button" ).prop( "disabled", true );		
		$( "#inbox-reparse-button" ).prop( "disabled", true );		
		$( "#inbox-purge-button" ).prop( "disabled", true );	
	}
	
} ( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
