/*
*
*	option-group.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

		
	$( "#wp-issues-crm" ).on ( 
		"change deletedWICRow spinchange", 
		'form#wic-form-option-group',
		function (e) {
			wpIssuesCRM.formDirty = true;
			wpIssuesCRM.setChangedFlags(e);
		}
	
	)
	
	// set up listener to trigger form initialization
	.on ( "initializeWICForm", function () { 

		$( "#field_order" ).prop( "readonly", true );

		// note that this non-delegated listener fires before the ajax listener in the bubbling
		$( ".wic-form-button" ).on( "click", function ( event ) { 
			return ( wpIssuesCRM.testForDupOptionValues () );
		});
		/*
 		*
 		*  	kludge to prevent user from disabling option_groups used by always installed fields
 		*  	these option groups can be edited (i.e., they are not reserved), but are always required
 		*
 		*	choose not to handle this by making multiple values for the dictionary system reserved item
 		*/
 		frozenFields = [ 
 			"activity_type_options", 
 			"address_type_options",
 			"case_status_options",
 			"email_type_options",
 			"follow_up_status_options",
 			"gender_options",
 			"party_options",
 			"phone_type_options",
 			"state_options",
 			"pro_con_options",
 			"voter_status_options"
 		];	
 		if ( $.inArray( $( "#option_group_slug" ).val(), frozenFields ) > -1 ) {
 			$( "#option_group_slug" ).attr( "readonly", true );
 			$( "#enabled" ).html('<option value="1" selected="selected">Enabled</option>' ); 			
 		}
 
 
	}) 

	// initialize (reinitialize) visible spinners
	.on ( "addedWICRow initializeWICForm", function () {
		$(".visible-templated-row :input.value-order").spinner( {
			min: 0,
			max: 5000
		});
		$( ".visible-templated-row :input.value-order" ).each(function(){
			if ( null === $(this).spinner("value") ) {
				$(this).spinner( "value", 0 );
			}
		})
	})
	
	$( "#wp-issues-crm" ).trigger ( "initializeWICForm" );

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.testForDupOptionValues = function() {

		var optionValues = document.getElementsByClassName( 'wic-input option-value' );

		valuesValues = [];
	
		for ( var i = 0; i < optionValues.length; i++ ) {
			var dbVal = optionValues[i].value;
			if ( null !== optionValues[i].offsetParent ) {
				valuesValues.push( optionValues[i].value.trim() );	
			}
		} 
		var sortedValues = valuesValues.sort();
	
		for (var j = 0;  j < sortedValues.length - 1; j++) {
		 if (sortedValues[j + 1] == sortedValues[j]) {
			var displayValue; 
			if ( '' == sortedValues[j].trim() ) {
				displayValue = '|BLANK|';
			} else {
					displayValue = '"' + sortedValues[j] + '"';   	 	
			}
			wpIssuesCRM.alert ( 'The database value of each option must be unique.  The value ' + displayValue + ' appears more once. ' )
			return false;
			}
		}	
		return true;
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
