<?php
/**
 * Date time methods
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * The enriched array class
 */
class _DateTime {

	/**
	 * Get previous year month
	 *
	 * @param string $year_month Year month
	 *
	 * @return string
	 */
	public static function getPreviousYearMonth( $year_month ) {

		// Create a DateTime object from the provided year-month string
		$date = \DateTime::createFromFormat( 'Y-m', $year_month );

		// Subtract one month from the date
		$date->modify( '-1 month' );

		// Return the previous year-month in 'YYYY-MM' format
		return $date->format( 'Y-m' );
	}
}
