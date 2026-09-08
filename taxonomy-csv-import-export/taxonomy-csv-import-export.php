<?php
/**
 * Plugin Name: Taxonomy CSV Import Export
 * Plugin URI: https://wordpress.org/plugins/taxonomy-csv-import-export
 * Description: Export and import all registered taxonomies with terms to/from CSV. Includes parent relationships.
 * Version: 1.5
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: Md.Rakib Hossen
 * Author URI: https://rlorakib.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxonomy-csv-import-export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



final class Taxonomy_CSV_Import_Export {

	private function __construct() {
		$this->define_constants();
		$this->load_dependency();
		register_activation_hook( __FILE__, array( $this, 'active' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'on_loaded_plugin' ) );
		add_action( 'taxocsie_loaded', array( $this, 'init_plugin' ) );
	}

	public function define_constants() {
		define( 'TAXOCSIE_VERSION', '1.5' );
		define( 'TAXOCSIE_DB_VERSION', '1.0' );
		define( 'TAXOCSIE_SETTINGS_NAME', 'academy_settings' );
		define( 'TAXOCSIE_PLUGIN_FILE', __FILE__ );
		define( 'TAXOCSIE_PLUGIN_SLUG', 'taxonomy-csv-import-export' );
		define( 'TAXOCSIE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		define( 'TAXOCSIE_INCLUDE_DIR_PATH', TAXOCSIE_ROOT_DIR_PATH . '/includes' );
		define( 'TAXOCSIE_ASSETS_DIR_PATH', TAXOCSIE_ROOT_DIR_PATH . '/assets' );
		define( 'TAXOCSIE_BLOCK_TEMPLATE_DIR_PATH', TAXOCSIE_ROOT_DIR_PATH . '/templates' );
		define( 'TAXOCSIE_ASSETS_URI', plugin_dir_url( __FILE__ ) . '/assets' );
	}

	public function active() {
		update_option( 'taxocsie_version', TAXOCSIE_VERSION );
		$installed = get_option( 'taxocsie_installed' );
		if ( ! $installed ) {
			update_option( 'taxocsie_installed', time() );
		}
	}

	public function on_loaded_plugin() {
		do_action( 'taxocsie_loaded' );
	}

	public function init_plugin() {
		do_action( 'taxocsie_after_init' );
		$this->dispatch_hooks();
		do_action( 'taxocsie_init' );
	}

	public static function init() {
		return new self();
	}

	public function dispatch_hooks() {
		TaxoCSIE\TaxonomyCreator::init();
		TaxoCSIE\Admin::init();
		TaxoCSIE\Menu::init();
		TaxoCSIE\Assets::init();
		TaxoCSIE\ImportPreview::init();
		TaxoCSIE\ImportProcessor::init();
		TaxoCSIE\ImportLogger::init();
	}

	public function load_dependency() {
		require_once __DIR__ . '/includes/autoload.php';
		require_once __DIR__ . '/includes/helper.php';
	}

	public function deactivate() {
	}
}

/**
 * @return Taxonomy_CSV_Import_Export
 */
function taxocsie_start() {
	return Taxonomy_CSV_Import_Export::init();
}

taxocsie_start();
