INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time ) VALUES
( 'capability_levels', 'edit_posts', 'Contributors (edit_posts)', 50, 1, 0, '2000-01-01 01:01:01'),
( 'capability_levels', 'read', 'Subscribers (read)', 60, 1, 0, '2000-01-01 01:01:01'),
( 'address_type_options', 'wic_reserved_4', 'Registered', 50, 1, 0, '2000-01-01 01:01:01');
UPDATE wp_wic_option_value set option_value = 'edit_theme_options', option_label = 'Administrator (edit_theme options)' WHERE parent_option_group_slug = 'capability_levels' and option_value = 'activate_plugins';
UPDATE wp_wic_option_value set option_value = 'publish_posts', option_label = 'Author (publish_posts)' WHERE parent_option_group_slug = 'capability_levels' and left(option_label, 5) ='Autho';