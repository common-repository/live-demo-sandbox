<?php
/**
 * Number related functions
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * Number handler class
 */
class _Number {

	/**
	 * Get integer
	 *
	 * @param int $num Source number
	 * @param int $min Min cap
	 * @param int $max Max cap
	 * @return int
	 */
	public static function getInt( $num, $min = null, $max = null ) {

		$num = is_numeric( $num ) ? (int) $num : 0;

		if ( null !== $min && $num < $min ) {
			$num = $min;
		}

		if ( null !== $max && $num > $max ) {
			$num = $max;
		}

		return $num;
	}
}
