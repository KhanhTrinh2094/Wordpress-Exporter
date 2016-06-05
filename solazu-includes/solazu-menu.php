<?php
/**
 * Solazu Menu Management
 *
 * @package    Solazu_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2016, Nguyễn Cảnh Khánh Trình
 * @link       https://github.com/KhanhTrinh2094/solazu-exporter
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      1.0
 */

/**
 * Add import/export page under Tools
 *
 * Also enqueue Stylesheet for this page only.
 *
 * @since 0.1
 */
function slz_add_menus() {

	add_management_page(
		esc_html__( 'Solazu Exporter', 'slz_exporter' ), // page title
		esc_html__( 'Solazu Exporter', 'slz_exporter' ), // menu title
		'manage_options', // capability
		'slz_exporter', // menu slug
		'slz_index' // callback for displaying page content
	);
	
}

add_action('admin_menu','slz_add_menus');
?>