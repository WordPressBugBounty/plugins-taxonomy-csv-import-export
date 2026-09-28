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

	public function enqueue_taxocsie_taxonomy_scripts( $hook_suffix )
	{
		if ( ! is_string( $hook_suffix ) || false === strpos( $hook_suffix, TAXOCSIE_PLUGIN_SLUG ) ) {
			return;
		}

		wp_enqueue_script(
			'taxocsie-scripts',
			TAXOCSIE_ASSETS_URI . '/dev-js/ajax.js',
			array( 'jquery' ),
			filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/dev-js/ajax.js' ),
			true
		);
		
		// Main data object with all nonces
		wp_localize_script('taxocsie-scripts', 'taxocsieData', array(
			// Existing nonces
			'previewExportNonce'   => wp_create_nonce('taxocsie_preview_export'),
			'getTermsNonce'        => wp_create_nonce('taxocsie_get_terms'),
			'massTermEditorNonce'  => wp_create_nonce( 'taxocsie_mass_term_editor' ),
			'termOrderingNonce'    => wp_create_nonce( 'taxocsie_term_ordering' ),
			'ajaxCreateTermNonce'  => wp_create_nonce('taxocsie_ajax_create_term'),
			'saveTaxonomyNonce'    => wp_create_nonce('taxocsie_save_taxonomy'),
			'deleteTaxonomyNonce'  => wp_create_nonce('taxocsie_delete_taxonomy'),
			'deleteTaxonomiesNonce' => wp_create_nonce('taxocsie_delete_taxonomies'),
			/* translators: %d: number of selected taxonomy registrations. */
			'bulkDeleteConfirm'    => __('Delete %d selected taxonomy registrations? Existing terms will remain in the database.', 'taxonomy-csv-import-export'),
			/* translators: %d: number of removed taxonomies. */
			'bulkDeleted'          => __('%d taxonomies were removed.', 'taxonomy-csv-import-export'),
			'premiumFormatsNonce'  => wp_create_nonce( 'taxocsie_premium_data_formats' ),
			'premiumExportAction'  => 'taxocsie_premium_prepare_export',
			'premiumImportAction'  => 'taxocsie_premium_import_terms',
			
			// NEW: Demo Generator nonce
			'demoAjaxNonce'        => wp_create_nonce('taxocsie_demo_ajax'),
			'demoBulkGenerate'    => __( 'Generate +10 for selected', 'taxonomy-csv-import-export' ),
			'demoBulkLimit'       => __( 'Select up to 10 taxonomies at a time.', 'taxonomy-csv-import-export' ),
			/* translators: %d: number of selected taxonomies. */
			'demoBulkConfirm'     => __( 'Generate 10 demo terms in each of %d selected taxonomies?', 'taxonomy-csv-import-export' ),
			/* translators: 1: created terms, 2: affected taxonomies. */
			'demoBulkSuccess'     => __( 'Created %1$d demo terms across %2$d taxonomies.', 'taxonomy-csv-import-export' ),
			/* translators: 1: completed taxonomies, 2: failed taxonomies. */
			'demoBulkPartial'     => __( '%1$d taxonomies completed; %2$d could not be completed.', 'taxonomy-csv-import-export' ),
			
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
			'mappingReady'         => __('Mapping ready', 'taxonomy-csv-import-export'),
			'mappingNameRequired'  => __('Map one CSV column to Name.', 'taxonomy-csv-import-export'),
			'mappingDuplicate'     => __('Each import field can only be mapped once.', 'taxonomy-csv-import-export'),
			'headerReadFailed'     => __('The CSV header could not be read.', 'taxonomy-csv-import-export'),
			'ignoreColumn'         => __('Ignore this column', 'taxonomy-csv-import-export'),
			'fieldName'            => __('Name (required)', 'taxonomy-csv-import-export'),
			'fieldSlug'            => __('Slug', 'taxonomy-csv-import-export'),
			'fieldDescription'     => __('Description', 'taxonomy-csv-import-export'),
			'fieldParent'          => __('Parent slug', 'taxonomy-csv-import-export'),
			'fieldLink'            => __('Custom link', 'taxonomy-csv-import-export'),
			'fieldImage'           => __('Image source', 'taxonomy-csv-import-export'),
			'massChooseTaxonomy'   => __( 'Choose a taxonomy first.', 'taxonomy-csv-import-export' ),
			'massLoading'          => __( 'Loading terms...', 'taxonomy-csv-import-export' ),
			'massNoTerms'          => __( 'No matching terms were found.', 'taxonomy-csv-import-export' ),
			'massUnsavedConfirm'   => __( 'Discard the unsaved term changes?', 'taxonomy-csv-import-export' ),
			'massNoChanges'        => __( 'No term changes to save.', 'taxonomy-csv-import-export' ),
			'massSaving'           => __( 'Saving term changes...', 'taxonomy-csv-import-export' ),
			/* translators: 1: current page, 2: total pages. */
			'massPage'             => __( 'Page %1$d of %2$d', 'taxonomy-csv-import-export' ),
		));
		
		// Enqueue CSS
		wp_enqueue_style('taxocsie-style', TAXOCSIE_ASSETS_URI . '/css/menu.css', array('wp-components'), filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/css/menu.css' ), 'all');
		wp_enqueue_style('taxocsie-tailwind', TAXOCSIE_ASSETS_URI . '/css/tailwind.css', array('taxocsie-style'), filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/css/tailwind.css' ), 'all');
		
		// NEW: Enqueue dashicons for status icons
		wp_enqueue_style('dashicons');
	}
}
