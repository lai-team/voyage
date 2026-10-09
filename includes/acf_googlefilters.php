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
		//error_log(print_r('typevalueLOAD: '.json_encode($value),true));
		$geom_json = json_decode($value) ;
		//error_log(print_r('typevalueLoad2: '.gettype($geom_json),true));
		if ( !empty($geom_json->geometry->coordinates)){
			$geom_json_properties = (array) $geom_json->properties;
			$geom_json_properties['lng'] = $geom_json->geometry->coordinates[0];
			$geom_json_properties['lat'] = $geom_json->geometry->coordinates[1];

		//error_log(print_r('typevalueLoad2: '.json_encode($geom_json_properties),true));
			return $geom_json_properties;
		}else{
			//$user_location=explode(",", json_decode(file_get_contents("http://ipinfo.io/".getClientIP()."/json"))->loc);
			$user_location=bv_userlocation();
			if( !empty($user_location) ) {
			//error_log( print_r('userlocation: '.json_encode($user_location). '  laTitude ' .$user_location['latitude'],true));
				$value['lat']=$user_location->latitude;
				$value['lng']=$user_location->longitude;
			}else{
				$value['lat']=1;
				$value['lng']=-1;
			}
			return $value;
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
		$lng = $value['lng'];
		$lat = $value['lat'];
		//var_error_log($value);
		//error_log(json_encode($value));
		//error_log(print_r('geomPT LNG: '. json_encode($lng),true));
		//error_log(print_r('geomPT LAT: '. json_encode($lat),true));
		$geom_point = WP_GeoUtil::point($lng,$lat);
		//error_log(print_r('geomPT: '. json_encode($geom_point),true));
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

