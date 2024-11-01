<?php
/*
*
*  class-wic-form-synch.php
*
*/

class WIC_Form_Synch extends WIC_Form_Parent  {

	// define the top row of buttons 
	protected function get_the_buttons ( &$data_array ) {}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		return ( 'Update registered constituents for '  . WIC_Entity_Owner::get_owner_type_label ( get_option ('wic_owner_type') ) . ' -- "' . get_option( 'wic_owner_id') . '".' );
	}

	protected function group_special( $group_slug ) { return true;}
	
	// working areas
	protected function group_special_mark_deleted () 			{ return $this->set_up_working_area ( 'synch-deleted' );}
	protected function group_special_refresh_registration () 	{ return $this->set_up_working_area ( 'synch-registration-refreshed' );}
	protected function group_special_refresh_address () 		{ return $this->set_up_working_area ( 'synch-address-refreshed' );}
	protected function group_special_add_constituents() 		{ return $this->set_up_working_area ( 'synch-added' );}
	private function set_up_working_area ( $divid ) {
		return 					
			'<div id = "' . $divid . '" class = "synch-working-area">' .
				'<div class="synch-results-area"></div>' .
				'<div class="synch-spinner">
					<img src="' . plugins_url( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'ajax-loader.gif' , __FILE__ ) . '">' . 
				'</div>' .
			'</div>';
	}
	// legend area
	protected function group_special_synch_info () {  
		return '
			<p>This form allows district site administrators to pull data from a central site in a multi-user multi-district arrangement.  It appears only 
			if the network administrators have assigned a district type to the district site.</p>
			<p>The most important things to know are:</p>
			<ol>
			<li>Only records within your assigned district will be available for synchronization from the central database.</li>
			<li>This is a one way synchronization -- data on the central database is never altered.</li>
			<li><em>Synching between the central and district copies of the database is done only by registration_id,</em> the unique identifier that should be on every record
			of the central database.</li></ol>
			<p>If the district copy has valid constituent records that were acquired externally without a registration_id, use of this synch mechanism will
			create duplicates.  If you have brought in data from another system, for example, there might be many such records.  In this case, for your first synch,
			ask your administrator to give you a file of your district records and upload them using the main WP Issues CRM upload
			function which gives you a lot of flexibility as to record matching approaches to minimize duplicates.</p><p>If you want to research the likely volume of duplicates, 
			externally acquired records can be identified in advanced search as having a blank "Synch Status" indicating they were never synched. To avoid overstating the size of that group, be sure
			to also screen by geography to your district.</p>
			<p>If you have been synchronizing to registered data regularly, then there should not be many valid constituent records with blank "Synch Status"
			 because as WP Issues CRM adds records through the email and other functions, it will be matching them to the registered data. </p>
			<p>A few other tips:</p>
			<ol>
			
			<li>None of these functions delete any of your constituents.  The "Departed Constituents" will mark those no longer on the central registration database.  
			You can choose to delete them by searching for constituents with "Synch Status" equal to "N" for "Not Found" and then using the bulk delete function.</li>
			<li>The "Registration Changed" function will identify and overlay registration data (including name, gender and age) 
			with data from the central database, but will never overlay data with a blank, so that if you have acquired data going beyond registration data, it will be preserved.</li>
			<li>The "Address Changed" function will only overlay data on the address record having the type "Registered".  So, if you have acquired address information of other types,
			it will be preserved.</li>
			<li>The "New Constituents" function will pull both registration information and address information.</li>
			<li>All synchronization functions assume that the central copy has no duplicate registration IDs and only one address per record.  Exceptions do not trigger database errors, but 
			will result in slightly confusing results for the particular records in question -- details of expected handling of duplicates are documented in the code for WIC_Entity_Synch.</li>
			</ol>';
	}



	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 

}