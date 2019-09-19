<?php
/**
 * Admin Ajax callbacks.
 *
 * These callback functions are forked from /wp-admin/includes/ajax-actions.php.
 *
 * @package HumanMade\SharedMedia
 */

namespace HumanMade\SharedMedia;

use WP_Query;

/*
 * Because this contains direct forks of WP Core's Ajax actions, we're only running
 * PHPCS on the parts of the functions we've changed from WP.
 */

// phpcs:disable

/**
 * Returns a JSON array of attachments based on query parameters.
 *
 * This is a copy of wp_ajax_query_attachments, that switches to the main blog in the network
 * before running the WP_Query for attachments.
 */
function ajax_query_attachments() {

	if ( ! current_user_can( 'upload_files' ) )
		wp_send_json_error();

	$query = isset( $_REQUEST['query'] ) ? (array) $_REQUEST['query'] : array();
	$keys = array(
		's', 'order', 'orderby', 'posts_per_page', 'paged', 'post_mime_type',
		'post_parent', 'post__in', 'post__not_in', 'year', 'monthnum'
	);
	foreach ( get_taxonomies_for_attachments( 'objects' ) as $t ) {
		if ( $t->query_var && isset( $query[ $t->query_var ] ) ) {
			$keys[] = $t->query_var;
		}
	}

	$query = array_intersect_key( $query, array_flip( $keys ) );
	$query['post_type'] = 'attachment';
	if ( MEDIA_TRASH
		&& ! empty( $_REQUEST['query']['post_status'] )
		&& 'trash' === $_REQUEST['query']['post_status'] ) {
		$query['post_status'] = 'trash';
	} else {
		$query['post_status'] = 'inherit';
	}

	if ( current_user_can( get_post_type_object( 'attachment' )->cap->read_private_posts ) )
		$query['post_status'] .= ',private';

	// Filter query clauses to include filenames.
	if ( isset( $query['s'] ) ) {
		add_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
	}

	/**
	 * Filters the arguments passed to WP_Query during an Ajax
	 * call for querying attachments.
	 *
	 * @since 3.7.0
	 *
	 * @see WP_Query::parse_query()
	 *
	 * @param array $query An array of query variables.
	 */
	$query = apply_filters('ajax_query_attachments_args', $query );

	// phpcs:enable
	// Query attachments from the main blog.
	switch_to_main_blog();

	$query = new WP_Query( $query );
	// phpcs:disable

	$posts = array_map( 'wp_prepare_attachment_for_js', $query->posts );
	$posts = array_filter( $posts );

	wp_send_json_success( $posts );
}

/**
 * Ajax handler for updating attachment attributes.
 *
 * This is a copy of wp_ajax_save_attachment, that switches to the main blog in the network
 * before updating post data.
 */
function ajax_save_attachment() {
	// phpcs:enable
	switch_to_main_blog();
	// phpcs:disable

	if ( ! isset( $_REQUEST['id'] ) || ! isset( $_REQUEST['changes'] ) )
		wp_send_json_error();

	if ( ! $id = absint( $_REQUEST['id'] ) )
		wp_send_json_error();

	check_ajax_referer( 'update-post_' . $id, 'nonce' );

	if ( ! current_user_can( 'edit_post', $id ) )
		wp_send_json_error();

	$changes = ( isset( $_REQUEST['changes'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['changes'] ) ) : [];
	$post    = get_post( $id, ARRAY_A );

	if ( 'attachment' != $post['post_type'] )
		wp_send_json_error();

	if ( isset( $changes['parent'] ) )
		$post['post_parent'] = $changes['parent'];

	if ( isset( $changes['title'] ) )
		$post['post_title'] = $changes['title'];

	if ( isset( $changes['caption'] ) )
		$post['post_excerpt'] = $changes['caption'];

	if ( isset( $changes['description'] ) )
		$post['post_content'] = $changes['description'];

	if ( MEDIA_TRASH && isset( $changes['status'] ) )
		$post['post_status'] = $changes['status'];

	if ( isset( $changes['alt'] ) ) {
		$alt = wp_unslash( $changes['alt'] );
		if ( $alt != get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
			$alt = wp_strip_all_tags( $alt, true );
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
		}
	}

	if ( wp_attachment_is( 'audio', $post['ID'] ) ) {
		$changed = false;
		$id3data = wp_get_attachment_metadata( $post['ID'] );
		if ( ! is_array( $id3data ) ) {
			$changed = true;
			$id3data = array();
		}
		foreach ( wp_get_attachment_id3_keys( (object) $post, 'edit' ) as $key => $label ) {
			if ( isset( $changes[ $key ] ) ) {
				$changed = true;
				$id3data[ $key ] = sanitize_text_field( wp_unslash( $changes[ $key ] ) );
			}
		}

		if ( $changed ) {
			wp_update_attachment_metadata( $id, $id3data );
		}
	}

	if ( MEDIA_TRASH && isset( $changes['status'] ) && 'trash' === $changes['status'] ) {
		wp_delete_post( $id );
	} else {
		wp_update_post( $post );
	}

	wp_send_json_success();
}

/**
 * Ajax handler for deleting an attachment.
 *
 * @param string $action Action to perform.
 */
function ajax_delete_attachment( string $action ) {

	// Make use of the expected delete nonce.
	if ( empty( $action ) || 'delete-attachment' === $action ) {
		$action = 'delete-post';
	}

	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	check_ajax_referer( "{$action}_$id" );

	// phpcs:enable
	// Switch to main blog after nonce checks.
	switch_to_main_blog();
	// phpcs:disable

	if ( ! current_user_can( 'delete_post', $id ) ) {
		wp_die( -1 );
	}
	if ( ! get_post( $id ) ) {
		wp_die( 1 );
	}
	if ( wp_delete_post( $id ) ) {
		wp_die( 1 );
	} else {
		wp_die( 0 );
	}
}
