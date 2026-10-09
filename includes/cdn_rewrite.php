<?php

// Guarded so the host can be overridden from wp-config.php, and so a
// redefinition is not a PHP 8 warning.
if ( ! defined( 'CDN_URL' ) ) {
	define('CDN_URL','//cdn.beau.voyage');
}

add_filter('post_thumbnail_url', 'cdn_rewrite_url', 20, 3);
add_filter('wp_get_original_image_url', 'cdn_rewrite_url', 20, 2);
add_filter('wp_get_attachment_url', 'cdn_rewrite_url', 20, 2); ## Primary filter
add_filter( 'wp_get_attachment_thumb_url', 'cdn_rewrite_url', 20, 2);
add_filter('get_header_image', 'cdn_rewrite_url', 20,1 );
add_filter('wp_get_attachment_image_src', 'cdn_rewrite_image_src', 20,4 );
add_filter('the_content', 'cdn_rewrite_url', 20,1 );
#add_filter('the_content', 'cdn_rewrite_content', 20,1 );

/**
 * Point a URL at the CDN.
 *
 * Only ever operates on strings. Several of the filters this is attached to
 * legitimately pass false -- get_header_image(), wp_get_attachment_thumb_url(),
 * wp_get_original_image_url(), post_thumbnail_url() -- and preg_replace() turned
 * that false into an empty string (plus a PHP 8.1 deprecation for the null
 * subject), breaking every `=== false` check downstream.
 *
 * @param mixed $url The value being filtered.
 * @return mixed The rewritten URL, or the value untouched if it is not a string.
 */
function cdn_rewrite_url($url,$post_id=1,$size=1){
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}

	$pattern=preg_quote('beau.voyage/wp-content/uploads','/');

	// [\w.-]+ rather than \w*: \w excludes hyphens and dots, so subsites whose
	// subdomain contains a hyphen -- which members choose themselves -- silently
	// bypassed the CDN altogether. The old `(\.*)` also matched runs of dots.
	return preg_replace('/\/\/[\w.-]*' . $pattern . '/', CDN_URL, $url);
}

/**
 * wp_get_attachment_image_src passes array|false, not a string.
 *
 * preg_replace() maps over arrays, so attaching cdn_rewrite_url() directly to
 * this filter rewrote the URL but also coerced the width and height from int to
 * string and the is_intermediate flag from false to '' -- for every caller of
 * wp_get_attachment_image_src() on the site.
 *
 * @param array|false $image Array of [ url, width, height, is_intermediate ].
 * @return array|false
 */
function cdn_rewrite_image_src( $image, $attachment_id = 0, $size = '', $icon = false ) {
	if ( ! is_array( $image ) || ! isset( $image[0] ) ) {
		return $image;
	}

	$image[0] = cdn_rewrite_url( $image[0] );

	return $image;
}

add_filter( 'wp_calculate_image_srcset', 'cdn_rewrite_content', 20, 5 );
function cdn_rewrite_content( $sources, $size_array, $image_src, $image_meta, $attachment_id){
	#error_log(print_r('neW contEnT: '.json_encode($content),true));
	if ( ! empty( $sources ) && is_array( $sources ) ) {
		foreach ( $sources as $key => $source ) {
			if ( isset( $source['url'] ) ) {
				$sources[ $key ]['url'] = cdn_rewrite_url( $source['url'] );
			}
		}
	}
	#error_log(print_r($image_src  . ' oLD sources: '. json_encode($sources),true));
	//error_log(print_r($attachment_id . ' nEW contEnT: '. cdn_rewrite_url( $content),true));
	return $sources;
}
