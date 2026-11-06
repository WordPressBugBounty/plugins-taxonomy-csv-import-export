<?php
/**
 * Post Order add-on (free). Lists the posts of a category in their order, lets you type an order
 * number, rename titles and trash posts. Drag and drop, quick sort and undo are Pro and live in
 * premium/class-post-order.php. Order is stored in the core "menu_order" field, so themes can sort
 * with orderby=menu_order.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostOrder {
	public const PAGE   = 'taxonomy-csv-import-export-post-order';
	public const NONCE  = 'taxocsie_post_order';
	private const LIMIT = 500;

	public static function init(): void {
		if ( ! AddonSettings::is_enabled( 'post_order' ) ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 19 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_taxocsie_post_order_save', array( __CLASS__, 'ajax_save_order' ) );
		add_action( 'wp_ajax_taxocsie_post_order_title', array( __CLASS__, 'ajax_update_title' ) );
		add_action( 'wp_ajax_taxocsie_post_order_trash', array( __CLASS__, 'ajax_trash' ) );
		add_shortcode( 'taxocsie_ordered_posts', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ), 20 );
	}

	/** Drag and drop, quick sort and undo need Pro. */
	public static function can_drag(): bool {
		return class_exists( \TaxoCSIE\Premium\PostOrder::class ) && \TaxoCSIE\Premium\PostOrder::is_available();
	}

	public static function add_page(): void {
		add_submenu_page(
			TAXOCSIE_PLUGIN_SLUG,
			__( 'Post Order', 'taxonomy-csv-import-export' ),
			__( 'Post Order', 'taxonomy-csv-import-export' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/* ------------------------- Frontend output ------------------------- */

	/** Gutenberg block that renders the same list as the shortcode. */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$js = TAXOCSIE_ASSETS_DIR_PATH . '/js/ordered-posts-block.js';
		wp_register_script(
			'taxocsie-ordered-posts-block',
			TAXOCSIE_ASSETS_URI . '/js/ordered-posts-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			is_readable( $js ) ? (string) filemtime( $js ) : TAXOCSIE_VERSION,
			true
		);
		wp_set_script_translations( 'taxocsie-ordered-posts-block', 'taxonomy-csv-import-export', TAXOCSIE_ROOT_DIR_PATH . 'languages' );
		register_block_type(
			'taxocsie/ordered-posts',
			array(
				'editor_script'   => 'taxocsie-ordered-posts-block',
				'render_callback' => array( __CLASS__, 'render_block' ),
				'attributes'      => array(
					'postType' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'taxonomy' => array(
						'type'    => 'string',
						'default' => 'category',
					),
					'term'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'limit'    => array(
						'type'    => 'number',
						'default' => 10,
					),
				),
			)
		);
	}

	public static function render_block( array $attributes ): string {
		return self::list_html(
			array(
				'post_type' => $attributes['postType'] ?? 'post',
				'taxonomy'  => $attributes['taxonomy'] ?? 'category',
				'term'      => $attributes['term'] ?? '',
				'limit'     => $attributes['limit'] ?? 10,
			)
		);
	}

	/** [taxocsie_ordered_posts post_type="post" taxonomy="category" term="news" limit="10"] */
	public static function shortcode( $atts ): string {
		return self::list_html(
			shortcode_atts(
				array(
					'post_type' => 'post',
					'taxonomy'  => 'category',
					'term'      => '',
					'limit'     => 10,
				),
				is_array( $atts ) ? $atts : array(),
				'taxocsie_ordered_posts'
			)
		);
	}

	/** Published posts of one term in the saved order. */
	private static function list_html( array $args ): string {
		$post_type = sanitize_key( (string) $args['post_type'] );
		$taxonomy  = sanitize_key( (string) $args['taxonomy'] );
		$term      = trim( (string) $args['term'] );
		$limit     = max( 1, min( 100, (int) $args['limit'] ) );
		if ( '' === $term || ! post_type_exists( $post_type ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return '';
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'has_password'   => false,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => $taxonomy,
						'field'            => ctype_digit( $term ) ? 'term_id' : 'slug',
						'terms'            => ctype_digit( $term ) ? (int) $term : sanitize_title( $term ),
						'include_children' => false,
					),
				),
			)
		);
		if ( ! $posts ) {
			return '';
		}

		$html = '<ul class="taxocsie-ordered-posts">';
		foreach ( $posts as $post ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
		}
		return $html . '</ul>';
	}

	/* ------------------------------ Admin ------------------------------ */

	/** Post types that can be ordered and have at least one taxonomy. */
	private static function post_types(): array {
		$types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			if ( self::taxonomies_for( $type->name ) ) {
				$types[ $type->name ] = $type;
			}
		}
		return $types;
	}

	private static function taxonomies_for( string $post_type ): array {
		$out = array();
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax ) {
			if ( $tax->show_ui ) {
				$out[ $tax->name ] = $tax;
			}
		}
		return $out;
	}

	public static function enqueue(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE !== $page ) {
			return;
		}
		$css = TAXOCSIE_ASSETS_DIR_PATH . '/css/post-order.css';
		$js  = TAXOCSIE_ASSETS_DIR_PATH . '/js/post-order.js';
		wp_enqueue_style( 'taxocsie-tool-pages', TAXOCSIE_ASSETS_URI . '/css/tool-pages.css', array(), (string) filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/css/tool-pages.css' ) );
		wp_enqueue_style( 'taxocsie-post-order', TAXOCSIE_ASSETS_URI . '/css/post-order.css', array(), (string) filemtime( $css ) );
		wp_enqueue_script( 'taxocsie-post-order', TAXOCSIE_ASSETS_URI . '/js/post-order.js', array( 'jquery' ), (string) filemtime( $js ), true );
		wp_localize_script(
			'taxocsie-post-order',
			'taxocsiePostOrder',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'saving'  => __( 'Saving…', 'taxonomy-csv-import-export' ),
				'saved'   => __( 'Saved', 'taxonomy-csv-import-export' ),
				'error'   => __( 'Could not save. Please try again.', 'taxonomy-csv-import-export' ),
				'confirm' => __( 'Move this post to the Trash?', 'taxonomy-csv-import-export' ),
				'empty'   => __( 'No posts left in this category.', 'taxonomy-csv-import-export' ),
			)
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot access this page.', 'taxonomy-csv-import-export' ) );
		}

		$types    = self::post_types();
		$can_drag = self::can_drag();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$post_type = isset( $_GET['ptype'] ) ? sanitize_key( wp_unslash( $_GET['ptype'] ) ) : '';
		if ( ! isset( $types[ $post_type ] ) ) {
			$post_type = isset( $types['docs'] ) ? 'docs' : (string) key( $types );
		}
		$taxes    = $post_type ? self::taxonomies_for( $post_type ) : array();
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( ! isset( $taxes[ $taxonomy ] ) ) {
			$taxonomy = (string) key( $taxes );
		}
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		// phpcs:enable

		$posts = array();
		if ( $post_type && $taxonomy && $term_id ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					'posts_per_page' => self::LIMIT,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy'         => $taxonomy,
							'terms'            => $term_id,
							'include_children' => false,
						),
					),
				)
			);
		}
		?>
		<div class="taxocsie-wrap taxocsie-tool taxocsie-po">
			<h1><?php esc_html_e( 'Post Order', 'taxonomy-csv-import-export' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					$can_drag
						? __( 'Choose a category to see every post in it. Drag rows to change their order, rename a title in place, or move a post to the Trash. Changes save automatically.', 'taxonomy-csv-import-export' )
						: __( 'Choose a category to see every post in it. Type a number in the Order column to move a post, rename a title in place, or move a post to the Trash. Changes save automatically.', 'taxonomy-csv-import-export' )
				);
				?>
			</p>

			<?php if ( ! $can_drag ) : ?>
				<div class="notice notice-info inline">
					<p>
						<strong><?php esc_html_e( 'Drag and drop is a Pro feature.', 'taxonomy-csv-import-export' ); ?></strong>
						<?php esc_html_e( 'Pro lets you drag posts into place, sort a whole category in one click (A–Z, newest first) and undo the last change.', 'taxonomy-csv-import-export' ); ?>
						<a href="<?php echo esc_url( Freemius::get_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<form method="get" class="taxocsie-po__filters" id="taxocsie-po-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label>
					<span><?php esc_html_e( 'Post type', 'taxonomy-csv-import-export' ); ?></span>
					<select name="ptype" data-autosubmit data-reset="taxonomy,term">
						<?php foreach ( $types as $type ) : ?>
							<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $post_type, $type->name ); ?>><?php echo esc_html( $type->labels->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php if ( $taxes ) : ?>
					<label>
						<span><?php esc_html_e( 'Taxonomy', 'taxonomy-csv-import-export' ); ?></span>
						<select name="taxonomy" data-autosubmit data-reset="term">
							<?php foreach ( $taxes as $tax ) : ?>
								<option value="<?php echo esc_attr( $tax->name ); ?>" <?php selected( $taxonomy, $tax->name ); ?>><?php echo esc_html( $tax->labels->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Category', 'taxonomy-csv-import-export' ); ?></span>
						<?php
						wp_dropdown_categories(
							array(
								'taxonomy'          => $taxonomy,
								'name'              => 'term',
								'id'                => 'taxocsie-po-term',
								'selected'          => $term_id,
								'hide_empty'        => false,
								'hierarchical'      => true,
								'show_option_none'  => __( '— Select —', 'taxonomy-csv-import-export' ),
								'option_none_value' => '0',
								'value_field'       => 'term_id',
							)
						);
						?>
					</label>
				<?php endif; ?>
				<noscript><button class="button" type="submit"><?php esc_html_e( 'Show', 'taxonomy-csv-import-export' ); ?></button></noscript>
			</form>

			<?php if ( ! $term_id ) : ?>
				<p class="taxocsie-po__hint"><?php esc_html_e( 'Select a category to start ordering.', 'taxonomy-csv-import-export' ); ?></p>
			<?php else : ?>
				<?php if ( $can_drag ) : ?>
					<div class="taxocsie-po__toolbar" role="group" aria-label="<?php esc_attr_e( 'Quick sort', 'taxonomy-csv-import-export' ); ?>">
						<span><?php esc_html_e( 'Quick sort:', 'taxonomy-csv-import-export' ); ?></span>
						<button type="button" class="button" data-sort="az"><?php esc_html_e( 'A–Z', 'taxonomy-csv-import-export' ); ?></button>
						<button type="button" class="button" data-sort="za"><?php esc_html_e( 'Z–A', 'taxonomy-csv-import-export' ); ?></button>
						<button type="button" class="button" data-sort="new"><?php esc_html_e( 'Newest first', 'taxonomy-csv-import-export' ); ?></button>
						<button type="button" class="button" data-sort="old"><?php esc_html_e( 'Oldest first', 'taxonomy-csv-import-export' ); ?></button>
						<button type="button" class="button" data-sort="reverse"><?php esc_html_e( 'Reverse', 'taxonomy-csv-import-export' ); ?></button>
						<button type="button" class="button" data-undo disabled><?php esc_html_e( 'Undo last change', 'taxonomy-csv-import-export' ); ?></button>
					</div>
				<?php endif; ?>
				<div class="taxocsie-po__status" id="taxocsie-po-status" role="status" aria-live="polite"></div>
				<table class="widefat striped taxocsie-po__table">
					<thead>
						<tr>
							<?php if ( $can_drag ) : ?>
								<th class="taxocsie-po__col-handle"><span class="screen-reader-text"><?php esc_html_e( 'Drag', 'taxonomy-csv-import-export' ); ?></span></th>
							<?php endif; ?>
							<th class="taxocsie-po__col-num"><?php esc_html_e( 'Order', 'taxonomy-csv-import-export' ); ?></th>
							<th><?php esc_html_e( 'Title', 'taxonomy-csv-import-export' ); ?></th>
							<th><?php esc_html_e( 'Status', 'taxonomy-csv-import-export' ); ?></th>
							<th class="taxocsie-po__col-actions"><?php esc_html_e( 'Actions', 'taxonomy-csv-import-export' ); ?></th>
						</tr>
					</thead>
					<tbody id="taxocsie-po-rows">
						<?php foreach ( $posts as $i => $post ) : ?>
							<tr data-id="<?php echo esc_attr( $post->ID ); ?>" data-date="<?php echo esc_attr( (string) get_post_time( 'U', true, $post ) ); ?>">
								<?php if ( $can_drag ) : ?>
									<td class="taxocsie-po__col-handle"><span class="taxocsie-po-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'taxonomy-csv-import-export' ); ?>"></span></td>
								<?php endif; ?>
								<td class="taxocsie-po__col-num"><input type="number" min="1" class="taxocsie-po-num small-text" value="<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Order number', 'taxonomy-csv-import-export' ); ?>"></td>
								<td><input type="text" class="taxocsie-po-title regular-text" value="<?php echo esc_attr( $post->post_title ); ?>" aria-label="<?php esc_attr_e( 'Post title', 'taxonomy-csv-import-export' ); ?>"></td>
								<td><?php echo esc_html( get_post_status_object( $post->post_status )->label ?? $post->post_status ); ?></td>
								<td class="taxocsie-po__col-actions">
									<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
										<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'taxonomy-csv-import-export' ); ?></a>
									<?php endif; ?>
									<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
										<button type="button" class="button button-small button-link-delete taxocsie-po-trash"><?php esc_html_e( 'Trash', 'taxonomy-csv-import-export' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="taxocsie-po__empty" id="taxocsie-po-empty" <?php echo $posts ? 'hidden' : ''; ?>><?php esc_html_e( 'No posts in this category yet.', 'taxonomy-csv-import-export' ); ?></p>
				<?php if ( count( $posts ) >= self::LIMIT ) : ?>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d: post limit. */ __( 'Showing the first %d posts.', 'taxonomy-csv-import-export' ), self::LIMIT ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------ AJAX ------------------------------ */

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Every handler calls guard(), which runs check_ajax_referer().

	private static function guard(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'taxonomy-csv-import-export' ) ), 403 );
		}
	}

	/** Save the sequence as menu_order 1..N (used by typed numbers and, in Pro, by dragging). */
	public static function ajax_save_order(): void {
		global $wpdb;
		self::guard();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each id is cast with absint below.
		$raw = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		if ( ! $ids || count( $ids ) > self::LIMIT ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'taxonomy-csv-import-export' ) ), 400 );
		}
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'taxonomy-csv-import-export' ) ), 403 );
			}
		}
		foreach ( $ids as $i => $id ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $i + 1 ), array( 'ID' => $id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $id );
		}
		wp_send_json_success();
	}

	public static function ajax_update_title(): void {
		self::guard();
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $id || '' === $title || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'taxonomy-csv-import-export' ) ), 400 );
		}
		$result = wp_update_post(
			wp_slash(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		wp_send_json_success( array( 'title' => get_the_title( $id ) ) );
	}

	public static function ajax_trash(): void {
		self::guard();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || ! current_user_can( 'delete_post', $id ) || ! wp_trash_post( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not move to the Trash.', 'taxonomy-csv-import-export' ) ), 400 );
		}
		wp_send_json_success();
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
