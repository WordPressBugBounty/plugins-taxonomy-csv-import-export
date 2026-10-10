<?php

/**
 * Plugin Name: WP Taxonomy Manager
 * Plugin URI: https://wp-taxonomy-manager.com/
 * Description: Import, export, and manage WordPress taxonomies and terms with CSV. Supports hierarchical terms and custom taxonomies.
 * Version: 2.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Md. Rakib Hossen
 * Author URI: https://rlorakib.wp-taxonomy-manager.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxonomy-csv-import-export
 * Domain Path: /languages
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !defined( 'TAXOCSIE_VENDOR_AVAILABLE' ) ) {
    define( 'TAXOCSIE_VENDOR_AVAILABLE', is_readable( __DIR__ . '/vendor/autoload.php' ) );
}
if ( function_exists( 'wtmp_fs' ) ) {
    wtmp_fs()->set_basename( false, __FILE__ );
} else {
    if ( TAXOCSIE_VENDOR_AVAILABLE ) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    // Action Scheduler must be registered before plugins_loaded. Its loader
    // selects the newest copy when another plugin also bundles the library.
    $action_scheduler = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
    if ( is_readable( $action_scheduler ) ) {
        require_once $action_scheduler;
    }
    // Keep plugin classes loadable when Composer's generated classmap is stale.
    require_once __DIR__ . '/includes/autoload.php';
    /**
     * Do not remove this conditional. Freemius uses it to distinguish the
     * free and paid plugin builds and deactivate the other build safely.
     */
    if ( !function_exists( 'wtmp_fs' ) ) {
        /**
         * Return the configured Freemius SDK instance.
         *
         * @return Freemius|null
         */
        function wtmp_fs() {
            global $wtmp_fs;
            if ( isset( $wtmp_fs ) && is_object( $wtmp_fs ) ) {
                return $wtmp_fs;
            }
            if ( !function_exists( 'fs_dynamic_init' ) ) {
                $sdk_file = __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';
                if ( !is_readable( $sdk_file ) ) {
                    $sdk_file = __DIR__ . '/vendor/freemius/start.php';
                }
                if ( !is_readable( $sdk_file ) ) {
                    return null;
                }
                require_once $sdk_file;
            }
            if ( !function_exists( 'fs_dynamic_init' ) ) {
                return null;
            }
            $wtmp_fs = fs_dynamic_init( array(
                'id'               => '39540',
                'slug'             => 'taxonomy-csv-import-export',
                'premium_slug'     => 'wp-taxonomy-manager-premium',
                'type'             => 'plugin',
                'public_key'       => 'pk_eb11df3e2835ebf13cc27b97b07a3',
                'is_premium'       => false,
                'anonymous_mode'   => true,
                'premium_suffix'   => 'Pro',
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'is_org_compliant' => true,
                'menu'             => array(
                    'slug'    => 'taxonomy-csv-import-export',
                    'pricing' => false,
                    'account' => false,
                    'contact' => false,
                    'support' => false,
                    'addons'  => false,
                ),
                'is_live'          => true,
            ) );
            // Keep first activation inside WordPress. License management remains
            // available from the plugin's Account page when the user chooses it.
            if ( is_object( $wtmp_fs ) && is_callable( array($wtmp_fs, 'add_filter') ) ) {
                $wtmp_fs->add_filter( 'redirect_on_activation', '__return_false' );
            }
            return ( is_object( $wtmp_fs ) ? $wtmp_fs : null );
        }

        // Freemius must initialize during the main plugin file execution.
        wtmp_fs();
        do_action( 'wtmp_fs_loaded' );
    }
    if ( !function_exists( 'taxocsie_fs' ) ) {
        /**
         * Backward-compatible accessor used by the plugin's licensing adapter.
         *
         * @return Freemius|null
         */
        function taxocsie_fs() {
            return ( function_exists( 'wtmp_fs' ) ? wtmp_fs() : null );
        }

    }
    final class Taxonomy_CSV_Import_Export {
        private function __construct() {
            $this->define_constants();
            register_activation_hook( __FILE__, array($this, 'active') );
            add_action( 'plugins_loaded', array($this, 'on_loaded_plugin') );
            add_action( 'taxocsie_loaded', array($this, 'init_plugin') );
            add_action( 'taxocsie_init', array($this, 'init_premium_module') );
            if ( !TAXOCSIE_VENDOR_AVAILABLE ) {
                add_action( 'admin_notices', array($this, 'render_missing_vendor_notice') );
            }
        }

        public function define_constants() {
            define( 'TAXOCSIE_VERSION', '2.1.0' );
            define( 'TAXOCSIE_PLUGIN_FILE', __FILE__ );
            define( 'TAXOCSIE_PLUGIN_SLUG', 'taxonomy-csv-import-export' );
            define( 'TAXOCSIE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
            define( 'TAXOCSIE_INCLUDE_DIR_PATH', TAXOCSIE_ROOT_DIR_PATH . '/includes' );
            define( 'TAXOCSIE_ASSETS_DIR_PATH', TAXOCSIE_ROOT_DIR_PATH . '/assets' );
            define( 'TAXOCSIE_ASSETS_URI', plugin_dir_url( __FILE__ ) . '/assets' );
        }

        public function active() {
            update_option( 'taxocsie_version', TAXOCSIE_VERSION );
            $installed = get_option( 'taxocsie_installed' );
            if ( !$installed ) {
                update_option( 'taxocsie_installed', time() );
            }
            $sdk = wtmp_fs();
            if ( !is_object( $sdk ) ) {
                return;
            }
            if ( is_callable( array($sdk, 'is_registered') ) && is_callable( array($sdk, 'is_anonymous') ) && is_callable( array($sdk, 'skip_connection') ) && !$sdk->is_registered() && !$sdk->is_anonymous() ) {
                $sdk->skip_connection();
            }
            if ( is_callable( array($sdk, 'get_storage') ) ) {
                $storage = $sdk->get_storage();
                if ( is_object( $storage ) ) {
                    $storage->require_license_activation = false;
                }
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
            TaxoCSIE\Settings::init();
            TaxoCSIE\TermCustomLinks::init();
            TaxoCSIE\TermRedirects::init();
            TaxoCSIE\UnusedTerms::init();
            TaxoCSIE\Admin::init();
            TaxoCSIE\Menu::init();
            TaxoCSIE\Assets::init();
            TaxoCSIE\Freemius::init();
            TaxoCSIE\ReviewPrompt::init();
            TaxoCSIE\FreemiusOptin::init();
            TaxoCSIE\Modules\RulesAutoTagging::init();
        }

        /**
         * Load optional Premium modules without coupling the free plugin to them.
         */
        public function init_premium_module() {
            if ( is_readable( __DIR__ . '/premium/class-bootstrap.php' ) && class_exists( 'TaxoCSIE\\Premium\\Bootstrap' ) ) {
                TaxoCSIE\Premium\Bootstrap::init();
            }
        }

        /**
         * Explain how to restore the optional licensing dependency.
         *
         * @return void
         */
        public function render_missing_vendor_notice() {
            if ( !current_user_can( 'activate_plugins' ) ) {
                return;
            }
            ?>
		<div class="notice notice-warning">
			<p>
				<?php 
            esc_html_e( 'WP Taxonomy Manager is running without its Composer dependencies. Reinstall the official plugin package or run composer install in the plugin directory to enable licensing and Premium access.', 'taxonomy-csv-import-export' );
            ?>
			</p>
		</div>
		<?php 
        }

    }

    /**
     * @return Taxonomy_CSV_Import_Export
     */
    function taxocsie_start() {
        return Taxonomy_CSV_Import_Export::init();
    }

    taxocsie_start();
}