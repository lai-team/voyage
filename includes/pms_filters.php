<?php

/**
 * Function that outputs the subscription plans
 *
 * Warning: Should not be used by other plugin developers as it is subject to change
 *
 * @param array $include            - return only these subscription plans
 * @param array $exclude_id_group   - exclude the groups that have these ids
 * @param mixed $member             - bool false to display input fields, object PMS_Member to display member information
 * @param int $default_checked      - default subscription plan to be selected
 *
 * @return string
 *
 */
add_filter( 'pms_output_subscription_plans','bv_pms_output_subscription_plans', 10,7 );
function bv_pms_output_subscription_plans( $output, $include , $exclude_id_group , $member , $pms_settings, $subscription_plans, $form_location ) {
    $default_checked = BASIC_ID;

    switch_to_blog(get_main_site_id());
    $output = '';
    $pms_settings = get_option( 'pms_payments_settings' );


    // Get all subscription plans
    if( empty( $include ) )
        // Called with no arguments against a signature whose first two
        // parameters are required -- an ArgumentCountError, i.e. fatal, on any
        // bare [pms-register], [pms-new-subscription] or upgrade form.
        $subscription_plans = bv_pms_get_subscription_plans( array(), true );
    else {
        if( !is_object( $include[0] ) )
            $subscription_plans = bv_pms_get_subscription_plans([], true, $include );
            //$subscription_plans = pms_get_subscription_plans( true, $include );
        else
            $subscription_plans = $include;
    }


    /*
     * Group subscription plans
     */
    $subscription_plan_groups = array();

    if( !empty( $subscription_plans ) ) {
        foreach( $subscription_plans as $subscription_plan ) {
            $subscription_plan_groups[ $subscription_plan->top_parent ][] = $subscription_plan;
        }
    }


    /*
     * Exclude certain groups like the ones the member is already subscribed to
     */
    if( !empty( $exclude_id_group ) ) {
        foreach( $exclude_id_group as $exclude_id ) {

            if( !empty( $subscription_plans ) ) {
                foreach( $subscription_plans as $subscription_plan ) {

                    if( $subscription_plan->id == $exclude_id ) {
                        if( isset( $subscription_plan_groups[ $subscription_plan->top_parent ] ) )
                            unset( $subscription_plan_groups[ $subscription_plan->top_parent ] );
                    }

                }
            }

        }
    }
    /*
     * Display the information for each plan
     */
    if( !empty( $subscription_plan_groups ) ) {

        if( !$member && count( $subscription_plan_groups ) == 1 && count( $subscription_plan_groups[ key($subscription_plan_groups) ] ) == 1 ) {

            $subscription_plan = $subscription_plan_groups[ key($subscription_plan_groups) ][0];

            // Output subscription plan wrapper
            $subscription_plan_output = '<div class="pms-subscription-plan pms-hidden">';

            // Output subscription plan hidden input and label
            $subscription_plan_output .= '<input type="hidden" name="subscription_plans" ' . pms_get_subscription_plan_input_data_attrs( $subscription_plan ) . ' value="' . esc_attr( $subscription_plan->id ) . '" />';
            $subscription_plan_output .= '<label><span class="pms-subscription-plan-name">' . $subscription_plan->name . '</span>';

                // Output subscription plan price
                $subscription_plan_output .= '<span class="pms-subscription-plan-price">' . pms_get_output_subscription_plan_price( $subscription_plan ) . '</span>';

                if( in_array( $form_location, array( 'register', 'new_subscription', 'retry_payment', 'upgrade_subscription', 'register_email_confirmation', 'wppb_register' ) ) ) {

                    // Output subscription plan trial
                    $subscription_plan_output .= '<span class="pms-subscription-plan-trial">' . pms_get_output_subscription_plan_trial( $subscription_plan ) . '</span>';

                    if( $form_location != 'upgrade_subscription' ){

                        // Output subscription plan sign-up
                        $subscription_plan_output .= '<span class="pms-subscription-plan-sign-up-fee">' . pms_get_output_subscription_plan_sign_up_fee( $subscription_plan ) . '</span>';
                    }

                }

            $subscription_plan_output .= '</label>';

            // Description
            if( !empty($subscription_plan->description) )
                $subscription_plan_output .= '<div class="pms-subscription-plan-description">' . apply_filters( 'pms_output_subscription_plan_description', htmlspecialchars_decode( esc_html( $subscription_plan->description ) ), $subscription_plan )  . '</div>';

            $subscription_plan_output .= '</div>';

            // Modify the entire subscription plan output if desired
            $output .= apply_filters( 'pms_subscription_plan_output', $subscription_plan_output, $subscription_plan );

        } else {

            $current_group = 1;
            $group_count = count( $subscription_plan_groups );

            $default_checked = ( ! empty( $_REQUEST['subscription_plans'] ) ? (int)$_REQUEST['subscription_plans'] : (int)$default_checked );
            $default_checked = ( ! empty( $default_checked ) ? $default_checked : $subscription_plans[0]->id );
            $default_checked = ( ! empty( $_GET['subscription_plan'] ) ? (int)$_GET['subscription_plan'] : (int)$default_checked );
            $default_checked = ( ! empty( $_GET['upgrade_subscription_plan'] ) ? (int)$_GET['upgrade_subscription_plan'] : (int)$default_checked );

            if( $form_location == 'upgrade_subscription' && isset( $subscription_plan_groups[key( $subscription_plan_groups )][0]->id ))
                $default_checked = $subscription_plan_groups[key( $subscription_plan_groups )][0]->id;

            $default_checked = apply_filters( 'pms_output_subscription_default_checked', $default_checked );

            foreach( $subscription_plan_groups as $top_parent_id => $subscriptions ) {

                /*
                 * Output subscription plan fields for forms
                 */
                if( !$member ) {

                    foreach( $subscriptions as $subscription_plan ) {

                        // Output subscription plan wrapper
                        $subscription_plan_output = '<div class="pms-subscription-plan pms-subscription-plan-'. $subscription_plan->id .'">';

                        // Output subscription plan radio button and label
                        $subscription_plan_output .= '<label>';
                        $subscription_plan_output .= '<input type="radio" name="subscription_plans" ' . pms_get_subscription_plan_input_data_attrs( $subscription_plan ) . ' value="' . esc_attr( $subscription_plan->id ) . '" ' .  checked( $default_checked, $subscription_plan->id, false ) . ( $default_checked == $subscription_plan->id ? 'data-default-selected="true"' : 'data-default-checked="false"' ) . ' />';

                            $subscription_plan_output .= '<span class="pms-subscription-plan-name">' . apply_filters( 'pms_output_subscription_plan_name', esc_html( $subscription_plan->name ), $subscription_plan ) . '</span>';

                            // Output subscription plan price
                            $subscription_plan_output .= '<span class="pms-subscription-plan-price">' . pms_get_output_subscription_plan_price( $subscription_plan ) . '</span>';

                            if( in_array( $form_location, array( 'register', 'new_subscription', 'retry_payment', 'upgrade_subscription', 'register_email_confirmation', 'wppb_register' ) ) ) {

                                // Output subscription plan trial
                                $subscription_plan_output .= '<span class="pms-subscription-plan-trial">' . pms_get_output_subscription_plan_trial( $subscription_plan ) . '</span>';

                                if( $form_location != 'upgrade_subscription' ){

                                    // Output subscription plan sign-up
                                    $subscription_plan_output .= '<span class="pms-subscription-plan-sign-up-fee">' . pms_get_output_subscription_plan_sign_up_fee( $subscription_plan ) . '</span>';
                                }
                            }

                        $subscription_plan_output .= '</label>';

                        // Description
                        if( !empty($subscription_plan->description) )
                            $subscription_plan_output .= '<div class="pms-subscription-plan-description">' . apply_filters( 'pms_output_subscription_plan_description', htmlspecialchars_decode( esc_html( $subscription_plan->description ) ), $subscription_plan )  . '</div>';

                        $subscription_plan_output .= '</div>';

                        // Modify the entire subscription plan output if desired
                        $output .= apply_filters( 'pms_subscription_plan_output', $subscription_plan_output, $subscription_plan );

                    }

                }

                $current_group++;
            }

        }

    }

    // Add error message if no plans have been selected
    if( !$member )
        $output .= pms_display_field_errors( pms_errors()->get_error_messages('subscription_plans'), true );

    //return apply_filters( 'pms_output_subscription_plans', $output, $include, $exclude_id_group, $member, $pms_settings, $subscription_plans, $form_location );
    restore_current_blog();
    return $output;

}
/**
 * Returns the HTML output for the subscription plan trial period
 *
 * @param PMS_Subscription_Plan $subscription_plan
 *
 * @return string
 *
 */
add_filter( 'pms_subscription_plan_output_trial','bv_pms_get_output_subscription_plan_trial', 20, 2 );
function bv_pms_get_output_subscription_plan_trial( $trial_output, $subscription_plan ) {

    if( is_null( $subscription_plan ) )
        return '';

    if( ! is_object( $subscription_plan ) )
        return '';

    if( empty( $subscription_plan->trial_duration ) )
        return '';

    switch_to_blog(get_main_site_id());
    // if current user already benefited from the trial on this plan, do not add it again
    if( is_user_logged_in() ){
        $user = get_userdata( get_current_user_id() );

        if( !empty( $user->user_email ) ){

            $used_trial = get_option( 'pms_used_trial_' . $subscription_plan->id, false );

            // in_array() needs an array; a non-array stored option is a
            // TypeError on PHP 8.
            if( $used_trial !== false && is_array( $used_trial ) && in_array( $user->user_email, $used_trial ) ) {
                restore_current_blog();
                return '';
            }

        }
    }

    if( ! pms_payment_gateways_support( pms_get_active_payment_gateways(), 'subscription_free_trial' ) ) {
        restore_current_blog();
        return '';
    }

    $trial_duration      = $subscription_plan->trial_duration;
    $trial_duration_unit = '';

    switch ( $subscription_plan->trial_duration_unit ) {
        case 'day':
            $trial_duration_unit = __( 'day', 'paid-member-subscriptions' );
            break;
        case 'week':
            $trial_duration_unit = __( 'week', 'paid-member-subscriptions' );
            break;
        case 'month':
            $trial_duration_unit = __( 'month', 'paid-member-subscriptions' );
            break;
        case 'year':
            $trial_duration_unit = __( 'year', 'paid-member-subscriptions' );
            break;
        default:
            $trial_duration_unit = '';
            break;
    }
    // Actual output
    $trial_output = sprintf( __( ' with a %1$s %2$s free trial', 'paid-member-subscriptions' ), $trial_duration, $trial_duration_unit );

    restore_current_blog();
    /**
     * Filter the trial output before returning
     *
     * @param string $trial_output
     * @param PMS_Subscription_Plan $subscription_plan
     *
     */
    //$trial_output = apply_filters( 'pms_subscription_plan_output_trial', $trial_output, $subscription_plan );

    // Return output
    return $trial_output;

}

/**
 * Returns the HTML output for the subscription plan sign-up fee
 *
 * @param PMS_Subscription_Plan $subscription_plan
 *
 * @return string
 *
 */
add_filter( 'pms_subscription_plan_output_sign_up_fee','bv_pms_get_output_subscription_plan_sign_up_fee',20, 2  );
function bv_pms_get_output_subscription_plan_sign_up_fee( $sign_up_output, $subscription_plan  ) {

    if( is_null( $subscription_plan ) )
        return '';

    if( ! is_object( $subscription_plan ) )
        return '';

    if( empty( $subscription_plan->sign_up_fee ) )
        return '';

    switch_to_blog(get_main_site_id());
    if( ! pms_payment_gateways_support( pms_get_active_payment_gateways(), 'subscription_sign_up_fee' ) ) {
        restore_current_blog();
        return '';
    }

    $sign_up_output = sprintf( __( ' and a %1$s sign-up fee', 'paid-member-subscriptions' ), pms_format_price( $subscription_plan->sign_up_fee, pms_get_active_currency() ), $subscription_plan );
    restore_current_blog();

    /**
     * Filter the sign-up output before returning
     *
     * @param string $trial_output
     * @param PMS_Subscription_Plan $subscription_plan
     *
     */
    //$sign_up_output = apply_filters( 'pms_subscription_plan_output_sign_up_fee', $sign_up_output, $subscription_plan );

    return $sign_up_output;

}

/**
 * Returns an array of PMS_Subscription_Plan objects that are possible upgrades for the given
 * subscription_plan_id
 *
 * @param int  $subscription_plan_id - the id of the subscription plan for which we want to receive the possible upgrades
 * @param bool $only_active          - whether to return only active subscription plans or no
 *
 */
add_filter( 'pms_get_subscription_plan_upgrades', 'bv_pms_get_subscription_plan_upgrades', 20,3 );
function bv_pms_get_subscription_plan_upgrades( $subscription_plans, $subscription_plan_id, $only_active ) {
    switch_to_blog(get_main_site_id());

    $current_post   = get_post( $subscription_plan_id );
    $parent_post_id = $current_post->post_parent;

    $subscription_plan_posts = array();

    while( $post_ancestor = get_post( $parent_post_id ) ) {

        $parent_post_id = $post_ancestor->post_parent;
        $subscription_plan_posts[] = $post_ancestor;

        if( empty( $post_ancestor->post_parent ) )
            break;

    }

    $subscription_plans = array();

    if( !empty( $subscription_plan_posts ) ) {
        foreach( $subscription_plan_posts as $subscription_plan_post ) {

            $subscription_plan = pms_get_subscription_plan( $subscription_plan_post );

            if( $only_active && !$subscription_plan->is_active() )
                continue;

            $subscription_plans[] = $subscription_plan;

        }
    }

    restore_current_blog();
    return $subscription_plans;
    /**
     * Filter the subscription plans available for upgrade just before returning them
     *
     * @param array $subscription_plans
     * @param int   $subscription_plan_id
     * @param bool  $only_active
     *
     */
    //return apply_filters( 'pms_get_subscription_plan_upgrades', $subscription_plans, $subscription_plan_id, $only_active );

}

/**
 * Returns all subscription plans into an array of objects
 *
 * @param $only_active   - true to return only active subscription plans, false to return all
 *
 * @return array
 *
 */
add_filter('pms_get_subscription_plans','bv_pms_get_subscription_plans',20,2);
function bv_pms_get_subscription_plans( $subscription_plans, $only_active,$include=array()  ) {
//error_log('subPlanDebug1: ' . json_encode($subscription_plans,true));

    switch_to_blog(get_main_site_id());

    /*
     * Reset rather than append. As a filter on pms_get_subscription_plans this
     * receives the list PMS has already built, and the loop at the end used to
     * push onto it -- so every consumer saw each plan twice: admin dropdowns,
     * the Discount Codes meta box, Email Reminders, the register form's
     * emptiness check. At priority 20 it also re-introduced plans that the
     * Fixed Period add-on had just filtered out at the same priority, quietly
     * defeating that add-on. The sibling function
     * bv_pms_get_subscription_plan_upgrades() already resets correctly.
     */
    $subscription_plans = array();
    $subscription_plan_post_ids = array();

    if( empty( $include ) ) {

        $subscription_plan_posts = get_posts( array('post_type' => 'pms-subscription', 'numberposts' => -1, 'post_status' => 'any' ) );

        $page_hierarchy_posts = get_page_hierarchy( $subscription_plan_posts );

        foreach( $page_hierarchy_posts as $post_id => $post_name ) {
            $subscription_plan_post_ids[] = $post_id;
        }
//error_log('subPlanDebug1: ' . json_encode($subscription_plans,true));

    } else {

        $subscription_plan_posts = get_posts( array('post_type' => 'pms-subscription', 'numberposts' => -1, 'include' => $include, 'orderby' => 'post__in', 'post_status' => 'any' ) );
        $subscription_plan_post_ids = $subscription_plan_posts;

    }

    // Return if we don't have any plans by now
    if( empty( $subscription_plan_post_ids ) ) {
        restore_current_blog();
        return $subscription_plans;
    }

//error_log('subPlanDebug: ' . json_encode($subscription_plan_post_ids,true));
    foreach( $subscription_plan_post_ids as $subscription_plan_post_id ) {
        $subscription_plan = pms_get_subscription_plan( $subscription_plan_post_id );

        if( $only_active && !$subscription_plan->is_active() )
            continue;

        $subscription_plans[] = $subscription_plan;
    }

    restore_current_blog();
    return $subscription_plans;
    //return apply_filters( 'pms_get_subscription_plans', $subscription_plans, $only_active );

}

add_filter('pms_member_account_not_member','bv_pms_mainaccounturl',10,2);
function bv_pms_mainaccounturl($message,$member){
//Fri Jul 16 15:21:51 CEST 2021
        $register_page = esc_url( pms_get_page( 'register', true ) );
	$account_page = esc_url( pms_get_page( 'account', true ) );
	$main_tab = defined('MAIN_TAB_NAME')?MAIN_TAB_NAME:'';
	return str_replace( '<a href="'.$register_page.'">',  '<a href="' . $account_page .$main_tab .'/">', $message  );
}

add_filter('pms_output_subscription_plan_action_renewal_time', 'pmsc_modify_renewal_action_output_time');
function pmsc_modify_renewal_action_output_time() {
    return 30;
}
