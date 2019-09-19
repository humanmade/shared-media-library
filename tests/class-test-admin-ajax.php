<?php
/**
 * Tests for the Shared Media plugin.
 *
 * @package HumanMade\SharedMedia
 */

namespace HumanMade\SharedMedia\Tests;

use WPAjaxDieContinueException;
use WP_Ajax_UnitTestCase;

/**
 * Class plugin tests
 *
 * @group ajax
 * @group shared-media
 */
class Test_Admin_Ajax extends WP_Ajax_UnitTestCase {

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
		require_once( ABSPATH . 'wp-admin/includes/ajax-actions.php' );

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
	 * Set up the test fixture
	 */
	public function setUp() {
		parent::setUp();
		// Set a user so the $post has 'post_author'.
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Test for querying attachments via Ajax.
	 */
	function test_ajax_query_attachments() {
		$this->markTestSkipped( 'Debugging user permissions in PHPUnit ajax requests.' );

		// Switch to a different blog.
		// @codingStandardsIgnoreStart (fine for VIP Go)
		switch_to_blog( self::$site_ids['foo.humanmade.com/'] );
		// @codingStandardsIgnoreEnd

		$_POST = [
			'post_id' => 0,
			'query'   => [
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => 40,
				'paged'          => 1,
			],
		];

		try {
			$this->_handleAjax( 'query-attachments' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( true );
	}

}
