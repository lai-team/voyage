<?php

add_filter( 'trp_get_default_trp_machine_translation_settings','bv_googletranslate',20); 
function bv_googletranslate($arr){
	// No key means machine translation cannot work; leave TranslatePress's own
	// defaults alone rather than enabling it with an empty key.
	if ( '' === GOOG_TRANSLATEv2_KEY || ! is_array( $arr ) ) {
		return $arr;
	}

	$arr['machine-translation'] = 'yes';
	$arr['machine_translation_limit'] = GOOG_TRANSLATEv2_CHARLIMIT;
	$arr['google-translate-key']=GOOG_TRANSLATEv2_KEY;
	return $arr;
}

add_filter('init', 'bv_checkautotranslate',100);
function bv_checkautotranslate(){
	if ( '' === GOOG_TRANSLATEv2_KEY || ! bv_dependency_available( 'trp' ) ) {
		return;
	}

	$trp = TRP_Translate_Press::get_trp_instance();
	$trp_settings = is_object( $trp ) ? $trp->get_component( 'settings' ) : null;

	// get_component() returns null/false if TranslatePress has not built its
	// settings component, or if a version renames it.
	if ( ! is_object( $trp_settings ) || ! method_exists( $trp_settings, 'get_default_trp_machine_translation_settings' ) ) {
		return;
	}

	$mt_settings_option = get_option('trp_machine_translation_settings', $trp_settings->get_default_trp_machine_translation_settings() );

	if ( ! is_array( $mt_settings_option ) ) {
		return;
	}

	if ( ! isset( $mt_settings_option['machine-translation'] ) || $mt_settings_option['machine-translation'] != 'yes' ) {
		$mt_settings_option['machine-translation'] = 'yes';
		update_option('trp_machine_translation_settings', $mt_settings_option );
	}
}
