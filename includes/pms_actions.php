<?php

 //'pms_member_subscription_update', $subscription['id'], array( 'status' => 'expired' ), $subscription );
//{"id":5,"user_id":"10","subscription_plan_id":"11","start_date":"2020-05-22 12:46:22","expiration_date":"","status":"pending","payment_profile_id":null,"payment_gateway":"","billing_amount":null,"billing_duration":null,"billing_duration_unit":null,"billing_cycles":null,"billing_next_payment":null,"billing_last_payment":null,"trial_end":""}
//

/* GDPR Delete user */
remove_action('template_redirect','pms_gdpr_delete_user');
add_action('template_redirect','bv_pms_gdpr_delete_user',20);
function bv_pms_gdpr_delete_user() {
    $gdpr_settings = pms_get_gdpr_settings();
    if( !empty( $gdpr_settings ) ) {
        if (!empty($gdpr_settings['gdpr_delete']) && $gdpr_settings['gdpr_delete'] === 'enabled') {
            if (isset($_REQUEST['pms_action']) && $_REQUEST['pms_action'] == 'pms_delete_user' && wp_verify_nonce($_REQUEST['pms_nonce'], 'pms-user-own-account-deletion') && isset($_REQUEST['pms_user']) && get_current_user_id() == $_REQUEST['pms_user']) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                include_once(ABSPATH . WPINC . '/ms-functions.php' ); // remove_user_from_blog
                include_once(ABSPATH . 'wp-admin/includes/ms.php' ); // wpmu_delete_user
                $user = new WP_User($_REQUEST['pms_user']);

                if (!empty($user->roles)) {
                    foreach ($user->roles as $role) {
                        if ($role != 'administrator') {
                                if (function_exists('bv_delete_user_subsite')){
                                        bv_delete_user_subsite($_REQUEST['pms_user']);
                                        /*
 *                                         if (get_user_meta( $_REQUEST['pms_user'],'user_blog')){
 *                                          bv_delete_user_subsite(get_user_meta( $_REQUEST['pms_user'],'user_blog',true));
 *                                         }/
 *                                          */
                                }
                            pms_member_delete_user_subscription_abandon($_REQUEST['pms_user']);
                            wpmu_delete_user($_REQUEST['pms_user']);
                        }
                    }
                }

                $args = array('pms_user', 'pms_action', 'pms_nonce');
                wp_redirect(remove_query_arg($args));
            }
        }
    }
}

remove_action( 'delete_user', 'pms_member_delete_user_subscription_cancel' );
add_action( 'delete_user', 'pms_member_delete_user_subscription_abandon',20 );
function pms_member_delete_user_subscription_abandon( $user_id = 0 ) {
//Fri Jul 16 15:23:23 CEST 2021
    if( empty( $user_id ) )
        return;

    $member_subscriptions = pms_get_member_subscriptions( array( 'user_id' => (int)$user_id ) );

    if( empty( $member_subscriptions ) )
        return;

    /*
    if (! function_exists('remove_child_subscriptions')){
          include_once(WP_PLUGIN_DIR . '/pms-add-on-group-memberships/includes/class-group-memberships.php');
    }
     */
    foreach( $member_subscriptions as $member_subscription ) {
      $subscription_data = array();
        $subscription_data['status'] = 'abandoned';

       // If we have a billing payment date, set it as the expiration date and remove it
      if( empty( $member_subscription->payment_profile_id ) && ! empty( $member_subscription->billing_next_payment ) ) {
             $subscription_data['expiration_date']      = date( 'Y-m-d H:i:s' );
               $subscription_data['billing_next_payment'] = '';
      }

      if( $member_subscription->update( $subscription_data ) ) {
              pms_success()->add( 'subscription_plans', apply_filters( 'pms_abandon_subscription_success', __( 'Your subscription has been successfully removed.', 'paid-member-subscriptions' ) ) );
              pms_add_member_subscription_log( $member_subscription->id, 'subscription_abandoned' );
        }
      $member_subscription_data=array();
      $member_subscription_data['subscription_plan_id']=$member_subscription->subscription_plan_id;
      //error_log(print_r('membersub id: ' . $member_subscription->id,true));
      //error_log(print_r('sub id: ' . $member_subscription->subscription_plan_id,true));
      (new PMS_Group_Memberships())->remove_child_subscriptions($member_subscription->id, $member_subscription_data);
      $member_subscription->remove();
          /*
        if( $member_subscription->status == 'active' ) {

            $member_subscription->update( array( 'status' => 'abandoned' ) );

        }
           */

    }
}

add_action('pms_abandon_member_subscription_successful','bv_pms_remove_subscription',1,2);
function bv_pms_remove_subscription($member_data,$member_subscription){

//Fri Jul 16 14:47:39 CEST 2021
        // Verify nonce
        //if( ! isset( $_REQUEST['pmstkn'] ) || ! wp_verify_nonce( $_REQUEST['pmstkn'], 'pms_abandon_subscription' ) )
         //   return;

        // Just in case, do not let logged out users get here
        if( ! is_user_logged_in() )
            return;

        if( empty( $_POST['subscription_id'] ) )
            return;

        // Get member subscription
        //$member_subscription = pms_get_member_subscription( (int)$_POST['subscription_id'] );

        if( is_null( $member_subscription ) )
            return;

        $member_subscription_data=array();
        $member_subscription_data['subscription_plan_id']=$member_subscription->subscription_plan_id;
        //print_r($member_subscription->subscription_plan_id);
        (new PMS_Group_Memberships())->remove_child_subscriptions($member_subscription->id, $member_subscription_data);
        $member_subscription->remove();
}

add_action('pms_member_subscription_update','bv_pms_update_privileges',10,3);
function bv_pms_update_privileges($sub_id, $arr_status, $oldsub){
	if (in_array($arr_status['status'], array('expired','abandoned'))){
		//canceled status can be used for buffer
		do_action('bv_privilege_update',$sub_id,$oldsub,false);
	}elseif ( $arr_status['status'] == 'active'){
		//pending status can be used for buffer
		do_action('bv_privilege_update',$sub_id,$oldsub,true);
	}
}


add_action('bv_privilege_update','bv_archive_subsite',10,3);
function bv_archive_subsite($sub_id, $oldsub, $unarchive){
	$plan_id = $oldsub["subscription_plan_id"];
	if ($plan_id == BASIC_ID){
		$user_id = $oldsub['user_id']; 
		$blog_id = get_user_meta( $user_id, 'user_blog',true );
		if ($blog_id) {
			update_blog_status( $blog_id, 'archived', $unarchive? '0' : '1' );
		}else{
			return;
		}
	}
}

add_action('bv_privilege_update','bv_pms_update_blogspace',10,3);
function bv_pms_update_blogspace($sub_id, $oldsub, $up){
	$user_id=$oldsub['user_id'];
	$plan_id = get_user_meta( $user_id,'private_plan',true);   
	if ( !empty($plan_id) && $plan_id == $oldsub['subscription_plan_id']){
		$blog_id = get_user_meta( $user_id, 'user_blog',true );
		switch_to_blog($blog_id);
		$plan_id_blog = get_option('private_plan');
		if( !empty($plan_id_blog) && $plan_id_blog == $oldsub['subscription_plan_id']){
			if (!$up){
				delete_option('blog_upload_space');
			}else{
				update_option('blog_upload_space',PREMIUM_SPACE_LIMIT);
			}
		}
		restore_current_blog();
	}
}

//Abandon child subscription from the group when the child subscription is deleted
add_action( 'pms_member_subscription_delete',    'bv_gm_update_childrole' , 8, 2 );
add_action('bv_privilege_update','bv_gm_update_childrole',10,3);
function bv_gm_update_childrole($sub_id,$oldsub,$up=false){
	$blog_id = bv_gm_get_group_blog($sub_id);
	$user_id = $oldsub['user_id'];
	switch_to_blog($blog_id);
	if(user_can($user_id,'editor')) return;
	$user_obj = new WP_User($user_id);
	$user_obj->for_site( $blog_id );
	if($up){
		$user_obj->add_role('contributor');
	}else{
		$user_obj->remove_role('contributor');
	}
	restore_current_blog();
}

// may need to remove hook below
//  add_action( 'pms_register_form_after_create_user',        array( $this, 'maybe_link_user_with_parent_subscription' ) );
add_action('user_register','bv_link_user_with_parent_subscriptions');
function bv_link_user_with_parent_subscriptions($user_id){
	if ( function_exists( 'pms_get_member_subscriptions' ) ){
		$user = get_userdata( $user_id );
		$meta_ids=bv_gm_get_all_meta_values_by_email($user->user_email);
		error_log(print_r('meta_ids: '. json_encode($meta_ids),true));
		foreach($meta_ids as $meta_id => $row){
			error_log(print_r('meta_id_row: '. json_encode($row),true));
			//var_dump($row->member_subscription_id);
			$owner_subscription = pms_get_member_subscription( $row->member_subscription_id );
			$subscription_data = array(
				'user_id'              => $user->ID,
				'subscription_plan_id' => $owner_subscription->subscription_plan_id,
				'start_date'           => $owner_subscription->start_date,
				'expiration_date'      => $owner_subscription->expiration_date,
				'status'               => 'pending',
			);
			$subscription = new PMS_Member_Subscription();
			$subscription->insert( $subscription_data );

			pms_add_member_subscription_meta( $subscription->id, 'pms_group_subscription_owner', $owner_subscription->id );
			pms_add_member_subscription_meta( $owner_subscription->id, 'pms_group_subscription_member', $subscription->id );

			pms_delete_member_subscription_meta( $owner_subscription->id, 'pms_gm_invited_emails_' . $meta_id );
			pms_delete_member_subscription_meta( $owner_subscription->id, 'pms_gm_invited_emails', $user->user_email );

			if( function_exists( 'pms_add_member_subscription_log' ) )
				pms_add_member_subscription_log( $subscription->id, 'group_user_accepted_invite' );

			bv_update_member_subscription($subscription->id,'active');
		/*
		$blog_id = bv_gm_get_group_blog( $owner_subscription->id);
		$user_obj = new WP_User($user_id);
		$user_obj = for_site($blog_id);
		$user_obj-> add_role('contributor');
		 */
		}
	}
}

function bv_gm_get_group_blog( $subscription_id ){
	if ( function_exists( 'pms_gm_is_group_owner' ) ){
		if( pms_gm_is_group_owner( $subscription_id ) )       
			$owner_subscription_id = $subscription_id;
		else
			$owner_subscription_id = pms_get_member_subscription_meta( $subscription_id, 'pms_group_subscription_owner', true );  
		if( empty( $owner_subscription_id ) )  return '';

		$owner_subscription = pms_get_member_subscription( (int)$owner_subscription_id );
		if( !empty( $owner_subscription ) )     
			$owner_user_id =  $owner_subscription->user_id;                                                                                                     
		if( !empty($owner_user_id))
			return (int) get_user_meta($owner_user_id,'user_blog',true);
		return '';
	}
} 


//Abandon child subscriptions when the parent abandons his  
add_action( 'pms_member_subscription_before_metadata_delete', 'bv_abandon_child_subscriptions' , 8, 2 );
function bv_abandon_child_subscriptions( $owner_id, $subscription_data ){
	if( empty( $owner_id ) )
		return;

	if ( function_exists( 'pms_gm_get_group_subscriptions' ) ){

		$plan = pms_get_subscription_plan( $subscription_data['subscription_plan_id'] );

		if( $plan->type == 'regular' )
			return;

		$group_subscriptions = pms_gm_get_group_subscriptions( $owner_id );

		if( empty( $group_subscriptions ) )
			return;

		foreach( $group_subscriptions as $subscription_id ){
			bv_update_member_subscription($sub_id,'abandoned');
			//$member_subscription = pms_get_member_subscription( $subscription_id );
			//$member_subscription->remove();
		}
	}
}

function bv_update_member_subscription($sub_id,$status='abandoned'){
	if ( function_exists( 'pms_get_member_subscription' ) ){
		$sub_obj= pms_get_member_subscription($sub_id);
		$sub_obj->update(array('status'=>$status));
	}
}

