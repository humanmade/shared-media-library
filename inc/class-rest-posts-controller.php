<?php
/**
 * REST API: WP_REST_Posts_Controller class
 *
 * @package HumanMade\SharedMedia
 */

namespace HumanMade\SharedMedia;

use WP_Error;
use WP_REST_Posts_Controller;

/**
 * Fork of the WP_REST_Posts_Controller class to override handling of featured media.
 *
 * @see WP_REST_Controller
 */
class REST_Posts_Controller extends WP_REST_Posts_Controller {

	/**
	 * Determines the featured media based on a request param.
	 *
	 * @param int $featured_media Featured Media ID.
	 * @param int $post_id        Post ID.
	 * @return bool|WP_Error Whether the post thumbnail was successfully deleted, otherwise WP_Error.
	 */
	protected function handle_featured_media( $featured_media, $post_id ) {
		$featured_media = (int) $featured_media;
		if ( $featured_media ) {
			$result = set_post_thumbnail( $post_id, $featured_media );
			if ( $result ) {
				return true;
			} else {
				return new WP_Error( 'rest_invalid_featured_media', __( 'Invalid featured media ID.' ), [ 'status' => 400 ] );
			}
		} else {
			return delete_post_thumbnail( $post_id );
		}
	}
}
