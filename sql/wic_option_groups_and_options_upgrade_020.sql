INSERT INTO wp_wic_option_group ( option_group_slug, option_group_desc, enabled, is_system_reserved, last_updated_time) VALUES
( 'read_account_options', 'Read Account Options', 1,  1, '2000-01-01 01:01:01'),
( 'send_account_options', 'Send Account Options', 1,  1, '2000-01-01 01:01:01');
INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time ) VALUES
( 'read_account_options', 'exchange', 'Exchange Web Server -- ActiveSync', 10, 1, 0, '2000-01-01 01:01:01'),
( 'read_account_options', 'gmail', 'Gmail -- Gmail API', 20, 1, 0, '2000-01-01 01:01:01'),
( 'read_account_options', 'legacy', 'Any account configured in main settings', 30, 1, 0, '2000-01-01 01:01:01'),
( 'send_account_options', 'exchange', 'Exchange Web Server -- ActiveSync', 10, 1, 0, '2000-01-01 01:01:01'),
( 'send_account_options', 'legacy', 'Any account configured in main settings', 20, 1, 0, '2000-01-01 01:01:01');