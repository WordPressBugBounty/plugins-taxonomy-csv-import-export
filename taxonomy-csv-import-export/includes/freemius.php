<?php
/**
 * Freemius licensing and administration integration.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps vendor-specific licensing details out of the plugin feature classes.
 */
final class Freemius {

	/**
	 * Settings page slug.
	 */
	public const SETTINGS_PAGE = 'taxonomy-csv-import-export-settings';

	/**
	 * Account page slug.
	 */
	public const ACCOUNT_PAGE = 'taxonomy-csv-import-export-license-account';

	/** @var string Account page hook suffix. */
	private static $account_hook = '';

	/**
	 * Register administration hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );

		$sdk = self::sdk();
		if ( null !== $sdk && is_callable( array( $sdk, 'add_filter' ) ) ) {
			$sdk->add_filter( 'pricing_url', array( __CLASS__, 'filter_pricing_url' ) );
			$sdk->add_filter( 'after_connect_url', array( __CLASS__, 'filter_after_license_activation_url' ) );
			$sdk->add_filter( 'after_network_activation_url', array( __CLASS__, 'filter_after_license_activation_url' ) );
		}
	}

	/** Point Freemius upgrade tabs and notices to website pricing. */
	public static function filter_pricing_url(): string {
		return self::get_upgrade_url();
	}

	/**
	 * Return to the plugin Account page after a successful license activation.
	 *
	 * @param mixed $url Freemius' default post-activation URL.
	 * @return string
	 */
	public static function filter_after_license_activation_url( $url ): string {
		return self::get_account_page_url();
	}

	/**
	 * Return the initialized SDK instance.
	 *
	 * @return object|null
	 */
	public static function sdk() {
		if ( ! function_exists( '\\taxocsie_fs' ) ) {
			return null;
		}

		$sdk = \taxocsie_fs();

		return is_object( $sdk ) ? $sdk : null;
	}

	/**
	 * Whether product credentials are available and the SDK initialized.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return null !== self::sdk();
	}

	/** Whether the installed package is the Freemius Premium build. */
	public static function is_premium_build(): bool {
		$sdk = self::sdk();
		if ( null === $sdk || ! is_callable( array( $sdk, 'is_premium' ) ) ) {
			return false;
		}

		try {
			return true === $sdk->is_premium();
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}

	/**
	 * Whether Freemius grants access to Premium code.
	 *
	 * @return bool
	 */
	public static function can_use_premium_code(): bool {
		$sdk = self::sdk();

		if ( null === $sdk || ! is_callable( array( $sdk, 'can_use_premium_code' ) ) ) {
			return false;
		}

		try {
			return true === $sdk->can_use_premium_code();
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}

	/**
	 * Whether this installation has an active, non-expired license.
	 *
	 * @return bool
	 */
	public static function has_active_license(): bool {
		$sdk = self::sdk();

		if ( null !== $sdk && is_callable( array( $sdk, 'has_active_valid_license' ) ) ) {
			try {
				return true === $sdk->has_active_valid_license();
			} catch ( \Throwable $throwable ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Get the SDK Account page URL used for license management.
	 *
	 * @return string
	 */
	public static function get_account_url(): string {
		$sdk = self::sdk();

		if ( null === $sdk || ! is_callable( array( $sdk, 'get_account_url' ) ) ) {
			return '';
		}

		try {
			$url = $sdk->get_account_url();
		} catch ( \Throwable $throwable ) {
			return '';
		}

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Get the plugin's visible Account page URL.
	 *
	 * Premium installs show license activation or Freemius account details here.
	 *
	 * @return string
	 */
	public static function get_account_page_url(): string {
		return add_query_arg( 'page', self::ACCOUNT_PAGE, admin_url( 'admin.php' ) );
	}

	/**
	 * Get the website pricing URL used by Pro upgrade links.
	 *
	 * @return string
	 */
	public static function get_upgrade_url(): string {
		$url = 'https://wp-taxonomy-manager.com/pricing';

		/**
		 * Filters the Premium entry URL.
		 *
		 * @since 1.5.0
		 *
		 * @param string $url Website pricing URL.
		 */
		$url = apply_filters( 'taxocsie_premium_upgrade_url', $url );

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Register Settings and the appropriate Free or Premium menu entry.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_submenu_page(
			TAXOCSIE_PLUGIN_SLUG,
			__( 'WP Taxonomy Manager Settings', 'taxonomy-csv-import-export' ),
			__( 'Settings', 'taxonomy-csv-import-export' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings_page' )
		);

		if ( ! self::is_premium_build() ) {
			global $submenu;
			$submenu[ TAXOCSIE_PLUGIN_SLUG ][] = array(
				__( 'Get Pro', 'taxonomy-csv-import-export' ),
				'manage_options',
				self::get_upgrade_url(),
				__( 'Get Pro', 'taxonomy-csv-import-export' ),
			);
			return;
		}

		$has_license = self::has_active_license();
		self::$account_hook = (string) add_submenu_page(
			TAXOCSIE_PLUGIN_SLUG,
			$has_license ? __( 'WP Taxonomy Manager Account', 'taxonomy-csv-import-export' ) : __( 'Activate WP Taxonomy Manager License', 'taxonomy-csv-import-export' ),
			$has_license ? __( 'Account', 'taxonomy-csv-import-export' ) : __( 'Activate License', 'taxonomy-csv-import-export' ),
			'manage_options',
			self::ACCOUNT_PAGE,
			array( __CLASS__, 'render_account_page' )
		);

		if ( '' !== self::$account_hook ) {
			add_action( 'load-' . self::$account_hook, array( __CLASS__, 'load_account_page' ) );
		}
	}

	/**
	 * Render the plugin Settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		self::guard_admin_page();
		?>
		<div class="taxocsie-wrap taxocsie-settings-wrap tw-max-w-none tw-py-6 tw-pr-5">
			<?php if ( class_exists( 'TaxoCSIE\\Settings' ) ) : ?>
				<?php Settings::render_form(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prepare Freemius assets and handlers for the Account page.
	 *
	 * @return void
	 */
	public static function load_account_page(): void {
		self::guard_admin_page();

		$sdk = self::sdk();
		if ( null === $sdk ) {
			return;
		}

		$has_license = self::has_active_license();
		if ( $has_license && is_callable( array( $sdk, '_account_page_load' ) ) ) {
			$sdk->_account_page_load();
		} elseif ( ! $has_license ) {
			wp_enqueue_script( 'jquery' );
			wp_add_inline_script( 'jquery', 'jQuery(window).on("load", function () { jQuery(".activate-license-trigger").first().trigger("click"); });' );
			if ( function_exists( 'fs_enqueue_local_style' ) ) {
				fs_enqueue_local_style( 'fs_common', '/admin/common.css' );
			}
		}
	}

	/** Render license activation or account details for the Premium build. */
	public static function render_account_page(): void {
		self::guard_admin_page();

		$sdk = self::sdk();
		if ( null !== $sdk && self::has_active_license() && is_callable( array( $sdk, '_account_page_render' ) ) ) {
			$sdk->_account_page_render();
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Activate WP Taxonomy Manager Pro', 'taxonomy-csv-import-export' ) . '</h1>';
		echo '<p>' . esc_html__( 'Enter the license key from your purchase email to activate Pro.', 'taxonomy-csv-import-export' ) . '</p>';
		if ( null !== $sdk && is_callable( array( $sdk, '_add_license_activation_dialog_box' ) ) && is_callable( array( $sdk, 'get_unique_affix' ) ) ) {
			$trigger_class = 'activate-license-trigger ' . $sdk->get_unique_affix();
			echo '<p><button type="button" class="button button-primary ' . esc_attr( $trigger_class ) . '">' . esc_html__( 'Enter license key', 'taxonomy-csv-import-export' ) . '</button></p>';
			$sdk->_add_license_activation_dialog_box();
		} else {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'License activation is temporarily unavailable. Please check the Freemius SDK installation.', 'taxonomy-csv-import-export' ) . '</p></div>';
		}
		echo '</div>';
	}

	/**
	 * Enforce the Settings/Get Pro page capability.
	 *
	 * @return void
	 */
	private static function guard_admin_page(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_die(
			esc_html__( 'You do not have permission to access this page.', 'taxonomy-csv-import-export' ),
			esc_html__( 'Access denied', 'taxonomy-csv-import-export' ),
			array( 'response' => 403 )
		);
	}
}
