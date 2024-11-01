INSERT INTO wp_wic_option_group ( option_group_slug, option_group_desc, enabled, is_system_reserved, last_updated_time ) VALUES
( 'charset_options', 'Character Set Options for Upload Files', 1, 1, '2000-01-01 01:01:01');
INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time) VALUES
('charset_options','UTF-8','ASCII, UTF-8 or Unknown',10,1,0, '2000-01-01 01:01:01'),
('charset_options','ISO-8859-1','Western European (ISO-8859-1)',20,1,0, '2000-01-01 01:01:01'),
('charset_options','WINDOWS-1252','ANSI (WINDOWS-1252)',30,1,0, '2000-01-01 01:01:01'),
('charset_options','MAC','MAC',40,1,0, '2000-01-01 01:01:01');