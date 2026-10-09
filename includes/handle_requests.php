<?php 

require_once( wp_normalize_path(ABSPATH).'wp-load.php');

// Handle subsite registration 
if(isset($_POST['register_blog']) && isset($_POST['site_url']) && isset($_POST['title']) && isset($_POST['_wp_nonce']) ){
    function temp_create_subsite(){
        // Check if user is logged in then verify the nonce value to make sure this is a valid request
        if(is_user_logged_in() && wp_verify_nonce($_POST['_wp_nonce'], 'pms-user-own-subsite-creation_' . get_current_user_id())){
            $user_id = get_current_user_id();


            $gdpr_settings = pms_get_gdpr_settings(); // Takes GDPR settings, if GDPR is disabled in wp-admin this will be 'false'
            // If GDPR is required
            if( !empty( $gdpr_settings ) ){
                // If user does not have GDPR
                if(!get_user_meta( $user_id, 'pms_gdpr_user_consent', true )){
                    // If user has requested to accept GDPR
                    if ( isset( $_POST['user_consent'] ) && $_POST['user_consent'] == '1' ) {
                        update_user_meta( $user_id, 'pms_gdpr_user_consent', 'yes' );
                        update_user_meta( $user_id, 'pms_gdpr_user_consent_time', time() );
                    }
                    // If user has not accepted GDPR
                    else{
                        pms_errors()->add('user_consent', __('This field is required.', 'paid-member-subscriptions'));
                        return;
                    }
                }
            }
            // Call the function which creates the subsite
            bv_create_subsite($user_id,$_POST['site_url'], $_POST['title']);
        }
    }
    add_action('init', 'temp_create_subsite');
}

// Handle subsite deletion
if (isset($_REQUEST['pms_action']) && $_REQUEST['pms_action'] == 'pms_delete_subsite') {
    function temp_delete_subsite(){
        // Check if the request is valid
        if(wp_verify_nonce($_REQUEST['pms_nonce'], 'pms-user-own-subsite-deletion') && isset($_REQUEST['pms_user']) && get_current_user_id() == $_REQUEST['pms_user']){
            $user_id = $_REQUEST['pms_user'];
	    //error_log(print_r('theusershouldbe: '.$user_id,true));
            $subsite_id = get_user_meta( $user_id, 'user_blog',true);
	    //error_log('userd'. $user_id);
	    //error_log('sited'. $subsite_id);
            bv_remove_subsite_subscription($user_id,$subsite_id);
            redirect($_SERVER["HTTP_REFERER"]);
            exit;
        }
        else{
        }
    }
    add_action('init', 'temp_delete_subsite');
}
