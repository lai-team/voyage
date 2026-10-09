<?php namespace SignAmazonEsRequests;

// In this example we've included the AWS SDK for PHP using the zip file
// // see: https://docs.aws.amazon.com/en_pv/sdk-for-php/v3/developer-guide/getting-started_installation.html
// // First, we load the AWS SDK autoloader so that we may use the classes
require_once( WP_PLUGIN_DIR . '/amazon-polly/vendor/aws/aws-autoloader.php' );
//
// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
    }

// require the classes we'll need
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use GuzzleHttp\Psr7\Request;

add_filter( 'http_request_args', __NAMESPACE__ . '\\sign_aws_request', 10, 2 );

function sign_aws_request( array $args, string $url ) : array {
	$host = parse_url( $url, PHP_URL_HOST );
        // You'll need to define EP_HOST in wp_config.php for this check to work
	// If you're using AWS Elasticsearch Service, this would be something like:
        // https://search-<domain>-<guid>.<region>.es.amazonaws.com
        // If not requesting elasticpress host, don't sign request
	if ( !defined('EP_HOST') || strpos(EP_HOST, $host) === false ) {
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
