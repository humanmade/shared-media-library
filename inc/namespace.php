<?php
/**
 * Shared functionality for the Shared Media library.
 *
 * @package HumanMade\SharedMedia
 */

namespace HumanMade\SharedMedia;

/**
 * Switch to the main network via a filter.
 *
 * There are several places where no action hooks are fired when adding/updating
 * attachments. To ensure that we're writing to the main network, we hijack the
 * an early filter, switch to blog, and pass through the data, untouched.
 *
 * @param mixed $value Optional. Initial value passed to the filter.
 * @return mixed unfiltered array of uploaded data.
 */
function switch_to_main_blog_in_filter( $value ) {
	switch_to_main_blog();

	return $value;
}

/**
 * Restore current blog on action.
 *
 * @param mixed $value Optional. Initial value passed to the action.
 * @return mixed unfiltered array of uploaded data.
 */
function restore_current_blog_in_action( $value ) {
	restore_current_blog();

	return $value;
}

/**
 * Switch to the main network blog.
 */
function switch_to_main_blog() {
	$network_id = get_network()->site_id;
	// @codingStandardsIgnoreStart (fine for VIP Go)
	switch_to_blog( $network_id );
	// @codingStandardsIgnoreEnd
}

/**
 * Specifically whitelist the post_meta keys that relate to attachments.
 */
function get_attachment_meta_keys() {
	$attachment_meta_keys = [
		'_wp_attachment_metadata',
		'_wp_attachment_image_alt',
		'_wp_attachment_context',
		'_wp_attachment_backup_sizes',
		'_wp_attachment_is_custom_header',
		'_wp_attached_file',
	];

	return apply_filters( 'hm_shared_attachment_meta_keys', $attachment_meta_keys );
}

/**
 * Switch to the main blog for REST API media requests.
 *
 * @param WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
 * @param WP_REST_Server   $handler  ResponseHandler instance (usually WP_REST_Server).
 * @param WP_REST_Request  $request  Request used to generate the response.
 * @return WP_HTTP_Response The unfiltered response object.
 */
function switch_blog_in_rest( $response, $handler, $request ) {
	$route = $request->get_route();

	// Only switch to blog for the media endpoint.
	if ( strpos( $route, '/wp/v2/media' ) === false ) {
		return $response;
	}

	/**
	 * Hackity hack hack hack.
	 *
	 * If uploading an image to a post, the post permission and type checks in the
	 * REST API cannot be relied upon since they'll be checking against the Image
	 * Bank site instead of the current, appropriate site.
	 *
	 * There is no way to filter these checks, and no good way built into the
	 * plugin yet to handle attribution across sites appropriately. Therefore,
	 * the only way to allow users to upload images into posts is to not attribute
	 * images to post IDs.
	 *
	 * Here, we check to see if a post ID is set (assigning against a post) and if
	 * file data is set (a new image being uploaded) then we remove the post ID.
	 */
	if ( $request->get_param( 'post' ) && ! empty( $request->get_file_params() ) ) {
		$request->offsetUnset( 'post' );
	}

	switch_to_main_blog();

	return $response;
}

/**
 * Restore the current blog after REST callbacks have been executed.
 *
 * @param WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
 * @param WP_REST_Server   $handler  ResponseHandler instance (usually WP_REST_Server).
 * @param WP_REST_Request  $request  Request used to generate the response.
 * @return WP_HTTP_Response The unfiltered response object.
 */
function restore_blog_in_rest( $response, $handler, $request ) {
	$route = $request->get_route();

	// Only switch to blog for the media endpoint.
	if ( strpos( $route, '/wp/v2/media' ) !== false ) {
		restore_current_blog();
	}

	return $response;
}

/**
 * Force all network uploads to reside in "wp-content/uploads", and by-pass
 * "files" URL rewrite for site-specific directories.
 *
 * @link http://wordpress.stackexchange.com/q/147750/1685
 *
 * @param  array $dirs An array of upload directory path info.
 * @return array The filtered array of upload directory info.
 */
function filter_upload_dir( array $dirs ) : array {

	// Get the hostname from the base URL and network URL.
	$baseurl_host = wp_parse_url( $dirs['baseurl'], PHP_URL_HOST );
	$network_host = wp_parse_url( network_site_url(), PHP_URL_HOST );

	// Replace the baseurl with network URL.
	$dirs['baseurl'] = str_replace( $baseurl_host, $network_host, $dirs['baseurl'] );

	return $dirs;
}

/**
 * Filter image_downsize() to run against the shared library.
 *
 * @param bool         $downsize Whether to short-circuit the image downsize. Default false.
 * @param int          $id       Attachment ID for image.
 * @param array|string $size     Size of image. Image size or array of width and height values (in that order).
 *                               Default 'medium'.
 * @return false|array Array containing the image URL, width, height, and boolean for whether
 *                     the image is an intermediate size. False on failure.
 */
function filter_image_downsize( $downsize, $id, $size ) {
	// Unhook this filter to avoid infite recursion.
	remove_filter( 'image_downsize', __NAMESPACE__ . '\\filter_image_downsize' );

	// Call image_downsize from the main blog.
	switch_to_main_blog();
	$downsize = image_downsize( $id, $size );
	restore_current_blog();

	// Replace the filter.
	add_filter( 'image_downsize', __NAMESPACE__ . '\\filter_image_downsize', 10, 3 );

	return $downsize;
}

/**
 * Filter callback for interrupting REST callbacks.
 *
 * @param bool            $response Dispatch result, will be used if not empty.
 * @param WP_REST_Request $request  Request used to generate the response.
 * @return false|WP_REST_Response A WP_REST_Response if filtered, false if not.
 */
function rest_dispatch_requests( $response, $request ) {
	$attributes                             = $request->get_attributes();
	list( $callback_class, $callback_func ) = ( ! empty( $attributes['callback'] ) ) ? $attributes['callback'] : [];

	// If the request is using the WP_REST_Posts_Controller, call the same callback from our custom controller.
	if (
		is_object( $callback_class )
		&& get_class( $callback_class ) === 'WP_REST_Posts_Controller'
		&& ( $callback_func === 'create_item' || $callback_func === 'update_item' )
	) {
		$controller = new REST_Posts_Controller( get_post_type( $request->get_params()['id'] ) );

		return call_user_func( [ $controller, $callback_func ], $request );
	}

	return $response;
}

/**
 * A fork of the WordPress function for setting a post thumbnail from the shared media library.
 *
 * @param int|WP_Post $post         Post ID or post object where thumbnail should be attached.
 * @param int         $thumbnail_id Thumbnail to attach.
 * @return int|bool True on success, false on failure.
 */
function set_post_thumbnail( $post, $thumbnail_id ) {
	$post         = get_post( $post );
	$thumbnail_id = absint( $thumbnail_id );
	$attachment   = false;

	// Get the attachment from the main blog.
	if ( $thumbnail_id ) {
		switch_to_main_blog();
		$attachment = get_post( $thumbnail_id );
		restore_current_blog();
	}

	if ( $post && $thumbnail_id && $attachment ) {
		if ( wp_get_attachment_image( $thumbnail_id, 'thumbnail' ) ) {
			return update_post_meta( $post->ID, '_thumbnail_id', $thumbnail_id );
		} else {
			return delete_post_meta( $post->ID, '_thumbnail_id' );
		}
	}

	return false;
}

/**
 * Enqueue admin assets.
 */
function admin_enqueue_scripts() {
	$screen = get_current_screen();

	if ( ! in_array( $screen->id, [ 'upload', 'post', 'post-edit' ], true ) ) {
		return;
	}

	$script_url = plugins_url( 'build/media.js', dirname( __FILE__, 1 ) );

	if ( SCRIPT_DEBUG ) {
		$script_url = '//localhost:8080/plugins/shared-media-library/build/media.js';
	}

	$script_dependencies = [
		'media-models',
		'media-views',
	];

	if ( 'upload' === $screen->id ) {
		$script_dependencies[] = 'media-grid';
	}

	wp_enqueue_script( 'shared-media-models', $script_url, $script_dependencies, null, true );
}

/**
 * Filter the default plupload settings to use our custom async-upload backend.
 *
 * @param array $settings Default Plupload settings array.
 *
 * @return array The filtered
 */
function filter_plupload_default_settings( array $settings ) : array {

	// Override the URL setting with our own backend.
	$settings['url'] = plugins_url( 'inc/async-upload.php', dirname( __FILE__ ) );

	return $settings;
}

/**
 * Set a login cookie with a nonstandard plugin path.
 *
 * This allows our async-upload.php file to authenticate properly, as it should
 * if it were in a normal plugin directory.
 *
 * @param string $auth_cookie Authentication cookie.
 * @param int    $expire      The time the login grace period expires as a UNIX timestamp.
 * @param int    $expiration  The time when the authentication cookie expires as a UNIX timestamp.
 *                            Default is 14 days from now.
 * @param int    $user_id     User ID.
 * @param string $scheme      Authentication scheme. Values include 'auth', 'secure_auth', or 'logged_in'.
 */
function set_client_mu_cookie_path( string $auth_cookie, int $expire, int $expiration, int $user_id, string $scheme ) {

	// Set cookie name based on secure setting.
	$secure           = ( $scheme === 'secure_auth' );
	$auth_cookie_name = ( $secure ) ? SECURE_AUTH_COOKIE : AUTH_COOKIE;

	// Current plugin path, relative to the site URL.
	$current_plugin_url  = untrailingslashit( plugins_url( '', dirname( __FILE__, 2 ) ) );
	$current_plugin_path = str_replace( get_site_url(), '', $current_plugin_url );

	// Set the auth cookie manually if not in the standard plugin path.
	if ( $current_plugin_path !== PLUGINS_COOKIE_PATH ) {
		setcookie( $auth_cookie_name, $auth_cookie, $expire, $current_plugin_path, COOKIE_DOMAIN, $secure, true );
	}
}

/**
 * Get a thumbnail image for Jetpack images with less complication.
 *
 * Due to the way that JetPack is written (essentially, excessive checks that don't
 * run through our shared media filters) we will never get a proper image for a post.
 * This filter rectifies that by looking for the featured image with less complicated
 * methods that do pass through the shared media switches.
 *
 * @param array|bool $media   Array of images that would be good for a specific post.
 * @param int        $post_id Post ID.
 * @param array      $args    Array of options to get images.
 * @return array
 */
function jetpack_get_thumbnail_image( $media, int $post_id, array $args ) : array {
	// If, somewhow, we already have media than return it.
	if ( ! empty( $media ) ) {
		return $media;
	}

	$thumbnail = (int) get_post_thumbnail_id( $post_id );

	// No thumbnail ID? Just bounce as we have nothing to look for.
	if ( ! $thumbnail ) {
		return [];
	}

	$image_data = wp_get_attachment_image_src( $thumbnail, $args['width'], $args['height'] );

	// No data to be found? Bounce.
	if ( empty( $image_data ) ) {
		return [];
	}

	// JetPack expects an array of arrays for this return.
	return [
		[
			'type'       => 'image',
			'from'       => 'thumbnail',
			'src'        => $image_data[0],
			'src_width'  => $image_data[1],
			'src_height' => $image_data[2],
			'href'       => $image_data[0],
		],
	];
}
