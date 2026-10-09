<?php

if( function_exists('acf_add_local_field_group') ):

acf_add_local_field_group(array(
	'key' => 'group_5e92632680fb4',
	'title' => 'Geolocation',
	'fields' => array(
		array(
			'key' => 'field_5e92632f2a260',
			'label' => 'Story Location',
			'name' => 'geom_point',
			'type' => 'google_map',
			'instructions' => 'The location of the story',
			'required' => 1,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'center_lat' => '',
			'center_lng' => '',
			'zoom' => '',
			'height' => 300,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'post',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'side',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
));

acf_add_local_field_group(array(
	'key' => 'group_5eb06df20825a',
	'title' => 'Location for Event',
	'fields' => array(
		array(
			'key' => 'field_5eb06e6f2ab4c',
			'label' => 'Location',
			'name' => 'geom_point',
			'type' => 'google_map',
			'instructions' => '',
			'required' => 1,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'center_lat' => '',
			'center_lng' => '',
			'zoom' => '',
			'height' => '',
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'tribe_events',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'side',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => array(
		0 => 'excerpt',
		1 => 'author',
	),
	'active' => true,
	'description' => 'Mark a stopping point',
));

acf_add_local_field_group(array(
	'key' => 'group_5e925a5a9cc4e',
	'title' => 'Metadata for attachments',
	'fields' => array(
		array(
			'key' => 'field_5e925ba8f263d',
			'label' => 'Original DateTime',
			'name' => 'datetime',
			'type' => 'date_time_picker',
			'instructions' => 'The original date time of the media content',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'display_format' => 'd/m/Y g:i a',
			'return_format' => 'd/m/Y g:i a',
			'first_day' => 1,
		),
		array(
			'key' => 'field_5e925c5c7f55b',
			'label' => 'Geolocation',
			'name' => 'geom_point',
			'type' => 'google_map',
			'instructions' => 'The location of the media content',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'center_lat' => '',
			'center_lng' => '',
			'zoom' => '',
			'height' => 500,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'attachment',
				'operator' => '==',
				'value' => 'all',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'side',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => array(
		0 => 'excerpt',
		1 => 'author',
	),
	'active' => true,
	'description' => '',
));

endif;
