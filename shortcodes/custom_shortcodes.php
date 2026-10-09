<?php 
function is_user_account_confirmed_shortcode($atts,$content){
    if(!is_user_account_confirmed(get_current_user_id())){
        $user_email=wp_get_current_user()->user_email;
        return "<div class='w-100 p-5 bg-warning'><h3 class='text-danger'>You need to verify your account, please check your email: $user_email</h3></div>"; 
    }
    return do_shortcode( $content );
}
add_shortcode("is_user_account_confirmed_shortcode","is_user_account_confirmed_shortcode");


function delete_user_button(){
    if(is_user_logged_in()){
        return '<a class="small text-danger ml-1" href="/delete-account"> Delete User </a>';
    }
}
add_shortcode("delete_user_button","delete_user_button");

// function delete_subsite_button(){
//     if(is_user_logged_in() && get_user_blog_id(get_current_user_id()) != get_main_site_id()){
//         return "<form method='POST'><button name='delete_blog' value='$user_id' type='submit' id='delete-subsite-btn'>Delte Subsite</button></form>";
//     }
// }
// add_shortcode("delete_subsite_button","delete_subsite_button");
