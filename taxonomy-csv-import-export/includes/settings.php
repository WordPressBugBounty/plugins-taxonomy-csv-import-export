<?php
/**
 * Plugin feature settings.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers, sanitizes, and exposes operational plugin settings.
 */
final class Settings {

	/** Stored option name. */
	public const OPTION_NAME = 'taxocsie_settings';

	/** Settings API group. */
	public const OPTION_GROUP = 'taxocsie_feature_settings';

	/** Settings screen slug. */
	public const PAGE = 'taxonomy-csv-import-export-settings';

	/** Register settings and runtime filters. */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'taxocsie_premium_dry_run_max_file_size', array( __CLASS__, 'filter_dry_run_max_file_size' ) );
		add_filter( 'taxocsie_premium_dry_run_max_rows', array( __CLASS__, 'filter_dry_run_max_rows' ) );
	}

	/**
	 * Default feature settings.
	 *
	 * @return array<string,int>
	 */
	public static function defaults(): array {
		return array(
			'dry_run_max_file_size_mb' => 5,
			'dry_run_max_rows'         => 5000,
			'hide_third_party_notices' => 1,
			'enable_custom_term_links' => 1,
			'remote_sync_history_limit' => 30,
		);
	}

	/**
	 * Return all feature settings merged with defaults.
	 *
	 * @return array<string,int>
	 */
	public static function get_all(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Return one feature setting.
	 *
	 * @param string $key Setting key.
	 * @return int|null
	 */
	public static function get( string $key ) {
		$settings = self::get_all();

		return isset( $settings[ $key ] ) ? (int) $settings[ $key ] : null;
	}

	/** Whether plugin pages should suppress external notices. */
	public static function should_hide_third_party_notices(): bool {
		return 1 === self::get( 'hide_third_party_notices' );
	}

	/** Whether custom taxonomy term links should replace generated archive links. */
	public static function custom_term_links_enabled(): bool {
		return 1 === self::get( 'enable_custom_term_links' );
	}

	/** Register Settings API fields. */
	public static function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'taxocsie_import_safety',
			__( 'Dry import preview', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_import_section' ),
			self::PAGE
		);

		add_settings_field(
			'dry_run_max_file_size_mb',
			__( 'Maximum CSV size', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_file_size_field' ),
			self::PAGE,
			'taxocsie_import_safety'
		);

		add_settings_field(
			'dry_run_max_rows',
			__( 'Maximum preview rows', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_max_rows_field' ),
			self::PAGE,
			'taxocsie_import_safety'
		);

		add_settings_section(
			'taxocsie_admin_interface',
			__( 'Admin interface', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_interface_section' ),
			self::PAGE
		);

		add_settings_field(
			'hide_third_party_notices',
			__( 'Admin notices', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_notices_field' ),
			self::PAGE,
			'taxocsie_admin_interface'
		);

		add_settings_field(
			'enable_custom_term_links',
			__( 'Custom term links', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_custom_links_field' ),
			self::PAGE,
			'taxocsie_admin_interface'
		);

		add_settings_section(
			'taxocsie_remote_sync',
			__( 'Remote CSV Sync', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_remote_sync_section' ),
			self::PAGE
		);

		add_settings_field(
			'remote_sync_history_limit',
			__( 'Run history', 'taxonomy-csv-import-export' ),
			array( __CLASS__, 'render_remote_sync_history_field' ),
			self::PAGE,
			'taxocsie_remote_sync'
		);
	}

	/**
	 * Sanitize the complete option array.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string,int>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$file_size = isset( $input['dry_run_max_file_size_mb'] ) ? absint( $input['dry_run_max_file_size_mb'] ) : 5;
		$max_rows  = isset( $input['dry_run_max_rows'] ) ? absint( $input['dry_run_max_rows'] ) : 5000;
		$history_limit = isset( $input['remote_sync_history_limit'] ) ? absint( $input['remote_sync_history_limit'] ) : 30;

		return array(
			'dry_run_max_file_size_mb' => max( 1, min( 100, $file_size ) ),
			'dry_run_max_rows'         => max( 100, min( 50000, $max_rows ) ),
			'hide_third_party_notices' => empty( $input['hide_third_party_notices'] ) ? 0 : 1,
			'enable_custom_term_links' => empty( $input['enable_custom_term_links'] ) ? 0 : 1,
			'remote_sync_history_limit' => max( 10, min( 100, $history_limit ) ),
		);
	}

	/** Render the feature settings form. */
	public static function render_form(): void {
		?>
		<section class="tw-mt-5 tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft">
			<div class="tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
				<h2 class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900"><?php esc_html_e( 'Feature settings', 'taxonomy-csv-import-export' ); ?></h2>
				<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Control import safety limits and the WP Taxonomy Manager admin experience.', 'taxonomy-csv-import-export' ); ?></p>
			</div>
			<div class="tw-px-6 tw-py-6 sm:tw-px-8">
				<?php settings_errors(); ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( self::OPTION_GROUP );
					do_settings_sections( self::PAGE );
					submit_button( __( 'Save feature settings', 'taxonomy-csv-import-export' ) );
					?>
				</form>
			</div>
		</section>
		<?php
	}

	/** Render import settings description. */
	public static function render_import_section(): void {
		echo '<p>' . esc_html__( 'Set server-side limits for the Premium dry-run analyzer. Larger limits require more memory and processing time.', 'taxonomy-csv-import-export' ) . '</p>';
	}

	/** Render interface settings description. */
	public static function render_interface_section(): void {
		echo '<p>' . esc_html__( 'Choose how WP Taxonomy Manager pages behave inside WordPress admin.', 'taxonomy-csv-import-export' ) . '</p>';
	}

	/** Render remote sync settings description. */
	public static function render_remote_sync_section(): void {
		echo '<p>' . esc_html__( 'Control how much recent sync activity is kept for the Pro Remote CSV Sync add-on.', 'taxonomy-csv-import-export' ) . '</p>';
	}

	/** Render the number of recent remote sync runs to keep. */
	public static function render_remote_sync_history_field(): void {
		$value = self::get( 'remote_sync_history_limit' );
		?>
		<input class="small-text" type="number" min="10" max="100" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[remote_sync_history_limit]" value="<?php echo esc_attr( (string) $value ); ?>">
		<span><?php esc_html_e( 'runs', 'taxonomy-csv-import-export' ); ?></span>
		<p class="description"><?php esc_html_e( 'Keep the latest 10–100 run results (default: 30). A new limit takes effect on the next sync run.', 'taxonomy-csv-import-export' ); ?></p>
		<?php
	}

	/** Render maximum file size field. */
	public static function render_file_size_field(): void {
		$value = self::get( 'dry_run_max_file_size_mb' );
		?>
		<input class="small-text" type="number" min="1" max="100" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[dry_run_max_file_size_mb]" value="<?php echo esc_attr( (string) $value ); ?>">
		<span><?php esc_html_e( 'MB', 'taxonomy-csv-import-export' ); ?></span>
		<p class="description"><?php esc_html_e( 'Allowed range: 1–100 MB. The web server upload limit may be lower.', 'taxonomy-csv-import-export' ); ?></p>
		<?php
	}

	/** Render maximum preview rows field. */
	public static function render_max_rows_field(): void {
		$value = self::get( 'dry_run_max_rows' );
		?>
		<input class="small-text" type="number" min="100" max="50000" step="100" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[dry_run_max_rows]" value="<?php echo esc_attr( (string) $value ); ?>">
		<p class="description"><?php esc_html_e( 'Allowed range: 100–50,000 CSV data rows.', 'taxonomy-csv-import-export' ); ?></p>
		<?php
	}

	/** Render notice suppression field. */
	public static function render_notices_field(): void {
		$checked = self::should_hide_third_party_notices();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_third_party_notices]" value="1" <?php checked( $checked ); ?>>
			<?php esc_html_e( 'Hide third-party notices on WP Taxonomy Manager pages', 'taxonomy-csv-import-export' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Notices remain visible everywhere else in WordPress admin.', 'taxonomy-csv-import-export' ); ?></p>
		<?php
	}

	/** Render the custom taxonomy term link toggle. */
	public static function render_custom_links_field(): void {
		$checked = self::custom_term_links_enabled();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_custom_term_links]" value="1" <?php checked( $checked ); ?>>
			<?php esc_html_e( 'Use each term\'s custom URL when WordPress generates its term link', 'taxonomy-csv-import-export' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Turning this off preserves saved URLs but restores normal taxonomy archive links.', 'taxonomy-csv-import-export' ); ?></p>
		<?php
	}

	/** Apply the configured dry-run file-size limit. */
	public static function filter_dry_run_max_file_size( $default ): int {
		$megabytes = self::get( 'dry_run_max_file_size_mb' );

		return max( MB_IN_BYTES, (int) $megabytes * MB_IN_BYTES );
	}

	/** Apply the configured dry-run row limit. */
	public static function filter_dry_run_max_rows( $default ): int {
		return max( 100, (int) self::get( 'dry_run_max_rows' ) );
	}
}
