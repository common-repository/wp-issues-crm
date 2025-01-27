CREATE TABLE wp_wic_activity (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
constituent_id bigint(20) unsigned NOT NULL,
activity_date varchar(10) NOT NULL,
activity_type varchar(254) NOT NULL,
activity_amount decimal(20,2) NOT NULL,
issue bigint(20) NOT NULL COMMENT 'post_id for associated issue',
pro_con varchar(255) NOT NULL,
activity_note LONGTEXT NOT NULL,
file_name varchar(254) NOT NULL,
file_size bigint(20) NOT NULL,
file_content LONGBLOB NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
email_batch_originated_constituent tinyint(1) NOT NULL,
related_inbox_image_record bigint(20) NOT NULL,
related_outbox_record bigint(20) NOT NULL,
upload_id bigint(20) NOT NULL,
PRIMARY KEY  (ID),
KEY constituent_id (constituent_id),
KEY activity_type (activity_type(180)),
KEY activity_date (activity_date),
KEY file_name (file_name(180)),
KEY last_updated_time (last_updated_time),
KEY last_updated_by (last_updated_by),
KEY upload_id (upload_id),
KEY related_inbox_image_record (related_inbox_image_record),
KEY related_outbox_record (related_outbox_record)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_address (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
constituent_id bigint(20) unsigned NOT NULL,
address_type varchar(254) NOT NULL,
address_line varchar(250) NOT NULL,
city varchar(250) NOT NULL,
state varchar(20) NOT NULL,
zip varchar(10) NOT NULL,
lat decimal(10, 8) NOT NULL,
lon decimal(11, 8) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
PRIMARY KEY  (ID),
KEY zip (zip),
KEY address_line (address_line),
KEY constituent_id (constituent_id),
KEY city (city),
KEY state (state),
KEY full_address (address_line(50),city(50),state(5),zip(10)),
KEY last_updated_time (last_updated_time),
KEY last_updated_by (last_updated_by),
KEY lat (lat),
KEY lon (lon)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_address_geocode_cache (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
address_raw varchar(250) NOT NULL,
city_raw varchar(250) NOT NULL,
state_raw varchar(20) NOT NULL,
zip_raw varchar(10) NOT NULL,
lat decimal(10, 8) NOT NULL,
lon decimal(11, 8) NOT NULL,
PRIMARY KEY  (ID),
KEY zip_raw (zip_raw),
KEY address_raw (address_raw),
KEY city (city_raw),
KEY state (state_raw),
KEY full_address (address_raw(50),city_raw(50),state_raw(5),zip_raw(10)),
KEY lat (lat),
KEY lon (lon)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_constituent (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
last_name varchar(50) NOT NULL,
first_name varchar(50) NOT NULL,
middle_name varchar(50) NOT NULL,
salutation varchar(100) NOT NULL,
date_of_birth varchar(10) NOT NULL,
year_of_birth varchar(4) NOT NULL,
is_deceased tinyint(1) NOT NULL,
is_my_constituent varchar(1) NOT NULL,
mark_deleted varchar(7) NOT NULL,
case_assigned bigint(20) NOT NULL,
case_review_date varchar(10) NOT NULL,
case_status varchar(50) NOT NULL,
gender varchar(50) NOT NULL,
occupation varchar(255) NOT NULL,
employer varchar(255) NOT NULL,
registration_id varchar(255) NOT NULL,
registration_synch_status char(1) NOT NULL,
registration_date varchar(10) NOT NULL,
registration_status varchar(255) NOT NULL,
party varchar(255) NOT NULL,
ward varchar(255) NOT NULL,
precinct varchar(255) NOT NULL,
council_district varchar(255) NOT NULL,
state_rep_district varchar(255) NOT NULL,
state_senate_district varchar(255) NOT NULL,
congressional_district varchar(255) NOT NULL,
councilor_district varchar(255) NOT NULL,
county varchar(255) NOT NULL,
other_district_1 varchar(255) NOT NULL,
other_district_2 varchar(255) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
PRIMARY KEY  (ID),
KEY last_name (last_name),
KEY middle_name (middle_name),
KEY dob (date_of_birth),
KEY gender (gender),
KEY first_name (first_name),
KEY is_deceased (is_deceased),
KEY is_deleted (mark_deleted),
KEY assigned (case_assigned),
KEY case_review_date (case_review_date),
KEY case_status (case_status),
KEY fnln (last_name,first_name),
KEY last_updated_time (last_updated_time),
KEY last_updated_by (last_updated_by),
KEY registration_id (registration_id(180))
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_data_dictionary (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
entity_slug varchar(60) NOT NULL,
group_slug varchar(60) NOT NULL,
field_slug varchar(60) NOT NULL,
field_type varchar(30) NOT NULL,
field_label varchar(120) NOT NULL,
field_order mediumint(9) NOT NULL,
listing_order int(11) NOT NULL,
sort_clause_order mediumint(11) NOT NULL,
required varchar(10) NOT NULL,
dedup tinyint(1) NOT NULL,
readonly tinyint(1) NOT NULL,
hidden tinyint(1) NOT NULL,
field_default varchar(30) NOT NULL,
transient tinyint(1) NOT NULL,
wp_query_parameter varchar(30) NOT NULL,
placeholder varchar(50) NOT NULL,
option_group varchar(50) NOT NULL,
list_formatter varchar(50) NOT NULL,
reverse_sort tinyint(1) NOT NULL DEFAULT '0',
customizable tinyint(1) NOT NULL DEFAULT '0',
enabled tinyint(1) NOT NULL DEFAULT '1',
uploadable int(11) NOT NULL,
upload_dedup tinyint(1) NOT NULL,
last_updated_by bigint(20) NOT NULL,
last_updated_time datetime NOT NULL,
PRIMARY KEY  (ID),
KEY entity_slug (entity_slug),
KEY field_group (group_slug)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_email (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
constituent_id bigint(20) unsigned NOT NULL,
email_type varchar(254) NOT NULL,
email_address varchar(253) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
PRIMARY KEY  (ID),
KEY constituent_id (constituent_id),
KEY email_address (email_address(180)),
KEY email_type (email_type(180)),
KEY last_updated_time (last_updated_time),
KEY last_updated_by (last_updated_by)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_external (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
external_type varchar(255) NOT NULL,
external_identifier varchar(255) NOT NULL,
external_name varchar(255) NOT NULL,
enabled tinyint(1) NOT NULL,
serialized_field_map blob NOT NULL,
activity_type varchar(255) NOT NULL,
address_type varchar(255) NOT NULL,
phone_type varchar(255) NOT NULL,
email_type varchar(255) NOT NULL,
case_assigned bigint(20) NOT NULL,
case_status varchar(255) NOT NULL,
front_end_posts varchar(255) NOT NULL,
front_end_post_initial_status varchar(255) NOT NULL,
policy_identity varchar(255) NOT NULL,
policy_custom_data varchar(255) NOT NULL,
policy_phone varchar(255) NOT NULL,
policy_address varchar(255) NOT NULL,
policy_email varchar(255) NOT NULL,
issue bigint(20) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
PRIMARY KEY  (ID)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_form_field_groups (
ID bigint(20) NOT NULL AUTO_INCREMENT,
entity_slug varchar(60) NOT NULL,
group_slug varchar(60) NOT NULL,
group_label varchar(255) NOT NULL,
group_legend text NOT NULL,
group_order smallint(6) NOT NULL DEFAULT '0',
initial_open tinyint(1) NOT NULL,
sidebar_location tinyint(1) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
PRIMARY KEY  (ID)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_incoming_filter (
ID bigint(20) NOT NULL AUTO_INCREMENT,
from_email_box varchar(100) NOT NULL,
from_email_domain varchar(200) NOT NULL,
subject_first_filtered varchar(255) NOT NULL,
block_whole_domain tinyint(1) NOT NULL,
filtered_since datetime NOT NULL,
PRIMARY KEY  (ID),
KEY email_address_reverse (from_email_domain(100),from_email_box(80))
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_image (
ID bigint(20) NOT NULL AUTO_INCREMENT,
full_folder_string varchar(255) NOT NULL,
no_longer_in_server_folder tinyint(1) NOT NULL,
folder_uid bigint(20) NOT NULL,
to_be_moved_on_server tinyint(1) NOT NULL,
utc_time_stamp double NOT NULL,
from_personal varchar(255) NOT NULL,
from_email varchar(255) NOT NULL,
from_domain varchar(255) NOT NULL,
raw_date varchar(255) NOT NULL,
subject varchar(255) NOT NULL,
activity_date varchar(10) NOT NULL,
email_date_time varchar(20) NOT NULL,
serialized_email_object LONGTEXT NOT NULL,
guess_mapped_issue bigint(20) NOT NULL,
guess_mapped_pro_con varchar(255) NOT NULL,
guess_mapped_issue_confidence tinyint NOT NULL,
non_address_word_count int NOT NULL,
mapped_issue bigint(20) unsigned NOT NULL,
mapped_pro_con varchar(255) NOT NULL,
is_my_constituent_guess varchar(1) NOT NULL,
assigned_constituent bigint(20) NOT NULL,
parse_quality varchar(1) NOT NULL,
account_thread_id varchar(255) NOT NULL,
account_thread_latest varchar(20) NOT NULL,
snippet text NOT NULL,
category varchar(255) NOT NULL,
extended_message_id varchar(512) NOT NULL,
inbox_defined_staff bigint(20) NOT NULL,
inbox_defined_issue bigint(20) NOT NULL,
inbox_defined_pro_con varchar(255) NOT NULL,
inbox_defined_reply_text LONGTEXT NOT NULL,
inbox_defined_reply_is_final tinyint(1) NOT NULL,
PRIMARY KEY  (ID),
KEY in_folder (full_folder_string(100),no_longer_in_server_folder),
KEY folder_string_uid (full_folder_string(100),folder_uid),
KEY pending_subject (subject(100),no_longer_in_server_folder,to_be_moved_on_server),
KEY ready_to_move (full_folder_string(100),no_longer_in_server_folder,to_be_moved_on_server),
KEY from_email_key (from_email(100)),
KEY account_thread_id_key (account_thread_id(100)),
KEY extended_message_id_key (extended_message_id(100)),
KEY from_domain_key (from_domain(100)),
KEY staff_subject (inbox_defined_staff,subject(100)),
KEY staff_final_subject (inbox_defined_staff,inbox_defined_reply_is_final,subject(100))
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_image_attachments (
ID bigint(20) NOT NULL AUTO_INCREMENT,
message_id bigint(20) NOT NULL,
attachment_number varchar(255) NOT NULL,
attachment_filename varchar(255) NOT NULL,
attachment_type varchar(255) NOT NULL,
attachment_subtype varchar(255) NOT NULL,
attachment_size bigint(20) NOT NULL,
attachment_saved tinyint(1) NOT NULL,
attachment LONGBLOB NOT NULL,
PRIMARY KEY  (ID),
KEY message_id_attachment_number (message_id,attachment_number(100))
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_image_attachments_xref (
ID bigint(20) NOT NULL AUTO_INCREMENT,
attachment_id bigint(20) NOT NULL,
attachment_md5 char(32) NOT NULL,
message_in_outbox tinyint(1) NOT NULL,
message_id bigint(20) NOT NULL,
message_attachment_cid varchar(255) NOT NULL,
message_attachment_number varchar(255) NOT NULL,
message_attachment_filename varchar(255) NOT NULL,
message_attachment_disposition varchar(255) NOT NULL,
PRIMARY KEY  (ID),
KEY attachment_md5_key (attachment_md5),
KEY attachment_id_key (attachment_id,message_in_outbox,message_id),
KEY message_id_key_only (message_id),
KEY message_id_plus_key (message_in_outbox,message_id,attachment_id,message_attachment_number(80)),
KEY message_attachment_filename_key (message_attachment_filename(100))
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_md5 (
inbox_message_id bigint(20) NOT NULL,
message_sentence_md5 varchar(32) NOT NULL,
message_sentence_length SMALLINT NOT NULL,
KEY inbox_message_id (inbox_message_id),
KEY message_sentence_md5 (message_sentence_md5)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_md5_issue_map (
sentence_md5 varchar(32) NOT NULL,
sentence_length SMALLINT NOT NULL,
md5_mapped_issue bigint(20) NOT NULL,
md5_mapped_pro_con varchar(255) NOT NULL,
map_utc_time_stamp double NOT NULL,
KEY sentence_md5 (sentence_md5),
KEY map_utc_time_stamp (map_utc_time_stamp)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_inbox_synch_log (
ID bigint(20) NOT NULL AUTO_INCREMENT,
utc_time_stamp double NOT NULL,
full_folder_string varchar(255) NOT NULL,
num_msg bigint(20) NOT NULL,
count_on_temp_table bigint(20) NOT NULL,
count_new bigint(20) NOT NULL,
count_to_be_deleted bigint(20) NOT NULL,
count_deleted bigint(20) NOT NULL,
image_extra_uids bigint(20) NOT NULL,
count_image_mark_deleted bigint(20) NOT NULL,
synch_count bigint(20) NOT NULL,
incomplete_record_count bigint(20) NOT NULL,
pending_move_delete_count bigint(20) NOT NULL,
stamped_synch_count bigint(20) NOT NULL,
stamped_incomplete_record_count bigint(20) NOT NULL,
added_with_this_timestamp bigint(20) NOT NULL,
check_connection_time double NOT NULL,
synch_fetch_time double NOT NULL,
do_deletes_time double NOT NULL,
process_moves_time double NOT NULL,
PRIMARY KEY  (ID)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_interface (
upload_field_name varchar(255) NOT NULL,
matched_entity varchar(255) NOT NULL,
matched_field varchar(255) NOT NULL,
PRIMARY KEY  (upload_field_name(180))
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_option_group (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
option_group_slug varchar(60) NOT NULL,
option_group_desc varchar(100) NOT NULL,
enabled tinyint(1) NOT NULL DEFAULT '1',
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
is_system_reserved tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY  (ID)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_option_value (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
option_group_id varchar(50) NOT NULL,
parent_option_group_slug varchar(60) NOT NULL,
option_value varchar(50) NOT NULL,
option_label varchar(200) NOT NULL,
value_order smallint(11) NOT NULL,
enabled tinyint(1) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
is_system_reserved tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY  (ID),
KEY enabled (enabled,option_group_id,value_order)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_outbox (
ID bigint(20) NOT NULL AUTO_INCREMENT,
attempted_send_time_stamp bigint(20) NOT NULL,
sent_ok tinyint(1) NOT NULL,
held tinyint(1) NOT NULL,
is_draft tinyint(1) NOT NULL,
queued_date_time varchar(20) NOT NULL,
sent_date_time varchar(20) NOT NULL,
is_reply_to bigint(20) NOT NULL,
subject varchar(255) NOT NULL,
serialized_email_object LONGTEXT NOT NULL,
to_address_concat varchar(240) NOT NULL,
PRIMARY KEY  (ID),
KEY next_queued (sent_ok,queued_date_time),
KEY sent_date_time (sent_date_time),
KEY subject (subject(180)),
KEY to_address_concat_key (to_address_concat(180)),
KEY is_reply_to (is_reply_to)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_phone (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
constituent_id bigint(20) unsigned NOT NULL,
phone_type varchar(254) NOT NULL,
phone_number varchar(255) NOT NULL,
extension varchar(10) NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
PRIMARY KEY  (ID),
KEY constituent_id (constituent_id),
KEY phone_number_key (phone_number),
KEY phone_type_key (phone_type(180)),
KEY last_updated_time (last_updated_time),
KEY last_updated_by (last_updated_by)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_search_log (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
favorite tinyint(1) NOT NULL,
user_id bigint(20) NOT NULL,
search_time varchar(20) NOT NULL,
share_name varchar(20) NOT NULL,
is_named tinyint(1) NOT NULL,
entity varchar(30) NOT NULL,
serialized_search_array text NOT NULL,
download_time varchar(20) NOT NULL,
serialized_search_parameters blob NOT NULL,
serialized_shape_array blob NOT NULL,
result_count bigint(20) NOT NULL,
PRIMARY KEY  (ID),
KEY user_entity_time (user_id,entity,search_time),
KEY user_time (user_id,search_time),
KEY user_favorite_time (user_id,favorite,search_time),
KEY named_user_favorite_time (is_named,share_name,user_id,favorite,search_time)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_subject_issue_map (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
incoming_email_subject varchar(255) NOT NULL,
email_batch_time_stamp datetime NOT NULL,
mapped_issue bigint(20) unsigned NOT NULL,
mapped_pro_con varchar(255) NOT NULL,
PRIMARY KEY  (ID),
KEY subject_time (incoming_email_subject(150),email_batch_time_stamp),
KEY reverse_st (email_batch_time_stamp,incoming_email_subject(150)),
KEY stamp (email_batch_time_stamp)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_uid_reservation (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
uid bigint(20) unsigned NOT NULL,
time_stamp datetime NOT NULL,
reservation_time decimal(20,6) NOT NULL,
batch_subject varchar(255) NOT NULL,
PRIMARY KEY  (uid),
KEY internal_id (ID)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_upload (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
upload_time datetime NOT NULL,
upload_by bigint(20) NOT NULL,
upload_chunks mediumint NOT NULL,
upload_description varchar(255) NOT NULL,
upload_file varchar(255) NOT NULL,
upload_status varchar(255) NOT NULL,
serialized_upload_parameters blob NOT NULL,
serialized_column_map blob NOT NULL,
serialized_match_results blob NOT NULL,
serialized_default_decisions blob NOT NULL,
serialized_final_results blob NOT NULL,
last_updated_time datetime NOT NULL,
last_updated_by bigint(20) NOT NULL,
purged tinyint(1) NOT NULL,
PRIMARY KEY  (ID),
KEY upload_time_upload_by (upload_time,upload_by)
)  DEFAULT CHARSET=utf8mb4;
CREATE TABLE wp_wic_upload_temp (
ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
upload_id bigint(20) unsigned NOT NULL,
chunk_id mediumint unsigned NOT NULL,
chunk mediumblob NOT NULL,
PRIMARY KEY  (ID),
KEY upload_chunk (upload_id,chunk_id)
)  DEFAULT CHARSET=utf8mb4;