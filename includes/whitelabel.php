<?php

/*
 * The `remove_action('admin_init','add_admin_menu_notification_counts')` that
 * used to be here never matched anything. TranslatePress registers that
 * callback as an object method at priority 1000
 * (translatepress-multilingual/includes/class-plugin-notices.php:85), so a
 * string callback at the default priority 10 removes nothing -- and this file
 * is parsed before TRP has registered it anyway.
 */

/**
 * Whether the current user should see the full, un-white-labelled admin.
 *
 * Previously `! in_array( 'administrator', $current_user->roles )`. That is
 * wrong on multisite in a way that matters: a Super Admin with no explicit role
 * on a subsite has an empty roles array, so they were given the *restricted*
 * admin. Capability checks do not have that problem.
 *
 * @return bool
 */
function bv_user_sees_full_admin() {
	return is_super_admin() || current_user_can( 'manage_network' ) || current_user_can( 'activate_plugins' );
}

add_action('admin_menu','bv_editor_menu',100);
function bv_editor_menu(){
	$current_user=wp_get_current_user();
	if( ! bv_user_sees_full_admin() ){
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
	// Grants a persistent capability based on a magic user-id range. Left in
	// place because removing it may take away a control someone relies on, but
	// WP_User::add_cap() writes to usermeta on every admin_menu load, and the
	// grant follows whoever happens to hold ids 1-3. Recorded in README.md.
	if ( $current_user->ID && $current_user->ID <= 3 ) {
		$current_user->add_cap('manage_privacy_options');
	}
}

function admin_style() {
	// __DIR__ is a filesystem path. Passed as a stylesheet src it was prefixed
	// with the site URL, so admin.css 404'd on every admin page -- meaning the
	// white-labelling CSS it exists for has never actually applied -- and the
	// server's absolute path was printed into the HTML.
	wp_enqueue_style(
		'admin-styles',
		plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
		array(),
		filemtime( dirname( __DIR__ ) . '/assets/css/admin.css' )
	);
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
		// __FILE__ is includes/whitelabel.php, so this resolved to
		// includes/assets/js/… -- a directory that does not exist.
		plugins_url( 'assets/js/whitelabel.js', dirname( __FILE__ ) ),
		array( 'wp-element', 'wp-editor', 'wp-hooks' ),
		filemtime( dirname( __DIR__ ) . '/assets/js/whitelabel.js' )
	);
}

//add_action( 'enqueue_block_editor_assets',  'enqueue_gutenberg_js' );

function cc_gutenberg_register_files() {
	// script file
	wp_register_script(
		'cc-block-script',
		// __FILE__ is includes/whitelabel.php, so this used to resolve to
		// includes/assets/js/block-script.js -- a path that does not exist, so
		// the script 404'd and never ran.
		plugins_url( 'assets/js/block-script.js', dirname( __FILE__ ) ),
		array( 'wp-blocks', 'wp-edit-post' ),
		filemtime( dirname( __DIR__ ) . '/assets/js/block-script.js' )
	);

	// register block editor script
	register_block_type( 'cc/ma-block-files', array(
		'editor_script' => 'cc-block-script'
	) );
}
add_action( 'init', 'cc_gutenberg_register_files' );

add_action('in_admin_header', function () {
	/*
	 * Scoped to the users actually being white-labelled. This ran
	 * unconditionally, so it also suppressed core security and update
	 * warnings, plugin vulnerability notices and database-upgrade prompts for
	 * administrators and super admins -- the people who need to see them.
	 */
	if ( bv_user_sees_full_admin() ) {
		return;
	}

	remove_all_actions('admin_notices');
	remove_all_actions('all_admin_notices');
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

	// get_current_screen() returns null in admin contexts where the screen has
	// not been set, and calling a method on null is a fatal on PHP 8 -- a
	// white-screen admin page rather than a warning.
	if ( ! $screen ) {
		return;
	}

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
