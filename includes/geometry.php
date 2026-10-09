<?php

function bv_geom_activation( $network_wide ) {                                                    
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {    
		global $wpdb;   
		// Get this so we can switch back to it later   
		$current_blog = $wpdb->blogid;  
		// Get all blogs in the network and activate plugin on each one  i
		$blog_ids = $wpdb->get_col(  "SELECT blog_id FROM $wpdb->blogs" );     
		foreach ( $blog_ids as $blog_id ) {       
			switch_to_blog( $blog_id );
			bv_geom_create_table();   
		}
		switch_to_blog( $current_blog );           
		return;   
	} else {  
		bv_geom_create_table(); // normal acticvation                                                                                                               
	}
}      
function bv_geom_create_table() {    
	$max_index_length=191      
	global $wpdb;
	$table_name = "{$wpdb->prefix}postgeom";    
	$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
               geom_id bigint(20) unsigned NOT NULL auto_increment,
               post_id bigint(20) unsigned NOT NULL default '0',
               geom_key datetime NOT NULL default '0000-00-00 00:00:00',
               geom_value geometry,
               PRIMARY KEY  (geom_id),
               KEY post_id (post_id),
               KEY geom_key (geom_key($max_index_length))
              )";
        $wpdb->query( $sql );
}

function _get_geom_table( $type ) {
	    global $wpdb;
	        $table_name = $type . 'geom';
	        if ( empty( $wpdb->$table_name ) ) {
			        return false;
				    }
		    return $wpdb->$table_name;
}

/*
 *	from update_meta_cache to update_geom_update
 */

function update_geom_cache( $meta_type, $object_ids ) {
    global $wpdb;
 
    if ( ! $meta_type || ! $object_ids ) {
        return false;
    }
 
    $table = _get_geom_table( $meta_type );
    if ( ! $table ) {
        return false;
    }
 
    $column = sanitize_key( $meta_type . '_id' );
 
    if ( ! is_array( $object_ids ) ) {
        $object_ids = preg_replace( '|[^0-9,]|', '', $object_ids );
        $object_ids = explode( ',', $object_ids );
    }
 
    $object_ids = array_map( 'intval', $object_ids );
 
    /**
     * Filters whether to update the metadata cache of a specific type.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user). Returning a non-null value
     * will effectively short-circuit the function.
     *
     * @since 5.0.0
     *
     * @param mixed $check      Whether to allow updating the meta cache of the given type.
     * @param int[] $object_ids Array of object IDs to update the meta cache for.
     */
/*
    $check = apply_filters( "update_{$meta_type}_metadata_cache", null, $object_ids );
    if ( null !== $check ) {
        return (bool) $check;
    }
*/
 
    $cache_key = $meta_type . '_geom';
    $ids       = array();
    $cache     = array();
    foreach ( $object_ids as $id ) {
        $cached_object = wp_cache_get( $id, $cache_key );
        if ( false === $cached_object ) {
            $ids[] = $id;
        } else {
            $cache[ $id ] = $cached_object;
        }
    }
 
    if ( empty( $ids ) ) {
        return $cache;
    }
 
    // Get meta info
    $id_list   = join( ',', $ids );
    $id_column = 'user' == $meta_type ? 'ugeom_id' : 'geom_id';
    $geom_list = $wpdb->get_results( "SELECT $column, geom_key, ST_AsGeoJSON(geom_value) FROM $table WHERE $column IN ($id_list) ORDER BY $id_column ASC", ARRAY_A );
 
    if ( ! empty( $geom_list ) ) {
        foreach ( $geom_list as $geomrow ) {
            $mpid = intval( $geomrow[ $column ] );
            $mkey = $geomrow['geom_key'];
            $mval = $geomrow['ST_AsGeoJSON(geom_value)'];
 
            // Force subkeys to be array type:
            if ( ! isset( $cache[ $mpid ] ) || ! is_array( $cache[ $mpid ] ) ) {
                $cache[ $mpid ] = array();
            }
            if ( ! isset( $cache[ $mpid ][ $mkey ] ) || ! is_array( $cache[ $mpid ][ $mkey ] ) ) {
                $cache[ $mpid ][ $mkey ] = array();
            }
 
            // Add a value to the current pid/key:
            $cache[ $mpid ][ $mkey ][] = $mval;
        }
    }
 
    foreach ( $ids as $id ) {
        if ( ! isset( $cache[ $id ] ) ) {
            $cache[ $id ] = array();
        }
        wp_cache_add( $id, $cache[ $id ], $cache_key );
    }
 
    return $cache;
}
/*
 *	from get_post_meta to get_post_geom
 *
 */

function get_geomdata( $meta_type, $object_id, $meta_key = '', $single = false ) {
    if ( ! $meta_type || ! is_numeric( $object_id ) ) {
        return false;
    }
 
    $object_id = absint( $object_id );
    if ( ! $object_id ) {
        return false;
    }
 
    /**
     * Filters whether to retrieve metadata of a specific type.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user). Returning a non-null value
     * will effectively short-circuit the function.
     *
     * @since 3.1.0
     *
     * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
     *                                     or an array of values.
     * @param int               $object_id Object ID.
     * @param string            $meta_key  Meta key.
     * @param bool              $single    Whether to return only the first value of the specified $meta_key.
     */
/*
    $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );
    if ( null !== $check ) {
        if ( $single && is_array( $check ) ) {
            return $check[0];
        } else {
            return $check;
        }
    }
*/
 
    $meta_cache = wp_cache_get( $object_id, $meta_type . '_geom' );
 
    if ( ! $meta_cache ) {
        $meta_cache = update_geom_cache( $meta_type, array( $object_id ) );
        if ( isset( $meta_cache[ $object_id ] ) ) {
            $meta_cache = $meta_cache[ $object_id ];
        } else {
            $meta_cache = null;
        }
    }
 
    if ( ! $meta_key ) {
        return $meta_cache;
    }
 
    if ( isset( $meta_cache[ $meta_key ] ) ) {
        if ( $single ) {
            return maybe_unserialize( $meta_cache[ $meta_key ][0] );
        } else {
            return array_map( 'maybe_unserialize', $meta_cache[ $meta_key ] );
        }
    }
 
    if ( $single ) {
        return '';
    } else {
        return array();
    }
}

function get_post_geom( $post_id, $key = '', $single = false ) {
    return get_geomdata( 'post', $post_id, $key, $single );
}

/*
 * from update_post_meta to update_post_geom
 *
 *
 */

function update_geomdata( $meta_type, $object_id, $geom_key, $geom_value, $prev_value = '' ) {
    global $wpdb;
 
    if ( ! $meta_type || ! $geom_key || ! is_numeric( $object_id ) ) {
        return false;
    }
 
    $object_id = absint( $object_id );
    if ( ! $object_id ) {
        return false;
    }
 
    $table = _get_geom_table( $meta_type );
    if ( ! $table ) {
        return false;
    }
 
    $meta_subtype = get_object_subtype( $meta_type, $object_id );
 
    $column    = sanitize_key( $meta_type . '_id' );
    $id_column = 'user' == $meta_type ? 'ugeom_id' : 'geom_id';
 
    // expected_slashed ($meta_key)
    $raw_meta_key = $geom_key;
    $geom_key     = wp_unslash( $geom_key );
    $passed_value = $geom_value;
    $geom_value   = wp_unslash( $geom_value );
    $geom_value   = sanitize_meta( $geom_key, $geom_value, $meta_type, $meta_subtype );
 
    /**
     * Filters whether to update metadata of a specific type.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user). Returning a non-null value
     * will effectively short-circuit the function.
     *
     * @since 3.1.0
     *
     * @param null|bool $check      Whether to allow updating metadata for the given type.
     * @param int       $object_id  Object ID.
     * @param string    $meta_key   Meta key.
     * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
     * @param mixed     $prev_value Optional. If specified, only update existing
     *                              metadata entries with the specified value.
     *                              Otherwise, update all entries.
     */
    $check = apply_filters( "update_{$meta_type}_metadata", null, $object_id, $geom_key, $geom_value, $prev_value );
/*
    if ( null !== $check ) {
        return (bool) $check;
    }
*/
 
    // Compare existing value to new value if no prev value given and the key exists only once.
    if ( empty( $prev_value ) ) {
        $old_value = get_geomdata( $meta_type, $object_id, $geom_key );
        if ( count( $old_value ) == 1 ) {
            if ( $old_value[0] === $geom_value ) {
                return false;
            }
        }
    }
 
    $meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $id_column FROM $table WHERE geom_key = %s AND $column = %d", $geom_key, $object_id ) );
    if ( empty( $meta_ids ) ) {
        return add_geomdata( $meta_type, $object_id, $raw_meta_key, $passed_value );
    }
 
    $_geom_value = $geom_value;
    $geom_value  = maybe_serialize( $geom_value );
 
    $data  = compact( 'geom_value' );
    $where = array(
        $column    => $object_id,
        'geom_key' => $geom_key,
    );
 
    if ( ! empty( $prev_value ) ) {
        $prev_value          = maybe_serialize( $prev_value );
        $where['geom_value'] = $prev_value;
    }
 
    foreach ( $meta_ids as $meta_id ) {
        /**
         * Fires immediately before updating metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user).
         *
         * @since 2.9.0
         *
         * @param int    $meta_id     ID of the metadata entry to update.
         * @param int    $object_id   Object ID.
         * @param string $meta_key    Meta key.
         * @param mixed  $_meta_value Meta value.
         */
        //do_action( "update_{$meta_type}_meta", $meta_id, $object_id, $geom_key, $_geom_value );
 
        if ( 'post' == $meta_type ) {
            /**
             * Fires immediately before updating a post's metadata.
             *
             * @since 2.9.0
             *
             * @param int    $meta_id    ID of metadata entry to update.
             * @param int    $object_id  Post ID.
             * @param string $meta_key   Meta key.
             * @param mixed  $meta_value Meta value. This will be a PHP-serialized string representation of the value if
             *                           the value is an array, an object, or itself a PHP-serialized string.
             */
            //do_action( 'update_postmeta', $meta_id, $object_id, $geom_key, $geom_value );
        }
    }
 
    $result = $wpdb->update( $table, $data, $where );
    if ( ! $result ) {
        return false;
    }
 
    wp_cache_delete( $object_id, $meta_type . '_geom' );
 
    foreach ( $meta_ids as $meta_id ) {
        /**
         * Fires immediately after updating metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user).
         *
         * @since 2.9.0
         *
         * @param int    $meta_id     ID of updated metadata entry.
         * @param int    $object_id   Object ID.
         * @param string $meta_key    Meta key.
         * @param mixed  $_meta_value Meta value.
         */
        //do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $geom_key, $_geom_value );
 
        if ( 'post' == $meta_type ) {
            /**
             * Fires immediately after updating a post's metadata.
             *
             * @since 2.9.0
             *
             * @param int    $meta_id    ID of updated metadata entry.
             * @param int    $object_id  Post ID.
             * @param string $meta_key   Meta key.
             * @param mixed  $meta_value Meta value. This will be a PHP-serialized string representation of the value if
             *                           the value is an array, an object, or itself a PHP-serialized string.
             */
            do_action( 'updated_postmeta', $meta_id, $object_id, $geom_key, $geom_value );
        }
    }
 
    return true;
}
	
function update_post_geom( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
    // Make sure meta is added to the post, not a revision.
    $the_post = wp_is_post_revision( $post_id );
    if ( $the_post ) {
        $post_id = $the_post;
    }
 
    return update_geomdata( 'post', $post_id, $meta_key, $meta_value, $prev_value );
}
/*
 *a from add_post_meta to add_post_geom
 *
 */

function add_geomdata( $meta_type, $object_id, $meta_key, $meta_value, $unique = false ) {
    global $wpdb;
 
    if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) ) {
        return false;
    }
 
    $object_id = absint( $object_id );
    if ( ! $object_id ) {
        return false;
    }
 
    $table = _get_geom_table( $meta_type );
    if ( ! $table ) {
        return false;
    }
 
    $meta_subtype = get_object_subtype( $meta_type, $object_id );
 
    $column = sanitize_key( $meta_type . '_id' );
 
    // expected_slashed ($meta_key)
    $meta_key   = wp_unslash( $meta_key );
    $meta_value = wp_unslash( $meta_value );
    $meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type, $meta_subtype );
 
    /**
     * Filters whether to add metadata of a specific type.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user). Returning a non-null value
     * will effectively short-circuit the function.
     *
     * @since 3.1.0
     *
     * @param null|bool $check      Whether to allow adding metadata for the given type.
     * @param int       $object_id  Object ID.
     * @param string    $meta_key   Meta key.
     * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
     * @param bool      $unique     Whether the specified meta key should be unique
     *                              for the object. Optional. Default false.
     */
    $check = apply_filters( "add_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $unique );
/*
    if ( null !== $check ) {
        return $check;
    }
*/
 
    if ( $unique && $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE geom_key = %s AND $column = %d",
            $meta_key,
            $object_id
        )
    ) ) {
        return false;
    }
 
    $_meta_value = $meta_value;
    $meta_value  = maybe_serialize( $meta_value );
 
    /**
     * Fires immediately before meta of a specific type is added.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user).
     *
     * @since 3.1.0
     *
     * @param int    $object_id   Object ID.
     * @param string $meta_key    Meta key.
     * @param mixed  $_meta_value Meta value.
     */
    //do_action( "add_{$meta_type}_meta", $object_id, $meta_key, $_meta_value );
 
    $result = $wpdb->insert(
        $table,
        array(
            $column      => $object_id,
            'geom_key'   => $meta_key,
            'geom_value' => $meta_value,
        )
    );
 
    if ( ! $result ) {
        return false;
    }
 
    $mid = (int) $wpdb->insert_id;
 
    wp_cache_delete( $object_id, $meta_type . '_geom' );
 
    /**
     * Fires immediately after meta of a specific type is added.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user).
     *
     * @since 2.9.0
     *
     * @param int    $mid         The meta ID after successful update.
     * @param int    $object_id   Object ID.
     * @param string $meta_key    Meta key.
     * @param mixed  $_meta_value Meta value.
     */
    //do_action( "added_{$meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value );
 
    return $mid;
}

function add_post_geom( $post_id, $meta_key, $meta_value, $unique = false ) {
    // Make sure meta is added to the post, not a revision.
    $the_post = wp_is_post_revision( $post_id );
    if ( $the_post ) {
        $post_id = $the_post;
    }
 
    return add_geomdata( 'post', $post_id, $meta_key, $meta_value, $unique );
}

/*
 * from delete_post_meta to delete_post_geom
 */

function delete_geomdata( $meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
    global $wpdb;
 
    if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) && ! $delete_all ) {
        return false;
    }
 
    $object_id = absint( $object_id );
    if ( ! $object_id && ! $delete_all ) {
        return false;
    }
 
    $table = _get_geom_table( $meta_type );
    if ( ! $table ) {
        return false;
    }
 
    $type_column = sanitize_key( $meta_type . '_id' );
    $id_column   = 'user' == $meta_type ? 'ugeom_id' : 'geom_id';
    // expected_slashed ($meta_key)
    $meta_key   = wp_unslash( $meta_key );
    $meta_value = wp_unslash( $meta_value );
 
    /**
     * Filters whether to delete metadata of a specific type.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user). Returning a non-null value
     * will effectively short-circuit the function.
     *
     * @since 3.1.0
     *
     * @param null|bool $delete     Whether to allow metadata deletion of the given type.
     * @param int       $object_id  Object ID.
     * @param string    $meta_key   Meta key.
     * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
     * @param bool      $delete_all Whether to delete the matching metadata entries
     *                              for all objects, ignoring the specified $object_id.
     *                              Default false.
     */
    $check = apply_filters( "delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $delete_all );
/*
    if ( null !== $check ) {
        return (bool) $check;
    }
*/
 
    $_meta_value = $meta_value;
    $meta_value  = maybe_serialize( $meta_value );
 
    $query = $wpdb->prepare( "SELECT $id_column FROM $table WHERE geom_key = %s", $meta_key );
 
    if ( ! $delete_all ) {
        $query .= $wpdb->prepare( " AND $type_column = %d", $object_id );
    }
 
    if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
        $query .= $wpdb->prepare( ' AND geom_value = %s', $meta_value );
    }
 
    $meta_ids = $wpdb->get_col( $query );
    if ( ! count( $meta_ids ) ) {
        return false;
    }
 
    if ( $delete_all ) {
        if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
            $object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $type_column FROM $table WHERE geom_key = %s AND geom_value = %s", $meta_key, $meta_value ) );
        } else {
            $object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $type_column FROM $table WHERE geom_key = %s", $meta_key ) );
        }
    }
 
    /**
     * Fires immediately before deleting metadata of a specific type.
     *
     * The dynamic portion of the hook, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user).
     *
     * @since 3.1.0
     *
     * @param array  $meta_ids    An array of metadata entry IDs to delete.
     * @param int    $object_id   Object ID.
     * @param string $meta_key    Meta key.
     * @param mixed  $_meta_value Meta value.
     */
/*
    do_action( "delete_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );
*/
 
    // Old-style action.
    if ( 'post' == $meta_type ) {
        /**
         * Fires immediately before deleting metadata for a post.
         *
         * @since 2.9.0
         *
         * @param array $meta_ids An array of post metadata entry IDs to delete.
         */
  //      do_action( 'delete_postmeta', $meta_ids );
    }
 
    $query = "DELETE FROM $table WHERE $id_column IN( " . implode( ',', $meta_ids ) . ' )';
 
    $count = $wpdb->query( $query );
 
    if ( ! $count ) {
        return false;
    }
 
    if ( $delete_all ) {
        foreach ( (array) $object_ids as $o_id ) {
            wp_cache_delete( $o_id, $meta_type . '_geom' );
        }
    } else {
        wp_cache_delete( $object_id, $meta_type . '_geom' );
    }
 
    /**
     * Fires immediately after deleting metadata of a specific type.
     *
     * The dynamic portion of the hook name, `$meta_type`, refers to the meta
     * object type (comment, post, term, or user).
     *
     * @since 2.9.0
     *
     * @param array  $meta_ids    An array of deleted metadata entry IDs.
     * @param int    $object_id   Object ID.
     * @param string $meta_key    Meta key.
     * @param mixed  $_meta_value Meta value.
     */
    //do_action( "deleted_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );
 
    // Old-style action.
    if ( 'post' == $meta_type ) {
        /**
         * Fires immediately after deleting metadata for a post.
         *
         * @since 2.9.0
         *
         * @param array $meta_ids An array of deleted post metadata entry IDs.
         */
     //   do_action( 'deleted_postmeta', $meta_ids );
    }
 
    return true;
}

function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
    // Make sure meta is added to the post, not a revision.
    $the_post = wp_is_post_revision( $post_id );
    if ( $the_post ) {
        $post_id = $the_post;
    }
 
    return delete_geomdata( 'post', $post_id, $meta_key, $meta_value );
}
