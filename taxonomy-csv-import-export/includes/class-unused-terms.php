<?php
/** Previewed cleanup and restoration of genuinely unused taxonomy terms. */
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UnusedTerms {
	private const PAGE = 'taxonomy-csv-import-export-unused';
	private const HISTORY = 'taxocsie_unused_term_history';
	private const SCAN_LIMIT = 500;
	private const DELETE_LIMIT = 50;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 19 );
		add_action( 'admin_post_taxocsie_clean_unused', array( __CLASS__, 'delete_selected' ) );
		add_action( 'admin_post_taxocsie_restore_unused', array( __CLASS__, 'restore' ) );
	}

	public static function add_page(): void {
		add_submenu_page( TAXOCSIE_PLUGIN_SLUG, __( 'Unused Terms', 'taxonomy-csv-import-export' ), __( 'Unused Terms', 'taxonomy-csv-import-export' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	private static function page_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	private static function redirect( string $status, array $args = array() ): void {
		wp_safe_redirect( self::page_url( array_merge( array( 'status' => $status ), $args ) ) );
		exit;
	}

	/** A zero count alone is insufficient: drafts can still have relationships. */
	public static function is_safe_candidate( \WP_Term $term ): bool {
		global $wpdb;
		$tax = get_taxonomy( $term->taxonomy );
		if ( ! $tax || ! $tax->public || ! current_user_can( $tax->cap->delete_terms ) || 0 !== (int) $term->count ) {
			return false;
		}
		$defaults = array( absint( get_option( 'default_category' ) ), absint( get_option( 'default_product_cat' ) ), absint( get_option( 'default_' . $term->taxonomy ) ) );
		if ( in_array( (int) $term->term_id, $defaults, true ) || TermCustomLinks::get( (int) $term->term_id ) ) {
			return false;
		}
		$has_objects = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d LIMIT 1", $term->term_taxonomy_id ) );
		$has_children = get_terms( array( 'taxonomy' => $term->taxonomy, 'parent' => $term->term_id, 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' ) );
		return ! $has_objects && ! is_wp_error( $has_children ) && ! $has_children;
	}

	private static function history(): array {
		$history = get_option( self::HISTORY, array() );
		return is_array( $history ) ? $history : array();
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot access this page.', 'taxonomy-csv-import-export' ) );
		}
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$selected = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		$tax = $taxonomies[ $selected ] ?? null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scan pagination.
		$scan_page = isset( $_GET['scan_page'] ) ? max( 1, absint( $_GET['scan_page'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only result.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$terms = array();
		$scanned = 0;
		if ( $tax && current_user_can( $tax->cap->manage_terms ) && current_user_can( $tax->cap->delete_terms ) ) {
			$result = get_terms( array( 'taxonomy' => $selected, 'hide_empty' => false, 'number' => self::SCAN_LIMIT, 'offset' => ( $scan_page - 1 ) * self::SCAN_LIMIT, 'orderby' => 'name', 'order' => 'ASC' ) );
			if ( ! is_wp_error( $result ) ) {
				$scanned = count( $result );
				foreach ( $result as $term ) {
					if ( self::is_safe_candidate( $term ) ) {
						$terms[] = $term;
					}
				}
			}
		}
		?>
		<div class="wrap taxocsie-unused-page">
			<h1><?php esc_html_e( 'Unused Terms', 'taxonomy-csv-import-export' ); ?></h1>
			<p><?php esc_html_e( 'Review terms with no assigned content or child terms. Selected terms can be restored with a new ID after cleanup.', 'taxonomy-csv-import-export' ); ?></p>
			<?php if ( $status ) : ?><div class="notice <?php echo esc_attr( in_array( $status, array( 'deleted', 'restored' ), true ) ? 'notice-success' : 'notice-warning' ); ?> inline"><p><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></p></div><?php endif; ?>
			<form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>"><label for="taxocsie-unused-taxonomy"><?php esc_html_e( 'Taxonomy', 'taxonomy-csv-import-export' ); ?></label> <select id="taxocsie-unused-taxonomy" name="taxonomy"><option value=""><?php esc_html_e( 'Select taxonomy', 'taxonomy-csv-import-export' ); ?></option><?php foreach ( $taxonomies as $item ) : ?><?php if ( current_user_can( $item->cap->manage_terms ) && current_user_can( $item->cap->delete_terms ) ) : ?><option value="<?php echo esc_attr( $item->name ); ?>" <?php selected( $selected, $item->name ); ?>><?php echo esc_html( $item->label ); ?></option><?php endif; ?><?php endforeach; ?></select> <button class="button button-primary" type="submit"><?php esc_html_e( 'Preview unused terms', 'taxonomy-csv-import-export' ); ?></button></form>
			<?php if ( $tax ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: 1: candidates, 2: scanned terms, 3: scan page. */ __( '%1$d candidates found among %2$d terms on scan page %3$d.', 'taxonomy-csv-import-export' ), count( $terms ), $scanned, $scan_page ) ); ?></p>
				<p><?php if ( $scan_page > 1 ) : ?><a class="button" href="<?php echo esc_url( self::page_url( array( 'taxonomy' => $selected, 'scan_page' => $scan_page - 1 ) ) ); ?>"><?php esc_html_e( 'Previous 500', 'taxonomy-csv-import-export' ); ?></a> <?php endif; ?><?php if ( self::SCAN_LIMIT === $scanned ) : ?><a class="button" href="<?php echo esc_url( self::page_url( array( 'taxonomy' => $selected, 'scan_page' => $scan_page + 1 ) ) ); ?>"><?php esc_html_e( 'Next 500', 'taxonomy-csv-import-export' ); ?></a><?php endif; ?></p>
				<?php if ( $terms ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="taxocsie_clean_unused"><input type="hidden" name="taxonomy" value="<?php echo esc_attr( $selected ); ?>"><?php wp_nonce_field( 'taxocsie_clean_unused' ); ?><table class="widefat striped"><thead><tr><th><span class="screen-reader-text"><?php esc_html_e( 'Select', 'taxonomy-csv-import-export' ); ?></span></th><th><?php esc_html_e( 'Name', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Slug', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Description', 'taxonomy-csv-import-export' ); ?></th></tr></thead><tbody><?php foreach ( $terms as $term ) : ?><tr><td><input type="checkbox" name="term_ids[]" value="<?php echo esc_attr( $term->term_id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: term name. */ __( 'Select %s', 'taxonomy-csv-import-export' ), $term->name ) ); ?>"></td><td><?php echo esc_html( $term->name ); ?></td><td><code><?php echo esc_html( $term->slug ); ?></code></td><td><?php echo esc_html( wp_trim_words( $term->description, 15 ) ); ?></td></tr><?php endforeach; ?></tbody></table><p><label><?php esc_html_e( 'Type DELETE to confirm:', 'taxonomy-csv-import-export' ); ?> <input type="text" name="confirmation" required pattern="DELETE" autocomplete="off"></label> <button type="submit" class="button button-primary"><?php esc_html_e( 'Delete selected (up to 50)', 'taxonomy-csv-import-export' ); ?></button></p></form><?php endif; ?>
			<?php endif; ?>
			<?php self::render_history(); ?>
		</div>
		<?php
	}

	public static function delete_selected(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'taxocsie_clean_unused' );
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$tax = get_taxonomy( $taxonomy );
		$ids = isset( $_POST['term_ids'] ) && is_array( $_POST['term_ids'] ) ? wp_unslash( $_POST['term_ids'] ) : array();
		$confirmation = isset( $_POST['confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) : '';
		if ( ! $tax || ! $tax->public || ! current_user_can( $tax->cap->delete_terms ) || 'DELETE' !== $confirmation || ! $ids || count( $ids ) > self::DELETE_LIMIT ) {
			self::redirect( 'invalid_selection', array( 'taxonomy' => $taxonomy ) );
		}
		$terms = array();
		foreach ( array_unique( $ids ) as $raw_id ) {
			if ( ! is_scalar( $raw_id ) || ! ctype_digit( (string) $raw_id ) ) {
				self::redirect( 'invalid_selection', array( 'taxonomy' => $taxonomy ) );
			}
			$term = get_term( absint( $raw_id ), $taxonomy );
			if ( ! $term instanceof \WP_Term || ! self::is_safe_candidate( $term ) ) {
				self::redirect( 'terms_changed_review_again', array( 'taxonomy' => $taxonomy ) );
			}
			$terms[] = $term;
		}
		$token = str_replace( '-', '', wp_generate_uuid4() );
		$entry = array( 'time' => time(), 'taxonomy' => $taxonomy, 'status' => 'pending', 'terms' => array() );
		foreach ( $terms as $term ) {
			$entry['terms'][] = array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'parent' => (int) $term->parent, 'meta' => get_term_meta( $term->term_id ), 'deleted' => false );
		}
		if ( ! update_option( 'taxocsie_unused_' . $token, $entry, false ) ) {
			self::redirect( 'backup_failed', array( 'taxonomy' => $taxonomy ) );
		}
		$history = self::history();
		array_unshift( $history, $token );
		foreach ( array_slice( $history, 20 ) as $old_token ) {
			delete_option( 'taxocsie_unused_' . $old_token );
		}
		update_option( self::HISTORY, array_slice( $history, 0, 20 ), false );
		$deleted = 0;
		foreach ( $terms as $index => $term ) {
			$fresh = get_term( $term->term_id, $taxonomy );
			if ( ! $fresh instanceof \WP_Term || ! self::is_safe_candidate( $fresh ) ) {
				break;
			}
			if ( true !== wp_delete_term( $term->term_id, $taxonomy ) ) {
				break;
			}
			$entry['terms'][ $index ]['deleted'] = true;
			++$deleted;
			update_option( 'taxocsie_unused_' . $token, $entry, false );
		}
		$entry['status'] = $deleted === count( $terms ) ? 'deleted' : 'partial';
		update_option( 'taxocsie_unused_' . $token, $entry, false );
		self::redirect( $entry['status'], array( 'taxonomy' => $taxonomy ) );
	}

	public static function restore(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'taxocsie_restore_unused' );
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$entry = in_array( $token, self::history(), true ) ? get_option( 'taxocsie_unused_' . $token, array() ) : array();
		$tax = is_array( $entry ) ? get_taxonomy( $entry['taxonomy'] ?? '' ) : null;
		if ( ! $tax || ! current_user_can( $tax->cap->manage_terms ) || ! current_user_can( $tax->cap->edit_terms ) || ! in_array( $entry['status'] ?? '', array( 'pending', 'deleted', 'partial', 'restore_partial' ), true ) ) {
			self::redirect( 'restore_unavailable' );
		}
		$restored = 0;
		foreach ( $entry['terms'] as &$item ) {
			$original_exists = ! empty( $item['id'] ) && get_term( (int) $item['id'], $tax->name ) instanceof \WP_Term;
			if ( ( empty( $item['deleted'] ) && $original_exists ) || ! empty( $item['restored_id'] ) || term_exists( $item['slug'], $tax->name ) || ( $item['parent'] && ! term_exists( $item['parent'], $tax->name ) ) ) {
				continue;
			}
			$new = wp_insert_term( $item['name'], $tax->name, array( 'slug' => $item['slug'], 'description' => $item['description'], 'parent' => $item['parent'] ) );
			if ( is_wp_error( $new ) ) {
				continue;
			}
			$item['restored_id'] = (int) $new['term_id'];
			foreach ( $item['meta'] as $key => $values ) {
				foreach ( $values as $value ) {
					add_term_meta( $item['restored_id'], $key, maybe_unserialize( $value ) );
				}
			}
			++$restored;
			update_option( 'taxocsie_unused_' . $token, $entry, false );
		}
		unset( $item );
		$pending = array_filter( $entry['terms'], static function ( $item ) use ( $tax ) { return empty( $item['restored_id'] ) && ( ! empty( $item['deleted'] ) || ( ! empty( $item['id'] ) && ! get_term( (int) $item['id'], $tax->name ) ) ); } );
		$entry['status'] = $pending ? 'restore_partial' : 'restored';
		update_option( 'taxocsie_unused_' . $token, $entry, false );
		self::redirect( $restored ? $entry['status'] : 'restore_unavailable', array( 'taxonomy' => $tax->name ) );
	}

	private static function render_history(): void {
		$rows = array();
		foreach ( self::history() as $token ) {
			$entry = get_option( 'taxocsie_unused_' . $token, array() );
			$tax = is_array( $entry ) ? get_taxonomy( $entry['taxonomy'] ?? '' ) : null;
			if ( $tax && current_user_can( $tax->cap->manage_terms ) ) {
				$rows[ $token ] = $entry;
			}
		}
		if ( ! $rows ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Cleanup history', 'taxonomy-csv-import-export' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time', 'taxonomy-csv-import-export' ) . '</th><th>' . esc_html__( 'Taxonomy', 'taxonomy-csv-import-export' ) . '</th><th>' . esc_html__( 'Terms', 'taxonomy-csv-import-export' ) . '</th><th>' . esc_html__( 'Status', 'taxonomy-csv-import-export' ) . '</th><th>' . esc_html__( 'Action', 'taxonomy-csv-import-export' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $token => $entry ) {
			echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ) . '</td><td>' . esc_html( $entry['taxonomy'] ) . '</td><td>' . esc_html( implode( ', ', array_map( static function ( $item ) { return $item['name']; }, $entry['terms'] ) ) ) . '</td><td>' . esc_html( $entry['status'] ) . '</td><td>';
			if ( in_array( $entry['status'], array( 'pending', 'deleted', 'partial', 'restore_partial' ), true ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="taxocsie_restore_unused"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
				wp_nonce_field( 'taxocsie_restore_unused' );
				echo '<button class="button" type="submit">' . esc_html__( 'Restore terms', 'taxonomy-csv-import-export' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
