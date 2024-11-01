/*
*
*	data-dictionary.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

	$( "#wp-issues-crm" ).on ( "initializeWICForm", function (e) { 
		$( "#field_order" ).spinner( {
			min: 0,
			max: 5000
		});
		$( "#field_order" ).prop( "readonly", true );

		// note that this non-delegated listener fires before the ajax listener in the bubbling
		$( ".wic-form-button" ).on( "click", function ( event ) { 
			return ( wpIssuesCRM.testForDupGroupOrderValues () );
		}); 

	}) 

	.on ( 
			"change spinchange", 
    		'form#wic-form-data-dictionary',
			function (e) {
				wpIssuesCRM.formDirty = true;
				wpIssuesCRM.setChangedFlags(e);
			}
		)

	.on ( "change", "#option_group", function() { 
		wpIssuesCRM.changeOptionGroup();				
	})
	
	.on ( "change", "#field_type", function () {
		wpIssuesCRM.changeFieldType();
	});

 	$( "#wp-issues-crm" ).trigger ( "initializeWICForm" ); 
});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.changeOptionGroup = function() {
		if ( '' == $( "#option_group" ).val() ) {
			wpIssuesCRM.setVal( $( "#field_type" )[0], "text", '' );
		} else {
			wpIssuesCRM.setVal( $( "#field_type" )[0], "selectmenu", '' )
		}
		// keep list formatter in synch;
		$( "#list_formatter" ).val( $( "#option_group" ).val() );
		
		// remove errors set regardless -- if change to a non-empty value then now consistent with select
		// -- if change to empty value, then resetting to text
		$( "#post-form-message-box" ).text ( 'Save/update field.' );
		$( "#post-form-message-box" ).removeClass ( 'wic-form-errors-found' )
		$( ".wic-form-button" ).prop( "disabled", false );
	}

	// main purpose is to prevent select field from being saved without option group
	// underlying form validation logic doesn't support cross field validation
	// if field_type is select and no option_group, show error, otherwise clear error; also default search choice
	wpIssuesCRM.changeFieldType = function () { 
		if ( 'text' == $( "#field_type" ).val() ) {
			wpIssuesCRM.setVal( $( "#option_group" )[0], '', '' );
			$( "#post-form-message-box" ).text ( 'Save/update field.' );		
			$( "#post-form-message-box" ).removeClass ( 'wic-form-errors-found' )
			$( ".wic-form-button" ).prop( "disabled", false );								
		} else if ( 'date' == $( "#field_type" ).val() ) {
			wpIssuesCRM.setVal( $( "#option_group" )[0], '', '' );
			$( "#post-form-message-box" ).text ( 'Save/update field.' );		
			$( "#post-form-message-box" ).removeClass ( 'wic-form-errors-found' )
			$( ".wic-form-button" ).prop( "disabled", false );			
		} else if ( 'selectmenu' == $( "#field_type" ).val() ) {
			if ( '' == $( "#option_group" ).val() ) {
				$( "#post-form-message-box" ).text ( 'Please specify an option group for your select field.' );
				$( "#post-form-message-box" ).addClass ( 'wic-form-errors-found' )
				$( ".wic-form-button" ).prop( "disabled", true );				
			}
		}				
	};

	wpIssuesCRM.testForDupGroupOrderValues = function() {

		var tableRows = $( ".field-table-row" );
		var fieldSlug = $( "#field_slug" ).val();
		var groupSlug = $( "#group_slug" ).val();
		var fieldOrder = $( "#field_order" ).val();
		var fieldLabel = $( "#field_label" ).val();
		var foundField = false;

		
		// first update table with current value 
		if ( fieldSlug ) { 
			tableRows.each(function(){
				$this = $(this);
				if ( fieldSlug == $this.children(".field-table-field-slug").text() ) { 
					foundField = true;
					$this.children(".field-table-field-group").text( $( "#group_slug :selected" ).text() );
					$this.children(".field-table-group-slug").text( groupSlug );
					$this.children(".field-table-field-order").text( fieldOrder );
					$this.children(".field-table-field-label").text( fieldLabel );
					return false;
				}
			}) 
		}
		
		// extract comparison array
		var groupOrderArray = [];
		tableRows.each(function(){
			$this = $(this);
			groupOrderArray.push( $this.children(".field-table-group-slug").text() + '|||' +  $this.children(".field-table-field-order").text() )
		}) 

		// add a plug for new field in comparison array
		if ( ! foundField ) { // because no fieldSlug or because missing from table because disabled
			groupOrderArray.push ( groupSlug + '|||' + fieldOrder );
		}

		var sortedGroupOrderArray = groupOrderArray.sort();		
		var countRows = sortedGroupOrderArray.length;

		for (var j = 0;  j < countRows - 1; j++) {
		 	if (sortedGroupOrderArray[j + 1] == sortedGroupOrderArray[j]) {
				var splitRow = sortedGroupOrderArray[j].split("|||");
				wpIssuesCRM.alert ( '<p>Please change screen order for field.</p>' + 
					( 
						foundField ? 
						( '<p>Two fields in group with system name <code>'     + splitRow[0] + '</code> have screen order <code>'  + splitRow[1] + '</code>.</p>' ) :
						( '<p>Your new/updated field in group with system name <code>' + splitRow[0] + '</code> has a screen order <code>' + splitRow[1] + '</code> which conflicts with an enabled field.</p>'  ) 
					) +
					'<p>This conflict, if allowed, would cause forms to omit one of them.</p>' 
				);
				return false;
			}
		}
		$( "#post-form-message-box" ).removeClass ( 'wic-form-errors-found' )
		$( "#post-form-message-box" ).text( "Updating database configuration ... this may take a little while ... ");	
		return true;
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
