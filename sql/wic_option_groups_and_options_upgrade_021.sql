DELETE FROM wp_wic_option_value WHERE parent_option_group_slug = 'capability_levels';
INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time ) VALUES
( 'capability_levels', 'edit_theme_options', 'Administrators (edit_theme_options)', 10, 1, 0 , '2000-01-01 01:01:01'),
( 'capability_levels', 'edit_others_posts', 'Editors and above (edit_others_posts)', 20, 1, 0 , '2000-01-01 01:01:01'),
( 'capability_levels', 'publish_posts', 'Authors and above (publish_posts)', 30, 1, 0 , '2000-01-01 01:01:01'),
( 'capability_levels', 'edit_posts', 'Contributors and above (edit_posts)', 40, 1, 0, '2000-01-01 01:01:01'),
( 'capability_levels', 'read', 'Subscribers and above (read)', 50, 1, 0, '2000-01-01 01:01:01'),
( 'capability_levels', 'manage_wic_constituents', 'Only Constituent Managers and Administrators', 60, 1, 0, '2000-01-01 01:01:01');