=== WP Issues CRM ===
Contributors: Will Brownsberger
Donate link: 
Tags: contact, crm, constituent, customer, issues, list, email, forms, database, upload
Requires at least: 5
Tested up to: 5.5
Stable tag: 4.5.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

CRM for offices that receive and respond to high-volume correspondence from broad constituencies.

== Description ==

NOTE:  This CRM has served us very well and we are currently in the process of migrating it to the Azure/.NET environment. We do not intend to continue to evolve this Wordpress/Linux version of it.  If you are a current user and need assistance with migration options, please contact the author at 617-771-8274.

We built this constituent relationship management system to meet the office needs of elected officials and public and non-profit organizations that respond to large constituencies or memberships.  However, it can support either office or campaign operations and is flexible enough to support a wide variety of organizations.

In addition to providing a clean, flexible and full-featured CRM, it offers a solid email client that automates recording and response to high-volume incoming email traffic.  It now supports both the secure Gmail API and the Microsoft Exchange ActiveSync API.

It uses the powerful classification tools of Wordpress, but uses custom tables for high performance access to a large constituent database. 

WP Issues CRM integrates with Two popular Wordpress Form generators (Contact Form 7 and Gravity Forms), supporting online fundraising and case-intake.  Data acquired through front-facing forms is accessible through the high performance backend of WP Issues CRM.

It also integrates with Google Maps to allow geographical selection within found lists for downloading or emailing.  It supports the configuration of boundary layers to enrich list maps.

WP Issues CRM offers special support for multi-site installations. A single central load of a jurisdication-wide database can supply regularly updated data for district slices of the database.

== Installation ==

1. Load WP Issues CRM through the Add New 'Plugins' menu or install the zip file in the plugins directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go through the WP Issues CRM Configure page and make basic configuration decisions.
1. Consider your office use of information and add any necessary constituent fields under Fields.
1. If you are adding some "Select" fields -- some typology like political party -- define the options under Options. 
1. TIP: Before adding new fields, consider whether you can just use Wordpress categories or tags -- if you enter activities for constituents, they each get assigned to an "Issue", which is just a Wordpress post.  Activities can be classified and retrieved using the Wordpress categories and tags assigned to their issue.
1. If you are planning to import data from an existing CRM, redefine WP Issues CRM standard option sets like Email Type for consistency with your previous system. 
1. Use the powerful WP Issues CRM upload subsystem to import data from your current CRM and/or from external sources. 
1. Configure your IMAP connection so you can read and parse incoming email directly into your database.
1. Configure your SMTP connection so you can send email through your preferred delivery platform.
1. Create interfaces to existing forms to bring your data back to WP Issues CRM. 

== Frequently Asked Questions ==

= Where can I view additional documentation? =

Visit http://wp-issues-crm.com.
 
= Where I can I get support? =

The support forum at http://wordpress.org/support/plugin/wp-issues-crm . 

If necessary, please do contact the author at help@wp-issues-crm.com 
-- we welcome feedback and do want to know how we can continue to improve this product.  

  
== Screenshots ==
1. Offers an email client integrated with your CRM, facilitating think-only-once replies to repetitive incoming email campaigns.
2. Parses personal identification and address data from emails and allows you to automatically record and reply to them.
3. Clear, transparent interface to manage incoming and outgoing email.
4. Offers quick send of outgoing email to any list of constituents retrieved from the CRM (WYSIWIG editor).
5. Upload facility offers a clean drag-and-drop interface for mapping fields.
6. Upload facility validates data in a transparent way.
7. Upload facility gives full control over matching and deduping.
8. Powerful search capability that gives quick response over large constituent databases.
9. A clean tabbed settings interface.
10. Create your own custom fields and define options for the custom dropdowns that you create.  
11. Customize options for built-in fields like activity-type.  
12. The constituent/update add screen is simple and user friendly.
13. You can create new "Issues" -- these are just Wordpress posts, but are created as private.  You can convert them to 
public posts at any time and edit through the regular Wordpress editor.  Issues are used to classify activities for constituents.
14. Activities pertain to both constituents and issues and can be added from either form.
15. The dashboard highlights activity and assigned cases.
16. The Manage Storage function allows you to safely purge outdated data from external sources.
17. The Interface Manager allows you to acquire data from front end forms.
18. Mapping form fields into the WP Issues CRM database is easy with the clean graphical map interface.
== Email Automation ==
* Solves the repetitive incoming email problem that major email clients do not solve with typical "conversation view"
* Groups repetitive campaign emails for bulk handling using reply standards you define
* Automatically parses emails to extract street address and other essential information
* Logs emails, creates new constituent records and generates your standard replies -- one click for multiple incoming emails
* Handles many variations of address format, achieving successful parsing for most incoming bulk traffic.
* Also facilitates quick turn around of bulk responses using your favorite outgoing snail mail or email list tool.
* Generate email messages to retrieved constituent lists
* Autoreply to repetitive incoming email based on trained subject lines
== Form Interfaces ==
* Capture constituent and transaction data directly into WP Issues CRM from popular form plugins
* Eliminate duplicate data entry for case management
* Use a powerful backend database to track and support your online fundraising
* Allow new post creation on your front facing site without a login.  You can allow anonymous posting or require users to identify themselves. Use the spam controls built into popular form tools.
== Powerful, Transparent Upload Facility ==
WP Issues CRM now includes a flexible upload subsystem. The upload subsystem is designed to handle large uploads as in an initial setup and also to support frequent smaller uploads to reduce manual data entry.
== Upload Features == 
* Handles common file .csv and txt file formats
* Learns your the field mappings for your repetitive file uploads
* Validates data transparently so that you can fix problems as they emerge
* Allows you to easily control the matching/deduping strategy and to test alternative approaches before finalizing your upload
* Allows you to add default data for an upload -- so, for example, you can upload a list and identify all on the list as having attended signed a petition related to an issue
* Automatically breaks every task and the final upload process into chunks to minimize memory and packet sizes and avoid exceeding system limits
* Allows you to download files documenting the results of your upload to allow, for example, the manual completion of records that failed in the upload
* Allows you to automatically backout some types of uploads
== Advanced Search ==
* Includes powerful general search facility for selecting groups of constituents and activities with complex definitions
* Download or review online with infinite scroll.
== Storage Mangement ==
Includes a facility to show storage usage and to selectively purge interim files and dated external data.  So, for example, suppose you initially uploaded your database from a voter list.  Over time, you added information about contacts with voters.  You could then easily purge all voters with no contacts and add a fresh voter list, matching to the voters that you kept to avoid duplication.
== Design of WP Issues CRM ==
WP Issues CRM uses a fully modular object-oriented design.  It is built around a data dictionary so that it is fundamentally flexible.  It uses code recursively so that with a small code base it can offer broadly extensible functionality.  Since version 3.0, it optimizes the balance of functions between client and server.  We use this product ourselves on a daily basis and we are committed to continuous long-term improvement of it.


== Changelog ==
= 4.5.5 = 
* NOTE: THIS IS THE LAST INTENDED RELEASE OF THIS PLUG-IN IN THIS PLATFORM.  IF YOU NEED ASSISTANCE IN MIGRATION, CONTACT 617-771-8274.
* Add cron lite version to allow mail jobs to be run from command line as continuous webjob in azure
* Eliminate settings of max_execution_time when web job running
* Set non-zero date values in tables loaded on installation to prevent InnoDB errors
= 4.5.4.1 = 
* Eliminate verbose mail and error log messages
* Load dictionary early in case of ALTERNATE_WP_CRON setting
= 4.5.4 
* Change SQL engine to default (InnoDB) instead of MyISAM (note this change does not self implement on existing installations and requires MYSQL 5.6.4)
* Add option to define LOCAL_MAX_PACKET_SIZE in case working in environment where it is not acceessible through show variables 
= 4.5.3.11
* Handle Office 365 IMAP non-standard behavior (return 0 instead of false on bad num_msg calld)
= 4.5.3.10
* Add diagnostic traces to support Office 365 IMAP synch issues resolution
= 4.5.3.9 = 
* Clean up to/reply names before offering to client for email reply use
= 4.5.3.8 = 
* Add internet headers to standard stored email object (make available for add-on local spam processing)
= 4.5.3.7 =
* Fix bug -- vestigial assignment of case on send of email
* Add logging for unsent messages even when not in verbose mode in ActiveSync
* Mark preferred PHP version as 7.3
= 4.5.3.6 =
* CSS changes to preserve look as WP changes css
= 4.5.3.5 =
* Convert all links in incoming email to open in new window on parse 
= 4.5.3.4 =
* Modify css and js to expand click target area for delete checkbox in inbox
= 4.5.3.3 =
* Display 'deleted user' if missing user for activity popup
= 4.5.3.2 =
* Expose last_updated_by, last_updated_time in activity popup
= 4.5.3.1 = 
* Sort issues by title in issue lists
= 4.5.3 = 
* Save state of dashboard to facilitate iterative select from dashboard and return to dashboard
= 4.5.2 =
* Revise security rules for template and issue creation from email -- allow to those with view_edit_unassigned
* Revise dashboard issues and cases functions to handle all/any/empty cases more consistently
= 4.5.1.4 =
* Eliminate false offer to send attachments on list send
= 4.5.1.3 =
* Settings documentation update
= 4.5.1.2 =
* Additional sanitization and css changes
= 4.5.1.1 =
* Fixes related to wild card handling and empty team list
= 4.5.1.0 =
* Revise handling of CATEGORY_TEAM -- based it on new email control Tab, rather than over inclusive user table approach
* Add additional email security construct -- send_email to allow more granular division of email responsibility
* Add hook to allow consolidation of tabs retroactively
* Workaround wordpress update_option bug for mailer hold
* Fix bug in computation of open unassigned cases on dashboard
= 4.5.0.2 =
* Alter function to support less than full load of Wordpress in cron environment
= 4.5.0.1 =
* New features to support office division of labor between drafters and approvers of email
* Add new dashboard work flow status widget
* Automatically save draft replies, constituent assignments and issue assignments in inbox 
* Add new approval button and tabs ("Assigned and Ready")
* Add new level of security segmentation by assignment of case/constituent, issue or email to users
* Redefine email capability so that those without it can access emails assigned to them -- inbox tabs and ui powers limited
* Rewrite security logic for clarity and to prevent cross-user violations of new rules
* NOTE: Non-administrators may need to add the capability to view unassigned records to their role in Configure > security
= 4.4.1.4 =
* Additional table locking
= 4.4.1.3 =
* Bug fix -- correct MYSQL
= 4.4.1.2 =
* Bug fix -- adding necessary tables to lock
= 4.4.1.1 =
* Further modify locking behavior in ActiveSync inbox synch
= 4.4.1 =
* Modify locking behavior in ActiveSync inbox synch to prevent process conflicts in overlapping cron runs for large inboxes
* Additional logging and timeouts in central cron run control
* In multisite installations when central cron control is enabled, limit to network administrators the authority to initiate online inbox resynchronization
* Resequence initial plugin load to minimize path to starting cron runs 
= 4.4.0.3 =
* Add missing close </em> tag in inbox form
= 4.4.0.2 =
* Change Dear behavior to use First name initial caps format
= 4.4.0.1 =
* SVN add tinymce plugin displaying "Dear Token".
= 4.4.0 =
* Add salutation field for constituents, make it uploadable
* Add "Dear Token" to allow repetitive reply and mail merge with salutation, first name or omitted
* Fix over length column name error for custom fields with long labels in downloads
= 4.3.2.5 =
* Further revise handling of collation in new issue creation in upload -- use binary to avoid unpredictable collation incompatibilities
= 4.3.2.4 =
* Fix logic error in auto-reply to non-constituents (would treat some bad-parsed as non-constituents even when control setting required good-parse as non-constituents for auto-reply )
= 4.3.2.3 =
* Remove error causing definition of collation in post_title comparison in upload-set-defaults.php
* Set default value for constituent_having_aggregator in advanced_search (so, preventing blank value submission)
* Cover case of undefined display date in transition line for reply messages
* Fix naming error that prevented missing google maps API key from being handled properly
* Fix style conflict with wp admin style  often-enqueued by other plugings (/wp-includes/css/jquery-ui-dialog.css) 
= 4.3.2.2 =
* Trap expired credentials in gmail deliver attempt and log in mail log, not error log
= 4.3.2.1 =
* Fix error -- prevent possibility of looking for non-instantiated entity in option-group display
= 4.3.2 =
* Avoid notices in activity listing by checking for existing of related inbox and outbox records
* Protect against possible message size and mapping history issues in md5 issue mapping
= 4.3.1 =
* Simplify marking of open and overdue assigned cases/issues -- show regardless of logged in user
* Open and close dashboard windows on clicks of input fields as well as drag bars
* Restore appearance of pro/con code on saved reply list
* Limit md5 analysis of message content to sentences mapped to less than 20 different issues
= 4.3.0 =
* Add drawing and point selection to Google maps api -- allow download of and email to selected points
* Allow save and retrieval of drawn selection shapes associated with issues and searches
* Add autosave logic to compose email window and form dirty logic to both compose window and message review/reply window
* Simplify dashboard layout
* Maximize screen display for email inbox interaction; color facelift for email interaction
= 4.2.3 =
* Handle undefined conditions in search_log formatter and single address geocoder
* Fix count logic in options form
* Handle expired nonce for attachments in activity notes
* Add link to original message when viewing in activity notes
= 4.2.2 =
* Add hook to allow local installations to pre-filter subject
= 4.2.1 =
* Move google map to single instance reused; add all map operating controls as google map controls
* Cleanup dashboard layout
= 4.2.0.1 =
* Fix multisite issue in google oauth login
* Fix multisite issue in staff lookup
* Add pipe separated option for uploads
* Fix illegal collation issue in issue uploads and comment compares
= 4.2.0 =
* Add Google Maps and Geocodio interfaces to display found constituent lists on maps.
= 4.1.1 = 
* Remove inbox purge from manage storage purge logs action; add orphan attachment purge to inbox purge/resynch 
= 4.1.0 =
* Install plugin copy of phpmailer version 6.0.6 (higher than current version used by Wordpress, 5.2.10) to support Google Oauth2 login for gmail delivery
* Upgrade main delivery routine to support google oauth2 login to SMTP for delivery; alter settings dialogs accordingly
* Upgrade timing control for main delivery so that it can run every two minutes; remove option for hourly scheduling
* Always enable list sending -- since 4.0.5 subject to security screen
* Simplify main settings -- move list send limit to email-out tab, eliminating email-auto tab
* Add standard lookup label function (consolidating existing function)
* Upgrade character set on upload and sync intermediate files to utf8mb4 from utf8
* Fix bug -- do not trigger autosave of tinymce editor where no editor on sweep
* Allow definition of USPS interface in config.php to support multisite installation
= 4.0.5 =
* Add a security setting for list send access
* Review and document security structure in code
* Adjust setting messages
* Add nonce checking for attachment downloads
= 4.0.4 = 
* Allow gmail api synch without clearing activesynch email address
* Clean 'capability level' option definitions and add a security setting for email access
= 4.0.3 = 
* replace WP's sanitize_file_name function with one that does not required logged in user and does not screen for mime types (but is stricter on character set)
= 4.0.2 =
* Update TinyMCE package to version 4.9.2
* Add print button to TinyMCE instances
* Fix attachment download header -- files with commas in name blocked on download
* Eliminate use of javascript defaults to improve IE compatibility
* Fix mime_type lookup
* Eliminate empty setup query
* Handle missing value cases in form_option_group, build_from_id, apply_filter_block and activesync_attachment
* Add cron return code handling to allow mixing of read/send accounts types in cron rotation
= 4.0.1RC4 =
* Add alert that Internet Explorer is unsupported; Chrome preferred
= 4.0.1RC3 =
* Move collation check so accessible for cron
* Create cron connection cache to avoid unnecessary autodiscovery
= 4.0.1RC2 =
* Attachment handling fix for forwarded messages
* New manual button
* Avoid displaying errors when assigned staff deleted for issues
* Avoid displaying errors when new activities are created for shell constituents (email only)
* Clean up test files erroneously included
* Block saving of php open tags
* Improve charset handling -- handle php dom feature/bug that treats unknown html as ISO-8859
= 4.0.1RC1 =
* Bulletproofing and fix for check_connection
* Fix for staff lookup in multiuser context
* Add dictionary to cron run startup 
= 4.0.0RC9 =
* Deprecating support for Ninja Form interface
* Make corrections necessary for php 7.2
= 4.0.0RC8 =
* Make necessary tweaks to allow php 7
= 4.0.0RC7 =
* Fix update sequence for message threading display
* Add multisite control parameters from max messages and max connect retries
= 4.0.0RC6 =
* Fix click target def in inbox tabs to reflect count span
* Fix treatment of utf-8 characters that are html entities (show as entities)
* Use body, not whole html docs to avoid doc tag insertion
= 4.0.0RC5 =
* Fix possible overinclusion of subject lines in checkbox for delete
* Fix missing out loop for cron control
* Add additional timing parameter for cron control
= 4.0.0RC4 =
* Add mechanism for cron tab control in ActiveSync multisite installtion
* Move mail log messages to own log
* Fix regex failure point in stripping of scripts, etc. from incoming html
= 4.0.0RC3 =
* Fix: Restore missing click listener for activity downloads
* Add warning that not PHP7 compatible
* Layout tweaks
= 4.0.0RC2 =
* Fixes:
* Message formatting -- div block instead of p block
* Eliminate change delay for tinymce
* Add error trapping for bad call to UID reservation
= 4.0.0RC1 =
* NOTE:  After Upgrade, go to the mail box "Synch" tab and "Reset Parse" to see inbox properly.
* Major pre-release -- strong beta
* Adds support for ActiveSync for both incoming and outgoing messages through an Exchange server
* Adds support for Gmail API with OAUTH security for incoming gmail messages
* Clarifies structure for adding additional incoming and outgoing email interfaces
* Rounds out functionality of mail client
* Many other smaller improvements
= 3.8.1.1 =
* Strip doctype tags from incoming emails.
= 3.8.1 =
* Release offers significant new compose capabilities in the email suite
* Also includes miscellaneous user interface improvements and bug fixes
* Published numbering skips because of internal releases while testing
= 3.5.2.4 = 
* Restrict activity_note export to first 1000 characters to avoid blowing up Excel
= 3.5.2.3 =
* Fix to export sql to avoid possible duplicate field names
= 3.5.2.2 =
* Fix is_changed logic bug 
= 3.5.2.1 =
* Add progress bar for uploads and check settings to maximize file size
= 3.5.2 =
* Adds ability to upload documents for constituents and issues as a new activity type
= 3.5.1.5 =
* Do not send attachments and exclude inline images from reply messages
= 3.5.1.4 =
* Bullet proofing and fixes to incoming email message structure processing
= 3.5.1.3 =
* Fix handling of inline base64 encoded images
* Trigger refresh on selectmenu change in dashboard
= 3.5.1.2 =
* Add user/status/date selection fields to issues and cases widgets on dashboard
= 3.5.1.1 =
* Additional bullet proofing for parse cases -- non_names, failure to save rule changes
= 3.5.1 =
* Review address parsing algorithm -- handle new cases to achieve highest possible parse quality across incoming email campaigns
= 3.5.0.2 =
* Improve bullet-proofing of parse algorithm against unusual white space character usage in incoming emails
= 3.5.0.1 =
* Avoid evaluation of multisite constant in non-multi site installations
= 3.5 =
* Add new functionality -- standard set of registration fields for installations supporting voter or resident databases
* Add new functionality -- ability to assign districts to secondary sites in multi-user installation (see documentation under "Data Owners" menu item visible to network administrators in multi-site installations)
* Add new functionality -- ability to synchronize data between secondary sites with primary site's copy (see documentation under "Synch Data" menu item visible to administrators of secondary sites after network administrators configure their district).
* Add new functionality -- settings panel for administrators showing multisite configuration status
* Reposition settings access for clarity
* Eliminate extra error popup message on form errors
* Relocate and encrypt saved mail passwords
= 3.4.3 =
* Add new functionality -- special autoreplies to trained message based on geography of parsed address (for non-constituents)
* Add new bulk maintenance functions -- delete or reassign activities; delete found constituents from advanced search (administrators only)
* Fix CSS scroll for subject list
* Prevent attempts to send email where email addresses is found but blank
= 3.4.2.7 =
* Improve error handling to eliminate spurious errors when new page loaded while ajax request pending
= 3.4.2.6 =
* Fix incompatibility with wpdb->prepare created by Wordpress 4.8.2 security patch (previously supported unsigned format)
= 3.4.2.5 =
* Do not issue error message when pending tasks deliberately aborted
= 3.4.2.4 =
* Fix activation sequence error
= 3.4.2.3 =
* Fix error in activity table install statement
= 3.4.2.2 =
* Fix activity list count
= 3.4.2.1 =
* Code to handle over-long groups of UIDs or options ( work around group_concat length limits )
* Improved error differentiation in subsidiary ajax post handler
= 3.4.2 =
* Bug fixes in send function for lists
* Improved error differentiation in main form button post ajax handler
= 3.4.1 =
* Simplify activity issue drop down settings -- requires issue to be set as "Always Appear" to show in drop down
* Tighten selection logic in auto email selection to reduce probability of spurious return email choice
* Add facility to allow reparsing after parse setting changes
= 3.4.0.3 =
* Bullet proof email sender timezone logic
= 3.4.0.2 =
* Remove reference to vestigial field from version 3.3
= 3.4.0.1 =
* Specify error if hit browser cache page size limit (most likely in Firefox)
= 3.4 =
* Full rewrite of the email client -- see https://wp-issues-crm.com/understanding-wp-issues-crm-email-in-version-3-4-and-above/
* Add streamlined incoming message delete and one-click blocking of unwanted incoming traffic
* Add tinymce -- WYSIWIG editor -- for replies
* Expose full incoming message addressee structure and attachments in reply user interface
* Create user interface for review of sent/done messages and saved reply standards
* Rewrite email reply automation logic -- simplify and streamline both the code and the user interface
* Integrate sentence content engine from version 3.3 Beta with subject line mapping to create a single reliable and understandable reply suggestion process
* Remove word content suggestion engine tested in version 3.3 Beta
* Replace outgoing automation tokens with simpler header/footer approach
* New dropdown control type integrating searching among dropdown options ( to support constituent and issue dropdowns without using jQuery UI autocomplete, which is incompatible with tinymce )
* Simplify settings and preferences -- only remaining configurable user preference is Email Signature
* Deprecate token substitution in outgoing email -- the new data structure and the availability of WYSISWYG editor makes tokens of marginal value. Tokens will be fully obsolesced in version 3.5.
= 3.3 Beta (experimental, released only for beta use) =
* Add new suggestion engine for email replies based on sentence content of emails
* Add new suggestion engine for email replies based on word content of emails
* Miscellaneous work flow improvements including cc of issue or case assigned staff on emails
= 3.2.3 =
* Add transition rule for inbox_detail display
= 3.2.2 =
* Show html message version (if available) in message detail display
* Show sanitized inline images in html version
* Fix bug that could interrupt parse cycle
* Bullet proof parse cycle loop against database failures
* Eliminate unnecessary connections in synch cycle (where no work to do)
* UI tweaks for ease of viewing inbox
= 3.2.1 =
* Revise synchronization process for efficiency and to meet small server time limits
* Fix bug in folder selection process that could lead to mis-assignment of folders
* Bullet-proof all inbox processing against blank folder errors
* Add settings information regarding server resource requirements
= 3.2 =
* Improve speed and consistency of email inbox response time by creating and synchronizing inbox image
* Accelerate workflow by adding skip and delete options in email subject line view
* Add synch monitoring function for inbox image 
* Add notice to user of necessary email settings for parsing
= 3.1.2 =
* Strengthen defensive code for form plugin-not-installed conditions
= 3.1.1 =
* Adds new interface functionality -- supports form submissions from Gravity Forms, Contact Form 7 and Ninja Forms
= 3.1 (released only to beta users ) =
* Added new interface functionality in prototype mode
= 3.0.2 =
* Fix count in upload progress message
* Eliminate spurious calls to upload_default_status on change of html5 input for uploader
* Fix issue autocomplete logic in upload default step to prevent cases where no issue set
* Change default value for type fields in upload processing to empty string, consistent with UI form (data def change)
* Hide "titles mapped" message in cases where all titles validated OK; hide activity default group when all activity fields mapped
* Fix infinite scroll in form search case -- move binding from ready to form init 
* Fix track/close behavior of tooltips on draggables in upload map
= 3.0.1 = 
* Replace MySQL version-checking function not compatible with PHP Version 5.5.
= 3.0 = 
* Complete rebuild to reallocate functions between client and server.
* All functions reviewed and streamlined from both a code and user interface perspective.
== Upgrade Notice ==
= 3.4 =
This upgrade includes major improvements to the automated email reply work flow.  If you are already using WP Issues CRM outgoing email automation, let your outgoing email queue empty before doing this upgrade. Read more at https://wp-issues-crm.com/understanding-wp-issues-crm-email-in-version-3-4-and-above/    



	  	
