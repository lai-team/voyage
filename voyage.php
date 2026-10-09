<?php
/**
 * Plugin Name: beauVoyage - Baisc functions
 * Plugin URI: https://beau.voyage/
 * Description: Providing functions to beauVoyage
 * Version: 0.1
 * Author: lai.consulting team
 * Author URI: http://lai.consulting/
 * GitLab Plugin URI: https://gitlab.com/beauvoyage/voyage
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
#$premium_id=55;
#$basic_id=456;


#var_dump(get_attached_media('image',4));
//foreach(get_attached_media('image',4) as $image1)
//error_log(print_r('images: ' . $image1->ID,true));
//define("WP_GEOMETA_DEBUG",2);
//define("BASIC_ID",11);
//define("PREMIUM_ID",12);
//define("PMS_GROUP_NAME","Private circle");
define("MAIN_TAB_NAME",'main');
define("BV_PLUGIN_DIR_URL",plugin_dir_url( __FILE__ ) );
//define("BV_PLUGIN_DIR",plugin_dir_path( __FILE__ ) );
//define("MIN_LENGTH_URL",7);

require_once  __DIR__ . '/shortcodes/shortcodes.php';
include_once(ABSPATH . WPINC . '/ms-functions.php' );
include_once(ABSPATH . 'wp-admin/includes/ms.php' );
include_once( __DIR__ . '/includes/handle_requests.php');
include_once( __DIR__ . '/includes/elasticpress_aws.php');
include_once( __DIR__ . '/includes/acf_metadata.php');
include_once( __DIR__ . '/includes/acf_googlefilters.php');
include_once( __DIR__ . '/includes/tec_hooks.php');
include_once( __DIR__ . '/includes/whitelabel.php');
include_once( __DIR__ . '/includes/gutenbergblocks.php');
include_once( __DIR__ . '/includes/pms_actions.php');
include_once( __DIR__ . '/includes/pms_filters.php');
include_once( __DIR__ . '/includes/search_filter.php');
include_once( __DIR__ . '/includes/spatial_functions.php');
include_once( __DIR__ . '/includes/trp_filters.php');
include_once( __DIR__ . '/includes/cdn_rewrite.php');

// saving acp settings locally
add_filter( 'acp/storage/file/directory', function() { return __DIR__ . '/acp-settings'; } );
// Use a writable path, directory will be created for you
//return get_stylesheet_directory() . '/acp-settings';

//add_filter( 'acp/storage/file/directory/writable', '__return_false' );

/**
 * Apply this network's role customisations.
 *
 * Previously these six lines ran at file scope on every request, which had
 * three problems:
 *
 *  1. get_role() returns null when the role is absent (a subsite whose
 *     wp_N_user_roles option is missing, or a partial install). Calling
 *     ->add_cap() on that is a fatal on PHP 8, with no plugin loaded after it.
 *  2. WP_Role::add_cap() persists to the *current blog's* user_roles option,
 *     so this issued a database write on any request where the capability was
 *     not already present.
 *  3. Most importantly, because it ran on whichever blog served the request,
 *     editors on the main site were granted manage_options too. That is the
 *     capability gating Settings and most plugins' admin pages. The grant is
 *     meant for subsite owners -- bv_create_subsite() makes each owner an
 *     editor on their own site -- so it is now scoped to subsites only.
 *
 * Runs once per version rather than per request; bump BV_ROLE_CAPS_VERSION to
 * re-apply.
 */
define( 'BV_ROLE_CAPS_VERSION', 2 );

function bv_apply_role_caps() {
	if ( (int) get_option( 'bv_role_caps_version' ) === BV_ROLE_CAPS_VERSION ) {
		return;
	}

	$is_main_site = ( get_current_blog_id() === (int) get_main_site_id() );

	$role_editor = get_role( 'editor' );
	if ( $role_editor ) {
		// Subsite owners are editors on their own site and need to reach
		// Settings there. Editors on the main site are a different population
		// and must not inherit it.
		if ( $is_main_site ) {
			$role_editor->remove_cap( 'manage_options' );
		} else {
			$role_editor->add_cap( 'manage_options' );
		}

		$role_editor->remove_cap( 'edit_tribe_venues' );
		$role_editor->remove_cap( 'edit_tribe_organizers' );
	}

	/*
	 * NOTE, deliberately unchanged: bv_member_privilege() in
	 * includes/search_filter.php is literally
	 * current_user_can('read_private_posts'), so this grant is what makes the
	 * private-category gate pass -- for members here, and also for the bv_map
	 * REST endpoint. Because the capability is attached to the contributor
	 * *role* rather than to a PMS subscription, "is a paying member" currently
	 * evaluates to "is any contributor".
	 *
	 * Tightening that means deciding who should actually see member content,
	 * and getting it wrong locks paying members out of what they paid for. It
	 * is left exactly as it was and recorded in README.md instead.
	 */
	$role_contributor = get_role( 'contributor' );
	if ( $role_contributor ) {
		$role_contributor->add_cap( 'read_private_posts' );
	}

	update_option( 'bv_role_caps_version', BV_ROLE_CAPS_VERSION );
}
add_action( 'init', 'bv_apply_role_caps' );

function var_error_log( $object=null ){
	ob_start();                    // start buffer capture
	var_dump( $object );           // dump the values
	$contents = ob_get_contents(); // put the buffer into a variable
	ob_end_clean();                // end capture
	error_log( $contents );        // log contents of the result of var_dump( $object )
}

function logErrors ( $message ){
	error_log(print_r($message,true),0,ABSPATH . 'wp-content/debug.log');
}

function bv_childcat($journey,$catarr){
	if (! in_array( $journey, array_map( function($cat){ return $cat->term_id;}, $catarr)))return array();
	do{
		$journeys[]=$journey;
		$journey=max(
			array_map( function($cat) use(&$journeys){ 
				return ($cat->parent==$journeys[array_key_last($journeys)])?$cat->term_id:NULL;
			}, $catarr));
	} while(!is_null($journey) );
	return array_reverse($journeys);
}

function bv_parentcat($catarr){
	$offspring = array_filter( $catarr, function($cat) {return $cat->parent > 1;});
	$cat_ids=array_map( function($category){return $category->term_id;} , $catarr);
	do{
		$reducedcats=$cat_ids;
		$cat_ids=array_map( function($id) use(&$offspring){
	//		error_log(print_r('offspring '. json_encode($offspring),true));
			$parent=array_reduce( $offspring,function($carry,$item) use(&$id) {
	//			error_log(print_r('id '. $id . '=' . $item->term_id,true));
				$carry= $item->term_id==$id?$item->parent:$carry;
				return $carry;
			},$id);
			return $parent;
	       	} , $cat_ids);
	} while( $reducedcats!=$cat_ids);
	return array_values(array_unique($reducedcats));
	/*
	foreach ($catarr as $cat){
		if (! in_array($cat->parent,$cat_ids)){
			$refucedcat[]=$cat;
		}
	}
	//return $refucedcat;
	return  array_map( function($cat){ return $cat->term_id ;},$refucedcat);
	 */
}

function bv_userlocation(){
	if (function_exists('geoip_detect2_get_info_from_current_ip') ){       
		switch_to_blog(get_main_site_id());      
		//$user_location= geoip_detect2_get_info_from_ip(geoip_detect2_get_client_ip(), $locales = array('en'), $options = array())-> location;     
		$user_location= geoip_detect2_get_info_from_current_ip() -> location;
		restore_current_blog();
	}else{
		$user_location= (object) array('latitude'=>1,'longitude'=>-1);
	}
	return $user_location??NULL;
}

function richedit_wp_cloudfront () {
   add_filter('user_can_richedit','__return_true');
}

add_action( 'init', 'richedit_wp_cloudfront', 9 );

// wp_update_post( array('ID'=> 186, 'post_parent'=>178) );
//add_action( 'init', 'bv_change_post_object' );
add_action('registered_post_type', 'bv_change_post_object', 10, 2 );
// Change dashboard Posts to News
function bv_change_post_object($post_type,$pto) {
	// Return, if not post type posts
	if ($post_type != 'post' ) return;
	//if ($post_type != 'post' && $post_type != 'tribe_events') return;
	/*
	wp_update_term(1, 'category', array(
		  'name' => 'Standalone Post',
		    'slug' => 'standalone'
	    ));

	$tax_labels = array(
		'name' => _x( 'Journey', 'taxonomy general name' ),
		'singular_name' => _x( 'Journey', 'taxonomy singular name' ),
		'search_items' =>  __( 'Search Journeys' ),
		'all_items' => __( 'All Journeys' ),
		'parent_item' => __( 'Parent Journey' ),
		'parent_item_colon' => __( 'Parent Journey:' ),
		'edit_item' => __( 'Edit Journey' ), 
		'view_item' => __( 'View Journey' ),
		'update_item' => __( 'Update Journey' ),
		'add_new_item' => __( 'Add New Journey' ),
		'new_item_name' => __( 'New Journey Name' ),
		'not_found' => __( 'No journeys found' ),
		'no_terms' => __( 'No journeys' ),
		'menu_name' => __( 'Journey' ),
	);    
	 */

	global $wp_taxonomies;
	$wp_tax_obj=$wp_taxonomies['category'];
	//$wp_tax_obj->labels = (object) $tax_labels;
	$wp_tax_obj->query_var = true;
	/*
	$tax_labels=$wp_tax_obj->labels;
	$tax_labels->name=__('Journey');
	$tax_labels->singular_name=__('Journey');
	$tax_labels->search_items=__('Search journeys');
	$tax_labels->all_items=__('All Journeys');
	$tax_labels->parent_item=__('Parent Journey');
	$tax_labels->parent_item_colon=__('Parent Journey:');
	$tax_labels->edit_item=__('Edit Journey');
	$tax_labels->view_item=__('View Journey');
	$tax_labels->update_item=__('Update Journey');
	$tax_labels->all_new_item=__('Add New Journey');
	$tax_labels->new_item_name=__('New Journey Name');
	$tax_labels->not_found=__('No journeys found');
	$tax_labels->no_terms=__('No journeys');
	$tax_labels->menu_name=__('Journey');
	 */

	global $wp_post_types; 
	//error_log(print_r('post types: ' . json_encode($wp_post_types ),true));
	$get_post_type_post=$wp_post_types['post'];
	//$get_post_type = get_post_type_object('post');
	$ptp_labels = $get_post_type_post->labels;
	$ptp_labels->name = __('Stories');
	$ptp_labels->singular_name = __('Story');
	$ptp_labels->add_new = __('Add Story');
	$ptp_labels->add_new_item = __('Add Story');
	$ptp_labels->edit_item = __('Edit Story');
	$ptp_labels->new_item = __( 'Story');
	$ptp_labels->view_item = __('View Story');
	$ptp_labels->search_items = __('Search Stories');
	$ptp_labels->not_found = __('No Story found');
	$ptp_labels->not_found_in_trash = __('No Story found in Trash');
	$ptp_labels->all_items = __('All Stories');
	$ptp_labels->menu_name = __('Story');
	$ptp_labels->name_admin_bar = __('Story');
	//$labels->parent_item_colon= 'Journey:';
	$get_post_type_post->menu_icon = 'dashicons-location-alt';
	//$get_post_type->hierarchical =true;
	$get_post_type_post->show_in_rest = true;
	//$get_post_type_post->taxonomies = array('category','post_tag');
	//add_post_type_support( 'post', 'page-attributes' );   

}

function add_media_tags() {
	register_taxonomy_for_object_type( 
		'post_tag', 
		'attachment' 
	);
	/*
	register_taxonomy_for_object_type( 
		'category', 
		'attachment' 
	);
	 */
}
add_action( 'init' , 'add_media_tags' );

//add_action('registered_post_type', 'bv_make_posts_hierarchical', 10, 2 );

// Runs after each post type is registered
/*
function bv_make_posts_hierarchical($post_type, $pto){

    // Return, if not post type posts
    if ($post_type != 'post') return;

    // access $wp_post_types global variable
    global $wp_post_types;

    // Set post type "post" to be hierarchical
    $wp_post_types['post']->hierarchical = 1;

    // Add page attributes to post backend
    // This adds the box to set up parent and menu order on edit posts.
    add_post_type_support( 'post', 'page-attributes' );
}
 */
/**
 * Applies category to pages
 */
/*
function add_categories_to_pages() {
    register_taxonomy_for_object_type( 'category', 'page' );
    }
add_action( 'init', 'add_categories_to_pages' );
 */
/*
add_filter( 'register_post_type_args', 'change_hierarchy_support', 10, 2 );
function change_hierarchy_support( $args, $post_type ){

    if ($post_type === 'post') { // <-- enter desired post type here

	$args['hierarchical'] = false;
	remove_post_type_support($post_type,'page-attributes');
    }

    return $args;
}
 */

// /* Story Post Type Start */
// function create_posttype() {

//     $labels = array(
//         'name' => _x( 'beau', 'taxonomy general name' ),
//         'singular_name' => _x( 'Beau', 'taxonomy singular name' ),
//         'search_items' =>  __( 'Search Beau' ),
//         'all_items' => __( 'All beau' ),
//         'parent_item' => __( 'Parent beau' ),
//         'parent_item_colon' => __( 'Parent beau:' ),
//         'edit_item' => __( 'Edit Topic' ), 
//         'update_item' => __( 'Update beau' ),
//         'add_new_item' => __( 'Add New Beau' ),
//         'new_item_name' => __( 'New Topic Name' ),
//         'menu_name' => __( 'Beau' ),
//       );    
//     register_taxonomy('beau',array('voyage'), array(
//         'hierarchical' => true,
//         'labels' => $labels,
//         'show_ui' => true,
//         'show_admin_column' => true,
//         'query_var' => true,
//         'rewrite' => array( 'slug' => 'beau' ),
//         'show_in_rest' => true,
//       ));

//     register_post_type( 'voyage',
//     // CPT Options
//     array(
//         'labels' => array(
//         'name' => 'voyage',
//         'singular_name' => 'Voyage',
//         ),
//         'public' => true,
//         'has_archive' => false,
//         'rewrite' => array('slug' => 'voyage'),
//         'supports' =>array( 'page-attributes', 'title', 'editor', 'thumbnail' ), 
//         'hierarchical'  =>true,
//         'show_in_rest' => true,
//         'taxonomies' => array('beau')
//         )
//     );
// }
// // Hooking up our function to theme setup
// add_action( 'init', 'create_posttype' );
/* Story Post Type End */


add_filter( 'pms_member_account_tabs', 'bv_dashicon_account_tabs', 100, 2 );
add_filter( 'pms_member_account_logout_tab', '__return_false', 10, 1 );
function bv_dashicon_account_tabs($tabs,$args){
        if (array_key_exists(MAIN_TAB_NAME."",$tabs)) $tabs[MAIN_TAB_NAME.""]="<span class=\"dashicons dashicons-feedback\">Main</span>";
        if (array_key_exists("subscriptions",$tabs)) $tabs["subscriptions"]="<span class=\"dashicons dashicons-cart\">Subscriptions</span>";
        if (array_key_exists("profile",$tabs)) $tabs["profile"]="<span class=\"dashicons dashicons-id-alt\">Edit Profile</span>";
        if (array_key_exists("payments",$tabs)) $tabs["payments"]="<span class=\"dashicons dashicons-clipboard\">Invoices</span>";
        //if (array_key_exists("logout",$tabs)) $tabs["logout"]="<span class=\"dashicons dashicons-migrate\">Logout</span>";
        $tabs["logout"]="<span class=\"dashicons dashicons-migrate\">Logout</span>";

        return $tabs;
}


// Add the main tab
add_filter( 'pms_member_account_tabs', 'pmsc_change_member_account_tabs', 20, 2 );
function pmsc_change_member_account_tabs( $tabs, $args ) {
	$tabs= array( MAIN_TAB_NAME."" =>__( 'Main', 'paid-member-subscriptions' )) + $tabs;

	return $tabs;
}

// Add content to the main tab
add_filter( 'pms_account_shortcode_content', 'pmsc_custom_tab_content', 20, 2 );
function pmsc_custom_tab_content( $output, $active_tab ) {
	if ( $active_tab == MAIN_TAB_NAME ) {

		$output .= do_shortcode( '[shortcode_subscription]' );
		$user_id = get_current_user_id();
		$blog_id = get_user_meta( $user_id, 'user_blog',true );
		$blog=get_blog_details($blog_id);
		if($blog_id)
			$output .= "<button name='delete_blog' value='$user_id' type='submit' id='delete-subsite-btn'>Delete Blog - ".$blog->blogname ."</button>";
	}

	return $output;
}


// Enqueue CSS
add_action('wp_enqueue_scripts', 'bv_enqueue_front_end_styles');
function bv_enqueue_front_end_styles(){
	wp_enqueue_style( 'bv_style', BV_PLUGIN_DIR_URL . '/assets/css/style.css');
}

// Enqueue Javascript
add_action('wp_enqueue_scripts', 'bv_enqueue_front_end_scripts');
function bv_enqueue_front_end_scripts() {

	// jQuery is a real dependency of this script and was not declared, so on any
	// page where jQuery loaded later the file threw on its first line.
	wp_enqueue_script(
		'bv-front-end',
		BV_PLUGIN_DIR_URL . 'assets/js/front-end.js',
		array( 'jquery' ),
		filemtime( __DIR__ . '/assets/js/front-end.js' ),
		true
	);

	/*
	 * Only mint the deletion credentials for someone who actually has a site to
	 * delete. This used to run for every logged-in visitor on every front-end
	 * page, publishing a ready-to-use URL and a valid nonce into the page
	 * source — which is how a member with no subsite could reach the destructive
	 * path at all. See bv_delete_user_subsite().
	 */
	$owned_site_id = is_user_logged_in()
		? absint( get_user_meta( get_current_user_id(), 'user_blog', true ) )
		: 0;

	$delete_url = '';
	$delete_nonce = '';

	if ( $owned_site_id > 1 ) {
		$delete_url   = add_query_arg( array(
			'pms_user'   => get_current_user_id(),
			'pms_action' => 'pms_delete_subsite',
		), home_url() );
		$delete_nonce = wp_create_nonce( 'pms-user-own-subsite-deletion_' . get_current_user_id() );
	}

	// Send variables to the javascript file
	wp_localize_script( 'bv-front-end', 'bvVar', array(
		'theme_dir_uri'     => get_template_directory_uri(),
		'delete_url'        => $delete_url,
		'delete_nonce'      => $delete_nonce,
		'delete_text'       => sprintf(__('Type %s to confirm deleting your subsite and all data associated with it:', 'paid-member-subscriptions'), 'DELETE' ),
		'delete_error_text' => sprintf(__('You did not type %s. Try again!', 'paid-member-subscriptions'), 'DELETE' ),
		'main_tab'          => MAIN_TAB_NAME,
		'user_id'           => get_current_user_id(),
		'user_email'        => is_user_logged_in() ? get_userdata( get_current_user_id() )->user_email : '',
	));
}

/*
 * The global redirect() helper that used to live here has been removed. It
 * called header('Location: …') on an unvalidated URL and its only live caller
 * passed $_SERVER['HTTP_REFERER'] straight into it — an open redirect. Call
 * sites now use wp_safe_redirect(), which restricts the destination to this
 * host. The name was also a collision risk in the global namespace.
 */

// /**
//  * Returns the user site/blog id
//  * @param int $user_id the user ID
//  * @return string|boolean the user blog id or if none False
//  */
function get_user_blog_id($user_id){   
	return get_user_meta( $user_id,'user_blog',true);
}

/**
 * Delete Subscription Plan
 *
 * @param $post_id
 *
 */
function bv_remove_subsite_subscription( $user_id, $site_id ) {
	$user_id = absint( $user_id );
	$site_id = absint( $site_id );
	$owned_id = absint( get_user_meta( $user_id, 'user_blog', true ) );

	// Both sides used to be compared loosely, so a user with no subsite passed
	// this check with '' == '' and went on to delete nothing-in-particular.
	// See bv_delete_user_subsite() for what that cost.
	if ( ! $owned_id || $owned_id !== $site_id ) {
		return new WP_Error(
			'bv_not_site_owner',
			__( 'You do not own that site.', 'paid-member-subscriptions' )
		);
	}

	pms_member_delete_user_subscription_abandon( $user_id );

	return bv_delete_user_subsite( $user_id );
}

function bv_sync_plans($plan_id_1, $plan_id_2){

	if(!$plan_id_1)$plan_id_1=PREMIUM_ID;
	$post = get_post( $plan_id_1 );
	$post_to_be_updated_obj= get_post($plan_id_2);

	if( empty( $post ) || $post->post_type != 'pms-subscription' )
		return;

	$meta_keys = get_post_custom_keys( $plan_id_1 );
	$to_skip = array( '_edit_lock', '_edit_last' );

	foreach( $meta_keys as $key ){
		if( in_array( $key, $to_skip ) )
			continue;

		$meta_values = get_post_custom_values( $key, $plan_id_1 );
		if( $meta_values != get_post_custom_values( $key, $plan_id_2) )
			update_post_meta( $plan_id_2, $key, $meta_values[0] );
	}
}



/**
 * Delete Subsite
 *
 * @param $user_id
 *
 */
function bv_delete_user_subsite( $bv_user_id ) {
	$bv_user_id = absint( $bv_user_id );

	if ( ! is_user_logged_in() || get_current_user_id() !== $bv_user_id ) {
		return new WP_Error(
			'bv_not_logged_in',
			__( 'You need to be logged in with the account you want to delete.', 'paid-member-subscriptions' )
		);
	}

	if ( is_super_admin( $bv_user_id ) ) {
		return new WP_Error(
			'bv_super_admin',
			__( 'Super admins cannot delete their own site this way.', 'paid-member-subscriptions' )
		);
	}

	$subsite_id = absint( get_user_meta( $bv_user_id, 'user_blog', true ) );

	/*
	 * Everything below destroys data, so establish first that $subsite_id names
	 * a real, non-main site.
	 *
	 * This previously read the meta value as a string and tested
	 * `$subsite_id == get_main_site_id()`. For a member with no subsite the
	 * value is '' and that comparison is false on both PHP 7 and 8, so
	 * wpmu_delete_blog('', true) ran. Inside core that becomes blog id 0:
	 * switch_to_blog(0) stays on the serving blog, get_site(0) is null so the
	 * drop is skipped, and get_users(['blog_id' => 0]) skips the capability
	 * restriction and returns every user in the network — each of whom then
	 * had remove_all_caps() called on them. Any logged-in member could strip
	 * capabilities from everyone on the site serving the request.
	 */
	if ( $subsite_id < 2 || $subsite_id === absint( get_main_site_id() ) || ! get_site( $subsite_id ) ) {
		return new WP_Error(
			'bv_no_subsite',
			__( 'There is no site associated with this account to delete.', 'paid-member-subscriptions' )
		);
	}

	if ( ! function_exists( 'wpmu_delete_blog' ) ) {
		require_once ABSPATH . 'wp-admin/includes/ms.php';
	}

	$plan_id = absint( get_user_meta( $bv_user_id, 'private_plan', true ) );

	/*
	 * wpmu_delete_blog( $id, true ) drops the site's own tables through
	 * wp_delete_site(). A hand-rolled information_schema sweep used to run
	 * afterwards; it has been removed because it was both broken and
	 * destructive:
	 *
	 *  - it referenced DB_NAME_BLOGS, which is defined nowhere in the install,
	 *    so on PHP 8 it threw a fatal Error *after* the site and the plan post
	 *    were already gone, leaving the teardown half-finished and the caller's
	 *    redirect unreached; and
	 *  - it built `LIKE 'wp_12_%'` without esc_like(). In SQL LIKE, `_` matches
	 *    any single character, so deleting site 12 also matched — and dropped —
	 *    the tables of sites 120 through 129.
	 */
	wpmu_delete_blog( $subsite_id, true );

	delete_user_meta( $bv_user_id, 'user_blog' );
	delete_user_meta( $bv_user_id, 'private_plan' );

	if ( $plan_id ) {
		switch_to_blog( get_main_site_id() );
		wp_delete_post( $plan_id ); // Deletes the plan.
		restore_current_blog();     // Previously never called, on any exit path.
	}

	return true;
}



/**
 * Duplicate Subscription Plan
 *
 * @param $post_id
 */
function bv_duplicate_subscription( $post_id,  $post_name, $post_title) {
	$post = get_post( $post_id );

	if( empty( $post ) || $post->post_type != 'pms-subscription' )
		return;

	//default fields that are copied over
	$fields = array( 'post_author', 'post_content', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_content_filtered', 'menu_order', 'post_type', 'post_mime_type', 'comment_count' );
	$new_post = array();
	$new_post['post_title']=$post_title;
	$new_post['post_name']=$post_name;
	foreach( $fields as $field )
		$new_post[$field] = $post->$field;

	//copy post meta data
	$new_post['meta_input'] = array();

	$meta_keys = get_post_custom_keys( $post_id );
	$to_skip = array( '_edit_lock', '_edit_last' );

	foreach( $meta_keys  as $key ){
		if( in_array( $key, $to_skip ) )
			continue;

		$meta_values = get_post_custom_values( $key, $post_id );

		foreach( $meta_values as $value ){
			$value = maybe_unserialize( $value );

			$new_post['meta_input'][$key] = $value;
		}
	}

	$new_id = wp_insert_post( wp_slash( $new_post ) );

	return $new_id;
}


//Minimum url length
add_filter( 'minimum_site_name_length', 'min_url_length' );
function min_url_length( $length ) {
	return MIN_LENGTH_URL;
}

function bv_create_subsite($user_id, $site_url, $site_title, $master_id=PREMIUM_ID){
	if(!$master_id)$master_id=PREMIUM_ID;
	if(get_user_meta( $user_id, 'user_blog'))return;
	// Illegal names array
	//$illegal_names = array( 'www', 'web', 'root', 'admin', MAIN_TAB_NAME, 'invite', 'administrator','nottest' );
	//update_site_option( 'illegal_names', $illegal_names );


	$result     = wpmu_validate_blog_signup( $site_url, $site_title );
	$domain     = $result['domain'];
	$path       = $result['path'];
	$blogname   = $result['blogname'];
	$blog_title = $result['blog_title'];
	$errors     = $result['errors'];

	if ( $errors->has_errors() ) {
		foreach ( $errors->get_error_codes() as $code ) {
			$severity = $errors->get_error_data( $code );
			foreach ( $errors->get_error_messages( $code ) as $error_message ) {
				if ( 'message' === $severity ) {
					$messages .= '  ' . $error_message . "<br />\n";
				} else {
					$error_messages .= '    ' . $error_message . "<br />\n";
				}
			}
		}
		pms_errors()->add('url', __($error_messages, 'paid-member-subsciptions'));
		return;
	}

	$site_id=wpmu_create_blog( $domain, $path, $blog_title, $user_id, array( 'public' => True ) ); // Creates a network blog

	// The blog object
	$blog=get_blog_details(array('blog_id'=>$site_id));
	if (!function_exists('wp_insert_category')) {                
		//echo "Load taxonomy";
		require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
	}
	$cat_obj=get_category_by_slug( 'private' );
	switch_to_blog( $site_id );
	$wpgm = WP_GeoMeta::get_instance(); 
	$wpgm->install();
	wp_update_term(1, 'category', array(
		'name' => $cat_obj->name,
		'slug' => $cat_obj->slug,
		'description' => $cat_obj->description,
		//'name' => 'Standalone Post',
		//'slug' => 'standalone'                                                                                                                                                        
	));    
	// 'Hello World!' post
	wp_delete_post( 1, true );
	// 'Sample page' page
	wp_delete_post( 2, true );
	global $wp_rewrite;
	//$wp_rewrite->set_permalink_structure('/%category%/%postname%/');
	$wp_rewrite->set_permalink_structure('/%category%/%year%/%monthnum%/%day%/%postname%/');
	$wp_rewrite->flush_rules();
	/*
	$new_cat_id = wp_create_category('standalone');
	wp_insert_category( array(
		'cat_ID'	=>	$cat_obj->term_id,
		'taxonomy'	=>	$cat_obj->taxonomy,
		'cat_name'	=>	$cat_obj->name,
		'category_description'	=>	$cat_obj->description
	));
	*/	
	restore_current_blog(  );
	// Create a new subscription for the subsite
	$plan_id = bv_duplicate_subscription($master_id, $site_id, $blog->siteurl);

	/*
	 * private_plan belongs to the new subsite, so switch to it explicitly.
	 *
	 * This previously read restore_current_blog(); update_option(...);
	 * restore_current_blog(); -- but the single switch opened above had already
	 * been closed on the line before, so the stack was empty, both calls were
	 * no-ops returning false, and the option was written to whichever blog was
	 * current. On the live path that is the main site, so every new subsite
	 * overwrote the main site's private_plan. The commented-out
	 * add_site_meta() directly above shows the intended target.
	 */
	switch_to_blog( $site_id );
	update_option( 'private_plan', $plan_id );
	restore_current_blog();

	// Add values to user meta
	add_user_meta( $user_id,'private_plan', $plan_id );
	add_user_meta( $user_id,'user_blog', $site_id );

	if(!is_super_admin( $user_id )){
		$user_obj = new WP_User( $user_id );
		//$user_obj->for_site( get_main_site_id() );
		$user_obj->set_role('contributor');
		$user_obj->for_site( $site_id );
		$user_obj->set_role('editor');
		//$user_obj->for_site( get_main_site_id() );
		//$user_obj->remove_role('editor');

	}
	bv_handle_theme_changes($site_id);
}

function bv_handle_theme_changes($site_id){

	switch_to_blog( $site_id );
	// Change to a different theme
	switch_theme( 'digital-nomad-child' );

        /*
         * The condition used to be `!isset($browser_lang) && substr(...)`,
         * evaluated immediately after assigning $browser_lang -- so isset()
         * was true whenever the cookie existed and the whole TranslatePress
         * block was skipped in exactly the case it was written for. When the
         * cookie was absent it warned on the undefined key, then ran the block
         * with $browser_lang null, writing update_option('WPLANG', null) and a
         * trp_settings array with null keys into every new subsite.
         *
         * The cookie is also unsanitised visitor input going straight into an
         * option, so it is sanitised and length-checked here.
         */
        $browser_lang = isset( $_COOKIE['trp_language'] )
                ? sanitize_text_field( wp_unslash( $_COOKIE['trp_language'] ) )
                : '';

        if ( $browser_lang !== '' && substr( $browser_lang, 0, 3 ) !== 'en_' ) {
                update_option('WPLANG',$browser_lang);

		$trp_settings=array(
			'default-language'=>$browser_lang ,
			"url-slugs"=> array( $browser_lang => substr($browser_lang,0,2),
			'en_US' => 'en'),
			"translation-languages"=>[$browser_lang,'en_US'] ,
			"publish-languages"=>[$browser_lang,'en_US'] ,
			"native_or_english_name"=> "native_name",
			"add-subdirectory-to-default-language"=>'no' ,
			"force-language-to-custom-links"=>'yes' ,
			"shortcode-options"=>"flags-full-names" ,
			"menu-options"=> "flags-full-names",
			"trp-ls-floater"=>'yes' ,
			"floater-options"=> "flags-short-names" ,
			"floater-position"=> "bottom-left" ,
		);
		update_option('trp_settings',$trp_settings);

		$trp_machine_translation_settings=array(
			"machine-translation"=>'yes' ,
			"translation-engine" =>  "google_translate_v2",
			"block-crawlers"=> 'yes',
			"machine_translation_counter_date"=>"2020-06-07" ,
			"machine_translation_counter"=>0 ,
			"machine_translation_limit"=>GOOG_TRANSLATEv2_CHARLIMIT ,
			"google-translate-key"=>GOOG_TRANSLATEv2_KEY ,
			"deepl-api-key"=> '',
			"machine_translation_log"=> false ,
		);
		update_option('trp_machine_translation_settings',$trp_machine_translation_settings);
	}

	// Add oages
	/*
	wp_insert_post( array(
		'post_title'     => 'Itinerary',
		'post_type'      => 'page',
		'post_name'      => 'itinerary',
		'post_status'    => 'publish',
		// Assign page template
		'page_template'  => 'page-itinerary.php'
	) );
	wp_insert_post( array(
		'post_title'     => 'Edit Post V2',
		'post_type'      => 'page',
		'post_name'      => 'edit-post-v2',
		'post_status'    => 'publish',
		// Assign page template
		'page_template'  => 'page-templates/edit-post-v2.php'
	) );

	wp_insert_post( array(
		'post_title'     => 'Profile',
		'post_type'      => 'page',
		'post_name'      => 'profile',
		'post_status'    => 'publish',
		// Assign page template
		'page_template'  => 'page-templates/profile-template.php'
	) );
	wp_insert_post( array(
		'post_title'     => 'Account',
		'post_type'      => 'page',
		'post_name'      => 'account',
		'post_status'    => 'publish',
		'post_content'   => '[ihc-user-page]',
		// Assign page template
		'page_template'  => 'page-templates/default-template.php'
	) );
	wp_insert_post( array(
		'post_title'     => 'gateway',
		'post_type'      => 'page',
		'post_name'      => 'gateway',
		'post_status'    => 'publish',
		// Assign page template
		'page_template'  => 'page-templates/gateway.php'
	));
	 */

	/*
	 * This was missing entirely. bv_handle_theme_changes() is the last call in
	 * bv_create_subsite(), which runs on init on the main site, so from that
	 * point the whole remaining request rendered against the newly created
	 * subsite -- wrong table prefix, wrong options, wrong theme.
	 */
	restore_current_blog();
}


if ( function_exists( 'pms_get_member_subscriptions' ) ) {
	global $wpdb;
	/**
	 * Return the users subsciptions levels
	 * @param int $u_id the id of the user
	 * @return array|boolean 'level' and 'status' or if none false
	 */
	function bv_get_user_levels($u_id){
		switch_to_blog( get_main_site_id() );
		$levels = @(new PMS_Member($u_id))->get_subscriptions();
		// print_r($levels);
		restore_current_blog(  );
		return $levels;
	}
	/**
	 * Decides if a user meets the minimum level requirement
	 * @param int $u_id the id of the user
	 * @param int $minlevel the minimum level required
	 * @return string
	 */
	function bv_get_required_level_status($u_id, $acceptable_leveles){
		$levels = bv_get_user_levels($u_id);
		// print_r($levels);
		if(!$levels) return false;
		foreach($levels as $level){
			if($level['subscription_plan_id'] == $acceptable_leveles){
				// print_r($subscription_plan->name);   
				if ( in_array($level['status'] ,array('active',
								//'pending',
								'canceled'))) return true;
				else return false;
				// else if($level['status'] == 'pending')$pending=true; 
			}
			// print_r(pms_get_subscription_plan($level->subscription_plan_id));
			// if ($level['level']>=$minlevel && $level['status']=='active') return 'active'; 
		}
		//return $pending;

	}
}

function bv_gm_get_all_meta_values_by_email( $value ){
	global $wpdb;

	// base_prefix, not prefix: PMS keeps a single set of tables on the main
	// site. With the per-blog prefix, a registration on a subsite queried
	// wp_12_pms_member_subscriptionmeta, which does not exist -- the query
	// errored, the function returned false, and the caller then tried to
	// foreach over it.
	$result = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id,member_subscription_id FROM {$wpdb->base_prefix}pms_member_subscriptionmeta WHERE meta_key = 'pms_gm_invited_emails' AND meta_value = %s", $value ), 'OBJECT_K' );

	if( !empty( $result ) )
		return $result;

	return false;
}


add_action('user_register', 'set_default_admin_color');
function set_default_admin_color($user_id) {
	$args = array(
		'ID' => $user_id,
		'admin_color' => 'light'
	);
	wp_update_user( $args );
}

/**
 * Retrieves the adjacent post from a given reference
 *
 * Can either be next or previous post.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param post_object	$current_post	The reference post object to get the next or previous post from.
 * @param bool         $previous       Optional. Whether to retrieve previous post. Default true
 * @param array         $term_array   Optional. Specify the term IDs to restrict the search to. Default empty.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return null|string|WP_Post Post object if successful. Null if global $post is not set. Empty string if no
 *                             corresponding post exists.
 */

function bv_get_adjac_post($current_post, $previous = true, $term_array=array(), $searchposttype='', $ppp = 10, $taxonomy = 'category' ) {
	if ( ! $current_post || ! taxonomy_exists( $taxonomy ) ) { return null; }

	$arg = array(
		'post_status' => 'publish',
		'posts_per_page' => $ppp,
		'orderby' => 'date',
	);

	$special_categories_local=$GLOBALS['special_categories'];
	$in_same_term = empty($term_array)? false: true;
	if( $term_array) $arg += array( 'category__and' => $term_array );

	// set post_type
	$query_posttype = empty($searchposttype)? $current_post->post_type :$searchposttype;
	$arg += array( 'post_type' => $query_posttype);
	
	// set date
	$current_post_date = $current_post->{'post_date'};
	$adjacent = $previous ? 'previous' : 'next';
	$arg += array( 'order' => $previous ? 'DESC' : 'ASC');
	$arg += array(
	       	'date_query' => array( 
			'inclusive' => true,
			array( $previous ? 'before' : 'after'  => $current_post_date ) 
		)
	);

	//logErrors('adj Args ' . $arg);
	$query = new WP_Query( $arg );
	return $query;
}

function bv_get_adjacent_post($current_post, $previous = true, $term_array=array(), $searchposttype='', $taxonomy = 'category', $excluded_terms='' ) {
	$in_same_term = empty($term_array)? false: true;
	$query_posttype = empty($searchposttype)? $current_post->post_type :$searchposttype;
	$special_categories_local=$GLOBALS['special_categories'];
	global $wpdb;

	//$post = get_post();
	if ( ! $current_post || ! taxonomy_exists( $taxonomy ) ) {
		return null;
	}

	$current_post_date = $current_post->{'post_date'};

	//error_log(print_r('post date'.$current_post_date,true));
	$join     = '';
	$where    = '';
	$adjacent = $previous ? 'previous' : 'next';

	if ( ! empty( $excluded_terms ) && ! is_array( $excluded_terms ) ) {
		// Back-compat, $excluded_terms used to be $excluded_categories with IDs separated by " and ".
		if ( false !== strpos( $excluded_terms, ' and ' ) ) {
			_deprecated_argument(
				__FUNCTION__,
				'3.3.0',
				sprintf(
					/* translators: %s: The word 'and'. */
					__( 'Use commas instead of %s to separate excluded terms.' ),
					"'and'"
				)
			);
			$excluded_terms = explode( ' and ', $excluded_terms );
		} else {
			$excluded_terms = explode( ',', $excluded_terms );
		}

		$excluded_terms = array_map( 'intval', $excluded_terms );
	}

	/**
	 * Filters the IDs of terms excluded from adjacent post queries.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 4.4.0
	 *
	 * @param array $excluded_terms Array of excluded term IDs.
	 */
	$excluded_terms = apply_filters( "get_{$adjacent}_post_excluded_terms", $excluded_terms );

	if ( $in_same_term || ! empty( $excluded_terms ) ) {
		if ( $in_same_term ) {
			$join  .= " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
			$where .= $wpdb->prepare( 'AND tt.taxonomy = %s', $taxonomy );

			if ( ! is_object_in_taxonomy( $query_posttype, $taxonomy ) ) {
				return '';
			}

/*	
	    $term_array=array();
	    foreach( $arr_slugs as $slug){
		    $term_array+=array(get_term_by('slug',$slug,$taxonomy)->term_id);
	    }
 */
			$term_array = array_intersect($term_array, wp_get_object_terms( $current_post->ID, $taxonomy, array( 'fields' => 'ids' ) ) );

			// Remove any exclusions from the term array to include.
			$term_array = array_diff( $term_array, (array) $excluded_terms );
			$term_array = array_map( 'intval', $term_array );

			if ( ! $term_array || is_wp_error( $term_array ) ) {
				return '';
			}

			$where .= ' AND tt.term_id IN (' . implode( ',', $term_array ) . ')';
		}

		if ( ! empty( $excluded_terms ) ) {
			$where .= " AND p.ID NOT IN ( SELECT tr.object_id FROM $wpdb->term_relationships tr LEFT JOIN $wpdb->term_taxonomy tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id) WHERE tt.term_id IN (" . implode( ',', array_map( 'intval', $excluded_terms ) ) . ') )';
		}
	}

	// 'post_status' clause depends on the current user.
 /*
    if ( is_user_logged_in() ) {
	$user_id = get_current_user_id();

	$post_type_object = get_post_type_object( $current_post->post_type );
	if ( empty( $post_type_object ) ) {
	    $post_type_cap    = $current_post->post_type;
	    $read_private_cap = 'read_private_' . $post_type_cap . 's';
	} else {
	    $read_private_cap = $post_type_object->cap->read_private_posts;
	}
  */ 
	/*
	 * Results should include private posts belonging to the current user, or private posts where the
	 * current user has the 'read_private_posts' cap.
	 */
    /*
	$private_states = get_post_stati( array( 'private' => true ) );
	$where         .= " AND ( p.post_status = 'publish'";
	foreach ( (array) $private_states as $state ) {
	    if ( current_user_can( $read_private_cap ) ) {
		$where .= $wpdb->prepare( ' OR p.post_status = %s', $state );
	    } else {
		$where .= $wpdb->prepare( ' OR (p.post_author = %d AND p.post_status = %s)', $user_id, $state );
	    }
	}
	$where .= ' )';
    } else {
	$where .= " AND p.post_status = 'publish'";
    }
     */
	$where .= " AND p.post_status = 'publish'";

	$op    = $previous ? '<' : '>';
	$order = $previous ? 'DESC' : 'ASC';

	/**
	 * Filters the JOIN clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 * @since 4.4.0 Added the `$taxonomy` and `$post` parameters.
	 *
	 * @param string  $join           The JOIN clause in the SQL.
	 * @param bool    $in_same_term   Whether post should be in a same taxonomy term.
	 * @param array   $excluded_terms Array of excluded term IDs.
	 * @param string  $taxonomy       Taxonomy. Used to identify the term used when `$in_same_term` is true.
	 * @param WP_Post $post           WP_Post object.
	 */
	$join = apply_filters( "get_{$adjacent}_post_join", $join, $in_same_term, $excluded_terms, $taxonomy, $current_post );

	/**
	 * Filters the WHERE clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 * @since 4.4.0 Added the `$taxonomy` and `$post` parameters.
	 *
	 * @param string  $where          The `WHERE` clause in the SQL.
	 * @param bool    $in_same_term   Whether post should be in a same taxonomy term.
	 * @param array   $excluded_terms Array of excluded term IDs.
	 * @param string  $taxonomy       Taxonomy. Used to identify the term used when `$in_same_term` is true.
	 * @param WP_Post $post           WP_Post object.
	 */
	$where = apply_filters( "get_{$adjacent}_post_where", $wpdb->prepare( "WHERE p.post_date $op %s AND p.post_type = %s $where", $current_post_date, $query_posttype ), $in_same_term, $excluded_terms, $taxonomy, $current_post );

	/**
	 * Filters the ORDER BY clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 * @since 4.4.0 Added the `$post` parameter.
	 * @since 4.9.0 Added the `$order` parameter.
	 *
	 * @param string $order_by The `ORDER BY` clause in the SQL.
	 * @param WP_Post $post    WP_Post object.
	 * @param string  $order   Sort order. 'DESC' for previous post, 'ASC' for next.
	 */
	$sort = apply_filters( "get_{$adjacent}_post_sort", "ORDER BY p.post_date $order LIMIT 1", $current_post, $order );

	$query     = "SELECT p.ID FROM $wpdb->posts AS p $join $where $sort";
	$query_key = 'adjacent_post_' . md5( $query );
	$result    = wp_cache_get( $query_key, 'counts' );
	if ( false !== $result ) {
		if ( $result ) {
			$result = get_post( $result );
		}
		return $result;
	}

	$result = $wpdb->get_var( $query );
	if ( null === $result ) {
		$result = '';
	}

	wp_cache_set( $query_key, $result, 'counts' );

	if ( $result ) {
		$result = get_post( $result );
	}

	return $result;
}

