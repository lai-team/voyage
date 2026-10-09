<?php

function shortcode_subscription($plan1=BASIC_ID,$premiumid=PREMIUM_ID){
    $user_id = get_current_user_id();
    //$plan1 = 456;
    if(!$plan1)$plan1=BASIC_ID;
    if(!$premiumid)$premiumid=PREMIUM_ID;
    $plan2 = get_user_meta( $user_id,'private_plan', true);
    
    if(!bv_get_required_level_status($user_id, $plan1)){
	    //error_log(print_r('pms-sub shortC0de: '. 
        //do_shortcode("[pms-subscriptions subscription_plans='$plan1']"),
	//	    true));
        return do_shortcode("[pms-subscriptions subscription_plans='$plan1']");
    }
    else if(!get_user_meta( $user_id, 'user_blog',true)){
        //echo 2;
        return do_shortcode( '[bv_register_subsite_form]' );
    }
    else if(!bv_get_required_level_status($user_id, $plan2 )){
        bv_sync_plans($premiumid,$plan2);
        return do_shortcode("[pms-subscriptions subscription_plans='$plan2']");
    }
    else{
        // Calls the Member list dashboard
        // return ((new PMS_Group_Memberships())->dashboard('')) ;
    }
}
add_shortcode("shortcode_subscription","shortcode_subscription");
