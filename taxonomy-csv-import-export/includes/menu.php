<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TaxoCSIE\Helper;

class Menu {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'add_taxocsie_menu_page' ) );
		add_filter( 'admin_footer_text', '__return_empty_string', 11 );
	}

	public function add_taxocsie_menu_page() {
		$icon_url   = $this->tcie_encode_svg_to_base64( TAXOCSIE_ASSETS_DIR_PATH . '/images/menu-button.svg' );
		$page_title = __( 'Import Export', 'taxonomy-csv-import-export' );
		add_menu_page( $page_title, $page_title, 'manage_options', TAXOCSIE_PLUGIN_SLUG, [ $this, 'get_taxocsie_export_function' ], $icon_url, 41 );
		foreach ( Helper::taxoscie_main_menu_title() as $item_key => $item ) {
			add_submenu_page( $item['parent_slug'], $item['title'], $item['title'], $item['capability'], $item_key, [ $this, $item['callback'] ] );
		}
	}

	/* -------------------------------------------------------
	   Shared: page header
	------------------------------------------------------- */
	private function page_header( string $title, string $desc = '' ): void {
		?>
		<div class="taxocsie-page-header">
			<h1 class="taxocsie-page-header__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $desc ) : ?>
				<p class="taxocsie-page-header__desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* -------------------------------------------------------
	   Taxonomy — Import / Export (MODIFIED with Dry Run)
	------------------------------------------------------- */
	public function get_taxocsie_export_function() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$default_mode = isset( $_GET['imported'] ) ? 'import' : 'export';

		// Check if preview is requested.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show_preview = isset( $_GET['preview'] ) && '1' === $_GET['preview'];
		?>
		<div class="taxocsie-wrap">

			<?php
			$this->page_header(
				__( 'Taxonomy Import / Export', 'taxonomy-csv-import-export' ),
				__(
					'Export taxonomy terms to a CSV file, or import terms from a CSV with dry run preview support.',
					'taxonomy-csv-import-export'
				)
			);
			?>

			<div class="taxocsie-tabs">
				<button
					type="button"
					class="taxocsie-tab<?php echo 'export' === $default_mode ? ' is-active' : ''; ?>"
					data-panel="taxocsie-export-panel"
				>
					<?php esc_html_e( 'Export CSV', 'taxonomy-csv-import-export' ); ?>
				</button>

				<button
					type="button"
					class="taxocsie-tab<?php echo 'import' === $default_mode ? ' is-active' : ''; ?>"
					data-panel="taxocsie-import-panel"
				>
					<?php esc_html_e( 'Import CSV', 'taxonomy-csv-import-export' ); ?>
				</button>
			</div>

			<!-- Export Panel -->
			<div
				id="taxocsie-export-panel"
				class="taxocsie-panel"
				<?php echo 'export' !== $default_mode ? ' style="display:none;"' : ''; ?>
			>
				<h2 class="taxocsie-panel__title">
					<?php esc_html_e( 'Export Taxonomy', 'taxonomy-csv-import-export' ); ?>
				</h2>

				<form class="taxocsie-panel__form" method="post">
					<?php wp_nonce_field( 'taxonomy_csv_export', 'taxonomy_csv_export_nonce' ); ?>

					<div class="taxocsie-panel__row">
						<select
							class="taxocsie-panel__select"
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

						<div class="taxocsie-panel__actions">
							<button
								type="button"
								id="export-preview-btn"
								class="button taxocsie-panel__btn-preview"
							>
								<?php esc_html_e( 'Preview', 'taxonomy-csv-import-export' ); ?>
							</button>

							<button
								type="submit"
								name="taxonomy_export_csv"
								class="button button-primary taxocsie-panel__btn-submit"
							>
								<?php esc_html_e( 'Export CSV', 'taxonomy-csv-import-export' ); ?>
							</button>
						</div>
					</div>
				</form>

				<div
					id="export-preview-container"
					class="taxocsie-panel__preview"
					<?php echo ! $show_preview ? 'style="display:none;"' : ''; ?>
				></div>
			</div>

			<!-- Import Panel -->
			<div
				id="taxocsie-import-panel"
				class="taxocsie-panel"
				<?php echo 'import' !== $default_mode ? ' style="display:none;"' : ''; ?>
			>
				<h2 class="taxocsie-panel__title">
					<?php esc_html_e( 'Import Taxonomy', 'taxonomy-csv-import-export' ); ?>
				</h2>

				<form
					class="taxocsie-panel__form"
					method="post"
					enctype="multipart/form-data"
				>
					<?php wp_nonce_field( 'taxonomy_csv_import', 'taxonomy_csv_import_nonce' ); ?>

					<div class="taxocsie-panel__row">

						<select
							class="taxocsie-panel__select"
							id="taxonomy"
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

						<input
							class="taxocsie-panel__file"
							type="file"
							name="csv_file"
							id="csv_file"
							accept=".csv"
							required
						>

						<div class="taxocsie-panel__actions">
							<button
								type="submit"
								name="taxonomy_import_csv"
								class="button button-primary taxocsie-panel__btn-submit"
							>
								<?php esc_html_e( 'Import CSV', 'taxonomy-csv-import-export' ); ?>
							</button>
						</div>

					</div>
				</form>

				<!-- Import Results -->
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['imported'] ) ) :
					$csv_data     = get_transient( 'taxoscie_csv_data' );
					$skipped_data = get_transient( 'taxoscie_csv_skipped' );

					$csv_data     = is_array( $csv_data ) ? $csv_data : array();
					$skipped_data = is_array( $skipped_data ) ? $skipped_data : array();

					$imported_count = count( $csv_data );
					$skipped_count  = count( $skipped_data );
					?>

					<div class="notice notice-success taxocsie-panel__notice">
						<p>
							<?php
							printf(
								/* translators: 1: imported count, 2: skipped count. */
								esc_html__(
									'Import completed. %1$d term(s) imported and %2$d duplicate/invalid term(s) skipped.',
									'taxonomy-csv-import-export'
								),
								esc_html( $imported_count ),
								esc_html( $skipped_count )
							);
							?>
						</p>
					</div>

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
							<table class="widefat striped taxocsie-panel__table">
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
							<table class="widefat striped taxocsie-panel__table taxocsie-panel__table--skipped">
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
		</div>

		<?php
		$this->taxocsie_custom_admin_footer();
	}

	/* -------------------------------------------------------
	   Create Term (UNCHANGED)
	------------------------------------------------------- */
	public function get_taxocsie_create_term_function() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		?>
		<div class="taxocsie-wrap">

			<?php $this->page_header(
				__( 'Create Term', 'taxonomy-csv-import-export' ),
				__( 'Select a taxonomy then add a new term with a title and optional description.', 'taxonomy-csv-import-export' )
			); ?>

			<div class="taxocsie-panel">
				<h2 class="taxocsie-panel__title">
					<?php esc_html_e( 'Choose a Taxonomy', 'taxonomy-csv-import-export' ); ?>
				</h2>

				<div class="taxocsie-taxonomy-list">
					<?php foreach ( $taxonomies as $taxonomy ) : ?>
						<div class="taxocsie-taxonomy-row" data-taxonomy="<?php echo esc_attr( $taxonomy->name ); ?>">

							<button type="button" class="taxocsie-taxonomy-row__header">
								<span class="taxocsie-taxonomy-row__label"><?php echo esc_html( $taxonomy->label ); ?></span>
								<span class="taxocsie-taxonomy-row__slug"><?php echo esc_html( $taxonomy->name ); ?></span>
								<span class="taxocsie-taxonomy-row__arrow">&#8964;</span>
							</button>

							<div class="taxocsie-taxonomy-row__body" style="display:none;">
								<div class="taxocsie-taxonomy-row__fields">

									<div class="taxocsie-panel__field">
										<label class="taxocsie-panel__field-label">
											<?php esc_html_e( 'Title', 'taxonomy-csv-import-export' ); ?>
											<span class="taxocsie-required">*</span>
										</label>
										<input
											class="taxocsie-panel__input taxocsie-row-title"
											type="text"
											placeholder="<?php esc_attr_e( 'Enter term title…', 'taxonomy-csv-import-export' ); ?>"
										>
									</div>

									<div class="taxocsie-panel__field">
										<label class="taxocsie-panel__field-label">
											<?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?>
											<span class="taxocsie-optional"><?php esc_html_e( '(optional)', 'taxonomy-csv-import-export' ); ?></span>
										</label>
										<textarea
											class="taxocsie-panel__textarea taxocsie-row-desc"
											rows="2"
											placeholder="<?php esc_attr_e( 'Enter description…', 'taxonomy-csv-import-export' ); ?>"
										></textarea>
									</div>

								</div>

								<div class="taxocsie-taxonomy-row__footer">
									<button type="button" class="button button-primary taxocsie-panel__btn-submit taxocsie-row-create-btn">
										<?php esc_html_e( 'Create Term', 'taxonomy-csv-import-export' ); ?>
									</button>
									<div class="taxocsie-row-feedback" style="display:none;"></div>
								</div>
							</div>

						</div>
					<?php endforeach; ?>
				</div>
			</div>

		</div>
		<?php
		$this->taxocsie_custom_admin_footer();
	}

	/* -------------------------------------------------------
	   Register Taxonomy (UNCHANGED)
	------------------------------------------------------- */
	public function get_taxocsie_register_taxonomy_function() {
		$post_types       = get_post_types( array( 'public' => true ), 'objects' );
		$saved_taxonomies = TaxonomyCreator::get_saved();
		?>
		<div class="taxocsie-wrap">

			<?php $this->page_header(
				__( 'Register Taxonomy', 'taxonomy-csv-import-export' ),
				__( 'Create a new custom taxonomy and attach it to one or more post types.', 'taxonomy-csv-import-export' )
			); ?>

			<div class="taxocsie-panel">
				<h2 class="taxocsie-panel__title">
					<?php esc_html_e( 'New Taxonomy', 'taxonomy-csv-import-export' ); ?>
				</h2>

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

					<div class="taxocsie-panel__field">
						<p class="taxocsie-panel__field-label">
							<?php esc_html_e( 'Taxonomy Type', 'taxonomy-csv-import-export' ); ?>
							<span class="taxocsie-required">*</span>
						</p>
						<div class="taxocsie-type-options">
							<label class="taxocsie-type-option taxocsie-type-option--active">
								<input type="radio" name="reg-type" value="0" checked>
								<span class="taxocsie-type-option__title"><?php esc_html_e( 'Tags', 'taxonomy-csv-import-export' ); ?></span>
								<span class="taxocsie-type-option__desc"><?php esc_html_e( 'Non-hierarchical. Flat list of terms.', 'taxonomy-csv-import-export' ); ?></span>
							</label>
							<label class="taxocsie-type-option">
								<input type="radio" name="reg-type" value="1">
								<span class="taxocsie-type-option__title"><?php esc_html_e( 'Categories', 'taxonomy-csv-import-export' ); ?></span>
								<span class="taxocsie-type-option__desc"><?php esc_html_e( 'Hierarchical. Terms can have parent terms.', 'taxonomy-csv-import-export' ); ?></span>
							</label>
						</div>
					</div>

					<div class="taxocsie-panel__field">
						<p class="taxocsie-panel__field-label">
							<?php esc_html_e( 'Attach to Post Types', 'taxonomy-csv-import-export' ); ?>
							<span class="taxocsie-required">*</span>
						</p>
						<div class="taxocsie-post-types-grid">
							<?php foreach ( $post_types as $pt ) : ?>
								<label class="taxocsie-checkbox-label">
									<input type="checkbox" class="reg-post-type" value="<?php echo esc_attr( $pt->name ); ?>">
									<span><?php echo esc_html( $pt->label . ' (' . $pt->name . ')' ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="taxocsie-panel__actions">
						<button type="button" id="taxocsie-reg-submit" class="button button-primary taxocsie-panel__btn-submit">
							<?php esc_html_e( 'Register Taxonomy', 'taxonomy-csv-import-export' ); ?>
						</button>
						<div id="taxocsie-reg-feedback" class="taxocsie-reg-feedback" style="display:none;"></div>
					</div>

				</div>
			</div>

			<div class="taxocsie-panel taxocsie-panel--mt" id="taxocsie-reg-panel"<?php echo empty( $saved_taxonomies ) ? ' style="display:none;"' : ''; ?>>
				<h2 class="taxocsie-panel__title">
					<?php esc_html_e( 'Registered Custom Taxonomies', 'taxonomy-csv-import-export' ); ?>
				</h2>
				<div class="taxocsie-panel__tablewrap taxocsie-panel__tablewrap--flush">
					<table class="widefat striped taxocsie-panel__table" id="taxocsie-reg-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Label', 'taxonomy-csv-import-export' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?></th>
								<th><?php esc_html_e( 'Type', 'taxonomy-csv-import-export' ); ?></th>
								<th><?php esc_html_e( 'Post Types', 'taxonomy-csv-import-export' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'taxonomy-csv-import-export' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $saved_taxonomies as $slug => $tax ) : ?>
								<tr data-slug="<?php echo esc_attr( $slug ); ?>">
									<td><strong><?php echo esc_html( $tax['label'] ); ?></strong></td>
									<td><code><?php echo esc_html( $slug ); ?></code></td>
									<td><?php echo ! empty( $tax['hierarchical'] ) ? esc_html__( 'Categories', 'taxonomy-csv-import-export' ) : esc_html__( 'Tags', 'taxonomy-csv-import-export' ); ?></td>
									<td><?php echo esc_html( implode( ', ', $tax['post_types'] ) ); ?></td>
									<td>
										<button type="button" class="button taxocsie-delete-taxonomy" data-slug="<?php echo esc_attr( $slug ); ?>">
											<?php esc_html_e( 'Delete', 'taxonomy-csv-import-export' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

		</div>
		<?php
		$this->taxocsie_custom_admin_footer();
	}

	/* -------------------------------------------------------
	   Users — Import / Export (UNCHANGED)
	------------------------------------------------------- */
	public function taxocsie_users_export_import_function() {
		global $wp_roles;
		$roles = $wp_roles->get_names();
		?>
		<div class="taxocsie-wrap">

			<?php $this->page_header(
				__( 'User Import / Export', 'taxonomy-csv-import-export' ),
				__( 'Export users to a CSV file filtered by role, or import users from a CSV.', 'taxonomy-csv-import-export' )
			); ?>

			<div class="taxocsie-two-col">

				<!-- Export -->
				<div class="taxocsie-panel">
					<h2 class="taxocsie-panel__title">
						<?php esc_html_e( 'Export Users', 'taxonomy-csv-import-export' ); ?>
					</h2>
					<form class="taxocsie-panel__form" method="get" action="">

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
							<button type="submit" name="taxocsie_export_users" class="button button-primary taxocsie-panel__btn-submit">
								<?php esc_html_e( 'Export CSV', 'taxonomy-csv-import-export' ); ?>
							</button>
						</div>

					</form>
				</div>

				<!-- Import -->
				<div class="taxocsie-panel">
					<h2 class="taxocsie-panel__title">
						<?php esc_html_e( 'Import Users', 'taxonomy-csv-import-export' ); ?>
					</h2>

					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( isset( $_GET['user-imported'] ) && $_GET['user-imported'] === 'success' ) : ?>
						<div class="notice notice-success taxocsie-panel__notice">
							<p><?php esc_html_e( 'Users imported successfully!', 'taxonomy-csv-import-export' ); ?></p>
						</div>
					<?php endif; ?>

					<form class="taxocsie-panel__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'taxocsie_import_users_nonce', 'taxocsie_nonce' ); ?>

						<div class="taxocsie-panel__field">
							<label class="taxocsie-panel__field-label" for="taxocsie_import_file">
								<?php esc_html_e( 'CSV File', 'taxonomy-csv-import-export' ); ?>
								<span class="taxocsie-required">*</span>
							</label>
							<input class="taxocsie-panel__file taxocsie-panel__file--block" type="file" id="taxocsie_import_file" name="taxocsie_import_file" accept=".csv" required>
							<span class="taxocsie-field-hint"><?php esc_html_e( 'Columns: username, email, password_hash, roles, first_name, last_name, meta', 'taxonomy-csv-import-export' ); ?></span>
						</div>

						<div class="taxocsie-panel__actions">
							<button type="submit" name="taxocsie_import_users" class="button button-primary taxocsie-panel__btn-submit">
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

	public function taxocsie_custom_admin_footer() {}

	/* -------------------------------------------------------
	   NEW: Demo Generator - Create Demo Categories/Tags
	------------------------------------------------------- */
	public function get_taxocsie_demo_generator_function() {
		$taxonomies = get_taxonomies(array('public' => true), 'objects');
		$demo_status = $this->handle_demo_generation();
		?>
		<div class="taxocsie-wrap">

			<?php $this->page_header(
				__('Demo Generator', 'taxonomy-csv-import-export'),
				__('Quickly generate demo categories or tags with one click. Perfect for testing and development.', 'taxonomy-csv-import-export')
			); ?>

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
									<option value=""><?php esc_html_e('— Select —', 'taxonomy-csv-import-export'); ?></option>
									<?php foreach ($taxonomies as $taxonomy) : ?>
										<option value="<?php echo esc_attr($taxonomy->name); ?>">
											<?php echo esc_html($taxonomy->label . ' (' . $taxonomy->name . ')'); ?>
											<?php if ($taxonomy->hierarchical) : ?>
												<?php esc_html_e('[Hierarchical]', 'taxonomy-csv-import-export'); ?>
											<?php else : ?>
												<?php esc_html_e('[Flat]', 'taxonomy-csv-import-export'); ?>
											<?php endif; ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="taxocsie-panel__field">
								<label class="taxocsie-panel__field-label" for="demo_count">
									<?php esc_html_e('Number of Terms', 'taxonomy-csv-import-export'); ?>
									<span class="taxocsie-required">*</span>
								</label>
								<div class="taxocsie-demo-count-wrapper">
									<input class="taxocsie-panel__input" type="number" id="demo_count" name="demo_count" 
										min="1" max="500" value="10" required style="width: 100px;">
									<span class="taxocsie-field-hint" style="margin-left: 10px;">
										<?php esc_html_e('(1-500)', 'taxonomy-csv-import-export'); ?>
									</span>
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
										<span><?php esc_html_e('Flat (all root terms)', 'taxonomy-csv-import-export'); ?></span>
									</label>
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_structure" value="hierarchical">
										<span><?php esc_html_e('Hierarchical (with parent-child relationships)', 'taxonomy-csv-import-export'); ?></span>
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
										<span><?php esc_html_e('With Prefix', 'taxonomy-csv-import-export'); ?></span>
									</label>
									<label class="taxocsie-checkbox-label">
										<input type="radio" name="demo_name_format" value="plain">
										<span><?php esc_html_e('Plain (Demo Term 1, Demo Term 2...)', 'taxonomy-csv-import-export'); ?></span>
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
									<span><?php esc_html_e('Add descriptions to terms', 'taxonomy-csv-import-export'); ?></span>
								</label>
								<br>
								<label class="taxocsie-checkbox-label">
									<input type="checkbox" name="demo_skip_existing" value="1" checked>
									<span><?php esc_html_e('Skip existing terms with same slug', 'taxonomy-csv-import-export'); ?></span>
								</label>
								<br>
								<label class="taxocsie-checkbox-label">
									<input type="checkbox" name="demo_show_progress" value="1" checked>
									<span><?php esc_html_e('Show progress during generation', 'taxonomy-csv-import-export'); ?></span>
								</label>
							</div>
						</div>

						<div class="taxocsie-panel__actions">
							<button type="submit" name="taxocsie_demo_generate" class="button button-primary taxocsie-panel__btn-submit" id="taxocsie-demo-btn">
								<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 5px;"></span>
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
		$this->taxocsie_custom_admin_footer();
	}

	/**
	 * Handle demo generation form submission
	 */
	private function handle_demo_generation() {
		if (!isset($_POST['taxocsie_demo_generate'])) {
			return false;
		}

		if (!wp_verify_nonce($_POST['taxocsie_demo_nonce'], 'taxocsie_demo_generate')) {
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

		$taxonomy = isset($_POST['demo_taxonomy']) ? sanitize_text_field(wp_unslash($_POST['demo_taxonomy'])) : '';
		$count = isset($_POST['demo_count']) ? intval($_POST['demo_count']) : 10;
		$structure = isset($_POST['demo_structure']) ? sanitize_text_field(wp_unslash($_POST['demo_structure'])) : 'flat';
		$name_format = isset($_POST['demo_name_format']) ? sanitize_text_field(wp_unslash($_POST['demo_name_format'])) : 'prefix';
		$add_description = isset($_POST['demo_add_description']);
		$skip_existing = isset($_POST['demo_skip_existing']);
		$show_progress = isset($_POST['demo_show_progress']);

		if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
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

		// Generate the terms
		$results = $this->generate_demo_terms($taxonomy, $count, $structure, $name_format, $add_description, $skip_existing);

		if ($show_progress) {
			// Store results in transient for display
			set_transient('taxocsie_demo_results', $results, 60);
		}

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

			// Generate slug
			$slug = sanitize_title($name);
			
			// Ensure unique slug
			$original_slug = $slug;
			$counter = 1;
			while (term_exists($slug, $taxonomy) || in_array($slug, $existing_slugs)) {
				$slug = $original_slug . '-' . $counter;
				$counter++;
			}
			$existing_slugs[] = $slug;

			// Check if term exists with this slug
			$existing_term = term_exists($slug, $taxonomy);
			if ($existing_term && $skip_existing) {
				$results['skipped']++;
				continue;
			}

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
		<table class="widefat striped taxocsie-panel__table">
			<thead>
				<tr>
					<th><?php esc_html_e('Taxonomy', 'taxonomy-csv-import-export'); ?></th>
					<th><?php esc_html_e('Type', 'taxonomy-csv-import-export'); ?></th>
					<th><?php esc_html_e('Total Terms', 'taxonomy-csv-import-export'); ?></th>
					<th><?php esc_html_e('Actions', 'taxonomy-csv-import-export'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($taxonomies as $taxonomy) : 
					$term_count = wp_count_terms(array(
						'taxonomy' => $taxonomy->name,
						'hide_empty' => false
					));
					$type_label = $taxonomy->hierarchical ? esc_html__('Hierarchical', 'taxonomy-csv-import-export') : esc_html__('Flat', 'taxonomy-csv-import-export');
				?>
					<tr>
						<td><strong><?php echo esc_html($taxonomy->label); ?></strong><br>
							<code><?php echo esc_html($taxonomy->name); ?></code>
						</td>
						<td><?php echo number_format_i18n( absint( $term_count) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td>
							<button type="button" class="button taxocsie-quick-btn" data-taxonomy="<?php echo esc_attr($taxonomy->name); ?>" data-count="10">
								+10
							</button>
							<button type="button" class="button taxocsie-quick-btn" data-taxonomy="<?php echo esc_attr($taxonomy->name); ?>" data-count="25">
								+25
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}