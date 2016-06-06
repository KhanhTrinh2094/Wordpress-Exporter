<?php
/**
 * Solazu Functions
 *
 * @package    Solazu_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2016, Nguyễn Cảnh Khánh Trình
 * @link       https://github.com/KhanhTrinh2094/solazu-exporter
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      1.0
 */

/**
 * Available widgets
 *
 * Gather site's widgets into array with ID base, name, etc.
 * Used by export and import functions.
 *
 * @since 1.0
 * @global array $wp_registered_widget_updates
 * @return array Widget information
 */
function slz_available_widgets() {

	global $wp_registered_widget_controls;

	$widget_controls = $wp_registered_widget_controls;

	$available_widgets = array();

	foreach ( $widget_controls as $widget ) {

		if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[$widget['id_base']] ) ) { // no dupes

			$available_widgets[$widget['id_base']]['id_base'] = $widget['id_base'];
			$available_widgets[$widget['id_base']]['name'] = $widget['name'];

		}

	}

	return apply_filters( 'slz_available_widgets', $available_widgets );

}


/**
 * Generate export widget data
 *
 * @since 1.0
 * @return string Export file contents
 */
function slz_generate_widget_data() {

	// Get all available widgets site supports
	$available_widgets = slz_available_widgets();

	// Get all widget instances for each widget
	$widget_instances = array();
	foreach ( $available_widgets as $widget_data ) {

		// Get all instances for this ID base
		$instances = get_option( 'widget_' . $widget_data['id_base'] );

		// Have instances
		if ( ! empty( $instances ) ) {

			// Loop instances
			foreach ( $instances as $instance_id => $instance_data ) {

				// Key is ID (not _multiwidget)
				if ( is_numeric( $instance_id ) ) {
					$unique_instance_id = $widget_data['id_base'] . '-' . $instance_id;
					$widget_instances[$unique_instance_id] = $instance_data;
				}

			}

		}

	}

	// Gather sidebars with their widget instances
	$sidebars_widgets = get_option( 'sidebars_widgets' ); // get sidebars and their unique widgets IDs
	$sidebars_widget_instances = array();
	foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {

		// Skip inactive widgets
		if ( 'wp_inactive_widgets' == $sidebar_id ) {
			continue;
		}

		// Skip if no data or not an array (array_version)
		if ( ! is_array( $widget_ids ) || empty( $widget_ids ) ) {
			continue;
		}

		// Loop widget IDs for this sidebar
		foreach ( $widget_ids as $widget_id ) {

			// Is there an instance for this widget ID?
			if ( isset( $widget_instances[$widget_id] ) ) {

				// Add to array
				$sidebars_widget_instances[$sidebar_id][$widget_id] = $widget_instances[$widget_id];

			}

		}

	}

	// Filter pre-encoded data
	$data = apply_filters( 'slz_unencoded_export_data', $sidebars_widget_instances );

	// Encode the data for file contents
	$encoded_data = json_encode( $data );

	// Return contents
	return apply_filters( 'slz_generate_export_data', $encoded_data );

}


/**
 * Save file to directory
 *
 * @since 1.0
 * @param string $file Path to save file
 * @param string $content content will be save to file
 * @global object $wp_filesystem
 */
function slz_save_file($file, $content){
	
	// global WP_Filesystem_Base Class
	global $wp_filesystem;

	// Initialize the WP filesystem, no more using 'file-put-contents' function
	if ( empty( $wp_filesystem ) ) {
		require_once (ABSPATH . '/wp-admin/includes/file.php');
		WP_Filesystem();
	}

	// write the $content to $file using wordpress function
	$wp_filesystem->put_contents(

		$file,
		$content,
		FS_CHMOD_FILE

	);

}

/**
 * Save file to directory
 *
 * @since 1.0
 * @param string $folder_name name of new folder
 * @global object $wp_filesystem
 */
function slz_make_dir($folder_name){

	// global WP_Filesystem_Base Class
	global $wp_filesystem;

	// Initialize the WP filesystem, no more using 'file-put-contents' function
	if ( empty( $wp_filesystem ) ) {
		require_once (ABSPATH . '/wp-admin/includes/file.php');
		WP_Filesystem();
	}

	$plugin_path = str_replace(ABSPATH, $wp_filesystem->abspath(), plugin_dir_path( __FILE__ ));

	// Now we can use $plugin_path in all our Filesystem API method calls
	if( !$wp_filesystem->is_dir( $plugin_path . '/' . $folder_name . '/' ) ) 
	{
		// directory didn't exist, so let's create it
		$wp_filesystem->mkdir($plugin_path . '/' . $folder_name . '/');

	}

}


/**
 * Copy file from theme directory to plugin directory
 *
 * @since 1.0
 * @param string $folder_name name of new folder
 * @global object $wp_filesystem
 */
function slz_copy_theme_file($target_name, $new_name){
	
	// global WP_Filesystem_Base Class
	global $wp_filesystem;

	// Initialize the WP filesystem, no more using 'file-put-contents' function
	if ( empty( $wp_filesystem ) ) {
		require_once (ABSPATH . '/wp-admin/includes/file.php');
		WP_Filesystem();
	}

	$plugin_dir = str_replace(ABSPATH, $wp_filesystem->abspath(), plugin_dir_path( __FILE__ )) . '/slz-temp/' . $new_name;
    $theme_dir = get_stylesheet_directory() . '/' . $target_name;

    if (!copy($theme_dir, $plugin_dir)) {
        return false;
    }

    return true;
}


/**
 * Remove folder and all file in this folder
 *
 * @since 1.0
 * @param string $dir name of new folder
 * @global object $wp_filesystem
 */
function slz_remove_folder( $dir ) { 

	$files = array_diff(scandir($dir), array('.','..')); 

	foreach ($files as $file) { 

		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 

	}

	return rmdir($dir); 
} 

/**
 * Process post method
 *
 * @since 1.0
 */
function post_excute(){

	if( !empty( $_POST ) ){
		$post_data = $_POST['slz'];

		// make temp directory
		slz_make_dir('slz-temp');

		// temp directory path
		$temp_dir = plugin_dir_path( __FILE__ ) . 'slz-temp/';

		// make file name in server
		$sitename = sanitize_key( get_bloginfo( 'name' ) );
		if ( ! empty( $sitename ) ) {
			$sitename .= '.';
		}
		$date = date( 'Y-m-d' );
		$zip_filename = $sitename . 'wordpress.' . $date . '.zip';

		// save wordpress content to xml file
		slz_save_file( $temp_dir . $post_data['wordpress_content_file'], slz_export_wp( array( "content" => "all" ) ) );
		// save widget data to json file
		slz_save_file( $temp_dir . $post_data['widget_backup_file'], slz_generate_widget_data() );
		// save redux theme option data to txt file
		slz_save_file( $temp_dir . $post_data['theme_option_file'], json_encode ( get_option('swbignews_options') ) );
		// copy screenshot file to temp folder
		slz_copy_theme_file( 'screenshot.png', $post_data['screen_image_file'] );
		// save exporter config data to config file
		slz_save_file( $temp_dir . 'config.json', json_encode ( $post_data ) );
		// save shinway custom sidebar to file
		slz_save_file( $temp_dir . $post_data['custom_sidebar_file'], json_encode ( get_option(SWBIGNEWS_THEME_CUSTOM_SIDEBAR_NAME) ) );


		// zip execute
		$zip = new ZipArchive;

		// create zip file
		$zip->open( $temp_dir . $zip_filename, ZipArchive::CREATE );
		// add wordpress xml content file to zip
		$zip->addFile( $temp_dir . $post_data['wordpress_content_file'], $post_data['wordpress_content_file'] );
		// add widget json file to zip
		$zip->addFile( $temp_dir . $post_data['widget_backup_file'], $post_data['widget_backup_file'] );
		// add theme option file to zip
		$zip->addFile( $temp_dir . $post_data['theme_option_file'], $post_data['theme_option_file'] );
		// add screenshot file to zip
		$zip->addFile( $temp_dir . $post_data['screen_image_file'], $post_data['screen_image_file'] );
		// add custom sidebar file to zip
		$zip->addFile( $temp_dir . $post_data['custom_sidebar_file'], $post_data['custom_sidebar_file'] );
		// add exporter config file to zip
		$zip->addFile( $temp_dir . 'config.json', 'config.json' );

		// zip excute and close
		$zip->close();

		// Headers to prompt "Save As"
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . $zip_filename );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $temp_dir . $zip_filename ) );

		// Clear buffering just in case
		@ob_end_clean();
		flush();

		// Output file contents
		readfile( $temp_dir . $zip_filename );

		slz_remove_folder( $temp_dir );
		// Stop execution
		exit;
	}
}

/**
 * Process get method
 *
 * @since 1.0
 */
function get_excute(){
	
}


?>