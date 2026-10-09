<?php 
function bv_register_subsite_form() {

    // Both of these are interpolated into the heredoc below unconditionally but
    // were only assigned inside their respective conditionals, so the common
    // case -- a consenting user with no validation errors -- emitted two
    // "Undefined variable" warnings on every render.
    $error_show   = '';
    $user_consent = '';

    // Checks for pms errors
    $errors = pms_errors()->get_error_messages('url');
    if($errors)
        $error_show = pms_display_field_errors( $errors, true ); // Holds errors
    
    // nonce value
    $nonce= wp_create_nonce( "pms-user-own-subsite-creation_" . get_current_user_id());

    // GDRP checkbox
    if(!get_user_meta( get_current_user_id(),'pms_gdpr_user_consent', true)){ // Checks if the users has consented already;
        ob_start();
        $gdpr_settings = pms_get_gdpr_settings(); // Takes GDPR settings, if GDPR is disabled in wp-admin this will be 'false'
        if( !empty( $gdpr_settings ) ){
            if( !empty( $gdpr_settings['gdpr_checkbox'] ) && $gdpr_settings['gdpr_checkbox'] === 'enabled' ){

                $field_errors = pms_errors()->get_error_messages('user_consent');// Holds errors ?>
                <span class="pms-field pms-gdpr-field <?php echo ( !empty( $field_errors ) ? 'pms-field-error' : '' ); ?>">
                    <label for="pms_user_consent">
                        <input id="pms_user_consent" name="user_consent" type="checkbox" value="1">
                        <?php echo ( isset($gdpr_settings['gdpr_checkbox_text']) ? wp_kses_post( str_replace( '{{privacy_policy}}', get_the_privacy_policy_link(), $gdpr_settings['gdpr_checkbox_text'] ) ) : __( 'I allow the website to collect and store the data I submit through this form. *', 'paid-member-subscriptions' ) ); ?>
                    </label>

                    <?php pms_display_field_errors( $field_errors );// Displays errors ?>
                </span>
                <?php
            }
        }
    $user_consent = ob_get_contents(); // Holds the entire HTML for user consent checkbox
    ob_end_clean();
    }
    
// Returns HTMl
return <<<HTML
    <form class='form-horizontal' action='' method='POST'>
        <fieldset class='p-3 p-md-5'>
        <input type="hidden" name='_wp_nonce' value="$nonce">
            <div class='control-group'>
                <!-- Site URL -->
                <label class='control-label' for='site_url'>Site Address (URL) </label>
                <div class='controls'>
                    <div style="display:flex;">
                    <input type='text' id='site_url' name='site_url' placeholder='' class='input-xlarge' style="width:auto">
                    <span>.beau.voyage</span>
                    </div>
                    $error_show
                    <p class='help-block'>Enter the URL / Path to your new site</p>
                </div>
            </div>
            <div class='control-group'>
                <!-- Site Title -->
                <label class='control-label' for='title'>Site Title </label>
                <div class='controls'>
                    <input type='text' id='title' name='title' placeholder='' class='input-xlarge w-100'>
                    <p class='help-block'>Enter the Title of your new site</p>
                </div>
            </div>
            $user_consent
            <div class='control-group'>
                <!-- Button -->
                <div class='controls'>
                    <input type='submit' name='register_blog' id='submit-btn' class='btn btn-outline-primary' style='width:auto;font-size:22px;margin-right:15px' value='Create Site'>
                </div>
            </div>
        </fieldset>
    </form>
HTML;
}
// Adds the shortcode
add_shortcode( 'bv_register_subsite_form', 'bv_register_subsite_form' );