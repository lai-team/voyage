<?php

defined( 'ABSPATH' ) || exit;

/*
 * This file used to `require_once wp-load.php` here, from inside a plugin that
 * WordPress had already loaded via wp-load.php. It was circular and only
 * harmless because of wp-load's own ABSPATH guard. The guard above is the
 * correct way to make a plugin include non-addressable directly.
 */

// Handle subsite registration
if(isset($_POST['register_blog']) && isset($_POST['site_url']) && isset($_POST['title']) && isset($_POST['_wp_nonce']) ){
    function temp_create_subsite(){
        // Check if user is logged in then verify the nonce value to make sure this is a valid request
        if(is_user_logged_in() && wp_verify_nonce($_POST['_wp_nonce'], 'pms-user-own-subsite-creation_' . get_current_user_id())){
            $user_id = get_current_user_id();

            /*
             * Re-assert membership server-side. The only gate was in the
             * renderer -- shortcode_subscription() emits the form once
             * bv_get_required_level_status() passes -- so a nonce minted while
             * a plan was active stayed usable for up to 48 hours after it
             * lapsed, and the form could be rendered by anything that runs the
             * shortcode. bv_create_subsite() goes on to grant the user an
             * editor role on a new network site, so the check belongs here too.
             */
            // bv_get_required_level_status() is defined inside a
            // function_exists('pms_get_member_subscriptions') block in
            // voyage.php, so guard rather than introduce a new fatal if PMS is
            // ever deactivated.
            if ( ! function_exists( 'bv_get_required_level_status' )
                || ! bv_get_required_level_status( $user_id, BASIC_ID ) ) {
                pms_errors()->add( 'url', __( 'An active subscription is required to create a site.', 'paid-member-subscriptions' ) );
                return;
            }


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

/*
 * Handle subsite deletion.
 *
 * POST only. This was previously reachable over GET from a fully-formed URL
 * that the plugin printed into every front-end page, so a link prefetcher, a
 * scanner or an unfurling bot could destroy a member's site with no
 * interaction — the "type DELETE to confirm" step is client-side only
 * (assets/js/front-end.js).
 */
if ( isset( $_POST['pms_action'] ) && 'pms_delete_subsite' === $_POST['pms_action'] ) {
    function temp_delete_subsite() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = isset( $_POST['pms_user'] ) ? absint( $_POST['pms_user'] ) : 0;
        $nonce   = isset( $_POST['pms_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pms_nonce'] ) ) : '';

        // The nonce action is now bound to the user, matching the creation
        // handler above, so it is not a single shared string per site.
        if ( ! $user_id || get_current_user_id() !== $user_id ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'pms-user-own-subsite-deletion_' . $user_id ) ) {
            return;
        }

        $subsite_id = absint( get_user_meta( $user_id, 'user_blog', true ) );

        $result = bv_remove_subsite_subscription( $user_id, $subsite_id );

        if ( is_wp_error( $result ) ) {
            pms_errors()->add( 'subsite_delete', $result->get_error_message() );
            return;
        }

        wp_safe_redirect( home_url() );
        exit;
    }
    add_action( 'init', 'temp_delete_subsite' );
}
