<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TaxoCSIE\Helper;
use TaxoCSIE\Premium\DataFormats;

class Menu {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'add_taxocsie_menu_page' ) );
		add_action( 'admin_menu', array( $self, 'add_taxocsie_addons_menu_page' ), 20 );
		add_action( 'admin_head', array( $self, 'hide_create_sidebar_item' ) );
		add_action( 'in_admin_header', array( $self, 'render_admin_navigation' ) );
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue_navigation_assets' ), 1000 );
		add_filter( 'admin_body_class', array( $self, 'add_admin_body_class' ) );
		add_filter( 'submenu_file', array( $self, 'highlight_all_terms_for_create' ) );
		add_filter( 'admin_footer_text', '__return_empty_string', 11 );
		add_action( 'admin_post_taxocsie_update_term', array( $self, 'handle_update_term' ) );
		add_action( 'admin_post_taxocsie_delete_term', array( $self, 'handle_delete_term' ) );
		add_action( 'admin_post_taxocsie_bulk_delete_terms', array( $self, 'handle_bulk_delete_terms' ) );
		add_action( 'admin_post_taxocsie_toggle_addon', array( $self, 'handle_toggle_addon' ) );
		add_action( 'wp_ajax_taxocsie_toggle_addon', array( $self, 'handle_toggle_addon_ajax' ) );
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue_addon_toggle_script' ) );
	}

	/**
	 * Add page-specific classes so workspace screens can manage their own scrolling.
	 */
	public function add_admin_body_class( string $classes ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( TAXOCSIE_PLUGIN_SLUG . '-create' === $current_page ) {
			$classes .= ' taxocsie-create-admin-page';
		}
		if ( TAXOCSIE_PLUGIN_SLUG . '-license-account' === $current_page ) {
			$classes .= ' taxocsie-account-admin-page';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation mode only.
		if ( isset( $_GET['taxocsie_embed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['taxocsie_embed'] ) ) ) {
			$classes .= ' taxocsie-embedded-admin';
		}
		if ( 'taxonomy-csv-import-export-demo' === $current_page ) {
			$classes .= ' taxocsie-demo-admin-page';
		}

		return $classes;
	}

	public function add_taxocsie_menu_page() {
		$icon_url   = $this->tcie_encode_svg_to_base64( TAXOCSIE_ASSETS_DIR_PATH . '/images/menu-button.svg' );
		$page_title = __( 'WP Taxonomy Manager', 'taxonomy-csv-import-export' );
		add_menu_page( $page_title, __( 'WP Taxonomy', 'taxonomy-csv-import-export' ), 'manage_options', TAXOCSIE_PLUGIN_SLUG, [ $this, 'get_taxocsie_dashboard_function' ], $icon_url, 41 );
		foreach ( Helper::taxoscie_main_menu_title() as $item_key => $item ) {
			add_submenu_page( $item['parent_slug'], $item['title'], $item['title'], $item['capability'], $item_key, [ $this, $item['callback'] ] );
		}

		// Keep WordPress's native parent mapping so the WP Taxonomy menu stays
		// open on Create Term, while the visible entry lives inside All Terms.
		global $submenu;
		if ( isset( $submenu[ TAXOCSIE_PLUGIN_SLUG ] ) ) {
			foreach ( $submenu[ TAXOCSIE_PLUGIN_SLUG ] as &$item ) {
				if ( TAXOCSIE_PLUGIN_SLUG . '-create' === $item[2] ) {
					$item[4] = trim( ( $item[4] ?? '' ) . ' taxocsie-sidebar-create-hidden' );
					break;
				}
			}
			unset( $item );
		}
	}

	/** Hide only the duplicate sidebar link; retain its registered page. */
	public function hide_create_sidebar_item(): void {
		echo '<style>#adminmenu .taxocsie-sidebar-create-hidden{display:none}</style>';
	}

	/** Keep All Terms selected while visiting its Create Term page. */
	public function highlight_all_terms_for_create( $submenu_file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation state only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return TAXOCSIE_PLUGIN_SLUG . '-create' === $page
			? TAXOCSIE_PLUGIN_SLUG . '-all-terms'
			: $submenu_file;
	}

	/** Place Add-ons after the other feature pages in the plugin submenu. */
	public function add_taxocsie_addons_menu_page(): void {
		add_submenu_page(
			TAXOCSIE_PLUGIN_SLUG,
			__( 'Add-ons', 'taxonomy-csv-import-export' ),
			__( 'Add-ons', 'taxonomy-csv-import-export' ),
			'manage_options',
			TAXOCSIE_PLUGIN_SLUG . '-addons',
			array( $this, 'get_taxocsie_addons_function' )
		);
	}

	/** Load the no-reload controls only on the Add-ons page. */
	public function enqueue_addon_toggle_script( $hook ): void {
		if ( ! is_string( $hook ) || false === strpos( $hook, TAXOCSIE_PLUGIN_SLUG . '-addons' ) ) {
			return;
		}
		$file = TAXOCSIE_ASSETS_DIR_PATH . '/js/addon-toggle.js';
		wp_enqueue_script( 'taxocsie-addon-toggle', TAXOCSIE_ASSETS_URI . '/js/addon-toggle.js', array(), (string) filemtime( $file ), true );
		wp_localize_script( 'taxocsie-addon-toggle', 'taxocsieAddonToggle', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'on' => __( 'On', 'taxonomy-csv-import-export' ),
			'off' => __( 'Off', 'taxonomy-csv-import-export' ),
			'onAction' => /* translators: %s: add-on name. */ __( 'Turn on %s', 'taxonomy-csv-import-export' ),
			'offAction' => /* translators: %s: add-on name. */ __( 'Turn off %s', 'taxonomy-csv-import-export' ),
			'saved' => __( 'Setting saved.', 'taxonomy-csv-import-export' ),
			'error' => __( 'Could not save this setting. Please try again.', 'taxonomy-csv-import-export' ),
		) );
	}

	/** Validate and save either the standard form or AJAX request. */
	private function save_addon_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to change add-ons.', 'taxonomy-csv-import-export' ), array( 'status' => 403 ) );
		}
		$key = isset( $_POST['addon'] ) && is_scalar( $_POST['addon'] ) ? sanitize_key( wp_unslash( $_POST['addon'] ) ) : '';
		if ( ! AddonSettings::is_supported( $key ) ) {
			return new \WP_Error( 'invalid_addon', __( 'Unknown add-on.', 'taxonomy-csv-import-export' ), array( 'status' => 400 ) );
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_scalar( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'taxocsie_toggle_addon_' . $key ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'This form has expired. Refresh the page and try again.', 'taxonomy-csv-import-export' ), array( 'status' => 403 ) );
		}
		$feature = AddonSettings::features()[ $key ];
		if ( ! class_exists( \TaxoCSIE\Premium\PremiumAccess::class ) || ! ( new \TaxoCSIE\Premium\PremiumAccess() )->can_use_feature( $feature ) ) {
			return new \WP_Error( 'license_required', __( 'An active Pro license is required.', 'taxonomy-csv-import-export' ), array( 'status' => 403 ) );
		}
		$value = isset( $_POST['enabled'] ) && is_scalar( $_POST['enabled'] ) ? (string) wp_unslash( $_POST['enabled'] ) : '';
		if ( ! in_array( $value, array( '0', '1' ), true ) ) {
			return new \WP_Error( 'invalid_state', __( 'Invalid add-on setting.', 'taxonomy-csv-import-export' ), array( 'status' => 400 ) );
		}
		$enabled = '1' === $value;
		if ( $enabled && 'woocommerce' === $key && ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error( 'woocommerce_required', __( 'Activate WooCommerce before turning on this add-on.', 'taxonomy-csv-import-export' ), array( 'status' => 409 ) );
		}
		if ( $enabled && 'acf' === $key && ! AddonSettings::is_acf_available() ) {
			return new \WP_Error( 'acf_required', __( 'Activate Advanced Custom Fields before turning on this add-on.', 'taxonomy-csv-import-export' ), array( 'status' => 409 ) );
		}
		if ( $enabled && 'remote_sync' === $key && ! class_exists( \TaxoCSIE\Premium\RemoteSync::class ) ) {
			return new \WP_Error( 'premium_code_missing', __( 'The Pro module is unavailable. Reinstall the Premium package.', 'taxonomy-csv-import-export' ), array( 'status' => 409 ) );
		}
		AddonSettings::set_enabled( $key, $enabled );
		if ( 'remote_sync' === $key ) {
			if ( $enabled ) {
				( new \TaxoCSIE\Premium\RemoteSync() )->ensure_schedule();
			} elseif ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'taxocsie_remote_csv_sync', array(), 'taxocsie-remote-sync' );
			}
		}
		return array( 'key' => $key, 'enabled' => $enabled );
	}

	/** Keep the normal form as a fallback when JavaScript is unavailable. */
	public function handle_toggle_addon(): void {
		$result = $this->save_addon_toggle();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) $result->get_error_data()['status'] ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-addons&updated=1' ) );
		exit;
	}

	public function handle_toggle_addon_ajax(): void {
		$result = $this->save_addon_toggle();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) $result->get_error_data()['status'] );
		}
		wp_send_json_success( $result );
	}

	/** Registered plugin pages, including menus supplied by enabled add-ons. */
	private function navigation_pages(): array {
		global $submenu;
		$pages = array();
		foreach ( $submenu[ TAXOCSIE_PLUGIN_SLUG ] ?? array() as $item ) {
			if ( ! isset( $item[0], $item[1], $item[2] ) || ! current_user_can( $item[1] ) || preg_match( '~^https?://~i', $item[2] ) ) {
				continue;
			}
			$pages[ $item[2] ] = wp_strip_all_tags( $item[0] );
		}
		return $pages;
	}

	/** Load navigation assets on every registered plugin screen. */
	public function enqueue_navigation_assets(): void {
		$sidebar_style = TAXOCSIE_ASSETS_DIR_PATH . '/css/sidebar-auto-tagging.css';
		$sidebar_script = TAXOCSIE_ASSETS_DIR_PATH . '/js/sidebar-auto-tagging.js';
		wp_enqueue_style( 'taxocsie-sidebar-auto-tagging', TAXOCSIE_ASSETS_URI . '/css/sidebar-auto-tagging.css', array(), (string) filemtime( $sidebar_style ) );
		wp_enqueue_script( 'taxocsie-sidebar-auto-tagging', TAXOCSIE_ASSETS_URI . '/js/sidebar-auto-tagging.js', array(), (string) filemtime( $sidebar_script ), true );
		wp_localize_script( 'taxocsie-sidebar-auto-tagging', 'taxocsieSidebarAutoTagging', array(
			'title' => __( 'Auto Tagging', 'taxonomy-csv-import-export' ),
			'manual' => __( 'Manually', 'taxonomy-csv-import-export' ),
			'ai' => __( 'AI Suggestions', 'taxonomy-csv-import-export' ),
			'termTools' => __( 'Term Tools', 'taxonomy-csv-import-export' ),
		) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! isset( $this->navigation_pages()[ $page ] ) ) {
			return;
		}
		$file = TAXOCSIE_ASSETS_DIR_PATH . '/css/admin-navigation.css';
		wp_enqueue_style( 'taxocsie-admin-navigation', TAXOCSIE_ASSETS_URI . '/css/admin-navigation.css', array( 'dashicons' ), (string) filemtime( $file ) );
		$script = TAXOCSIE_ASSETS_DIR_PATH . '/js/admin-navigation.js';
		wp_enqueue_script( 'taxocsie-admin-navigation', TAXOCSIE_ASSETS_URI . '/js/admin-navigation.js', array(), (string) filemtime( $script ), true );
	}

	/** Show the same navigation above every plugin page. */
	public function render_admin_navigation(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation mode only.
		if ( isset( $_GET['taxocsie_embed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['taxocsie_embed'] ) ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$pages = $this->navigation_pages();
		if ( ! isset( $pages[ $page ] ) ) {
			return;
		}

		$base = TAXOCSIE_PLUGIN_SLUG;
		$term_tools = array( 'taxocsie-term-meta-transfer', 'taxocsie-term-maintenance', $base . '-redirects', $base . '-unused', $base . '-duplicates' );
		$visible_term_tools = array_values( array_filter( $term_tools, static function ( $slug ) use ( $pages ) { return isset( $pages[ $slug ] ); } ) );
		$groups = array(
			array( 'slug' => $base, 'children' => array() ),
			array( 'slug' => $base . '-all-terms', 'children' => array( $base . '-create', 'taxonomy-csv-import-export-demo', $base . '-auto-tagging', $base . '-auto-tagging-ai', $base . '-term-fields' ) ),
			array( 'slug' => $base . '-taxonomy', 'children' => array() ),
			array( 'slug' => $base . '-manager', 'children' => array( $base . '-advanced-import', $base . '-remote-sync' ) ),
			array( 'slug' => $base . '-addons', 'children' => array() ),
			array( 'slug' => $base . '-settings', 'children' => array() ),
			array( 'slug' => $base . '-license-account', 'children' => array() ),
		);
		$used = array();
		?>
		<header class="taxocsie-admin-navigation">
			<span class="taxocsie-admin-navigation__brand" aria-hidden="true"><span class="dashicons dashicons-category"></span></span>
			<nav class="taxocsie-admin-navigation__tabs" aria-label="<?php esc_attr_e( 'WP Taxonomy Manager navigation', 'taxonomy-csv-import-export' ); ?>">
				<?php foreach ( $groups as $group ) :
					$slug = $group['slug'];
					if ( ! isset( $pages[ $slug ] ) ) {
						continue;
					}
					$used[ $slug ] = true;
					$children = array_values( array_filter( $group['children'], static function ( $child ) use ( $pages ) { return isset( $pages[ $child ] ); } ) );
					$is_active = $page === $slug || in_array( $page, $children, true );
					if ( $children ) : ?>
						<details class="taxocsie-admin-navigation__group<?php echo $is_active ? ' is-active' : ''; ?>">
							<summary class="taxocsie-admin-navigation__tab"><?php echo esc_html( $pages[ $slug ] ); ?><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
							<div class="taxocsie-admin-navigation__dropdown">
								<?php foreach ( array_merge( array( $slug ), $children ) as $child ) : $used[ $child ] = true; ?>
									<?php if ( $child === $base . '-auto-tagging' && isset( $pages[ $base . '-auto-tagging-ai' ] ) ) : ?>
										<div class="taxocsie-admin-navigation__subgroup"><a class="taxocsie-admin-navigation__subgroup-title" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $child ) ); ?>"><?php esc_html_e( 'Auto Tagging', 'taxonomy-csv-import-export' ); ?></a>
											<a class="taxocsie-admin-navigation__link<?php echo $page === $child ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $child ) ); ?>" <?php echo $page === $child ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Manually', 'taxonomy-csv-import-export' ); ?></a>
											<a class="taxocsie-admin-navigation__link<?php echo $page === $base . '-auto-tagging-ai' ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $base . '-auto-tagging-ai' ) ); ?>" <?php echo $page === $base . '-auto-tagging-ai' ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'AI Suggestions', 'taxonomy-csv-import-export' ); ?></a>
										</div>
									<?php elseif ( $child !== $base . '-auto-tagging-ai' ) : ?>
										<a class="taxocsie-admin-navigation__link<?php echo $page === $child ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $child ) ); ?>" <?php echo $page === $child ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $pages[ $child ] ); ?></a>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</details>
					<?php else : ?>
						<a class="taxocsie-admin-navigation__tab<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $pages[ $slug ] ); ?></a>
					<?php endif;
					if ( $slug === $base . '-all-terms' && $visible_term_tools ) : ?>
						<details class="taxocsie-admin-navigation__group<?php echo in_array( $page, $visible_term_tools, true ) ? ' is-active' : ''; ?>">
							<summary class="taxocsie-admin-navigation__tab"><?php esc_html_e( 'Term Tools', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
							<div class="taxocsie-admin-navigation__dropdown">
								<?php foreach ( $visible_term_tools as $tool_slug ) : $used[ $tool_slug ] = true; ?>
									<a class="taxocsie-admin-navigation__link<?php echo $page === $tool_slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tool_slug ) ); ?>" <?php echo $page === $tool_slug ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $pages[ $tool_slug ] ); ?></a>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif;
				endforeach;
				$other_pages = array_diff_key( $pages, $used );
				if ( $other_pages ) : ?>
					<details class="taxocsie-admin-navigation__group<?php echo isset( $other_pages[ $page ] ) ? ' is-active' : ''; ?>">
						<summary class="taxocsie-admin-navigation__tab"><?php esc_html_e( 'More', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
						<div class="taxocsie-admin-navigation__dropdown">
							<?php foreach ( $other_pages as $slug => $label ) : ?>
								<a class="taxocsie-admin-navigation__link<?php echo $page === $slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" <?php echo $page === $slug ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endif; ?>
			</nav>
			<a class="taxocsie-admin-navigation__help" href="<?php echo esc_url( 'https://wp-taxonomy-manager.com/documentation' ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Help documentation (opens in a new tab)', 'taxonomy-csv-import-export' ); ?>">
				<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Help', 'taxonomy-csv-import-export' ); ?></span>
			</a>
		</header>
		<?php
	}

	/* -------------------------------------------------------
	   Taxonomy — Import / Export (MODIFIED with Dry Run)
	------------------------------------------------------- */
	public function get_taxocsie_export_function() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		$default_mode  = 'export';
		$default_user_action = 'export';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested_view = isset( $_GET['manager_view'] ) ? sanitize_key( wp_unslash( $_GET['manager_view'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested_user_action = isset( $_GET['manager_action'] ) ? sanitize_key( wp_unslash( $_GET['manager_action'] ) ) : '';
		if ( in_array( $requested_user_action, array( 'export', 'import' ), true ) ) {
			$default_user_action = $requested_user_action;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['user-imported'] ) ) {
			$default_mode = 'users';
			$default_user_action = 'import';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['imported'] ) ) {
			$default_mode = 'import';
		} elseif ( in_array( $requested_view, array( 'export', 'import', 'edit', 'order', 'users' ), true ) ) {
			$default_mode = $requested_view;
		}

		// Check if preview is requested.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show_preview = isset( $_GET['preview'] ) && '1' === $_GET['preview'];
		$has_initial_action = $show_preview
			|| isset( $_GET['imported'] )
			|| isset( $_GET['user-imported'] )
			|| in_array( $requested_view, array( 'export', 'import', 'edit', 'order', 'users' ), true );
		$term_images_available = class_exists( 'TaxoCSIE\\Premium\\TermImageManager' )
			&& \TaxoCSIE\Premium\TermImageManager::is_available();
		$premium_formats_available = class_exists( DataFormats::class ) && DataFormats::is_available();
		$term_ordering_available   = class_exists( 'TaxoCSIE\\Premium\\TermOrdering' ) && \TaxoCSIE\Premium\TermOrdering::is_available();
		$upgrade_url               = Freemius::get_upgrade_url();
		?>
		<div class="taxocsie-wrap taxocsie-manager-wrap tw-max-w-[1200px] tw-py-6 tw-pr-5">

			<div class="taxocsie-manager-nav" aria-label="<?php esc_attr_e( 'Data Manager workspace navigation', 'taxonomy-csv-import-export' ); ?>">
				<div class="taxocsie-manager-nav__primary">
					<div class="taxocsie-manager-step-heading"><span class="taxocsie-manager-step-number" aria-hidden="true">1</span><div><strong><?php esc_html_e( 'Choose what to manage', 'taxonomy-csv-import-export' ); ?></strong><p><?php esc_html_e( 'Select taxonomy terms or WordPress users.', 'taxonomy-csv-import-export' ); ?></p></div></div>
					<div class="taxocsie-manager-nav__sections" role="group" aria-label="<?php esc_attr_e( 'Data type', 'taxonomy-csv-import-export' ); ?>">
						<button type="button" class="taxocsie-manager-section<?php echo $has_initial_action && 'users' !== $default_mode ? ' is-active' : ''; ?>" data-manager-section="terms" aria-pressed="<?php echo $has_initial_action && 'users' !== $default_mode ? 'true' : 'false'; ?>">
							<span class="dashicons dashicons-category" aria-hidden="true"></span>
							<span><strong><?php esc_html_e( 'Categories, tags & terms', 'taxonomy-csv-import-export' ); ?></strong><small><?php esc_html_e( 'Post, product and custom taxonomies', 'taxonomy-csv-import-export' ); ?></small></span>
						</button>
						<button type="button" class="taxocsie-manager-section<?php echo $has_initial_action && 'users' === $default_mode ? ' is-active' : ''; ?>" data-manager-section="users" aria-pressed="<?php echo $has_initial_action && 'users' === $default_mode ? 'true' : 'false'; ?>">
							<span class="dashicons dashicons-groups" aria-hidden="true"></span>
							<span><strong><?php esc_html_e( 'Users', 'taxonomy-csv-import-export' ); ?></strong><small><?php esc_html_e( 'Transfer WordPress user records', 'taxonomy-csv-import-export' ); ?></small></span>
						</button>
					</div>
				</div>

				<div class="taxocsie-manager-nav__actions<?php echo ! $has_initial_action ? ' is-locked' : ''; ?>" data-locked-message="<?php esc_attr_e( 'Choose what to manage in Step 1 first.', 'taxonomy-csv-import-export' ); ?>"<?php echo ! $has_initial_action ? ' tabindex="0"' : ''; ?>>
					<div class="taxocsie-manager-step-heading"><span class="taxocsie-manager-step-number" aria-hidden="true">2</span><div><strong><?php esc_html_e( 'Choose an action', 'taxonomy-csv-import-export' ); ?></strong><p><?php esc_html_e( 'Export, import, edit, or order the selected data.', 'taxonomy-csv-import-export' ); ?></p></div></div>
					<div class="taxocsie-manager-actions" data-manager-actions="terms" role="tablist" aria-label="<?php esc_attr_e( 'Taxonomy term actions', 'taxonomy-csv-import-export' ); ?>"<?php echo 'users' === $default_mode ? ' hidden' : ''; ?>>
						<button type="button" id="taxocsie-tab-export" class="taxocsie-manager-action<?php echo $has_initial_action && 'export' === $default_mode ? ' is-active' : ''; ?>" data-manager-section="terms" data-panel="taxocsie-export-panel" role="tab" aria-controls="taxocsie-export-panel" aria-selected="<?php echo $has_initial_action && 'export' === $default_mode ? 'true' : 'false'; ?>" tabindex="<?php echo 'export' === $default_mode ? '0' : '-1'; ?>" <?php disabled( ! $has_initial_action ); ?>>
							<span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export', 'taxonomy-csv-import-export' ); ?>
						</button>
						<button type="button" id="taxocsie-tab-import" class="taxocsie-manager-action<?php echo $has_initial_action && 'import' === $default_mode ? ' is-active' : ''; ?>" data-manager-section="terms" data-panel="taxocsie-import-panel" role="tab" aria-controls="taxocsie-import-panel" aria-selected="<?php echo $has_initial_action && 'import' === $default_mode ? 'true' : 'false'; ?>" tabindex="<?php echo 'import' === $default_mode ? '0' : '-1'; ?>" <?php disabled( ! $has_initial_action ); ?>>
							<span class="dashicons dashicons-upload" aria-hidden="true"></span><?php esc_html_e( 'Import', 'taxonomy-csv-import-export' ); ?>
						</button>
						<button type="button" id="taxocsie-tab-edit" class="taxocsie-manager-action<?php echo $has_initial_action && 'edit' === $default_mode ? ' is-active' : ''; ?>" data-manager-section="terms" data-panel="taxocsie-mass-edit-panel" role="tab" aria-controls="taxocsie-mass-edit-panel" aria-selected="<?php echo $has_initial_action && 'edit' === $default_mode ? 'true' : 'false'; ?>" tabindex="<?php echo 'edit' === $default_mode ? '0' : '-1'; ?>" <?php disabled( ! $has_initial_action ); ?>>
							<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Edit', 'taxonomy-csv-import-export' ); ?>
						</button>
						<button type="button" id="taxocsie-tab-order" class="taxocsie-manager-action<?php echo $has_initial_action && 'order' === $default_mode ? ' is-active' : ''; ?>" data-manager-section="terms" data-panel="taxocsie-order-panel" role="tab" aria-controls="taxocsie-order-panel" aria-selected="<?php echo $has_initial_action && 'order' === $default_mode ? 'true' : 'false'; ?>" tabindex="<?php echo 'order' === $default_mode ? '0' : '-1'; ?>" <?php disabled( ! $has_initial_action ); ?>>
							<span class="dashicons dashicons-sort" aria-hidden="true"></span><?php esc_html_e( 'Order', 'taxonomy-csv-import-export' ); ?><small class="taxocsie-action-pro">PRO</small>
						</button>
					</div>
					<div class="taxocsie-manager-actions" data-manager-actions="users" role="tablist" aria-label="<?php esc_attr_e( 'User actions', 'taxonomy-csv-import-export' ); ?>"<?php echo 'users' !== $default_mode ? ' hidden' : ''; ?>>
						<button type="button" id="taxocsie-tab-user-export" class="taxocsie-manager-action<?php echo $has_initial_action && 'users' === $default_mode && 'export' === $default_user_action ? ' is-active' : ''; ?>" data-manager-section="users" data-panel="taxocsie-user-export-panel" role="tab" aria-controls="taxocsie-user-export-panel" aria-selected="<?php echo $has_initial_action && 'users' === $default_mode && 'export' === $default_user_action ? 'true' : 'false'; ?>" tabindex="<?php echo 'export' === $default_user_action ? '0' : '-1'; ?>" <?php disabled( ! $has_initial_action ); ?>>
							<span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export', 'taxonomy-csv-import-export' ); ?>
						</button>
						<button type="button" id="taxocsie-tab-user-import" class="taxocsie-manager-action<?php echo $has_initial_action && 'users' === $default_mode && 'import' === $default_user_action ? ' is-active' : ''; ?>" data-manager-section="users" data-panel="taxocsie-user-import-panel" role="tab" aria-controls="taxocsie-user-import-panel" aria-selected="<?php echo $has_initial_action && 'users' === $default_mode && 'import' === $default_user_action ? 'true' : 'false'; ?>" tabindex="<?php echo 'import' === $default_user_action ? '0' : '-1'; ?>" <?php disabled( ! $has_initial_action ); ?>>
							<span class="dashicons dashicons-upload" aria-hidden="true"></span><?php esc_html_e( 'Import', 'taxonomy-csv-import-export' ); ?>
						</button>
					</div>
				</div>
			</div>
			<noscript><p class="taxocsie-form-help"><?php esc_html_e( 'Choose a task:', 'taxonomy-csv-import-export' ); ?> <a href="<?php echo esc_url( add_query_arg( 'manager_view', 'export' ) ); ?>"><?php esc_html_e( 'Export terms', 'taxonomy-csv-import-export' ); ?></a> &middot; <a href="<?php echo esc_url( add_query_arg( 'manager_view', 'import' ) ); ?>"><?php esc_html_e( 'Import terms', 'taxonomy-csv-import-export' ); ?></a> &middot; <a href="<?php echo esc_url( add_query_arg( array( 'manager_view' => 'users', 'manager_action' => 'export' ) ) ); ?>"><?php esc_html_e( 'Export users', 'taxonomy-csv-import-export' ); ?></a> &middot; <a href="<?php echo esc_url( add_query_arg( array( 'manager_view' => 'users', 'manager_action' => 'import' ) ) ); ?>"><?php esc_html_e( 'Import users', 'taxonomy-csv-import-export' ); ?></a></p></noscript>
			<div class="taxocsie-manager-current-step<?php echo ! $has_initial_action ? ' is-locked' : ''; ?>" data-locked-message="<?php esc_attr_e( 'Choose an action in Step 2 first to use these options.', 'taxonomy-csv-import-export' ); ?>"<?php echo ! $has_initial_action ? ' tabindex="0"' : ''; ?>><span class="taxocsie-manager-step-number" aria-hidden="true">3</span><div><strong><?php esc_html_e( 'Complete the task', 'taxonomy-csv-import-export' ); ?></strong><p><?php esc_html_e( 'Work through the options below, then complete the selected action.', 'taxonomy-csv-import-export' ); ?></p></div></div>

			<!-- Export Panel -->
			<div
				id="taxocsie-export-panel"
				role="tabpanel" aria-labelledby="taxocsie-tab-export"
				class="taxocsie-manager-panel taxocsie-panel<?php echo ! $has_initial_action ? ' is-locked' : ''; ?> tw-mt-4 tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft"
				<?php echo 'export' !== $default_mode ? ' style="display:none;"' : ''; ?>
				<?php echo ! $has_initial_action ? ' inert' : ''; ?>
			>
				<div class="tw-flex tw-items-center tw-gap-3 tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
					<div class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-brand-100 tw-text-brand-700">
						<span class="dashicons dashicons-download tw-text-xl" aria-hidden="true"></span>
					</div>
					<div>
						<h2 class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900">
							<?php esc_html_e( 'Export categories, tags & terms', 'taxonomy-csv-import-export' ); ?>
						</h2>
						<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Choose categories, tags or another taxonomy. Preview the terms, then download the file.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
				</div>

				<form id="taxocsie-export-form" class="taxocsie-panel__form tw-px-6 tw-py-7 sm:tw-px-8" method="post">
					<?php wp_nonce_field( 'taxonomy_csv_export', 'taxonomy_csv_export_nonce' ); ?>
					<input type="hidden" name="taxonomy_export_csv" value="1">

					<div class="taxocsie-manager-substep"><span>1</span><div><strong><?php esc_html_e( 'Choose taxonomy and format', 'taxonomy-csv-import-export' ); ?></strong><p><?php esc_html_e( 'Select the terms to include and the file type to download.', 'taxonomy-csv-import-export' ); ?></p></div></div>
					<div class="taxocsie-panel__row tw-flex tw-flex-col tw-gap-5 lg:tw-flex-row lg:tw-items-end">
						<div class="tw-w-full lg:tw-max-w-md">
							<label class="tw-mb-2 tw-block tw-text-sm tw-font-semibold tw-text-slate-700" for="export-taxonomy"><?php esc_html_e( 'What do you want to export?', 'taxonomy-csv-import-export' ); ?></label>
						<select
							class="taxocsie-panel__select tw-h-11 tw-w-full tw-max-w-none tw-rounded-lg tw-border tw-border-slate-300 tw-bg-white tw-px-3.5 tw-text-sm tw-text-slate-800 tw-shadow-sm tw-outline-none tw-transition focus:tw-border-brand-500 focus:tw-ring-4 focus:tw-ring-brand-100"
							id="export-taxonomy"
							name="taxocsie_type"
							required
						>
							<option value="">
								<?php esc_html_e( 'Select Taxonomy', 'taxonomy-csv-import-export' ); ?>
							</option>

							<?php foreach ( $taxonomies as $taxonomy ) : ?>
								<option value="<?php echo esc_attr( $taxonomy->name ); ?>">
									<?php
									echo esc_html(
										$taxonomy->label . ' (' . $taxonomy->name . ')'
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
						</div>

						<div class="tw-w-full lg:tw-w-auto">
							<label class="tw-mb-2 tw-block tw-text-sm tw-font-semibold tw-text-slate-700" for="taxocsie-export-format"><?php esc_html_e( 'File format', 'taxonomy-csv-import-export' ); ?></label>
							<select class="taxocsie-panel__select tw-h-11 tw-w-full tw-rounded-lg tw-border tw-border-slate-300 tw-bg-white tw-px-3.5 tw-text-sm tw-text-slate-800 tw-shadow-sm" id="taxocsie-export-format" name="taxocsie_format">
								<option value="csv"><?php esc_html_e( 'CSV (.csv)', 'taxonomy-csv-import-export' ); ?></option>
								<option value="xlsx" <?php disabled( ! $premium_formats_available ); ?>><?php echo esc_html( $premium_formats_available ? __( 'Excel (.xlsx)', 'taxonomy-csv-import-export' ) : __( 'Excel (.xlsx) — Pro', 'taxonomy-csv-import-export' ) ); ?></option>
								<option value="json" <?php disabled( ! $premium_formats_available ); ?>><?php echo esc_html( $premium_formats_available ? __( 'JSON (.json)', 'taxonomy-csv-import-export' ) : __( 'JSON (.json) — Pro', 'taxonomy-csv-import-export' ) ); ?></option>
							</select>
						</div>

					</div>
					<p class="taxocsie-form-help"><?php esc_html_e( 'All terms in the selected taxonomy are included unless you add filters below.', 'taxonomy-csv-import-export' ); ?></p>

					<div class="taxocsie-manager-substep"><span>2</span><div><strong><?php esc_html_e( 'Refine the export', 'taxonomy-csv-import-export' ); ?></strong><p><?php esc_html_e( 'Filters and term images are optional.', 'taxonomy-csv-import-export' ); ?></p></div></div>
					<details class="taxocsie-export-filters">
						<summary>
							<span><span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e( 'Filter exported terms', 'taxonomy-csv-import-export' ); ?></span>
							<small><?php esc_html_e( 'Optional', 'taxonomy-csv-import-export' ); ?></small>
						</summary>
						<div class="taxocsie-export-filters__grid">
							<label class="taxocsie-export-filter taxocsie-export-filter--check">
								<input type="checkbox" name="taxocsie_filter_hide_empty" value="1">
								<span><strong><?php esc_html_e( 'Hide empty terms', 'taxonomy-csv-import-export' ); ?></strong><small><?php esc_html_e( 'Only include terms assigned to content.', 'taxonomy-csv-import-export' ); ?></small></span>
							</label>
							<div class="taxocsie-export-filter taxocsie-export-filter--name">
								<label for="taxocsie-filter-name"><?php esc_html_e( 'Name filter', 'taxonomy-csv-import-export' ); ?></label>
								<div class="taxocsie-export-filter__joined">
									<select name="taxocsie_filter_name_match" aria-label="<?php esc_attr_e( 'Name matching method', 'taxonomy-csv-import-export' ); ?>">
										<option value="contains"><?php esc_html_e( 'Contains', 'taxonomy-csv-import-export' ); ?></option>
										<option value="prefix"><?php esc_html_e( 'Starts with', 'taxonomy-csv-import-export' ); ?></option>
									</select>
									<input type="search" id="taxocsie-filter-name" name="taxocsie_filter_name" placeholder="<?php esc_attr_e( 'Term name', 'taxonomy-csv-import-export' ); ?>">
								</div>
							</div>
							<div class="taxocsie-export-filter">
								<label for="taxocsie-filter-parent"><?php esc_html_e( 'Parent', 'taxonomy-csv-import-export' ); ?></label>
								<select id="taxocsie-filter-parent" name="taxocsie_filter_parent">
									<option value="any"><?php esc_html_e( 'Any parent', 'taxonomy-csv-import-export' ); ?></option>
									<option value="root"><?php esc_html_e( 'Top-level terms only', 'taxonomy-csv-import-export' ); ?></option>
								</select>
							</div>
							<div class="taxocsie-export-filter">
								<span class="taxocsie-export-filter__label"><?php esc_html_e( 'Term count', 'taxonomy-csv-import-export' ); ?></span>
								<div class="taxocsie-export-filter__range">
									<input type="number" min="0" name="taxocsie_filter_count_min" aria-label="<?php esc_attr_e( 'Minimum term count', 'taxonomy-csv-import-export' ); ?>" placeholder="<?php esc_attr_e( 'Minimum', 'taxonomy-csv-import-export' ); ?>">
									<span>&ndash;</span>
									<input type="number" min="0" name="taxocsie_filter_count_max" aria-label="<?php esc_attr_e( 'Maximum term count', 'taxonomy-csv-import-export' ); ?>" placeholder="<?php esc_attr_e( 'Maximum', 'taxonomy-csv-import-export' ); ?>">
								</div>
							</div>
						</div>
					</details>

					<?php if ( ! $premium_formats_available ) : ?>
						<div class="taxocsie-format-pro-note">
							<span class="dashicons dashicons-lock" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Excel and JSON formats are available with Pro.', 'taxonomy-csv-import-export' ); ?> <a href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?></a></span>
						</div>
					<?php endif; ?>

					<div id="taxocsie-export-images-option" class="taxocsie-export-images-option">
						<label class="taxocsie-export-images-option__label" for="taxocsie-include-images">
							<input
								type="checkbox"
								id="taxocsie-include-images"
								data-available="<?php echo $term_images_available ? '1' : '0'; ?>"
								name="taxocsie_include_images"
								value="1"
								<?php checked( $term_images_available ); ?>
								<?php disabled( ! $term_images_available ); ?>
							>
							<span class="taxocsie-export-images-option__content">
								<span class="taxocsie-export-images-option__heading">
									<strong><?php esc_html_e( 'Include term images', 'taxonomy-csv-import-export' ); ?></strong>
									<?php if ( ! $term_images_available ) : ?>
										<span class="taxocsie-export-images-option__badge"><?php esc_html_e( 'Pro', 'taxonomy-csv-import-export' ); ?></span>
									<?php endif; ?>
								</span>
								<small><?php esc_html_e( 'Add each term’s Media Library image URL to the CSV.', 'taxonomy-csv-import-export' ); ?></small>
							</span>
						</label>
						<?php if ( ! $term_images_available ) : ?>
							<a class="taxocsie-export-images-option__upgrade" href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to enable term images', 'taxonomy-csv-import-export' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="taxocsie-manager-substep"><span>3</span><div><strong><?php esc_html_e( 'Preview or download', 'taxonomy-csv-import-export' ); ?></strong><p><?php esc_html_e( 'Preview the terms first, or download the selected file directly.', 'taxonomy-csv-import-export' ); ?></p></div></div>
					<div class="taxocsie-panel__actions taxocsie-export-actions tw-flex tw-flex-wrap tw-gap-3">
						<button type="button" id="export-preview-btn" class="button taxocsie-panel__btn-preview tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-rounded-lg tw-border tw-border-slate-300 tw-bg-white tw-px-5 tw-text-sm tw-font-semibold tw-text-slate-700 tw-shadow-sm tw-transition hover:tw-border-brand-500 hover:tw-text-brand-700 focus:tw-ring-4 focus:tw-ring-brand-100"><?php esc_html_e( 'Preview', 'taxonomy-csv-import-export' ); ?></button>
						<button type="submit" class="button button-primary taxocsie-panel__btn-submit tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-rounded-lg tw-border-0 tw-bg-brand-600 tw-px-6 tw-text-sm tw-font-semibold tw-text-white tw-shadow-sm tw-transition hover:tw-bg-brand-700 focus:tw-ring-4 focus:tw-ring-brand-100"><?php esc_html_e( 'Download CSV', 'taxonomy-csv-import-export' ); ?></button>
					</div>
				</form>

				<div
					id="export-preview-container"
					class="taxocsie-panel__preview tw-border-t tw-border-slate-100 tw-px-6 tw-py-6 sm:tw-px-8"
					<?php echo ! $show_preview ? 'style="display:none;"' : ''; ?>
				></div>
			</div>

			<!-- Import Panel -->
			<div
				id="taxocsie-import-panel"
				role="tabpanel" aria-labelledby="taxocsie-tab-import"
				class="taxocsie-manager-panel taxocsie-panel taxocsie-import-wizard tw-mt-4 tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft"
				<?php echo ! $has_initial_action || 'import' !== $default_mode ? ' style="display:none;"' : ''; ?>
			>
				<div class="taxocsie-import-wizard__steps" aria-label="<?php esc_attr_e( 'CSV import progress', 'taxonomy-csv-import-export' ); ?>">
					<button type="button" class="taxocsie-import-step is-active" data-import-step="1" aria-current="step">
						<span>1</span>
						<?php esc_html_e( 'Choose destination', 'taxonomy-csv-import-export' ); ?>
					</button>
					<button type="button" class="taxocsie-import-step" data-import-step="2">
						<span>2</span>
						<?php esc_html_e( 'Upload file', 'taxonomy-csv-import-export' ); ?>
					</button>
					<button type="button" class="taxocsie-import-step" data-import-step="3">
						<span>3</span>
						<?php esc_html_e( 'Confirm import', 'taxonomy-csv-import-export' ); ?>
					</button>
				</div>

				<form
					id="taxocsie-import-wizard-form"
					class="taxocsie-import-wizard__form"
					method="post"
					enctype="multipart/form-data"
				>
					<?php wp_nonce_field( 'taxonomy_csv_import', 'taxonomy_csv_import_nonce' ); ?>
					<input type="hidden" name="taxonomy_import_csv" value="1">

					<section class="taxocsie-import-stage is-active" data-import-stage="1">
						<header class="taxocsie-import-wizard__intro">
							<h2><?php esc_html_e( 'Choose where terms will go', 'taxonomy-csv-import-export' ); ?></h2>
						<p><?php esc_html_e( 'Choose where the new categories or tags should be created. For standard post categories, select Categories (category).', 'taxonomy-csv-import-export' ); ?></p>
						</header>

						<div class="taxocsie-import-wizard__destination">
							<label for="taxonomy">
								<span class="dashicons dashicons-category" aria-hidden="true"></span>
								<span>
									<strong><?php esc_html_e( 'Destination category, tag or taxonomy', 'taxonomy-csv-import-export' ); ?></strong>
									<small><?php esc_html_e( 'The file must contain term names. Existing names or slugs are skipped.', 'taxonomy-csv-import-export' ); ?></small>
								</span>
							</label>
							<select class="taxocsie-panel__select" id="taxonomy" name="taxocsie_type" required>
								<option value=""><?php esc_html_e( 'Select Taxonomy', 'taxonomy-csv-import-export' ); ?></option>
								<?php foreach ( $taxonomies as $taxonomy ) : ?>
									<option value="<?php echo esc_attr( $taxonomy->name ); ?>"><?php echo esc_html( $taxonomy->label . ' (' . $taxonomy->name . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="taxocsie-import-stage__actions taxocsie-import-stage__actions--end">
							<button type="button" class="button button-primary taxocsie-wizard-next" data-next-step="2">
								<?php esc_html_e( 'Continue to upload', 'taxonomy-csv-import-export' ); ?>
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
						</div>
					</section>

					<section class="taxocsie-import-stage" data-import-stage="2" hidden>
						<header class="taxocsie-import-wizard__intro">
							<h2 id="taxocsie-import-upload-title"><?php esc_html_e( 'Upload your CSV file', 'taxonomy-csv-import-export' ); ?></h2>
							<p><?php esc_html_e( 'Choose a file with a name field. For CSV, match its columns below or download the example template.', 'taxonomy-csv-import-export' ); ?></p>
						</header>

						<div class="taxocsie-import-format-field">
							<label class="tw-mb-2 tw-block tw-text-sm tw-font-semibold tw-text-slate-700" for="taxocsie-import-format"><?php esc_html_e( 'File format', 'taxonomy-csv-import-export' ); ?></label>
							<select class="taxocsie-panel__select" id="taxocsie-import-format" name="taxocsie_format">
								<option value="csv"><?php esc_html_e( 'CSV (.csv or .txt)', 'taxonomy-csv-import-export' ); ?></option>
								<option value="xlsx" <?php disabled( ! $premium_formats_available ); ?>><?php echo esc_html( $premium_formats_available ? __( 'Excel (.xlsx)', 'taxonomy-csv-import-export' ) : __( 'Excel (.xlsx) — Pro', 'taxonomy-csv-import-export' ) ); ?></option>
								<option value="json" <?php disabled( ! $premium_formats_available ); ?>><?php echo esc_html( $premium_formats_available ? __( 'JSON (.json)', 'taxonomy-csv-import-export' ) : __( 'JSON (.json) — Pro', 'taxonomy-csv-import-export' ) ); ?></option>
							</select>
							<?php if ( ! $premium_formats_available ) : ?>
								<div class="taxocsie-format-pro-note">
									<span class="dashicons dashicons-lock" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Excel and JSON formats are available with Pro.', 'taxonomy-csv-import-export' ); ?> <a href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?></a></span>
								</div>
							<?php endif; ?>
						</div>

						<div id="taxocsie-upload-section" class="taxocsie-upload-section" tabindex="0">
						<div class="taxocsie-upload-section__content">
							<span class="taxocsie-upload-section__icon dashicons dashicons-cloud-upload" aria-hidden="true"></span>
							<h3 id="taxocsie-upload-heading"><?php esc_html_e( 'Drag and drop your CSV file here', 'taxonomy-csv-import-export' ); ?></h3>
							<p id="taxocsie-upload-formats"><?php esc_html_e( 'Supported formats: .csv and .txt', 'taxonomy-csv-import-export' ); ?></p>
							<input
								type="file"
								name="csv_file"
								id="csv_file"
								accept=".csv,.txt,text/csv,text/plain"
								required
							>
							<label class="taxocsie-file-upload-btn" for="csv_file">
								<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
								<span id="taxocsie-file-button-label"><?php esc_html_e( 'Choose a CSV file', 'taxonomy-csv-import-export' ); ?></span>
							</label>
							<div id="file-status" class="taxocsie-file-status" aria-live="polite">
								<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
								<span class="taxocsie-file-empty"><?php esc_html_e( 'No file selected', 'taxonomy-csv-import-export' ); ?></span>
							</div>
						</div>
						</div>

						<section class="taxocsie-column-mapper" id="taxocsie-column-mapper" aria-labelledby="taxocsie-column-mapper-title" hidden>
							<header class="taxocsie-column-mapper__heading">
								<div>
									<h3 id="taxocsie-column-mapper-title"><?php esc_html_e( 'Map CSV columns', 'taxonomy-csv-import-export' ); ?></h3>
									<p><?php esc_html_e( 'Match each file column to a term field. Name is required; choose Ignore for columns you do not need.', 'taxonomy-csv-import-export' ); ?></p>
								</div>
								<span id="taxocsie-column-mapper-status" class="taxocsie-column-mapper__status" role="status"></span>
							</header>
							<div class="taxocsie-column-mapper__table-wrap">
								<table class="taxocsie-column-mapper__table">
									<thead><tr><th scope="col"><?php esc_html_e( 'CSV column', 'taxonomy-csv-import-export' ); ?></th><th scope="col"><?php esc_html_e( 'Import as', 'taxonomy-csv-import-export' ); ?></th></tr></thead>
									<tbody id="taxocsie-column-mapping-rows"></tbody>
								</table>
							</div>
						</section>

						<div class="taxocsie-import-stage__actions">
							<button type="button" class="button taxocsie-wizard-back" data-previous-step="1">
								<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
								<?php esc_html_e( 'Back', 'taxonomy-csv-import-export' ); ?>
							</button>
							<a class="button taxocsie-template-button taxocsie-csv-only-note" href="<?php echo esc_url( TAXOCSIE_ASSETS_URI . '/templates/taxonomy-import-template.csv' ); ?>" download>
								<span class="dashicons dashicons-download" aria-hidden="true"></span>
								<?php esc_html_e( 'Download template', 'taxonomy-csv-import-export' ); ?>
							</a>
							<button type="button" class="button button-primary taxocsie-wizard-next" data-next-step="3">
								<?php esc_html_e( 'Confirm import', 'taxonomy-csv-import-export' ); ?>
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
						</div>

						<div class="taxocsie-csv-only-note tw-mt-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4 tw-text-sm tw-text-slate-600">
							<strong class="tw-text-slate-800"><?php esc_html_e( 'Custom term links', 'taxonomy-csv-import-export' ); ?></strong>
							<p class="tw-mb-0 tw-mt-1"><?php esc_html_e( 'Add an optional custom_link column with an internal or external HTTP(S) URL. Imported links replace the normal term archive URL.', 'taxonomy-csv-import-export' ); ?></p>
						</div>

						<div class="taxocsie-csv-only-note tw-mt-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4 tw-text-sm tw-text-slate-600">
							<strong class="tw-text-slate-800"><?php esc_html_e( 'Term images', 'taxonomy-csv-import-export' ); ?> <a class="tw-ml-1 tw-rounded-full tw-bg-brand-100 tw-px-2 tw-py-0.5 tw-text-xs tw-text-brand-700 tw-no-underline" href="<?php echo esc_url( $upgrade_url ); ?>" aria-label="<?php esc_attr_e( 'View Pro pricing on our website', 'taxonomy-csv-import-export' ); ?>"><?php esc_html_e( 'Pro', 'taxonomy-csv-import-export' ); ?></a></strong>
							<?php if ( $term_images_available ) : ?>
								<p class="tw-mb-0 tw-mt-1"><?php esc_html_e( 'Add an image_source column with a public HTTP or HTTPS image URL. Images are downloaded into the Media Library and generated during import.', 'taxonomy-csv-import-export' ); ?></p>
							<?php else : ?>
								<p class="tw-mb-0 tw-mt-1"><?php esc_html_e( 'Image URLs in CSV files require Pro. Terms will still import without images.', 'taxonomy-csv-import-export' ); ?> <a href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade', 'taxonomy-csv-import-export' ); ?></a></p>
							<?php endif; ?>
						</div>
					</section>

					<section class="taxocsie-import-stage" data-import-stage="3" hidden>
						<header class="taxocsie-import-wizard__intro">
							<h2><?php esc_html_e( 'Confirm destination and import', 'taxonomy-csv-import-export' ); ?></h2>
							<p><?php esc_html_e( 'This creates new terms. Existing names or slugs are skipped; this CSV import does not update them.', 'taxonomy-csv-import-export' ); ?></p>
						</header>

						<div class="taxocsie-import-review">
							<div class="taxocsie-import-review__item">
								<span class="dashicons dashicons-category" aria-hidden="true"></span>
								<span><small><?php esc_html_e( 'Destination taxonomy', 'taxonomy-csv-import-export' ); ?></small><strong id="taxocsie-review-taxonomy">&mdash;</strong></span>
							</div>
							<div class="taxocsie-import-review__item">
								<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
								<span><small id="taxocsie-review-file-label"><?php esc_html_e( 'CSV file', 'taxonomy-csv-import-export' ); ?></small><strong id="taxocsie-review-file">&mdash;</strong></span>
							</div>
						</div>
						<p class="taxocsie-form-help"><?php esc_html_e( 'For parent and child categories, put parent rows before their children, or make sure the parent already exists.', 'taxonomy-csv-import-export' ); ?></p>

						<div class="taxocsie-import-stage__actions">
							<button type="button" class="button taxocsie-wizard-back" data-previous-step="2">
								<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
								<?php esc_html_e( 'Back', 'taxonomy-csv-import-export' ); ?>
							</button>
							<button type="submit" class="button button-primary taxocsie-import-wizard__submit">
								<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<?php esc_html_e( 'Import CSV', 'taxonomy-csv-import-export' ); ?>
							</button>
						</div>
					</section>
				</form>

				<!-- Import Results -->
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['imported'] ) ) :
					$csv_data     = get_transient( 'taxoscie_csv_data' );
					$skipped_data = get_transient( 'taxoscie_csv_skipped' );
					$has_images   = (bool) get_transient( 'taxoscie_csv_has_images' );
					$image_errors = get_transient( 'taxoscie_csv_image_errors' );
					$has_links    = (bool) get_transient( 'taxoscie_csv_has_custom_links' );
					$link_errors  = get_transient( 'taxoscie_csv_link_errors' );

					$csv_data     = is_array( $csv_data ) ? $csv_data : array();
					$skipped_data = is_array( $skipped_data ) ? $skipped_data : array();
					$image_errors = is_array( $image_errors ) ? $image_errors : array();
					$link_errors  = is_array( $link_errors ) ? $link_errors : array();

					$imported_count = count( $csv_data );
					$skipped_count  = count( $skipped_data );
					?>

					<div class="notice notice-success taxocsie-panel__notice">
						<p>
							<?php
							printf(
								/* translators: 1: imported count, 2: skipped count. */
								esc_html__(
									'Import finished: %1$d new term(s) created; %2$d existing or invalid term(s) skipped.',
									'taxonomy-csv-import-export'
								),
								esc_html( $imported_count ),
								esc_html( $skipped_count )
							);
							?>
						</p>
					</div>

					<?php if ( ! empty( $image_errors ) ) : ?>
						<div class="notice notice-warning taxocsie-panel__notice">
							<p><strong><?php esc_html_e( 'Some terms were imported, but their images could not be generated:', 'taxonomy-csv-import-export' ); ?></strong></p>
							<ul>
								<?php foreach ( $image_errors as $image_error ) : ?>
									<li><?php echo esc_html( ( $image_error['term'] ?? '' ) . ': ' . ( $image_error['message'] ?? '' ) ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $link_errors ) ) : ?>
						<div class="notice notice-warning taxocsie-panel__notice">
							<p><strong><?php esc_html_e( 'Some terms were imported, but their custom links were rejected:', 'taxonomy-csv-import-export' ); ?></strong></p>
							<ul>
								<?php foreach ( $link_errors as $link_error ) : ?>
									<li><?php echo esc_html( ( $link_error['term'] ?? '' ) . ': ' . ( $link_error['message'] ?? '' ) ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $csv_data ) ) : ?>

						<h3 class="taxocsie-result__title">
							<?php
							printf(
								/* translators: %d: imported count. */
								esc_html__(
									'Imported Terms (%d)',
									'taxonomy-csv-import-export'
								),
								esc_html( $imported_count )
							);
							?>
						</h3>

						<div class="taxocsie-panel__tablewrap">
							<table class="widefat striped taxocsie-panel__table taxocsie-enhanced-table">
								<thead>
									<tr>
										<th>
											<?php esc_html_e( 'Title', 'taxonomy-csv-import-export' ); ?>
										</th>
										<th>
											<?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?>
										</th>
										<th>
											<?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?>
										</th>
										<th>
											<?php esc_html_e( 'Parent Slug', 'taxonomy-csv-import-export' ); ?>
										</th>
										<?php if ( $has_links ) : ?>
											<th><?php esc_html_e( 'Custom Link', 'taxonomy-csv-import-export' ); ?></th>
										<?php endif; ?>
										<?php if ( $has_images ) : ?>
											<th><?php esc_html_e( 'Image Source', 'taxonomy-csv-import-export' ); ?></th>
										<?php endif; ?>
									</tr>
								</thead>

								<tbody>
									<?php foreach ( $csv_data as $row ) : ?>
										<tr>
											<?php foreach ( $row as $cell ) : ?>
												<td>
													<?php
													echo esc_html(
														html_entity_decode( $cell )
													);
													?>
												</td>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

					<?php endif; ?>

					<?php if ( ! empty( $skipped_data ) ) : ?>

						<div class="notice notice-warning taxocsie-panel__notice">
							<p>
								<?php
								printf(
									/* translators: %d: skipped count. */
									esc_html__(
										'%d term(s) were skipped because they already exist or could not be imported.',
										'taxonomy-csv-import-export'
									),
									esc_html( $skipped_count )
								);
								?>
							</p>
						</div>

						<h3 class="taxocsie-result__title taxocsie-result__title--skipped">
							<?php
							printf(
								/* translators: %d: skipped count. */
								esc_html__(
									'Skipped Terms (%d)',
									'taxonomy-csv-import-export'
								),
								esc_html( $skipped_count )
							);
							?>
						</h3>

						<div class="taxocsie-panel__tablewrap">
							<table class="widefat striped taxocsie-panel__table taxocsie-panel__table--skipped taxocsie-enhanced-table">
								<thead>
									<tr>
										<th>
											<?php esc_html_e( 'Title', 'taxonomy-csv-import-export' ); ?>
										</th>
										<th>
											<?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?>
										</th>
										<th>
											<?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?>
										</th>
										<th>
											<?php esc_html_e( 'Parent Slug', 'taxonomy-csv-import-export' ); ?>
										</th>
										<?php if ( $has_links ) : ?>
											<th><?php esc_html_e( 'Custom Link', 'taxonomy-csv-import-export' ); ?></th>
										<?php endif; ?>
										<?php if ( $has_images ) : ?>
											<th><?php esc_html_e( 'Image Source', 'taxonomy-csv-import-export' ); ?></th>
										<?php endif; ?>
									</tr>
								</thead>

								<tbody>
									<?php foreach ( $skipped_data as $row ) : ?>
										<tr>
											<?php foreach ( $row as $cell ) : ?>
												<td>
													<?php
													echo esc_html(
														html_entity_decode( $cell )
													);
													?>
												</td>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

					<?php endif; ?>

				<?php endif; ?>
			</div>

			<!-- Inline term editor -->
			<div
				id="taxocsie-mass-edit-panel"
				role="tabpanel" aria-labelledby="taxocsie-tab-edit"
				class="taxocsie-manager-panel taxocsie-panel taxocsie-mass-editor tw-mt-4 tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft"
				<?php echo ! $has_initial_action || 'edit' !== $default_mode ? ' style="display:none;"' : ''; ?>
			>
				<div class="tw-flex tw-items-center tw-gap-3 tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
					<div class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-brand-100 tw-text-brand-700">
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					</div>
					<div>
						<h2 class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900"><?php esc_html_e( 'Inline Term Editor', 'taxonomy-csv-import-export' ); ?></h2>
						<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Edit multiple term names and slugs, then save all changed rows together.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
				</div>

				<div class="taxocsie-mass-editor__controls">
					<div class="taxocsie-mass-editor__field">
						<label for="taxocsie-mass-taxonomy"><?php esc_html_e( 'Taxonomy', 'taxonomy-csv-import-export' ); ?></label>
						<select id="taxocsie-mass-taxonomy" class="taxocsie-panel__select">
							<option value=""><?php esc_html_e( 'Select taxonomy', 'taxonomy-csv-import-export' ); ?></option>
							<?php foreach ( $taxonomies as $taxonomy ) : ?>
								<option value="<?php echo esc_attr( $taxonomy->name ); ?>"><?php echo esc_html( $taxonomy->label . ' (' . $taxonomy->name . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="taxocsie-mass-editor__field taxocsie-mass-editor__field--search">
						<label for="taxocsie-mass-search"><?php esc_html_e( 'Search terms', 'taxonomy-csv-import-export' ); ?></label>
						<input type="search" id="taxocsie-mass-search" placeholder="<?php esc_attr_e( 'Filter by name or slug', 'taxonomy-csv-import-export' ); ?>">
					</div>
					<button type="button" id="taxocsie-mass-load" class="button button-primary">
						<span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Load terms', 'taxonomy-csv-import-export' ); ?>
					</button>
				</div>

				<div id="taxocsie-mass-status" class="taxocsie-mass-editor__status" role="status" aria-live="polite">
					<?php esc_html_e( 'Choose a taxonomy to begin editing.', 'taxonomy-csv-import-export' ); ?>
				</div>
				<div id="taxocsie-mass-table-controls" class="taxocsie-table-controls taxocsie-mass-editor__table-controls" hidden>
					<div class="taxocsie-table-total-wrap"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><strong id="taxocsie-mass-total">0</strong><span><?php esc_html_e( 'total', 'taxonomy-csv-import-export' ); ?></span></div>
					<div class="taxocsie-table-pagination">
						<label for="taxocsie-mass-per-page"><?php esc_html_e( 'Rows per page', 'taxonomy-csv-import-export' ); ?></label>
						<select id="taxocsie-mass-per-page" class="taxocsie-table-per-page"><option value="5">5</option><option value="10" selected>10</option><option value="20">20</option><option value="50">50</option></select>
						<span class="taxocsie-page-status"><span id="taxocsie-mass-current-page">1</span> <?php esc_html_e( 'of', 'taxonomy-csv-import-export' ); ?> <span id="taxocsie-mass-total-pages">1</span></span>
						<div class="taxocsie-pagination-buttons"><button type="button" id="taxocsie-mass-prev" aria-label="<?php esc_attr_e( 'Previous page', 'taxonomy-csv-import-export' ); ?>" disabled><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button><button type="button" id="taxocsie-mass-next" aria-label="<?php esc_attr_e( 'Next page', 'taxonomy-csv-import-export' ); ?>" disabled><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button></div>
					</div>
				</div>

				<div id="taxocsie-mass-table-wrap" class="taxocsie-mass-editor__table-wrap" hidden>
					<table class="widefat striped taxocsie-mass-editor__table">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Term name', 'taxonomy-csv-import-export' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Parent', 'taxonomy-csv-import-export' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Count', 'taxonomy-csv-import-export' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Status', 'taxonomy-csv-import-export' ); ?></span></th>
						</tr></thead>
						<tbody id="taxocsie-mass-rows"></tbody>
					</table>
				</div>

				<div id="taxocsie-mass-footer" class="taxocsie-mass-editor__footer" hidden>
				<span class="taxocsie-mass-editor__footer-hint"><?php esc_html_e( 'Changes on other pages stay queued until you save.', 'taxonomy-csv-import-export' ); ?></span>
					<button type="button" id="taxocsie-mass-save" class="button button-primary" disabled>
						<span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Save changes', 'taxonomy-csv-import-export' ); ?>
					</button>
				</div>
			</div>

			<div id="taxocsie-order-panel" role="tabpanel" aria-labelledby="taxocsie-tab-order" class="taxocsie-manager-panel taxocsie-panel taxocsie-order-panel tw-mt-4"<?php echo ! $has_initial_action || 'order' !== $default_mode ? ' style="display:none;"' : ''; ?>>
				<div class="tw-flex tw-items-center tw-gap-3 tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
					<span class="dashicons dashicons-sort tw-text-brand-700" aria-hidden="true"></span>
					<div><h2 class="tw-m-0 tw-text-base tw-font-bold"><?php esc_html_e( 'Taxonomy Order', 'taxonomy-csv-import-export' ); ?></h2><p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Drag terms into the order you want, without changing their parent relationships.', 'taxonomy-csv-import-export' ); ?></p></div>
				</div>
				<?php if ( $term_ordering_available ) : ?>
					<div class="taxocsie-order-controls">
						<select id="taxocsie-order-taxonomy"><option value=""><?php esc_html_e( 'Select taxonomy', 'taxonomy-csv-import-export' ); ?></option><?php foreach ( $taxonomies as $taxonomy ) : ?><option value="<?php echo esc_attr( $taxonomy->name ); ?>"><?php echo esc_html( $taxonomy->label ); ?></option><?php endforeach; ?></select>
						<button type="button" id="taxocsie-order-load" class="button button-primary"><?php esc_html_e( 'Load terms', 'taxonomy-csv-import-export' ); ?></button>
					</div>
					<p id="taxocsie-order-status" class="taxocsie-order-status" role="status"><?php esc_html_e( 'Choose a taxonomy to start ordering.', 'taxonomy-csv-import-export' ); ?></p>
					<ul id="taxocsie-order-list" class="taxocsie-order-list" hidden></ul>
					<div id="taxocsie-order-footer" class="taxocsie-order-footer" hidden>
						<label><input type="checkbox" id="taxocsie-order-frontend"> <?php esc_html_e( 'Use this custom order for frontend term queries', 'taxonomy-csv-import-export' ); ?></label>
						<button type="button" id="taxocsie-order-save" class="button button-primary"><?php esc_html_e( 'Save order', 'taxonomy-csv-import-export' ); ?></button>
					</div>
				<?php else : ?>
					<div class="taxocsie-order-locked"><span class="dashicons dashicons-lock" aria-hidden="true"></span><h3><?php esc_html_e( 'Taxonomy ordering is a Pro feature', 'taxonomy-csv-import-export' ); ?></h3><p><?php esc_html_e( 'Activate Pro to save drag-and-drop term order and optionally apply it on the frontend.', 'taxonomy-csv-import-export' ); ?></p><a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'View Pro options', 'taxonomy-csv-import-export' ); ?></a></div>
				<?php endif; ?>
			</div>

			<?php $this->render_users_manager_panel( $default_mode, $default_user_action ); ?>
		</div>

		<?php
	}

	/**
	 * Render the plugin Dashboard.
	 */
	public function get_taxocsie_dashboard_function(): void {
		$stats        = Dashboard::get_stats();
		$show_upgrade = ! Freemius::can_use_premium_code();
		$upgrade_url  = $show_upgrade ? Freemius::get_upgrade_url() : '';
		$show_sync_summary = ! $show_upgrade
			&& AddonSettings::is_enabled( 'remote_sync' )
			&& class_exists( \TaxoCSIE\Premium\PremiumAccess::class )
			&& ( new \TaxoCSIE\Premium\PremiumAccess() )->can_use_feature( 'scheduled_remote_csv_sync' );
		$sync_config = $show_sync_summary ? get_option( 'taxocsie_remote_sync_config', array() ) : array();
		$sync_config = is_array( $sync_config ) ? $sync_config : array();
		$sync_logs = $show_sync_summary ? get_option( 'taxocsie_remote_sync_log', array() ) : array();
		$sync_logs = is_array( $sync_logs ) ? $sync_logs : array();
		$last_sync = isset( $sync_logs[0] ) && is_array( $sync_logs[0] ) ? $sync_logs[0] : array();
		$sync_scheduler_ready = function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_has_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' )
			&& did_action( 'action_scheduler_init' );
		$sync_status_labels = array(
			'success' => __( 'Completed', 'taxonomy-csv-import-export' ),
			'partial' => __( 'Completed with skipped rows', 'taxonomy-csv-import-export' ),
			'fetch_error' => __( 'Fetch failed', 'taxonomy-csv-import-export' ),
			'size_error' => __( 'File too large', 'taxonomy-csv-import-export' ),
			'csv_open_error' => __( 'CSV could not be opened', 'taxonomy-csv-import-export' ),
			'csv_header_error' => __( 'Invalid CSV header', 'taxonomy-csv-import-export' ),
			'csv_limit_or_columns_error' => __( 'CSV row limit or columns invalid', 'taxonomy-csv-import-export' ),
		);
		$last_sync_status = sanitize_key( $last_sync['status'] ?? '' );
		$pro_features = array(
			__( 'Advanced CSV import with dry-run diagnostics and import modes', 'taxonomy-csv-import-export' ),
			__( 'Excel and JSON import and export', 'taxonomy-csv-import-export' ),
			__( 'Term images with Media Library controls', 'taxonomy-csv-import-export' ),
			__( 'Drag-and-drop term ordering', 'taxonomy-csv-import-export' ),
			__( 'Duplicate term finder, merge, and restore', 'taxonomy-csv-import-export' ),
			__( 'Scheduled remote CSV sync with authentication and run history', 'taxonomy-csv-import-export' ),
			__( 'Custom term fields for text, color, image, select, and URL values', 'taxonomy-csv-import-export' ),
			__( 'AI-assisted tag suggestions from existing terms', 'taxonomy-csv-import-export' ),
			__( 'WooCommerce brand logos and attribute swatches (WooCommerce required)', 'taxonomy-csv-import-export' ),
		);

		$manager_url = admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-manager' );
		$actions     = array(
			array(
				'label'       => __( 'Export taxonomy', 'taxonomy-csv-import-export' ),
				'description' => __( 'Preview and download taxonomy terms as CSV.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-download',
				'url'         => add_query_arg( 'manager_view', 'export', $manager_url ),
			),
			array(
				'label'       => __( 'Import taxonomy', 'taxonomy-csv-import-export' ),
				'description' => __( 'Upload taxonomy terms from a CSV file.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-upload',
				'url'         => add_query_arg( 'manager_view', 'import', $manager_url ),
			),
			array(
				'label'       => __( 'Manage users', 'taxonomy-csv-import-export' ),
				'description' => __( 'Import or export WordPress users by role.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-groups',
				'url'         => add_query_arg( 'manager_view', 'users', $manager_url ),
			),
			array(
				'label'       => __( 'Create a term', 'taxonomy-csv-import-export' ),
				'description' => __( 'Add a new term to any available taxonomy.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-plus-alt2',
				'url'         => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-create' ),
			),
			array(
				'label'       => __( 'All terms', 'taxonomy-csv-import-export' ),
				'description' => __( 'Search, edit, and organize terms across taxonomies.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-category',
				'url'         => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-all-terms' ),
			),
			array(
				'label'       => __( 'Generate demo terms', 'taxonomy-csv-import-export' ),
				'description' => __( 'Create realistic sample terms for testing.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-lightbulb',
				'url'         => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-demo' ),
			),
			array(
				'label'       => __( 'Register taxonomy', 'taxonomy-csv-import-export' ),
				'description' => __( 'Build a custom taxonomy without writing code.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-tag',
				'url'         => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-taxonomy' ),
			),
			array(
				'label'       => __( 'Settings', 'taxonomy-csv-import-export' ),
				'description' => __( 'Adjust import limits and admin preferences.', 'taxonomy-csv-import-export' ),
				'icon'        => 'dashicons-admin-generic',
				'url'         => admin_url( 'admin.php?page=' . Settings::PAGE ),
			),
		);
		?>
		<div class="taxocsie-wrap taxocsie-dashboard">
			<?php if ( $show_upgrade && '' !== $upgrade_url ) : ?>
				<div class="taxocsie-dashboard__upgrade" role="region" aria-label="<?php esc_attr_e( 'Upgrade to WP Taxonomy Manager Pro', 'taxonomy-csv-import-export' ); ?>">
					<p><strong><?php esc_html_e( 'You are using WP Taxonomy Manager Free.', 'taxonomy-csv-import-export' ); ?></strong> <?php esc_html_e( 'Upgrade to Pro for advanced imports, term media, automation, and WooCommerce tools.', 'taxonomy-csv-import-export' ); ?></p>
					<a class="taxocsie-dashboard__upgrade-button" href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a>
				</div>
			<?php endif; ?>
			<section class="taxocsie-getting-started" aria-labelledby="taxocsie-getting-started-title">
				<h2 id="taxocsie-getting-started-title"><?php esc_html_e( 'Moving categories to another site?', 'taxonomy-csv-import-export' ); ?></h2>
				<ol>
					<li><span>1</span><?php esc_html_e( 'Export Categories on the original site.', 'taxonomy-csv-import-export' ); ?></li>
					<li><span>2</span><?php esc_html_e( 'Import that CSV into Categories on the new site.', 'taxonomy-csv-import-export' ); ?></li>
					<li><span>3</span><?php esc_html_e( 'Check the results in All Terms.', 'taxonomy-csv-import-export' ); ?></li>
				</ol>
				<p class="taxocsie-getting-started__note"><?php esc_html_e( 'CSV moves the category names and details. It does not move posts or their category assignments.', 'taxonomy-csv-import-export' ); ?></p>
				<a href="<?php echo esc_url( add_query_arg( 'manager_view', 'export', $manager_url ) ); ?>"><?php esc_html_e( 'Start with export', 'taxonomy-csv-import-export' ); ?> <span aria-hidden="true">&rarr;</span></a>
			</section>

			<?php if ( $show_upgrade ) : ?>
				<div class="taxocsie-dashboard__layout">
					<main class="taxocsie-dashboard__main">
			<?php endif; ?>

			<div class="taxocsie-dashboard__metrics" aria-label="<?php esc_attr_e( 'Activity overview', 'taxonomy-csv-import-export' ); ?>">
				<?php
				$metric_cards = array(
					array(
						'label' => __( 'Exported items', 'taxonomy-csv-import-export' ),
						'value' => $stats['exported_items'],
						'note'  => __( 'Terms and users downloaded', 'taxonomy-csv-import-export' ),
						'icon'  => 'dashicons-download',
						'tone'  => 'brand',
					),
					array(
						'label' => __( 'Imported items', 'taxonomy-csv-import-export' ),
						'value' => $stats['imported_items'],
						'note'  => __( 'Terms and users processed', 'taxonomy-csv-import-export' ),
						'icon'  => 'dashicons-upload',
						'tone'  => 'cyan',
					),
					array(
						'label' => __( 'Created / generated', 'taxonomy-csv-import-export' ),
						'value' => $stats['created_items'],
						'note'  => sprintf(
							/* translators: %s: Total number of taxonomy terms. */
							__( '%s total terms available', 'taxonomy-csv-import-export' ),
							number_format_i18n( $stats['total_terms'] )
						),
						'icon'  => 'dashicons-plus-alt2',
						'tone'  => 'emerald',
					),
					array(
						'label' => __( 'Registered taxonomies', 'taxonomy-csv-import-export' ),
						'value' => $stats['registered_taxonomies'],
						'note'  => sprintf(
							/* translators: %s: Number of available public taxonomies. */
							__( '%s public taxonomies available', 'taxonomy-csv-import-export' ),
							number_format_i18n( $stats['available_taxonomies'] )
						),
						'icon'  => 'dashicons-tag',
						'tone'  => 'amber',
					),
				);

				foreach ( $metric_cards as $card ) :
					?>
					<div class="taxocsie-dashboard__metric taxocsie-dashboard__metric--<?php echo esc_attr( $card['tone'] ); ?>">
						<div class="taxocsie-dashboard__metric-top">
							<div>
								<p class="taxocsie-dashboard__metric-label"><?php echo esc_html( $card['label'] ); ?></p>
								<p class="taxocsie-dashboard__metric-value"><?php echo esc_html( number_format_i18n( $card['value'] ) ); ?></p>
							</div>
							<div class="taxocsie-dashboard__metric-icon">
								<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
							</div>
						</div>
						<p class="taxocsie-dashboard__metric-note"><?php echo esc_html( $card['note'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $show_sync_summary ) : ?>
				<section class="taxocsie-dashboard__panel taxocsie-dashboard__sync" aria-labelledby="taxocsie-dashboard-sync-title">
					<div class="taxocsie-dashboard__panel-header">
						<div>
							<h2 id="taxocsie-dashboard-sync-title"><?php esc_html_e( 'Remote CSV Sync', 'taxonomy-csv-import-export' ); ?></h2>
							<p><?php esc_html_e( 'Schedule and latest import result at a glance.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-remote-sync' ) ); ?>"><?php esc_html_e( 'Manage sync', 'taxonomy-csv-import-export' ); ?></a>
					</div>
					<div class="taxocsie-dashboard__sync-grid">
						<div>
							<p><?php esc_html_e( 'Schedule', 'taxonomy-csv-import-export' ); ?></p>
							<strong><?php echo esc_html( ! empty( $sync_config['enabled'] ) ? ( $sync_scheduler_ready ? __( 'Active', 'taxonomy-csv-import-export' ) : __( 'Scheduler unavailable', 'taxonomy-csv-import-export' ) ) : __( 'Paused', 'taxonomy-csv-import-export' ) ); ?></strong>
						</div>
						<div>
							<p><?php esc_html_e( 'Last run', 'taxonomy-csv-import-export' ); ?></p>
							<strong><?php echo ! empty( $last_sync['time'] ) ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_sync['time'] ) ) : esc_html__( 'Not yet run', 'taxonomy-csv-import-export' ); ?></strong>
						</div>
						<div>
							<p><?php esc_html_e( 'Last result', 'taxonomy-csv-import-export' ); ?></p>
							<strong><?php echo esc_html( $last_sync ? ( $sync_status_labels[ $last_sync_status ] ?? ucwords( str_replace( '_', ' ', $last_sync_status ) ) ) : __( 'No result yet', 'taxonomy-csv-import-export' ) ); ?></strong>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<section class="taxocsie-dashboard__panel taxocsie-dashboard__actions" aria-labelledby="taxocsie-dashboard-actions-title">
				<div class="taxocsie-dashboard__panel-header">
					<div>
						<h2 id="taxocsie-dashboard-actions-title"><?php esc_html_e( 'Quick actions', 'taxonomy-csv-import-export' ); ?></h2>
						<p><?php esc_html_e( 'Jump directly to the task you want to complete.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
					<span class="taxocsie-dashboard__section-label"><?php esc_html_e( 'Data Manager tools', 'taxonomy-csv-import-export' ); ?></span>
				</div>

				<div class="taxocsie-dashboard__action-grid">
					<?php foreach ( $actions as $action ) : ?>
						<a class="taxocsie-dashboard__action" href="<?php echo esc_url( $action['url'] ); ?>">
							<span class="taxocsie-dashboard__action-icon">
								<span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>" aria-hidden="true"></span>
							</span>
							<span class="taxocsie-dashboard__action-copy">
								<strong><?php echo esc_html( $action['label'] ); ?></strong>
								<span><?php echo esc_html( $action['description'] ); ?></span>
							</span>
							<span class="dashicons dashicons-arrow-right-alt2 taxocsie-dashboard__action-arrow" aria-hidden="true"></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>

			<?php if ( $show_upgrade ) : ?>
					</main>

					<aside class="taxocsie-pro-card" aria-labelledby="taxocsie-pro-card-title">
						<div class="taxocsie-pro-card__header">
							<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
							<h2 id="taxocsie-pro-card-title"><?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?></h2>
						</div>
						<div class="taxocsie-pro-card__body">
							<p><?php esc_html_e( 'Manage complex taxonomy workflows with these Pro tools:', 'taxonomy-csv-import-export' ); ?></p>
							<ul>
								<?php foreach ( $pro_features as $feature ) : ?>
									<li>
										<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
										<span><?php echo esc_html( $feature ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( '' !== $upgrade_url ) : ?>
								<a class="taxocsie-pro-card__button" href="<?php echo esc_url( $upgrade_url ); ?>">
									<?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?>
									<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
								</a>
							<?php endif; ?>
						</div>
					</aside>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Preserve only list filters after a term action; never trust a posted URL. */
	private function terms_return_url( array $source, string $notice ): string {
		$args = array( 'page' => TAXOCSIE_PLUGIN_SLUG . '-all-terms', 'taxocsie_notice' => $notice );
		if ( ! empty( $source['list_taxonomy'] ) ) {
			$args['taxonomy'] = sanitize_key( wp_unslash( $source['list_taxonomy'] ) );
		}
		if ( ! empty( $source['list_search'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $source['list_search'] ) );
		}
		if ( ! empty( $source['list_paged'] ) ) {
			$args['paged'] = max( 1, absint( $source['list_paged'] ) );
		}
		if ( ! empty( $source['list_per_page'] ) && in_array( absint( $source['list_per_page'] ), array( 5, 10, 20, 50 ), true ) ) {
			$args['per_page'] = absint( $source['list_per_page'] );
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** Verify a posted term belongs to an editable public taxonomy. */
	private function posted_term( string $capability ): \WP_Term {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot perform this action.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		$taxonomy_name = isset( $_POST['taxonomy_name'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy_name'] ) ) : '';
		$term_id       = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
		$taxonomy      = get_taxonomy( $taxonomy_name );
		if ( ! $term_id || ! $taxonomy || ! $taxonomy->public || ! current_user_can( $taxonomy->cap->$capability ) ) {
			wp_die( esc_html__( 'You cannot manage this term.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'taxocsie_' . ( 'delete_terms' === $capability ? 'delete' : 'update' ) . '_term_' . $term_id );
		$term = get_term( $term_id, $taxonomy_name );
		if ( ! $term instanceof \WP_Term ) {
			wp_die( esc_html__( 'Term not found.', 'taxonomy-csv-import-export' ), '', array( 'response' => 404 ) );
		}
		return $term;
	}

	public function handle_update_term(): void {
		$term = $this->posted_term( 'edit_terms' );
		$name = isset( $_POST['term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';
		$slug = isset( $_POST['term_slug'] ) ? sanitize_title( wp_unslash( $_POST['term_slug'] ) ) : '';
		$description = isset( $_POST['term_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['term_description'] ) ) : '';
		$result = '' !== $name && '' !== $slug
			? wp_update_term( $term->term_id, $term->taxonomy, array( 'name' => $name, 'slug' => $slug, 'description' => $description ) )
			: new \WP_Error( 'required_fields' );
		wp_safe_redirect( $this->terms_return_url( $_POST, is_wp_error( $result ) ? 'update_error' : 'updated' ) );
		exit;
	}

	public function handle_delete_term(): void {
		$term = $this->posted_term( 'delete_terms' );
		$result = wp_delete_term( $term->term_id, $term->taxonomy );
		wp_safe_redirect( $this->terms_return_url( $_POST, ( is_wp_error( $result ) || ! $result ) ? 'delete_error' : 'deleted' ) );
		exit;
	}

	/** Delete only explicitly checked terms after verifying every term's taxonomy. */
	public function handle_bulk_delete_terms(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot perform this action.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'taxocsie_bulk_delete_terms' );
		$posted_ids = isset( $_POST['term_ids'] ) && is_array( $_POST['term_ids'] ) ? wp_unslash( $_POST['term_ids'] ) : array();
		$ids = array();
		foreach ( $posted_ids as $posted_id ) {
			if ( ! is_scalar( $posted_id ) || ! ctype_digit( (string) $posted_id ) ) {
				wp_safe_redirect( $this->terms_return_url( $_POST, 'bulk_error' ) );
				exit;
			}
			$ids[] = absint( $posted_id );
		}
		$ids = array_unique( $ids );
		if ( ! $ids || count( $ids ) > 50 || in_array( 0, $ids, true ) ) {
			wp_safe_redirect( $this->terms_return_url( $_POST, 'bulk_error' ) );
			exit;
		}
		$terms = array();
		foreach ( $ids as $id ) {
			$term = get_term( $id );
			$taxonomy = $term instanceof \WP_Term ? get_taxonomy( $term->taxonomy ) : null;
			if ( ! $taxonomy || ! $taxonomy->public || ! current_user_can( $taxonomy->cap->delete_terms ) ) {
				wp_die( esc_html__( 'You cannot delete one or more selected terms.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
			}
			$terms[] = $term;
		}
		$deleted = 0;
		foreach ( $terms as $term ) {
			$result = wp_delete_term( $term->term_id, $term->taxonomy );
			if ( ! is_wp_error( $result ) && $result ) {
				++$deleted;
			}
		}
		$url = $this->terms_return_url( $_POST, $deleted === count( $terms ) ? 'bulk_deleted' : 'bulk_partial' );
		wp_safe_redirect( add_query_arg( array( 'deleted_count' => $deleted, 'requested_count' => count( $terms ) ), $url ) );
		exit;
	}

	/** Search and manage public taxonomy terms without loading the entire catalog. */
	public function get_taxocsie_all_terms_function(): void {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$selected_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page size.
		$requested_per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 10;
		if ( ! isset( $taxonomies[ $selected_taxonomy ] ) ) {
			$selected_taxonomy = '';
		}
		$taxonomy_names = $selected_taxonomy ? array( $selected_taxonomy ) : array_keys( $taxonomies );
		$per_page = in_array( $requested_per_page, array( 5, 10, 20, 50 ), true ) ? $requested_per_page : 10;
		$query = array( 'taxonomy' => $taxonomy_names, 'hide_empty' => false, 'search' => $search );
		$total = $taxonomy_names ? get_terms( $query + array( 'fields' => 'count' ) ) : 0;
		$total = is_wp_error( $total ) ? 0 : (int) $total;
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged = min( $paged, $pages );
		$terms = $taxonomy_names ? get_terms( $query + array( 'number' => $per_page, 'offset' => ( $paged - 1 ) * $per_page, 'orderby' => 'name', 'order' => 'ASC' ) ) : array();
		$terms = is_wp_error( $terms ) ? array() : $terms;
		$list_url = add_query_arg( array_filter( array( 'page' => TAXOCSIE_PLUGIN_SLUG . '-all-terms', 'taxonomy' => $selected_taxonomy, 's' => $search, 'per_page' => $per_page ) ), admin_url( 'admin.php' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status following a nonce-checked POST.
		$notice = isset( $_GET['taxocsie_notice'] ) ? sanitize_key( wp_unslash( $_GET['taxocsie_notice'] ) ) : '';
		$messages = array(
			'updated' => __( 'Term updated successfully.', 'taxonomy-csv-import-export' ),
			'deleted' => __( 'Term deleted successfully.', 'taxonomy-csv-import-export' ),
			'update_error' => __( 'Could not update the term. Check its name and slug, then try again.', 'taxonomy-csv-import-export' ),
			'delete_error' => __( 'Could not delete this term. It may be protected by WordPress.', 'taxonomy-csv-import-export' ),
			'bulk_error' => __( 'Select up to 50 terms on this page before deleting.', 'taxonomy-csv-import-export' ),
			'bulk_deleted' => __( 'Selected terms deleted successfully.', 'taxonomy-csv-import-export' ),
			'bulk_partial' => __( 'Some selected terms could not be deleted. WordPress may protect them.', 'taxonomy-csv-import-export' ),
		);
		?>
		<div class="taxocsie-wrap taxocsie-all-terms tw-py-6 tw-pr-5">
			<div class="taxocsie-help-strip" role="note"><strong><?php esc_html_e( 'Looking for a category?', 'taxonomy-csv-import-export' ); ?></strong><span><?php esc_html_e( 'Choose Categories in the taxonomy filter. Use Edit on a row for its name, slug or description; use Advanced edit for its parent.', 'taxonomy-csv-import-export' ); ?></span></div>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-unused' ) ); ?>"><?php esc_html_e( 'Review unused terms', 'taxonomy-csv-import-export' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-redirects' ) ); ?>"><?php esc_html_e( 'View term redirects', 'taxonomy-csv-import-export' ); ?></a></p>
			<?php if ( isset( $messages[ $notice ] ) ) : ?>
				<div class="notice <?php echo esc_attr( in_array( $notice, array( 'updated', 'deleted', 'bulk_deleted' ), true ) ? 'notice-success' : 'notice-error' ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $notice ] ); ?><?php if ( in_array( $notice, array( 'bulk_deleted', 'bulk_partial' ), true ) ) : ?> <?php echo esc_html( sprintf( /* translators: 1: deleted terms, 2: selected terms. */ __( '%1$d of %2$d deleted.', 'taxonomy-csv-import-export' ), isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0, isset( $_GET['requested_count'] ) ? absint( $_GET['requested_count'] ) : 0 ) ); ?><?php endif; ?></p></div>
			<?php endif; ?>
			<div class="taxocsie-panel taxocsie-all-terms__panel">
				<div class="taxocsie-all-terms__toolbar">
					<div><h2><?php esc_html_e( 'Registered terms', 'taxonomy-csv-import-export' ); ?></h2><p><?php echo esc_html( sprintf( /* translators: %s: formatted number of terms. */ _n( '%s term found', '%s terms found', $total, 'taxonomy-csv-import-export' ), number_format_i18n( $total ) ) ); ?></p></div>
					<form method="get" class="taxocsie-all-terms__filters">
						<input type="hidden" name="page" value="<?php echo esc_attr( TAXOCSIE_PLUGIN_SLUG . '-all-terms' ); ?>">
						<input type="hidden" name="per_page" value="<?php echo esc_attr( $per_page ); ?>">
						<label class="screen-reader-text" for="taxocsie-term-search"><?php esc_html_e( 'Search terms', 'taxonomy-csv-import-export' ); ?></label>
						<input id="taxocsie-term-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search terms…', 'taxonomy-csv-import-export' ); ?>">
						<label class="screen-reader-text" for="taxocsie-filter-taxonomy"><?php esc_html_e( 'Filter by taxonomy', 'taxonomy-csv-import-export' ); ?></label>
						<select id="taxocsie-filter-taxonomy" name="taxonomy"><option value=""><?php esc_html_e( 'All taxonomies', 'taxonomy-csv-import-export' ); ?></option><?php foreach ( $taxonomies as $taxonomy ) : ?><option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $selected_taxonomy, $taxonomy->name ); ?>><?php echo esc_html( $taxonomy->label ); ?></option><?php endforeach; ?></select>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'taxonomy-csv-import-export' ); ?></button>
						<?php if ( $search || $selected_taxonomy ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-all-terms' ) ); ?>"><?php esc_html_e( 'Clear', 'taxonomy-csv-import-export' ); ?></a><?php endif; ?>
					</form>
				</div>
				<div class="taxocsie-all-terms__controls taxocsie-table-controls" aria-label="<?php esc_attr_e( 'Terms table controls', 'taxonomy-csv-import-export' ); ?>">
					<div class="taxocsie-table-total-wrap"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span><?php esc_html_e( 'total', 'taxonomy-csv-import-export' ); ?></span></div>
					<form id="taxocsie-terms-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php /* translators: %d: number of selected terms. */ esc_attr_e( 'Delete %d selected terms? Their associations with content will be removed. This cannot be undone.', 'taxonomy-csv-import-export' ); ?>"><input type="hidden" name="action" value="taxocsie_bulk_delete_terms"><input type="hidden" name="list_taxonomy" value="<?php echo esc_attr( $selected_taxonomy ); ?>"><input type="hidden" name="list_search" value="<?php echo esc_attr( $search ); ?>"><input type="hidden" name="list_paged" value="<?php echo esc_attr( $paged ); ?>"><input type="hidden" name="list_per_page" value="<?php echo esc_attr( $per_page ); ?>"><?php wp_nonce_field( 'taxocsie_bulk_delete_terms' ); ?><button type="submit" class="button taxocsie-delete-selected" disabled><span class="dashicons dashicons-trash" aria-hidden="true"></span><span><?php esc_html_e( 'Delete selected', 'taxonomy-csv-import-export' ); ?></span><span class="taxocsie-delete-selected__count">0</span></button></form>
				<div class="taxocsie-table-pagination"><label for="taxocsie-terms-per-page"><?php esc_html_e( 'Rows per page', 'taxonomy-csv-import-export' ); ?></label><select id="taxocsie-terms-per-page" class="taxocsie-table-per-page" aria-label="<?php esc_attr_e( 'Rows per page', 'taxonomy-csv-import-export' ); ?>"><?php foreach ( array( 5, 10, 20, 50 ) as $size ) : ?><option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option><?php endforeach; ?></select><span class="taxocsie-page-status"><span><?php echo esc_html( $paged ); ?></span> <?php esc_html_e( 'of', 'taxonomy-csv-import-export' ); ?> <span><?php echo esc_html( $pages ); ?></span></span><div class="taxocsie-pagination-buttons"><button type="button" class="taxocsie-terms-page-prev" aria-label="<?php esc_attr_e( 'Previous page', 'taxonomy-csv-import-export' ); ?>" data-url="<?php echo esc_url( add_query_arg( 'paged', max( 1, $paged - 1 ), $list_url ) ); ?>" <?php disabled( $paged <= 1 ); ?>><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button><button type="button" class="taxocsie-terms-page-next" aria-label="<?php esc_attr_e( 'Next page', 'taxonomy-csv-import-export' ); ?>" data-url="<?php echo esc_url( add_query_arg( 'paged', min( $pages, $paged + 1 ), $list_url ) ); ?>" <?php disabled( $paged >= $pages ); ?>><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button></div></div>
				</div>
				<div class="taxocsie-all-terms__scroll"><table class="wp-list-table widefat taxocsie-all-terms__table">
					<thead><tr><th scope="col" class="taxocsie-select-column"><input type="checkbox" class="taxocsie-terms-select-all" aria-label="<?php esc_attr_e( 'Select all terms on this page', 'taxonomy-csv-import-export' ); ?>" <?php disabled( ! $terms ); ?>></th><th scope="col"><?php esc_html_e( 'Term', 'taxonomy-csv-import-export' ); ?></th><th scope="col"><?php esc_html_e( 'Taxonomy', 'taxonomy-csv-import-export' ); ?></th><th scope="col"><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?></th><th scope="col"><?php esc_html_e( 'Parent', 'taxonomy-csv-import-export' ); ?></th><th scope="col"><?php esc_html_e( 'Count', 'taxonomy-csv-import-export' ); ?></th><th scope="col"><?php esc_html_e( 'Actions', 'taxonomy-csv-import-export' ); ?></th></tr></thead>
					<tbody>
					<?php if ( ! $terms ) : ?><tr><td colspan="7" class="taxocsie-all-terms__empty"><span class="dashicons dashicons-search" aria-hidden="true"></span><strong><?php esc_html_e( 'No terms found', 'taxonomy-csv-import-export' ); ?></strong><span><?php esc_html_e( 'Try a different search or taxonomy, or create a new term.', 'taxonomy-csv-import-export' ); ?></span></td></tr><?php endif; ?>
					<?php foreach ( $terms as $term ) :
						$taxonomy = $taxonomies[ $term->taxonomy ];
						$parent = $term->parent ? get_term( $term->parent, $term->taxonomy ) : null;
						$can_edit = current_user_can( $taxonomy->cap->edit_terms );
						$can_delete = current_user_can( $taxonomy->cap->delete_terms );
						$edit_id = 'taxocsie-term-edit-' . $term->term_id;
						$image_id = class_exists( 'TaxoCSIE\\Premium\\TermImageManager' )
							? \TaxoCSIE\Premium\TermImageManager::get_attachment_id( (int) $term->term_id, $term->taxonomy )
							: 0;
						$image_html = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'taxocsie-all-terms__image', 'alt' => '', 'loading' => 'lazy' ) ) : '';
					?>
					<tr class="taxocsie-all-terms__row">
						<td class="taxocsie-select-column"><input type="checkbox" class="taxocsie-term-select" form="taxocsie-terms-bulk-form" name="term_ids[]" value="<?php echo esc_attr( $term->term_id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: term or taxonomy name. */ __( 'Select %s', 'taxonomy-csv-import-export' ), $term->name ) ); ?>" <?php disabled( ! $can_delete ); ?>></td>
						<td><span class="taxocsie-all-terms__title"><?php echo wp_kses_post( $image_html ); ?><strong><?php echo esc_html( $term->name ); ?></strong></span><?php if ( $term->description ) : ?><span class="taxocsie-all-terms__description"><?php echo esc_html( wp_trim_words( $term->description, 14 ) ); ?></span><?php endif; ?></td>
						<td><span class="taxocsie-all-terms__badge"><?php echo esc_html( $taxonomy->label ); ?></span></td>
						<td><code><?php echo esc_html( $term->slug ); ?></code></td>
						<td><?php echo esc_html( $parent instanceof \WP_Term ? $parent->name : '—' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $term->count ) ); ?></td>
						<td><div class="taxocsie-all-terms__actions">
							<?php if ( $can_edit ) : ?><button type="button" class="button-link taxocsie-term-edit-toggle" data-target="<?php echo esc_attr( $edit_id ); ?>" aria-controls="<?php echo esc_attr( $edit_id ); ?>" aria-expanded="false"><?php esc_html_e( 'Edit', 'taxonomy-csv-import-export' ); ?></button><?php endif; ?>
							<?php if ( $can_delete ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="taxocsie-term-delete-form" data-confirm="<?php esc_attr_e( 'Delete this term? This removes its associations with content. This cannot be undone.', 'taxonomy-csv-import-export' ); ?>"><input type="hidden" name="action" value="taxocsie_delete_term"><input type="hidden" name="term_id" value="<?php echo esc_attr( $term->term_id ); ?>"><input type="hidden" name="taxonomy_name" value="<?php echo esc_attr( $term->taxonomy ); ?>"><input type="hidden" name="list_taxonomy" value="<?php echo esc_attr( $selected_taxonomy ); ?>"><input type="hidden" name="list_search" value="<?php echo esc_attr( $search ); ?>"><input type="hidden" name="list_paged" value="<?php echo esc_attr( $paged ); ?>"><input type="hidden" name="list_per_page" value="<?php echo esc_attr( $per_page ); ?>"><?php wp_nonce_field( 'taxocsie_delete_term_' . $term->term_id ); ?><button type="submit" class="button-link taxocsie-all-terms__delete"><?php esc_html_e( 'Delete', 'taxonomy-csv-import-export' ); ?></button></form><?php endif; ?>
						</div></td>
					</tr>
					<?php if ( $can_edit ) : ?><tr id="<?php echo esc_attr( $edit_id ); ?>" class="taxocsie-term-edit-row" hidden><td colspan="7"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="taxocsie_update_term"><input type="hidden" name="term_id" value="<?php echo esc_attr( $term->term_id ); ?>"><input type="hidden" name="taxonomy_name" value="<?php echo esc_attr( $term->taxonomy ); ?>"><input type="hidden" name="list_taxonomy" value="<?php echo esc_attr( $selected_taxonomy ); ?>"><input type="hidden" name="list_search" value="<?php echo esc_attr( $search ); ?>"><input type="hidden" name="list_paged" value="<?php echo esc_attr( $paged ); ?>"><input type="hidden" name="list_per_page" value="<?php echo esc_attr( $per_page ); ?>"><?php wp_nonce_field( 'taxocsie_update_term_' . $term->term_id ); ?>
						<div class="taxocsie-term-edit-fields"><label><?php esc_html_e( 'Name', 'taxonomy-csv-import-export' ); ?><input type="text" name="term_name" value="<?php echo esc_attr( $term->name ); ?>" required></label><label><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?><input type="text" name="term_slug" value="<?php echo esc_attr( $term->slug ); ?>" required></label><label class="taxocsie-term-edit-fields__description"><?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?><textarea name="term_description" rows="2"><?php echo esc_textarea( $term->description ); ?></textarea></label></div>
						<div class="taxocsie-term-edit-footer"><span><?php esc_html_e( 'To change the parent or advanced fields, use WordPress edit.', 'taxonomy-csv-import-export' ); ?> <?php $native_url = get_edit_term_link( $term->term_id, $term->taxonomy ); if ( $native_url ) : ?><a href="<?php echo esc_url( $native_url ); ?>"><?php esc_html_e( 'Advanced edit', 'taxonomy-csv-import-export' ); ?></a><?php endif; ?></span><div><button type="button" class="button taxocsie-term-edit-cancel"><?php esc_html_e( 'Cancel', 'taxonomy-csv-import-export' ); ?></button> <button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'taxonomy-csv-import-export' ); ?></button></div></div></form></td></tr><?php endif; ?>
					<?php endforeach; ?>
					</tbody></table></div>
			</div>
		</div>
		<?php
	}

	/* -------------------------------------------------------
	   Add-ons
	------------------------------------------------------- */
	public function get_taxocsie_addons_function(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'taxonomy-csv-import-export' ) );
		}

		/**
		 * Filters the add-ons displayed in the catalog.
		 *
		 * Each add-on may contain name, description, icon, and url values.
		 * Add-ons are intentionally registered separately from this page so the
		 * catalog can be populated when the product list is ready.
		 *
		 * @since 1.5.0
		 *
		 * @param array<int, array<string, string>> $addons Add-on definitions.
		 */
		$addons = apply_filters( 'taxocsie_addons_catalog', array(
			array(
				'key' => 'acf',
				'name' => __( 'ACF Term Fields', 'taxonomy-csv-import-export' ),
				'description' => __( 'Import and export ACF taxonomy fields with CSV. Supports scalar fields and local Media Library image IDs.', 'taxonomy-csv-import-export' ),
				'icon' => 'dashicons-admin-generic',
				'feature' => 'advanced_csv_import',
				'requires_acf' => true,
				'url' => admin_url( 'admin.php?page=taxocsie-term-meta-transfer' ),
			),
			array(
				'key' => 'duplicate_terms',
				'name' => __( 'Duplicate Terms', 'taxonomy-csv-import-export' ),
				'description' => __( 'Find likely duplicates, preview the impact, and merge with an audit trail and restore action.', 'taxonomy-csv-import-export' ),
				'icon' => 'dashicons-admin-page',
				'feature' => 'duplicate_term_merge',
				'url' => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-duplicates' ),
			),
			array(
				'key' => 'remote_sync',
				'name' => __( 'Remote CSV Sync', 'taxonomy-csv-import-export' ),
				'description' => __( 'Schedule authenticated CSV imports and review recent runs.', 'taxonomy-csv-import-export' ),
				'icon' => 'dashicons-update',
				'feature' => 'scheduled_remote_csv_sync',
				'url' => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-remote-sync' ),
			),
			array(
				'key' => 'woocommerce',
				'name' => __( 'WooCommerce Brands & Swatches', 'taxonomy-csv-import-export' ),
				'description' => __( 'Configure brand logos and product attribute colors or images in Term Fields.', 'taxonomy-csv-import-export' ),
				'icon' => 'dashicons-products',
				'feature' => 'dynamic_term_meta',
				'requires_woocommerce' => true,
				'url' => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-term-fields' ),
			),
		) );
		$addons = is_array( $addons ) ? $addons : array();

		$has_premium = Freemius::can_use_premium_code();
		$access = class_exists( 'TaxoCSIE\\Premium\\PremiumAccess' ) ? new \TaxoCSIE\Premium\PremiumAccess() : null;
		$upgrade_url = Freemius::get_upgrade_url();
		?>
		<div class="taxocsie-wrap taxocsie-addons-page tw-py-6">
			<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['updated'] ) ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Add-on setting saved.', 'taxonomy-csv-import-export' ); ?></p></div>
			<?php endif; ?>

			<section class="taxocsie-addons-catalog tw-mt-5 tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft" aria-labelledby="taxocsie-addons-title">
				<div class="tw-flex tw-items-center tw-justify-between tw-gap-4 tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
					<div>
						<h2 id="taxocsie-addons-title" class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900"><?php esc_html_e( 'Add-on catalog', 'taxonomy-csv-import-export' ); ?></h2>
						<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Open a tool from its card. If it is unavailable, check its license and required plugin.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
					<a class="tw-shrink-0 tw-rounded-full tw-bg-brand-100 tw-px-3 tw-py-1 tw-text-xs tw-font-semibold tw-text-brand-700 tw-no-underline" href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Pro features', 'taxonomy-csv-import-export' ); ?></a>
				</div>

				<?php if ( empty( $addons ) ) : ?>
					<div class="tw-flex tw-flex-col tw-items-center tw-gap-3 tw-p-5 sm:tw-p-6">
						<span class="tw-flex tw-h-11 tw-w-11 tw-items-center tw-justify-center tw-rounded-xl tw-bg-brand-100 tw-text-brand-700">
							<span class="dashicons dashicons-admin-plugins tw-text-[22px]" aria-hidden="true"></span>
						</span>
						<div>
							<h3 class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900"><?php esc_html_e( 'Add-ons are coming soon', 'taxonomy-csv-import-export' ); ?></h3>
							<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'The catalog is ready and will be populated as add-ons become available.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
						<?php if ( ! $has_premium && '' !== $upgrade_url ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'taxonomy-csv-import-export' ); ?></a>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="taxocsie-addons-grid">
						<?php foreach ( $addons as $addon ) : ?>
							<?php
							if ( ! is_array( $addon ) || empty( $addon['name'] ) ) {
								continue;
							}

							$name        = (string) $addon['name'];
							$description = isset( $addon['description'] ) ? (string) $addon['description'] : '';
							$icon        = isset( $addon['icon'] ) ? sanitize_html_class( $addon['icon'] ) : 'dashicons-admin-plugins';
							$addon_url   = isset( $addon['url'] ) ? esc_url( $addon['url'] ) : '';
							$addon_key   = isset( $addon['key'] ) ? sanitize_key( $addon['key'] ) : '';
							$toggleable  = AddonSettings::is_supported( $addon_key );
							$feature     = $toggleable ? AddonSettings::features()[ $addon_key ] : ( isset( $addon['feature'] ) ? sanitize_key( $addon['feature'] ) : '' );
							$unlocked    = $feature && $access ? $access->can_use_feature( $feature ) : $has_premium;
							$needs_woo   = ! empty( $addon['requires_woocommerce'] ) && ! class_exists( 'WooCommerce' );
							$needs_acf   = ! empty( $addon['requires_acf'] ) && ! AddonSettings::is_acf_available();
							$unavailable = $needs_woo || $needs_acf;
							$enabled     = ( $toggleable ? AddonSettings::is_enabled( $addon_key ) : true ) && ! $unavailable;
							?>
							<article class="taxocsie-addon-card"<?php echo $toggleable ? ' data-addon-card="' . esc_attr( $addon_key ) . '" data-requires-woocommerce="' . ( $needs_woo ? '1' : '0' ) . '" data-requires-acf="' . ( $needs_acf ? '1' : '0' ) . '"' : ''; ?>>
								<div class="taxocsie-addon-card__header">
									<span class="taxocsie-addon-card__icon">
										<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
									</span>
								<?php if ( $toggleable && $unlocked ) : ?>
									<div class="taxocsie-addon-card__status">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-addon-toggle-form data-addon-name="<?php echo esc_attr( $name ); ?>"<?php echo in_array( $addon_key, array( 'duplicate_terms', 'remote_sync' ), true ) ? ' data-menu-slug="' . esc_attr( TAXOCSIE_PLUGIN_SLUG . ( 'duplicate_terms' === $addon_key ? '-duplicates' : '-remote-sync' ) ) . '" data-menu-url="' . esc_url( $addon_url ) . '"' : ''; ?>>
											<input type="hidden" name="action" value="taxocsie_toggle_addon">
											<input type="hidden" name="addon" value="<?php echo esc_attr( $addon_key ); ?>">
											<input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
											<?php wp_nonce_field( 'taxocsie_toggle_addon_' . $addon_key ); ?>
											<button type="submit" class="taxocsie-addon-toggle<?php echo $enabled ? ' is-on' : ''; ?>" aria-pressed="<?php echo $enabled ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $needs_woo ? __( 'Activate WooCommerce to turn on this add-on', 'taxonomy-csv-import-export' ) : ( $needs_acf ? __( 'Activate Advanced Custom Fields to turn on this add-on', 'taxonomy-csv-import-export' ) : sprintf( $enabled ? /* translators: %s: add-on name. */ __( 'Turn off %s', 'taxonomy-csv-import-export' ) : /* translators: %s: add-on name. */ __( 'Turn on %s', 'taxonomy-csv-import-export' ), $name ) ) ); ?>" <?php disabled( $unavailable ); ?>><?php echo esc_html( $enabled ? __( 'On', 'taxonomy-csv-import-export' ) : __( 'Off', 'taxonomy-csv-import-export' ) ); ?></button>
										</form>
										<span class="taxocsie-addon-feedback tw-text-xs" role="status" aria-live="polite" data-addon-feedback></span>
									</div>
								<?php else : ?>
									<span class="taxocsie-addon-card__pro"><?php esc_html_e( 'Pro', 'taxonomy-csv-import-export' ); ?></span>
								<?php endif; ?>
								</div>
								<div class="taxocsie-addon-card__body">
									<div class="taxocsie-addon-card__title">
										<h3><?php echo esc_html( $name ); ?></h3>
										<?php if ( ! $unlocked && '' !== $upgrade_url ) : ?>
											<a class="taxocsie-addon-card__link" href="<?php echo esc_url( $upgrade_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: add-on name. */ __( 'Upgrade to use %s', 'taxonomy-csv-import-export' ), $name ) ); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
										<?php elseif ( '' !== $addon_url ) : ?>
											<a class="taxocsie-addon-card__link" <?php echo $toggleable ? 'data-addon-open ' : ''; ?>href="<?php echo esc_url( $addon_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: add-on name. */ __( 'Open %s', 'taxonomy-csv-import-export' ), $name ) ); ?>"<?php echo $toggleable && ( ! $enabled || $unavailable ) ? ' hidden' : ''; ?>><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
										<?php endif; ?>
									</div>
									<?php if ( '' !== $description ) : ?>
										<p><?php echo esc_html( $description ); ?></p>
									<?php endif; ?>
								</div>
								<?php if ( $toggleable && $unlocked ) : ?>
									<div class="taxocsie-addon-card__footer">
										<span class="taxocsie-addon-card__hint" data-addon-off<?php echo $enabled || $unavailable ? ' hidden' : ''; ?>><?php esc_html_e( 'Turn on to open this add-on', 'taxonomy-csv-import-export' ); ?></span>
										<span class="taxocsie-addon-card__hint taxocsie-addon-card__hint--warning" data-addon-needs-woo<?php echo ! $needs_woo ? ' hidden' : ''; ?>><?php esc_html_e( 'Activate WooCommerce to use this add-on', 'taxonomy-csv-import-export' ); ?></span>
										<span class="taxocsie-addon-card__hint taxocsie-addon-card__hint--warning" data-addon-needs-acf<?php echo ! $needs_acf ? ' hidden' : ''; ?>><?php esc_html_e( 'Activate Advanced Custom Fields to use this add-on', 'taxonomy-csv-import-export' ); ?></span>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/* -------------------------------------------------------
	   Create Term
	------------------------------------------------------- */
	public function get_taxocsie_create_term_function() {
		$taxonomies     = get_taxonomies( array( 'public' => true ), 'objects' );
		$first_taxonomy = ! empty( $taxonomies ) ? reset( $taxonomies ) : null;
		$term_images_available = class_exists( 'TaxoCSIE\\Premium\\TermImageManager' )
			&& \TaxoCSIE\Premium\TermImageManager::is_available();
		$upgrade_url = Freemius::get_upgrade_url();
		?>
		<div class="taxocsie-wrap taxocsie-create-page">
			<div class="taxocsie-create-shell">


			<div class="taxocsie-create-workspace" data-taxonomy="<?php echo $first_taxonomy ? esc_attr( $first_taxonomy->name ) : ''; ?>">
				<aside class="taxocsie-create-selector" aria-labelledby="taxocsie-taxonomy-heading">
					<div class="taxocsie-create-section-header">
						<span class="taxocsie-create-step">1</span>
						<div>
							<h2 id="taxocsie-taxonomy-heading"><?php esc_html_e( 'Choose a taxonomy', 'taxonomy-csv-import-export' ); ?></h2>
							<p><?php esc_html_e( 'Select where your new term belongs.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
					</div>

					<div class="taxocsie-taxonomy-list" role="listbox" aria-label="<?php esc_attr_e( 'Available taxonomies', 'taxonomy-csv-import-export' ); ?>">
						<?php foreach ( $taxonomies as $taxonomy ) :
							$is_selected = $first_taxonomy && $taxonomy->name === $first_taxonomy->name;
							$taxonomy_icon = $taxonomy->hierarchical ? 'dashicons-category' : 'dashicons-filter';

							if ( 'post_tag' === $taxonomy->name ) {
								$taxonomy_icon = 'dashicons-tag';
							} elseif ( 'post_format' === $taxonomy->name ) {
								$taxonomy_icon = 'dashicons-admin-appearance';
							}
							?>
							<button type="button" class="taxocsie-taxonomy-choice<?php echo $is_selected ? ' is-selected' : ''; ?>" data-taxonomy="<?php echo esc_attr( $taxonomy->name ); ?>" data-label="<?php echo esc_attr( $taxonomy->label ); ?>" role="option" aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>">
								<span class="taxocsie-taxonomy-choice__icon dashicons <?php echo esc_attr( $taxonomy_icon ); ?>" aria-hidden="true"></span>
								<span class="taxocsie-taxonomy-choice__content">
									<strong><?php echo esc_html( $taxonomy->label ); ?></strong>
									<code><?php echo esc_html( $taxonomy->name ); ?></code>
								</span>
								<span class="taxocsie-taxonomy-choice__check dashicons dashicons-yes-alt" aria-hidden="true"></span>
							</button>
						<?php endforeach; ?>
						<?php if ( empty( $taxonomies ) ) : ?>
							<p class="taxocsie-create-empty"><?php esc_html_e( 'No public taxonomies are available.', 'taxonomy-csv-import-export' ); ?></p>
						<?php endif; ?>
					</div>
				</aside>

				<section class="taxocsie-create-form" aria-labelledby="taxocsie-term-heading">
					<div class="taxocsie-create-section-header taxocsie-create-section-header--form">
						<span class="taxocsie-create-step">2</span>
						<div>
							<h2 id="taxocsie-term-heading"><?php esc_html_e( 'Create your term', 'taxonomy-csv-import-export' ); ?></h2>
							<p><?php esc_html_e( 'Adding to', 'taxonomy-csv-import-export' ); ?> <strong class="taxocsie-selected-taxonomy"><?php echo $first_taxonomy ? esc_html( $first_taxonomy->label ) : esc_html__( 'No taxonomy selected', 'taxonomy-csv-import-export' ); ?></strong></p>
						</div>
					</div>

					<div class="taxocsie-create-form__body">
						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="taxocsie-term-title"><?php esc_html_e( 'Term name', 'taxonomy-csv-import-export' ); ?> <span class="taxocsie-required">*</span></label>
							<input id="taxocsie-term-title" class="taxocsie-panel__input taxocsie-row-title" type="text" placeholder="<?php esc_attr_e( 'e.g. Product updates', 'taxonomy-csv-import-export' ); ?>" autocomplete="off">
							<span class="taxocsie-field-hint"><?php esc_html_e( 'Use a clear, concise name. WordPress will generate the slug.', 'taxonomy-csv-import-export' ); ?></span>
						</div>

						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="taxocsie-term-description"><?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?> <span class="taxocsie-optional"><?php esc_html_e( 'Optional', 'taxonomy-csv-import-export' ); ?></span></label>
							<textarea id="taxocsie-term-description" class="taxocsie-panel__textarea taxocsie-row-desc" rows="5" placeholder="<?php esc_attr_e( 'Explain what this term is used for...', 'taxonomy-csv-import-export' ); ?>"></textarea>
						</div>

						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="taxocsie-term-link"><?php esc_html_e( 'Custom link', 'taxonomy-csv-import-export' ); ?> <span class="taxocsie-optional"><?php esc_html_e( 'Optional', 'taxonomy-csv-import-export' ); ?></span></label>
							<div class="taxocsie-create-url-field">
								<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
								<input id="taxocsie-term-link" class="taxocsie-panel__input taxocsie-row-custom-link" type="url" inputmode="url" placeholder="https://example.com/page">
							</div>
							<span class="taxocsie-field-hint"><?php esc_html_e( 'Send visitors to a specific URL when they open this term.', 'taxonomy-csv-import-export' ); ?></span>
						</div>

						<div class="taxocsie-panel__field taxocsie-create-image-field">
							<div class="taxocsie-create-image-field__heading"><span class="taxocsie-panel__field-label"><?php esc_html_e( 'Term image', 'taxonomy-csv-import-export' ); ?> <span class="taxocsie-optional"><?php esc_html_e( 'Optional', 'taxonomy-csv-import-export' ); ?></span></span><span class="taxocsie-create-image-field__pro"><?php esc_html_e( 'PRO', 'taxonomy-csv-import-export' ); ?></span></div>
							<?php if ( $term_images_available ) : ?>
								<div class="taxocsie-term-image-field">
									<input type="hidden" name="taxocsie_term_image_id" value="" class="taxocsie-create-image-id">
									<div class="taxocsie-term-image-preview" hidden></div>
									<div class="taxocsie-term-image-actions"><button type="button" class="button taxocsie-select-term-image"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m3 17 5-5 4 4 3-3 6 6"></path></svg><?php esc_html_e( 'Choose image', 'taxonomy-csv-import-export' ); ?></button><button type="button" class="button taxocsie-remove-term-image taxocsie-create-image-remove" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"></path></svg><?php esc_html_e( 'Remove image', 'taxonomy-csv-import-export' ); ?></button></div>
								</div>
								<span class="taxocsie-field-hint"><?php esc_html_e( 'Choose or upload an image from the WordPress Media Library.', 'taxonomy-csv-import-export' ); ?></span>
							<?php else : ?>
								<div class="taxocsie-create-image-field__locked"><span class="dashicons dashicons-lock" aria-hidden="true"></span><span><?php esc_html_e( 'Add an image to this term with Pro.', 'taxonomy-csv-import-export' ); ?></span><?php if ( $upgrade_url ) : ?><a href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Unlock Pro', 'taxonomy-csv-import-export' ); ?></a><?php endif; ?></div>
							<?php endif; ?>
						</div>
					</div>

					<div class="taxocsie-create-form__footer">
						<div class="taxocsie-row-feedback" role="status" aria-live="polite" style="display:none;"></div>
						<button type="button" class="button button-primary taxocsie-panel__btn-submit taxocsie-row-create-btn" <?php disabled( empty( $taxonomies ) ); ?>>
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php esc_html_e( 'Create term', 'taxonomy-csv-import-export' ); ?>
						</button>
					</div>
				</section>
			</div>
			</div>

		</div>
		<?php
	}

	/* -------------------------------------------------------
	   Register Taxonomy
	------------------------------------------------------- */
	public function get_taxocsie_register_taxonomy_function() {
		$post_types       = get_post_types( array( 'public' => true ), 'objects' );
		$saved_taxonomies = TaxonomyCreator::get_saved();
		?>
		<div class="taxocsie-wrap taxocsie-register-page">

			<div class="taxocsie-help-strip" role="note"><strong><?php esc_html_e( 'Need a regular category?', 'taxonomy-csv-import-export' ); ?></strong><span><?php esc_html_e( 'Use Create Term and choose Categories. Register Taxonomy creates an entirely new group, such as Genres.', 'taxonomy-csv-import-export' ); ?></span></div>

			<div class="taxocsie-panel taxocsie-panel--mt taxocsie-register-list" id="taxocsie-reg-panel">
				<div class="taxocsie-register-list__header">
					<div>
						<h2><?php esc_html_e( 'Registered taxonomies', 'taxonomy-csv-import-export' ); ?></h2>
						<p><?php esc_html_e( 'Custom taxonomies currently managed by this plugin.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
					<div class="taxocsie-register-list__actions">
						<button type="button" class="button taxocsie-delete-selected" id="taxocsie-delete-selected" disabled>
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Delete selected', 'taxonomy-csv-import-export' ); ?></span>
							<span class="taxocsie-delete-selected__count" id="taxocsie-selected-count">0</span>
						</button>
						<button type="button" class="button button-primary taxocsie-new-taxonomy" id="taxocsie-new-taxonomy" aria-controls="taxocsie-register-card" aria-expanded="false">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php esc_html_e( 'New taxonomy', 'taxonomy-csv-import-export' ); ?>
						</button>
					</div>
				</div>
				<div class="taxocsie-table-controls" aria-label="<?php esc_attr_e( 'Taxonomy table controls', 'taxonomy-csv-import-export' ); ?>">
					<div class="taxocsie-table-total-wrap">
						<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
						<strong id="taxocsie-table-total"><?php echo esc_html( count( $saved_taxonomies ) ); ?></strong>
						<span><?php esc_html_e( 'total', 'taxonomy-csv-import-export' ); ?></span>
					</div>
					<div class="taxocsie-table-pagination">
						<label for="taxocsie-per-page"><?php esc_html_e( 'Rows per page', 'taxonomy-csv-import-export' ); ?></label>
						<select id="taxocsie-per-page" class="taxocsie-table-per-page">
							<option value="5">5</option>
							<option value="10" selected>10</option>
							<option value="20">20</option>
							<option value="50">50</option>
						</select>
						<span class="taxocsie-page-status"><span id="taxocsie-current-page">1</span> <?php esc_html_e( 'of', 'taxonomy-csv-import-export' ); ?> <span id="taxocsie-total-pages">1</span></span>
						<div class="taxocsie-pagination-buttons">
							<button type="button" id="taxocsie-page-prev" aria-label="<?php esc_attr_e( 'Previous page', 'taxonomy-csv-import-export' ); ?>" disabled>
								<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
							</button>
							<button type="button" id="taxocsie-page-next" aria-label="<?php esc_attr_e( 'Next page', 'taxonomy-csv-import-export' ); ?>" disabled>
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
						</div>
					</div>
				</div>
				<div class="taxocsie-panel__tablewrap taxocsie-panel__tablewrap--flush">
					<table class="widefat striped taxocsie-panel__table" id="taxocsie-reg-table">
						<thead>
							<tr>
								<th class="taxocsie-select-column"><input type="checkbox" id="taxocsie-select-all" aria-label="<?php esc_attr_e( 'Select all registered taxonomies', 'taxonomy-csv-import-export' ); ?>" <?php disabled( empty( $saved_taxonomies ) ); ?>></th>
								<th class="taxocsie-sortable-column" data-sort-column="0" aria-sort="none"><button type="button"><?php esc_html_e( 'Label', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-sort" aria-hidden="true"></span></button></th>
								<th class="taxocsie-sortable-column" data-sort-column="1" aria-sort="none"><button type="button"><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-sort" aria-hidden="true"></span></button></th>
								<th class="taxocsie-sortable-column" data-sort-column="2" aria-sort="none"><button type="button"><?php esc_html_e( 'Type', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-sort" aria-hidden="true"></span></button></th>
								<th class="taxocsie-sortable-column" data-sort-column="3" aria-sort="none"><button type="button"><?php esc_html_e( 'Post Types', 'taxonomy-csv-import-export' ); ?><span class="dashicons dashicons-sort" aria-hidden="true"></span></button></th>
								<th><?php esc_html_e( 'Actions', 'taxonomy-csv-import-export' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $saved_taxonomies ) ) : ?>
								<tr class="taxocsie-empty-taxonomies">
									<td colspan="6">
										<div class="taxocsie-empty-taxonomies__content">
											<span class="taxocsie-empty-taxonomies__icon dashicons dashicons-category" aria-hidden="true"></span>
											<div>
												<strong><?php esc_html_e( 'No custom taxonomies yet', 'taxonomy-csv-import-export' ); ?></strong>
												<span><?php esc_html_e( 'Create your first taxonomy to organize content with custom tags or categories.', 'taxonomy-csv-import-export' ); ?></span>
											</div>
										</div>
									</td>
								</tr>
							<?php endif; ?>
							<?php foreach ( $saved_taxonomies as $slug => $tax ) : ?>
								<tr data-slug="<?php echo esc_attr( $slug ); ?>">
									<td class="taxocsie-select-column"><input type="checkbox" class="taxocsie-select-taxonomy" value="<?php echo esc_attr( $slug ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: term or taxonomy name. */ __( 'Select %s', 'taxonomy-csv-import-export' ), $tax['label'] ) ); ?>"></td>
									<td><strong class="taxocsie-taxonomy-label"><?php echo esc_html( $tax['label'] ); ?></strong></td>
									<td><code><?php echo esc_html( $slug ); ?></code></td>
									<td><span class="taxocsie-taxonomy-type-badge"><?php echo ! empty( $tax['hierarchical'] ) ? esc_html__( 'Categories', 'taxonomy-csv-import-export' ) : esc_html__( 'Tags', 'taxonomy-csv-import-export' ); ?></span></td>
									<td><span class="taxocsie-post-types-value"><?php echo esc_html( implode( ', ', $tax['post_types'] ) ); ?></span></td>
									<td>
										<div class="taxocsie-row-actions">
											<button type="button" class="taxocsie-row-actions__toggle" aria-haspopup="menu" aria-expanded="false">
												<span class="taxocsie-row-actions__dots" aria-hidden="true"></span>
												<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: taxonomy name. */ __( 'Open actions for %s', 'taxonomy-csv-import-export' ), $tax['label'] ) ); ?></span>
											</button>
											<div class="taxocsie-row-actions__menu" role="menu" hidden>
												<button
													type="button"
													class="taxocsie-edit-taxonomy"
													role="menuitem"
													data-slug="<?php echo esc_attr( $slug ); ?>"
													data-label="<?php echo esc_attr( $tax['label'] ); ?>"
													data-singular="<?php echo esc_attr( $tax['singular_label'] ?? $tax['label'] ); ?>"
													data-description="<?php echo esc_attr( $tax['description'] ?? '' ); ?>"
													data-hierarchical="<?php echo ! empty( $tax['hierarchical'] ) ? '1' : '0'; ?>"
													data-post-types="<?php echo esc_attr( wp_json_encode( $tax['post_types'] ) ); ?>"
												>
													<span class="dashicons dashicons-edit" aria-hidden="true"></span>
													<?php esc_html_e( 'Edit', 'taxonomy-csv-import-export' ); ?>
												</button>
												<button type="button" class="taxocsie-delete-taxonomy" role="menuitem" data-slug="<?php echo esc_attr( $slug ); ?>">
													<span class="dashicons dashicons-trash" aria-hidden="true"></span>
													<?php esc_html_e( 'Delete', 'taxonomy-csv-import-export' ); ?>
												</button>
											</div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="taxocsie-panel taxocsie-register-card" id="taxocsie-register-card" hidden>
				<div class="taxocsie-register-card__header">
					<span class="taxocsie-register-card__icon dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<div>
						<h2 id="taxocsie-register-form-title"><?php esc_html_e( 'New taxonomy', 'taxonomy-csv-import-export' ); ?></h2>
						<p id="taxocsie-register-form-description"><?php esc_html_e( 'Define its labels, structure, and where it should appear.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
				</div>

				<div class="taxocsie-panel__form" id="taxocsie-reg-form">

					<div class="taxocsie-reg-grid">
						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="reg-label">
								<?php esc_html_e( 'Label (Plural)', 'taxonomy-csv-import-export' ); ?>
								<span class="taxocsie-required">*</span>
							</label>
							<input class="taxocsie-panel__input" type="text" id="reg-label" placeholder="<?php esc_attr_e( 'e.g. Genres', 'taxonomy-csv-import-export' ); ?>">
						</div>

						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="reg-singular">
								<?php esc_html_e( 'Singular Label', 'taxonomy-csv-import-export' ); ?>
								<span class="taxocsie-optional"><?php esc_html_e( '(optional)', 'taxonomy-csv-import-export' ); ?></span>
							</label>
							<input class="taxocsie-panel__input" type="text" id="reg-singular" placeholder="<?php esc_attr_e( 'e.g. Genre', 'taxonomy-csv-import-export' ); ?>">
						</div>

						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="reg-slug">
								<?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?>
								<span class="taxocsie-required">*</span>
							</label>
							<input class="taxocsie-panel__input" type="text" id="reg-slug" placeholder="<?php esc_attr_e( 'e.g. genre', 'taxonomy-csv-import-export' ); ?>">
							<span class="taxocsie-field-hint"><?php esc_html_e( 'Auto-generated. Lowercase, no spaces.', 'taxonomy-csv-import-export' ); ?></span>
						</div>
					</div>

					<div class="taxocsie-panel__field">
						<label class="taxocsie-panel__field-label" for="reg-description">
							<?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?>
							<span class="taxocsie-optional"><?php esc_html_e( '(optional)', 'taxonomy-csv-import-export' ); ?></span>
						</label>
						<textarea class="taxocsie-panel__textarea" id="reg-description" rows="3" placeholder="<?php esc_attr_e( 'Describe what this taxonomy is used for…', 'taxonomy-csv-import-export' ); ?>"></textarea>
					</div>

					<div class="taxocsie-panel__field taxocsie-register-setting">
						<div class="taxocsie-register-setting__header">
							<span class="taxocsie-register-setting__icon dashicons dashicons-networking" aria-hidden="true"></span>
							<div>
								<p class="taxocsie-panel__field-label" id="taxocsie-taxonomy-type-label">
									<?php esc_html_e( 'Taxonomy Type', 'taxonomy-csv-import-export' ); ?>
									<span class="taxocsie-required">*</span>
								</p>
								<span class="taxocsie-register-setting__description"><?php esc_html_e( 'Choose how terms are organized.', 'taxonomy-csv-import-export' ); ?></span>
							</div>
						</div>
						<div class="taxocsie-type-options" role="radiogroup" aria-labelledby="taxocsie-taxonomy-type-label">
							<label class="taxocsie-type-option taxocsie-type-option--active">
								<input type="radio" name="reg-type" value="0" checked>
								<span class="taxocsie-type-option__icon dashicons dashicons-tag" aria-hidden="true"></span>
								<span class="taxocsie-type-option__content">
									<span class="taxocsie-type-option__title"><?php esc_html_e( 'Tags', 'taxonomy-csv-import-export' ); ?></span>
									<span class="taxocsie-type-option__desc"><?php esc_html_e( 'Flat list of independent terms.', 'taxonomy-csv-import-export' ); ?></span>
								</span>
								<span class="taxocsie-type-option__check dashicons dashicons-yes-alt" aria-hidden="true"></span>
							</label>
							<label class="taxocsie-type-option">
								<input type="radio" name="reg-type" value="1">
								<span class="taxocsie-type-option__icon dashicons dashicons-category" aria-hidden="true"></span>
								<span class="taxocsie-type-option__content">
									<span class="taxocsie-type-option__title"><?php esc_html_e( 'Categories', 'taxonomy-csv-import-export' ); ?></span>
									<span class="taxocsie-type-option__desc"><?php esc_html_e( 'Nested terms with parent relationships.', 'taxonomy-csv-import-export' ); ?></span>
								</span>
								<span class="taxocsie-type-option__check dashicons dashicons-yes-alt" aria-hidden="true"></span>
							</label>
						</div>
					</div>

					<div class="taxocsie-panel__field taxocsie-register-setting">
						<div class="taxocsie-register-setting__header">
							<span class="taxocsie-register-setting__icon dashicons dashicons-admin-post" aria-hidden="true"></span>
							<div>
								<p class="taxocsie-panel__field-label" id="taxocsie-post-types-label">
									<?php esc_html_e( 'Attach to Post Types', 'taxonomy-csv-import-export' ); ?>
									<span class="taxocsie-required">*</span>
								</p>
								<span class="taxocsie-register-setting__description"><?php esc_html_e( 'Select where this taxonomy will be available.', 'taxonomy-csv-import-export' ); ?></span>
							</div>
						</div>
						<div class="taxocsie-post-types-grid" role="group" aria-labelledby="taxocsie-post-types-label">
							<?php foreach ( $post_types as $pt ) :
								$post_type_icon = 'dashicons-admin-post';
								if ( 'page' === $pt->name ) {
									$post_type_icon = 'dashicons-admin-page';
								} elseif ( 'attachment' === $pt->name ) {
									$post_type_icon = 'dashicons-format-image';
								}
								?>
								<label class="taxocsie-checkbox-label">
									<input type="checkbox" class="reg-post-type" value="<?php echo esc_attr( $pt->name ); ?>">
									<span class="taxocsie-post-type-icon dashicons <?php echo esc_attr( $post_type_icon ); ?>" aria-hidden="true"></span>
									<span class="taxocsie-post-type-content"><strong><?php echo esc_html( $pt->label ); ?></strong><code><?php echo esc_html( $pt->name ); ?></code></span>
									<span class="taxocsie-post-type-check dashicons dashicons-yes" aria-hidden="true"></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="taxocsie-panel__actions">
						<div id="taxocsie-reg-feedback" class="taxocsie-reg-feedback" role="status" aria-live="polite" style="display:none;"></div>
						<button type="button" id="taxocsie-reg-cancel" class="button taxocsie-register-cancel">
							<?php esc_html_e( 'Cancel', 'taxonomy-csv-import-export' ); ?>
						</button>
						<button type="button" id="taxocsie-reg-submit" class="button button-primary taxocsie-panel__btn-submit">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<span id="taxocsie-reg-submit-label"><?php esc_html_e( 'Register Taxonomy', 'taxonomy-csv-import-export' ); ?></span>
						</button>
					</div>

				</div>
			</div>

		</div>
		<?php
	}

	/* -------------------------------------------------------
	   Users — Import / Export (UNCHANGED)
	------------------------------------------------------- */
	private function render_users_manager_panel( string $default_mode, string $default_user_action = 'export' ): void {
		global $wp_roles;
		$roles = $wp_roles->get_names();
		?>
		<div
			id="taxocsie-users-panel"
			class="taxocsie-manager-panel tw-mt-4"
			<?php echo 'users' !== $default_mode ? ' style="display:none;"' : ''; ?>
		>
			<div class="taxocsie-user-action-panels">

				<!-- Export -->
				<div id="taxocsie-user-export-panel" class="taxocsie-panel taxocsie-user-action-panel tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft" role="tabpanel" aria-labelledby="taxocsie-tab-user-export"<?php echo 'export' !== $default_user_action ? ' hidden' : ''; ?>>
					<div class="tw-flex tw-items-center tw-gap-3 tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
						<div class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-brand-100 tw-text-brand-700">
							<span class="dashicons dashicons-download tw-text-xl" aria-hidden="true"></span>
						</div>
						<div>
							<h2 class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900"><?php esc_html_e( 'Export Users', 'taxonomy-csv-import-export' ); ?></h2>
						<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Download WordPress accounts as CSV. The file contains sensitive account data; store it securely.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
					</div>
					<form class="taxocsie-panel__form tw-px-6 tw-py-7 sm:tw-px-8" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="taxocsie_export_users">
						<?php wp_nonce_field( 'taxocsie_export_users', 'taxocsie_export_users_nonce' ); ?>

						<div class="taxocsie-panel__field">
							<p class="taxocsie-panel__field-label">
								<?php esc_html_e( 'Filter by Role', 'taxonomy-csv-import-export' ); ?>
								<span class="taxocsie-optional"><?php esc_html_e( '(optional)', 'taxonomy-csv-import-export' ); ?></span>
							</p>
							<div class="taxocsie-roles-grid">
								<?php foreach ( $roles as $key => $label ) : ?>
									<label class="taxocsie-checkbox-label">
										<input type="checkbox" name="roles[]" value="<?php echo esc_attr( $key ); ?>">
										<span><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<span class="taxocsie-field-hint"><?php esc_html_e( 'Leave all unchecked to export every user.', 'taxonomy-csv-import-export' ); ?></span>
						</div>

						<div class="taxocsie-panel__actions">
							<button type="submit" class="button button-primary taxocsie-panel__btn-submit tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-rounded-lg tw-border-0 tw-bg-brand-600 tw-px-6 tw-text-sm tw-font-semibold tw-text-white tw-shadow-sm tw-transition hover:tw-bg-brand-700 focus:tw-ring-4 focus:tw-ring-brand-100">
								<?php esc_html_e( 'Export CSV', 'taxonomy-csv-import-export' ); ?>
							</button>
						</div>

					</form>
				</div>

				<!-- Import -->
				<div id="taxocsie-user-import-panel" class="taxocsie-panel taxocsie-user-action-panel tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-soft" role="tabpanel" aria-labelledby="taxocsie-tab-user-import"<?php echo 'import' !== $default_user_action ? ' hidden' : ''; ?>>
					<div class="tw-flex tw-items-center tw-gap-3 tw-border-b tw-border-slate-100 tw-bg-slate-50/80 tw-px-6 tw-py-5 sm:tw-px-8">
						<div class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-cyan-100 tw-text-cyan-700">
							<span class="dashicons dashicons-upload tw-text-xl" aria-hidden="true"></span>
						</div>
						<div>
							<h2 class="tw-m-0 tw-text-base tw-font-bold tw-text-slate-900"><?php esc_html_e( 'Import Users', 'taxonomy-csv-import-export' ); ?></h2>
						<p class="tw-m-0 tw-mt-1 tw-text-sm tw-text-slate-500"><?php esc_html_e( 'Create or update WordPress accounts from a CSV file. Review roles before importing.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
					</div>

					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( isset( $_GET['user-imported'] ) && $_GET['user-imported'] === 'success' ) : ?>
						<div class="notice notice-success taxocsie-panel__notice">
							<p><?php esc_html_e( 'Users imported successfully!', 'taxonomy-csv-import-export' ); ?></p>
						</div>
					<?php endif; ?>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( isset( $_GET['user-imported'] ) && $_GET['user-imported'] === 'error' ) : ?>
						<div class="notice notice-error taxocsie-panel__notice">
							<p><?php esc_html_e( 'User import failed. Please check the CSV file and try again.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
					<?php endif; ?>

					<form class="taxocsie-panel__form tw-px-6 tw-py-7 sm:tw-px-8" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'taxocsie_import_users_nonce', 'taxocsie_nonce' ); ?>

						<div class="taxocsie-panel__field taxocsie-user-import-field">
							<label class="taxocsie-panel__field-label" for="taxocsie_import_file">
								<?php esc_html_e( 'CSV File', 'taxonomy-csv-import-export' ); ?>
								<span class="taxocsie-required">*</span>
							</label>
							<div class="taxocsie-user-upload">
								<div class="taxocsie-user-upload__intro">
									<span class="taxocsie-user-upload__icon dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
									<div>
										<strong><?php esc_html_e( 'Upload a user CSV', 'taxonomy-csv-import-export' ); ?></strong>
										<p><?php esc_html_e( 'Choose a .csv file from your computer.', 'taxonomy-csv-import-export' ); ?></p>
									</div>
								</div>
								<div class="taxocsie-user-upload__control">
									<input type="file" id="taxocsie_import_file" name="taxocsie_import_file" accept=".csv,text/csv" aria-describedby="taxocsie-user-import-columns" required>
									<span class="taxocsie-field-hint" id="taxocsie-user-import-columns"><?php esc_html_e( 'Columns: username, email, password_hash, roles, first_name, last_name, meta', 'taxonomy-csv-import-export' ); ?></span>
								</div>
							</div>
						</div>

						<div class="taxocsie-panel__actions">
							<button type="submit" name="taxocsie_import_users" class="button button-primary taxocsie-panel__btn-submit tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-rounded-lg tw-border-0 tw-bg-brand-600 tw-px-6 tw-text-sm tw-font-semibold tw-text-white tw-shadow-sm tw-transition hover:tw-bg-brand-700 focus:tw-ring-4 focus:tw-ring-brand-100">
								<?php esc_html_e( 'Import Users', 'taxonomy-csv-import-export' ); ?>
							</button>
						</div>

					</form>
				</div>

			</div>

		</div>
		<?php
	}

	/* -------------------------------------------------------
	   Helpers (UNCHANGED)
	------------------------------------------------------- */
	public function tcie_encode_svg_to_base64( string $path ) {
		if ( ! file_exists( $path ) ) {
			return false;
		}
		$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( $svg === false ) {
			return false;
		}
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/* -------------------------------------------------------
	   NEW: Demo Generator - Create Demo Categories/Tags
	------------------------------------------------------- */
	public function get_taxocsie_demo_generator_function() {
		$taxonomies = get_taxonomies(array('public' => true), 'objects');
		$demo_status = $this->handle_demo_generation();
		?>
		<div class="taxocsie-wrap taxocsie-demo-page">


			<?php if ($demo_status) : ?>
				<div class="notice notice-<?php echo esc_attr($demo_status['type']); ?> is-dismissible taxocsie-panel__notice">
					<p><?php echo wp_kses_post($demo_status['message']); ?></p>
				</div>
			<?php endif; ?>

			<div class="taxocsie-demo-grid">
				<!-- Quick Generate Panel -->
				<div class="taxocsie-panel taxocsie-panel--demo">
					<h2 class="taxocsie-panel__title">
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e('Quick Generate', 'taxonomy-csv-import-export'); ?>
					</h2>
					
					<form class="taxocsie-panel__form" method="post" id="taxocsie-demo-form">
						<?php wp_nonce_field('taxocsie_demo_generate', 'taxocsie_demo_nonce'); ?>
						
						<div class="taxocsie-demo-row">
							<div class="taxocsie-panel__field">
								<label class="taxocsie-panel__field-label" for="demo_taxonomy">
									<?php esc_html_e('Select Taxonomy', 'taxonomy-csv-import-export'); ?>
									<span class="taxocsie-required">*</span>
								</label>
								<select class="taxocsie-panel__select" id="demo_taxonomy" name="demo_taxonomy" required>
									<option value=""><?php esc_html_e('Choose a taxonomy', 'taxonomy-csv-import-export'); ?></option>
									<?php foreach ($taxonomies as $taxonomy) : ?>
										<option value="<?php echo esc_attr($taxonomy->name); ?>" data-hierarchical="<?php echo esc_attr( $taxonomy->hierarchical ? '1' : '0' ); ?>">
											<?php
											echo esc_html(
												$taxonomy->label . ' — ' .
												( $taxonomy->hierarchical
													? __( 'Hierarchical', 'taxonomy-csv-import-export' )
													: __( 'Flat', 'taxonomy-csv-import-export' ) )
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="taxocsie-panel__field">
								<label class="taxocsie-panel__field-label" for="demo_count">
									<?php esc_html_e('Terms', 'taxonomy-csv-import-export'); ?>
									<span class="taxocsie-required">*</span>
									<span class="taxocsie-demo-count-range"><?php esc_html_e('1–500', 'taxonomy-csv-import-export'); ?></span>
								</label>
								<div class="taxocsie-demo-count-wrapper">
									<input class="taxocsie-panel__input" type="number" id="demo_count" name="demo_count" 
										min="1" max="500" value="10" required>
								</div>
							</div>
						</div>

						<div class="taxocsie-demo-row">
							<div class="taxocsie-panel__field">
								<label class="taxocsie-panel__field-label">
									<?php esc_html_e('Term Structure', 'taxonomy-csv-import-export'); ?>
								</label>
								<div class="taxocsie-demo-structure-options">
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_structure" value="flat" checked>
										<span><?php esc_html_e('Flat terms', 'taxonomy-csv-import-export'); ?></span>
									</label>
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_structure" value="hierarchical">
										<span><?php esc_html_e('Parent / child', 'taxonomy-csv-import-export'); ?></span>
									</label>
								</div>
								<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
									<?php esc_html_e('Hierarchical creates a tree structure with random parent-child relationships.', 'taxonomy-csv-import-export'); ?>
								</p>
							</div>
						</div>

						<div class="taxocsie-demo-row">
							<div class="taxocsie-panel__field">
								<label class="taxocsie-panel__field-label">
									<?php esc_html_e('Name Format', 'taxonomy-csv-import-export'); ?>
								</label>
								<div class="taxocsie-demo-name-options">
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_name_format" value="prefix" checked>
										<span><?php esc_html_e('Prefix', 'taxonomy-csv-import-export'); ?></span>
									</label>
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_name_format" value="plain">
										<span><?php esc_html_e('Plain', 'taxonomy-csv-import-export'); ?></span>
									</label>
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_name_format" value="random">
										<span><?php esc_html_e('Random Words', 'taxonomy-csv-import-export'); ?></span>
									</label>
								</div>
							</div>
						</div>

						<div class="taxocsie-demo-row">
							<div class="taxocsie-panel__field">
								<label class="taxocsie-panel__field-label">
									<?php esc_html_e('Options', 'taxonomy-csv-import-export'); ?>
								</label>
								<label class="taxocsie-checkbox-label">
									<input type="checkbox" name="demo_add_description" value="1" checked>
									<span><?php esc_html_e('Descriptions', 'taxonomy-csv-import-export'); ?></span>
								</label>
								<br>
								<label class="taxocsie-checkbox-label">
									<input type="checkbox" name="demo_skip_existing" value="1" checked>
									<span><?php esc_html_e('Skip existing', 'taxonomy-csv-import-export'); ?></span>
								</label>
							</div>
						</div>

						<div class="taxocsie-panel__actions">
							<button type="submit" name="taxocsie_demo_generate" class="button button-primary taxocsie-panel__btn-submit" id="taxocsie-demo-btn">
								<span class="dashicons dashicons-controls-play taxocsie-demo-generate-icon" aria-hidden="true"></span>
								<?php esc_html_e('Generate Demo Terms', 'taxonomy-csv-import-export'); ?>
							</button>
							<span class="spinner" style="float: none; margin-left: 10px;"></span>
						</div>
					</form>
				</div>

				<!-- Stats Panel -->
				<div class="taxocsie-panel taxocsie-panel--demo-stats">
					<h2 class="taxocsie-panel__title">
						<span class="dashicons dashicons-chart-bar"></span>
						<?php esc_html_e('Current Taxonomy Stats', 'taxonomy-csv-import-export'); ?>
					</h2>
					
					<div id="taxocsie-demo-stats">
						<?php $this->render_demo_stats(); ?>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Handle demo generation form submission
	 */
	private function handle_demo_generation() {
		if (!isset($_POST['taxocsie_demo_generate'])) {
			return false;
		}

		$nonce = isset( $_POST['taxocsie_demo_nonce'] ) && is_scalar( $_POST['taxocsie_demo_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['taxocsie_demo_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'taxocsie_demo_generate' ) ) {
			return array(
				'type' => 'error',
				'message' => __('Security check failed. Please try again.', 'taxonomy-csv-import-export')
			);
		}

		if (!current_user_can('manage_options')) {
			return array(
				'type' => 'error',
				'message' => __('You do not have permission to perform this action.', 'taxonomy-csv-import-export')
			);
		}

		$taxonomy = isset($_POST['demo_taxonomy']) && is_scalar( $_POST['demo_taxonomy'] ) ? sanitize_key(wp_unslash($_POST['demo_taxonomy'])) : '';
		$count = isset($_POST['demo_count']) && is_scalar( $_POST['demo_count'] ) ? intval( wp_unslash( $_POST['demo_count'] ) ) : 10;
		$structure = isset($_POST['demo_structure']) && is_scalar( $_POST['demo_structure'] ) ? sanitize_key(wp_unslash($_POST['demo_structure'])) : 'flat';
		$name_format = isset($_POST['demo_name_format']) && is_scalar( $_POST['demo_name_format'] ) ? sanitize_key(wp_unslash($_POST['demo_name_format'])) : 'prefix';
		$add_description = isset($_POST['demo_add_description']);
		$skip_existing = isset($_POST['demo_skip_existing']);
		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_obj || ! $taxonomy_obj->public || ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) {
			return array(
				'type' => 'error',
				'message' => __('Invalid taxonomy selected.', 'taxonomy-csv-import-export')
			);
		}

		if ($count < 1 || $count > 500) {
			return array(
				'type' => 'error',
				'message' => __('Please enter a number between 1 and 500.', 'taxonomy-csv-import-export')
			);
		}
		if ( ! in_array( $structure, array( 'flat', 'hierarchical' ), true ) || ! in_array( $name_format, array( 'prefix', 'plain', 'random' ), true ) || ( 'hierarchical' === $structure && ! $taxonomy_obj->hierarchical ) ) {
			return array(
				'type' => 'error',
				'message' => __( 'Invalid demo generator options.', 'taxonomy-csv-import-export' ),
			);
		}

		// Generate the terms
		$results = $this->generate_demo_terms($taxonomy, $count, $structure, $name_format, $add_description, $skip_existing);

		$message = sprintf(
			/* translators: %1$d is the number of terms created, %2$s is the taxonomy name */
			esc_html__('Generated <strong>%1$d</strong> terms in <strong>%2$s</strong>.', 'taxonomy-csv-import-export'),
			$results['created'],
			$taxonomy
		);

		if ($results['skipped'] > 0) {
			$message .= ' ' . sprintf(
				/* translators: %1$d is the number of skipped terms */
				esc_html__('Skipped <strong>%1$d</strong> existing terms.', 'taxonomy-csv-import-export'),
				$results['skipped']
			);
		}

		if ($results['errors'] > 0) {
			$message .= ' ' . sprintf(
				/* translators: %1$d is the number of errors */
				esc_html__('<strong>%1$d</strong> errors occurred.', 'taxonomy-csv-import-export'),
				$results['errors']
			);
			return array(
				'type' => 'warning',
				'message' => $message
			);
		}

		return array(
			'type' => 'success',
			'message' => $message
		);
	}

	/**
	 * Generate demo terms
	 */
	public function generate_demo_terms($taxonomy, $count, $structure, $name_format, $add_description, $skip_existing) {
		$results = array(
			'created' => 0,
			'skipped' => 0,
			'errors' => 0,
			'terms' => array()
		);

		$taxonomy_obj = get_taxonomy($taxonomy);
		$is_hierarchical = $taxonomy_obj->hierarchical;

		// Prepare word lists for random generation
		$words = array(
			'Technology', 'Design', 'Development', 'Marketing', 'Business', 
			'Creative', 'Digital', 'Social', 'Media', 'Content',
			'Strategy', 'Innovation', 'Solutions', 'Services', 'Products',
			'Community', 'Education', 'Health', 'Finance', 'Travel',
			'Food', 'Fashion', 'Art', 'Music', 'Sports',
			'Science', 'Nature', 'Culture', 'History', 'Future'
		);

		$descriptions = array(
			'This is a demo term created for testing purposes.',
			'A sample term to demonstrate the taxonomy structure.',
			'Used for development and testing scenarios.',
			'Example term with descriptive content.',
			'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
			'Praesent commodo cursus magna, vel scelerisque nisl consectetur et.',
			'Nullam id dolor id nibh ultricies vehicula ut id elit.',
			'Cras justo odio, dapibus ac facilisis in, egestas eget quam.',
			'Vestibulum id ligula porta felis euismod semper.',
			'Duis mollis, est non commodo luctus, nisi erat porttitor ligula.'
		);

		$existing_slugs = array();
		$created_terms = array();

		for ($i = 1; $i <= $count; $i++) {
			// Generate name based on format
			switch ($name_format) {
				case 'prefix':
					$name = 'Demo ' . $this->generate_demo_name($i);
					break;
				case 'plain':
					$name = 'Demo Term ' . $i;
					break;
				case 'random':
					$name = $words[array_rand($words)] . ' ' . $words[array_rand($words)];
					break;
				default:
					$name = 'Demo Term ' . $i;
			}

			// Check the original candidate before making a unique replacement.
			$original_name = $name;
			$original_slug = sanitize_title( $name );
			if ( $skip_existing && ( term_exists( $original_slug, $taxonomy ) || term_exists( $name, $taxonomy ) ) ) {
				$results['skipped']++;
				continue;
			}

			$slug = $original_slug;
			$counter = 1;
			while ( term_exists( $slug, $taxonomy ) || term_exists( $name, $taxonomy ) || in_array( $slug, $existing_slugs, true ) ) {
				$name = $original_name . ' ' . $counter;
				$slug = $original_slug . '-' . $counter;
				$counter++;
			}
			$existing_slugs[] = $slug;

			// Determine parent for hierarchical structure
			$parent = 0;
			if ($is_hierarchical && $structure === 'hierarchical' && $i > 1) {
				//phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				if (mt_rand(1, 100) <= 60 && !empty($created_terms)) {
					$parent_key = array_rand($created_terms);
					$parent = $created_terms[$parent_key];
				}
			}

			// Generate description
			$description = '';
			if ($add_description) {
				$description = $descriptions[array_rand($descriptions)];
				if ($i % 3 === 0) {
					$description .= ' ' . $descriptions[array_rand($descriptions)];
				}
			}

			// Insert term
			$args = array(
				'slug' => $slug,
				'description' => $description,
				'parent' => $parent
			);

			$result = wp_insert_term($name, $taxonomy, $args);

			if (is_wp_error($result)) {
				$results['errors']++;
				continue;
			}

			$results['created']++;
			$created_terms[] = $result['term_id'];
			$results['terms'][] = array(
				'id' => $result['term_id'],
				'name' => $name,
				'slug' => $slug
			);
		}

		Dashboard::increment( Dashboard::CREATED_ITEMS, $results['created'] );

		return $results;
	}

	/**
	 * Generate a demo name with number
	 */
	private function generate_demo_name($number) {
		$suffixes = array('Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa');
		$index = ($number - 1) % count($suffixes);
		return $suffixes[$index] . ' ' . $number;
	}

	/**
	 * Render demo stats
	 */
	private function render_demo_stats() {
		$taxonomies = get_taxonomies(array('public' => true), 'objects');
		?>
		<table class="widefat striped taxocsie-panel__table taxocsie-enhanced-table">
			<thead>
				<tr>
					<th scope="col" class="taxocsie-demo-select-column" data-no-sort="true"><input type="checkbox" class="taxocsie-demo-select-page" aria-label="<?php esc_attr_e( 'Select taxonomies on this page', 'taxonomy-csv-import-export' ); ?>" <?php disabled( ! $taxonomies ); ?>></th>
					<th><?php esc_html_e('Taxonomy', 'taxonomy-csv-import-export'); ?></th>
					<th><?php esc_html_e('Type', 'taxonomy-csv-import-export'); ?></th>
					<th><?php esc_html_e('Total Terms', 'taxonomy-csv-import-export'); ?></th>
					<th data-no-sort="true"><?php esc_html_e('Actions', 'taxonomy-csv-import-export'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($taxonomies as $taxonomy) : 
					$term_count = wp_count_terms(array(
						'taxonomy' => $taxonomy->name,
						'hide_empty' => false
					));
					$term_count = is_wp_error( $term_count ) ? 0 : absint( $term_count );
					$type_label = $taxonomy->hierarchical ? esc_html__('Hierarchical', 'taxonomy-csv-import-export') : esc_html__('Flat', 'taxonomy-csv-import-export');
				?>
					<tr>
						<td class="taxocsie-demo-select-column"><input type="checkbox" class="taxocsie-demo-select-taxonomy" value="<?php echo esc_attr( $taxonomy->name ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: term or taxonomy name. */ __( 'Select %s', 'taxonomy-csv-import-export' ), $taxonomy->label ) ); ?>"></td>
						<td><strong class="taxocsie-demo-taxonomy-name"><?php echo esc_html($taxonomy->label); ?></strong>
							<code><?php echo esc_html($taxonomy->name); ?></code>
						</td>
						<td>
							<span class="taxocsie-demo-type <?php echo esc_attr( $taxonomy->hierarchical ? 'is-hierarchical' : 'is-flat' ); ?>">
								<?php echo esc_html( $type_label ); ?>
							</span>
						</td>
						<td><strong class="taxocsie-demo-term-count"><?php echo esc_html( number_format_i18n( $term_count ) ); ?></strong></td>
						<td>
							<div class="taxocsie-demo-table-actions">
								<button type="button" class="button taxocsie-quick-btn" data-taxonomy="<?php echo esc_attr($taxonomy->name); ?>" data-count="10">
									+10
								</button>
								<button type="button" class="button taxocsie-quick-btn" data-taxonomy="<?php echo esc_attr($taxonomy->name); ?>" data-count="25">
									+25
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
