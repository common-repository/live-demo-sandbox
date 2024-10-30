<?php
/**
 * The dispatcher where all the ajax request pass through after validation
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * Dispatcher class
 */
class Dispatcher {

	/**
	 * App ID
	 *
	 * @var string
	 */
	private $app_id;

	/**
	 * Controlles class array
	 *
	 * @var array
	 */
	private $controllers;

	/**
	 * Dispatcher registration in constructor
	 *
	 * @param string $app_id The unique app ID
	 * @param array  $controllers Initial controllers array
	 */
	public function __construct( string $app_id, array $controllers ) {

		// Register ajax handlers only if it is ajax call
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$this->app_id      = $app_id;
		$this->controllers = $controllers;

		$this->registerControllers();
	}

	/**
	 * Register ajax request handlers
	 *
	 * @return void
	 */
	public function registerControllers() {

		$registered_methods = array();

		// Loop through controllers classes
		foreach ( $this->controllers as $class ) {

			// Loop through controller methods in the class
			foreach ( $class::PREREQUISITES as $method => $prerequisites ) {
				if ( in_array( $method, $registered_methods, true ) ) {
					continue;
				}

				// Determine ajax handler types
				$handlers    = array();
				$handlers [] = 'wp_ajax_' . $this->app_id . '_' . $method;

				// Check if norpriv necessary
				if ( ( $prerequisites['nopriv'] ?? false ) === true ) {
					$handlers[] = 'wp_ajax_nopriv_' . $this->app_id . '_' . $method;
				}

				// Loop through the handlers and register
				foreach ( $handlers as $handler ) {
					add_action(
						$handler,
						function() use ( $class, $method, $prerequisites ) {
							$this->dispatch( $class, $method, $prerequisites );
						}
					);
				}

				$registered_methods[] = $method;
			}
		}
	}

	/**
	 * Dispatch request to target handler after doing verifications
	 *
	 * @param string $class         The class to dispatch the request to
	 * @param string $method        The method of the class to invoke
	 * @param array  $prerequisites Controller access prerequisites
	 *
	 * @return void
	 */
	public function dispatch( $class, $method, $prerequisites ) {

		// Nonce verification
		$matched = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['nonce_action'] ?? '' ) ) );
		$matched = $matched || wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['nonce_action'] ?? '' ) ) );
		$is_post = strtolower( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) === 'post';

		// We can't really restrict GET requests for nonce.
		// Because GET requests usually comes from bookmarked URL or direct links where nonce doesn't really make any sense.
		// Rather we've enhanced security by verifying accepted argument data types, sanitizing and escaping in all cases.
		if ( $is_post && ! $matched ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed!' ) );
		}

		// Validate access privilege
		$required_roles = _Array::getArray( ( $prerequisites['role'] ?? array() ), true );
		$required_roles = apply_filters( 'solidie_controller_roles_' . $this->app_id, $required_roles, $class, $method, $prerequisites );
		if ( ! User::validateRole( get_current_user_id(), $required_roles ) ) {
			wp_send_json_error( array( 'message' => 'You are not authorized!' ) );
		}

		// Now pass to the action handler function
		if ( ( ! is_object( $class ) && ! class_exists( $class ) ) || ! method_exists( $class, $method ) ) {
			wp_send_json_error( array( 'message' => 'Invalid Endpoint!' ) );
		}

		// Prepare request data
		$params = _Array::getMethodParams( $class, $method );

		// Pick only the used arguments in the mathod from request data
		$args = array();
		foreach ( $params as $param => $configs ) {
			$args[ $param ] = wp_unslash( ( $is_post ? ( $_POST[ $param ] ?? null ) : ( $_GET[ $param ] ?? null ) ) ?? $_FILES[ $param ] ?? $configs['default'] ?? null );
		}

		// Sanitize and type cast
		$args = _Array::sanitizeRecursive( $args );

		// Now verify all the arguments expected data types after casting
		foreach ( $args as $name => $value ) {

			// The request data value type
			$arg_type = gettype( $value );

			// The acceptable type by the method
			$param_type = $params[ $name ]['type'];

			// Check if request data type and accepted type matched
			if ( $arg_type != $param_type ) {

				$is_null    = null === $value || '' === $value;
				$is_numeric = $is_null || is_numeric( $value );

				if ( 'string' === $param_type && $is_numeric ) {
					$args[ $name ] = (string) $value;

				} elseif ( 'double' === $param_type && $is_numeric ) {
					$args[ $name ] = (float) $value;

				} elseif ( 'integer' === $param_type && $is_numeric ) {
					$args[ $name ] = (int) $value;

				} elseif ( 'boolean' === $param_type && $is_numeric ) {
					$args[ $name ] = (bool) $value;

				} elseif ( 'array' === $param_type && ( $is_null || 'integer' === $arg_type ) ) {
					// Sometimes 0 can be passed instead of array
					// Then use empty array rather
					// So far the seneario has found when thumbnail is not set in content editor
					$args[ $name ] = array();

				} else {
					wp_send_json_error(
						array(
							'message'  => 'Invalid request data!',
							'param'    => $name,
							'accepts'  => $param_type,
							'received' => $arg_type,
						)
					);
				}
			}
		}

		// Then pass to method with spread as the parameter count is variable.
		$args = array_values( $args );
		if ( is_object( $class ) ) {
			$class->$method( ...$args );
		} else {
			$class::$method( ...$args );
		}
	}
}
