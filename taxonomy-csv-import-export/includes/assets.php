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
		add_action('admin_enqueue_scripts', array($self, 'enqueue_table_styles'), 999);
	}

	/** Load the shared table finish after page-specific admin styles. */
	public function enqueue_table_styles( $hook_suffix )
	{
		if ( ! is_string( $hook_suffix ) || false === strpos( $hook_suffix, TAXOCSIE_PLUGIN_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'taxocsie-table-ui',
			TAXOCSIE_ASSETS_URI . '/css/table-ui.css',
			array( 'taxocsie-style' ),
			filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/css/table-ui.css' ),
			'all'
		);
	}

	public function enqueue_taxocsie_taxonomy_scripts( $hook_suffix )
	{
		if ( ! is_string( $hook_suffix ) || false === strpos( $hook_suffix, TAXOCSIE_PLUGIN_SLUG ) ) {
			return;
		}
		if ( false !== strpos( $hook_suffix, 'taxonomy-csv-import-export-unused' ) || false !== strpos( $hook_suffix, 'taxonomy-csv-import-export-redirects' ) ) {
			wp_enqueue_style( 'taxocsie-term-care', TAXOCSIE_ASSETS_URI . '/css/term-care.css', array(), filemtime( TAXOCSIE_ASSETS_DIR_PATH . '/css/term-care.css' ) );
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
			'importing'            => __('Importing...', 'taxonomy-csv-import-export'),
			'error'                => __('An error occurred. Please try again.', 'taxonomy-csv-import-export'),
			'largeFileWarning'     => __('Large files may take longer to import.', 'taxonomy-csv-import-export'),
			'mappingReady'         => __('Mapping ready', 'taxonomy-csv-import-export'),
			'mappingReading'       => __('Reading CSV columns. Please wait a moment.', 'taxonomy-csv-import-export'),
			'mappingNameRequired'  => __('Map one CSV column to Name.', 'taxonomy-csv-import-export'),
			'mappingDuplicate'     => __('Each import field can only be mapped once.', 'taxonomy-csv-import-export'),
			'headerReadFailed'     => __('The CSV header could not be read.', 'taxonomy-csv-import-export'),
			'chooseDestination'    => __('Select a destination category, tag or taxonomy to continue.', 'taxonomy-csv-import-export'),
			'chooseImportFile'     => __('Choose an import file to continue.', 'taxonomy-csv-import-export'),
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
