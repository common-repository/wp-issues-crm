/*
*
*	synch.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

		
	$( "#wp-issues-crm" ).on ( 
		"click", 
		'.action-synch-button',
		function (e) {
			wpIssuesCRM.loadSynchData (  $( this ).parent().parent().attr( "id"), "exec" )
		}
	
	)
	
	// initialize each of the working areas
	$( ".synch-working-area" ).each( function() { 
		wpIssuesCRM.loadSynchData ( $( this ).attr( "id"), "init" ) ;
	});
	
	
	
	
	

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.loadSynchData = function ( synchDivId, mode ) {
		var resultsArea = $( "#" + synchDivId + " .synch-results-area" );
		var spinner 	= $( "#" + synchDivId + " .synch-spinner" );
		var action = synchDivId.split('-')[1]; 
		spinner.show();
		$( ".action-synch-button").prop('disabled', true);
		wpIssuesCRM.ajaxPost( 'synch', 'do_synch',  action, mode,  function( response ) {
			resultsArea.html( response )
			$( ".action-synch-button").prop('disabled', false);
			spinner.hide();

		});
	
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
