<?php

remove_action('admin_init','add_admin_menu_notification_counts');
add_action('admin_menu','bv_editor_menu',100);
function bv_editor_menu(){
	$current_user=wp_get_current_user();
	//error_log(print_r('currentUser: '. json_encode($current_user),true));
	$user_role = $current_user->roles;
	if( !in_array( strtolower('administrator'), $user_role ) ){
		remove_submenu_page('edit.php?post_type=tribe_events','edit.php?post_type=tribe_organizer' );  
		remove_submenu_page('edit.php?post_type=tribe_events','edit.php?post_type=tribe_venue' );  
		remove_submenu_page('edit.php?post_type=tribe_events','tribe-common' );  
		remove_submenu_page('edit.php?post_type=tribe_events','tribe-help' );  
		remove_menu_page('edit.php?post_type=ep-pointer');

		//remove_submenu_page('options-general.php','translate-press' );  
		//remove_menu_page( 'options-general.php'  );
		//add_options_page( 'Settings', 'Setting', 'publish_posts', 'options-general.php', '', 1 );
		//   add_menu_page( "Settings", "General", "publish_posts", "options-general.php");

		//publish_posts
	}
	if($current_user->ID <=3) $current_user->add_cap('manage_privacy_options');
}

function admin_style() {
	wp_enqueue_style('admin-styles', __DIR__.'/../assets/css/admin.css');
	//error_log(print_r(file_get_contents(__DIR__. '/../assets/css/admin.css'),true));
}
add_action('admin_enqueue_scripts', 'admin_style');

remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
add_action( 'admin_head', function(){
	ob_start(); ?>
    <style>
	#your-profile > h2,
	.user-rich-editing-wrap,
	.user-syntax-highlighting-wrap,
	.user-comment-shortcuts-wrap,
	.user-admin-bar-front-wrap {
	    display: none;
	}
    </style>
<?php ob_end_flush();
});

function enqueue_gutenberg_js() {   
	wp_enqueue_script(    
		'replace-animation-script',    
		plugins_url( 'assets/js/whitelabel.js', __FILE__ ), 
		array( 'wp-element', 'wp-editor', 'wp-hooks' ),   
		filemtime( __DIR__ . '/assets/js/whitelabel.js' )
	);
}

//add_action( 'enqueue_block_editor_assets',  'enqueue_gutenberg_js' );

function cc_gutenberg_register_files() {                                                                                                                                                                    // script file                                                                                                                                                                                          wp_register_script(                                                                                                                                                                                         'cc-block-script',                                                                                                                                                                                              plugins_url( 'assets/js/block-script.js', __FILE__ ),                                                                                                                                           //get_stylesheet_directory_uri() .'assets/js/block-script.js', // adjust the path to the JS file                                                                                                        array( 'wp-blocks', 'wp-edit-post' )                                                                                                                                                                );                                                                                                                                                                                                      // register block editor script                                                                                                                                                                         register_block_type( 'cc/ma-block-files', array(                                                                                                                                                            'editor_script' => 'cc-block-script'                                                                                                                                                                ) );

}
add_action( 'init', 'cc_gutenberg_register_files' ); 

add_action('in_admin_header', function () {
//	if (!$is_my_admin_page) return;
	remove_all_actions('admin_notices');
	remove_all_actions('all_admin_notices');
	//add_action('admin_notices', function () {
	//echo 'My notice';
	//});
}, 1000);

function remove_screen_options($display_boolean, $wp_screen_object){
	$blacklist = array('post.php', 'post-new.php', 'index.php', 'edit.php');
	if (in_array($GLOBALS['pagenow'], $blacklist)) {
		$wp_screen_object->render_screen_layout();
		$wp_screen_object->render_per_page_options();
		return false;
	} else {
		return true;
	}
}
#add_filter('screen_options_show_screen', 'remove_screen_options', 10, 2);
add_action('admin_head', 'mytheme_remove_help_tabs');
function mytheme_remove_help_tabs() {
	$screen = get_current_screen();
	$screen->remove_help_tabs();
}
add_filter('screen_options_show_screen', '__return_false');

//add_filter( 'dashboard_glance_items', 'custom_glance_items', 20, 1 );

function custom_glance_items( $items = array() ) {

	// $post_types = array( 'post_type_1', 'post_type_2' );
    /*
    foreach( $post_types as $type ) {

	if( ! post_type_exists( $type ) ) continue;

	$num_posts = wp_count_posts( $type );

	if( $num_posts ) {

	    $published = intval( $num_posts->publish );
	    $post_type = get_post_type_object( $type );

	    $text = _n( '%s ' . $post_type->labels->singular_name, '%s ' . $post_type->labels->name, $published, 'your_textdomain' );
	    $text = sprintf( $text, number_format_i18n( $published ) );

	    if ( current_user_can( $post_type->cap->edit_posts ) ) {
		$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $type, $text ) . "\n";
	    } else {
		$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $type, $text ) . "\n";
	    }
	}
    }
     */
	error_log(print_r('glance '.json_encode($items),true));
	return $items;
}
