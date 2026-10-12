<?php
/** Optional email updates invitation for site administrators. */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UpdatesOptin {
	private const META_DISMISSED = 'taxocsie_updates_optin_dismissed';
	private const META_COMPLETE = 'taxocsie_updates_optin_complete';
	private const OPTION_SITE_COMPLETE = 'taxocsie_updates_optin_site_complete';
	private const ENDPOINT = 'https://wp-taxonomy-manager.com/wp-json/wtmp-updates/v1/subscribe';

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_taxocsie_updates_optin', array( __CLASS__, 'submit' ) );
		add_action( 'admin_post_taxocsie_updates_optin_dismiss', array( __CLASS__, 'dismiss' ) );
	}

	public static function render(): void {
		if ( ! self::is_site_administrator() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status message.
		$status = isset( $_GET['taxocsie_updates_optin'] ) ? sanitize_key( wp_unslash( $_GET['taxocsie_updates_optin'] ) ) : '';
		if ( 'success' === $status ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'You are subscribed to WP Taxonomy Manager email updates.', 'taxonomy-csv-import-export' ) . '</p></div>';
		} elseif ( in_array( $status, array( 'error', 'connection', 'invalid_response', 'not_subscribed', 'sendfox_unsubscribed', 'sendfox_invalid_response', 'site_subscribed', 'site_pending', 'not_configured', 'rate_limited' ), true ) ) {
			$messages = array(
				'error' => __( 'We could not subscribe your email. Please try again later.', 'taxonomy-csv-import-export' ),
				'connection' => __( 'Could not connect to the WP Taxonomy Manager updates server. Please try again later.', 'taxonomy-csv-import-export' ),
				'invalid_response' => __( 'The WP Taxonomy Manager updates server did not confirm the subscription. Please try again later.', 'taxonomy-csv-import-export' ),
				'not_subscribed' => __( 'The updates server could not confirm this SendFox subscription. Please contact WP Taxonomy Manager support.', 'taxonomy-csv-import-export' ),
				'sendfox_unsubscribed' => __( 'This email is unsubscribed from updates in SendFox. Please contact WP Taxonomy Manager support to resubscribe.', 'taxonomy-csv-import-export' ),
				'sendfox_invalid_response' => __( 'SendFox did not confirm the subscription. Please try again later.', 'taxonomy-csv-import-export' ),
				'site_subscribed' => __( 'This site has already subscribed with a different email.', 'taxonomy-csv-import-export' ),
				'site_pending' => __( 'A subscription request for this site is already in progress.', 'taxonomy-csv-import-export' ),
				'not_configured' => __( 'Email updates are temporarily unavailable on the WP Taxonomy Manager server.', 'taxonomy-csv-import-export' ),
				'rate_limited' => __( 'Too many subscription attempts. Please try again later.', 'taxonomy-csv-import-export' ),
			);
			echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $status ] ) . '</p></div>';
		} elseif ( preg_match( '/^http_([3-5][0-9]{2})(?:_([a-z0-9_]{1,64}))?$/', $status, $matches ) ) {
			if ( isset( $matches[2] ) ) {
				/* translators: 1: HTTP status code, 2: updates server error code. */
				$message = sprintf( __( 'The WP Taxonomy Manager updates server returned HTTP %1$d (error: %2$s).', 'taxonomy-csv-import-export' ), (int) $matches[1], $matches[2] );
			} else {
				/* translators: %d: HTTP status code. */
				$message = sprintf( __( 'The WP Taxonomy Manager updates server returned HTTP %d. Please try again later.', 'taxonomy-csv-import-export' ), (int) $matches[1] );
			}
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}

		$user = wp_get_current_user();
		$completed = get_user_meta( $user->ID, self::META_COMPLETE, true );
		$already_subscribed = is_array( $completed ) && isset( $completed['email'] ) && is_string( $completed['email'] ) && 0 === strcasecmp( $completed['email'], $user->user_email );
		if ( ! is_email( sanitize_email( $user->user_email ) ) || get_option( self::OPTION_SITE_COMPLETE ) || get_user_meta( $user->ID, self::META_DISMISSED, true ) || $already_subscribed ) {
			return;
		}

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=taxocsie_updates_optin_dismiss' ), 'taxocsie_updates_optin_dismiss' );
		?>
		<div class="notice notice-info taxocsie-updates-optin">
			<p><strong><?php esc_html_e( 'Get WP Taxonomy Manager updates', 'taxonomy-csv-import-export' ); ?></strong></p>
			<p><?php esc_html_e( 'Clicking Yes sends your email to WP Taxonomy Manager for updates through SendFox. You can unsubscribe from any update email.', 'taxonomy-csv-import-export' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="taxocsie_updates_optin">
				<?php wp_nonce_field( 'taxocsie_updates_optin' ); ?>
				<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Yes, subscribe me', 'taxonomy-csv-import-export' ); ?></button> <a class="button" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'No thanks', 'taxonomy-csv-import-export' ); ?></a></p>
			</form>
		</div>
		<?php
	}

	public static function submit(): void {
		self::guard( 'taxocsie_updates_optin' );
		if ( get_option( self::OPTION_SITE_COMPLETE ) ) {
			self::redirect( 'success' );
		}
		$user = wp_get_current_user();
		$email = sanitize_email( $user->user_email );
		if ( ! is_email( $email ) ) {
			self::redirect( 'error' );
		}
		$site_url = home_url( '/' );

		$response = wp_safe_remote_post( self::ENDPOINT, array(
			'timeout' => 15,
			'redirection' => 0,
			'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body' => wp_json_encode( array( 'email' => $email, 'site_url' => $site_url ) ),
		) );
		if ( is_wp_error( $response ) ) {
			self::redirect( 'connection' );
		}
		$response_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			$error = json_decode( wp_remote_retrieve_body( $response ) );
			$code = is_object( $error ) && isset( $error->code ) && is_string( $error->code ) ? $error->code : '';
			if ( in_array( $code, array( 'not_subscribed', 'sendfox_unsubscribed', 'sendfox_invalid_response', 'site_subscribed', 'site_pending', 'not_configured', 'rate_limited' ), true ) ) {
				self::redirect( $code );
			}
			$status = $response_code >= 300 && $response_code < 600 ? 'http_' . $response_code : 'error';
			$error_code = substr( sanitize_key( $code ), 0, 64 );
			if ( 'error' !== $status && '' !== $error_code ) {
				$status .= '_' . $error_code;
			}
			self::redirect( $status );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $data ) || true !== ( $data->subscribed ?? null ) ) {
			self::redirect( 'invalid_response' );
		}

		update_option( self::OPTION_SITE_COMPLETE, array( 'site_url' => $site_url, 'time' => time() ), false );
		update_user_meta( $user->ID, self::META_COMPLETE, array( 'email' => $email, 'time' => time() ) );
		self::redirect( 'success' );
	}

	public static function dismiss(): void {
		self::guard( 'taxocsie_updates_optin_dismiss' );
		update_user_meta( get_current_user_id(), self::META_DISMISSED, 1 );
		self::redirect( '' );
	}

	private static function guard( string $action ): void {
		if ( ! self::is_site_administrator() ) {
			wp_die( esc_html__( 'Access denied.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private static function is_site_administrator(): bool {
		$user = wp_get_current_user();
		return current_user_can( 'manage_options' )
			&& isset( $user->roles )
			&& in_array( 'administrator', (array) $user->roles, true );
	}

	private static function redirect( string $status ): void {
		$url = admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG );
		if ( '' !== $status ) {
			$url = add_query_arg( 'taxocsie_updates_optin', $status, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
