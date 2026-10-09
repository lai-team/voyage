<?php

add_filter( 'trp_get_default_trp_machine_translation_settings','bv_googletranslate',20); 
function bv_googletranslate($arr){
	$arr['machine-translation'] = 'yes';
	$arr['machine_translation_limit'] = GOOG_TRANSLATEv2_CHARLIMIT;
	$arr['google-translate-key']=GOOG_TRANSLATEv2_KEY;
	return $arr;
}

add_filter('init', 'bv_checkautotranslate',100);
function bv_checkautotranslate(){
	if(class_exists('TRP_Translate_Press')){
		$trp = TRP_Translate_Press::get_trp_instance();
		$trp_settings = $trp->get_component( 'settings' );
		$mt_settings_option = get_option('trp_machine_translation_settings', $trp_settings->get_default_trp_machine_translation_settings() );  
		if ( $mt_settings_option['machine-translation'] != 'yes' ) {                                                                                                                                                                            $mt_settings_option['machine-translation'] = 'yes';                                                                                                                                                                                 update_option('trp_machine_translation_settings', $mt_settings_option );
		}
	}
}
