<?php
/** Recent Free CSV imports and a previewed undo for the terms they created. */
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImportHistory {
	private const OPTION = 'taxocsie_import_history';
	private const LIMIT  = 5;

	public static function init(): void {
		add_action( 'admin_post_taxocsie_undo_import', array( __CLASS__, 'undo' ) );
	}

	/**
	 * Remember the terms one import created, newest first.
	 *
	 * @param int[] $term_ids Created term IDs in creation order.
	 * @return string Job ID, or '' when nothing was created.
	 */
	public static function record( string $taxonomy, string $file, array $term_ids ): string {
		$term_ids = array_values( array_filter( array_map( 'absint', $term_ids ) ) );
		if ( ! $term_ids ) {
			return '';
		}

		$id   = str_replace( '-', '', wp_generate_uuid4() );
		$jobs = self::jobs();
		array_unshift( $jobs, array(
			'id'       => $id,
			'time'     => time(),
			'user'     => get_current_user_id(),
			'taxonomy' => $taxonomy,
			'file'     => $file,
			'terms'    => $term_ids,
			'status'   => 'imported',
		) );
		update_option( self::OPTION, array_slice( $jobs, 0, self::LIMIT ), false );

		return $id;
	}

	/** Imports the current user may review, newest first. */
	public static function recent(): array {
		return array_values( array_filter( self::jobs(), array( __CLASS__, 'can_undo' ) ) );
	}

	/** Link to the import screen, optionally opening one import's undo preview. */
	public static function review_url( string $id = '' ): string {
		return self::url( '' !== $id ? array( 'undo_review' => $id ) : array() );
	}

	private static function jobs(): array {
		$jobs = get_option( self::OPTION, array() );
		return is_array( $jobs ) ? array_values( array_filter( $jobs, 'is_array' ) ) : array();
	}

	private static function find( string $id ): ?array {
		foreach ( self::jobs() as $job ) {
			if ( '' !== $id && ( $job['id'] ?? '' ) === $id ) {
				return $job;
			}
		}
		return null;
	}

	private static function can_undo( array $job ): bool {
		$tax = get_taxonomy( $job['taxonomy'] ?? '' );
		return $tax instanceof \WP_Taxonomy
			&& current_user_can( 'manage_options' )
			&& current_user_can( $tax->cap->manage_terms )
			&& current_user_can( $tax->cap->delete_terms );
	}

	/**
	 * Decide what undo would do with each created term.
	 *
	 * A term is removed only when it still exists, has no assigned content, is
	 * not a default term, and every child would also be removed. Terms are
	 * checked newest first so children are decided before their parents.
	 *
	 * @return array[] Items in creation order with id, name, slug, and state.
	 */
	public static function preview( array $job ): array {
		global $wpdb;
		$taxonomy = (string) $job['taxonomy'];
		$defaults = array_filter( array(
			absint( get_option( 'default_category' ) ),
			absint( get_option( 'default_product_cat' ) ),
			absint( get_option( 'default_' . $taxonomy ) ),
			absint( get_option( 'default_term_' . $taxonomy ) ),
		) );
		$removing = array();
		$items    = array();

		foreach ( array_reverse( (array) $job['terms'] ) as $term_id ) {
			$term_id = absint( $term_id );
			$term    = get_term( $term_id, $taxonomy );
			$item    = array( 'id' => $term_id, 'name' => '', 'slug' => '', 'state' => 'missing' );

			if ( $term instanceof \WP_Term ) {
				$item['name'] = $term->name;
				$item['slug'] = $term->slug;
				$children     = get_terms( array( 'taxonomy' => $taxonomy, 'parent' => $term_id, 'hide_empty' => false, 'fields' => 'ids' ) );
				// Live check that includes drafts; the cached term count can be stale or skip them.
				$has_objects  = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d LIMIT 1", $term->term_taxonomy_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				if ( in_array( $term_id, $defaults, true ) ) {
					$item['state'] = 'default';
				} elseif ( $has_objects ) {
					$item['state'] = 'in_use';
				} elseif ( is_wp_error( $children ) || array_diff( array_map( 'absint', $children ), array_keys( $removing ) ) ) {
					$item['state'] = 'has_children';
				} else {
					$item['state']        = 'remove';
					$removing[ $term_id ] = true;
				}
			}

			$items[] = $item;
		}

		return array_reverse( $items );
	}

	public static function undo(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'taxocsie_undo_import' );

		$id  = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		$job = self::find( $id );
		if ( ! $job || 'undone' === $job['status'] || ! self::can_undo( $job ) ) {
			self::redirect( array( 'undo_status' => 'unavailable' ) );
		}

		$removed = 0;
		$kept    = 0;
		foreach ( array_reverse( self::preview( $job ) ) as $item ) {
			if ( 'remove' === $item['state'] && true === wp_delete_term( $item['id'], $job['taxonomy'] ) ) {
				++$removed;
			} elseif ( 'missing' !== $item['state'] ) {
				++$kept;
			}
		}

		$jobs = self::jobs();
		foreach ( $jobs as &$entry ) {
			if ( ( $entry['id'] ?? '' ) === $id ) {
				$entry['status'] = $kept ? 'partial' : 'undone';
			}
		}
		unset( $entry );
		update_option( self::OPTION, $jobs, false );

		self::redirect( array( 'undo_status' => 'done', 'undo_removed' => $removed, 'undo_kept' => $kept ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => TAXOCSIE_PLUGIN_SLUG . '-manager', 'manager_view' => 'import' ), $args ),
			admin_url( 'admin.php' )
		) . '#taxocsie-import-history';
	}

	private static function redirect( array $args ): void {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	private static function state_label( string $state ): string {
		$labels = array(
			'remove'       => __( 'Will be deleted', 'taxonomy-csv-import-export' ),
			'in_use'       => __( 'Kept: assigned to content', 'taxonomy-csv-import-export' ),
			'has_children' => __( 'Kept: has other child terms', 'taxonomy-csv-import-export' ),
			'default'      => __( 'Kept: default term', 'taxonomy-csv-import-export' ),
			'missing'      => __( 'Already deleted', 'taxonomy-csv-import-export' ),
		);
		return $labels[ $state ] ?? $state;
	}

	public static function status_label( string $status ): string {
		$labels = array(
			'imported' => __( 'Imported', 'taxonomy-csv-import-export' ),
			'partial'  => __( 'Partly undone', 'taxonomy-csv-import-export' ),
			'undone'   => __( 'Undone', 'taxonomy-csv-import-export' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/** Render recent imports, the undo preview, and the undo result. */
	public static function render(): void {
		$jobs = self::recent();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only state.
		$status  = isset( $_GET['undo_status'] ) ? sanitize_key( wp_unslash( $_GET['undo_status'] ) ) : '';
		$review  = isset( $_GET['undo_review'] ) ? sanitize_key( wp_unslash( $_GET['undo_review'] ) ) : '';
		$removed = isset( $_GET['undo_removed'] ) ? absint( $_GET['undo_removed'] ) : 0;
		$kept    = isset( $_GET['undo_kept'] ) ? absint( $_GET['undo_kept'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $jobs && ! $status ) {
			return;
		}
		$review_job = self::find( $review );
		?>
		<div id="taxocsie-import-history" class="taxocsie-import-history">
			<?php if ( 'done' === $status ) : ?>
				<div class="notice notice-success inline taxocsie-panel__notice"><p>
					<?php
					echo esc_html( sprintf(
						/* translators: 1: deleted terms, 2: kept terms. */
						__( 'Undo finished: %1$d term(s) deleted; %2$d term(s) kept.', 'taxonomy-csv-import-export' ),
						$removed,
						$kept
					) );
					?>
				</p></div>
			<?php elseif ( 'unavailable' === $status ) : ?>
				<div class="notice notice-warning inline taxocsie-panel__notice"><p><?php esc_html_e( 'This import can no longer be undone.', 'taxonomy-csv-import-export' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $review_job && 'undone' !== $review_job['status'] && self::can_undo( $review_job ) ) : ?>
				<?php
				$items     = self::preview( $review_job );
				$removable = count( array_filter( $items, static function ( $item ) { return 'remove' === $item['state']; } ) );
				?>
				<h3 class="taxocsie-result__title"><?php echo esc_html( sprintf( /* translators: %s: file name. */ __( 'Undo import: %s', 'taxonomy-csv-import-export' ), $review_job['file'] ) ); ?></h3>
				<p><?php esc_html_e( 'Undo deletes the terms this import created. Terms that are now assigned to content, are a default term, or have child terms from elsewhere are kept. Existing terms that the import skipped are never changed.', 'taxonomy-csv-import-export' ); ?></p>
				<div class="taxocsie-panel__tablewrap">
					<table class="widefat striped taxocsie-panel__table">
						<thead><tr><th><?php esc_html_e( 'Term', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Undo result', 'taxonomy-csv-import-export' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr><td><?php echo esc_html( $item['name'] ); ?></td><td><code><?php echo esc_html( $item['slug'] ); ?></code></td><td><?php echo esc_html( self::state_label( $item['state'] ) ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if ( $removable ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="taxocsie-import-history__confirm">
						<input type="hidden" name="action" value="taxocsie_undo_import">
						<input type="hidden" name="job" value="<?php echo esc_attr( $review_job['id'] ); ?>">
						<?php wp_nonce_field( 'taxocsie_undo_import' ); ?>
						<a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Cancel', 'taxonomy-csv-import-export' ); ?></a>
						<button type="submit" class="button button-primary"><?php echo esc_html( sprintf( /* translators: %d: number of terms. */ _n( 'Delete %d term', 'Delete %d terms', $removable, 'taxonomy-csv-import-export' ), $removable ) ); ?></button>
					</form>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Nothing can be deleted for this import.', 'taxonomy-csv-import-export' ); ?></strong></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $jobs ) : ?>
				<h3 class="taxocsie-result__title"><?php esc_html_e( 'Recent CSV imports', 'taxonomy-csv-import-export' ); ?></h3>
				<div class="taxocsie-panel__tablewrap">
					<table class="widefat striped taxocsie-panel__table">
						<thead><tr><th><?php esc_html_e( 'Time', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Taxonomy', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'File', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Created terms', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Status', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Action', 'taxonomy-csv-import-export' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $jobs as $job ) : ?>
								<tr>
									<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $job['time'] ) ); ?></td>
									<td><?php echo esc_html( $job['taxonomy'] ); ?></td>
									<td><?php echo esc_html( $job['file'] ); ?></td>
									<td><?php echo esc_html( (string) count( (array) $job['terms'] ) ); ?></td>
									<td><?php echo esc_html( self::status_label( (string) $job['status'] ) ); ?></td>
									<td>
										<?php if ( 'undone' !== $job['status'] ) : ?>
											<a class="button" href="<?php echo esc_url( self::url( array( 'undo_review' => $job['id'] ) ) ); ?>"><?php esc_html_e( 'Review undo', 'taxonomy-csv-import-export' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
