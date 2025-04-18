<?php
/**
 * Unused Media Cleaner Plugin
 *
 * This plugin provides functionality to identify and delete unused media attachments
 * in a WordPress installation. It includes methods for retrieving unused media,
 * rendering an admin page, and handling AJAX requests for media management.
 *
 * @package Unused_Media_cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Unused_Media_Cleaner
 *
 * This class provides functionality to identify and delete unused media attachments
 * in a WordPress installation. It includes methods for retrieving unused media,
 * rendering an admin page, and handling AJAX requests for media management.
 */
class Unused_Media_Cleaner {


	/**
	 * Constructor for the Unused_Media_Cleaner class.
	 *
	 * Initializes the class by adding necessary WordPress hooks for admin menu,
	 * scripts, and AJAX actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'uumc_register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'uumc_enqueue_assets' ) );
		add_action( 'wp_ajax_uumc_get_attachments', array( $this, 'uumc_ajax_get_attachments' ) );
		add_action( 'wp_ajax_uumc_delete_attachments', array( $this, 'uumc_ajax_delete_attachments' ) );
	}

	/**
	 * Handles actions to perform on plugin activation.
	 *
	 * This function can be used to set up initial configurations or flush rewrite rules.
	 *
	 * @return void
	 */
	public static function uumc_activate() {
		// Optional: run on plugin activation.
	}

	/**
	 * Handles actions to perform on plugin deactivation.
	 *
	 * This function can be used to clean up configurations or flush rewrite rules.
	 *
	 * @return void
	 */
	public static function uumc_deactivate() {
		// Optional: run on plugin deactivation.
	}

	/**
	 * Registers the Media Cleaner submenu in the WordPress admin menu.
	 *
	 * This function adds a submenu under the "Media" menu in the WordPress admin dashboard.
	 *
	 * @return void
	 */
	public function uumc_register_menu() {
		add_submenu_page(
			'upload.php',
			'Media Cleaner',
			'<span class="dashicons dashicons-trash"></span> Cleaner',
			'manage_options',
			'uumc-media-cleaner',
			array( $this, 'uumc_render_page' )
		);
	}

	/**
	 * Renders the Media Cleaner admin page.
	 *
	 * This function outputs the HTML for the Media Cleaner page in the WordPress admin dashboard.
	 *
	 * @return void
	 */
	public function uumc_render_page() {
		?>
		<div class="wrap">
			<h1>Media Cleaner</h1>
			<button id="umc-delete-selected" class="button button-secondary">Delete Selected</button>
			<button id="umc-delete-all" class="button button-secondary">Delete All</button>

			<table id="umc-media-table" class="wp-list-table widefat striped hover">
				<thead>
					<tr>
						<th><input type="checkbox" id="umc-select-all" /></th>
						<th>ID</th>
						<th>Preview</th>
						<th>Filename</th>
						<th>URL</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Enqueues scripts and styles for the Media Cleaner admin page.
	 *
	 * This function loads the necessary CSS and JavaScript files for the Media Cleaner
	 * page in the WordPress admin dashboard.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function uumc_enqueue_assets( $hook ) {
		if ( 'media_page_uumc-media-cleaner' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'datatables-css', UUMC_PLUGIN_URL . 'assets/css/jquery.dataTables.min.css', array(), UUMC_VERSION );
		wp_enqueue_script( 'datatables', UUMC_PLUGIN_URL . 'assets/js/jquery.dataTables.min.js', array( 'jquery' ), UUMC_VERSION, true );
		wp_enqueue_script( 'uumc-script', UUMC_PLUGIN_URL . 'assets/js/umc-script.js', array( 'jquery', 'datatables' ), filemtime( UUMC_PLUGIN_PATH . 'assets/js/umc-script.js' ), true );

		wp_localize_script(
			'uumc-script',
			'uumc_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'uumc_nonce' ),
			)
		);
	}

	/**
	 * Retrieves a list of unused media attachments.
	 *
	 * This function identifies media attachments in WordPress that are not used
	 * as featured images, in custom fields, or in post content.
	 *
	 * @return array List of unused attachment IDs.
	 */
	private function uumc_get_unused_attachments() {
		$all_attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_status'    => 'inherit',
				'fields'         => 'ids',
			)
		);

		$unused = array();

		foreach ( $all_attachments as $attachment_id ) {
			if ( ! $this->uumc_is_attachment_used( $attachment_id ) ) {
				$unused[] = $attachment_id;
			}
		}

		return $unused;
	}

	/**
	 * Checks if a media attachment is used in the WordPress site.
	 *
	 * This function verifies if the given attachment ID is used as a featured image,
	 * in post content, custom fields, or other metadata.
	 *
	 * @param int $attachment_id The ID of the attachment to check.
	 * @return bool True if the attachment is used, false otherwise.
	 */
	private function uumc_is_attachment_used( $attachment_id ) {
		global $wpdb;

		$url         = wp_get_attachment_url( $attachment_id );
		$escaped_url = '%' . $wpdb->esc_like( $url ) . '%';

		$queries = array(
			// 1. Featured image (excluding revisions).
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->postmeta pm
				 JOIN $wpdb->posts p ON pm.post_id = p.ID
				 WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d AND p.post_type != 'revision'
				 LIMIT 1",
				$attachment_id
			),

			// 2. Exact match in postmeta (e.g., ACF image fields).
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->postmeta pm
				 JOIN $wpdb->posts p ON pm.post_id = p.ID
				 WHERE pm.meta_value = %d AND p.post_type != 'revision'
				 LIMIT 1",
				$attachment_id
			),

			// 3. Serialized postmeta (e.g., ACF galleries).
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->postmeta pm
				 JOIN $wpdb->posts p ON pm.post_id = p.ID
				 WHERE pm.meta_value LIKE %s AND p.post_type != 'revision'
				 LIMIT 1",
				'%"' . $attachment_id . '"%'
			),

			// 4. Post content (Gutenberg, WPBakery, etc.).
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->posts WHERE post_content LIKE %s AND post_type != 'revision' LIMIT 1",
				$escaped_url
			),

			// 5. Elementor data (JSON).
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->postmeta pm
				 JOIN $wpdb->posts p ON pm.post_id = p.ID
				 WHERE pm.meta_key = '_elementor_data' AND pm.meta_value LIKE %s AND p.post_type != 'revision'
				 LIMIT 1",
				$escaped_url
			),

			// 6. WPBakery sometimes stores as IDs in content.
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->posts WHERE post_content LIKE %s AND post_type != 'revision' LIMIT 1",
				'%"' . $attachment_id . '"%'
			),

			// 7. Termmeta.
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->termmeta WHERE meta_value = %d OR meta_value LIKE %s LIMIT 1",
				$attachment_id,
				'%"' . $attachment_id . '"%'
			),

			// 8. Usermeta.
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->usermeta WHERE meta_value = %d OR meta_value LIKE %s LIMIT 1",
				$attachment_id,
				'%"' . $attachment_id . '"%'
			),
		);

		foreach ( $queries as $query ) {
			if ( $wpdb->get_var( $query ) ) { //phpcs:ignore
				return true;
			}
		}

		return false;
	}


	/**
	 * Handles the AJAX request to retrieve unused media attachments.
	 *
	 * This function checks user permissions, retrieves unused attachments,
	 * and returns the data in JSON format.
	 *
	 * @return void Outputs JSON response with unused attachments or error message.
	 */
	public function uumc_ajax_get_attachments() {
		check_ajax_referer( 'uumc_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$attachments = $this->uumc_get_unused_attachments();

		$data = array();

		foreach ( $attachments as $id ) {
			$url      = wp_get_attachment_url( $id );
			$filename = basename( $url );
			$thumb    = wp_get_attachment_image( $id, array( 60, 60 ), true );

			$data[] = array(
				'id'       => $id,
				'thumb'    => $thumb,
				'filename' => esc_html( $filename ),
				'url'      => esc_url( $url ),
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * Handles the AJAX request to delete selected media attachments.
	 *
	 * This function checks user permissions, validates input IDs,
	 * deletes the specified attachments, and returns a JSON response.
	 *
	 * @return void Outputs JSON response with the number of deleted attachments or an error message.
	 */
	public function uumc_ajax_delete_attachments() {
		check_ajax_referer( 'uumc_nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( 'No IDs provided.' );
		}

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_attachment( $id, true ) ) {
				++$deleted;
			}
		}

		wp_send_json_success( "Deleted {$deleted} attachments." );
	}
}
