<?php


add_action( 'pre_get_posts', 'bv_search_subsites');
function bv_search_subsites($query){
	if (get_current_blog_id()==get_main_site_id() && in_array($query->get('post_type') ,array('post',EVENTS_POSTTYPE) )){
		$query->set( 'ep_integrate', 'true') ;
		$query->set( 'sites' ,'all') ;
		$query->set( 'posts_per_page', 10) ;
		//$query->set( 'order', 'DESC') ;
		//$query->set( 'orderby', 'date') ;
		$query->set( 'author__not_in',array(1,2,3));      
		$query->set( 'category__not_in',array(1));
		/*
		$tax_query= array(
			'taxonomy' => 'category',
			'include_children' => true,
			'field'    => 'term_id',
			'operator' => 'NOT IN',
			'terms'    => array( 1 ),
		);
		$query->tax_query->queries[] = $tax_query;
		$query->query_vars['tax_query'] = $query->tax_query->queries;
		 */
	}
	//error_log(print_r('search subsites   ' . json_encode($query),true));
}

//add_filter( 'ep_searchable_post_types', 'bv_ep_posttypes' );
function bv_ep_posttypes($posttypes){
	return array('post','tribe_events');
}

add_action( 'pre_get_posts', 'bv_query_exclude_private');

/**
 * Hide the restricted category tree from visitors without member privilege.
 *
 * Previously gated on `isset( $query->tax_query )`. WP_Query only populates
 * that property inside the archive branch of parse_query(), so it is null for
 * is_single / is_page / p / page_id / pagename -- meaning the exclusion applied
 * to listings and archives but NOT to a direct permalink. Anyone who obtained
 * the URL of a restricted post could read it.
 *
 * It also mutated $query->tax_query->queries behind an @, which is what made
 * the isset() guard necessary in the first place. Using $query->set() works
 * uniformly for singular and archive queries and merges with any tax_query the
 * request already carries.
 *
 * This filters queries. It is not access control: anything that fetches the
 * post outside the main query -- the REST API, a secondary WP_Query, a feed --
 * is unaffected. Real enforcement belongs in template_redirect with a
 * current_user_can() check, or in WordPress's own private post status. See
 * README.md.
 */
function bv_query_exclude_private($query){
	if ( ! $query->is_main_query() || is_admin() || bv_member_privilege() ) {
		return;
	}

	$restricted = array(
		'taxonomy'         => 'category',
		'include_children' => true,
		'field'            => 'term_id',
		'operator'         => 'NOT IN',
		'terms'            => array( 1 ),
	);

	$tax_query = $query->get( 'tax_query' );

	if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
		$tax_query = array();
	}

	$tax_query[] = $restricted;

	$query->set( 'tax_query', $tax_query );
}

function bv_member_privilege(){
        return current_user_can('read_private_posts');
}

function privcats($negate=false,$cat_id=1){
	$cat_objects = get_categories(array( 'child_of' => $cat_id) );
	$cat_ids = array($cat_id); 
	foreach($cat_objects as $cat){
		$cat_ids[]=$cat->term_id;
	}
	// Was `$cats_ids = array_map(..., $cats_ids)` -- an undefined variable on
	// both sides, so a PHP 8 TypeError, and the result was discarded anyway
	// because the function returns $cat_ids.
	if ( $negate ) {
		$cat_ids = array_map( function( $x ) { return -1 * $x; }, $cat_ids );
	}

	return $cat_ids;
}
