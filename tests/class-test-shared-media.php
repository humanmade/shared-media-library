<?php
/**
 * Tests for the Shared Media plugin.
 *
 * @package HumanMade\SharedMedia
 */

namespace HumanMade\SharedMedia\Tests;

use HumanMade\SharedMedia;
use WP_Query;
use WP_UnitTestCase;

/**
 * Class plugin tests
 *
 * @group shared-media
 */
class Test_Shared_Media extends WP_UnitTestCase {

	/**
	 * An array of site IDs.
	 *
	 * @var array $site_ids
	 */
	protected static $site_ids;

	/**
	 * An admin user ID.
	 *
	 * @var int $admin_id
	 */
	protected static $admin_id;

	/**
	 * An author user ID.
	 *
	 * @var int $user_id
	 */
	protected static $user_id;

	/**
	 * An array of generated attachment IDs.
	 *
	 * @var array $attachment_ids
	 */
	protected static $attachment_ids = [];

	/**
	 * Set up data before running tests.
	 *
	 * @param WP_UnitTest_Factory $factory A WP_UnitTest_Factory instance.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Get access to `wp_handle_upload()` and `wp_handle_sideload()`.
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		// Create our test sites.
		self::$site_ids = [
			'humanmade.com/'     => [
				'domain' => 'humanmade.com',
				'path'   => '/',
			],
			'foo.humanmade.com/' => [
				'domain' => 'foo.humanmade.com',
				'path'   => '/',
			],
			'bar.humanmade.com/' => [
				'domain' => 'bar.humanmade.com',
				'path'   => '/',
			],
		];

		foreach ( self::$site_ids as &$id ) {
			$id = $factory->blog->create( $id );
		}

		unset( $id );

		// Create test users.
		self::$admin_id = $factory->user->create( [ 'role' => 'administrator' ] );
		self::$user_id  = $factory->user->create( [ 'role' => 'author' ] );

		// Create some test attachments in the main blog.
		for ( $i = 0; $i < 5; $i++ ) {
			self::$attachment_ids[] = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png', 0 );
		}

		// Normalize the array of attachments.
		sort( self::$attachment_ids );
	}

	/**
	 * Tear down data before running tests.
	 */
	public static function wpTearDownAfterClass() {
		foreach ( self::$site_ids as $id ) {
			wpmu_delete_blog( $id, true );
		}
	}

	/**
	 * Set up before running each test.
	 */
	public function setUp() {
		parent::setUp();

		// Switch to the main blog before running each test.
		SharedMedia\switch_to_main_blog();
	}

	/**
	 * Test that wp_handle_upload() adds the post to the main blog.
	 */
	function test_wp_handle_upload_is_shared() {
		// Switch to a different blog before uploading.
		// @codingStandardsIgnoreStart (fine for VIP Go)
		switch_to_blog( self::$site_ids['foo.humanmade.com/'] );
		// @codingStandardsIgnoreEnd
		$this->assertEquals( get_current_blog_id(), self::$site_ids['foo.humanmade.com/'] );

		$test_file = DIR_TESTDATA . '/images/test-image.jpg';
		// Make a copy of this file as it gets moved during the file upload.
		$tmp_name = wp_tempnam( $test_file );
		copy( $test_file, $tmp_name );

		// Create a mock $_FILES array to pass to media_handle_upload.
		$_FILES['upload'] = [
			'tmp_name' => $tmp_name,
			'name'     => 'This is a test.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $test_file ),
		];

		$post_id = media_handle_upload(
			'upload', 0, [], [
				'action'    => 'test_shared_uploads',
				'test_form' => false,
			]
		);

		unset( $_FILES['upload'] );

		// Switch to the main blog.
		SharedMedia\switch_to_main_blog();
		$this->assertEquals( get_current_blog_id(), get_network()->site_id );

		// The attachment post should be available on the main network.
		$post = get_post( $post_id );

		$this->assertEquals( 'This is a test', $post->post_title );
	}

	/**
	 * Ensure REST GET requests return the same group of attachments.
	 */
	function test_get_attachments_from_rest() {
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );

		$rest_ids = wp_list_pluck( $response->data, 'id' );
		sort( $rest_ids );

		$this->assertEquals( self::$attachment_ids, $rest_ids );

		// Switch to a different blog.
		// @codingStandardsIgnoreStart (fine for VIP Go)
		switch_to_blog( self::$site_ids['foo.humanmade.com/'] );
		// @codingStandardsIgnoreEnd

		$request  = new \WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );

		$rest_ids = wp_list_pluck( $response->data, 'id' );
		sort( $rest_ids );

		$this->assertEquals( self::$attachment_ids, $rest_ids );
	}

	/**
	 * Ensure REST GET requests return the same individual attachment.
	 */
	function test_get_specific_attachment_from_rest() {
		$attachment_id = self::$attachment_ids[0];
		$attachment    = get_post( $attachment_id );

		// Create request to be reused.
		$request = new \WP_REST_Request( 'GET', sprintf( '/wp/v2/media/%s', $attachment_id ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( isset( $response->data['slug'] ) );
		$this->assertSame( $attachment->post_name, $response->data['slug'] );

		// Switch to a different blog.
		// @codingStandardsIgnoreStart (fine for VIP Go)
		switch_to_blog( self::$site_ids['foo.humanmade.com/'] );
		// @codingStandardsIgnoreEnd

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( isset( $response->data['slug'] ) );
		$this->assertSame( $attachment->post_name, $response->data['slug'] );
	}

	/**
	 * Ensure all queries for attachments query the main blog's post table.
	 */
	function test_wp_query_attachments() {
		$this->markTestSkipped( 'Not yet implemented for WP_Query.' );

		// Switch to a different blog.
		// @codingStandardsIgnoreStart (fine for VIP Go)
		switch_to_blog( self::$site_ids['foo.humanmade.com/'] );
		// @codingStandardsIgnoreEnd

		$query = new WP_Query( [
			'post_type'   => 'attachment',
			'post_status' => 'inherit', // Required for attachments.
			'fields'      => 'ids',
		] );

		// Presorted.
		$expected = self::$attachment_ids;

		$actual = $query->posts;
		sort( $actual );

		$this->assertEquals( $expected, $actual, 'Could not query attachments from foo.humanmade.com' );
	}

}
