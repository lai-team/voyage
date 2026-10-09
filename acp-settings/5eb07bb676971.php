<?php

return array (
  'version' => '5.1.5',
  'title' => 'Itineraries for network',
  'type' => 'tribe_events',
  'id' => '5eb07bb676971',
  'updated' => 1589140844,
  'columns' => 
  array (
    'categories' => 
    array (
      'type' => 'categories',
      'label' => 'Journeys',
      'width' => '10',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'on',
      'enable_term_creation' => 'on',
      'export' => 'off',
      'filter' => 'off',
    ),
    'title' => 
    array (
      'type' => 'title',
      'label' => 'Title',
      'width' => '15',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'on',
      'export' => 'off',
    ),
    'start-date' => 
    array (
      'type' => 'start-date',
      'label' => 'Start Date',
      'width' => '',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'on',
      'export' => 'off',
      'filter' => 'off',
      'filter_label' => '',
      'filter_format' => '',
    ),
    'end-date' => 
    array (
      'type' => 'end-date',
      'label' => 'End Date',
      'width' => '',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'on',
      'export' => 'off',
      'filter' => 'off',
      'filter_label' => '',
      'filter_format' => '',
    ),
    '5eb08a5365dd3' => 
    array (
      'type' => 'column-ec-event_duration',
      'label' => '<span class="dashicons dashicons-backup"></span>',
      'width' => '10',
      'width_unit' => '%',
      'export' => 'off',
    ),
    'tags' => 
    array (
      'type' => 'tags',
      'label' => '<span class="dashicons dashicons-tag"></span>',
      'width' => '15',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'on',
      'enable_term_creation' => 'on',
      'export' => 'off',
      'filter' => 'off',
      'filter_label' => '',
    ),
    '5eb055ba667f8' => 
    array (
      'type' => 'column-featured_image',
      'label' => '<span class="dashicons dashicons-format-image"></span>',
      'width' => '180',
      'width_unit' => 'px',
      'featured_image_display' => 'image',
      'image_size' => 'thumbnail',
      'image_size_w' => '100',
      'image_size_h' => '100',
      'sort' => 'on',
      'edit' => 'on',
      'export' => 'off',
      'filter' => 'off',
      'filter_label' => '',
    ),
    'comments' => 
    array (
      'type' => 'comments',
      'label' => '<span class="vers comment-grey-bubble" title="Comments"><span class="screen-reader-text">Comments</span></span>',
      'width' => '',
      'width_unit' => '%',
      'export' => 'off',
    ),
  ),
  'settings' => 
  array (
    'hide_inline_edit' => 'off',
    'hide_bulk_edit' => 'on',
    'hide_filters' => 'off',
    'hide_segments' => 'off',
    'hide_search' => 'off',
    'hide_export' => 'on',
    'hide_new_inline' => 'on',
    'hide_bulk_actions' => 'on',
    'horizontal_scrolling' => 'off',
    'sorting' => 'start-date',
    'sorting_order' => 'desc',
  ),
);