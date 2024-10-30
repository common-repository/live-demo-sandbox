<?php
/**
 * WP plugin readme file parser
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * Readme file parse class
 */
class Readme {

	/**
	 * Readme path
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Contructor
	 *
	 * @param string $path The readme path
	 */
	public function __construct( string $path ) {
		$this->path = $path;
	}

	/**
	 * Get latest changelog from readme.txt file.
	 *
	 * @return array
	 */
	public function getLatestChangelog() {

		$changelog = file_get_contents( $this->path );
		$changelog = explode( '== Changelog ==', $changelog );
		$changelog = trim( end( $changelog ) );
		$changelog = explode( PHP_EOL . PHP_EOL, $changelog )[0];
		$lines     = array_slice( explode( PHP_EOL, $changelog ), 2 );
		$lines     = array_map(
			function ( $line ) {
				return trim( trim( $line, '*' ) );
			},
			$lines
		);

		return array_filter( $lines );
	}
}
