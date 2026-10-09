<?php

function my_acf_google_map_api( $api ){
	
	$api['key'] = GOOG_MAP_KEY;
	
	return $api;
}

add_filter('acf/fields/google_map/api', 'my_acf_google_map_api');

function my_acf_init() {
		acf_update_setting('google_api_key', GOOG_MAP_KEY);
}

add_action('acf/init', 'my_acf_init');

add_filter('acf/load_value/type=google_map', 'bv_load_gjson4goog', 5, 3);
add_filter('acf/update_value/type=google_map', 'bv_update_goog2gjson', 20, 3);

function bv_load_gjson4goog( $value, $post_id, $field ) {

        // Ensure value is an array.
        if( $value ) {
		// ACF's own google_map value is an array, and json_decode() requires a
		// string -- a TypeError on PHP 8 for any row written without this
		// filter running (an import, WP-CLI, the REST API).
		$geom_json = is_string( $value ) ? json_decode( $value ) : null;

		if ( is_object( $geom_json ) && ! empty( $geom_json->geometry->coordinates ) ) {
			$geom_json_properties = (array) $geom_json->properties;
			$geom_json_properties['lng'] = $geom_json->geometry->coordinates[0];
			$geom_json_properties['lat'] = $geom_json->geometry->coordinates[1];

		//error_log(print_r('typevalueLoad2: '.json_encode($geom_json_properties),true));
			return $geom_json_properties;
		}else{
			/*
			 * $value is a string on this branch -- we only get here when
			 * json_decode() produced something without geometry->coordinates --
			 * and `$value['lat'] = …` on a string is
			 * "Cannot access offset of type string on string", a fatal on
			 * PHP 8. Build a fresh array instead of writing into it.
			 */
			$coords = array();
			if ( is_array( $value ) ) {
				$coords = $value;
			} elseif ( is_object( $geom_json ) ) {
				$coords = (array) $geom_json;
			}

			$user_location = bv_userlocation();

			if ( ! empty( $user_location ) && isset( $user_location->latitude, $user_location->longitude ) ) {
				$coords['lat'] = $user_location->latitude;
				$coords['lng'] = $user_location->longitude;
			} else {
				$coords['lat'] = 1;
				$coords['lng'] = -1;
			}

			return $coords;
		}
	}
        // Return default.
        return false;
}

function bv_update_goog2gjson( $value, $post_id, $field ) {

        // Ensure value is an array.
 	if( is_string($value) ) {
		$value = json_decode( wp_unslash($value), true );
	}
        if( $value ) {
		//error_log(print_r('valueUPDATE: '. json_encode($value),true));
		//error_log(print_r('typevalueUPDATE: '.gettype($value),true));
		//$value=$json_decode($value);
		if ( ! is_array( $value ) || ! isset( $value['lng'], $value['lat'] ) ) {
			return $value;
		}

		$lng = $value['lng'];
		$lat = $value['lat'];

		/*
		 * Without this guard, deactivating WP-GeoMeta makes every post save
		 * fatal. spatial_functions.php already guards the same class this way;
		 * the write path did not.
		 */
		if ( ! class_exists( 'WP_GeoUtil' ) ) {
			return $value;
		}

		$geom_point = WP_GeoUtil::point($lng,$lat);

		/*
		 * WP_GeoUtil::__callStatic() returns null when the function is not in
		 * get_capabilities(), so json_decode(null) yielded null and the
		 * function fell through to `return false` -- silently discarding the
		 * pin the user had just placed. Return the submitted value instead, so
		 * a spatial-layer problem costs the GeoJSON conversion rather than the
		 * data.
		 */
		if ( ! is_string( $geom_point ) || '' === $geom_point ) {
			return $value;
		}

		$geom_point_json= json_decode($geom_point);
		if ($geom_point_json){
			$geom_point_json->properties = (object) $value;
		//error_log( print_r('update_goog2gjson: '. json_encode( $geom_point_json),true) );
		return json_encode( $geom_point_json) ;
		}
        }

        // Return default.
        return false;
}

