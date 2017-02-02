<?php
/**
 * Solazu index
 *
 * @package    Solazu_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2016, Swlabs
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      1.0
 */

defined( 'DEFAULT_CUSTOM_SIDEBAR_NAME' )      || define( 'DEFAULT_CUSTOM_SIDEBAR_NAME', 'swbignews_custom_sidebar' );
if( !defined( 'SWLABSCORE_CUSTOM_SIDEBAR_NAME' ) ) {
	defined( 'CUSTOM_SIDEBAR_NAME' )      || define( 'CUSTOM_SIDEBAR_NAME', DEFAULT_CUSTOM_SIDEBAR_NAME );
} else {
	defined( 'CUSTOM_SIDEBAR_NAME' )      || define( 'CUSTOM_SIDEBAR_NAME', SWLABSCORE_CUSTOM_SIDEBAR_NAME );
}

function slz_index(){
	global $reduxConfig;
	if(!empty($reduxConfig))
		$opt_name = $reduxConfig->args['opt_name'];
	$nav_menus = wp_get_nav_menus();
	$menu_count = count( $nav_menus );
	$menus = get_registered_nav_menus();
	$menu_locations = get_nav_menu_locations();
	$my_theme = wp_get_theme();

	if( empty( $opt_name ) ){
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cannot export redux framework theme option data. Please check your redux plugin!', 'slz_exporter' ) . '</p></div>';
		//slz_die ( 'Cannot load Redux Framework. Please check and try again !' );
	}

	if( !defined( 'SWLABSCORE_CUSTOM_SIDEBAR_NAME' ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cannot find constant SWLABSCORE_CUSTOM_SIDEBAR_NAME. The custom sidebar name will be set to "' . DEFAULT_CUSTOM_SIDEBAR_NAME . '" !', 'slz_exporter' ) . '</p></div>';
		//slz_die ( 'Undefined custom sidebar in this theme. Please check and try again !' );
	}
	
	?>
	<div class="slz_home">
		<h1><?php esc_html_e('Solazu Exporter', 'slz_exporter'); ?></h1>
		<div class="slz_description">
			<?php esc_html_e('Help you to export all data of wordpress site. The exported data will contain your posts, pages, comments, menu, widget, custom fields, categories, and tags.', 'slz_exporter'); ?>
		</div>
		<div class="slz_logo">
			<img src="<?php echo esc_url ( plugins_url( 'slz-images/slz-logo.png', dirname( __FILE__ ) ) ); ?>" />
		</div>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab nav-tab-active" href="#"><?php esc_html_e('Exporter', 'slz_exporter'); ?></a> 
			<a class="nav-tab nav-tab" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'slz_importer' ), 'tools.php' ) ) ); ?>"><?php esc_html_e('Importer', 'slz_exporter'); ?></a> 
		</h2>
		<div class="slz_content">
			<form action="" method="post">
				<div class="slz_intro">
					<p><?php esc_html_e('All fields have assigned default values. Don\'t edit if you are not sure.', 'slz_exporter'); ?></p>
				</div>
				<div id="accordion" class="slz_accordion">
					<h3><?php esc_html_e('General Settings', 'slz_exporter'); ?></h3>
					<div class="slz_accordion_content">
						<table class="widefat" cellspacing="0">
							<tbody>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Sample Data Name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php echo esc_attr( $my_theme->get( 'Name' ) ); ?>" name="slz[name]" class="regular-text" required="required" />
										<input type="hidden" value="exporter" name="action" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Sample Data Description:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="Sample data of <?php echo esc_attr( $my_theme->get( 'Name' ) ); ?> theme" name="slz[description]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Demo Url:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php echo esc_attr( $my_theme->get( 'ThemeURI' ) ); ?>" name="slz[demo_url]" class="regular-text" required="required" />
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<h3>Menu Settings</h3>
					<div class="slz_accordion_content">
						<table class="widefat" cellspacing="0">
							<tbody>
								<tr>
									<td colspan="2">
										<?php esc_html_e('Your theme supports ' . esc_html( count ( $menus ) ) . ' menus. Select which menu appears in each location to export.', 'slz_exporter'); ?>
									</td>
								</tr>
								<?php
								
								foreach ( $menus as $location => $description ) {
								?>
								<tr>
									<td class="slz_title">
										<?php echo esc_html( $description ); ?>
									</td>
									<td>
										<select name="slz[menu][<?php echo esc_attr( $location ); ?>]" id="locations-<?php echo esc_attr( $location ); ?>">
											<option value=""><?php printf( '&mdash; %s &mdash;', esc_html__( 'Select a Menu' , 'slz_exporter' ) ); ?></option>
											<?php foreach ( $nav_menus as $menu ) : ?>
												<?php $selected = isset( $menu_locations[$location] ) && $menu_locations[$location] == $menu->term_id; ?>
												<option <?php if ( $selected ) echo 'selected'; ?> value="<?php echo $menu->name; ?>">
													<?php echo wp_html_excerpt( $menu->name, 40, '&hellip;' ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php
								}
								?>
							</tbody>
						</table>
					</div>
					<h3>Advanced settings</h3>
					<div class="slz_accordion_content">
						<table class="widefat" cellspacing="0">
							<tbody>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Demo Tag:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php esc_html_e('Recommend', 'slz_exporter'); ?>" name="slz[tag]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Data Author:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php echo esc_attr( $my_theme->get( 'Author' ) ); ?>" name="slz[author]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Author URL:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php echo esc_attr( $my_theme->get( 'AuthorURI' ) ); ?>" name="slz[author_url]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Export Date:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php echo esc_attr( date( "d/m/Y" ) ); ?>" name="slz[backup_date]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Redux Opt Name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="<?php echo esc_attr( $opt_name ); ?>" name="slz[redux_opt_name]" class="regular-text" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Content file name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="content.xml" name="slz[wordpress_content_file]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Theme option file name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="theme-options.txt" name="slz[theme_option_file]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Widget file name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="widgets.json" name="slz[widget_backup_file]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Custom sidebar file name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="custom-sidebar.json" name="slz[custom_sidebar_file]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Custom category file name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="custom-category.json" name="slz[custom_category_file]" class="regular-text" required="required" />
									</td>
								</tr>
								<tr>
									<td class="slz_title">
										<?php esc_html_e('Screenshot file name:', 'slz_exporter'); ?>
									</td>
									<td>
										<input type="text" value="screen-image.png" name="slz[screen_image_file]" class="regular-text" required="required" />
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
				<?php submit_button( esc_html_x( 'Download Export File', 'button', 'slz_exporter' ) ); ?>
			</form>
		</div>
	</div>
 <?php
}

function slz_importer(){
	global $reduxConfig, $result;
	$opt_name = $reduxConfig->args['opt_name'];

	if( empty( $opt_name ) ){
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cannot import redux framework theme option data. Please check your redux plugin!', 'slz_exporter' ) . '</p></div>';
		//slz_die ( 'Cannot load Redux Framework. Please check and try again !' );
	}

	if( !defined( 'SWLABSCORE_CUSTOM_SIDEBAR_NAME' ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cannot find constant SWLABSCORE_CUSTOM_SIDEBAR_NAME. The custom sidebar name will be set to "' . DEFAULT_CUSTOM_SIDEBAR_NAME . '" !', 'slz_exporter' ) . '</p></div>';
		//slz_die ( 'Undefined custom sidebar in this theme. Please check and try again !' );
	}
	
	?>
	<div class="slz_home">
		<h1><?php esc_html_e('Solazu Exporter', 'slz_exporter'); ?></h1>
		<div class="slz_description">
			<?php esc_html_e('Help you to export all data of wordpress site. The exported data will contain your posts, pages, comments, menu, widget, custom fields, categories, and tags.', 'slz_exporter'); ?>
		</div>
		<div class="slz_logo">
			<img src="<?php echo esc_url ( plugins_url( 'slz-images/slz-logo.png', dirname( __FILE__ ) ) ); ?>" />
		</div>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab nav-tab" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'slz_exporter' ), 'tools.php' ) ) ); ?>"><?php esc_html_e('Exporter', 'slz_exporter'); ?></a> 
			<a class="nav-tab nav-tab-active" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'slz_importer' ), 'tools.php' ) ) ); ?>"><?php esc_html_e('Importer', 'slz_exporter'); ?></a> 
		</h2>
		<div class="slz_content">
			<form action="" method="post" enctype="multipart/form-data">
				<div class="slz_intro">
					<p><?php if(empty($result)) esc_html_e('Please select a .zip file generated by this plugin.', 'slz_exporter'); ?></p>
				</div>
				<div id="accordion" class="slz_accordion">
				<?php 

				if($result == 'success') {
					echo '<h3>' . esc_html__('Import Data Succesful', 'slz_exporter') . '</h3>';
				} else {
				?>
					<h3><?php esc_html_e('Import Data', 'slz_exporter'); ?></h3>
					<div class="slz_accordion_content">
						<table class="widefat" cellspacing="0">
							<tbody>
								<tr>
									<td>
										<?php wp_nonce_field( 'slz_import', 'slz_import_nonce' ); ?>
										<input type="file" name="slz_import_file" id="slz-import-file" />
										<input type="hidden" value="importer" name="action" />
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<?php } ?>
				</div>
				<?php if(empty($result)) submit_button( esc_html_x( 'Import Data', 'button', 'slz_exporter' ) ); ?>
			</form>
		</div>
	</div>
	<?php
}

?>