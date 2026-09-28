<?php
/** Respectful, user-specific WordPress.org review prompt. */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewPrompt {
	private const META_DISMISSED = 'taxocsie_review_prompt_dismissed';
	private const META_SNOOZE = 'taxocsie_review_prompt_snooze_until';

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_taxocsie_review_prompt', array( __CLASS__, 'handle' ) );
	}

	public static function render(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || 0 !== strpos( $page, TAXOCSIE_PLUGIN_SLUG ) || ! self::eligible() ) { return; }
		$url = admin_url( 'admin-post.php?action=taxocsie_review_prompt' );
		?>
		<div class="notice notice-info taxocsie-review-prompt">
			<p><strong><?php esc_html_e( 'Enjoying WP Taxonomy Manager?', 'taxonomy-csv-import-export' ); ?></strong></p>
			<p><?php esc_html_e( 'A short, honest WordPress.org review helps other site owners discover the plugin.', 'taxonomy-csv-import-export' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'choice', 'review', $url ), 'taxocsie_review_prompt' ) ); ?>"><?php esc_html_e( 'Leave a review', 'taxonomy-csv-import-export' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'choice', 'later', $url ), 'taxocsie_review_prompt' ) ); ?>"><?php esc_html_e( 'Maybe later', 'taxonomy-csv-import-export' ); ?></a>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'choice', 'dismiss', $url ), 'taxocsie_review_prompt' ) ); ?>"><?php esc_html_e( 'Already reviewed / do not ask again', 'taxonomy-csv-import-export' ); ?></a>
			</p>
		</div>
		<?php
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'taxocsie_review_prompt' );
		$choice = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		if ( 'later' === $choice ) {
			update_user_meta( get_current_user_id(), self::META_SNOOZE, time() + ( 14 * DAY_IN_SECONDS ) );
		} else {
			update_user_meta( get_current_user_id(), self::META_DISMISSED, 1 );
		}
		if ( 'review' === $choice ) {
			wp_redirect( 'https://wordpress.org/support/plugin/taxonomy-csv-import-export/reviews/#new-post' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed trusted destination.
			exit;
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG ) );
		exit;
	}

	private static function eligible(): bool {
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::META_DISMISSED, true ) || time() < (int) get_user_meta( $user_id, self::META_SNOOZE, true ) ) { return false; }
		$installed = (int) get_option( 'taxocsie_installed', 0 );
		$aged = $installed > 0 && time() >= $installed + ( 7 * DAY_IN_SECONDS );
		return $aged || 3 <= (int) get_option( 'taxocsie_successful_imports', 0 );
	}
}
