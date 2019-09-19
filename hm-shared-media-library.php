<?php
/**
 * Plugin Name: HM Shared Media Library
 * Description: HM Shared Media Library
 * Author: Human Made Limited
 * Version: 0.1.0
 * Author URI: http://humanmade.com
 * @package   HM Shared Media Library
 * @version   0.1.0
 * @license   GPL v2 or later
 * Network: true
 */

namespace HumanMade\SharedMedia;

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/ajax-actions.php';
require_once __DIR__ . '/inc/class-rest-posts-controller.php';

// Replace the default wp_ajax_query_attachments handler with our own.
remove_action( 'wp_ajax_query-attachments', 'wp_ajax_query_attachments', 1 );
add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\\ajax_query_attachments', 1 );

// Replace the default wp_ajax_save_attachment handler with our own.
remove_action( 'wp_ajax_save-attachment', 'wp_ajax_save_attachment', 1 );
add_action( 'wp_ajax_save-attachment', __NAMESPACE__ . '\\ajax_save_attachment', 1 );

// Add custom Ajax handler for deleting attachments.
add_action( 'wp_ajax_delete-attachment', __NAMESPACE__ . '\\ajax_delete_attachment', 1 );

// Switch to blog during REST API requests.
add_filter( 'rest_request_before_callbacks', __NAMESPACE__ . '\\switch_blog_in_rest', 10, 3 );
add_filter( 'rest_request_after_callbacks', __NAMESPACE__ . '\\restore_blog_in_rest', 99, 3 );
add_filter( 'rest_dispatch_request', __NAMESPACE__ . '\\rest_dispatch_requests', 10, 2 );

// Modify the upload processes.
add_filter( 'upload_dir', __NAMESPACE__ . '\\filter_upload_dir', 5 ); // Ensure this fires before other filters.
add_filter( 'image_downsize', __NAMESPACE__ . '\\filter_image_downsize', 10, 3 );
add_filter( 'plupload_default_settings', __NAMESPACE__ . '\\filter_plupload_default_settings' );

// Modify data handling for attachments.
add_filter( 'wp_insert_attachment_data', __NAMESPACE__ . '\\switch_to_main_blog_in_filter' );
add_action( 'attachment_updated', __NAMESPACE__ . '\\switch_to_main_blog' );
add_action( 'add_attachment', __NAMESPACE__ . '\\restore_current_blog_in_action', 11 );
add_action( 'edit_attachment', __NAMESPACE__ . '\\restore_current_blog_in_action', 11 );

// Enqueue scripts.
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts' );

// Set auth cookies for the plugins directory.
add_action( 'set_auth_cookie', __NAMESPACE__ . '\\set_client_mu_cookie_path', 10, 5 );

// Customizations for third-party plugins.
add_filter( 'jetpack_images_get_images', __NAMESPACE__ . '\\jetpack_get_thumbnail_image', 10, 3 );
