<?php
/**
 * Optional Freemius account opt-in for the site administrator.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FreemiusOptin {
	private const META_DISMISSED = 'taxocsie_freemius_optin_dismissed';
	private const META_COMPLETE  = 'taxocsie_freemius_optin_complete';
	private const META_PENDING   = 'taxocsie_freemius_optin_pending_email';

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_taxocsie_freemius_optin', array( __CLASS__, 'submit' ) );
		add_action( 'admin_post_taxocsie_freemius_optin_dismiss', array( __CLASS__, 'dismiss' ) );
		add_action( 'admin_init', array( __CLASS__, 'complete_pending' ) );
	}

	/** Show the consent choice to eligible administrators in WordPress admin. */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status message.
		$status = isset( $_GET['taxocsie_freemius_optin'] ) ? sanitize_key( wp_unslash( $_GET['taxocsie_freemius_optin'] ) ) : '';
		if ( 'error' === $status ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Freemius could not connect the site administrator email. Please try again.', 'taxonomy-csv-import-export' ) . '</p></div>';
		} elseif ( 'pending' === $status ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Please check your email to complete your Freemius account confirmation.', 'taxonomy-csv-import-export' ) . '</p></div>';
		} elseif ( 'success' === $status ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'The site administrator email was connected with Freemius.', 'taxonomy-csv-import-export' ) . '</p></div>';
		}

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::META_DISMISSED, true ) || get_user_meta( $user_id, self::META_COMPLETE, true ) || get_user_meta( $user_id, self::META_PENDING, true ) ) {
			return;
		}

		$sdk = Freemius::sdk();
		if ( ! is_object( $sdk ) || ! is_callable( array( $sdk, 'opt_in' ) ) ) {
			return;
		}

		$email = self::site_admin_email();
		if ( '' === $email || ! self::matches_account( $sdk, $email ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=taxocsie_freemius_optin_dismiss' ), 'taxocsie_freemius_optin_dismiss' );
		?>
		<div class="notice notice-info taxocsie-freemius-optin">
			<p><strong><?php esc_html_e( 'Get WP Taxonomy Manager updates', 'taxonomy-csv-import-export' ); ?></strong></p>
			<p><?php esc_html_e( 'Clicking Yes sends the site administrator email, site URL, WordPress and plugin versions, and enabled diagnostic information to Freemius for the plugin account.', 'taxonomy-csv-import-export' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="taxocsie_freemius_optin">
				<?php wp_nonce_field( 'taxocsie_freemius_optin' ); ?>
				<p>
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Yes, connect me', 'taxonomy-csv-import-export' ); ?></button>
					<a class="button" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'No thanks', 'taxonomy-csv-import-export' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/** Apply the current administrator's explicit consent through the SDK. */
	public static function submit(): void {
		self::guard( 'taxocsie_freemius_optin' );

		$sdk   = Freemius::sdk();
		$email = self::site_admin_email();
		if ( ! is_object( $sdk ) || '' === $email || ! self::matches_account( $sdk, $email ) ) {
			self::redirect( 'error' );
		}

		try {
			if ( ! $sdk->is_registered( true ) || $sdk->is_anonymous() ) {
				$result = $sdk->opt_in( $email, false, false, false, false, false, false, false, array(), false );
				if ( is_wp_error( $result ) || false === $result || ( is_object( $result ) && isset( $result->error ) ) ) {
					self::redirect( 'error' );
				}
				if ( ! $sdk->is_registered( true ) ) {
					if ( $sdk->is_pending_activation() ) {
						update_user_meta( get_current_user_id(), self::META_PENDING, $email );
						self::redirect( 'pending' );
					}
					self::redirect( 'error' );
				}
			}

			if ( ! self::matches_account( $sdk, $email ) ) {
				self::redirect( 'error' );
			}

		} catch ( \Throwable $throwable ) {
			self::redirect( 'error' );
		}

		update_user_meta( get_current_user_id(), self::META_COMPLETE, 1 );
		delete_user_meta( get_current_user_id(), self::META_PENDING );
		self::redirect( 'success' );
	}

	/** Finish a previously approved opt-in after Freemius confirms the email. */
	public static function complete_pending(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$email = get_user_meta( get_current_user_id(), self::META_PENDING, true );
		if ( ! is_string( $email ) || '' === $email || $email !== self::site_admin_email() ) {
			return;
		}

		$sdk = Freemius::sdk();
		if ( ! is_object( $sdk ) ) {
			return;
		}

		try {
			if ( ! $sdk->is_registered( true ) || ! self::matches_account( $sdk, $email ) ) {
				return;
			}
		} catch ( \Throwable $throwable ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::META_COMPLETE, 1 );
		delete_user_meta( get_current_user_id(), self::META_PENDING );
	}

	private static function site_admin_email(): string {
		$email = get_option( 'admin_email' );
		return is_string( $email ) && is_email( $email ) ? $email : '';
	}

	/** Dismiss the invitation only for this administrator. */
	public static function dismiss(): void {
		self::guard( 'taxocsie_freemius_optin_dismiss' );
		update_user_meta( get_current_user_id(), self::META_DISMISSED, 1 );
		self::redirect( '' );
	}

	private static function matches_account( $sdk, string $email ): bool {
		if ( ! is_callable( array( $sdk, 'is_registered' ) ) || ! $sdk->is_registered( true ) ) {
			return true;
		}
		$account = $sdk->get_user();
		return is_object( $account ) && isset( $account->email ) && 0 === strcasecmp( $account->email, $email );
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private static function redirect( string $status ): void {
		$url = admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG );
		if ( '' !== $status ) {
			$url = add_query_arg( 'taxocsie_freemius_optin', $status, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
