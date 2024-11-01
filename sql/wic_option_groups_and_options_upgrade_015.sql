INSERT INTO wp_wic_option_group ( option_group_slug, option_group_desc, enabled, is_system_reserved, last_updated_time) VALUES
( 'is_my_constituent_options', 'Is My Constituent Options', 1,  1, '2000-01-01 01:01:01'),
( 'use_is_my_constituent_rule_options', 'Is My Constituent Rule Enabled', 1,  1, '2000-01-01 01:01:01'),
( 'use_non_constituent_responder_options', 'Non-constituent Response Enabled', 1,  1, '2000-01-01 01:01:01');
INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time ) VALUES
( 'is_my_constituent_options', '', 'Unknown', 10, 1, 0, '2000-01-01 01:01:01'),
( 'is_my_constituent_options', 'Y', 'Yes', 20, 1, 0, '2000-01-01 01:01:01'),
( 'is_my_constituent_options', 'N', 'No', 30, 1, 0, '2000-01-01 01:01:01'),
( 'use_is_my_constituent_rule_options', 'N', 'Disabled: Save new constituents from incoming emails without setting "My Constituent" value', 10, 1, 0, '2000-01-01 01:01:01'),
( 'use_is_my_constituent_rule_options', 'Y', 'Use geography below to identify constituents when receiving incoming emails', 20, 1, 0, '2000-01-01 01:01:01'),
( 'use_non_constituent_responder_options', '1', 'Disabled: Never send standard non-constituent reply', 10, 1, 0, '2000-01-01 01:01:01'),
( 'use_non_constituent_responder_options', '2', 'Send standard non-constituent reply only to those successfully parsed as non-constituents', 20, 1, 0, '2000-01-01 01:01:01'),
( 'use_non_constituent_responder_options', '3', 'Send standard non-constituent reply to all those not successfully parsed as constituents', 30, 1, 0, '2000-01-01 01:01:01');