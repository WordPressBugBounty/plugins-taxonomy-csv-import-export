<?php
/**
 * Auto "Order" (menu_order) in the post editor: picking a category fills the
 * Order field with the next number in that category and shows the numbers
 * already used. Works for every post type that supports "page-attributes".
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostOrderEditor {

	public static function init(): void {
		if ( ! AddonSettings::is_enabled( 'post_order' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register_route(): void {
		register_rest_route(
			'taxocsie/v1',
			'/next-order',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'taxonomy'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'term'      => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'post_id'   => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
				'callback'            => array( __CLASS__, 'next_order' ),
			)
		);
	}

	public static function next_order( \WP_REST_Request $req ) {
		$post_type = $req['post_type'];
		$taxonomy  = $req['taxonomy'];

		if ( ! post_type_exists( $post_type ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return new \WP_Error( 'taxocsie_bad_request', __( 'Invalid post type or taxonomy.', 'taxonomy-csv-import-export' ), array( 'status' => 400 ) );
		}

		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => $req['post_id'] ? array( $req['post_id'] ) : array(),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => $taxonomy,
						'terms'            => $req['term'],
						'include_children' => false,
					),
				),
			)
		);

		$max   = 0;
		$items = array();
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'read_post', $id ) ) {
				continue;
			}
			$order   = (int) get_post_field( 'menu_order', $id );
			$max     = max( $max, $order );
			$title   = get_the_title( $id );
			$items[] = array(
				'id'    => $id,
				'title' => '' !== $title ? html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) : __( '(no title)', 'taxonomy-csv-import-export' ),
				'order' => $order,
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return $a['order'] === $b['order'] ? strcasecmp( $a['title'], $b['title'] ) : $a['order'] - $b['order'];
			}
		);

		$term = get_term( $req['term'], $taxonomy );

		return array(
			'order' => $items ? $max + 1 : 1,
			'term'  => ( $term && ! is_wp_error( $term ) ) ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '',
			'items' => $items,
		);
	}

	/**
	 * Load the editor script on post edit screens.
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue( $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! post_type_supports( $screen->post_type, 'page-attributes' ) ) {
			return;
		}

		$taxes = array();
		foreach ( get_object_taxonomies( $screen->post_type, 'objects' ) as $tax ) {
			if ( $tax->show_ui ) {
				$taxes[] = array(
					'name'     => $tax->name,
					'restBase' => $tax->rest_base ? $tax->rest_base : $tax->name,
				);
			}
		}
		if ( ! $taxes ) {
			return;
		}

		$file = TAXOCSIE_ASSETS_DIR_PATH . '/js/post-order-editor.js';
		$deps = $screen->is_block_editor()
			? array( 'wp-api-fetch', 'wp-data', 'wp-element', 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-components', 'jquery' )
			: array( 'wp-api-fetch', 'wp-data', 'jquery' );

		wp_enqueue_script( 'taxocsie-post-order-editor', TAXOCSIE_ASSETS_URI . '/js/post-order-editor.js', $deps, (string) filemtime( $file ), true );
		wp_localize_script(
			'taxocsie-post-order-editor',
			'taxocsiePostOrderEditor',
			array(
				'postType' => $screen->post_type,
				'taxes'    => $taxes,
			)
		);
	}
}
