<?php
/**
 * Minimal fallback autoloader used when Composer dependencies are unavailable.
 *
 * The official distribution includes vendor/autoload.php. This fallback keeps
 * WordPress operational when a source checkout is installed without vendor;
 * vendor-backed licensing remains disabled until dependencies are restored.
 *
 * @package TaxoCSIE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class_name ) {
		$class_map = array(
			'TaxoCSIE\\Admin'                       => __DIR__ . '/admin.php',
			'TaxoCSIE\\AddonSettings'               => __DIR__ . '/class-addon-settings.php',
			'TaxoCSIE\\Assets'                      => __DIR__ . '/assets.php',
			'TaxoCSIE\\Dashboard'                   => __DIR__ . '/dashboard.php',
			'TaxoCSIE\\Freemius'                    => __DIR__ . '/freemius.php',
			'TaxoCSIE\\Helper'                      => __DIR__ . '/helper.php',
			'TaxoCSIE\\Menu'                        => __DIR__ . '/menu.php',
			'TaxoCSIE\\ReviewPrompt'                => __DIR__ . '/review-prompt.php',
			'TaxoCSIE\\Settings'                    => __DIR__ . '/settings.php',
			'TaxoCSIE\\FreemiusOptin'                => __DIR__ . '/freemius-optin.php',
			'TaxoCSIE\\UpdatesOptin'                 => __DIR__ . '/updates-optin.php',
			'TaxoCSIE\\TermCustomLinks'             => __DIR__ . '/term-custom-links.php',
			'TaxoCSIE\\TermRedirects'               => __DIR__ . '/class-term-redirects.php',
			'TaxoCSIE\\UnusedTerms'                 => __DIR__ . '/class-unused-terms.php',
			'TaxoCSIE\\TermQueryFilters'            => __DIR__ . '/term-query-filters.php',
			'TaxoCSIE\\TaxonomyCreator'             => __DIR__ . '/taxonomy-creator.php',
			'TaxoCSIE\\Modules\\RulesAutoTagging'    => __DIR__ . '/modules/ai-auto-tagging/ai-auto-tagging.php',
			'TaxoCSIE\\Premium\\Bootstrap'          => dirname( __DIR__ ) . '/premium/class-bootstrap.php',
			'TaxoCSIE\\Premium\\AIAutoTagging'     => dirname( __DIR__ ) . '/premium/class-ai-auto-tagging.php',
			'TaxoCSIE\\Premium\\PremiumAccess'      => dirname( __DIR__ ) . '/premium/class-premium-access.php',
			'TaxoCSIE\\Premium\\DataFormats'        => dirname( __DIR__ ) . '/premium/class-data-formats.php',
			'TaxoCSIE\\Premium\\TermImageManager'   => dirname( __DIR__ ) . '/premium/class-term-image-manager.php',
			'TaxoCSIE\\Premium\\TermImageUI'        => dirname( __DIR__ ) . '/premium/class-term-image-ui.php',
			'TaxoCSIE\\Premium\\TermOrdering'       => dirname( __DIR__ ) . '/premium/class-term-ordering.php',
			'TaxoCSIE\\Premium\\DuplicateTerms'     => dirname( __DIR__ ) . '/premium/class-duplicate-terms.php',
			'TaxoCSIE\\Premium\\RemoteSync'         => dirname( __DIR__ ) . '/premium/class-remote-sync.php',
			'TaxoCSIE\\Premium\\TermMetaFields'     => dirname( __DIR__ ) . '/premium/class-term-meta-fields.php',
			'TaxoCSIE\\Premium\\WooCommerceFields'  => dirname( __DIR__ ) . '/premium/class-woocommerce-fields.php',
			'TaxoCSIE\\Premium\\Admin\\AdvancedImportPage' => dirname( __DIR__ ) . '/premium/admin/class-advanced-import-page.php',
			'TaxoCSIE\\Premium\\Admin\\DryRunPreview'      => dirname( __DIR__ ) . '/premium/admin/class-dry-run-preview.php',
			'TaxoCSIE\\Premium\\Admin\\ImportMode'         => dirname( __DIR__ ) . '/premium/admin/class-import-mode.php',
			'TaxoCSIE\\Premium\\Admin\\ImportPresets'      => dirname( __DIR__ ) . '/premium/admin/class-import-presets.php',
			'TaxoCSIE\\Premium\\Admin\\UpdateFields'       => dirname( __DIR__ ) . '/premium/admin/class-update-fields.php',
			'TaxoCSIE\\Premium\\Admin\\BatchImport'        => dirname( __DIR__ ) . '/premium/admin/class-batch-import.php',
			'TaxoCSIE\\Premium\\Admin\\TermMetaTransfer'   => dirname( __DIR__ ) . '/premium/admin/class-term-meta-transfer.php',
			'TaxoCSIE\\Premium\\Admin\\TermMaintenance'    => dirname( __DIR__ ) . '/premium/admin/class-term-maintenance.php',
		);

		if ( isset( $class_map[ $class_name ] ) && is_readable( $class_map[ $class_name ] ) ) {
			require_once $class_map[ $class_name ];
		}
	}
);
