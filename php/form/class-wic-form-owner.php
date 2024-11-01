<?php
/*
*
*  class-wic-form-owner.php
*
*/

class WIC_Form_Owner extends WIC_Form_Parent  {

	// define the top row of buttons 
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons ( $data_array ) .  '<a href="' . site_url() . '/wp-admin/admin.php?page=wp-issues-crm-owners">' . __( 'Back to Owner List', 'wp-issues-crm' ) . '</a>');
	
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		return ( $this->get_the_verb( $data_array ) . ' ' . __('Owner Data. ', 'wp-issues-crm') . $message );
	}

	protected function group_special( $group_slug ) {
		return 'owner_info' == $group_slug;
	}

	protected function group_special_owner_info () {  
		return '
			<p>This form allows network administrators to assign districts to secondary sites in a multi-site environment.</p>
			<p>This form defines the slice that each secondary site will receive when they synchronize using the "Synch Data" menu item.  A secondary site administrator will see a "Synch Data" option
			on their menu if and only if "Owner Type" is defined here.</p>
			<p>To setup users as owning district data in a multiuser configuration of WP Issues CRM, the steps are as follows.</p> 
			<ol>
			<li>Load the jurisdiction wide data into the copy of WP Issues CRM running on the primary site (Blog 1 or whichever Blog is configured as primary in config.php). <em>Populate the Registration district codes 
			that you want to use through the main WP Issues CRM upload function</em> (the <span class="dashicons dashicons-upload"></span> button on the primary WP Issues CRM form).  WP Issues CRM upload allows you to map the database you have received in a .csv format to fields in your WP Issues CRM database.</li>
			<li><b>Make sure the Registration_ID field is populated with a unique identifier for each resident or voter.</b> Synchronization depends on this identifier.</li>
			<li>From this form, assign an Owner Type to the site -- any geographic field (city/state/zip) or any Registration district code.</li>
			<li>Assign an Owner ID -- the value of the registration code that corresponds to this site.</li>
			<li>Activate WP Issues CRM on the secondary site (this could be done first; order does not matter).</li>
			<li>Direct the adminstrator of that site to the "Synch Data" option on their menu to pull the records for that district over to their copy of WP Issues CRM</li>
			</ol>
			<p>For example, if you were setting up state senate districts and "2SM" were the code used to refer to a particular senate district, records in the main database for voters from that district </li>
			should have the value "2SM" in the state_senate_district field.  To set up that district for a particular blog, select Senate as Owner Type and "2SM" as Owner ID.</p>
			<p> Note that City, State, Zip, Precinct and Ward can also be used to define slices or districts and that you can mix different district types in the multisite installation.</p>
			
			<p>On this form:</p>
			<ol>
			<li>The "Owner Name" is just a memo field.</li>
			<li>The "Owner Type" defines the field that will be used to segment the central database for this secondary site.  For example, if the main database were a statewide database of voters,
			the secondary site could have been setup to support a senate district.  If senate is selected here, then this site will be able to copy the records from the statewide database that
			have the value set in Owner ID in the field state_senate_district. </li>
			<li>The "Owner ID" can have any value including blank.  Records having this value in the field assigned by owner type will be pullable by this site.</li>
			</ol>
			<p>A few other points</p>
			<ol>
			<li>Only records within the assigned district will be available for synchronization from the central database.</li>
			<li>This is a one way synchronization -- data on the central database is never altered.</li>
			<li><em>Synching between the central and district copies of the database is done only by registration_id,</em> the unique identifier that should be on every record
			of the central database.</li></ol>';
	}
	
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 

}