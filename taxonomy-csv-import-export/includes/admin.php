<?php

namespace TaxoCSIE;

if (!defined('ABSPATH')) {
	exit;
}

use TaxoCSIE\Helper;
use TaxoCSIE\Admin\SessionHandler;
use TaxoCSIE\Admin\PreviewTable;
use TaxoCSIE\Admin\Validator;

class Admin
{

	public static function init()
	{
		$self = new self();
		\TaxoCSIE\Admin\Preview::init();
		add_action('admin_init', array($self, 'handle_taxocsie_import_export'));
		add_action('admin_head', array($self, 'taxocsie_admin_notice_remove'));
		add_action('wp_ajax_taxocsie_preview_export', array($self, 'handle_preview_export'));
		add_action('wp_ajax_taxocsie_get_parent_terms', array($self, 'handle_get_parent_terms'));
		add_action('wp_ajax_taxocsie_ajax_create_term', array($self, 'handle_ajax_create_term'));
		
		// NEW: Add AJAX handlers for dry run preview
		add_action('wp_ajax_taxocsie_dry_run_preview', array($self, 'handle_dry_run_preview'));
		add_action('wp_ajax_taxocsie_start_import', array($self, 'handle_start_import'));
		add_action('wp_ajax_taxocsie_download_error_report', array($self, 'handle_download_error_report'));
		add_action('wp_ajax_taxocsie_cancel_preview', array($self, 'handle_cancel_preview'));

		// NEW: Add AJAX handler for demo generator
		add_action('wp_ajax_taxocsie_ajax_demo_generate', array($self, 'handle_ajax_demo_generate'));
		
		// Load new classes
		$self->load_dry_run_classes();
	}
	
	/**
	 * Load dry run related classes
	 */
	private function load_dry_run_classes() {
		$dry_run_files = array(
			'validator' => TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/validator.php',
			'session-handler' => TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/session-handler.php',
			'preview-table' => TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/preview-table.php',
		);
		
		foreach ($dry_run_files as $file) {
			if (file_exists($file)) {
				require_once $file;
			}
		}
	}

	public function handle_taxocsie_import_export()
	{
		if ( isset( $_POST['taxonomy_export_csv'] ) ) {
			$this->taxocsie_export_csv();
		} elseif ( isset( $_POST['taxonomy_import_csv'] ) && isset( $_FILES['csv_file'] ) ) {
			// Check if dry run is enabled.
			$dry_run = isset( $_POST['tcie_dry_run'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['tcie_dry_run'] ) );
			if ( $dry_run ) {
				$this->handle_dry_run_import();
			} else {
				$this->taxocsie_import_csv();
			}
		} elseif ( isset( $_GET['taxocsie_export_users'] ) ) {
			$this->taxocsie_export_users();
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
		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || false === $csv_upload ) {
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
		wp_safe_redirect(admin_url('admin.php?page=taxonomy-csv-import-export&preview=1'));
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
		if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
			wp_die('Invalid taxonomy.');
		}

		$terms = get_terms(
			array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'orderby' => 'name',
				'order' => 'ASC',
			)
		);

		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="' . $taxonomy . '-terms.csv"');

		$output = fopen('php://output', 'w');
		fputcsv($output, array('name', 'slug', 'description', 'parent_slug'), ',', '"', '');

		foreach ($terms as $term) {
			$parent_term = $term->parent ? get_term($term->parent, $taxonomy) : null;
			$parent_slug = $parent_term ? $parent_term->slug : '';
			fputcsv($output, array($term->name, $term->slug, $term->description, $parent_slug), ',', '"', '');
		}
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

		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || false === $csv_upload ) {
			wp_die( 'Invalid import request.' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$csv = array_map(
			static function ( $line ) {
				return str_getcsv( $line, ',', '"', '' );
			},
			file( $csv_upload ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_file
		);

		// Remove header.
		unset( $csv[0] );

		$imported = array();
		$skipped  = array();

		foreach ( $csv as $row ) {
			if ( count( $row ) < 4 ) {
				continue;
			}

			$name         = sanitize_text_field( $row[0] );
			$slug         = sanitize_title( $row[1] );
			$description  = sanitize_text_field( $row[2] );
			$parent_slug  = sanitize_title( $row[3] );
			$parent_id    = 0;

			if ( empty( $name ) ) {
				continue;
			}

			/*
			* Check if term already exists by name.
			*/
			$existing_term = term_exists( $name, $taxonomy );

			if ( $existing_term ) {
				$skipped[] = $row;
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
					$skipped[] = $row;
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
				$skipped[] = $row;
				continue;
			}

			$imported[] = $row;
		}

		/*
		* Store only successfully imported rows.
		*/
		set_transient(
			'taxoscie_csv_data',
			$imported,
			60
		);

		set_transient(
			'taxoscie_csv_skipped',
			$skipped,
			60
		);

		wp_safe_redirect(
			admin_url(
				'admin.php?page=taxonomy-csv-import-export&imported=1'
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
		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
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
		
		// Load validator
		if (!class_exists('TCIE_Validator')) {
			require_once TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/validator.php';
		}
		
		// Validate rows
		$validator = new Validator();
		$validated_rows = $validator->validate_rows($csv_data, $taxonomy);
		
		// Generate preview
		$preview_data = $this->generate_preview_data($validated_rows);
		
		// Store in session
		if (!class_exists('TCIE_Session_Handler')) {
			require_once TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/session-handler.php';
		}
		
		$session = SessionHandler::get_instance();
		$session->save_preview_data($preview_data);
		
		// Render preview table
		if (!class_exists('TCIE_Preview_Table')) {
			require_once TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/preview-table.php';
		}
		
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
		if (!class_exists('SessionHandler')) {
			require_once TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/session-handler.php';
		}
		
		$session = SessionHandler::get_instance();
		$preview_data = $session->get_preview_data();
		
		if (empty($preview_data)) {
			wp_send_json_error(__('No preview data found. Please analyze the CSV first.', 'taxonomy-csv-import-export'));
		}
		
		// Perform import
		$results = $this->perform_import_from_preview($preview_data, $import_mode);
		
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
		
		if (!class_exists('TCIE_Session_Handler')) {
			require_once TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/session-handler.php';
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
				
				fputcsv($output, array(
					$row['row_number'],
					$row['name'],
					$row['slug'],
					$row['status'],
					implode('; ', $error_messages),
					implode('; ', $suggestions)
				));
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
		
		if (!class_exists('SessionHandler')) {
			require_once TAXOCSIE_INCLUDE_DIR_PATH . 'includes/admin/session-handler.php';
		}
		
		$session = SessionHandler::get_instance();
		$session->clear_preview_data();
		
		wp_send_json_success();
	}

	public function taxocsie_import_users() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if (
			! isset( $_POST['taxocsie_nonce'] ) ||
			! wp_verify_nonce( $_POST['taxocsie_nonce'], 'taxocsie_import_users_nonce' )
		) {
			wp_safe_redirect( admin_url( 'admin.php?page=taxonomy-csv-import-export&import=error' ) );
			exit;
		}

		$file = $this->validate_csv_upload( 'taxocsie_import_file' );
		if ( false === $file ) {
			wp_safe_redirect( admin_url( 'admin.php?page=taxonomy-csv-import-export-users&import=error' ) );
			exit;
		}

		Helper::import_users( $file );

		wp_safe_redirect( admin_url( 'admin.php?page=taxonomy-csv-import-export-users&user-imported=success' ) );
		exit;
	}

	public function taxocsie_export_users() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$selected_roles = isset( $_GET['roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_GET['roles'] ) ) : [];

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
		$parent      = absint( $_POST['term_parent'] ?? 0 );

		$base = admin_url( 'admin.php?page=taxonomy-csv-import-export-create' );

		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_safe_redirect( $base . '&create_error=' . rawurlencode( 'Invalid taxonomy selected.' ) );
			exit;
		}

		if ( empty( $name ) ) {
			wp_safe_redirect( $base . '&create_error=' . rawurlencode( 'Term name is required.' ) );
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

		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( esc_html__( 'Invalid taxonomy.', 'taxonomy-csv-import-export' ) );
		}

		if ( empty( $name ) ) {
			wp_send_json_error( esc_html__( 'Term name is required.', 'taxonomy-csv-import-export' ) );
		}

		$result = wp_insert_term( $name, $taxonomy, array( 'description' => $description ) );

		if ( is_wp_error( $result ) ) {
			//phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			wp_send_json_error( esc_html__( $result->get_error_message(), 'taxonomy-csv-import-export' ) );
		}

		$term = get_term( $result['term_id'], $taxonomy );
		wp_send_json_success( array(
			'term_id' => $result['term_id'],
			'name'    => $term->name,
			'slug'    => $term->slug,
		) );
	}

	public function handle_get_parent_terms() {
		check_ajax_referer( 'taxocsie_get_terms', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
		}

		$taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ?? '' ) );

		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
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

		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( esc_html__( 'Invalid taxonomy.', 'taxonomy-csv-import-export' ) );
		}

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( esc_html__( 'Failed to fetch terms.', 'taxonomy-csv-import-export' ) );
		}

		$rows = array();
		foreach ( $terms as $term ) {
			$parent_term = $term->parent ? get_term( $term->parent, $taxonomy ) : null;
			$rows[] = array(
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent_slug' => ( $parent_term && ! is_wp_error( $parent_term ) ) ? $parent_term->slug : '',
			);
		}

		wp_send_json_success( $rows );
	}

	// Check if we are on your custom menu page
	public function taxocsie_admin_notice_remove()
	{
		$screen = get_current_screen();
		if ( isset( $screen->id ) && (
			$screen->id === 'toplevel_page_taxonomy-csv-import-export' ||
			$screen->id === 'import-export_page_taxonomy-csv-import-export-import' ||
			$screen->id === 'import-export_page_taxonomy-csv-import-export-users' ||
			$screen->id === 'import-export_page_taxonomy-csv-import-export-create' ||
			$screen->id === 'import-export_page_taxonomy-csv-import-export-taxonomy'
		) ) {

			// Remove default WP and plugin notices
			remove_all_actions('admin_notices');
			remove_all_actions('all_admin_notices');
		}
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

		if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
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