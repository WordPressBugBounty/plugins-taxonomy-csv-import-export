<?php

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyCreator {

	const OPTION_KEY = 'taxocsie_custom_taxonomies';

	public static function init() {
		$self = new self();
		add_action( 'init', array( $self, 'register_saved_taxonomies' ), 5 );
		add_action( 'wp_ajax_taxocsie_save_taxonomy',   array( $self, 'handle_ajax_save_taxonomy' ) );
		add_action( 'wp_ajax_taxocsie_delete_taxonomy', array( $self, 'handle_ajax_delete_taxonomy' ) );
		add_action( 'wp_ajax_taxocsie_delete_taxonomies', array( $self, 'handle_ajax_delete_taxonomies' ) );
	}

	public function register_saved_taxonomies() {
		$taxonomies = get_option( self::OPTION_KEY, array() );
		foreach ( $taxonomies as $slug => $data ) {
			if ( taxonomy_exists( $slug ) ) {
				continue;
			}
			register_taxonomy(
				$slug,
				$data['post_types'],
				array(
					'labels'       => array(
						'name'          => $data['label'],
						'singular_name' => $data['singular_label'],
					),
					'description'  => $data['description'] ?? '',
					'hierarchical' => (bool) $data['hierarchical'],
					'public'       => true,
					'show_ui'      => true,
					'show_in_menu' => true,
					'show_in_rest' => true,
					'rewrite'      => array( 'slug' => $slug ),
				)
			);
		}
	}

	public function handle_ajax_save_taxonomy() {
		check_ajax_referer( 'taxocsie_save_taxonomy', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$label          = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		$singular_label = sanitize_text_field( wp_unslash( $_POST['singular_label'] ?? '' ) );
		$slug           = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		$description    = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$hierarchical   = ! empty( $_POST['hierarchical'] ) && $_POST['hierarchical'] === '1';
		$original_slug  = sanitize_key( wp_unslash( $_POST['original_slug'] ?? '' ) );
		$post_types     = isset( $_POST['post_types'] )
			? array_values( array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) ) )
			: array();

		if ( empty( $label ) ) {
			wp_send_json_error( __( 'Label is required.', 'taxonomy-csv-import-export' ) );
		}
		if ( empty( $slug ) ) {
			wp_send_json_error( __( 'Slug is required.', 'taxonomy-csv-import-export' ) );
		}
		if ( empty( $post_types ) ) {
			wp_send_json_error( __( 'Please select at least one post type.', 'taxonomy-csv-import-export' ) );
		}

		$saved = get_option( self::OPTION_KEY, array() );
		$is_edit = ! empty( $original_slug );

		if ( $is_edit && ! isset( $saved[ $original_slug ] ) ) {
			wp_send_json_error( __( 'The taxonomy you are editing could not be found.', 'taxonomy-csv-import-export' ) );
		}
		if ( isset( $saved[ $slug ] ) && ( ! $is_edit || $slug !== $original_slug ) ) {
			wp_send_json_error( __( 'A custom taxonomy with this slug already exists.', 'taxonomy-csv-import-export' ) );
		}
		if ( taxonomy_exists( $slug ) && ( ! $is_edit || $slug !== $original_slug ) ) {
			wp_send_json_error( __( 'This slug is already used by WordPress or another plugin.', 'taxonomy-csv-import-export' ) );
		}

		if ( $is_edit && $slug !== $original_slug ) {
			unset( $saved[ $original_slug ] );
		}

		$saved[ $slug ] = array(
			'label'          => $label,
			'singular_label' => $singular_label ?: $label,
			'description'    => $description,
			'hierarchical'   => $hierarchical,
			'post_types'     => $post_types,
		);

		update_option( self::OPTION_KEY, $saved );

		wp_send_json_success( array(
			'slug'           => $slug,
			'original_slug'  => $original_slug,
			'label'          => $label,
			'singular_label' => $singular_label ?: $label,
			'description'    => $description,
			'hierarchical'   => $hierarchical,
			'post_types'     => $post_types,
			'updated'        => $is_edit,
		) );
	}

	public function handle_ajax_delete_taxonomy() {
		check_ajax_referer( 'taxocsie_delete_taxonomy', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( empty( $slug ) ) {
			wp_send_json_error( __( 'Invalid slug.', 'taxonomy-csv-import-export' ) );
		}

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! isset( $saved[ $slug ] ) ) {
			wp_send_json_error( __( 'Taxonomy not found.', 'taxonomy-csv-import-export' ) );
		}

		unset( $saved[ $slug ] );
		update_option( self::OPTION_KEY, $saved );

		wp_send_json_success( array( 'slug' => $slug ) );
	}

	/** Delete selected plugin-managed taxonomies in one update. */
	public function handle_ajax_delete_taxonomies() {
		check_ajax_referer( 'taxocsie_delete_taxonomies', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$requested = isset( $_POST['slugs'] ) ? wp_unslash( $_POST['slugs'] ) : array();
		if ( ! is_array( $requested ) || empty( $requested ) ) {
			wp_send_json_error( __( 'Select at least one taxonomy.', 'taxonomy-csv-import-export' ) );
		}

		$slugs = array();
		foreach ( $requested as $requested_slug ) {
			if ( ! is_string( $requested_slug ) || '' === $requested_slug ) {
				wp_send_json_error( __( 'Invalid taxonomy selection.', 'taxonomy-csv-import-export' ) );
			}
			$slug = sanitize_key( $requested_slug );
			if ( '' === $slug || $slug !== $requested_slug ) {
				wp_send_json_error( __( 'Invalid taxonomy selection.', 'taxonomy-csv-import-export' ) );
			}
			$slugs[ $slug ] = $slug;
		}

		$saved = get_option( self::OPTION_KEY, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( $slugs as $slug ) {
			if ( ! isset( $saved[ $slug ] ) ) {
				wp_send_json_error( __( 'One or more selected taxonomies were not found. Refresh the page and try again.', 'taxonomy-csv-import-export' ) );
			}
		}

		foreach ( $slugs as $slug ) {
			unset( $saved[ $slug ] );
		}
		update_option( self::OPTION_KEY, $saved );

		wp_send_json_success( array( 'slugs' => array_values( $slugs ) ) );
	}

	public static function get_saved() {
		return (array) get_option( self::OPTION_KEY, array() );
	}
}
