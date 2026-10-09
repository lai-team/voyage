<?php

return array (
  'version' => '5.1.5',
  'title' => 'Media for network',
  'type' => 'wp-media',
  'id' => '5eb07bd1c122e',
  'updated' => 1589671094,
  'columns' => 
  array (
    'title' => 
    array (
      'type' => 'title',
      'label' => '<span class="dashicons dashicons-format-image"></span>',
      'width' => '20',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'on',
      'export' => 'on',
    ),
    'date' => 
    array (
      'type' => 'date',
      'label' => '<span class="dashicons dashicons-calendar-alt"></span>',
      'width' => '15',
      'width_unit' => '%',
      'sort' => 'on',
      'edit' => 'off',
      'export' => 'on',
      'filter' => 'off',
      'filter_label' => '',
      'filter_format' => '',
    ),
    '5e94fdbf97ad5' => 
    array (
      'type' => 'column-description',
      'label' => 'Description',
      'width' => '',
      'width_unit' => '%',
      'string_limit' => '',
      'before' => '',
      'after' => '',
      'sort' => 'on',
      'edit' => 'on',
      'export' => 'on',
      'filter' => 'off',
      'filter_label' => '',
    ),
    'tags' => 
    array (
      'type' => 'tags',
      'label' => '<span class="dashicons dashicons-tag"></span>',
      'width' => '10',
      'width_unit' => '%',
      'export' => 'on',
    ),
    'comments' => 
    array (
      'type' => 'comments',
      'label' => '<span class="vers comment-grey-bubble" title="Comments"><span class="screen-reader-text">Comments</span></span>',
      'width' => '10',
      'width_unit' => '%',
      'export' => 'on',
      'filter' => 'off',
      'filter_label' => '',
    ),
    '5ebfbfe5275c6' => 
    array (
      'type' => 'column-meta',
      'label' => '<span class="dashicons dashicons-location"></span>',
      'width' => '45',
      'width_unit' => 'px',
      'field' => 'geom_point',
      'field_type' => 'has_content',
      'before' => '',
      'after' => '',
      'sort' => 'on',
      'export' => 'off',
      'filter' => 'off',
      'filter_label' => '',
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
    'hide_bulk_actions' => 'off',
    'horizontal_scrolling' => 'off',
    'sorting' => 'date',
    'sorting_order' => 'desc',
  ),
);