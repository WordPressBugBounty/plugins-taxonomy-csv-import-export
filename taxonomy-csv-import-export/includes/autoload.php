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
			'TaxoCSIE\\TermCustomLinks'             => __DIR__ . '/term-custom-links.php',
			'TaxoCSIE\\TermQueryFilters'            => __DIR__ . '/term-query-filters.php',
			'TaxoCSIE\\TaxonomyCreator'             => __DIR__ . '/taxonomy-creator.php',
			'TaxoCSIE\\Admin\\PreviewTable'         => __DIR__ . '/admin/preview-table.php',
			'TaxoCSIE\\Admin\\SessionHandler'       => __DIR__ . '/admin/session-handler.php',
			'TaxoCSIE\\Admin\\Validator'            => __DIR__ . '/admin/validator.php',
			'TaxoCSIE\\Premium\\Bootstrap'          => dirname( __DIR__ ) . '/premium/class-bootstrap.php',
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
		);

		if ( isset( $class_map[ $class_name ] ) && is_readable( $class_map[ $class_name ] ) ) {
			require_once $class_map[ $class_name ];
		}
	}
);
