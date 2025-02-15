<?php
/**
 * Database handler
 *
 * @package live-demo-sandbox
 */

namespace Solidie_Sandbox\Models;

use SolidieLib\_Number;
use Solidie_Sandbox\Main;

/**
 * Databse handler class
 */
class DB {

	/**
	 * Prepare the table name, add prefixes
	 *
	 * @param  string $name      The table name to get prefixed
	 * @param  array  $arguments Callstatic arguments
	 * @return string
	 */
	public static function __callStatic( $name, $arguments ) {
		global $wpdb;
		return $wpdb->prefix . Main::$configs->db_prefix . $name;
	}

	/**
	 * Remove unnecessary things from the SQL
	 *
	 * @param  string $sql The raw exported SQL file
	 * @return array
	 */
	private static function purgeSQL( string $sql ) {
		$pattern = '/(CREATE TABLE .*?);/si';
		preg_match_all( $pattern, $sql, $matches );

		return $matches[0];
	}

	/**
	 * Apply dynamic collation, charset, prefix etc.
	 *
	 * @param  array $queries Array of single queries.
	 * @return array
	 */
	private static function applyDynamics( array $queries ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		return array_map(
			function ( $query ) use ( $wpdb, $charset_collate ) {
				// Replace table prefix
				$query = str_replace( 'wp_slds_', $wpdb->prefix . Main::$configs->db_prefix, $query );

				// Replace table configs
				$query = str_replace( 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci', $charset_collate, $query );

				// Replace column configs
				$query = str_replace( 'CHARACTER SET utf8mb4', 'CHARACTER SET ' . $wpdb->charset, $query );
				$query = str_replace( 'COLLATE utf8mb4_unicode_520_ci', 'COLLATE ' . $wpdb->collate, $query );

				return $query;
			},
			$queries
		);
	}

	/**
	 * Inspect all the things in queries
	 *
	 * @param array $queries Single query array
	 * @return array
	 */
	private static function getInspected( array $queries ) {
		foreach ( $queries as $index => $query ) {

			// Pick table name
			preg_match( '/CREATE TABLE IF NOT EXISTS `(.*)`/', $query, $matches );
			$table_name = $matches[1];

			// Pick column definitions
			$lines   = explode( PHP_EOL, $query );
			$columns = array();
			foreach ( $lines as $line ) {

				$line = trim( $line );
				if ( empty( $line ) || ! ( strpos( $line, '`' ) === 0 ) ) {
					continue;
				}

				$column_name             = substr( $line, 1, strpos( $line, '`', 2 ) - 1 );
				$columns[ $column_name ] = trim( $line, ',' );
			}

			$queries[ $index ] = array(
				'query'   => $query,
				'table'   => $table_name,
				'columns' => $columns,
			);
		}

		return $queries;
	}

	/**
	 * Import the DB from SQL file.
	 * ---------------------------
	 * Must have in the SQL
	 *
	 * 1. Table prefix: wp_slds_
	 * 2. ENGINE=InnoDB
	 * 3. DEFAULT CHARSET=utf8mb4
	 * 4. COLLATE=utf8mb4_unicode_520_ci
	 * 5. Column CHARACTER SET utf8mb4
	 * 6. Column COLLATE utf8mb4_unicode_520_ci
	 * 7. CREATE TABLE IF NOT EXISTS
	 *
	 * So these can be replaced with dymanic configuration correctly. And no onflict with existing table with same names.
	 *
	 * @param  string $sql Raw exported SQL file contents
	 * @return void
	 */
	public static function import( $sql ) {
		$queries = self::purgeSQL( $sql );
		$queries = self::applyDynamics( $queries );
		$queries = self::getInspected( $queries );

		// Load helper methods if not loaded already
		include_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;

		foreach ( $queries as $query ) {
			dbDelta( $query['query'] );

			// Add missing columns to the table
			// Because the previous dbDelta just creates new table if not exists already
			// So missing columns doesn't get created automatically.
			$current_columns = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME = %s',
					$wpdb->dbname,
					$query['table']
				)
			);

			// Loop through the columns in latest SQL file
			foreach ( $query['columns'] as $column => $column_definition ) {
				// Add the columns if not in the database
				if ( ! in_array( $column, $current_columns, true ) ) {
					$wpdb->query( "ALTER TABLE {$query['table']} ADD {$column_definition}" );
				}
			}
		}
	}

	/**
	 * Get limit for queries
	 *
	 * @param  int|null $limit The limit to prepare
	 * @return int
	 */
	public static function getLimit( $limit = null ) {
		if ( ! is_numeric( $limit ) ) {
			$limit = 20;
		}
		return apply_filters( 'slds_query_result_count', _Number::getInt( $limit, 1 ) );
	}

	/**
	 * Get page num to get results for
	 *
	 * @param  int|null $page The page to prepare
	 * @return int
	 */
	public static function getPage( $page = null ) {
		return _Number::getInt( $page, 1 );
	}
}
