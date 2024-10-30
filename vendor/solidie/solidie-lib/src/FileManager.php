<?php
/**
 * File uploader functionalities
 *
 * @package solidie/solidie-lib
 */

namespace SolidieLib;

/**
 * File and directory handler class
 */
class FileManager {

	/**
	 * Delete WP files
	 *
	 * @param  int|array $file_id File ID or array of files IDs
	 * @return void
	 */
	public static function deleteFile( $file_id ) {
		if ( ! is_array( $file_id ) ) {
			$file_id = array( $file_id );
		}

		// Loop through file IDs and delete
		foreach ( $file_id as $id ) {
			if ( ! empty( $id ) && is_numeric( $id ) ) {
				wp_delete_attachment( $id, true );
			}
		}
	}

	/**
	 * Move directory to new location.
	 * Sensitive function, do not use if you do not know exactly what would happen.
	 *
	 * @param string $src From directory
	 * @param string $dst To directory
	 * @return void
	 */
	public static function moveDirectory( $src, $dst ) {
		$dir = opendir( $src );
		@mkdir( $dst );
		while ( false !== ( $file = readdir( $dir ) ) ) {
			if ( '.' !== $file && '..' !== $file ) {
				if ( is_dir( $src . '/' . $file ) ) {
					self::moveDirectory( $src . '/' . $file, $dst . '/' . $file );
					rmdir( $src . '/' . $file );
				} else {
					rename( $src . '/' . $file, $dst . '/' . $file );
				}
			}
		}
		closedir( $dir );
	}

	/**
	 * Get the directory name inside the zip file
	 *
	 * @param  string $zip_file_path The zip file path to get dir name from inside
	 * @return string|null
	 */
	public static function getOnlyFolderNameInZip( $zip_file_path ) {

		if ( ! file_exists( $zip_file_path ) ) {
			return null;
		}

		$dir = null;
		$zip = new \ZipArchive();

		if ( $zip->open( $zip_file_path ) === true ) {

			$stat     = $zip->statIndex( 0 );
			$filename = is_array( $stat ) ? ( $stat['name'] ?? '' ) : '';
			$dir_name = explode( '/', $filename );
			$dir      = $dir_name[0] ?? null;

			$zip->close();
		}

		return $dir;
	}

	/**
	 * Delete directory
	 *
	 * @param  string $folder Dir path to delete including files and sub folders
	 * @return bool
	 */
	public static function deleteDirectory( $folder ) {

		// Check if the folder exists
		if ( ! is_string( $folder ) || ! file_exists( $folder ) || ! is_dir( $folder ) ) {
			return false;
		}

		// Check if it's a directory
		if ( ! is_dir( $folder ) ) {
			return false;
		}

		// Open the directory
		$dir = opendir( $folder );

		// Loop through the contents of the directory
		while ( ( $file = readdir( $dir ) ) !== false ) {
			// Skip the special '.' and '..' folders
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			// Build the full path to the item
			$path = $folder . DIRECTORY_SEPARATOR . $file;

			// Recursively delete directories or just delete files
			if ( is_dir( $path ) ) {
				self::deleteDirectory( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		// Close the directory
		closedir( $dir );

		// Delete the folder itself
		return rmdir( $folder );
	}

	/**
	 * Organize uploaded files hierarchy
	 *
	 * @param array $file_s The file holder array to organize
	 * @return array
	 */
	public static function organizeUploadedHierarchy( array $file_s ) {
		$new_array = array();

		$columns = array( 'name', 'size', 'type', 'tmp_name', 'error' );

		// Loop through data types like name, tmp_name etc.
		foreach ( $columns as $column ) {

			if ( ! isset( $file_s[ $column ] ) ) {
				continue;
			}

			// Loop through data
			foreach ( $file_s[ $column ] as $post_name => $data_list ) {

				if ( ! isset( $new_array[ $post_name ] ) ) {
					$new_array[ $post_name ] = array();
				}

				if ( ! is_array( $data_list ) ) {
					$new_array[ $post_name ][ $column ] = $data_list;
					continue;
				}

				foreach ( $data_list as $index => $data ) {
					if ( ! isset( $new_array[ $post_name ][ $index ] ) ) {
						$new_array[ $post_name ][ $index ] = array();
					}

					$new_array[ $post_name ][ $index ][ $column ] = $data;
				}
			}
		}

		return $new_array;
	}

	/**
	 * List files in a directory
	 *
	 * @param string $directory The directory to list files in
	 * @return array
	 */
	public static function getFilesInDirectory( string $directory ) {

		$files = array();

		// Check if the directory exists
		if ( is_dir( $directory ) ) {
			$iterator = new \DirectoryIterator( $directory );

			foreach ( $iterator as $file_info ) {
				if ( $file_info->isFile() ) {
					$filename           = pathinfo( $file_info->getFilename(), PATHINFO_FILENAME );
					$files[ $filename ] = $file_info->getPathname();
				}
			}
		}

		return $files;
	}

}
