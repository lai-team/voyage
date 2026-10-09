<?php namespace SignAmazonEsRequests;

// If this file is called directly, abort. This guard used to sit *after* the
// require_once below, so a direct request to
// /wp-content/plugins/voyage/includes/elasticpress_aws.php ran the AWS SDK
// autoloader before reaching it.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * The AWS SDK is borrowed from another plugin's vendor directory. That is a
 * single point of failure for the whole network: an unguarded require_once on
 * a missing file is a fatal on every request, on every site, including
 * wp-admin -- so deleting or renaming the unrelated amazon-polly plugin took
 * the network down. ElasticPress 5.x ships its own SigV4 support and this
 * filter should eventually be retired in favour of it.
 */
$bv_aws_autoloader = WP_PLUGIN_DIR . '/amazon-polly/vendor/aws/aws-autoloader.php';

if ( ! class_exists( '\\Aws\\Signature\\SignatureV4' ) && file_exists( $bv_aws_autoloader ) ) {
	require_once $bv_aws_autoloader;
}

unset( $bv_aws_autoloader );

// require the classes we'll need
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use GuzzleHttp\Psr7\Request;

add_filter( 'http_request_args', __NAMESPACE__ . '\\sign_aws_request', 10, 2 );

function sign_aws_request( array $args, string $url ) : array {
	$host = parse_url( $url, PHP_URL_HOST );

	/*
	 * This guard used to FAIL OPEN.
	 *
	 * parse_url() returns null for a URL with no host, and strpos($hay, null)
	 * coerces the needle to '' and returns 0 -- not false. `0 === false` is
	 * false, so the early return was skipped and the request was signed with
	 * the live AWS credentials and sent to whatever destination it named. Any
	 * wp_remote_*() call anywhere in the stack with a schemeless or relative
	 * URL therefore left here carrying an
	 * `Authorization: AWS4-HMAC-SHA256 Credential=...` header.
	 *
	 * Requiring a host, and requiring the region and credentials up front,
	 * makes every failure mode "do not sign".
	 */
	if ( empty( $host ) || ! defined( 'EP_HOST' ) || strpos( EP_HOST, $host ) === false ) {
		return $args;
	}

	if ( ! defined( 'AWS_REGION' ) || ! defined( 'ES_AWS_KEY' ) || ! defined( 'ES_AWS_SECRET' ) ) {
		error_log( 'AWS_REGION, ES_AWS_KEY and ES_AWS_SECRET must be defined in wp-config.php to sign ElasticPress requests' );
		return $args;
	}

	if ( ! class_exists( '\\Aws\\Signature\\SignatureV4' ) ) {
		error_log( 'AWS SDK unavailable; ElasticPress requests will not be signed' );
		return $args;
	}

	// otherwise, sign the request using the AWS SDK and return the $args array
	$request = new Request( $args['method'], $url, $args['headers'], $args['body'] );
	$signer = new SignatureV4( 'es', AWS_REGION ); // region specific
	if ( defined( 'ES_AWS_KEY' ) ) {
		$credentials = new Credentials( ES_AWS_KEY, ES_AWS_SECRET );
		$signed_request = $signer->signRequest( $request, $credentials );
		$args['headers']['Authorization'] = $signed_request->getHeader( 'Authorization' )[0];
		$args['headers']['X-Amz-Date'] = $signed_request->getHeader( 'X-Amz-Date' )[0];
	} else {
		error_log('ES_AWS_KEY & ES_AWS_SECRET must be defined in wp_config.php');
	}
	return $args;
}

//add_filter('ep_index_posts_args', 'ep_index_wpml', 20 );
/*
function ep_index_wpml($args) {
	  return array_merge($args, ['suppress_filters' => true]);
}*/
