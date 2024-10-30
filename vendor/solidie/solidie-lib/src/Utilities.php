<?php
/**
 * The utilities functionalities
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * The class
 */
class Utilities {

	/**
	 * Get equivalent days from period
	 *
	 * @param string $period The period string
	 * @return int|null
	 */
	public static function periodToDays( string $period ) {

		$days = null;

		switch ( $period ) {

			case 'month':
				$days = 30;
				break;

			case 'year':
				$days = 365;
				break;
		}

		return $days;
	}

	/**
	 * Check if the page is a Crew Dashboard
	 *
	 * @param string $root_menu_slug Root menu slug
	 * @param string $sub_page Optional sub page name to match too
	 * @return boolean
	 */
	public static function isAdminScreen( string $root_menu_slug, string $sub_page = null ) {
		$is_dashboard = is_admin() && get_admin_page_parent() === $root_menu_slug;

		if ( $is_dashboard && null !== $sub_page ) {

			// Accessing $_GET['page'] directly will most likely show nonce error in wpcs check.
			// However checking nonce is pointless since visitor can visit dashboard pages from bookmark or direct link.

			$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pages        = ! is_array( $sub_page ) ? array( $sub_page ) : $sub_page;
			$is_dashboard = in_array( $current_page, $pages, true );
		}

		return $is_dashboard;
	}

	/**
	 * Check if Solidie Pro version installed or not
	 *
	 * @param string  $path Plugin path The plugin path
	 * @param boolean $check_active Whether to check if active
	 *
	 * @return boolean
	 */
	public static function isPluginInstalled( string $path, bool $check_active = false ) {

		if ( file_exists( trailingslashit( WP_PLUGIN_DIR ) . $path ) ) {
			return true && ( ! $check_active || ( function_exists( 'is_plugin_active' ) && is_plugin_active( $path ) ) );
		}

		return false;
	}

	/**
	 * Get unique ID to point solid app in any setup
	 *
	 * @param string $url The URL to get app ID by
	 * @return string
	 */
	public static function getAppId( $url ) {
		$pattern = '/\/([^\/]+)\/wp-content\/(plugins|themes)\/([^\/]+)\/.*/';
		preg_match( $pattern, $url, $matches );

		$parsed_string = strtolower( "CrewMat_{$matches[1]}_{$matches[3]}" );
		$app_id        = preg_replace( '/[^a-zA-Z0-9_]/', '', $parsed_string );

		return $app_id;
	}

	/**
	 * Generate admin page urls
	 *
	 * @param string $page The page name
	 * @return string
	 */
	public static function getBackendPermalink( string $page ) {
		return add_query_arg(
			array(
				'page' => $page,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Return page list especially for settings page
	 *
	 * @param init $limit How many pages t oget
	 *
	 * @return array
	 */
	public static function getPageList( $limit ) {
		// Define arguments for get_posts to retrieve pages
		$args = array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => $limit,
		);

		// Get the list of pages
		$pages = get_posts( $args );

		$page_list = array_map(
			function ( $page ) {
				return array(
					'id'    => (int) $page->ID,
					'label' => $page->post_title,
				);
			},
			$pages
		);

		return $page_list;
	}
}
