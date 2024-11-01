/*
* oauth.js
*/
jQuery( document ).ready( function($) { 

	$( "#wp-issues-crm" ).on( "click", "#wic_connect_to_gmail", function ( event ) {
		 window.location = $( event.target ).val();
		 event.stopImmediatePropagation();
	});

	$( "#wp-issues-crm" ).on( "click", "#wic_disconnect_from_gmail", function ( event ) {
		wpIssuesCRM.disconnectGmail( event ); 
		event.stopImmediatePropagation();
	});
});


// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {
	
	wpIssuesCRM.disconnectGmail = function ( event ) {
	
		$(event.target).text( 'Disconnecting' ).attr('disabled',true);		
		wpIssuesCRM.ajaxPost( 'email_oauth', 'disconnect',  '', '',  function( response ) {
			$(event.target).text( 'Disconnected' );
			$( ".wic-oauth-gtg" ).text( 'Disconnected' );
			$( ".wic-oauth-background" ).text ( "You can reconnect to change accounts or reauthorize the connection. If you do neither, WP Issues CRM will connect using the settings for the IMAP connection defined on the main WP Issues CRM Configure page.")
		});	
	
	} 

} ( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery ) ); // end  namespace enclosure	
