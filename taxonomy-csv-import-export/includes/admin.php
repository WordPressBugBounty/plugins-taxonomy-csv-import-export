<?php

namespace TaxoCSIE;

if (!defined('ABSPATH')) {
	exit;
}

use TaxoCSIE\Helper;
use TaxoCSIE\Admin\SessionHandler;
use TaxoCSIE\Admin\PreviewTable;
use TaxoCSIE\Admin\Validator;
use TaxoCSIE\Premium\TermImageManager;

class Admin
{
	private function can_use_taxonomy( string $taxonomy, string $capability = 'manage_terms' ): bool {
		$object = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : false;
		return $object instanceof \WP_Taxonomy
			&& ! empty( $object->cap->$capability )
			&& current_user_can( $object->cap->$capability );
	}

	public static function init()
	{
		$self = new self();
		add_action('admin_init', array($self, 'handle_taxocsie_import_export'));
		add_action( 'admin_post_taxocsie_export_users', array( $self, 'taxocsie_export_users' ) );
		add_action( 'admin_head', array( $self, 'taxocsie_admin_notice_remove' ), PHP_INT_MAX );
		add_action('wp_ajax_taxocsie_preview_export', array($self, 'handle_preview_export'));
		add_action('wp_ajax_taxocsie_get_parent_terms', array($self, 'handle_get_parent_terms'));
		add_action('wp_ajax_taxocsie_ajax_create_term', array($self, 'handle_ajax_create_term'));
		add_action( 'wp_ajax_taxocsie_mass_get_terms', array( $self, 'handle_mass_get_terms' ) );
		add_action( 'wp_ajax_taxocsie_mass_update_terms', array( $self, 'handle_mass_update_terms' ) );
		
		// NEW: Add AJAX handler for demo generator
		add_action('wp_ajax_taxocsie_ajax_demo_generate', array($self, 'handle_ajax_demo_generate'));
	}

	public function handle_taxocsie_import_export()
	{
		if ( isset( $_POST['taxonomy_export_csv'] ) ) {
			$this->taxocsie_export_csv();
		} elseif ( isset( $_POST['taxonomy_import_csv'] ) && isset( $_FILES['csv_file'] ) ) {
			if ( isset( $_POST['tcie_dry_run'] ) ) {
				wp_die( esc_html__( 'The legacy dry-run importer is unavailable. Use Advanced Import instead.', 'taxonomy-csv-import-export' ) );
			}

			$this->taxocsie_import_csv();
		} elseif ( isset( $_POST['taxocsie_import_users'] ) && isset( $_FILES['taxocsie_import_file'] ) ) {
			$this->taxocsie_import_users();
		} elseif ( isset( $_POST['taxocsie_create_term'] ) ) {
			$this->taxocsie_create_term();
		}

	}

	/**
	 * Validate and return a safe CSV upload temp path.
	 *
	 * @param string $field_name Uploaded file field name.
	 * @return string|false Temp file path or false.
	 */
	private function validate_csv_upload( $field_name = 'csv_file' ) {
		if ( ! isset( $_FILES[ $field_name ] ) || ! is_array( $_FILES[ $field_name ] ) ) {
			return false;
		}

		if ( empty( $_FILES[ $field_name ]['tmp_name'] ) || ! is_uploaded_file( $_FILES[ $field_name ]['tmp_name'] ) ) {
			return false;
		}

		if ( ! empty( $_FILES[ $field_name ]['error'] ) ) {
			return false;
		}

		$basename = isset( $_FILES[ $field_name ]['name'] ) ? sanitize_file_name( wp_basename( wp_unslash( $_FILES[ $field_name ]['name'] ) ) ) : '';
		$filetype = wp_check_filetype( $basename, array( 'csv' => 'text/csv', 'txt' => 'text/plain' ) );
		if ( ! in_array( $filetype['ext'], array( 'csv', 'txt' ), true ) ) {
			return false;
		}

		if ( ! file_exists( $_FILES[ $field_name ]['tmp_name'] ) || ! is_readable( $_FILES[ $field_name ]['tmp_name'] ) ) {
			return false;
		}

		return $_FILES[ $field_name ]['tmp_name'];
	}
	
	/**
	 * Handle dry run import (preview only)
	 */
	public function handle_dry_run_import()
	{
		if (!current_user_can('manage_options') || !check_admin_referer('taxonomy_csv_import', 'taxonomy_csv_import_nonce')) {
			wp_die('Unauthorized import request.');
		}

		$taxonomy = isset( $_POST['taxocsie_type'] ) ? sanitize_text_field( wp_unslash( $_POST['taxocsie_type'] ) ) : '';
		$csv_upload = $this->validate_csv_upload( 'csv_file' );
		if ( ! $this->can_use_taxonomy( $taxonomy ) || ! $this->can_use_taxonomy( $taxonomy, 'edit_terms' ) || false === $csv_upload ) {
			wp_die( 'Invalid import request.' );
		}
		
		// Parse CSV.
		$csv_data = $this->parse_csv_file( $csv_upload );
		if (empty($csv_data)) {
			wp_die('No data found in CSV file.');
		}
		
		// Validate rows
		$validator = new Validator();
		$validated_rows = $validator->validate_rows($csv_data, $taxonomy);
		
		// Generate preview data
		$preview_data = $this->generate_preview_data($validated_rows);
		
		// Store in session
		$session = SessionHandler::get_instance();
		$session->save_preview_data($preview_data);
		
		// Redirect to preview page
		wp_safe_redirect(admin_url('admin.php?page=taxonomy-csv-import-export-manager&preview=1'));
		exit;
	}
	
	/**
	 * Parse CSV file
	 */
	private function parse_csv_file($file_path) {
		$rows = array();
		$handle = fopen($file_path, 'r');// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		
		if (!$handle) {
			return $rows;
		}
		
		// Read headers
		$headers = fgetcsv($handle, 0, ',', '"', '\\');
		if (false === $headers) {
			fclose($handle);// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $rows;
		}
		
		// Normalize headers
		$headers = array_map('strtolower', array_map('trim', $headers));
		$required = array('name', 'taxonomy');
		
		// Check required headers
		$missing = array_diff($required, $headers);
		if (!empty($missing)) {
			fclose($handle);// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array('error' => sprintf(
				/* translators: 1: Missing headers */
				esc_html__('Missing required headers: %s', 'taxonomy-csv-import-export'),
				implode(', ', $missing)
			));
		}
		
		// Read rows
		$row_number = 1;
		while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			$data = array_map( array( Helper::class, 'csv_original_cell' ), $data );
			$row_number++;
			
			if (empty(array_filter($data))) {
				continue;
			}
			
			$row = array();
			foreach ($headers as $index => $header) {
				$row[$header] = isset($data[$index]) ? trim($data[$index]) : '';
			}
			
			// Add taxonomy if not present in CSV
			if (!isset($row['taxonomy']) || empty($row['taxonomy'])) {
				// Will be set during validation
			}
			
			$rows[] = array(
				'row_number' => $row_number,
				'data' => $row
			);
		}
		
		fclose($handle);// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}
	
	/**
	 * Generate preview data from validated rows
	 */
	private function generate_preview_data($validated_rows) {
		$preview_data = array(
			'rows' => array(),
			'summary' => array(
				'total' => 0,
				'new' => 0,
				'update' => 0,
				'skip' => 0,
				'warning' => 0,
				'error' => 0,
				'ready' => 0
			)
		);
		
		foreach ($validated_rows as $row) {
			$preview_row = array(
				'row_number' => $row['row_number'],
				'name' => $row['name'],
				'slug' => $row['slug'],
				'description' => $row['description'],
				'parent' => $row['parent'],
				'taxonomy' => $row['taxonomy'],
				'status' => $row['status'],
				'errors' => $row['errors'],
				'warnings' => $row['warnings'],
				'existing_term' => isset($row['existing_term']) ? $row['existing_term'] : null
			);
			
			$preview_data['rows'][] = $preview_row;
			
			// Update summary
			$preview_data['summary']['total']++;
			
			if ('error' === $row['status']) {
				$preview_data['summary']['error']++;
			} elseif ('warning' === $row['status']) {
				$preview_data['summary']['warning']++;
			} elseif ('update' === $row['status']) {
				$preview_data['summary']['update']++;
				$preview_data['summary']['ready']++;
			} elseif ('new' === $row['status']) {
				$preview_data['summary']['new']++;
				$preview_data['summary']['ready']++;
			} elseif ('skip' === $row['status']) {
				$preview_data['summary']['skip']++;
			}
		}
		
		return $preview_data;
	}

	public function taxocsie_export_csv()
	{
		if (!current_user_can('manage_options') || !check_admin_referer('taxonomy_csv_export', 'taxonomy_csv_export_nonce')) {
			wp_die('Unauthorized export request.');
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$taxonomy = sanitize_text_field(wp_unslash($_POST['taxocsie_type']));
		if ( ! $this->can_use_taxonomy( $taxonomy ) ) {
			wp_die('Invalid taxonomy.');
		}

		$include_images = isset( $_POST['taxocsie_include_images'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['taxocsie_include_images'] ) )
			&& class_exists( TermImageManager::class )
			&& TermImageManager::is_available();

		$terms = TermQueryFilters::get_terms( $taxonomy, $_POST );

		if ( is_wp_error( $terms ) ) {
			wp_die( esc_html__( 'Unable to export taxonomy terms.', 'taxonomy-csv-import-export' ) );
		}

		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="' . $taxonomy . '-terms.csv"');

		$output = fopen('php://output', 'w');
		$headers = array( 'name', 'slug', 'description', 'parent_slug', 'custom_link' );
		if ( $include_images ) {
			$headers[] = 'image_source';
		}
		fputcsv( $output, $headers, ',', '"', '' );

		foreach ($terms as $term) {
			$parent_term = $term->parent ? get_term($term->parent, $taxonomy) : null;
			$parent_slug = $parent_term ? $parent_term->slug : '';
			$row = array( $term->name, $term->slug, $term->description, $parent_slug, TermCustomLinks::get( (int) $term->term_id ) );
			if ( $include_images ) {
				$row[] = TermImageManager::get_source( (int) $term->term_id, $taxonomy );
			}
			fputcsv( $output, array_map( array( Helper::class, 'csv_safe_cell' ), $row ), ',', '"', '' );
		}
		Dashboard::increment( Dashboard::EXPORTED_ITEMS, count( $terms ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose($output);
		exit;
	}

	public function taxocsie_import_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'taxonomy_csv_import', 'taxonomy_csv_import_nonce' ) ) {
			wp_die( 'Unauthorized import request.' );
		}

		$taxonomy = isset( $_POST['taxocsie_type'] )
			? sanitize_text_field( wp_unslash( $_POST['taxocsie_type'] ) )
			: '';

		$csv_upload = $this->validate_csv_upload( 'csv_file' );

		if ( ! $this->can_use_taxonomy( $taxonomy ) || ! $this->can_use_taxonomy( $taxonomy, 'edit_terms' ) || false === $csv_upload ) {
			wp_die( 'Invalid import request.' );
		}

		$handle = fopen( $csv_upload, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			wp_die( esc_html__( 'The CSV file could not be opened.', 'taxonomy-csv-import-export' ) );
		}

		$raw_headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $raw_headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_die( esc_html__( 'The CSV file is empty.', 'taxonomy-csv-import-export' ) );
		}

		$aliases = array(
			'name'         => 'name',
			'term_name'    => 'name',
			'title'        => 'name',
			'slug'         => 'slug',
			'term_slug'    => 'slug',
			'description'  => 'description',
			'desc'         => 'description',
			'parent'       => 'parent_slug',
			'parent_slug'  => 'parent_slug',
			'image'        => 'image_source',
			'image_url'    => 'image_source',
			'image_source' => 'image_source',
			'custom_link'  => 'custom_link',
			'custom_url'   => 'custom_link',
			'term_link'    => 'custom_link',
		);
		$destinations      = array( 'name', 'slug', 'description', 'parent_slug', 'image_source', 'custom_link' );
		$submitted_mapping = isset( $_POST['column_mapping'] ) && is_array( $_POST['column_mapping'] )
			? wp_unslash( $_POST['column_mapping'] )
			: array();
		$headers           = array();
		foreach ( $raw_headers as $index => $header ) {
			$header = (string) $header;
			if ( 0 === $index ) {
				$header = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $header );
			}
			$key    = str_replace( array( ' ', '-' ), '_', strtolower( trim( $header ) ) );
			$mapped = isset( $aliases[ $key ] ) ? $aliases[ $key ] : 'ignore';

			if ( array_key_exists( $index, $submitted_mapping ) && is_scalar( $submitted_mapping[ $index ] ) ) {
				$mapped = sanitize_key( (string) $submitted_mapping[ $index ] );
			}

			if ( 'ignore' === $mapped || '' === $mapped ) {
				$headers[] = 'ignored_' . $index;
				continue;
			}

			if ( ! in_array( $mapped, $destinations, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_die( esc_html__( 'The submitted column mapping is invalid.', 'taxonomy-csv-import-export' ) );
			}

			if ( in_array( $mapped, $headers, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_die( esc_html__( 'Each import field can only be mapped once.', 'taxonomy-csv-import-export' ) );
			}

			$headers[] = $mapped;
		}

		if ( ! in_array( 'name', $headers, true ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_die( esc_html__( 'The CSV must include a name column.', 'taxonomy-csv-import-export' ) );
		}

		$images_available = class_exists( TermImageManager::class ) && TermImageManager::is_available();
		$has_image_column = in_array( 'image_source', $headers, true );
		$has_link_column  = in_array( 'custom_link', $headers, true );
		$imported         = array();
		$skipped          = array();
		$image_errors     = array();
		$link_errors      = array();

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			$row = array_map( array( Helper::class, 'csv_original_cell' ), $row );
			if ( array() === array_filter( $row, static function ( $value ) { return '' !== trim( (string) $value ); } ) ) {
				continue;
			}

			$data = array();
			foreach ( $headers as $index => $header ) {
				if ( 0 !== strpos( $header, 'ignored_' ) ) {
					$data[ $header ] = isset( $row[ $index ] ) ? $row[ $index ] : '';
				}
			}

			$name         = sanitize_text_field( $data['name'] ?? '' );
			$slug         = sanitize_title( $data['slug'] ?? '' );
			$description  = sanitize_textarea_field( $data['description'] ?? '' );
			$parent_slug  = sanitize_title( $data['parent_slug'] ?? '' );
			$image_source = sanitize_text_field( $data['image_source'] ?? '' );
			$custom_link  = sanitize_text_field( $data['custom_link'] ?? '' );
			$parent_id    = 0;
			$display_row  = array( $name, $slug, $description, $parent_slug );
			if ( $has_link_column ) {
				$display_row[] = $custom_link;
			}
			if ( $has_image_column ) {
				$display_row[] = $image_source;
			}

			if ( empty( $name ) ) {
				continue;
			}

			/*
			* Check if term already exists by name.
			*/
			$existing_term = term_exists( $name, $taxonomy );

			if ( $existing_term ) {
				$skipped[] = $display_row;
				continue;
			}

			/*
			* Check if term already exists by slug.
			*/
			if ( ! empty( $slug ) ) {
				$existing_slug = get_term_by(
					'slug',
					$slug,
					$taxonomy
				);

				if ( $existing_slug && ! is_wp_error( $existing_slug ) ) {
					$skipped[] = $display_row;
					continue;
				}
			}

			/*
			* Resolve parent slug to ID.
			*/
			if ( ! empty( $parent_slug ) ) {
				$parent_term = get_term_by(
					'slug',
					$parent_slug,
					$taxonomy
				);

				if ( $parent_term && ! is_wp_error( $parent_term ) ) {
					$parent_id = (int) $parent_term->term_id;
				}
			}

			/*
			* Insert only unique terms.
			*/
			$inserted = wp_insert_term(
				$name,
				$taxonomy,
				array(
					'slug'        => $slug,
					'description' => $description,
					'parent'      => $parent_id,
				)
			);

			if ( is_wp_error( $inserted ) ) {
				$skipped[] = $display_row;
				continue;
			}

			if ( $images_available && '' !== $image_source ) {
				$image_result = TermImageManager::import_from_source( (int) $inserted['term_id'], $taxonomy, $image_source, $name );
				if ( is_wp_error( $image_result ) ) {
					$image_errors[] = array(
						'term'    => $name,
						'message' => $image_result->get_error_message(),
					);
				}
			}

			if ( '' !== $custom_link ) {
				$normalized_link = TermCustomLinks::sanitize_link( $custom_link );
				if ( '' === $normalized_link || ! TermCustomLinks::set( (int) $inserted['term_id'], $normalized_link ) ) {
					$link_errors[] = array(
						'term'    => $name,
						'message' => __( 'The custom link is not a valid HTTP or HTTPS URL.', 'taxonomy-csv-import-export' ),
					);
				}
			}

			$imported[] = $display_row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		/*
		* Store only successfully imported rows.
		*/
		set_transient(
			'taxoscie_csv_data',
			$imported,
			60
		);

		Dashboard::increment( Dashboard::IMPORTED_ITEMS, count( $imported ) );

		set_transient(
			'taxoscie_csv_skipped',
			$skipped,
			60
		);

		set_transient( 'taxoscie_csv_has_images', $has_image_column, 60 );
		set_transient( 'taxoscie_csv_image_errors', $image_errors, 60 );
		set_transient( 'taxoscie_csv_has_custom_links', $has_link_column, 60 );
		set_transient( 'taxoscie_csv_link_errors', $link_errors, 60 );

		wp_safe_redirect(
			admin_url(
				'admin.php?page=taxonomy-csv-import-export-manager&imported=1'
			)
		);

		exit;
	}
	
	/**
	 * AJAX: Handle dry run preview
	 */
	public function handle_dry_run_preview() {
		check_ajax_referer('taxocsie_dry_run', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized.', 'taxonomy-csv-import-export'));
		}
		
		$csv_upload = $this->validate_csv_upload( 'csv_file' );
		if ( false === $csv_upload ) {
			wp_send_json_error( __( 'Please upload a CSV file.', 'taxonomy-csv-import-export' ) );
		}
		
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( ! $this->can_use_taxonomy( $taxonomy ) ) {
			wp_send_json_error( __( 'Invalid taxonomy.', 'taxonomy-csv-import-export' ) );
		}
		
		// Parse CSV.
		$csv_data = $this->parse_csv_file( $csv_upload );
		if (isset($csv_data['error'])) {
			wp_send_json_error($csv_data['error']);
		}
		
		if (empty($csv_data)) {
			wp_send_json_error(__('No data found in CSV file.', 'taxonomy-csv-import-export'));
		}
		
		// Validate rows
		$validator = new Validator();
		$validated_rows = $validator->validate_rows($csv_data, $taxonomy);
		
		// Generate preview
		$preview_data = $this->generate_preview_data($validated_rows);
		
		// Store in session
		$session = SessionHandler::get_instance();
		$session->save_preview_data($preview_data);
		
		// Render preview table
		$preview_table = new PreviewTable();
		$preview_html = $preview_table->render($preview_data);
		
		wp_send_json_success(array(
			'preview_html' => $preview_html,
			'summary' => $preview_data['summary'],
			'has_errors' => $preview_data['summary']['error'] > 0,
			'ready_count' => $preview_data['summary']['ready']
		));
	}
	
	/**
	 * AJAX: Start import from preview
	 */
	public function handle_start_import() {
		check_ajax_referer('taxocsie_start_import', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized.', 'taxonomy-csv-import-export'));
		}
		
		$import_mode = isset($_POST['import_mode']) ? sanitize_text_field(wp_unslash($_POST['import_mode'])) : 'valid_only';
		
		// Get preview data from session
		$session = SessionHandler::get_instance();
		$preview_data = $session->get_preview_data();
		
		if (empty($preview_data)) {
			wp_send_json_error(__('No preview data found. Please analyze the CSV first.', 'taxonomy-csv-import-export'));
		}
		foreach ( $preview_data['rows'] as $row ) {
			if ( ! isset( $row['taxonomy'] ) || ! is_string( $row['taxonomy'] ) || ! $this->can_use_taxonomy( $row['taxonomy'] ) || ! $this->can_use_taxonomy( $row['taxonomy'], 'edit_terms' ) ) {
				wp_send_json_error( __( 'You cannot manage terms in the selected taxonomy.', 'taxonomy-csv-import-export' ) );
			}
		}
		
		// Perform import
		$results = $this->perform_import_from_preview($preview_data, $import_mode);
		Dashboard::increment( Dashboard::IMPORTED_ITEMS, $results['imported'] + $results['updated'] );
		
		// Clear session
		$session->clear_preview_data();
		
		wp_send_json_success(array(
			'message' => sprintf(
				/* translators: 1: Imported count, 2: Updated count, 3: Skipped count */
				esc_html__('Import completed! %1$d imported, %2$d updated, %3$d skipped.', 'taxonomy-csv-import-export'),
				$results['imported'],
				$results['updated'],
				$results['skipped']
			),
			'results' => $results
		));
	}
	
	/**
	 * Perform import from preview data
	 */
	private function perform_import_from_preview($preview_data, $import_mode) {
		$results = array(
			'imported' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors' => array()
		);
		
		foreach ($preview_data['rows'] as $row) {
			// Skip rows with errors
			if (!empty($row['errors'])) {
				if ('stop_on_error' === $import_mode) {
					throw new \Exception(sprintf(
						/* translators: 1: Row number, 2: Error messages */
						esc_html__('Stopped at row %1$d: %2$s', 'taxonomy-csv-import-export'),
						$row['row_number'],//phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						implode(', ', wp_list_pluck($row['errors'], 'message'))// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					));
				}
				$results['skipped']++;
				continue;
			}
			
			// Process the row
			try {
				$taxonomy = $row['taxonomy'];
				$name = $row['name'];
				$slug = !empty($row['slug']) ? $row['slug'] : sanitize_title($name);
				$description = isset($row['description']) ? $row['description'] : '';
				$parent = isset($row['parent_id']) ? $row['parent_id'] : 0;
				
				// Check if term exists
				$existing = isset($row['existing_term']) ? $row['existing_term'] : null;
				
				if ($existing) {
					// Update existing term
					$args = array(
						'name' => $name,
						'slug' => $slug,
						'description' => $description,
						'parent' => $parent
					);
					
					$result = wp_update_term($existing->term_id, $taxonomy, $args);
					
					if (is_wp_error($result)) {
						throw new \Exception($result->get_error_message());
					}
					
					$results['updated']++;
				} else {
					// Insert new term
					$args = array(
						'slug' => $slug,
						'description' => $description,
						'parent' => $parent
					);
					
					$result = wp_insert_term($name, $taxonomy, $args);
					
					if (is_wp_error($result)) {
						throw new \Exception($result->get_error_message());
					}
					
					$results['imported']++;
				}
			} catch (\Exception $e) {
				if ('stop_on_error' === $import_mode) {
					throw $e;
				}
				$results['errors'][] = array(
					'row' => $row['row_number'],
					'message' => $e->getMessage()
				);
				$results['skipped']++;
			}
		}
		
		return $results;
	}
	
	/**
	 * AJAX: Download error report
	 */
	public function handle_download_error_report() {
		check_ajax_referer('taxocsie_download_errors', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized.');
		}
		
		$session = SessionHandler::get_instance();
		$preview_data = $session->get_preview_data();
		
		if (empty($preview_data)) {
			wp_die('No preview data found.');
		}
		
		// Generate CSV
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="error-report.csv"');
		
		$output = fopen('php://output', 'w');
		fputcsv($output, array('Row', 'Name', 'Slug', 'Status', 'Error', 'Suggestion'));
		
		foreach ($preview_data['rows'] as $row) {
			if (!empty($row['errors'])) {
				$error_messages = array();
				$suggestions = array();
				
				foreach ($row['errors'] as $error) {
					$error_messages[] = $error['message'];
					if (isset($error['suggestion'])) {
						$suggestions[] = $error['suggestion'];
					}
				}
				
				fputcsv($output, array_map( array( Helper::class, 'csv_safe_cell' ), array(
					$row['row_number'],
					$row['name'],
					$row['slug'],
					$row['status'],
					implode('; ', $error_messages),
					implode('; ', $suggestions)
				) ));
			}
		}
		
		fclose($output);// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
	
	/**
	 * AJAX: Cancel preview
	 */
	public function handle_cancel_preview() {
		check_ajax_referer('taxocsie_cancel_preview', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized.', 'taxonomy-csv-import-export'));
		}
		
		$session = SessionHandler::get_instance();
		$session->clear_preview_data();
		
		wp_send_json_success();
	}

	public function taxocsie_import_users() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'create_users' ) || ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if (
			! isset( $_POST['taxocsie_nonce'] ) ||
			! wp_verify_nonce( $_POST['taxocsie_nonce'], 'taxocsie_import_users_nonce' )
		) {
			wp_safe_redirect( admin_url( 'admin.php?page=taxonomy-csv-import-export-manager&manager_view=users&manager_action=import&user-imported=error' ) );
			exit;
		}

		$file = $this->validate_csv_upload( 'taxocsie_import_file' );
		if ( false === $file ) {
			wp_safe_redirect( admin_url( 'admin.php?page=taxonomy-csv-import-export-manager&manager_view=users&manager_action=import&user-imported=error' ) );
			exit;
		}

		$imported = Helper::import_users( $file );
		Dashboard::increment( Dashboard::IMPORTED_ITEMS, $imported );

		wp_safe_redirect( admin_url( 'admin.php?page=taxonomy-csv-import-export-manager&manager_view=users&manager_action=import&user-imported=success' ) );
		exit;
	}

	public function taxocsie_export_users() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'list_users' ) || ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to export users.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'taxocsie_export_users', 'taxocsie_export_users_nonce' );

		$roles = isset( $_POST['roles'] ) ? wp_unslash( $_POST['roles'] ) : array();
		if ( ! is_array( $roles ) ) {
			wp_die( esc_html__( 'Invalid role filter.', 'taxonomy-csv-import-export' ), '', array( 'response' => 400 ) );
		}

		$available_roles = wp_roles()->roles;
		$selected_roles  = array();
		foreach ( $roles as $role ) {
			if ( ! is_string( $role ) || ! isset( $available_roles[ $role ] ) ) {
				wp_die( esc_html__( 'Invalid role filter.', 'taxonomy-csv-import-export' ), '', array( 'response' => 400 ) );
			}
			$selected_roles[] = $role;
		}

		Helper::export_users( $selected_roles );
	}

	public function taxocsie_create_term() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'taxocsie_create_term', 'taxocsie_create_term_nonce' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$taxonomy    = sanitize_text_field( wp_unslash( $_POST['taxocsie_create_taxonomy'] ?? '' ) );
		$name        = sanitize_text_field( wp_unslash( $_POST['term_name'] ?? '' ) );
		$slug        = sanitize_title( wp_unslash( $_POST['term_slug'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['term_description'] ?? '' ) );
		$raw_link    = sanitize_text_field( wp_unslash( $_POST['term_custom_link'] ?? '' ) );
		$custom_link = '' !== $raw_link ? TermCustomLinks::sanitize_link( $raw_link ) : '';
		$parent      = absint( $_POST['term_parent'] ?? 0 );

		$base = admin_url( 'admin.php?page=taxonomy-csv-import-export-create' );

		if ( ! $this->can_use_taxonomy( $taxonomy, 'edit_terms' ) ) {
			wp_safe_redirect( $base . '&create_error=' . rawurlencode( 'Invalid taxonomy selected.' ) );
			exit;
		}

		if ( empty( $name ) ) {
			wp_safe_redirect( $base . '&create_error=' . rawurlencode( 'Term name is required.' ) );
			exit;
		}

		if ( '' !== $raw_link && '' === $custom_link ) {
			wp_safe_redirect( $base . '&create_error=' . rawurlencode( 'Custom link must be a valid HTTP or HTTPS URL.' ) );
			exit;
		}

		$args = array(
			'description' => $description,
			'parent'      => $parent,
		);
		if ( ! empty( $slug ) ) {
			$args['slug'] = $slug;
		}

		$result = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( $base . '&create_error=' . rawurlencode( $result->get_error_message() ) );
			exit;
		}

		if ( '' !== $custom_link ) {
			TermCustomLinks::set( (int) $result['term_id'], $custom_link );
		}

		Dashboard::increment( Dashboard::CREATED_ITEMS );

		wp_safe_redirect( $base . '&term_created=1&created_taxonomy=' . rawurlencode( $taxonomy ) );
		exit;
	}

	public function handle_ajax_create_term() {
		check_ajax_referer( 'taxocsie_ajax_create_term', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$taxonomy    = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ?? '' ) );
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$raw_link    = sanitize_text_field( wp_unslash( $_POST['custom_link'] ?? '' ) );
		$custom_link = '' !== $raw_link ? TermCustomLinks::sanitize_link( $raw_link ) : '';
		$raw_image_id = $_POST['image_id'] ?? '';
		$image_id = 0;

		if ( ! $this->can_use_taxonomy( $taxonomy, 'edit_terms' ) ) {
			wp_send_json_error( esc_html__( 'Invalid taxonomy.', 'taxonomy-csv-import-export' ) );
		}

		if ( empty( $name ) ) {
			wp_send_json_error( esc_html__( 'Term name is required.', 'taxonomy-csv-import-export' ) );
		}

		if ( '' !== $raw_link && '' === $custom_link ) {
			wp_send_json_error( esc_html__( 'Custom link must be a valid HTTP or HTTPS URL.', 'taxonomy-csv-import-export' ) );
		}

		if ( '' !== $raw_image_id ) {
			if ( ! is_scalar( $raw_image_id ) || ! ctype_digit( (string) $raw_image_id ) || 0 === absint( $raw_image_id ) ) {
				wp_send_json_error( esc_html__( 'Choose a valid image from the Media Library.', 'taxonomy-csv-import-export' ) );
			}
			if ( ! class_exists( TermImageManager::class ) || ! TermImageManager::is_available() ) {
				wp_send_json_error( esc_html__( 'Term images require an active Pro license.', 'taxonomy-csv-import-export' ) );
			}
			$image_id = absint( $raw_image_id );
			if ( ! wp_attachment_is_image( $image_id ) ) {
				wp_send_json_error( esc_html__( 'Choose a valid image from the Media Library.', 'taxonomy-csv-import-export' ) );
			}
		}

		$result = wp_insert_term( $name, $taxonomy, array( 'description' => $description ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( esc_html( $result->get_error_message() ) );
		}

		if ( '' !== $custom_link ) {
			TermCustomLinks::set( (int) $result['term_id'], $custom_link );
		}
		$image_warning = '';
		if ( $image_id ) {
			$image_result = TermImageManager::set_attachment_id( (int) $result['term_id'], $taxonomy, $image_id );
			if ( is_wp_error( $image_result ) ) {
				$image_warning = $image_result->get_error_message();
			}
		}

		Dashboard::increment( Dashboard::CREATED_ITEMS );

		$term = get_term( $result['term_id'], $taxonomy );
		wp_send_json_success( array(
			'term_id' => $result['term_id'],
			'name'    => $term->name,
			'slug'    => $term->slug,
			'image_warning' => $image_warning,
		) );
	}

	public function handle_get_parent_terms() {
		check_ajax_referer( 'taxocsie_get_terms', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ?? '' ) );

		if ( ! $this->can_use_taxonomy( $taxonomy ) ) {
			wp_send_json_error( esc_html__( 'Invalid taxonomy.', 'taxonomy-csv-import-export' ) );
		}

		$tax_obj = get_taxonomy( $taxonomy );
		$terms   = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		$items = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$items[] = array( 'id' => $term->term_id, 'name' => $term->name );
			}
		}

		wp_send_json_success( array(
			'hierarchical' => (bool) $tax_obj->hierarchical,
			'terms'        => $items,
		) );
	}

	public function handle_preview_export() {
		check_ajax_referer( 'taxocsie_preview_export', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ?? '' ) );

		if ( ! $this->can_use_taxonomy( $taxonomy ) ) {
			wp_send_json_error( esc_html__( 'Invalid taxonomy.', 'taxonomy-csv-import-export' ) );
		}

		$include_images = isset( $_POST['include_images'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['include_images'] ) )
			&& class_exists( TermImageManager::class )
			&& TermImageManager::is_available();

		$terms = TermQueryFilters::get_terms( $taxonomy, $_POST );

		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( esc_html__( 'Failed to fetch terms.', 'taxonomy-csv-import-export' ) );
		}

		$rows = array();
		foreach ( $terms as $term ) {
			$parent_term = $term->parent ? get_term( $term->parent, $taxonomy ) : null;
			$row = array(
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent_slug' => ( $parent_term && ! is_wp_error( $parent_term ) ) ? $parent_term->slug : '',
				'custom_link' => TermCustomLinks::get( (int) $term->term_id ),
			);
			if ( $include_images ) {
				$row['image_source'] = TermImageManager::get_source( (int) $term->term_id, $taxonomy );
			}
			$rows[] = $row;
		}

		wp_send_json_success( $rows );
	}

	/** Return one editable page of terms for the Manager workspace. */
	public function handle_mass_get_terms(): void {
		check_ajax_referer( 'taxocsie_mass_term_editor', 'nonce' );

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$tax_obj  = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : null;
		if ( ! $tax_obj || empty( $tax_obj->cap->manage_terms ) || ! current_user_can( $tax_obj->cap->manage_terms ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit terms in this taxonomy.', 'taxonomy-csv-import-export' ) ), 403 );
		}

		$page     = max( 1, isset( $_POST['page_number'] ) ? absint( $_POST['page_number'] ) : 1 );
		$requested_per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
		$per_page = in_array( $requested_per_page, array( 5, 10, 20, 50 ), true ) ? $requested_per_page : 10;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$count_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'count',
		);
		if ( '' !== $search ) {
			$count_args['search'] = $search;
		}
		$total_result = get_terms( $count_args );
		if ( is_wp_error( $total_result ) ) {
			wp_send_json_error( array( 'message' => $total_result->get_error_message() ) );
		}
		$total = (int) $total_result;
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page = min( $page, $total_pages );
		$args     = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( array( 'message' => $terms->get_error_message() ) );
		}

		$rows  = array();
		foreach ( $terms as $term ) {
			$parent = $term->parent ? get_term( $term->parent, $taxonomy ) : null;
			$rows[] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => $parent instanceof \WP_Term ? $parent->name : '',
				'count'  => (int) $term->count,
			);
		}

		wp_send_json_success( array(
			'rows'       => $rows,
			'page'       => $page,
			'total'      => $total,
			'totalPages' => $total_pages,
		) );
	}

	/** Validate and save changed term names/slugs in one bounded request. */
	public function handle_mass_update_terms(): void {
		check_ajax_referer( 'taxocsie_mass_term_editor', 'nonce' );

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$tax_obj  = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : null;
		if ( ! $tax_obj || empty( $tax_obj->cap->manage_terms ) || ! current_user_can( $tax_obj->cap->manage_terms ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit terms in this taxonomy.', 'taxonomy-csv-import-export' ) ), 403 );
		}

		$raw_rows = isset( $_POST['terms'] ) && is_string( $_POST['terms'] ) ? json_decode( wp_unslash( $_POST['terms'] ), true ) : array();
		if ( ! is_array( $raw_rows ) || count( $raw_rows ) > 100 ) {
			wp_send_json_error( array( 'message' => __( 'The update request is invalid or too large.', 'taxonomy-csv-import-export' ) ) );
		}

		$updated = array();
		$errors  = array();
		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$term_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$name    = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$slug    = isset( $row['slug'] ) ? sanitize_title( $row['slug'] ) : '';
			$term    = $term_id ? get_term( $term_id, $taxonomy ) : null;

			if ( ! $term instanceof \WP_Term || '' === $name ) {
				$errors[ $term_id ] = __( 'A valid term and non-empty name are required.', 'taxonomy-csv-import-export' );
				continue;
			}

			$result = wp_update_term( $term_id, $taxonomy, array(
				'name' => $name,
				'slug' => '' !== $slug ? $slug : sanitize_title( $name ),
			) );
			if ( is_wp_error( $result ) ) {
				$errors[ $term_id ] = $result->get_error_message();
				continue;
			}

			$updated[] = $term_id;
		}

		wp_send_json_success( array(
			'updated' => $updated,
			'errors'  => $errors,
			'message' => sprintf(
				/* translators: %d: number of updated terms. */
				_n( '%d term updated.', '%d terms updated.', count( $updated ), 'taxonomy-csv-import-export' ),
				count( $updated )
			),
		) );
	}

	/**
	 * Remove third-party notices from every WP Taxonomy Manager screen.
	 *
	 * Matching the page slug instead of hard-coding screen IDs also covers
	 * Premium, Settings, and future submenu pages. Other WordPress screens are
	 * intentionally left untouched.
	 *
	 * @return void
	 */
	public function taxocsie_admin_notice_remove() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$slug = defined( 'TAXOCSIE_PLUGIN_SLUG' ) ? TAXOCSIE_PLUGIN_SLUG : 'taxonomy-csv-import-export';

		if ( $slug !== $page && 0 !== strpos( $page, $slug . '-' ) ) {
			return;
		}

		if ( class_exists( 'TaxoCSIE\\Settings' ) && ! Settings::should_hide_third_party_notices() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/**
	 * AJAX: Quick generate demo terms
	 */
	public function handle_ajax_demo_generate() {
		// Check nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'taxocsie_demo_ajax')) {
			wp_send_json_error(array('message' => esc_html__('Security check failed. Please refresh the page and try again.', 'taxonomy-csv-import-export')));
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'taxonomy-csv-import-export')));
		}

		$taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';
		$count = isset($_POST['count']) ? intval($_POST['count']) : 10;
		$structure = isset($_POST['structure']) ? sanitize_text_field(wp_unslash($_POST['structure'])) : 'flat';
		$name_format = isset($_POST['name_format']) ? sanitize_text_field(wp_unslash($_POST['name_format'])) : 'prefix';
		$add_description = isset($_POST['add_description']) && 'true' === $_POST['add_description'];
		$skip_existing = isset($_POST['skip_existing']) && 'true' === $_POST['skip_existing'];

		if ( ! $this->can_use_taxonomy( $taxonomy, 'edit_terms' ) ) {
			wp_send_json_error(array('message' => esc_html__('Invalid taxonomy selected.', 'taxonomy-csv-import-export')));
		}

		if ($count < 1 || $count > 500) {
			wp_send_json_error(array('message' => esc_html__('Please enter a number between 1 and 500.', 'taxonomy-csv-import-export')));
		}

		// Use the menu's generation method
		$menu = new Menu();
		$results = $menu->generate_demo_terms($taxonomy, $count, $structure, $name_format, $add_description, $skip_existing);

		if ($results['created'] === 0 && $results['errors'] === 0) {
			wp_send_json_error(array(
				'message' => esc_html__('No terms were created. All terms might already exist.', 'taxonomy-csv-import-export'),
				'results' => $results
			));
		}

		$message = sprintf(
			// translators: 1: Number of terms created, 2: Taxonomy name
			esc_html__('Generated %1$d terms in "%2$s".', 'taxonomy-csv-import-export'),
			$results['created'],
			$taxonomy
		);

		if ($results['skipped'] > 0) {
			$message .= ' ' . sprintf(
				// translators: 1: Number of skipped terms
				esc_html__('Skipped %1$d existing terms.', 'taxonomy-csv-import-export'),
				$results['skipped']
			);
		}

		if ($results['errors'] > 0) {
			$message .= ' ' . sprintf(
				/* translators: 1: Number of errors */
				esc_html__('%1$d errors occurred.', 'taxonomy-csv-import-export'),
				$results['errors']
			);
		}

		wp_send_json_success(array(
			'message' => $message,
			'results' => $results
		));
	}
}
