<?php

namespace TaxoCSIE;

if (!defined('ABSPATH')) {
	exit;
}


class Assets
{
	public static function init()
	{
		$self = new self();
		add_action('admin_enqueue_scripts', array($self, 'enqueue_taxocsie_taxonomy_scripts'));
	}

	public function enqueue_taxocsie_taxonomy_scripts()
	{
		// Get current screen to only load on our pages
		$screen = get_current_screen();
		$is_plugin_page = false;
		
		if ($screen && isset($screen->id)) {
			$plugin_pages = array(
				'toplevel_page_taxonomy-csv-import-export',
				'import-export_page_taxonomy-csv-import-export-import',
				'import-export_page_taxonomy-csv-import-export-users',
				'import-export_page_taxonomy-csv-import-export-create',
				'import-export_page_taxonomy-csv-import-export-taxonomy'
			);
			$is_plugin_page = in_array($screen->id, $plugin_pages);
		}

		// Enqueue scripts for all admin pages
		wp_enqueue_script('taxocsie-scripts', TAXOCSIE_ASSETS_URI . '/dev-js/ajax.js', array('jquery'), TAXOCSIE_VERSION, true);
		
		// Main data object with all nonces
		wp_localize_script('taxocsie-scripts', 'taxocsieData', array(
			// Existing nonces
			'previewExportNonce'   => wp_create_nonce('taxocsie_preview_export'),
			'getTermsNonce'        => wp_create_nonce('taxocsie_get_terms'),
			'ajaxCreateTermNonce'  => wp_create_nonce('taxocsie_ajax_create_term'),
			'saveTaxonomyNonce'    => wp_create_nonce('taxocsie_save_taxonomy'),
			'deleteTaxonomyNonce'  => wp_create_nonce('taxocsie_delete_taxonomy'),
			
			// NEW: Dry Run / Preview nonces
			'dryRunNonce'          => wp_create_nonce('taxocsie_dry_run'),
			'startImportNonce'     => wp_create_nonce('taxocsie_start_import'),
			'cancelPreviewNonce'   => wp_create_nonce('taxocsie_cancel_preview'),
			'downloadErrorsNonce'  => wp_create_nonce('taxocsie_download_errors'),

			// NEW: Demo Generator nonce
			'demoAjaxNonce'        => wp_create_nonce('taxocsie_demo_ajax'),
			
			// NEW: AJAX URLs
			'ajaxUrl'              => admin_url('admin-ajax.php'),
			
			// NEW: Localization strings for UI
			'analyzing'            => __('Analyzing CSV...', 'taxonomy-csv-import-export'),
			'importing'            => __('Importing...', 'taxonomy-csv-import-export'),
			'importBtn'            => __('Import CSV', 'taxonomy-csv-import-export'),
			'startImport'          => __('Start Import', 'taxonomy-csv-import-export'),
			'chooseFile'           => __('Please select a CSV file.', 'taxonomy-csv-import-export'),
			'invalidFile'          => __('Please upload a CSV file.', 'taxonomy-csv-import-export'),
			'confirmImport'        => __('Are you sure you want to start the import? This action cannot be undone.', 'taxonomy-csv-import-export'),
			'error'                => __('An error occurred. Please try again.', 'taxonomy-csv-import-export'),
			'downloadErrors'       => __('Download Error Report', 'taxonomy-csv-import-export'),
			'clearPreview'         => __('Clear preview data? This will discard all analysis results.', 'taxonomy-csv-import-export'),
			'largeFileWarning'     => __('Large file detected. Dry run mode is recommended.', 'taxonomy-csv-import-export'),
			'noDataFound'          => __('No data found in CSV file.', 'taxonomy-csv-import-export'),
			'readyToImport'        => __('rows ready to import.', 'taxonomy-csv-import-export'),
			'rowsWithErrors'       => __('rows have errors.', 'taxonomy-csv-import-export'),
			'noReadyRows'          => __('No rows are ready to import. Please fix the errors.', 'taxonomy-csv-import-export'),
			'importCompleted'      => __('Import completed!', 'taxonomy-csv-import-export'),
			'previewCleared'       => __('Preview data cleared.', 'taxonomy-csv-import-export'),
			'timeoutError'         => __('Request timed out. The file may be too large.', 'taxonomy-csv-import-export'),
			'invalidResponse'      => __('Invalid response from server. Please check your file.', 'taxonomy-csv-import-export'),
		));
		
		// Enqueue CSS
		wp_enqueue_style('taxocsie-style', TAXOCSIE_ASSETS_URI . '/css/menu.css', array('wp-components'), filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/css/menu.css' ), 'all');
		
		// NEW: Enqueue dashicons for status icons
		wp_enqueue_style('dashicons');
		
		// NEW: Add inline styles for dynamic elements if needed
		// if ($is_plugin_page) {
		// 	$this->add_inline_styles();
		// }
	}
}