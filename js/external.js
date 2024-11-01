/*
*
*	external.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

		
	$( "#wp-issues-crm" ).on ( 
		"change", 
		'form#wic-form-external',
		function (e) {
			wpIssuesCRM.formDirty = true;
			wpIssuesCRM.setChangedFlags(e);
		}
	)
	
	// set up listener to trigger form initialization
	.on ( "initializeWICForm", function () { 
		// note that this non-delegated listener fires before the ajax listener in the bubbling
		$( "#map_form_fields_button" ).on( "click", function ( event ) {
			event.preventDefault();
			wpIssuesCRM.setupFieldMapDialog();
		});
	}) 

	// show or hide option list based on 
	.on ( "change", "#external_type", function() {
		wpIssuesCRM.getExternalIdentifierControl();
	})

	// wipe out map if change form type and/or form
	.on ( "change", "#external_type, #external_identifier", function() {
		if ( $( "#external_identifier" ).val() > '0' &&  $( "#wic-form-external #ID" ).val() > '0' ) {
			wpIssuesCRM.formFieldMap = {};
			wpIssuesCRM.updateFormFieldMap ( '', '' );
			$( "#post-form-message-box" ).text ( 'Form field mappings (if any) reset on form change.' );
		}
	})

	// support delete button
	.on ("click", "#wic-external-delete-button", function ( event ) {
		var ID = $( event.target ).val();
		wpIssuesCRM.confirm(
			function () {
				wpIssuesCRM.ajaxPost( 'external', 'hard_delete', ID, '',  function( response ) {
					$( "#post-form-message-box" ).text ( response.message );
					$( "#post-form-message-box" ).addClass ( 'wic-form-errors-found' )
					setTimeout ( function () {
						window.location.href = response.list_page ;
						},
						3000
					);	
				});
			},
			false,
			'<div id="constituent_delete_dialog"><h4>' + 'Delete this interface?' + '</h4>' +
			'<p>Deleting this form interface will NOT delete the form it supports or any of the data created through it.</p>' +
			'</div>'
		)
	} )

	$( "#wp-issues-crm" ).trigger ( "initializeWICForm" );

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.formFieldMap = {}; // main synch object between popup and database

	// note that javascript falsey values do not include string 0
	wpIssuesCRM.setupFieldMapDialog = function () {
		externalID = $( "#wic-form-external #ID" ).val();
	    externalIsChanged = $( "#wic-form-external #is_changed" ).val();
		if (  0 == externalID || 1 == externalIsChanged  ) {
			wpIssuesCRM.alert ( 
				'<p>Please save/update interface details before mapping fields.</p>'
			);
		} else {
			wpIssuesCRM.ajaxPost( 'external', 'setup_field_map', externalID, '', function( response ) {
				wpIssuesCRM.doFieldMapDialog ( response );			
			});	
		}
	}

	wpIssuesCRM.doFieldMapDialog = function ( response ) {
		
		fieldMapPopup = $.parseHTML ( '<div id="field-map-popup" title="' + response.dialog_title  + '">' + response.dialog_content + '</div>' );

		wpIssuesCRM.fieldMapPopupObject = $( fieldMapPopup );
		wpIssuesCRM.fieldMapPopupObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: true,
			close: function ( event, ui ) {
				wpIssuesCRM.formDirty = false; 
				wpIssuesCRM.fieldMapPopupObject.remove(); // cleanup object
				},
			position: { my: "left top", at: "left top", of: "#wp-issues-crm" }, 	
			width:  $( "#wp-issues-crm" ).width(),
			height:  Math.min( 1000, .9 * $(window).height()  ), 
			buttons: [
				{
					width: 100,
					text: "Save",
					click: function() { // noop -- always disabled.  Save is automatic.
					}
				},
				{
					width: 100,
					text: "Close",
					click: function() {
						wpIssuesCRM.fieldMapPopupObject.dialog( "close" );
					}
				}
			],
			modal: true,
		});
		$(".ui-dialog-buttonpane button:contains('Sav')").attr("disabled", true).addClass("ui-state-disabled");
		// use the function from upload-map.js
		wpIssuesCRM.initializeMapping();

	}

	wpIssuesCRM.loadFormFieldMap = function() {
		wpIssuesCRM.ajaxPost( 'external', 'get_field_map',  $('#wic-form-external #ID').val(), '', function( response ) {
			// calling parameters are: entity, action_requested, id_requested, data object, callback
			wpIssuesCRM.formFieldMap = response ? response : {}; // empty should be empty object
			// loop through the response dropping upload-fields into targets
			for ( x in wpIssuesCRM.formFieldMap ) {
				if ( wpIssuesCRM.formFieldMap[x] > '' ) {
					draggableID = "wic-draggable___" + x ;
					droppableID =  "wic-droppable" + '___' + wpIssuesCRM.formFieldMap[x].entity + '___' + wpIssuesCRM.formFieldMap[x].field ;
					// drop the draggable upload field into the droppable
					// note that dropEventDetails will take no action if draggableID does not exist (as in field deleted from form, but existing in map)
					wpIssuesCRM.dropEventDetails ( draggableID, droppableID ) ;
				}
			}	
			$( ".wic-draggable" ).draggable( "enable" );
		});		
	}

	wpIssuesCRM.updateFormFieldMap = function ( dragObject, dropObject ) {
		$( ".wic-draggable" ).draggable( "disable" );
		$saveButtonText = $(".ui-dialog-buttonpane button:contains('Sav') .ui-button-text").text( 'Saving ...');
	
		if ( dropObject ) {
			wpIssuesCRM.formFieldMap[dragObject] = dropObject;
		} else {
			delete wpIssuesCRM.formFieldMap[dragObject];
		}
		// send column map on server
		wpIssuesCRM.ajaxPost( 'external', 'update_field_map',  $('#wic-form-external #ID').val(), wpIssuesCRM.formFieldMap, function( response ) {
			// reenable draggables after update complete 
			$( ".wic-draggable" ).draggable( "enable" );
			$saveButtonText.text('Saved');
		});
	}

	wpIssuesCRM.getExternalIdentifierControl = function(){
		// when change type, start over with identifier
		wpIssuesCRM.ajaxPost( 'external', 'get_external_identifier_options', $( "#external_type").val() , '', function( response ) {
			// insert the new options
			wpIssuesCRM.setOptions( $( "#external_identifier" )[0], response );
		});		
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
