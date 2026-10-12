<?php
/**
 * Registry of everything this plugin needs from outside itself.
 *
 * Nothing here is loaded by this plugin: other plugins, wp-config.php
 * constants, a theme, a vendored SDK. Any of them can be absent -- a plugin
 * deactivated on one subsite, a constant dropped when wp-config.php is rebuilt
 * -- and on PHP 8 an undefined constant or a call to an undefined function is a
 * fatal that takes down every request, including wp-admin.
 *
 * Two rules keep that from happening:
 *
 *  1. Constants are given an inert fallback here, before anything else runs, and
 *     recorded as missing. The fallbacks are chosen so that "not configured"
 *     disables a feature rather than mis-configuring it (a plan id of 0 matches
 *     no plan; an empty API key is never written over a working one). Constants
 *     that other plugins also read (EP_HOST, AWS_REGION ...) are deliberately
 *     NOT given a fallback, since defining them here would configure those
 *     plugins.
 *  2. Plugin availability is checked at call time with bv_dependency_available(),
 *     never at file-include time. Plugin load order is not guaranteed, so a
 *     file-scope function_exists('pms_...') can be false for a plugin that is
 *     active.
 */

defined( 'ABSPATH' ) || exit;

/**
 * wp-config.php constants this plugin reads.
 *
 * 'fallback' is defined when the constant is missing; omit it to leave the
 * constant undefined (callers must then use defined()). 'required' constants are
 * reported in the admin notice.
 *
 * @return array<string,array>
 */
function bv_constant_registry() {
	return array(
		'BASIC_ID'                   => array( 'fallback' => 0,            'required' => true,  'for' => 'free plan id' ),
		'PREMIUM_ID'                 => array( 'fallback' => 0,            'required' => true,  'for' => 'premium plan id' ),
		'PREMIUM_SPACE_LIMIT'        => array( 'fallback' => 0,            'required' => true,  'for' => 'premium upload quota (0 leaves quotas untouched)' ),
		'MIN_LENGTH_URL'             => array( 'fallback' => 4,            'required' => false, 'for' => 'minimum subsite slug length (WordPress default)' ),
		'EVENTS_POSTTYPE'            => array( 'fallback' => 'tribe_events', 'required' => false, 'for' => 'events post type' ),
		'GOOG_MAP_KEY'               => array( 'fallback' => '',           'required' => true,  'for' => 'Google Maps key (empty leaves ACF/TEC settings alone)' ),
		'GOOG_TRANSLATEv2_KEY'       => array( 'fallback' => '',           'required' => false, 'for' => 'machine translation key (empty disables it)' ),
		'GOOG_TRANSLATEv2_CHARLIMIT' => array( 'fallback' => 0,            'required' => false, 'for' => 'machine translation character limit' ),
		// Read by ElasticPress as well: no fallback, report only.
		'EP_HOST'                    => array( 'required' => false, 'for' => 'ElasticPress endpoint' ),
		'ES_AWS_KEY'                 => array( 'required' => false, 'for' => 'SigV4 signing of ElasticPress requests' ),
		'ES_AWS_SECRET'              => array( 'required' => false, 'for' => 'SigV4 signing of ElasticPress requests' ),
		'AWS_REGION'                 => array( 'required' => false, 'for' => 'SigV4 signing of ElasticPress requests' ),
	);
}

/**
 * Define fallbacks for missing constants and return the names that were missing.
 *
 * @return string[]
 */
function bv_apply_constant_fallbacks() {
	$missing = array();

	foreach ( bv_constant_registry() as $name => $spec ) {
		if ( defined( $name ) ) {
			continue;
		}

		$missing[] = $name;

		if ( array_key_exists( 'fallback', $spec ) ) {
			define( $name, $spec['fallback'] );
		}
	}

	return $missing;
}

/**
 * Names of constants that were not set in wp-config.php when this plugin loaded.
 *
 * Captured once: after bv_apply_constant_fallbacks() has run they are all
 * defined, so defined() can no longer tell.
 *
 * @return string[]
 */
function bv_missing_constants() {
	static $missing = null;

	if ( null === $missing ) {
		$missing = bv_apply_constant_fallbacks();
	}

	return $missing;
}

// Run at load, so the fallbacks exist before any other file reads the constants.
bv_missing_constants();

/**
 * Plugins and libraries this plugin integrates with.
 *
 * Each 'check' is evaluated lazily. 'required' ones are reported in the admin
 * notice; the rest degrade silently.
 *
 * @return array<string,array>
 */
function bv_dependency_registry() {
	return array(
		'pms'        => array(
			'label'    => 'Paid Member Subscriptions',
			'required' => true,
			'check'    => function () {
				return function_exists( 'pms_get_member_subscriptions' ) && function_exists( 'pms_errors' ) && class_exists( 'PMS_Member' );
			},
		),
		'acf'        => array(
			'label'    => 'Advanced Custom Fields Pro',
			'required' => true,
			'check'    => function () {
				return function_exists( 'acf_add_local_field_group' );
			},
		),
		'geometa'    => array(
			'label'    => 'WP-GeoMeta',
			'required' => true,
			'check'    => function () {
				return class_exists( 'WP_GeoMeta' ) && class_exists( 'WP_GeoUtil' );
			},
		),
		'trp'        => array(
			'label'    => 'TranslatePress',
			'required' => false,
			'check'    => function () {
				return class_exists( 'TRP_Translate_Press' );
			},
		),
		'tec'        => array(
			'label'    => 'The Events Calendar',
			'required' => false,
			'check'    => function () {
				return function_exists( 'tribe_events' );
			},
		),
		'elasticpress' => array(
			'label'    => 'ElasticPress',
			'required' => false,
			'check'    => function () {
				return defined( 'EP_VERSION' );
			},
		),
		'aws_sdk'    => array(
			'label'    => 'AWS SDK (borrowed from Amazon Polly)',
			'required' => false,
			'check'    => function () {
				return class_exists( '\\Aws\\Signature\\SignatureV4' )
					&& class_exists( '\\Aws\\Credentials\\Credentials' )
					&& class_exists( '\\GuzzleHttp\\Psr7\\Request' );
			},
		),
	);
}

/**
 * Is an integration available right now?
 *
 * Call at run time (inside a hook callback), not at file scope. An unknown key
 * is "not available" rather than an error.
 *
 * @param string $key Key from bv_dependency_registry().
 * @return bool
 */
function bv_dependency_available( $key ) {
	$registry = bv_dependency_registry();

	if ( ! isset( $registry[ $key ] ) ) {
		return false;
	}

	return (bool) call_user_func( $registry[ $key ]['check'] );
}

/**
 * Are the plan ids configured?
 *
 * The fallback for both is 0, which matches no plan. Code that branches on a
 * plan id must not treat "unconfigured" as a real id.
 *
 * @return bool
 */
function bv_plans_configured() {
	return (int) BASIC_ID > 0 && (int) PREMIUM_ID > 0;
}

/**
 * Whether subsites can be created, i.e. whether every step bv_create_subsite()
 * takes can complete. Checked up front because the function creates the blog
 * first and fails later: a missing class partway through leaves a half-built
 * site and an unbalanced switch_to_blog() stack.
 *
 * @return bool
 */
function bv_can_create_subsites() {
	return bv_dependency_available( 'pms' )
		&& bv_dependency_available( 'geometa' )
		&& bv_plans_configured();
}

/**
 * Cache-busting version for one of this plugin's own assets.
 *
 * filemtime() is false for a missing file (plus a warning); wp_enqueue_*()
 * would then treat false as "use the WordPress version" silently, so be
 * explicit and fall back to the plugin version.
 *
 * @param string $absolute_path Path on disk.
 * @return string
 */
function bv_asset_version( $absolute_path ) {
	$mtime = is_readable( $absolute_path ) ? filemtime( $absolute_path ) : false;

	return $mtime ? (string) $mtime : '0.1';
}

/**
 * Log a missing dependency at most once an hour per key, so a deactivated
 * plugin produces one line rather than one per request.
 *
 * @param string $key     Throttle key.
 * @param string $message What is missing and what was skipped.
 */
function bv_log_dependency_problem( $key, $message ) {
	$transient = 'bv_dep_' . md5( $key );

	if ( get_site_transient( $transient ) ) {
		return;
	}

	set_site_transient( $transient, 1, HOUR_IN_SECONDS );
	error_log( 'voyage: ' . $message );
}

/**
 * Collect what is currently missing, for the admin notice.
 *
 * @return string[] Human-readable lines.
 */
function bv_dependency_problems() {
	$problems = array();

	foreach ( bv_dependency_registry() as $dep ) {
		if ( $dep['required'] && ! call_user_func( $dep['check'] ) ) {
			$problems[] = sprintf( 'Plugin not active: %s', $dep['label'] );
		}
	}

	$registry = bv_constant_registry();

	foreach ( bv_missing_constants() as $name ) {
		if ( ! empty( $registry[ $name ]['required'] ) ) {
			$problems[] = sprintf( 'wp-config.php constant not defined: %s (%s)', $name, $registry[ $name ]['for'] );
		}
	}

	return $problems;
}

/**
 * Tell super admins when something this plugin depends on is missing.
 *
 * Without this the guards turn a fatal into silence, which is better for
 * visitors but worse for whoever has to work out why signups do nothing.
 */
function bv_dependency_notice() {
	if ( ! is_super_admin() ) {
		return;
	}

	$problems = bv_dependency_problems();

	if ( empty( $problems ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>beauVoyage (voyage):</strong> some features are disabled because of missing dependencies.</p><ul style="list-style:disc;margin-left:2em">';

	foreach ( $problems as $problem ) {
		echo '<li>' . esc_html( $problem ) . '</li>';
	}

	echo '</ul></div>';
}
add_action( 'admin_notices', 'bv_dependency_notice' );
add_action( 'network_admin_notices', 'bv_dependency_notice' );
