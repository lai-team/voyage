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
function bv_query_exclude_private($query){
        //if( empty( $query->query_vars['suppress_filters'] )) {
        if( $query->is_main_query() && !bv_member_privilege() && isset( $query->tax_query ) ){
                //$query->set( 'category__not_in',privcats());
		$tax_query= array(
			'taxonomy' => 'category',
			'include_children' => true,
			'field'    => 'term_id',
			'operator' => 'NOT IN',
			'terms'    => array( 1 ),
		);
                #error_log(print_r('Noooo   contribUTOR    ' . json_encode($query->tax_query),true));
                #error_log(print_r('No   contribUTOR    ' . json_encode($tax_query),true));
		@$query->tax_query->queries[] = $tax_query;
		#array_push($query->tax_query->queries, $tax_query);
		$query->query_vars['tax_query'] = $query->tax_query->queries;
//                error_log(print_r('Noooo   contribUTOR    ' . json_encode($query->query_vars),true));
        }else{
 //               error_log(print_r('Privatecontent   ' . json_encode($query->query_vars),true));                                                                  
        }
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
	if($negate) $cats_ids=array_map(function($x){return -1*$x;},$cats_ids);
	return $cat_ids;
}
