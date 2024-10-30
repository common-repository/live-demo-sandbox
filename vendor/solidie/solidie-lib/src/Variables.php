<?php
/**
 * The variable provider functionalities
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * The class
 */
class Variables {

	/**
	 * Configs
	 *
	 * @var object
	 */
	private $configs;

	/**
	 * Variable constructor
	 *
	 * @param object $configs App configs
	 */
	public function __construct( $configs ) {

		$this->configs = $configs;

		// Load css colors and style
		add_action( 'wp_head', array( $this, 'loadStyles' ) );
		add_action( 'admin_head', array( $this, 'loadStyles' ) );
	}

	/**
	 * Get variables
	 *
	 * @return array
	 */
	public function get() {

		$nonce_action = '_solidie_' . str_replace( '-', '_', gmdate( 'Y-m-d' ) );
		$nonce        = wp_create_nonce( $nonce_action );
		$user         = wp_get_current_user();

		// Determine the react react root path
		$parsed    = wp_parse_url( get_home_url() );
		$root_site = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $parsed['host'] . ( ! empty( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
		$home_path = trim( $parsed['path'] ?? '', '/' );
		$page_path = is_singular() ? trim( str_replace( $root_site, '', get_permalink( get_the_ID() ) ), '/' ) : null;

		return array(
			'is_admin'     => is_admin() ? true : false,
			'action_hooks' => array(),
			'filter_hooks' => array(),
			'mountpoints'  => (object) array(),
			'home_path'    => $home_path,
			'page_path'    => $page_path,
			'app_id'       => $this->configs->app_id,
			'nonce'        => $nonce,
			'nonce_action' => $nonce_action,
			'colors'       => $this->getColorPallete(),
			'opacities'    => Colors::getOpacities(),
			'contrast'     => Colors::CONTRAST_FACTOR,
			'text_domain'  => $this->configs->text_domain,
			'date_format'  => get_option( 'date_format' ),
			'time_format'  => get_option( 'time_format' ),
			'is_apache'    => is_admin() ? strpos( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ), 'Apache' ) !== false : null,
			'bloginfo'     => array(
				'name' => get_bloginfo( 'name' ),
			),
			'user'         => array(
				'id'           => $user ? $user->ID : 0,
				'first_name'   => $user ? $user->first_name : null,
				'last_name'    => $user ? $user->last_name : null,
				'email'        => $user ? $user->user_email : null,
				'display_name' => $user ? $user->display_name : null,
				'avatar_url'   => $user ? get_avatar_url( $user->ID ) : null,
				'username'     => $user ? $user->user_login : null,
			),
			'settings'     => array(),
			'permalinks'   => array(
				'home_url' => get_home_url(),
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'logout'   => htmlspecialchars_decode( wp_logout_url( get_home_url() ) ),
				'logo_url' => wp_get_attachment_image_url( get_theme_mod('custom_logo'), 'full' ),
			),
		);
	}

	/**
	 * Get color pallete
	 *
	 * @return array
	 */
	public function getColorPallete() {
		return Colors::getColors( $this->configs->color_scheme ?? null );
	}

	/**
	 * Load styles
	 *
	 * @return void
	 */
	public function loadStyles() {

		// Load dynamic colors
		$dynamic_colors = $this->getColorPallete();
		$solidie_colors = '.' . $this->configs->app_id . '{';
		foreach ( $dynamic_colors as $name => $code ) {
			$solidie_colors .= '--solidie-color-' . esc_attr( $name ) . ':' . esc_attr( $code ) . ';';
		}
		$solidie_colors .= '}';

		$handler = 'solidie-colors-scheme';	

		if ( ! wp_style_is( $handler, 'enqueued' )  ) {
			wp_enqueue_style( $handler, $this->configs->url . 'vendor/solidie/solidie-lib/dist/libraries/colors-loader.css' );
		}

		wp_add_inline_style( $handler, $solidie_colors );
	}
}
