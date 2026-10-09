<?php
function wpdocs_allowed_block_types( $allowed_blocks, $post ) {
	$current_user=wp_get_current_user();
	//error_log(print_r('currentUser: '. json_encode($current_user),true));

	$user_role = $current_user->roles;
	if( !in_array( strtolower('administrator'), $user_role ) )
		$allowed_blocks = array(
			'core/block',
			'core/image',
			'core/paragraph',
			'core/list',
			'core/gallery',
			'core/quote',
			//'core/cover',
			'core/columns',
			'core/video',
			'core/table',
			'core/verse',
			'core/preformatted',
			'core/pullquote',
			'core/text-columns',
			'core/media-text',
			'core/separator',
			//'core/spacer',
			'core-embed/vimeo',
			'core-embed/youtube',
		);
	return $allowed_blocks;
}

add_filter( 'allowed_block_types', 'wpdocs_allowed_block_types', 10, 2 );
