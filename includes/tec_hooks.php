<?php

// Two callbacks registered on `init` with entirely commented-out bodies, and a
// commented copy of TEC's own populate_field_with_default_api_key() carrying
// TEC's shipped default Google Maps key, were removed here. The key was never
// ours — it is public in every Events Calendar install — but a dead AIza…
// string trips every secret scanner that looks at this repository. The site's
// own key is the GOOG_MAP_KEY constant in wp-config.php, applied by
// includes/acf_googlefilters.php.

//function bv_null(){return null;}

//add_filter( 'tribe_events_after_html', 'bv_null',99 );
//add_filter( 'admin_footer_text', 'bv_null',99 );
//add_filter( 'tribe_html_credit', 'bv_null',99 );


/**
 * Find the next or previous event relative to a given post.
 *
 * Adapted from Tribe__Events__Adjacent_Events::get_closest_event(), with the
 * class's $this->current_event_id replaced by a $post_obj parameter.
 *
 * The Events Calendar is optional on this network -- it is currently not
 * activated on any site -- so everything this touches is guarded. Without TEC
 * there is no event post type and therefore no adjacent event: return null,
 * which is also what the function returns when nothing is found.
 *
 * @param WP_Post $post_obj        Post to find the neighbour of.
 * @param string  $mode            'next' or 'previous'.
 * @param array   $term_array      Optional terms to restrict to.
 * @param string  $searchposttype  Unused; kept for call-signature compatibility.
 * @param string  $taxonomy        Taxonomy for $term_array.
 * @return WP_Post|null
 */
function bv_get_closest_event($post_obj, $mode = 'next', $term_array=array(), $searchposttype='', $taxonomy= 'cat' ) {
	if ( ! function_exists( 'tribe_events' ) || ! class_exists( 'Tribe__Events__Adjacent_Events' ) ) {
		return null;
	}

	if ( ! $post_obj instanceof WP_Post ) {
		return null;
	}

	//$query_posttype = empty($searchposttype)? $current_post->post_type :$searchposttype;

	//$post_obj = get_post( $this->current_event_id );

	if ( 'previous' === $mode ) {
		$order      = 'DESC';
		$direction  = '<';
	} else {
		$order      = 'ASC';
		$direction  = '>';
		$mode       = 'next';
	}

	$args = [
		'posts_per_page' => 1,
		'post__not_in'   => [ $post_obj->ID ],
		'meta_query'     => [
			[
				'key'     => '_EventStartDate',
				'value'   => $post_obj->_EventStartDate??$post_obj->post_date,
				'type'    => 'DATETIME',
				'compare' => $direction,
			],
			[
				'key'     => '_EventHideFromUpcoming',
				'compare' => 'NOT EXISTS',
			],
			'relation'    => 'AND',
		],
	];
	$restrict_cat=(isset($term_array) && !empty($term_array) );
	if ( $restrict_cat ){
		$args += array( $taxonomy => implode(",",$term_array));
	}

	$events_orm = tribe_events();

	/**
	 * Allows the query arguments used when retrieving the next/previous event link
	 * to be modified.
	 *
	 * @since 4.6.12
	 *
	 * @param array   $args
	 * @param WP_Post $post_obj
	 */
	$args = (array) apply_filters( "tribe_events_get_{$mode}_event_link", $args, $post_obj );

	$events_orm->order_by( 'event_date', $order );
	$events_orm->by_args( $args );
	$query = $events_orm->get_query();

	/*
	 * Make sure we are not including same datetime events.
	 *
	 * These two lines read [ $this, 'get_closest_event_where' ] when this was
	 * copied out of Tribe__Events__Adjacent_Events, where $this was an
	 * instance of that class. In a plain function $this is undefined, so both
	 * lines were a fatal "Using $this when not in object context" -- with TEC
	 * active as well as without.
	 *
	 * get_closest_event_where() does not read any instance state: it recovers
	 * the post id from the SQL itself. So TEC's own singleton is the correct
	 * callable, and it keeps the ~100 lines of WHERE-rewriting regex
	 * maintained upstream rather than copied here. The callable is held in a
	 * variable so add_filter and remove_filter are given the identical
	 * instance.
	 */
	$bv_tec_adjacent = function_exists( 'tribe' )
		? tribe( 'tec.adjacent-events' )
		: new Tribe__Events__Adjacent_Events();

	$bv_closest_where = [ $bv_tec_adjacent, 'get_closest_event_where' ];

	add_filter( 'posts_where', $bv_closest_where );

	// Fetch the posts
	$query->get_posts();

	// Remove this filter right after fetching the events
	remove_filter( 'posts_where', $bv_closest_where );

	$results = $query->posts;

	$event = null;

	// If we successfully located the next/prev event, we should have precisely one element in $results
	if ( 1 === count( $results ) ) {
		$event = current( $results );
	}

	/**
	 * Affords an opportunity to modify the event used to generate the event link (typically for
	 * the next or previous event in relation to $post).
	 *
	 * @since 4.6.12
	 *
	 * @param WP_Post $post_obj
	 * @param string  $mode (typically "previous" or "next")
	 */
	return apply_filters( 'tribe_events_get_closest_event', $event, $post_obj, $mode );
}

function bv_tribe_events_adjacent_category( $args, $post){
	error_log(print_r('tribe_adjacents1: '. json_encode($args),true));
	$special_categories_local = bv_special_categories();
	$category=get_queried_object();	
	if( $category && is_a($category,'WP_Term') && !in_array($category->slug,$special_categories_local)){
	//	$args += array(
	//		'cat'   => $category->term_id  ,
	//	);
	}
	error_log(print_r('tribe_adjacents2: '. json_encode($args),true));
	return $args;
};
//add_filter("tribe_events_get_previous_event_link",'bv_tribe_events_adjacent_category',10,2);
//add_filter("tribe_events_get_next_event_link",'bv_tribe_events_adjacent_category',10,2);

add_filter( 'tribe_editor_classic_is_active', '__return_false' );
add_filter( 'tribe_events_blocks_editor_is_on', '__return_true' );

add_filter('tribe_events_register_event_cat_type_args','bv_hide_tribe_events_cat');
function bv_hide_tribe_events_cat($args){
//error_log(print_r('args: '. json_encode($args),true));
	if ($args['labels']['name'] == 'Event Categories'){
		$args['public'] = false;
		$args['show_ui'] = false;
	}

	return $args;
}

/*
 * The Events Calendar snippet
 * Enable 'default' categories for Events
 * Gist: https://gist.github.com/niconerd/4445c5e205ef1de3690f6094159e9f88
 */
add_filter( 'tribe_events_register_event_type_args', 'tribe_enable_post_categories' );
function tribe_enable_post_categories( $args ){
	$args['taxonomies'][] = 'category';
	return $args;
}

add_filter( 'tribe_events_editor_default_template', function( $template ) {
	$template = [
		[ 'tribe/event-datetime' ],
		[ 'core/paragraph', [
			'placeholder' => __( 'Add Description...', 'the-events-calendar' ),
		], ],
		//[ 'tribe/event-organizer' ],
		//[ 'tribe/event-venue' ],
		[ 'tribe/event-website' ],
		[ 'tribe/event-links' ],
	];
	return $template;
}, 11, 1 );

/**
 * Provides an opportunity to modify the labels used for the event post type.
 *
 * @var array
 */
add_filter( 'tribe_events_register_event_post_type_labels', 'bv_change_tribe_events_labels'); 
function bv_change_tribe_events_labels($labels){
	$labels=array(
	'name'                     => __('Itineraries'),
	'singular_name'            => __('Itinerary'),
	'add_new'                  => esc_html__( 'Add New', 'the-events-calendar' ),
	'add_new_item'             => sprintf( esc_html__( 'Add New %s', 'the-events-calendar' ), 'Stop' ),
	'edit_item'                => sprintf( esc_html__( 'Edit %s', 'the-events-calendar' ), 'Stopping Point' ),
	'new_item'                 => sprintf( esc_html__( 'New %s', 'the-events-calendar' ), 'Stopping Point' ),
	'view_item'                => sprintf( esc_html__( 'View %s', 'the-events-calendar' ), 'Stopping Point' ),
	'search_items'             => sprintf( esc_html__( 'Search %s', 'the-events-calendar' ), 'Stops' ),
	'not_found'                => sprintf( esc_html__( 'No %s found', 'the-events-calendar' ), 'stops' ),
	'not_found_in_trash'       => sprintf( esc_html__( 'No %s found in Trash', 'the-events-calendar' ), 'stops' ),
	'item_published'           => sprintf( esc_html__( '%s published.', 'the-events-calendar' ), 'Stopping Point' ),
	'item_published_privately' => sprintf( esc_html__( '%s published privately.', 'the-events-calendar' ), 'Stopping Point' ),
	'item_reverted_to_draft'   => sprintf( esc_html__( '%s reverted to draft.', 'the-events-calendar' ), 'Stop' ),
	'item_scheduled'           => sprintf( esc_html__( '%s scheduled.', 'the-events-calendar' ), 'Event' ),
	'item_updated'             => sprintf( esc_html__( '%s updated.', 'the-events-calendar' ), 'Stopping Point' ),
	'name_admin_bar'	=> __('Itinerary'),
	'menu_name'		=> __('Itinerary'),
	) ;
	return $labels;
}
