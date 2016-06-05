<?php
/*
Plugin Name: Solazu Exporter
Plugin URI: https://www.facebook.com/nguyencanh.khanhtrinh.7
Description: Solazu Exporter by Trinh.NCK
Version: 1.0
Author: Trinh.NCK
Author URI: https://www.facebook.com/nguyencanh.khanhtrinh.7
Text Domain: slz-exporter
*/

/**
 * Main class
 *
 * @since 0.1
 */
if( class_exists('Solazu_Exporter') === false ){

	class Solazu_Exporter {

		/**
		 * Includes to load
		 *
		 * @since 0.1
		 * @var array
		 */
		public $includes;

		/**
		 * Constructor
		 *
		 * Add actions for methods that define constants, load translation and load includes.
		 *
		 * @since 0.1
		 * @access public
		 */
		public function __construct() {

			// Load plugin script
			add_action( 'plugins_loaded', array( &$this, 'slz_load_style' ), 1 );

			// Load plugin stylesheet
			add_action( 'plugins_loaded', array( &$this, 'slz_load_script' ), 1 );

			// Set includes
			add_action( 'plugins_loaded', array( &$this, 'slz_set_includes' ), 1 );

			// Load includes
			add_action( 'plugins_loaded', array( &$this, 'slz_load_includes' ), 1 );

			// Load function
			add_action( 'plugins_loaded', array( &$this, 'slz_load_function' ), 1 );

		}

		/**
		 * Set includes
		 *
		 * @since 0.1
		 * @access public
		 */
		public function slz_set_includes() {

			$this->includes = apply_filters( 'slz_includes', array(

				// Admin only
				'admin' => array(

					// Functions
					// ABSPATH . 'wp-admin/includes/export.php',
					ABSPATH . 'wp-admin/includes/plugin.php',
					plugin_dir_path( __FILE__ ) . '/solazu-includes/solazu-page.php',
					plugin_dir_path( __FILE__ ) . '/solazu-includes/solazu-menu.php',
					plugin_dir_path( __FILE__ ) . '/solazu-includes/solazu-function.php',
					plugin_dir_path( __FILE__ ) . '/solazu-includes/solazu-export.php'

				)

			) );

		}

		/**
		 * Load plugin script
		 *
		 * @since 1.0
		 * @access public
		 */
		public function slz_load_script(){

			wp_enqueue_script('slz_main_script', plugins_url( 'solazu-scripts/solazu-main.js', __FILE__ ), array('jquery', 'jquery-ui-accordion'));

		}

		/**
		 * Load plugin stylesheet
		 *
		 * @since 1.0
		 * @access public
		 */
		public function slz_load_style(){

			wp_enqueue_style('slz_main_stylesheet', plugins_url( 'solazu-css/solazu-styles.css', __FILE__ ));
			
		}

		/**
		 * Auto load function
		 *
		 * @since 1.0
		 * @access public
		 */
		public function slz_load_function(){

			add_action( 'load-tools_page_slz_exporter', 'post_excute' );
			add_action( 'load-tools_page_slz_exporter', 'get_excute' );
			
		}

		/**
		 * Load includes
		 *
	 	 * Include files based on whether or not condition is met.
		 *
		 * @since 0.1
		 * @access public
		 */
		public function slz_load_includes() {

			// Get includes
			$includes = $this->includes;

			// Loop conditions
			foreach ( $includes as $condition => $files ) {

				$do_includes = false;

				// Check condition
				switch( $condition ) {

					// Admin Only
					case 'admin':

						if ( is_admin() ) {
							$do_includes = true;
						}

						break;

					// Frontend Only
					case 'frontend':

						if ( ! is_admin() ) {
							$do_includes = true;
						}

						break;

					// Admin or Frontend (always)
					default:

						$do_includes = true;

						break;

				}

				// Loop files if condition met
				if ( $do_includes ) {

					foreach ( $files as $file ) {
						require_once $file;
					}

				}

			}

		}

	}

}
// Instantiate the main class
new Solazu_Exporter();
