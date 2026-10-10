<?php
/** Permanent redirects for taxonomy URLs changed by this plugin. */
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TermRedirects {
	private const OPTION = 'taxocsie_term_redirects';
	private const PAGE = 'taxonomy-csv-import-export-redirects';
	private static $before = array();

	public static function init(): void {
		add_action( 'edit_terms', array( __CLASS__, 'before_edit' ), 10, 2 );
		add_action( 'edited_term', array( __CLASS__, 'after_edit' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 19 );
		add_action( 'admin_post_taxocsie_remove_redirect', array( __CLASS__, 'remove_redirect' ) );
	}

	public static function add_page(): void {
		add_submenu_page( TAXOCSIE_PLUGIN_SLUG, __( 'Term Redirects', 'taxonomy-csv-import-export' ), __( 'Term Redirects', 'taxonomy-csv-import-export' ), 'manage_options', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	/** Capture the old archive link before WordPress updates a term or its parent. */
	public static function before_edit( $term_id, $taxonomy ): void {
		$key = sanitize_key( $taxonomy ) . ':' . absint( $term_id );
		if ( isset( self::$before[ $key ] ) ) {
			return;
		}
		self::$before[ $key ] = self::term_path( absint( $term_id ), sanitize_key( $taxonomy ) );
	}

	public static function after_edit( $term_id, $tt_id, $taxonomy ): void {
		unset( $tt_id );
		$key = sanitize_key( $taxonomy ) . ':' . absint( $term_id );
		$old = self::$before[ $key ] ?? '';
		unset( self::$before[ $key ] );
		$new = self::term_path( absint( $term_id ), sanitize_key( $taxonomy ) );
		if ( $old && $new && $old !== $new ) {
			self::record( $old, absint( $term_id ), sanitize_key( $taxonomy ) );
		}
	}

	/** Only same-site, ordinary archive paths may become redirect sources. */
	public static function term_path( int $term_id, string $taxonomy ): string {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof \WP_Term || TermCustomLinks::get( $term_id ) ) {
			return '';
		}
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) || ! is_string( $url ) ) {
			return '';
		}
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$normalized = $path ? self::normalize_path( $path ) : '';
		return $home_host && $home_host === $host && '/' !== $normalized ? $normalized : '';
	}

	private static function normalize_path( string $path ): string {
		$path = rawurldecode( $path );
		$path = trim( $path, '/' );
		return '' === $path ? '/' : '/' . $path . '/';
	}

	/** Save a verified source path and a surviving destination term. */
	public static function record( string $source_path, int $target_id, string $taxonomy, string $token = '' ): void {
		$source_path = self::normalize_path( $source_path );
		$target_path = self::term_path( $target_id, $taxonomy );
		if ( '/' === $source_path || ! $target_path || $source_path === $target_path ) {
			return;
		}
		$rules = get_option( self::OPTION, array() );
		$rules = is_array( $rules ) ? $rules : array();
		$rules[ $source_path ] = array( 'target_id' => $target_id, 'taxonomy' => $taxonomy, 'token' => $token );
		update_option( self::OPTION, array_slice( $rules, -500, null, true ), false );
	}

	public static function remove_token( string $token ): void {
		if ( '' === $token ) {
			return;
		}
		$rules = get_option( self::OPTION, array() );
		if ( ! is_array( $rules ) ) {
			return;
		}
		foreach ( $rules as $path => $rule ) {
			if ( is_array( $rule ) && ( $rule['token'] ?? '' ) === $token ) {
				unset( $rules[ $path ] );
			}
		}
		update_option( self::OPTION, $rules, false );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		$rules = get_option( self::OPTION, array() );
		$rules = is_array( $rules ) ? $rules : array();
		?>
		<div class="wrap taxocsie-redirect-page"><h1><?php esc_html_e( 'Term Redirects', 'taxonomy-csv-import-export' ); ?></h1><p><?php esc_html_e( 'Old taxonomy URLs saved after a term URL changes or two terms are merged. A redirect runs only when the old URL is a 404.', 'taxonomy-csv-import-export' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only result. ?>
			<?php if ( isset( $_GET['removed'] ) ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'Redirect removed.', 'taxonomy-csv-import-export' ); ?></p></div><?php endif; ?>
			<?php if ( ! $rules ) : ?><p><?php esc_html_e( 'No redirects have been created yet.', 'taxonomy-csv-import-export' ); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Old path', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Current destination', 'taxonomy-csv-import-export' ); ?></th><th><?php esc_html_e( 'Action', 'taxonomy-csv-import-export' ); ?></th></tr></thead><tbody>
			<?php foreach ( $rules as $path => $rule ) : ?><?php $term = is_array( $rule ) ? get_term( absint( $rule['target_id'] ?? 0 ), sanitize_key( $rule['taxonomy'] ?? '' ) ) : null; $url = $term instanceof \WP_Term ? get_term_link( $term ) : ''; ?><tr><td><code><?php echo esc_html( $path ); ?></code></td><td><?php if ( is_string( $url ) && $url ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a><?php else : ?><?php esc_html_e( 'Destination missing', 'taxonomy-csv-import-export' ); ?><?php endif; ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="taxocsie_remove_redirect"><input type="hidden" name="path" value="<?php echo esc_attr( $path ); ?>"><?php wp_nonce_field( 'taxocsie_remove_redirect_' . $path ); ?><button class="button" type="submit"><?php esc_html_e( 'Remove', 'taxonomy-csv-import-export' ); ?></button></form></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div>
		<?php
	}

	public static function remove_redirect(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		$path = isset( $_POST['path'] ) && is_scalar( $_POST['path'] ) ? self::normalize_path( (string) wp_unslash( $_POST['path'] ) ) : '';
		check_admin_referer( 'taxocsie_remove_redirect_' . $path );
		$rules = get_option( self::OPTION, array() );
		if ( is_array( $rules ) && isset( $rules[ $path ] ) ) {
			unset( $rules[ $path ] );
			update_option( self::OPTION, $rules, false );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'removed' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function maybe_redirect(): void {
		if ( ! is_404() || is_admin() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		$path = self::normalize_path( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		$rules = get_option( self::OPTION, array() );
		$rule = is_array( $rules ) ? ( $rules[ $path ] ?? null ) : null;
		if ( ! is_array( $rule ) ) {
			return;
		}
		$taxonomy = sanitize_key( $rule['taxonomy'] ?? '' );
		$target = get_term( absint( $rule['target_id'] ?? 0 ), $taxonomy );
		if ( ! $target instanceof \WP_Term || TermCustomLinks::get( (int) $target->term_id ) ) {
			return;
		}
		$url = get_term_link( $target );
		$host = is_string( $url ) ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
		if ( is_wp_error( $url ) || $host !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) || self::normalize_path( (string) wp_parse_url( $url, PHP_URL_PATH ) ) === $path ) {
			return;
		}
		wp_safe_redirect( $url, 301, 'WP Taxonomy Manager' );
		exit;
	}
}
