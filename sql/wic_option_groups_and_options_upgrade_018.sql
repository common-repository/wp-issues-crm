INSERT INTO wp_wic_inbox_image_attachments_xref ( attachment_id,attachment_md5,message_in_outbox,message_id,message_attachment_cid,message_attachment_number,message_attachment_filename,message_attachment_disposition )
SELECT ID,md5(attachment),0,message_id,'',attachment_number, attachment_filename, 'attachment' FROM wp_wic_inbox_image_attachments;
UPDATE wp_wic_option_value SET option_label = 'Email In' WHERE option_value = 'wic_reserved_00000000'; 
UPDATE wp_wic_option_value SET option_label = 'Email Out' WHERE option_value = 'wic_reserved_99999999';