<?php
/**
 * Solazu Import Functions
 *
 * @package    Solazu_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2016, Swlabs
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      1.0
 */

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

// include WXR file parsers
require dirname( __FILE__ ) . '/slz-parse-xml.php';

class Slz_Demo_Importer extends WP_Importer {
	var $max_wxr_version = 1.2; // max. supported WXR version

	var $id; // WXR attachment ID

	// information to import from WXR file
	var $version;
	var $authors = array();
	var $posts = array();
	var $terms = array();
	var $categories = array();
	var $tags = array();
	var $base_url = '';

	// mappings from old information to new
	var $processed_authors = array();
	var $author_mapping = array();
	var $processed_terms = array();
	var $processed_posts = array();
	var $post_orphans = array();
	var $processed_menu_items = array();
	var $menu_item_orphans = array();
	var $missing_menu_items = array();

	var $fetch_attachments = false;
	var $url_remap = array();
	var $featured_images = array();

	//used for progress calculation
    var $total_progress_steps = 0;
    var $current_progress_step = 0;
    var $widget_import_results = false;

    // file use to import
    public $theme_options_file;
    public $widgets_file;
    public $demo_file;
    public $theme_option_name;
    public $demo_menu;
    public $custom_sidebar_file;
    public $custom_sidebar_name;
    public $custom_category_file;

	function Slz_Demo_Importer() { /* nothing */ }

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the WXR file for importing
	 */
	function import() {
		add_filter( 'import_post_meta_key', array( $this, 'is_valid_meta_key' ) );
		add_filter( 'http_request_timeout', array( &$this, 'bump_request_timeout' ) );

		$this->verify();
		$this->log( 'Verify successful. Start transaction', '' );

		$this->import_start( );
		wp_suspend_cache_invalidation( true );
		$this->process_categories();

		if( !empty( $this->custom_category_file ) ) {
			$this->process_custom_categories();
			$this->counting();
			$this->log( 'Update custom category successful', '' );
		}
		
		$this->process_tags();
		$this->process_terms();
		$this->process_posts();
		wp_suspend_cache_invalidation( false );

		// update incorrect/missing information in the DB
		$this->backfill_parents();
		$this->backfill_attachment_urls();
		$this->remap_featured_images();

		$this->import_end();
		if( !empty( $this->theme_option_name ) && !empty( $this->theme_options_file ) ) {
			$this->set_demo_theme_options( );
			$this->counting();
			$this->log( 'Update theme option successful', '' );
		}

		if( !empty( $this->custom_sidebar_name ) && !empty( $this->custom_sidebar_file ) ) {
			$this->process_custom_sidebar();
			$this->counting();
			$this->log( 'Update custom sidebar successful', '' );
		}


		$this->process_widget_import_file( );
		$this->counting();
		$this->log( 'Update widget successful', '' );

		if( !empty ( $this->demo_menu ) ) {
			$this->set_demo_menus();
			$this->counting();
			$this->log( 'Set demo menu successful', '' );
		}
		
	}

	/**
	 * Parses the WXR file and prepares us for the task of processing parsed data
	 *
	 * @param string $file Path to the WXR file for importing
	 */
	function import_start( ) {

		$import_data = $this->parse( $this->demo_file );

		if ( is_wp_error( $import_data ) ) {
			$this->error_log( 'Error : Sorry, there has been an error : ' . esc_html( $import_data->get_error_message() ), '' );
			die();
		}

		$this->version = $import_data['version'];
		$this->posts = $import_data['posts'];
		$this->terms = $import_data['terms'];
		$this->categories = $import_data['categories'];
		$this->tags = $import_data['tags'];
		$this->base_url = esc_url( $import_data['base_url'] );

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		do_action( 'import_start' );
	}

	/**
	 * Performs post-import cleanup of files and the cache
	 */
	function import_end() {
		wp_import_cleanup( $this->id );

		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		do_action( 'import_end' );
	}

	/**
	 * Verify input and valid file type
	 */
	function verify(){
		if ( empty( $this->demo_file ) || ! is_file( $this->demo_file ) ) {
			$this->error_log( 'Error : The demo file does not exist, please check and try again.', '' );
			die();
		}

		if( pathinfo( $this->demo_file, PATHINFO_EXTENSION ) != 'xml' ){
			$this->error_log( 'Error : The file type you are attempting to access is not supported.', '' );
			die();
		}

		if ( empty( $this->widgets_file ) || !is_file( $this->widgets_file ) ) {
			$this->error_log( 'Error : The widget file does not exist, please check and try again.', '' );
			die();
		}

		if( pathinfo( $this->widgets_file, PATHINFO_EXTENSION ) != 'json' ){
			$this->error_log( 'Error : The file type you are attempting to access is not supported.', '' );
			die();
		}

	}

	/**
	 * Create new categories based on import information
	 *
	 * Doesn't create a new category if its slug already exists
	 */
	function process_categories() {
		$this->categories = apply_filters( 'wp_import_categories', $this->categories );

		if ( empty( $this->categories ) )
			return;

		foreach ( $this->categories as $cat ) {
			$this->counting();
			// if the category already exists leave it alone
			$term_id = term_exists( $cat['category_nicename'], 'category' );
			if ( $term_id ) {
				if ( is_array($term_id) ) $term_id = $term_id['term_id'];
				if ( isset($cat['term_id']) )
					$this->processed_terms[intval($cat['term_id'])] = (int) $term_id;
				$this->log( 'Already exists Category : ' . esc_attr ( $cat['cat_name'] ), '' );
				continue;
			}

			$category_parent = empty( $cat['category_parent'] ) ? 0 : category_exists( $cat['category_parent'] );
			$category_description = isset( $cat['category_description'] ) ? $cat['category_description'] : '';
			$catarr = array(
				'category_nicename' => $cat['category_nicename'],
				'category_parent' => $category_parent,
				'cat_name' => $cat['cat_name'],
				'category_description' => $category_description
			);

			$id = wp_insert_category( $catarr );
			if ( ! is_wp_error( $id ) ) {
				if ( isset($cat['term_id']) )
					$this->processed_terms[intval($cat['term_id'])] = $id;
				$this->log( 'Add Category : ' . esc_attr ( $cat['cat_name'] ), '' );
			}
		}

		unset( $this->categories );
	}

	/**
	 * Create new post tags based on import information
	 *
	 * Doesn't create a tag if its slug already exists
	 */
	function process_tags() {
		$this->tags = apply_filters( 'wp_import_tags', $this->tags );

		if ( empty( $this->tags ) )
			return;

		foreach ( $this->tags as $tag ) {
			$this->counting();
			// if the tag already exists leave it alone
			$term_id = term_exists( $tag['tag_slug'], 'post_tag' );
			if ( $term_id ) {
				if ( is_array($term_id) ) $term_id = $term_id['term_id'];
				if ( isset($tag['term_id']) )
					$this->processed_terms[intval($tag['term_id'])] = (int) $term_id;
				$this->log( 'Already exists Tag : ' . esc_attr ( $tag['tag_name'] ), '' );
				continue;
			}

			$tag_desc = isset( $tag['tag_description'] ) ? $tag['tag_description'] : '';
			$tagarr = array( 'slug' => $tag['tag_slug'], 'description' => $tag_desc );

			$id = wp_insert_term( $tag['tag_name'], 'post_tag', $tagarr );
			if ( ! is_wp_error( $id ) ) {
				if ( isset($tag['term_id']) )
					$this->processed_terms[intval($tag['term_id'])] = $id['term_id'];
				$this->log( 'Add Tags : ' . esc_attr ( $tag['tag_name'] ), '' );
			}
		}

		unset( $this->tags );
	}

	/**
	 * Create new terms based on import information
	 *
	 * Doesn't create a term its slug already exists
	 */
	function process_terms() {
		$this->terms = apply_filters( 'wp_import_terms', $this->terms );

		if ( empty( $this->terms ) )
			return;

		foreach ( $this->terms as $term ) {
			$this->counting();
			// if the term already exists in the correct taxonomy leave it alone
			$term_id = term_exists( $term['slug'], $term['term_taxonomy'] );
			if ( $term_id ) {
				if ( is_array($term_id) ) $term_id = $term_id['term_id'];
				if ( isset($term['term_id']) )
					$this->processed_terms[intval($term['term_id'])] = (int) $term_id;
				$this->log( 'Already exists Term : ' . esc_attr ( $term['term_name'] ), '' );
				continue;
			}

			if ( empty( $term['term_parent'] ) ) {
				$parent = 0;
			} else {
				$parent = term_exists( $term['term_parent'], $term['term_taxonomy'] );
				if ( is_array( $parent ) ) $parent = $parent['term_id'];
			}
			$description = isset( $term['term_description'] ) ? $term['term_description'] : '';
			$termarr = array( 'slug' => $term['slug'], 'description' => $description, 'parent' => intval($parent) );

			$id = wp_insert_term( $term['term_name'], $term['term_taxonomy'], $termarr );
			if ( ! is_wp_error( $id ) ) {
				if ( isset($term['term_id']) )
					$this->processed_terms[intval($term['term_id'])] = $id['term_id'];
				$this->log( 'Add Terms : ' . esc_attr ( $term['term_name'] ), '' );
			}
		}

		unset( $this->terms );
	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 */
	function process_posts() {
		$this->posts = apply_filters( 'wp_import_posts', $this->posts );

		foreach ( $this->posts as $post ) {
			$this->counting();
			$post = apply_filters( 'wp_import_post_data_raw', $post );

			if ( ! post_type_exists( $post['post_type'] ) ) {
				$this->log( 'Failed to import : ' . esc_html($post['post_title']) . ' (Invalid post type ' . esc_html($post['post_type']) . ')', '' );
				do_action( 'wp_import_post_exists', $post );
				continue;
			}

			if ( isset( $this->processed_posts[$post['post_id']] ) && ! empty( $post['post_id'] ) ){
				continue;
			}

			if ( $post['status'] == 'auto-draft' ){
				continue;
			}

			if ( 'nav_menu_item' == $post['post_type'] ) {
				$this->process_menu_item( $post );
				continue;
			}

			$post_type_object = get_post_type_object( $post['post_type'] );

			$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
			if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
				$this->log( 'Already exists ' . esc_html( $post['post_type'] ) . ' : ' . esc_attr ( $post['post_title'] ), '' );
				$comment_post_ID = $post_id = $post_exists;
			} else {
				$post_parent = (int) $post['post_parent'];
				if ( $post_parent ) {
					// if we already know the parent, map it to the new local ID
					if ( isset( $this->processed_posts[$post_parent] ) ) {
						$post_parent = $this->processed_posts[$post_parent];
					// otherwise record the parent for later
					} else {
						$this->post_orphans[intval($post['post_id'])] = $post_parent;
						$post_parent = 0;
					}
				}

				$author = (int) get_current_user_id();

				$postdata = array(
					'import_id' => $post['post_id'], 'post_author' => $author, 'post_date' => $post['post_date'],
					'post_date_gmt' => $post['post_date_gmt'], 'post_content' => $post['post_content'],
					'post_excerpt' => $post['post_excerpt'], 'post_title' => $post['post_title'],
					'post_status' => $post['status'], 'post_name' => $post['post_name'],
					'comment_status' => $post['comment_status'], 'ping_status' => $post['ping_status'],
					'guid' => $post['guid'], 'post_parent' => $post_parent, 'menu_order' => $post['menu_order'],
					'post_type' => $post['post_type'], 'post_password' => $post['post_password']
				);

				$original_post_ID = $post['post_id'];
				$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $post );

				if ( 'attachment' == $postdata['post_type'] ) {
					$remote_url = ! empty($post['attachment_url']) ? $post['attachment_url'] : $post['guid'];

					// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
					// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
					$postdata['upload_date'] = $post['post_date'];
					if ( isset( $post['postmeta'] ) ) {
						foreach( $post['postmeta'] as $meta ) {
							if ( $meta['key'] == '_wp_attached_file' ) {
								if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta['value'], $matches ) )
									$postdata['upload_date'] = $matches[0];
								break;
							}
						}
					}

					$comment_post_ID = $post_id = $this->process_attachment( $postdata, $remote_url );
				} else {
					$comment_post_ID = $post_id = wp_insert_post( $postdata, true );
					do_action( 'wp_import_insert_post', $post_id, $original_post_ID, $postdata, $post );
					$this->log( 'Add ' . esc_html( $post['post_type'] ) . ' : ' . esc_html( $post['post_title'] ), '' );
				}

				if ( is_wp_error( $post_id ) ) {
					$this->log( 'Failed to import ' . $post_type_object->labels->singular_name . ' : ' . esc_html($post['post_title']), '' );
					continue;
				}

				if ( $post['is_sticky'] == 1 )
					stick_post( $post_id );
			}

			// map pre-import ID to local ID
			$this->processed_posts[intval($post['post_id'])] = (int) $post_id;

			if ( ! isset( $post['terms'] ) )
				$post['terms'] = array();

			$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

			// add categories, tags and other terms
			if ( ! empty( $post['terms'] ) ) {
				$terms_to_set = array();
				foreach ( $post['terms'] as $term ) {
					// back compat with WXR 1.0 map 'tag' to 'post_tag'
					$taxonomy = ( 'tag' == $term['domain'] ) ? 'post_tag' : $term['domain'];
					$term_exists = term_exists( $term['slug'], $taxonomy );
					$term_id = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
					if ( ! $term_id ) {
						$t = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
						if ( ! is_wp_error( $t ) ) {
							$term_id = $t['term_id'];
							do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );
							$this->log( 'Add Terms : ' . esc_html($taxonomy). ' ' . esc_attr ( $term['name'] ), '' );
						} else {
							$this->log( 'Failed to import ' . esc_html($taxonomy) . ' : ' . esc_html($term['name']), '' );
							do_action( 'wp_import_insert_term_failed', $t, $term, $post_id, $post );
							continue;
						}
					}
					$terms_to_set[$taxonomy][] = intval( $term_id );
				}

				foreach ( $terms_to_set as $tax => $ids ) {
					$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
					do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
				}
				unset( $post['terms'], $terms_to_set );
			}

			if ( ! isset( $post['comments'] ) )
				$post['comments'] = array();

			$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

			// add/update comments
			if ( ! empty( $post['comments'] ) ) {
				$num_comments = 0;
				$inserted_comments = array();
				foreach ( $post['comments'] as $comment ) {
					$comment_id	= $comment['comment_id'];
					$newcomments[$comment_id]['comment_post_ID']      = $comment_post_ID;
					$newcomments[$comment_id]['comment_author']       = $comment['comment_author'];
					$newcomments[$comment_id]['comment_author_email'] = $comment['comment_author_email'];
					$newcomments[$comment_id]['comment_author_IP']    = $comment['comment_author_IP'];
					$newcomments[$comment_id]['comment_author_url']   = $comment['comment_author_url'];
					$newcomments[$comment_id]['comment_date']         = $comment['comment_date'];
					$newcomments[$comment_id]['comment_date_gmt']     = $comment['comment_date_gmt'];
					$newcomments[$comment_id]['comment_content']      = $comment['comment_content'];
					$newcomments[$comment_id]['comment_approved']     = $comment['comment_approved'];
					$newcomments[$comment_id]['comment_type']         = $comment['comment_type'];
					$newcomments[$comment_id]['comment_parent'] 	  = $comment['comment_parent'];
					$newcomments[$comment_id]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
					if ( isset( $this->processed_authors[$comment['comment_user_id']] ) )
						$newcomments[$comment_id]['user_id'] = $this->processed_authors[$comment['comment_user_id']];
				}
				ksort( $newcomments );

				foreach ( $newcomments as $key => $comment ) {
					// if this is a new post we can skip the comment_exists() check
					if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
						if ( isset( $inserted_comments[$comment['comment_parent']] ) )
							$comment['comment_parent'] = $inserted_comments[$comment['comment_parent']];
						$comment = wp_filter_comment( $comment );
						$inserted_comments[$key] = wp_insert_comment( $comment );
						do_action( 'wp_import_insert_comment', $inserted_comments[$key], $comment, $comment_post_ID, $post );

						foreach( $comment['commentmeta'] as $meta ) {
							$value = maybe_unserialize( $meta['value'] );
							add_comment_meta( $inserted_comments[$key], $meta['key'], $value );
						}

						$num_comments++;
					}
				}
				unset( $newcomments, $inserted_comments, $post['comments'] );
			}

			if ( ! isset( $post['postmeta'] ) )
				$post['postmeta'] = array();

			$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

			// add/update post meta
			if ( ! empty( $post['postmeta'] ) ) {
				foreach ( $post['postmeta'] as $meta ) {
					$key = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
					$value = false;

					if ( '_edit_last' == $key ) {
						if ( isset( $this->processed_authors[intval($meta['value'])] ) )
							$value = $this->processed_authors[intval($meta['value'])];
						else
							$key = false;
					}

					if ( $key ) {
						// export gets meta straight from the DB so could have a serialized string
						if ( ! $value )
							$value = maybe_unserialize( $meta['value'] );
						add_post_meta( $post_id, $key, $value );
						do_action( 'import_post_meta', $post_id, $key, $value );

						// if the post has a featured image, take note of this in case of remap
						if ( '_thumbnail_id' == $key )
							$this->featured_images[$post_id] = (int) $value;
					}
				}
			}
		}

		unset( $this->posts );
	}

	/**
	 * Attempt to create a new menu item from import data
	 *
	 * Fails for draft, orphaned menu items and those without an associated nav_menu
	 * or an invalid nav_menu term. If the post type or term object which the menu item
	 * represents doesn't exist then the menu item will not be imported (waits until the
	 * end of the import to retry again before discarding).
	 *
	 * @param array $item Menu item details from WXR file
	 */
	function process_menu_item( $item ) {
		// skip draft, orphaned menu items
		if ( 'draft' == $item['status'] )
			return;

		$menu_slug = false;
		if ( isset($item['terms']) ) {
			// loop through terms, assume first nav_menu term is correct menu
			foreach ( $item['terms'] as $term ) {
				if ( 'nav_menu' == $term['domain'] ) {
					$menu_slug = $term['slug'];
					break;
				}
			}
		}

		// no nav_menu term associated with this menu item
		if ( ! $menu_slug ) {
			$this->log( 'Menu item skipped due to missing menu slug', '' );
			return;
		}

		$menu_id = term_exists( $menu_slug, 'nav_menu' );
		if ( ! $menu_id ) {
			$this->log( 'Menu item skipped due to invalid menu slug: ' . esc_html( $menu_slug ), '' );
			return;
		} else {
			$menu_id = is_array( $menu_id ) ? $menu_id['term_id'] : $menu_id;
		}

		foreach ( $item['postmeta'] as $meta )
			$$meta['key'] = $meta['value'];

		if ( 'taxonomy' == $_menu_item_type && isset( $this->processed_terms[intval($_menu_item_object_id)] ) ) {
			$_menu_item_object_id = $this->processed_terms[intval($_menu_item_object_id)];
		} else if ( 'post_type' == $_menu_item_type && isset( $this->processed_posts[intval($_menu_item_object_id)] ) ) {
			$_menu_item_object_id = $this->processed_posts[intval($_menu_item_object_id)];
		} else if ( 'custom' != $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			$this->missing_menu_items[] = $item;
			return;
		}

		if ( isset( $this->processed_menu_items[intval($_menu_item_menu_item_parent)] ) ) {
			$_menu_item_menu_item_parent = $this->processed_menu_items[intval($_menu_item_menu_item_parent)];
		} else if ( $_menu_item_menu_item_parent ) {
			$this->menu_item_orphans[intval($item['post_id'])] = (int) $_menu_item_menu_item_parent;
			$_menu_item_menu_item_parent = 0;
		}

		// wp_update_nav_menu_item expects CSS classes as a space separated string
		$_menu_item_classes = maybe_unserialize( $_menu_item_classes );
		if ( is_array( $_menu_item_classes ) )
			$_menu_item_classes = implode( ' ', $_menu_item_classes );

		$args = array(
			'menu-item-object-id' => $_menu_item_object_id,
			'menu-item-object' => $_menu_item_object,
			'menu-item-parent-id' => $_menu_item_menu_item_parent,
			'menu-item-position' => intval( $item['menu_order'] ),
			'menu-item-type' => $_menu_item_type,
			'menu-item-title' => $item['post_title'],
			'menu-item-url' => $_menu_item_url,
			'menu-item-description' => $item['post_content'],
			'menu-item-attr-title' => $item['post_excerpt'],
			'menu-item-target' => $_menu_item_target,
			'menu-item-classes' => $_menu_item_classes,
			'menu-item-xfn' => $_menu_item_xfn,
			'menu-item-status' => $item['status']
		);

		$id = wp_update_nav_menu_item( $menu_id, 0, $args );
		foreach ($item['postmeta'] as $custom_value) {
			if(strpos($custom_value['key'], '_menu-item-shw') !== false){
				update_post_meta( $id, $custom_value['key'], $custom_value['value'] );
			}
		}
		
		if ( $id && ! is_wp_error( $id ) )
			$this->processed_menu_items[intval($item['post_id'])] = (int) $id;
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	function process_attachment( $post, $url ) {
		if ( ! $this->fetch_attachments ){
			$this->log( 'Fetching attachments is not enabled', '' );
			return;
		}
		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) )
			$url = rtrim( $this->base_url, '/' ) . $url;

		$upload = $this->fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) )
			return $upload;

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else{
			$this->log( 'Invalid file type', '' );
			return;
		}

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
		if ( preg_match( '!^image/!', $info['type'] ) ) {
			$parts = pathinfo( $url );
			$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $upload['url'] );
			$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

			$this->url_remap[$parts['dirname'] . '/' . $name] = $parts_new['dirname'] . '/' . $name_new;
		}

		return $post_id;
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url URL of item to fetch
	 * @param array $post Attachment details
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	function fetch_remote_file( $url, $post ) {
		// extract the file name and extension from the url
		$file_name = basename( $url );

		// get placeholder file in the upload dir with a unique, sanitized filename
		$upload = wp_upload_bits( $file_name, 0, '', $post['upload_date'] );
		if ( $upload['error'] ){
			$this->log( 'Upload dir not found : ' . $upload['error'], '' );
			return;
		}

		// fetch the remote url and write it to the placeholder file
		$response = wp_remote_get( $url, array(
			'stream' => true,
			'filename' => $upload['file']
		) );

		// request failed
		if ( is_wp_error( $response ) ) {
			@unlink( $upload['file'] );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// make sure the fetch was successful
		if ( $code !== 200 ) {
			@unlink( $upload['file'] );
			$this->log( 'Import attachment error : Remote server returned ' . $code . ' ' . get_status_header_desc( $code ) . ' for ' . $url, '' );
			return;
		}

		$filesize = filesize( $upload['file'] );
		$headers = wp_remote_retrieve_headers( $response );

		if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
			@unlink( $upload['file'] );
			$this->log( 'Import attachment error : Remote file is incorrect size', '' );
			return;
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			$this->log( 'Import attachment error : Zero size file downloaded', '' );
			return;
		}

		$max_size = (int) $this->max_attachment_size();
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			@unlink( $upload['file'] );
			$this->log( 'Import attachment error : Remote file is too large, limit is ' . size_format($max_size), '' );
			return;
		}

		// keep track of the old and new urls so we can substitute them later
		$this->url_remap[$url] = $upload['url'];
		$this->url_remap[$post['guid']] = $upload['url']; // r13735, really needed?
		// keep track of the destination if the remote url is redirected somewhere else
		if ( isset($headers['x-final-location']) && $headers['x-final-location'] != $url )
			$this->url_remap[$headers['x-final-location']] = $upload['url'];

		return $upload;
	}

	public function set_demo_theme_options( ) {

		// Get file contents and decode
		$data = file_get_contents( $this->theme_options_file );
		$data = json_decode( $data, true );
		$data = maybe_unserialize( $data );

		// Only if there is data
		if ( !empty( $data ) || is_array( $data ) ) {
			// Hook before import
			update_option( $this->theme_option_name, $data );
		}

	}

	/**
	* Process import file
	*
	* This parses a file and triggers importation of its widgets.
	*
	* @since 2.2.0
	*
	* @param string  $file Path to .wie file uploaded
	* @global string $widget_import_results
	*/
    function process_widget_import_file( ) {

		// Get file contents and decode
		$data = file_get_contents( $this->widgets_file );

		$data = json_decode( $data );
		// Import the widget data
		// Make results available for display on import/export page
		$this->widget_import_results = $this->import_widgets( $data );
    }

	/**
	* Process custom category
	*
	* This parses a file and triggers importation of its widgets.
	*
	* @since 2.2.0
	*
	*/
    function process_custom_categories( ) {

		// Get file contents and decode
		$data = file_get_contents( $this->custom_category_file );

		if(!empty($data)) {
			$data = json_decode( $data );

			foreach ($data as $key => $value) {
				$shw_options = get_option( $key );
				if( empty( $shw_options ) ) {
					add_option( $key, json_decode(json_encode($value), true) );
				} else {
					update_option( $key, json_decode(json_encode($value), true) );
				}
			}
		}

		// Import the widget data
		// Make results available for display on import/export page
		$this->widget_import_results = $this->import_widgets( $data );
    }

    /**
	* Process custom sidebar
	*
	* This parses a file and triggers importation of its widgets.
	*
	* @since 2.2.0
	*
	*/
	function process_custom_sidebar( ) {
		if( empty ( $this->custom_sidebar_file ) ) return;
		$sidebar_data = file_get_contents( $this->custom_sidebar_file );

		if(!empty($sidebar_data)){
			$sidebar_data = json_decode( $sidebar_data, true );
			$sidebar_data = maybe_unserialize( $sidebar_data );

			// Only if there is data
			if ( !empty( $sidebar_data ) || is_array( $sidebar_data ) ) {
				$shw_options = get_option( $this->custom_sidebar_name );
				if( empty( $shw_options ) ) {
					add_option( $this->custom_sidebar_name, $sidebar_data );
				} else {
					update_option( $this->custom_sidebar_name, $sidebar_data );
				}
			}

			$sidebars_option = get_option( $this->custom_sidebar_name );
			$args =  array (
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => ''
			);
			if( is_array($sidebars_option) ) {
				foreach ( $sidebars_option as $sidebar_value ) {
					if( !empty($sidebar_value) ) {
						$name = isset($sidebar_value['name']) ? $sidebar_value['name'] : '';
						$title = isset($sidebar_value['title']) ? $sidebar_value['title'] : '';
						$class = isset($sidebar_value['class']) ? $sidebar_value['class'] : '';
						$args['name']   = $title;
						$args['id']     = str_replace(' ','-',strtolower( $name ));
						$args['class']  = 'shw-custom';
						$args['before_widget'] = '<div class="box %2$s shw-widget '. esc_attr($class) .'">';
						$args['after_widget']  = '</div>';
						$args['before_title']  = '<div class="section-name">';
						$args['after_title']   = '</div>';
						register_sidebar($args);
					}
				}
			}
		}
	}

	/**
	* Import widget JSON data
	*
	* @since 2.2.0
	* @global array $wp_registered_sidebars
	* @param object  $data JSON widget data from .wie file
	* @return array Results array
	*/
	public function import_widgets( $data ) {

		global $wp_registered_sidebars;

		// Have valid data?
		// If no data or could not decode
		if ( empty( $data ) || ! is_object( $data ) ) {
			return;
		}

		// Get all available widgets site supports
		$available_widgets = $this->available_widgets();

		// Get all existing widget instances
		$widget_instances = array();
		foreach ( $available_widgets as $widget_data ) {
			$widget_instances[$widget_data['id_base']] = get_option( 'widget_' . $widget_data['id_base'] );
		}

		// Begin results
		$results = array();

		// Loop import data's sidebars
		foreach ( $data as $sidebar_id => $widgets ) {
			// Skip inactive widgets
			// (should not be in export file)
			if ( 'wp_inactive_widgets' == $sidebar_id ) {
				continue;
			}

			// Check if sidebar is available on this site
			// Otherwise add widgets to inactive, and say so
			if ( isset( $wp_registered_sidebars[$sidebar_id] ) ) {
				$sidebar_available = true;
				$use_sidebar_id = $sidebar_id;
				$sidebar_message_type = 'success';
				$sidebar_message = '';
			} else {
				$sidebar_available = false;
				$use_sidebar_id = 'wp_inactive_widgets'; // add to inactive if sidebar does not exist in theme
				$sidebar_message_type = 'error';
				$sidebar_message = esc_html__( 'Sidebar does not exist in theme (using Inactive)', 'bignews' );
			}

			// Result for sidebar
			$results[$sidebar_id]['name'] = ! empty( $wp_registered_sidebars[$sidebar_id]['name'] ) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id; // sidebar name if theme supports it; otherwise ID
			$results[$sidebar_id]['message_type'] = $sidebar_message_type;
			$results[$sidebar_id]['message'] = $sidebar_message;
			$results[$sidebar_id]['widgets'] = array();

			// Loop widgets
			foreach ( $widgets as $widget_instance_id => $widget ) {

				$fail = false;
				// Get id_base (remove -# from end) and instance ID number
				$id_base = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
				$instance_id_number = str_replace( $id_base . '-', '', $widget_instance_id );

				// Does site support this widget?
				if ( ! $fail && ! isset( $available_widgets[$id_base] ) ) {
					$fail = true;
					$widget_message_type = 'error';
					$widget_message = esc_html__( 'Site does not support widget', 'bignews' ); // explain why widget not imported
				}

				// Does widget with identical settings already exist in same sidebar?
				if ( ! $fail && isset( $widget_instances[$id_base] ) ) {

					// Get existing widgets in this sidebar
					$sidebars_widgets = get_option( 'sidebars_widgets' );
					$sidebar_widgets = isset( $sidebars_widgets[$use_sidebar_id] ) ? $sidebars_widgets[$use_sidebar_id] : array(); // check Inactive if that's where will go

					// Loop widgets with ID base
					$single_widget_instances = ! empty( $widget_instances[$id_base] ) ? $widget_instances[$id_base] : array();

					foreach ( $single_widget_instances as $check_id => $check_widget ) {

						// Is widget in same sidebar and has identical settings?
						if ( in_array( "$id_base-$check_id", $sidebar_widgets ) && (array) $widget == $check_widget ) {

							$fail = true;
							$widget_message_type = 'warning';
							$widget_message = esc_html__( 'Widget already exists', 'bignews' ); // explain why widget not imported
							break;

						}

					}

				}

				// No failure
				if ( ! $fail ) {

					// Add widget instance
					$single_widget_instances = get_option( 'widget_' . $id_base ); // all instances for that widget ID base, get fresh every time
					$single_widget_instances = ! empty( $single_widget_instances ) ? $single_widget_instances : array( '_multiwidget' => 1 ); // start fresh if have to
					$single_widget_instances[] = (array) $widget; // add it

					// Get the key it was given
					end( $single_widget_instances );
					$new_instance_id_number = key( $single_widget_instances );

					// If key is 0, make it 1
					// When 0, an issue can occur where adding a widget causes data from other widget to load, and the widget doesn't stick (reload wipes it)
					if ( '0' === strval( $new_instance_id_number ) ) {
						$new_instance_id_number = 1;
						$single_widget_instances[$new_instance_id_number] = $single_widget_instances[0];
						unset( $single_widget_instances[0] );
					}

					// Move _multiwidget to end of array for uniformity
					if ( isset( $single_widget_instances['_multiwidget'] ) ) {
						$multiwidget = $single_widget_instances['_multiwidget'];
						unset( $single_widget_instances['_multiwidget'] );
						$single_widget_instances['_multiwidget'] = $multiwidget;
					}


					// Update option with new widget
					update_option( 'widget_' . $id_base, $single_widget_instances );

					// Assign widget instance to sidebar
					$sidebars_widgets = get_option( 'sidebars_widgets' ); // which sidebars have which widgets, get fresh every time
					$new_instance_id = $id_base . '-' . $new_instance_id_number; // use ID number from new widget instance
					$sidebars_widgets[$use_sidebar_id][] = $new_instance_id; // add new instance to sidebar
					update_option( 'sidebars_widgets', $sidebars_widgets ); // save the amended data

					// Success message
					if ( $sidebar_available ) {
						$widget_message_type = 'success';
						$widget_message = esc_html__( 'Imported', 'bignews' );
					} else {
						$widget_message_type = 'warning';
						$widget_message = esc_html__( 'Imported to Inactive', 'bignews' );
					}

				}

				// Result for widget instance
				$results[$sidebar_id]['widgets'][$widget_instance_id]['name'] = isset( $available_widgets[$id_base]['name'] ) ? $available_widgets[$id_base]['name'] : $id_base; // widget name or ID if name not available (not supported by site)
				$results[$sidebar_id]['widgets'][$widget_instance_id]['title'] = !empty( $widget->title ) ? $widget->title : esc_html__( 'No Title', 'bignews' ); // show "No Title" if widget instance is untitled
				$results[$sidebar_id]['widgets'][$widget_instance_id]['message_type'] = $widget_message_type;
				$results[$sidebar_id]['widgets'][$widget_instance_id]['message'] = $widget_message;
			}
		}
		return $results;

	}

	/**
	* Available widgets
	*
	* Gather site's widgets into array with ID base, name, etc.
	* Used by export and import functions.
	*
	* @since 2.2.0
	*
	* @global array $wp_registered_widget_updates
	* @return array Widget information
	*/
	function available_widgets() {
		global $wp_registered_widget_controls;
		$widget_controls = $wp_registered_widget_controls;
		$available_widgets = array();

		foreach ( $widget_controls as $widget ) {

			if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[$widget['id_base']] ) ) { // no dupes

				$available_widgets[$widget['id_base']]['id_base'] = $widget['id_base'];
				$available_widgets[$widget['id_base']]['name'] = $widget['name'];

			}
		}

		return $available_widgets;

	}

	/**
	 * Attempt to associate posts and menu items with previously missing parents
	 *
	 * An imported post's parent may not have been imported when it was first created
	 * so try again. Similarly for child menu items and menu items which were missing
	 * the object (e.g. post) they represent in the menu
	 */
	function backfill_parents() {
		global $wpdb;

		// find parents for post orphans
		foreach ( $this->post_orphans as $child_id => $parent_id ) {
			$local_child_id = $local_parent_id = false;
			if ( isset( $this->processed_posts[$child_id] ) )
				$local_child_id = $this->processed_posts[$child_id];
			if ( isset( $this->processed_posts[$parent_id] ) )
				$local_parent_id = $this->processed_posts[$parent_id];

			if ( $local_child_id && $local_parent_id )
				$wpdb->update( $wpdb->posts, array( 'post_parent' => $local_parent_id ), array( 'ID' => $local_child_id ), '%d', '%d' );
		}

		// all other posts/terms are imported, retry menu items with missing associated object
		$missing_menu_items = $this->missing_menu_items;
		foreach ( $missing_menu_items as $item )
			$this->process_menu_item( $item );

		// find parents for menu item orphans
		foreach ( $this->menu_item_orphans as $child_id => $parent_id ) {
			$local_child_id = $local_parent_id = 0;
			if ( isset( $this->processed_menu_items[$child_id] ) )
				$local_child_id = $this->processed_menu_items[$child_id];
			if ( isset( $this->processed_menu_items[$parent_id] ) )
				$local_parent_id = $this->processed_menu_items[$parent_id];

			if ( $local_child_id && $local_parent_id )
				update_post_meta( $local_child_id, '_menu_item_menu_item_parent', (int) $local_parent_id );
		}
	}

	/**
	 * Add menus
	 *
	 * @since 0.0.1
	 */
	public function set_demo_menus() {
		$menu_data = array();
		if(!empty($this->demo_menu)){
			foreach ($this->demo_menu as $menu_location => $menu_name) {
				$menu_id   = get_term_by('name', $menu_name, 'nav_menu');
				$menu_data[$menu_location] = $menu_id->term_id;
			}
		}

		set_theme_mod( 'nav_menu_locations', $menu_data);
		$front_page = get_page_by_title( 'Home' );
		update_option( 'page_on_front', $front_page->ID );
		update_option( 'show_on_front', 'page' );
	}

	/**
	 * Use stored mapping information to update old attachment URLs
	 */
	function backfill_attachment_urls() {
		global $wpdb;
		// make sure we do the longest urls first, in case one is a substring of another
		uksort( $this->url_remap, array(&$this, 'cmpr_strlen') );

		foreach ( $this->url_remap as $from_url => $to_url ) {
			// remap urls in post_content
			$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url) );
			// remap enclosure urls
			$result = $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from_url, $to_url) );
		}
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 */
	function remap_featured_images() {
		// cycle through posts that have a featured image
		foreach ( $this->featured_images as $post_id => $value ) {
			if ( isset( $this->processed_posts[$value] ) ) {
				$new_id = $this->processed_posts[$value];
				// only update if there's a difference
				if ( $new_id != $value )
					update_post_meta( $post_id, '_thumbnail_id', $new_id );
			}
		}
	}

	/**
	 * Parse a WXR file
	 *
	 * @param string $file Path to WXR file for parsing
	 * @return array Information gathered from the WXR file
	 */
	function parse( $file ) {
		$parser = new SwlabsCore_SimpleXML();
		$data = $parser->parse( $file );
		if( !empty($data) && is_array($data) ){
			$this->total_progress_steps = count( $data['posts'] ) + count( $data['categories'] ) + count( $data['tags'] ) + count( $data['terms'] );
		}

		if( !empty( $this->custom_category_file ) ) {
			$this->total_progress_steps++;
		}
		
		if( !empty( $this->theme_option_name ) && !empty( $this->theme_options_file ) ) {
			$this->total_progress_steps++;
		}

		if( !empty( $this->custom_sidebar_name ) && !empty( $this->custom_sidebar_file ) ) {
			$this->total_progress_steps++;
		}

		if( !empty ( $this->demo_menu ) ) {
			$this->total_progress_steps++;
		}

		return $data;
	}

	/**
     * Logging function
     * @param $function
     * @param $msg
     * @param bool $important
     */
    function log($function, $msg = '', $important = false) {
        // echo '<div class="td-demo-msg">' . $this->current_progress_step . ' - ' . $function . '  ' . $msg . '</div>';
        // ob_flush();
        // flush();
    }

    /**
     * Logging error function
     * @param $function
     * @param $msg
     * @param bool $important
     */
    function error_log($function, $msg = '', $important = false) {
        // echo '<div class="td-demo-msg">' . $function . '  ' . $msg . '</div>';
        // echo "<script>jQuery.fn.swlabsCom.shwProgressError();</script>";
        // ob_flush();
        // flush();
    }

    /**
     * Counting function
     */
    function counting() {
        // $current_progress_percent = round(($this->current_progress_step * 100) / $this->total_progress_steps, 0);
        // echo "<script>jQuery.fn.swlabsCom.shwProgressStep('" . $current_progress_percent . "');</script>";
        // $this->current_progress_step++;
        // ob_flush();
        // flush();
    }

	/**
	 * Decide if the given meta key maps to information we will want to import
	 *
	 * @param string $key The meta key to check
	 * @return string|bool The key if we do want to import, false if not
	 */
	function is_valid_meta_key( $key ) {
		// skip attachment metadata since we'll regenerate it from scratch
		// skip _edit_lock as not relevant for import
		if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ) ) )
			return false;
		return $key;
	}

	/**
	 * Decide whether or not the importer is allowed to create users.
	 * Default is true, can be filtered via import_allow_create_users
	 *
	 * @return bool True if creating users is allowed
	 */
	function allow_create_users() {
		return apply_filters( 'import_allow_create_users', true );
	}

	/**
	 * Decide whether or not the importer should attempt to download attachment files.
	 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
	 * made at the import options screen must also be true, false here hides that checkbox.
	 *
	 * @return bool True if downloading attachments is allowed
	 */
	function allow_fetch_attachments() {
		return apply_filters( 'import_allow_fetch_attachments', true );
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
	 *
	 * @return int Maximum attachment file size to import
	 */
	function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 * @return int 60
	 */
	function bump_request_timeout( $val = 60 ) {
		return 60;
	}

	// return the difference in length between two strings
	function cmpr_strlen( $a, $b ) {
		return strlen($b) - strlen($a);
	}
}