<?php
/**
 * Database handler
 *
 * @package solidie-lib
 */

namespace SolidieLib;

use SolidieLib\_Array;

/**
 * Databse handler class
 */
class DB {

	/**
	 * @var object $configs Configuration object
	 * @property string $app_id App ID
	 * @property string $db_prefix Database table prefix
	 * @property string $activation_hook Activation hook name
	 * @property string $db_deployed_hook Hook name to be called when db is deployed
	 * @property string $sql_path SQL file path
	 */
	private object $configs;

	/**
	 * @var string $db_configs_key Database configs option key
	 */
	private string $db_configs_key;

	/**
	 * Constructor
	 *
	 * @param object $configs Configuration object
	 * @return void
	 */
	public function __construct( $configs ) {
		
		$this->configs = $configs;
		$this->db_configs_key = $configs->app_id . '_db_configs';
		$this->prepareTableNames();

		add_action( $configs->activation_hook, array( $this, 'importDB' ) );
		add_action( 'admin_init', array( $this, 'importDBOnUpdate' ), 0 );
	}

	/**
	 * Trigger import db function on plugin update
	 *
	 * @return void
	 */
	public function importDBOnUpdate() {

		$last_version = $this->getDBConfigs( 'version' );
		
		if ( empty( $last_version ) || version_compare( $last_version, $this->configs->version, '<' ) ) {
			$this->importDB();
		}
	}

	/**
	 * Get database configs
	 *
	 * @param string $key The key to get
	 * @return mixed
	 */
	private function getDBConfigs( string $key = null ) {
		$configs = _Array::getArray( get_option( $this->db_configs_key ) );
		return $key ? ( $configs[ $key ] ?? null )  : $configs;
	}

	/**
	 * Import database
	 *
	 * @return void
	 */
	public function importDB() {
		
		$this->import( file_get_contents( $this->configs->sql_path ) );

		$this->updateDBConfig( 'version', $this->configs->version );
		$this->updateDBConfig( 'tables', null );

		if ( ! empty( $this->configs->db_deployed_hook ) ) {
			do_action( $this->configs->db_deployed_hook );
		}
	}

	/**
	 * Update a specific database config value
	 *
	 * @param string $key   The key to update
	 * @param mixed  $value The value to set
	 * @return bool         True if the update was successful, false otherwise
	 */
	public function updateDBConfig( string $key, $value ) {
		$configs = $this->getDBConfigs();
		$configs[ $key ] = $value;
		return update_option( $this->db_configs_key, $configs, true );
	}

	/**
	 * Add table names into wpdb object
	 *
	 * @return void
	 */
	private function prepareTableNames() {

		global $wpdb;

		$table_names = _Array::getArray( $this->getDBConfigs( 'tables' ) );

		if ( empty( $table_names ) ) {

			$inspected = $this->getInspected( $this->purgeSQL( file_get_contents( $this->configs->sql_path ) ) );
			
			$table_names = array_column( $inspected, 'table_only' );

			$this->updateDBConfig( 'tables', $table_names );
		}

		foreach ( $table_names as $table_name ) {
			$wpdb->{$this->configs->db_prefix . $table_name} = $wpdb->prefix . $this->configs->db_prefix . $table_name;
		}
	}

	/**
	 * Remove unnecessary things from the SQL
	 *
	 * @param string $sql The raw exported SQL file
	 * @return array
	 */
	private function purgeSQL( string $sql ) {
		$pattern = '/(CREATE TABLE .*?);/si';
		preg_match_all( $pattern, $sql, $matches );

		return $matches[0];
	}

	/**
	 * Apply dynamic collation, charset, prefix etc.
	 *
	 * @param array $queries Array of single queries.
	 * @return array
	 */
	private function applyDynamics( array $queries ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		return array_map(
			function ( $query ) use ( $wpdb, $charset_collate ) {

				// Replace table prefix
				$query = str_replace( 'wp_' . $this->configs->db_prefix, $wpdb->prefix . $this->configs->db_prefix, $query );

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
	 * @return array
	 */
	private function getInspected( array $queries ) {
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
				'query'      => $query,
				'table'      => $table_name,
				'table_only' => str_replace( 'wp_' . $this->configs->db_prefix, '', $table_name ),
				'columns'    => $columns,
			);
		}

		return $queries;
	}

	/**
	 * Import the DB from SQL file.
	 * ---------------------------
	 * Must have in the SQL
	 *
	 * 1. Table prefix: wp_solidie_
	 * 2. ENGINE=InnoDB
	 * 3. DEFAULT CHARSET=utf8mb4
	 * 4. COLLATE=utf8mb4_unicode_520_ci
	 * 5. Column CHARACTER SET utf8mb4
	 * 6. Column COLLATE utf8mb4_unicode_520_ci
	 * 7. CREATE TABLE IF NOT EXISTS
	 *
	 * So these can be replaced with dymanic configuration correctly. And no onflict with existing table with same names.
	 *
	 * @param string $sql Raw exported SQL file contents
	 * @return void
	 */
	public function import( $sql ) {
		$queries = $this->purgeSQL( $sql );
		$queries = $this->applyDynamics( $queries );
		$queries = $this->getInspected( $queries );

		// Load helper methods if not loaded already
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
				if ( ! in_array( $column, $current_columns ) ) {
					$wpdb->query( "ALTER TABLE {$query['table']} ADD {$column_definition}" );
				}
			}
		}
	}
}
