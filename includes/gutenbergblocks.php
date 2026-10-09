<?php
/*
 * Note: this only filters the block inserter. It is not access control -- block
 * markup can still be submitted via the REST API or the code editor, and
 * nothing here touches the unfiltered_html capability. Recorded in README.md.
 */
function wpdocs_allowed_block_types( $allowed_blocks, $post ) {
	// Was `! in_array( 'administrator', $current_user->roles )`, which gave the
	// restricted block list to multisite Super Admins, whose roles array is
	// empty on a subsite where they hold no explicit role.
	if ( ! bv_user_sees_full_admin() )
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
