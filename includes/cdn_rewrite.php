<?php

#define('CDN_URL','//img.beau.voyage/file/beauvoyage/wp-content/uploads');
#define('CDN_URL','//media.beau.voyage');
define('CDN_URL','//cdn.beau.voyage');

add_filter('post_thumbnail_url', 'cdn_rewrite_url', 20, 3);
add_filter('wp_get_original_image_url', 'cdn_rewrite_url', 20, 2);
add_filter('wp_get_attachment_url', 'cdn_rewrite_url', 20, 2); ## Primary filter
add_filter( 'wp_get_attachment_thumb_url', 'cdn_rewrite_url', 20, 2);
add_filter('get_header_image', 'cdn_rewrite_url', 20,1 );
add_filter('wp_get_attachment_image_src', 'cdn_rewrite_url', 20,4 );
add_filter('the_content', 'cdn_rewrite_url', 20,1 );
#add_filter('the_content', 'cdn_rewrite_content', 20,1 );
function cdn_rewrite_url($url,$post_id=1,$size=1){
	#$pattern='/\/\/(.*?)beau.voyage\/wp-content\/uploads/';
	$pattern=preg_quote('beau.voyage/wp-content/uploads','/');
	#$url_new= preg_replace($pattern ,CDN_URL ,$url);
	$url_new=preg_replace('/\/\/(\w*?)(\.*)'. $pattern . '/', CDN_URL,$url);
	#error_log(print_r('neW URL: '.json_encode($url_new),true));
	#error_log(print_r($pattern . '  neW URL: '. preg_replace('/\/\/(.*?)'. $pattern . '/ui', CDN_URL,$url) ,true));
	return $url_new;
}

add_filter( 'wp_calculate_image_srcset', 'cdn_rewrite_content', 20, 5 );
function cdn_rewrite_content( $sources, $size_array, $image_src, $image_meta, $attachment_id){
	#error_log(print_r('neW contEnT: '.json_encode($content),true));
	if (! empty($sources) ){
		foreach ($sources as &$id){
			$id['url'] = cdn_rewrite_url($id['url']);
		}
	}
	#error_log(print_r($image_src  . ' oLD sources: '. json_encode($sources),true));
	//error_log(print_r($attachment_id . ' nEW contEnT: '. cdn_rewrite_url( $content),true));
	return $sources;
}
