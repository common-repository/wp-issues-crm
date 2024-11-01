INSERT INTO wp_wic_option_group ( option_group_slug, option_group_desc, enabled, is_system_reserved, last_updated_time) VALUES
( 'smtp_send_tool_options', 'smtp_send_tool_options', 1, 1, '2000-01-01 01:01:01'),
( 'smtp_secure_options', 'smtp_secure_options', 1, 1, '2000-01-01 01:01:01'),
( 'require_good_ssl_certificate_options', 'require_good_ssl_certificate_options', 1, 1, '2000-01-01 01:01:01'),
( 'smtp_debug_level_options', 'smtp_debug_level_options', 1, 1, '2000-01-01 01:01:01'),
( 'use_IPV4_options', 'smtp_debug_level_options', 1, 1, '2000-01-01 01:01:01');
INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time) VALUES
( 'smtp_send_tool_options', 'smtp', 'Use SMTP -- most flexible, slower, may offer best deliverability', 10, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_send_tool_options', 'mail', 'Use PHP mailer (ignores settings below) -- safe only for low volume', 20, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_send_tool_options', 'sendmail', 'Use Sendmail (ignores settings below) -- may be fastest, but may have low deliverability', 30, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_secure_options', 'tls', 'Use TLS (recommended) -- usually works on port 587, possibly on port 25', 10, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_secure_options', 'ssl', 'Use SSL (older version of TLS) -- try port 465', 20, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_secure_options', 'not', 'Insecure server connection (undesirable, use only if secure not available -- usually port 25', 30, 1,  0, '2000-01-01 01:01:01'),
( 'require_good_ssl_certificate_options', 'strict', 'Require third-party TLS/SSL certificate of site identity', 10, 1,  0, '2000-01-01 01:01:01'),
( 'require_good_ssl_certificate_options', 'selfOK', 'Accept self-signed TLS/SSL certificate', 20, 1,  0, '2000-01-01 01:01:01'),
( 'require_good_ssl_certificate_options', 'no', 'Do not validate TLS/SSL certificate', 30, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_debug_level_options', '1', 'Show commands', 10, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_debug_level_options', '2', 'Show commands and data', 20, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_debug_level_options', '3', 'Show commands, data and connection status', 30, 1,  0, '2000-01-01 01:01:01'),
( 'smtp_debug_level_options', '4', 'Show full server connection dialog', 40, 1,  0, '2000-01-01 01:01:01'),
( 'use_IPV4_options', 'no', 'Do not force IPV4 -- usually correct', 10, 1,  0, '2000-01-01 01:01:01'),
( 'use_IPV4_options', 'yes', 'Force IPV4 -- if troubleshooting suggests it', 20, 1,  0, '2000-01-01 01:01:01'),
( 'activity_type_options', 'wic_reserved_99999998', 'Queued email', 9998, 1,  1, '2000-01-01 01:01:01'),
( 'activity_type_options', 'wic_reserved_99999999', 'Email autosent', 9999, 1, 1, '2000-01-01 01:01:01');