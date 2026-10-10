<?php
/**
 * Free keyword-based auto tagging and categorization.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assigns taxonomy terms when configured keywords occur in published content.
 */
final class RulesAutoTagging {

	/** Stable key consumed by the Pro Module Manager. */
	public const MODULE_KEY = 'rules_auto_tagging';

	/** Legacy/marketing key retained for manager migrations. */
	public const LEGACY_MODULE_KEY = 'ai_auto_tagging';

	private const OPTION_NAME = 'wp_taxonomy_auto_tag_rules';
	private const PAGE_SLUG = 'taxonomy-csv-import-export-auto-tagging';
	private const AI_PAGE_SLUG = 'taxonomy-csv-import-export-auto-tagging-ai';
	private const SAVE_ACTION = 'taxocsie_save_auto_tagging_rules';
	private const SAVE_NONCE_ACTION = 'taxocsie_save_auto_tagging_rules';
	private const SAVE_NONCE_NAME = 'taxocsie_auto_tagging_nonce';
	private const TERMS_AJAX_ACTION = 'taxocsie_auto_tagging_terms';
	private const TERMS_NONCE_ACTION = 'taxocsie_auto_tagging_terms';
	private const DELETE_ACTION = 'taxocsie_delete_auto_tagging_rules';
	private const CAPABILITY = 'manage_categories';
	private const DEFAULT_MAX_RULES = 100;
	private const DEFAULT_MAX_KEYWORDS_PER_RULE = 50;
	private const DEFAULT_TERM_LIMIT = 500;

	/** @var self|null */
	private static $instance;

	/** Prevent duplicate term assignments during nested post updates. */
	private static $processing = false;

	/** @var string */
	private $page_hook = '';
	private $ai_page_hook = '';

	/** Start the module once. */
	public static function init(): ?self {
		if ( self::$instance instanceof self ) {
			return self::$instance;
		}

		self::$instance = new self();
		self::$instance->register_hooks();
		return self::$instance;
	}

	/** Allow a Module Manager to discover this standalone file. */
	public static function module_definition(): array {
		return array(
			'key'         => self::MODULE_KEY,
			'legacy_key'  => self::LEGACY_MODULE_KEY,
			'name'        => __( 'Auto Tagging', 'taxonomy-csv-import-export' ),
			'description' => __( 'Assign taxonomy terms using keyword rules when posts are saved.', 'taxonomy-csv-import-export' ),
			'loader'      => array( self::class, 'init' ),
		);
	}

	private function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save_rules' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'delete_rules' ) );
		add_action( 'wp_ajax_' . self::TERMS_AJAX_ACTION, array( $this, 'ajax_get_terms' ) );
		add_action( 'wp_after_insert_post', array( $this, 'apply_rules' ), 20, 4 );
	}

	/** Register the module settings submenu. */
	public function register_admin_page(): void {
		$parent = defined( 'TAXOCSIE_PLUGIN_SLUG' ) ? TAXOCSIE_PLUGIN_SLUG : 'taxonomy-csv-import-export';
		$this->page_hook = (string) add_submenu_page(
			$parent,
			__( 'Auto Tagging', 'taxonomy-csv-import-export' ),
			__( 'Auto Tagging: Manually', 'taxonomy-csv-import-export' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
		$this->ai_page_hook = (string) add_submenu_page(
			$parent,
			__( 'Auto Tagging', 'taxonomy-csv-import-export' ),
			__( 'Auto Tagging: AI Suggestions', 'taxonomy-csv-import-export' ),
			self::CAPABILITY,
			self::AI_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/** Load the single-file module's scoped CSS and jQuery behavior. */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array_filter( array( $this->page_hook, $this->ai_page_hook ) ), true ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'dashicons' );
		wp_register_style( 'taxocsie-rules-auto-tagging', false, array(), defined( 'TAXOCSIE_VERSION' ) ? TAXOCSIE_VERSION : '1.0' );
		wp_enqueue_style( 'taxocsie-rules-auto-tagging' );
		wp_add_inline_style( 'taxocsie-rules-auto-tagging', $this->admin_css() );
		wp_add_inline_style( 'taxocsie-rules-auto-tagging', $this->admin_notification_css() );
		wp_add_inline_style( 'taxocsie-rules-auto-tagging', $this->admin_saved_table_css() );
		wp_add_inline_style( 'taxocsie-rules-auto-tagging', $this->admin_table_controls_css() );
		wp_add_inline_style( 'taxocsie-rules-auto-tagging', $this->admin_row_action_css() );
		$layout_path = TAXOCSIE_ASSETS_DIR_PATH . '/css/auto-tagging.css';
		wp_enqueue_style( 'taxocsie-auto-tagging-layout', TAXOCSIE_ASSETS_URI . '/css/auto-tagging.css', array( 'taxocsie-rules-auto-tagging' ), (string) filemtime( $layout_path ) );

		wp_localize_script(
			'jquery',
			'taxocsieAutoTagging',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'action'        => self::TERMS_AJAX_ACTION,
				'nonce'         => wp_create_nonce( self::TERMS_NONCE_ACTION ),
				'loading'       => __( 'Loading terms…', 'taxonomy-csv-import-export' ),
				'selectTerm'    => __( 'Select a term', 'taxonomy-csv-import-export' ),
				'noTerms'       => __( 'No terms found', 'taxonomy-csv-import-export' ),
				'requestFailed' => __( 'Terms could not be loaded.', 'taxonomy-csv-import-export' ),
				'newRule'       => __( 'New rule', 'taxonomy-csv-import-export' ),
				'editRule'      => __( 'Edit rule', 'taxonomy-csv-import-export' ),
				'discardChanges' => __( 'Discard unsaved rule changes?', 'taxonomy-csv-import-export' ),
				/* translators: %d: number of selected auto-tagging rules. */
				'confirmDelete'  => __( 'Delete %d selected auto-tagging rules? This cannot be undone.', 'taxonomy-csv-import-export' ),
				/* translators: 1: current page, 2: total pages. */
				'pageOf'         => __( '%1$d of %2$d', 'taxonomy-csv-import-export' ),
				/* translators: 1: visible rules, 2: total rules. */
				'visibleCount'   => __( '%1$d of %2$d rules', 'taxonomy-csv-import-export' ),
				'unsavedDeleteWarning' => __( ' Unsaved edits in the rule editor will be lost.', 'taxonomy-csv-import-export' ),
			)
		);
		wp_add_inline_script( 'jquery', $this->admin_javascript() );
	}

	/** Render the rules settings interface. */
	public function render_admin_page(): void {
		$this->guard_admin_access();

		$rules            = $this->get_rules();
		$rule_revision    = hash( 'sha256', wp_json_encode( get_option( self::OPTION_NAME, array() ) ) );
		$saved_rule_count = count( $rules );
		$taxonomies       = $this->get_manageable_taxonomies();
		$has_ai_settings  = (bool) has_action( 'taxocsie_auto_tagging_after_rules_settings' );
		$current_page     = isset( $_GET['page'] ) && is_scalar( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing.
		$active_tab       = self::AI_PAGE_SLUG === $current_page ? 'pro' : 'free';
		if ( empty( $rules ) ) {
			$rules[] = array( 'keywords' => array(), 'taxonomy' => '', 'term_id' => 0 );
		}
		?>
		<div class="wrap taxocsie-auto-tagging-wrap">
			<hr class="wp-header-end">
			<form id="taxocsie-keyword-rules" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="taxocsie-rules-form<?php echo $has_ai_settings ? ' taxocsie-rules-form--pro' : ' taxocsie-rules-form--free'; ?>" data-saved-rules="<?php echo esc_attr( (string) $saved_rule_count ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<input type="hidden" name="settings_tab" value="<?php echo esc_attr( $active_tab ); ?>">
				<?php wp_nonce_field( self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME ); ?>
				<div id="taxocsie-panel-free" class="taxocsie-auto-tagging-tab-panel" <?php echo 'free' === $active_tab ? '' : 'hidden'; ?>>
					<div class="taxocsie-auto-tagging-guide" role="note">
						<strong><?php esc_html_e( 'How it works', 'taxonomy-csv-import-export' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Create the category or tag you want to assign.', 'taxonomy-csv-import-export' ); ?></li>
							<li><?php esc_html_e( 'Add a keyword rule below, or enable AI suggestions with Pro.', 'taxonomy-csv-import-export' ); ?></li>
							<li><?php esc_html_e( 'Save these settings, then save a published post to try it.', 'taxonomy-csv-import-export' ); ?></li>
						</ol>
						<p><?php esc_html_e( 'Existing posts are not updated automatically. Assigned terms are kept when new ones are added.', 'taxonomy-csv-import-export' ); ?></p>
					</div>

				<div class="taxocsie-rules-card">
					<div class="taxocsie-rules-card__heading">
						<div class="taxocsie-section-heading"><span class="taxocsie-section-heading__icon"><span class="dashicons dashicons-filter" aria-hidden="true"></span></span><div>
							<span class="taxocsie-section-kicker"><?php esc_html_e( 'FREE AUTOMATION', 'taxonomy-csv-import-export' ); ?></span>
						<h2><?php esc_html_e( 'Add categories or tags by keyword', 'taxonomy-csv-import-export' ); ?></h2>
						<p><?php esc_html_e( 'A rule checks the post title and content when you save a published post. If a word matches, it adds the term you selected.', 'taxonomy-csv-import-export' ); ?></p>
						</div></div>
						<button type="button" class="button taxocsie-add-rule"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a keyword rule', 'taxonomy-csv-import-export' ); ?></button>
					</div>
					<?php if ( isset( $_GET['rules-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['rules-updated'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation-only redirect flag. ?>
						<div class="notice-success taxocsie-panel__notice taxocsie-rules-notification" data-toast-duration="3000" role="status">
							<span class="dashicons dashicons-yes-alt taxocsie-rules-notification__icon" aria-hidden="true"></span>
							<div class="taxocsie-rules-notification__content">
								<strong><?php esc_html_e( 'Auto tagging settings saved', 'taxonomy-csv-import-export' ); ?></strong>
								<p><?php echo esc_html( sprintf( /* translators: %d: number of active keyword rules. */ _n( '%d keyword rule saved. Changes apply when a published post is saved.', '%d keyword rules saved. Changes apply when a published post is saved.', $saved_rule_count, 'taxonomy-csv-import-export' ), $saved_rule_count ) ); ?></p>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( isset( $_GET['rules-deleted'] ) && absint( $_GET['rules-deleted'] ) > 0 ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation-only redirect flag. ?>
						<div class="notice-success taxocsie-panel__notice taxocsie-rules-notification" data-toast-duration="3000" role="status">
							<span class="dashicons dashicons-yes-alt taxocsie-rules-notification__icon" aria-hidden="true"></span>
							<div class="taxocsie-rules-notification__content">
								<strong><?php esc_html_e( 'Rules deleted', 'taxonomy-csv-import-export' ); ?></strong>
								<p><?php echo esc_html( sprintf( /* translators: %d: number of deleted auto-tagging rules. */ _n( '%d auto-tagging rule was deleted.', '%d auto-tagging rules were deleted.', absint( $_GET['rules-deleted'] ), 'taxonomy-csv-import-export' ), absint( $_GET['rules-deleted'] ) ) ); ?></p>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( empty( $taxonomies ) ) : ?>
						<div class="taxocsie-rules-notification taxocsie-rules-notification--warning" role="alert">
							<span class="dashicons dashicons-warning taxocsie-rules-notification__icon" aria-hidden="true"></span>
							<div class="taxocsie-rules-notification__content">
								<strong><?php esc_html_e( 'No taxonomies available', 'taxonomy-csv-import-export' ); ?></strong>
								<p><?php esc_html_e( 'Register a taxonomy or ask an administrator for permission to manage terms before adding a rule.', 'taxonomy-csv-import-export' ); ?></p>
							</div>
						</div>
					<?php endif; ?>

					<?php ob_start(); ?>
					<div class="taxocsie-saved-rules">
						<div class="taxocsie-saved-rules__heading">
							<h3><?php esc_html_e( 'Your keyword rules', 'taxonomy-csv-import-export' ); ?></h3>
							<span><?php echo esc_html( sprintf( /* translators: %d: number of saved rules. */ _n( '%d rule', '%d rules', $saved_rule_count, 'taxonomy-csv-import-export' ), $saved_rule_count ) ); ?></span>
						</div>
						<p class="taxocsie-saved-rules__help"><?php esc_html_e( 'Use Edit to change a rule, then save at the bottom. Delete removes a saved rule immediately after confirmation.', 'taxonomy-csv-import-export' ); ?></p>
						<div class="taxocsie-saved-rules__toolbar">
							<label for="taxocsie-rule-search" class="screen-reader-text"><?php esc_html_e( 'Search saved rules', 'taxonomy-csv-import-export' ); ?></label>
							<input type="search" id="taxocsie-rule-search" class="taxocsie-saved-rules__search" placeholder="<?php esc_attr_e( 'Search keywords, taxonomy, or term...', 'taxonomy-csv-import-export' ); ?>" autocomplete="off">
							<button type="submit" form="taxocsie-delete-rules-form" class="button taxocsie-saved-rules__delete" <?php disabled( 0 === $saved_rule_count ); ?>><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="taxocsie-saved-rules__delete-label"><?php esc_html_e( 'Delete selected', 'taxonomy-csv-import-export' ); ?></span><span class="taxocsie-saved-rules__selected-count">0</span></button>
						</div>
						<p class="taxocsie-table-scroll-hint"><?php esc_html_e( 'Swipe the table to view all columns and actions.', 'taxonomy-csv-import-export' ); ?></p>
						<div class="taxocsie-saved-rules__scroll">
							<table class="taxocsie-saved-rules__table">
								<thead><tr>
									<th scope="col"><input type="checkbox" class="taxocsie-saved-rules__select-page" aria-label="<?php esc_attr_e( 'Select all rules on this page', 'taxonomy-csv-import-export' ); ?>" <?php disabled( 0 === $saved_rule_count ); ?>></th>
									<th scope="col"><?php esc_html_e( 'Keywords', 'taxonomy-csv-import-export' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Category or tag group', 'taxonomy-csv-import-export' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Category or tag added', 'taxonomy-csv-import-export' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Action', 'taxonomy-csv-import-export' ); ?></th>
								</tr></thead>
								<tbody>
								<?php if ( 0 === $saved_rule_count ) : ?>
									<tr><td colspan="5" class="taxocsie-saved-rules__empty"><?php esc_html_e( 'No rules saved yet. Add a rule below to get started.', 'taxonomy-csv-import-export' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $rules as $index => $rule ) : ?>
										<?php
										$taxonomy_name = (string) $rule['taxonomy'];
										$taxonomy_label = isset( $taxonomies[ $taxonomy_name ] ) ? $taxonomies[ $taxonomy_name ]->label : $taxonomy_name;
										$assigned_term  = get_term( (int) $rule['term_id'], $taxonomy_name );
										?>
										<tr data-saved-rule-index="<?php echo esc_attr( (string) $index ); ?>">
											<td><input type="checkbox" form="taxocsie-delete-rules-form" name="indices[]" value="<?php echo esc_attr( (string) $index ); ?>" class="taxocsie-saved-rules__select" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rule number. */ __( 'Select rule %d', 'taxonomy-csv-import-export' ), $index + 1 ) ); ?>"></td>
											<td><?php echo esc_html( implode( ', ', $rule['keywords'] ) ); ?></td>
											<td><?php echo esc_html( $taxonomy_label ); ?></td>
											<td><?php echo esc_html( $assigned_term instanceof \WP_Term ? $assigned_term->name : __( 'Term unavailable', 'taxonomy-csv-import-export' ) ); ?></td>
											<td><div class="taxocsie-saved-rules__row-actions"><button type="button" class="button taxocsie-edit-saved-rule" data-rule-index="<?php echo esc_attr( (string) $index ); ?>" aria-controls="taxocsie-rule-row-<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rule number. */ __( 'Edit rule %d', 'taxonomy-csv-import-export' ), $index + 1 ) ); ?>"><?php esc_html_e( 'Edit', 'taxonomy-csv-import-export' ); ?></button><button type="button" class="button taxocsie-delete-saved-rule" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rule number. */ __( 'Delete rule %d', 'taxonomy-csv-import-export' ), $index + 1 ) ); ?>"><?php esc_html_e( 'Delete', 'taxonomy-csv-import-export' ); ?></button></div></td>
										</tr>
									<?php endforeach; ?>
									<?php endif; ?>
								<tr class="taxocsie-saved-rules__no-results" hidden><td colspan="5"><?php esc_html_e( 'No rules match your search.', 'taxonomy-csv-import-export' ); ?></td></tr>
								</tbody>
							</table>
						</div>
						<div class="taxocsie-saved-rules__footer" hidden>
							<span class="taxocsie-saved-rules__result-count" aria-live="polite"></span>
							<div class="taxocsie-table-pagination">
								<label for="taxocsie-rules-per-page"><?php esc_html_e( 'Rows per page', 'taxonomy-csv-import-export' ); ?></label>
								<select id="taxocsie-rules-per-page" class="taxocsie-table-per-page"><?php foreach ( array( 5, 10, 20, 50 ) as $size ) : ?><option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( 5, $size ); ?>><?php echo esc_html( (string) $size ); ?></option><?php endforeach; ?></select>
								<span class="taxocsie-page-status"></span>
								<div class="taxocsie-pagination-buttons"><button type="button" class="taxocsie-rules-prev" aria-label="<?php esc_attr_e( 'Previous page', 'taxonomy-csv-import-export' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button><button type="button" class="taxocsie-rules-next" aria-label="<?php esc_attr_e( 'Next page', 'taxonomy-csv-import-export' ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button></div>
							</div>
						</div>
					</div>

					<?php $saved_rules_html = ob_get_clean(); ?>
					<div class="taxocsie-rules-editor">
						<div class="taxocsie-rules-editor__heading">
							<h3 class="taxocsie-rules-editor__title"><?php esc_html_e( 'New keyword rule', 'taxonomy-csv-import-export' ); ?></h3>
							<p><?php esc_html_e( 'Example: if a post mentions “iPhone”, add the “Smartphones” tag. Create that tag in WordPress first.', 'taxonomy-csv-import-export' ); ?></p>
						</div>
						<div class="taxocsie-rules-list" data-next-index="<?php echo esc_attr( (string) count( $rules ) ); ?>">
							<?php foreach ( $rules as $index => $rule ) : ?>
								<?php $this->render_rule_row( (int) $index, $rule, $taxonomies ); ?>
							<?php endforeach; ?>
						</div>
						<p class="taxocsie-rules-editor__save-note"><?php esc_html_e( 'After filling in a rule, use Save auto tagging settings below.', 'taxonomy-csv-import-export' ); ?></p>
					</div>
					<div class="taxocsie-rule-validation" role="alert" hidden><?php esc_html_e( 'Finish the keyword, group, and category or tag for this rule before saving.', 'taxonomy-csv-import-export' ); ?></div>
					<?php if ( $saved_rule_count > 0 ) { echo $saved_rules_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Buffered plugin-rendered markup escapes each dynamic value. 
					} ?>
				</div>

				<div class="taxocsie-rules-actions">
					<button type="button" class="button taxocsie-cancel-rule-edit"><?php esc_html_e( 'Cancel rule edit', 'taxonomy-csv-import-export' ); ?></button>
					<button type="submit" class="button button-primary" <?php disabled( empty( $taxonomies ) ); ?>><?php esc_html_e( 'Save auto tagging settings', 'taxonomy-csv-import-export' ); ?></button>
				</div>
				</div>
				<div id="taxocsie-panel-pro" class="taxocsie-auto-tagging-tab-panel" <?php echo 'pro' === $active_tab ? '' : 'hidden'; ?>>
					<?php if ( $has_ai_settings ) : ?>
						<?php do_action( 'taxocsie_auto_tagging_after_rules_settings', $taxonomies ); ?>
						<div class="taxocsie-rules-actions taxocsie-rules-actions--pro"><button type="submit" class="button button-primary" <?php disabled( empty( $taxonomies ) ); ?>><?php esc_html_e( 'Save auto tagging settings', 'taxonomy-csv-import-export' ); ?></button></div>
					<?php else : ?>
						<section class="taxocsie-ai-upgrade" aria-labelledby="taxocsie-pro-upgrade-title">
							<div class="taxocsie-rules-card__heading taxocsie-ai-heading"><div class="taxocsie-section-heading"><span class="taxocsie-section-heading__icon taxocsie-section-heading__icon--ai"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span></span><div><span class="taxocsie-section-kicker"><?php esc_html_e( 'SMART AUTOMATION', 'taxonomy-csv-import-export' ); ?></span><h2 id="taxocsie-pro-upgrade-title"><?php esc_html_e( 'Optional AI suggestions', 'taxonomy-csv-import-export' ); ?></h2><p><?php esc_html_e( 'AI automatically adds relevant existing categories or tags when you save a published post.', 'taxonomy-csv-import-export' ); ?></p></div></div><span class="taxocsie-section-label taxocsie-section-label--pro"><?php esc_html_e( 'Pro', 'taxonomy-csv-import-export' ); ?></span></div>
							<div class="taxocsie-ai-upgrade__body"><div><h3><?php esc_html_e( 'Tag posts faster with AI (Pro).', 'taxonomy-csv-import-export' ); ?></h3><p><?php esc_html_e( 'Connect a supported provider, choose a taxonomy, and let AI suggest existing terms for published posts.', 'taxonomy-csv-import-export' ); ?></p></div><a class="button" href="<?php echo esc_url( \TaxoCSIE\Freemius::get_upgrade_url() ); ?>"><?php esc_html_e( 'Learn about Pro', 'taxonomy-csv-import-export' ); ?></a></div>
						</section>
					<?php endif; ?>
				</div>
			</form>
			<form id="taxocsie-delete-rules-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_ACTION ); ?>">
				<input type="hidden" name="rule_revision" value="<?php echo esc_attr( $rule_revision ); ?>">
				<?php wp_nonce_field( self::DELETE_ACTION ); ?>
			</form>

			<script type="text/html" id="tmpl-taxocsie-auto-tagging-rule">
				<?php $this->render_rule_row( '__INDEX__', array( 'keywords' => array(), 'taxonomy' => '', 'term_id' => 0 ), $taxonomies ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Render one settings row.
	 *
	 * @param int|string                            $index      Rule index or template token.
	 * @param array<string,mixed>                   $rule       Rule data.
	 * @param array<string,\WP_Taxonomy>             $taxonomies Manageable taxonomies.
	 */
	private function render_rule_row( $index, array $rule, array $taxonomies ): void {
		$keywords = isset( $rule['keywords'] ) && is_array( $rule['keywords'] ) ? implode( ', ', $rule['keywords'] ) : '';
		$taxonomy = isset( $rule['taxonomy'] ) ? sanitize_key( (string) $rule['taxonomy'] ) : '';
		$term_id  = isset( $rule['term_id'] ) ? absint( $rule['term_id'] ) : 0;
		$term     = $term_id && $taxonomy ? get_term( $term_id, $taxonomy ) : null;
		$name     = 'rules[' . $index . ']';
		?>
		<div class="taxocsie-rule-row" id="taxocsie-rule-row-<?php echo esc_attr( (string) $index ); ?>" data-rule-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="taxocsie-rule-field taxocsie-rule-field--keywords">
				<label for="taxocsie-keywords-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Keywords', 'taxonomy-csv-import-export' ); ?></label>
				<input type="text" id="taxocsie-keywords-<?php echo esc_attr( (string) $index ); ?>" name="<?php echo esc_attr( $name . '[keywords]' ); ?>" value="<?php echo esc_attr( $keywords ); ?>" placeholder="<?php esc_attr_e( 'iPhone, Samsung, Smartphone', 'taxonomy-csv-import-export' ); ?>">
					<small><?php esc_html_e( 'Write a word or phrase found in the post title or content. For several matches, separate them with commas.', 'taxonomy-csv-import-export' ); ?></small>
			</div>

			<div class="taxocsie-rule-field">
				<label for="taxocsie-taxonomy-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Category or tag group', 'taxonomy-csv-import-export' ); ?></label>
				<select class="taxocsie-rule-taxonomy" id="taxocsie-taxonomy-<?php echo esc_attr( (string) $index ); ?>" name="<?php echo esc_attr( $name . '[taxonomy]' ); ?>">
					<option value=""><?php esc_html_e( 'Select a taxonomy', 'taxonomy-csv-import-export' ); ?></option>
					<?php foreach ( $taxonomies as $taxonomy_object ) : ?>
						<option value="<?php echo esc_attr( $taxonomy_object->name ); ?>" <?php selected( $taxonomy, $taxonomy_object->name ); ?>><?php echo esc_html( $taxonomy_object->label ); ?></option>
					<?php endforeach; ?>
					</select>
					<small><?php esc_html_e( 'Choose Categories for post categories, Tags for post tags, or another group you use.', 'taxonomy-csv-import-export' ); ?></small>
				</div>

			<div class="taxocsie-rule-field">
				<label for="taxocsie-term-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Category or tag to add', 'taxonomy-csv-import-export' ); ?></label>
				<select class="taxocsie-rule-term" id="taxocsie-term-<?php echo esc_attr( (string) $index ); ?>" name="<?php echo esc_attr( $name . '[term_id]' ); ?>" data-selected="<?php echo esc_attr( (string) $term_id ); ?>" data-loaded="0" <?php disabled( '' === $taxonomy ); ?>>
					<option value=""><?php esc_html_e( 'Select a term', 'taxonomy-csv-import-export' ); ?></option>
					<?php if ( $term instanceof \WP_Term ) : ?>
						<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" selected><?php echo esc_html( $term->name ); ?></option>
					<?php endif; ?>
					</select>
					<small><?php esc_html_e( 'Choose an existing term from the group above. The rule adds it without removing other terms.', 'taxonomy-csv-import-export' ); ?></small>
				</div>

			<button type="button" class="taxocsie-remove-rule" aria-label="<?php esc_attr_e( 'Remove rule', 'taxonomy-csv-import-export' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
			</button>
		</div>
		<?php
	}

	/** Persist validated rules with a post/redirect/get flow. */
	public function save_rules(): void {
		$this->guard_admin_access();
		check_admin_referer( self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME );

		$tab = isset( $_POST['settings_tab'] ) && is_scalar( $_POST['settings_tab'] ) && 'pro' === sanitize_key( wp_unslash( $_POST['settings_tab'] ) ) ? 'pro' : 'free';
		if ( 'pro' === $tab ) {
			if ( ! has_action( 'taxocsie_auto_tagging_save_additional_settings' ) || ! isset( $_POST['ai_settings_present'] ) ) {
				wp_die( esc_html__( 'Pro settings are unavailable.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
			}
			do_action( 'taxocsie_auto_tagging_save_additional_settings' );
		} else {
			$submitted = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field-by-field below.
			$rules = $this->sanitize_rules( $submitted );
			update_option( self::OPTION_NAME, $rules, false );
		}

		wp_safe_redirect(
			add_query_arg(
				'pro' === $tab ? array( 'page' => self::AI_PAGE_SLUG, 'ai-updated' => '1' ) : array( 'page' => self::PAGE_SLUG, 'rules-updated' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Delete only selected rules from the version of the table the user saw. */
	public function delete_rules(): void {
		$this->guard_admin_access();
		check_admin_referer( self::DELETE_ACTION );

		$stored_rules = get_option( self::OPTION_NAME, array() );
		$stored_rules = is_array( $stored_rules ) ? $stored_rules : array();
		$rules = $this->get_rules();
		$expected_revision = hash( 'sha256', wp_json_encode( $stored_rules ) );
		$submitted_revision = isset( $_POST['rule_revision'] ) && is_string( $_POST['rule_revision'] )
			? sanitize_text_field( wp_unslash( $_POST['rule_revision'] ) )
			: '';
		if ( ! hash_equals( $expected_revision, $submitted_revision ) ) {
			wp_die( esc_html__( 'Rules changed since this page loaded. Refresh and select them again.', 'taxonomy-csv-import-export' ), '', array( 'response' => 409 ) );
		}

		$submitted = isset( $_POST['indices'] ) && is_array( $_POST['indices'] ) ? wp_unslash( $_POST['indices'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each index is validated below.
		if ( empty( $submitted ) || count( $submitted ) > count( $rules ) ) {
			wp_die( esc_html__( 'Select at least one valid rule to delete.', 'taxonomy-csv-import-export' ), '', array( 'response' => 400 ) );
		}

		$indices = array();
		foreach ( $submitted as $raw_index ) {
			if ( ! is_scalar( $raw_index ) || ! ctype_digit( (string) $raw_index ) || ! array_key_exists( (int) $raw_index, $rules ) ) {
				wp_die( esc_html__( 'The selected rules are invalid. Refresh and try again.', 'taxonomy-csv-import-export' ), '', array( 'response' => 400 ) );
			}
			$indices[ (int) $raw_index ] = true;
		}

		$visible_index = 0;
		foreach ( $stored_rules as $stored_index => $stored_rule ) {
			if ( ! is_array( $stored_rule ) || empty( $this->sanitize_rules( array( $stored_rule ) ) ) ) {
				continue;
			}
			if ( isset( $indices[ $visible_index ] ) ) {
				unset( $stored_rules[ $stored_index ] );
			}
			++$visible_index;
		}
		update_option( self::OPTION_NAME, array_values( $stored_rules ), false );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'rules-deleted' => count( $indices ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Return terms for a selected taxonomy. */
	public function ajax_get_terms(): void {
		if ( false === check_ajax_referer( self::TERMS_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The security token is invalid or expired.', 'taxonomy-csv-import-export' ) ), 403 );
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to use rules-based auto tagging.', 'taxonomy-csv-import-export' ) ), 403 );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$object   = $this->get_manageable_taxonomy( $taxonomy );
		if ( ! $object instanceof \WP_Taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Select a valid taxonomy.', 'taxonomy-csv-import-export' ) ), 400 );
		}

		$limit = max( 1, (int) apply_filters( 'taxocsie_auto_tagging_term_limit', self::DEFAULT_TERM_LIMIT ) );
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => $limit,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( array( 'message' => __( 'Terms could not be loaded.', 'taxonomy-csv-import-export' ) ), 500 );
		}

		$data = array();
		foreach ( $terms as $term ) {
			$data[] = array( 'id' => (int) $term->term_id, 'name' => (string) $term->name );
		}
		wp_send_json_success( array( 'terms' => $data ) );
	}

	/**
	 * Apply matching rules after WordPress has inserted/updated a post.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post       $post        Post object.
	 * @param bool          $update      Whether this was an update.
	 * @param \WP_Post|null  $post_before Previous post object.
	 */
	public function apply_rules( int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature documents update context.
		if ( self::$processing || 'publish' !== $post->post_status ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$rules = $this->get_rules();
		if ( empty( $rules ) ) {
			return;
		}

		$content = html_entity_decode(
			wp_strip_all_tags( $post->post_title . "\n" . strip_shortcodes( $post->post_content ) ),
			ENT_QUOTES | ENT_HTML5,
			get_bloginfo( 'charset' ) ?: 'UTF-8'
		);
		$assignments = array();

		foreach ( $rules as $rule ) {
			$taxonomy = isset( $rule['taxonomy'] ) ? (string) $rule['taxonomy'] : '';
			$term_id  = isset( $rule['term_id'] ) ? absint( $rule['term_id'] ) : 0;
			if ( '' === $taxonomy || ! $term_id || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
				continue;
			}
			$term = get_term( $term_id, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			foreach ( $rule['keywords'] as $keyword ) {
				if ( $this->contains_keyword( $content, (string) $keyword ) ) {
					$assignments[ $taxonomy ][ $term_id ] = $term_id;
					break;
				}
			}
		}

		if ( empty( $assignments ) ) {
			return;
		}

		self::$processing = true;
		try {
			foreach ( $assignments as $taxonomy => $term_ids ) {
				$result = wp_set_object_terms( $post_id, array_values( $term_ids ), $taxonomy, true );
				if ( is_wp_error( $result ) ) {
					/** Fires when a matched rule could not assign its term. */
					do_action( 'taxocsie_auto_tagging_assignment_error', $result, $post_id, $taxonomy, $term_ids );
				}
			}
		} finally {
			self::$processing = false;
		}
	}

	private function contains_keyword( string $content, string $keyword ): bool {
		if ( '' === $keyword ) {
			return false;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $content, $keyword, 0, 'UTF-8' );
		}
		return false !== stripos( $content, $keyword );
	}

	/** @return array<int,array{keywords:array<int,string>,taxonomy:string,term_id:int}> */
	private function get_rules(): array {
		$rules = get_option( self::OPTION_NAME, array() );
		return is_array( $rules ) ? $this->sanitize_rules( $rules ) : array();
	}

	/** @return array<int,array{keywords:array<int,string>,taxonomy:string,term_id:int}> */
	private function sanitize_rules( array $submitted ): array {
		$clean        = array();
		$max_rules    = max( 1, (int) apply_filters( 'taxocsie_auto_tagging_max_rules', self::DEFAULT_MAX_RULES ) );
		$max_keywords = max( 1, (int) apply_filters( 'taxocsie_auto_tagging_max_keywords_per_rule', self::DEFAULT_MAX_KEYWORDS_PER_RULE ) );

		foreach ( array_slice( $submitted, 0, $max_rules ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$taxonomy = isset( $rule['taxonomy'] ) ? sanitize_key( (string) $rule['taxonomy'] ) : '';
			$term_id  = isset( $rule['term_id'] ) ? absint( $rule['term_id'] ) : 0;
			$raw       = isset( $rule['keywords'] ) ? $rule['keywords'] : array();
			$keywords  = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
			$keywords  = array_slice( $keywords, 0, $max_keywords );
			$keywords  = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $keyword ): string {
								return mb_substr( sanitize_text_field( (string) $keyword ), 0, 100 );
							},
							$keywords
						),
						static function ( string $keyword ): bool { return '' !== $keyword; }
					)
				)
			);

			$taxonomy_object = $this->get_manageable_taxonomy( $taxonomy );
			$term            = $term_id && $taxonomy_object ? get_term( $term_id, $taxonomy ) : null;
			if ( empty( $keywords ) || ! $taxonomy_object instanceof \WP_Taxonomy || ! $term instanceof \WP_Term ) {
				continue;
			}

			$clean[] = array( 'keywords' => $keywords, 'taxonomy' => $taxonomy, 'term_id' => $term_id );
		}

		return $clean;
	}

	/** @return array<string,\WP_Taxonomy> */
	private function get_manageable_taxonomies(): array {
		$objects = get_taxonomies( array( 'show_ui' => true ), 'objects' );
		$result  = array();
		foreach ( $objects as $object ) {
			if ( $object instanceof \WP_Taxonomy && $this->can_manage_taxonomy( $object ) ) {
				$result[ $object->name ] = $object;
			}
		}
		return $result;
	}

	private function get_manageable_taxonomy( string $taxonomy ): ?\WP_Taxonomy {
		$object = '' !== $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $object instanceof \WP_Taxonomy && $object->show_ui && $this->can_manage_taxonomy( $object ) ? $object : null;
	}

	private function can_manage_taxonomy( \WP_Taxonomy $taxonomy ): bool {
		$capability = isset( $taxonomy->cap->manage_terms ) ? (string) $taxonomy->cap->manage_terms : '';
		return '' !== $capability && current_user_can( $capability );
	}

	private function guard_admin_access(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not authorized to manage rules-based auto tagging.', 'taxonomy-csv-import-export' ),
				esc_html__( 'Access denied', 'taxonomy-csv-import-export' ),
				array( 'response' => 403 )
			);
		}
	}

	private function admin_css(): string {
		return '.taxocsie-auto-tagging-wrap{width:auto;max-width:none;padding-top:12px}.taxocsie-auto-tagging-header{display:flex;align-items:center;gap:16px;padding:24px 28px;margin-bottom:20px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 18px 45px -28px rgba(15,23,42,.35)}.taxocsie-auto-tagging-header>.dashicons{display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;background:#ede9fe;color:#5144bd;font-size:22px}.taxocsie-auto-tagging-header h1{margin:0;color:#0f172a;font-size:24px;line-height:1.3}.taxocsie-auto-tagging-header p{margin:5px 0 0;color:#64748b}.taxocsie-pro-pill{margin-left:auto;padding:5px 11px;border-radius:999px;background:#ede9fe;color:#5144bd;font-size:12px;font-weight:700;text-decoration:none}.taxocsie-pro-pill:hover,.taxocsie-pro-pill:focus{background:#ddd6fe;color:#4338ca}.taxocsie-rules-card{overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 18px 45px -28px rgba(15,23,42,.35)}.taxocsie-rules-card__heading{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:20px 24px;background:#f8fafc;border-bottom:1px solid #f1f5f9}.taxocsie-rules-card__heading h2{margin:0;color:#0f172a;font-size:16px}.taxocsie-rules-card__heading p{margin:4px 0 0;color:#64748b}.taxocsie-add-rule.button{display:inline-flex!important;align-items:center;justify-content:center;flex:0 0 auto;gap:7px;min-height:40px;margin:0;padding:0 16px;border:1px solid #7164e6;border-radius:8px;background:#fff;color:#5144bd;font-size:13px;font-weight:600;line-height:1;white-space:nowrap;box-shadow:0 1px 2px rgba(15,23,42,.05);transition:background-color .15s,border-color .15s,color .15s,box-shadow .15s}.taxocsie-add-rule.button:hover{border-color:#6254d6;background:#f5f3ff;color:#4338a8}.taxocsie-add-rule.button:focus{border-color:#6254d6;color:#4338a8;box-shadow:0 0 0 3px rgba(113,100,230,.2);outline:0}.taxocsie-add-rule .dashicons{display:inline-flex;align-items:center;justify-content:center;flex:0 0 18px;width:18px;height:18px;font-size:18px;line-height:1!important}.taxocsie-rules-list{padding:20px 24px}.taxocsie-rule-row{display:grid;grid-template-columns:minmax(260px,1.4fr) minmax(180px,.8fr) minmax(180px,.8fr) 36px;align-items:start;gap:16px;padding:18px;margin-bottom:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px}.taxocsie-rule-row:last-child{margin-bottom:0}.taxocsie-rule-field label{display:block;margin-bottom:7px;color:#334155;font-size:13px;font-weight:600}.taxocsie-rule-field input,.taxocsie-rule-field select{width:100%;min-height:40px;border-color:#cbd5e1}.taxocsie-rule-field small{display:block;margin-top:5px;color:#64748b}.taxocsie-remove-rule{display:flex;align-items:center;justify-content:center;width:34px;height:34px;margin-top:27px;padding:0;color:#b91c1c;background:#fff;border:1px solid #fecaca;border-radius:8px;cursor:pointer}.taxocsie-remove-rule:hover,.taxocsie-remove-rule:focus{color:#fff;background:#b91c1c;border-color:#b91c1c}.taxocsie-rules-actions{display:flex;justify-content:flex-end;padding-top:16px}.taxocsie-rules-actions .button-primary{min-height:38px;padding:0 22px}@media(max-width:900px){.taxocsie-rule-row{grid-template-columns:1fr 1fr}.taxocsie-rule-field--keywords{grid-column:1/-1}.taxocsie-remove-rule{margin-top:27px}}@media(max-width:600px){.taxocsie-auto-tagging-header,.taxocsie-rules-card__heading{align-items:flex-start;flex-direction:column}.taxocsie-pro-pill{margin-left:0}.taxocsie-rule-row{grid-template-columns:1fr}.taxocsie-rule-field--keywords{grid-column:auto}.taxocsie-remove-rule{margin-top:0}.taxocsie-rules-list{padding:14px}}';
	}

	private function admin_notification_css(): string {
		return '.taxocsie-rules-card .taxocsie-rules-notification{display:flex;align-items:flex-start;gap:12px;margin:16px 24px 0;padding:14px 16px;border:1px solid #bbebd2;border-radius:10px;background:#f0fdf6;box-shadow:none;color:#14532d}.taxocsie-rules-card .taxocsie-rules-notification--warning{border-color:#f5dda2;background:#fffbeb;color:#78350f}.taxocsie-rules-notification__icon{flex:0 0 20px;margin-top:1px;font-size:20px;line-height:20px}.taxocsie-rules-notification__content{min-width:0}.taxocsie-rules-notification__content strong{display:block;font-size:13px;line-height:1.4}.taxocsie-rules-notification__content p{margin:3px 0 0;font-size:13px;line-height:1.5;color:#365d46}.taxocsie-rules-notification--warning p{color:#78572b}@media(max-width:600px){.taxocsie-rules-card .taxocsie-rules-notification{margin:14px 14px 0}}';
	}

	private function admin_saved_table_css(): string {
		return '.taxocsie-saved-rules{padding:22px 24px 24px}.taxocsie-saved-rules__heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.taxocsie-saved-rules__heading h3,.taxocsie-rules-editor__heading h3{margin:0;color:#0f172a;font-size:14px;font-weight:700}.taxocsie-saved-rules__heading span{padding:5px 10px;border-radius:999px;background:#ede9fe;color:#5144bd;font-size:12px;font-weight:700}.taxocsie-saved-rules__scroll{overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px}.taxocsie-saved-rules__table{width:100%;min-width:640px;border-collapse:collapse;table-layout:fixed}.taxocsie-saved-rules__table th,.taxocsie-saved-rules__table td{padding:13px 16px;text-align:left;vertical-align:middle;border-bottom:1px solid #e9eef5;overflow-wrap:anywhere}.taxocsie-saved-rules__table th{background:#f8fafc;color:#475569;font-size:12px;font-weight:700}.taxocsie-saved-rules__table td{color:#334155;font-size:13px;line-height:1.5}.taxocsie-saved-rules__table tr:last-child td{border-bottom:0}.taxocsie-saved-rules__table th:first-child{width:42%}.taxocsie-saved-rules__table th:nth-child(2){width:22%}.taxocsie-saved-rules__table th:nth-child(3){width:26%}.taxocsie-saved-rules__table th:last-child{width:10%}.taxocsie-saved-rules__table .button{min-height:30px;margin:0;padding:0 12px;border-radius:6px}.taxocsie-saved-rules__empty{text-align:center!important;color:#64748b!important}.taxocsie-rules-editor{border-top:1px solid #e2e8f0}.taxocsie-rules-editor__heading{padding:18px 24px 0}.taxocsie-rules-editor__heading p{margin:4px 0 0;color:#64748b;font-size:12px}.taxocsie-rules-form.is-collapsed .taxocsie-rules-editor,.taxocsie-rules-form.is-collapsed .taxocsie-rules-actions{display:none}.taxocsie-rule-row[hidden]{display:none}.taxocsie-rule-row:focus-within{border-color:#8b7ff1;box-shadow:0 0 0 2px rgba(113,100,230,.1)}.taxocsie-rules-actions{gap:10px}.taxocsie-rules-actions .taxocsie-cancel-rule-edit{min-height:38px;margin:0;padding:0 18px;border-radius:8px}@media(max-width:600px){.taxocsie-saved-rules{padding:16px}.taxocsie-rules-editor__heading{padding:16px 16px 0}}';
	}

	private function admin_table_controls_css(): string {
		return '.taxocsie-saved-rules__toolbar,.taxocsie-saved-rules__footer{display:flex;align-items:center;justify-content:space-between;gap:14px}.taxocsie-saved-rules__toolbar{margin-bottom:14px}.taxocsie-saved-rules__search{width:100%;max-width:360px;min-height:38px;padding:7px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.taxocsie-saved-rules__delete.button{display:inline-flex!important;align-items:center;justify-content:center;flex:0 0 auto;gap:7px;height:40px;min-height:40px;margin:0;padding:0 13px;border:1px solid #fecaca;border-radius:8px;background:#fff;color:#b91c1c;font-size:13px;font-weight:600;line-height:1!important;white-space:nowrap}.taxocsie-saved-rules__delete.button:hover:not(:disabled){border-color:#ef4444;background:#fff5f5;color:#991b1b}.taxocsie-saved-rules__delete.button:disabled{opacity:.55}.taxocsie-saved-rules__delete .dashicons{display:inline-flex!important;align-items:center;justify-content:center;flex:0 0 16px;width:16px;height:16px;margin:0;padding:0;font-size:16px;line-height:1!important;vertical-align:middle}.taxocsie-saved-rules__delete .dashicons:before{display:block;line-height:1}.taxocsie-saved-rules__delete-label{display:inline-flex;align-items:center;line-height:1}.taxocsie-saved-rules__selected-count{display:inline-flex;align-items:center;justify-content:center;min-width:19px;height:19px;padding:0 4px;border-radius:5px;background:#fee2e2;font-size:11px}.taxocsie-saved-rules__table th:first-child{width:44px}.taxocsie-saved-rules__table th:nth-child(2){width:auto}.taxocsie-saved-rules__table th:nth-child(3){width:20%}.taxocsie-saved-rules__table th:nth-child(4){width:24%}.taxocsie-saved-rules__table th:last-child{width:90px}.taxocsie-saved-rules__table th:first-child,.taxocsie-saved-rules__table td:first-child{padding-right:8px;text-align:center}.taxocsie-saved-rules__table input[type=checkbox]{margin:0}.taxocsie-saved-rules__table tr[hidden],.taxocsie-saved-rules__footer[hidden]{display:none!important}.taxocsie-saved-rules__no-results td{text-align:center;color:#64748b}.taxocsie-saved-rules__footer{margin-top:14px;color:#64748b;font-size:12px}.taxocsie-saved-rules__footer .taxocsie-table-pagination{flex-wrap:wrap}@media(max-width:700px){.taxocsie-saved-rules__toolbar,.taxocsie-saved-rules__footer{align-items:stretch;flex-direction:column}.taxocsie-saved-rules__search{max-width:none}.taxocsie-saved-rules__footer .taxocsie-table-pagination{justify-content:flex-start}}';
	}

	private function admin_row_action_css(): string {
		return '.taxocsie-saved-rules__table th:last-child{width:170px}.taxocsie-saved-rules__row-actions{display:flex;align-items:center;gap:8px;white-space:nowrap}.taxocsie-saved-rules__row-actions .button{display:inline-flex;align-items:center;justify-content:center;height:36px;min-height:36px;margin:0;padding:0 12px;border-radius:6px;font-size:12px;line-height:1}.taxocsie-saved-rules__row-actions .taxocsie-delete-saved-rule{border-color:#fecaca;background:#fff;color:#b91c1c}.taxocsie-saved-rules__row-actions .taxocsie-delete-saved-rule:hover,.taxocsie-saved-rules__row-actions .taxocsie-delete-saved-rule:focus{border-color:#ef4444;background:#fff5f5;color:#991b1b}';
	}

	private function admin_javascript(): string {
		return <<<'JS'
jQuery(function ($) {
	'use strict';
	var config = window.taxocsieAutoTagging || {};
	var $form = $('.taxocsie-rules-form');
	var $list = $form.find('.taxocsie-rules-list');
	var savedRuleCount = parseInt($form.attr('data-saved-rules'), 10) || 0;
	var initialRows = $list.html();
	var initialNextIndex = $list.attr('data-next-index');
	var initialValues = $form.serialize();
	var initialRuleValues = $list.find(':input').serialize();
	var $settingsTab = $form.find('input[name="settings_tab"]');
	var $aiEnabled = $form.find('input[name="ai_enabled"]');
	var $aiTaxonomy = $form.find('select[name="ai_taxonomy"]');
	function updateAiRequirement() {
		$aiTaxonomy.prop('required', $settingsTab.val() === 'pro' && $aiEnabled.is(':checked'));
	}
	$aiEnabled.on('change', updateAiRequirement);
	updateAiRequirement();
	$form.on('input change', '.taxocsie-rule-row :input', function () {
		$(this).removeAttr('aria-invalid');
		$form.find('.taxocsie-rule-validation').prop('hidden', true);
	});
	$form.on('submit', function (event) {
		if ($settingsTab.val() === 'pro') return;
		var invalidField = null;
		$list.children('.taxocsie-rule-row').each(function () {
			var $row = $(this);
			var $keywords = $row.find('input[name$="[keywords]"]');
			var $taxonomy = $row.find('.taxocsie-rule-taxonomy');
			var $term = $row.find('.taxocsie-rule-term');
			var hasKeywords = $.trim($keywords.val()) !== '';
			var hasTaxonomy = Boolean($taxonomy.val());
			var hasTerm = Boolean($term.val());
			if (!hasKeywords && !hasTaxonomy && !hasTerm) return;
			if (!hasKeywords || !hasTaxonomy || !hasTerm) {
				invalidField = !hasKeywords ? $keywords : (!hasTaxonomy ? $taxonomy : $term);
				return false;
			}
		});
		if (invalidField) {
			event.preventDefault();
			var $row = invalidField.closest('.taxocsie-rule-row');
			showEditor($row.attr('data-rule-index') < savedRuleCount ? 'edit' : 'new', $row.attr('data-rule-index'));
			invalidField.attr('aria-invalid', 'true').trigger('focus');
			$form.find('.taxocsie-rule-validation').prop('hidden', false);
		}
	});
	if (savedRuleCount > 0) {
		$form.addClass('is-collapsed');
	} else {
		$form.attr('data-editor-mode', 'new');
	}

	function resetEditor() {
		$list.html(initialRows).attr('data-next-index', initialNextIndex);
		$form.addClass('is-collapsed').removeAttr('data-editor-mode data-editor-index');
	}

	function confirmModeChange(mode, index) {
		var currentMode = $form.attr('data-editor-mode') || '';
		var currentIndex = $form.attr('data-editor-index') || '';
		if (!currentMode || (currentMode === mode && (mode !== 'edit' || currentIndex === index))) {
			return true;
		}
		if ($list.find(':input').serialize() !== initialRuleValues && !window.confirm(config.discardChanges || 'Discard unsaved rule changes?')) {
			return false;
		}
		resetEditor();
		return true;
	}

	function showEditor(mode, index) {
		$form.removeClass('is-collapsed').attr('data-editor-mode', mode).attr('data-editor-index', index || '');
		$form.find('.taxocsie-rules-editor__title').text(mode === 'edit' ? (config.editRule || 'Edit rule') : (config.newRule || 'New rule'));
		$list.children('.taxocsie-rule-row').each(function () {
			var rowIndex = $(this).attr('data-rule-index');
			$(this).prop('hidden', mode === 'edit' ? rowIndex !== index : parseInt(rowIndex, 10) < savedRuleCount);
		});
	}

	var $savedRows = $('.taxocsie-saved-rules__table tbody tr[data-saved-rule-index]');
	var $selectPage = $('.taxocsie-saved-rules__select-page');
	var $deleteButton = $('.taxocsie-saved-rules__delete');
	var $perPage = $('#taxocsie-rules-per-page');
	var currentPage = 1;

	function formatCount(pattern, first, second) {
		return String(pattern).replace('%1$d', first).replace('%2$d', second);
	}

	function updateSelection() {
		var $selected = $savedRows.find('.taxocsie-saved-rules__select:checked');
		var $visible = $savedRows.filter(function () { return !this.hidden; }).find('.taxocsie-saved-rules__select');
		var visibleSelected = $visible.filter(':checked').length;
		$('.taxocsie-saved-rules__selected-count').text($selected.length);
		$deleteButton.prop('disabled', !$selected.length);
		$selectPage.prop('checked', $visible.length > 0 && visibleSelected === $visible.length);
		$selectPage.prop('indeterminate', visibleSelected > 0 && visibleSelected < $visible.length);
		$selectPage.prop('disabled', !$visible.length);
	}

	function renderSavedTable() {
		var query = $.trim($('#taxocsie-rule-search').val()).toLocaleLowerCase();
		var perPage = parseInt($perPage.val(), 10) || 5;
		var $matches = $savedRows.filter(function () {
			return $(this).children('td').slice(1, 4).text().toLocaleLowerCase().indexOf(query) !== -1;
		});
		var pages = Math.max(1, Math.ceil($matches.length / perPage));
		currentPage = Math.max(1, Math.min(currentPage, pages));
		$savedRows.prop('hidden', true);
		$matches.slice((currentPage - 1) * perPage, currentPage * perPage).prop('hidden', false);
		$('.taxocsie-saved-rules__no-results').prop('hidden', !$savedRows.length || $matches.length > 0);
		$('.taxocsie-saved-rules__footer').prop('hidden', !$savedRows.length);
		$('.taxocsie-saved-rules__result-count').text(formatCount(config.visibleCount || '%1$d of %2$d rules', $matches.length, $savedRows.length));
		$('.taxocsie-saved-rules__footer .taxocsie-page-status').text(formatCount(config.pageOf || '%1$d of %2$d', currentPage, pages));
		$('.taxocsie-rules-prev').prop('disabled', currentPage <= 1);
		$('.taxocsie-rules-next').prop('disabled', currentPage >= pages);
		updateSelection();
	}

	$('#taxocsie-rule-search').on('keydown', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
		}
	}).on('input', function () {
		currentPage = 1;
		renderSavedTable();
	});
	$perPage.on('change', function () {
		currentPage = 1;
		renderSavedTable();
	});
	$('.taxocsie-rules-prev').on('click', function () { currentPage--; renderSavedTable(); });
	$('.taxocsie-rules-next').on('click', function () { currentPage++; renderSavedTable(); });
	$selectPage.on('change', function () {
		$savedRows.filter(function () { return !this.hidden; }).find('.taxocsie-saved-rules__select').prop('checked', this.checked);
		updateSelection();
	});
	$savedRows.find('.taxocsie-saved-rules__select').on('change', updateSelection);
	function deleteMessage(count) {
		var message = (config.confirmDelete || 'Delete %d selected auto-tagging rules? This cannot be undone.').replace('%d', count);
		if ($form.serialize() !== initialValues) {
			message += config.unsavedDeleteWarning || ' Unsaved edits in the rule editor will be lost.';
		}
		return message;
	}
	$(document).on('click', '.taxocsie-delete-saved-rule', function () {
		if (!window.confirm(deleteMessage(1))) {
			return;
		}
		$savedRows.find('.taxocsie-saved-rules__select').prop('checked', false);
		$(this).closest('tr').find('.taxocsie-saved-rules__select').prop('checked', true);
		document.getElementById('taxocsie-delete-rules-form').submit();
	});
	$('#taxocsie-delete-rules-form').on('submit', function (event) {
		var count = $savedRows.find('.taxocsie-saved-rules__select:checked').length;
		if (!count || !window.confirm(deleteMessage(count))) {
			event.preventDefault();
		}
	});
	renderSavedTable();

	function fillTerms($select, terms, selected) {
		$select.empty().append(new Option(config.selectTerm || 'Select a term', ''));
		$.each(terms, function (_, term) {
			$select.append(new Option(term.name, term.id, false, String(term.id) === String(selected)));
		});
		if (!terms.length) {
			$select.append(new Option(config.noTerms || 'No terms found', '', false, false));
		}
		$select.prop('disabled', false).attr('data-loaded', '1').data('selected', selected || '');
	}

	function loadTerms($row, force) {
		var taxonomy = $row.find('.taxocsie-rule-taxonomy').val();
		var $select = $row.find('.taxocsie-rule-term');
		var selected = $select.data('selected') || $select.val() || '';
		if (!taxonomy) {
			$select.empty().append(new Option(config.selectTerm || 'Select a term', '')).prop('disabled', true).attr('data-loaded', '0');
			return;
		}
		if (!force && String($select.attr('data-loaded')) === '1') {
			return;
		}
		$select.prop('disabled', true).empty().append(new Option(config.loading || 'Loading terms…', ''));
		$.post(config.ajaxUrl, { action: config.action, nonce: config.nonce, taxonomy: taxonomy })
			.done(function (response) {
				if (response.success && response.data && $.isArray(response.data.terms)) {
					fillTerms($select, response.data.terms, selected);
					return;
				}
				$select.empty().append(new Option((response.data && response.data.message) || config.requestFailed, '')).prop('disabled', true);
			})
			.fail(function () {
				$select.empty().append(new Option(config.requestFailed || 'Terms could not be loaded.', '')).prop('disabled', true);
			});
	}

	$(document).on('change', '.taxocsie-rule-taxonomy', function () {
		var $term = $(this).closest('.taxocsie-rule-row').find('.taxocsie-rule-term');
		$term.data('selected', '').attr('data-selected', '');
		loadTerms($(this).closest('.taxocsie-rule-row'), true);
	});

	$(document).on('focus mousedown', '.taxocsie-rule-term', function () {
		loadTerms($(this).closest('.taxocsie-rule-row'), false);
	});

	$('.taxocsie-add-rule').on('click', function () {
		if (!confirmModeChange('new', '')) {
			return;
		}
		if (savedRuleCount === 0 && $list.children('.taxocsie-rule-row').length === 1 && !$list.find('input[name$="[keywords]"]').val()) {
			showEditor('new', '');
			$list.find('input[name$="[keywords]"]').first().trigger('focus');
			return;
		}
		if (savedRuleCount === 0 && $form.hasClass('is-collapsed')) {
			$list.empty();
		}
		var index = parseInt($list.attr('data-next-index'), 10) || 0;
		var template = $('#tmpl-taxocsie-auto-tagging-rule').html().replace(/__INDEX__/g, index);
		$list.attr('data-next-index', index + 1).append(template);
		showEditor('new', '');
		$list.children('.taxocsie-rule-row').last().find('input').trigger('focus');
	});

	$(document).on('click', '.taxocsie-edit-saved-rule', function () {
		var index = String($(this).attr('data-rule-index'));
		if (!confirmModeChange('edit', index)) {
			return;
		}
		var $row = $list.children('.taxocsie-rule-row').filter(function () {
			return $(this).attr('data-rule-index') === index;
		}).first();
		if ($row.length) {
			showEditor('edit', index);
			$row.find('input').first().trigger('focus');
		}
	});

	$('.taxocsie-cancel-rule-edit').on('click', function () {
		resetEditor();
	});

	$(document).on('click', '.taxocsie-remove-rule', function () {
		var $rows = $('.taxocsie-rule-row');
		if ($rows.length === 1) {
			$rows.find('input').val('');
			$rows.find('select').val('').trigger('change');
			return;
		}
		$(this).closest('.taxocsie-rule-row').remove();
	});
});
JS;
	}
}
