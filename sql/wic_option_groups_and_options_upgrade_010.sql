INSERT INTO wp_wic_option_group ( option_group_slug, option_group_desc, enabled, is_system_reserved, last_updated_time) VALUES
( 'constituent_interface_options', 'Constituent Field Interface Options', 1, 1, '2000-01-01 01:01:01'),
( 'front_end_post_options', 'Front End Post Creation Interface Options', 1, 1, '2000-01-01 01:01:01'),
( 'front_end_post_initial_status_options', 'Front End Post Initial Status Options', 1, 1, '2000-01-01 01:01:01'),
( 'multivalue_interface_options', 'Multivalue Field Interface Options', 1, 1, '2000-01-01 01:01:01');
INSERT INTO wp_wic_option_value ( parent_option_group_slug, option_value, option_label, value_order, enabled, is_system_reserved, last_updated_time) VALUES
('constituent_interface_options','never','Never update',10,1,0, '2000-01-01 01:01:01'),
('constituent_interface_options','blank_existing','Update only if blank on existing record',20,1,0, '2000-01-01 01:01:01'),
('constituent_interface_options','non_blank_incoming','Update only if non-blank on incoming record',30,1,0, '2000-01-01 01:01:01'),
('constituent_interface_options','always','Always update',40,1,0, '2000-01-01 01:01:01'),
('front_end_post_options','never','Do not create new posts or users',10,1,0, '2000-01-01 01:01:01'),
('front_end_post_options','match','Create new posts if email matches existing Wordpress user',20,1,0, '2000-01-01 01:01:01'),
('front_end_post_options','add','Create new posts, add Wordpress user if no match for user',30,1,0, '2000-01-01 01:01:01'),
('front_end_post_initial_status_options','draft','Draft',10,1,0, '2000-01-01 01:01:01'),
('front_end_post_initial_status_options','pending','Pending Approval',20,1,0, '2000-01-01 01:01:01'),
('front_end_post_initial_status_options','private','Private',30,1,0, '2000-01-01 01:01:01'),
('front_end_post_initial_status_options','publish','Published',40,1,0, '2000-01-01 01:01:01'),
('multivalue_interface_options','never','Never add or update',10,1,0, '2000-01-01 01:01:01'),
('multivalue_interface_options','always','Always add as new if not equal to an existing value (regardless of type) ',20,1,0, '2000-01-01 01:01:01'),
('multivalue_interface_options','update','Update if matching type and add for new type',30,1,0, '2000-01-01 01:01:01'),
('multivalue_interface_options','update_test_match','Update if matching type except if match value on first five characters and add for new type',40,1,0, '2000-01-01 01:01:01');