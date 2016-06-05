<?php

function slz_index(){
	global $reduxConfig;
	$opt_name = $reduxConfig->args['opt_name'];
	$nav_menus = wp_get_nav_menus();
	$menu_count = count( $nav_menus );
	$menus = get_registered_nav_menus();
	$menu_locations = get_nav_menu_locations();
	$my_theme = wp_get_theme();
	?>
	<div class="slz_home">
		<h1><?php esc_html_e('Solazu Exporter', 'slz_exporter'); ?></h1>
		<div class="slz_description">
			Our core mantra at Redux is backwards compatibility. With hundreds of thousands of instances worldwide, you can be assured that we will take care of you and your clients.
		</div>
		<div class="slz_logo">
			<img src="<?php echo esc_url ( plugins_url( 'solazu-images/solazu-logo.png', dirname( __FILE__ ) ) ); ?>" />
		</div>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab nav-tab-active" href="#"><?php esc_html_e('Dashboard', 'slz_exporter'); ?></a> 
			<a class="nav-tab" href="https://www.google.com/search?sourceid=chrome-psyapi2&amp;ion=1&amp;espv=2&amp;ie=UTF-8&amp;q=google.%20please%20support%20me&amp;oq=google.%20please%20support%20me&amp;aqs=chrome..69i57j69i64.5570j0j7" target="_blank"><?php esc_html_e('Support', 'slz_exporter'); ?></a>
			<a class="nav-tab" href="https://github.com/KhanhTrinh2094/solazu-exporter" target="_blank"><?php esc_html_e('Github', 'slz_exporter'); ?></a>
		</h2>
		<div class="slz_content">
			<form action="" method="post">
				<div class="slz_intro">
					<p>Control user group role access to the features and options of Visual Composer - manage WordPress default and custom roles.</p>
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
										<?php esc_html_e('Your theme supports ' . esc_html( count ( $menus ) ) . ' menus. Select which menu appears in each location.', 'slz_exporter'); ?>
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
										<select name="slz[menu][<?php echo esc_attr( $location ); ?>]" id="locations-<?php echo esc_attr( $location ); ?>" required="required" >
											<option value=""><?php printf( '&mdash; %s &mdash;', esc_html__( 'Select a Menu' , 'slz_exporter' ) ); ?></option>
											<?php foreach ( $nav_menus as $menu ) : ?>
												<?php $selected = isset( $menu_locations[$location] ) && $menu_locations[$location] == $menu->term_id; ?>
												<option <?php if ( $selected ) echo 'selected'; ?> value="<?php echo $menu->term_id; ?>">
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
										<input type="text" value="<?php echo esc_attr( $opt_name ); ?>" name="slz[redux_opt_name]" class="regular-text" required="required" />
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


function get_excute(){
	
}

?>