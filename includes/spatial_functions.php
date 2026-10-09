<?php

function bv_journey_stats($journey_id){
	$factor=1.2;
	$stats=array();
	$post_ids=get_posts(array('category'=> $journey_id,
		'fields'          => 'ids', // Only get post IDs
		'posts_per_page'  => -1));

	$geom_points= array_filter(array_map(function($id){return get_post_meta($id,'geom_point',true);},$post_ids) );  
	if(class_exists('WP_GeoUtil')){
	       	$lineString=WP_GeoUtil::linestring(...$geom_points);  
		$distance=WP_GeoUtil::st_length($lineString) ;

		$stats['degree']=$distance;
		//$stats['km']=$distance * $factor * 111.139;
		//$stats['mile']=$distance * $factor * 69 ;
		//$stats['nm']=$distance * $factor * 60 ;
	}
	if( count($post_ids) > 1 ){
		$firstDate = get_the_date( 'U',end($post_ids) ) ;
		$lastDate = get_the_date( 'U', $post_ids[0] ) ;
		$deltaTime= human_time_diff($firstDate, $lastDate);
		$stats['duration']=$deltaTime;
	}
	return $stats;
}

function bv_journeys_stats(){
	$stats=array();

	return $stats;
}
