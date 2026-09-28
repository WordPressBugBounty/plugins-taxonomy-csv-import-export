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
			$sdk->add_filter( 'after_connect_url', array( __CLASS__, 'filter_after_license_activation_url' ) );
			$sdk->add_filter( 'after_network_activation_url', array( __CLASS__, 'filter_after_license_activation_url' ) );
		}
	}

	/**
	 * Send successful license activations to the same URL used by Pro badges.
	 *
	 * @param mixed $url Freemius' default post-activation URL.
	 * @return string
	 */
	public static function filter_after_license_activation_url( $url ): string {
		$upgrade_url = self::get_upgrade_url();

		if ( '' !== $upgrade_url ) {
			return $upgrade_url;
		}

		return is_string( $url ) ? $url : '';
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
	private static function has_active_license(): bool {
		$sdk = self::sdk();

		if ( null !== $sdk && is_callable( array( $sdk, 'has_active_valid_license' ) ) ) {
			try {
				return true === $sdk->has_active_valid_license();
			} catch ( \Throwable $throwable ) {
				return false;
			}
		}

		return self::can_use_premium_code();
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
	 * The page renders Freemius pricing for unlicensed sites and account details
	 * for licensed sites, so every Pro entry point has one consistent target.
	 *
	 * @return string
	 */
	public static function get_account_page_url(): string {
		return add_query_arg( 'page', self::ACCOUNT_PAGE, admin_url( 'admin.php' ) );
	}

	/**
	 * Get the internal upgrade entry URL.
	 *
	 * @return string
	 */
	public static function get_upgrade_url(): string {
		$url = self::get_account_page_url();

		/**
		 * Filters the Premium entry URL.
		 *
		 * @since 1.5.0
		 *
		 * @param string $url Account page URL.
		 */
		$url = apply_filters( 'taxocsie_premium_upgrade_url', $url );

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Register separate Settings and Account pages below the plugin menu.
	 *
	 * The Account callback dynamically renders Freemius pricing or account data.
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

		self::$account_hook = (string) add_submenu_page(
			TAXOCSIE_PLUGIN_SLUG,
			__( 'WP Taxonomy Manager Account', 'taxonomy-csv-import-export' ),
			__( 'Account', 'taxonomy-csv-import-export' ),
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
			<div class="tw-relative tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-px-6 tw-py-6 tw-shadow-soft sm:tw-px-8">
				<div class="tw-flex tw-items-start tw-gap-4">
					<div class="tw-flex tw-h-11 tw-w-11 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-brand-100 tw-text-brand-700">
						<span class="dashicons dashicons-admin-settings tw-text-[22px]" aria-hidden="true"></span>
					</div>
					<div>
						<h1 class="tw-m-0 tw-text-2xl tw-font-bold tw-tracking-tight tw-text-slate-900"><?php esc_html_e( 'Settings', 'taxonomy-csv-import-export' ); ?></h1>
						<p class="tw-mb-0 tw-mt-1.5 tw-text-sm tw-leading-6 tw-text-slate-600"><?php esc_html_e( 'Configure WP Taxonomy Manager features and import limits.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
				</div>
			</div>

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

		if ( self::has_active_license() && is_callable( array( $sdk, '_account_page_load' ) ) ) {
			$sdk->_account_page_load();
			return;
		}

		if ( function_exists( 'fs_enqueue_local_style' ) ) {
			fs_enqueue_local_style( 'fs_common', '/admin/common.css' );
			fs_enqueue_local_style( 'fs_checkout', '/admin/checkout.css' );
		}
	}

	/** Render native Freemius pricing or account details based on license state. */
	public static function render_account_page(): void {
		self::guard_admin_page();

		$sdk = self::sdk();
		if ( null === $sdk ) {
			echo '<div class="wrap"><div class="notice notice-warning"><p>' . esc_html__( 'Freemius is not configured, so account information is unavailable.', 'taxonomy-csv-import-export' ) . '</p></div></div>';
			return;
		}

		if ( self::has_active_license() && is_callable( array( $sdk, '_account_page_render' ) ) ) {
			$sdk->_account_page_render();
			return;
		}

		if ( is_callable( array( $sdk, '_pricing_page_render' ) ) ) {
			$sdk->_pricing_page_render();
			return;
		}

		echo '<div class="wrap"><div class="notice notice-warning"><p>' . esc_html__( 'Freemius pricing is currently unavailable.', 'taxonomy-csv-import-export' ) . '</p></div></div>';
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
