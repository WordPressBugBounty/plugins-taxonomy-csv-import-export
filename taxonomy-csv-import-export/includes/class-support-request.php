<?php
/**
 * Floating support button and question form on plugin screens.
 *
 * Questions are sent only when an administrator submits the form. The
 * request goes to the WP Taxonomy Manager website, which emails the support
 * team with Reply-To set to the sender, so no support address lives here.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SupportRequest {
	private const ENDPOINT     = 'https://wp-taxonomy-manager.com/wp-json/wtmp-support/v1/message';
	private const CONTACT_URL  = 'https://wp-taxonomy-manager.com/contact/';
	private const ACTION       = 'taxocsie_support_request';
	private const MIN_MESSAGE  = 10;
	private const MAX_MESSAGE  = 5000;
	private const HOURLY_LIMIT = 5;

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/** @return array<string, string> Topic keys and labels. */
	public static function topics(): array {
		return array(
			'question' => __( 'A question about using the plugin', 'taxonomy-csv-import-export' ),
			'bug'      => __( 'Something is not working', 'taxonomy-csv-import-export' ),
			'feature'  => __( 'Feature request', 'taxonomy-csv-import-export' ),
			'license'  => __( 'Pro, billing, or license', 'taxonomy-csv-import-export' ),
		);
	}

	/** Plugin screens only; the in-page frame is skipped because its outer page shows the button. */
	private static function is_visible(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$page     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$embedded = isset( $_GET['taxocsie_embed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['taxocsie_embed'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return ! $embedded && isset( Menu::navigation_pages()[ $page ] );
	}

	private static function endpoint(): string {
		/**
		 * Filters the URL that receives support questions.
		 *
		 * @param string $url Support endpoint URL.
		 */
		$url = apply_filters( 'taxocsie_support_endpoint', self::ENDPOINT );
		return is_string( $url ) ? esc_url_raw( $url, array( 'https', 'http' ) ) : '';
	}

	private static function contact_url(): string {
		/**
		 * Filters the contact page offered when a question cannot be sent.
		 *
		 * @param string $url Contact page URL.
		 */
		$url = apply_filters( 'taxocsie_support_contact_url', self::CONTACT_URL );
		return is_string( $url ) ? esc_url_raw( $url, array( 'https', 'http' ) ) : '';
	}

	public static function enqueue(): void {
		if ( ! self::is_visible() ) {
			return;
		}
		$style  = TAXOCSIE_ASSETS_DIR_PATH . '/css/support.css';
		$script = TAXOCSIE_ASSETS_DIR_PATH . '/js/support.js';
		wp_enqueue_style( 'taxocsie-support', TAXOCSIE_ASSETS_URI . '/css/support.css', array(), (string) filemtime( $style ) );
		wp_enqueue_script( 'taxocsie-support', TAXOCSIE_ASSETS_URI . '/js/support.js', array(), (string) filemtime( $script ), true );
		wp_localize_script( 'taxocsie-support', 'taxocsieSupport', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'contactUrl'  => self::contact_url(),
			'send'        => __( 'Send question', 'taxonomy-csv-import-export' ),
			'sending'     => __( 'Sending...', 'taxonomy-csv-import-export' ),
			'error'       => __( 'Your question could not be sent. Please try again later.', 'taxonomy-csv-import-export' ),
			'contactLink' => __( 'Use the contact page instead', 'taxonomy-csv-import-export' ),
			/* translators: 1: characters typed, 2: character limit. */
			'count'       => __( '%1$d / %2$d', 'taxonomy-csv-import-export' ),
		) );
	}

	/** Headset support icon; static markup with no user data. */
	private static function icon(): string {
		return '<svg class="taxocsie-support-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">'
			. '<path d="M5.2 10.4a6.8 6.8 0 0 1 13.6 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>'
			. '<rect x="3.6" y="9.2" width="3.2" height="5.4" rx="1.6" fill="currentColor"/>'
			. '<rect x="17.2" y="9.2" width="3.2" height="5.4" rx="1.6" fill="currentColor"/>'
			. '<circle cx="12" cy="10.6" r="4.1" fill="currentColor"/>'
			. '<path d="M18.8 14c0 1.7-1.4 2.6-3.2 2.6h-1.4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>'
			. '<rect x="11.2" y="15.8" width="3.2" height="1.6" rx=".8" fill="currentColor"/>'
			. '<path d="M4.4 22.4c.7-2.9 3.8-4.4 7.6-4.4s6.9 1.5 7.6 4.4z" fill="currentColor"/>'
			. '</svg>';
	}

	public static function render(): void {
		if ( ! self::is_visible() ) {
			return;
		}
		$user    = wp_get_current_user();
		$contact = self::contact_url();
		?>
		<button type="button" class="taxocsie-support-launcher" aria-haspopup="dialog" aria-controls="taxocsie-support-dialog" data-support-open>
			<?php echo self::icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			<span class="taxocsie-support-launcher__label"><?php esc_html_e( 'Ask a question', 'taxonomy-csv-import-export' ); ?></span>
		</button>

		<dialog id="taxocsie-support-dialog" class="taxocsie-support" aria-labelledby="taxocsie-support-title">
			<div class="taxocsie-support__header">
				<span class="taxocsie-support__icon"><?php echo self::icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?></span>
				<div>
					<h2 id="taxocsie-support-title"><?php esc_html_e( 'Ask a question', 'taxonomy-csv-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Send your question to the WP Taxonomy Manager team. We reply by email.', 'taxonomy-csv-import-export' ); ?></p>
				</div>
				<button type="button" class="taxocsie-support__close" data-support-close aria-label="<?php esc_attr_e( 'Close', 'taxonomy-csv-import-export' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</div>

			<form class="taxocsie-support__form" data-support-form>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, 'nonce', false ); ?>
				<div class="taxocsie-support__row">
					<label class="taxocsie-support__field">
						<span><?php esc_html_e( 'Your name', 'taxonomy-csv-import-export' ); ?></span>
						<input type="text" name="name" maxlength="100" autocomplete="name" value="<?php echo esc_attr( $user->display_name ); ?>">
					</label>
					<label class="taxocsie-support__field">
						<span><?php esc_html_e( 'Reply email', 'taxonomy-csv-import-export' ); ?></span>
						<input type="email" name="email" maxlength="254" autocomplete="email" required aria-describedby="taxocsie-support-email-hint" value="<?php echo esc_attr( $user->user_email ); ?>">
						<small id="taxocsie-support-email-hint" class="taxocsie-support__hint"><?php esc_html_e( 'The reply from support arrives in this inbox.', 'taxonomy-csv-import-export' ); ?></small>
					</label>
				</div>
				<label class="taxocsie-support__field">
					<span><?php esc_html_e( 'Topic', 'taxonomy-csv-import-export' ); ?></span>
					<select name="topic">
						<?php foreach ( self::topics() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="taxocsie-support__field">
					<span><?php esc_html_e( 'Your question', 'taxonomy-csv-import-export' ); ?></span>
					<textarea name="message" rows="6" required minlength="<?php echo esc_attr( (string) self::MIN_MESSAGE ); ?>" maxlength="<?php echo esc_attr( (string) self::MAX_MESSAGE ); ?>" placeholder="<?php esc_attr_e( 'Describe what you want to do, what you tried, and what happened.', 'taxonomy-csv-import-export' ); ?>"></textarea>
					<small class="taxocsie-support__count" data-support-count aria-live="polite"></small>
				</label>
				<label class="taxocsie-support__check">
					<input type="checkbox" name="include_details" value="1" checked>
					<span><?php esc_html_e( 'Include site details: site address, WordPress, PHP, and plugin versions, plan, and language.', 'taxonomy-csv-import-export' ); ?></span>
				</label>
				<p class="taxocsie-support__privacy">
					<?php esc_html_e( 'Your name, email, and question are sent to wp-taxonomy-manager.com so the team can reply.', 'taxonomy-csv-import-export' ); ?>
					<?php if ( '' !== $contact ) : ?>
						<a href="<?php echo esc_url( $contact ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contact page', 'taxonomy-csv-import-export' ); ?></a>
					<?php endif; ?>
				</p>
				<div class="taxocsie-support__error" role="alert" data-support-error hidden></div>
				<div class="taxocsie-support__actions">
					<button type="button" class="taxocsie-support__button" data-support-close><?php esc_html_e( 'Cancel', 'taxonomy-csv-import-export' ); ?></button>
					<button type="submit" class="taxocsie-support__button taxocsie-support__button--primary"><?php esc_html_e( 'Send question', 'taxonomy-csv-import-export' ); ?></button>
				</div>
			</form>

			<div class="taxocsie-support__done" data-support-done tabindex="-1" hidden>
				<span class="taxocsie-support__done-icon"><span class="dashicons dashicons-yes" aria-hidden="true"></span></span>
				<h3><?php esc_html_e( 'Your question was sent', 'taxonomy-csv-import-export' ); ?></h3>
				<p data-support-done-text></p>
				<div class="taxocsie-support__inbox" data-support-inbox>
					<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					<p>
						<strong><?php esc_html_e( 'Check your email inbox for the reply.', 'taxonomy-csv-import-export' ); ?></strong>
						<?php esc_html_e( 'When support replies, the answer is sent to the email above, not shown here. If you do not see it, check your spam or junk folder too.', 'taxonomy-csv-import-export' ); ?>
					</p>
				</div>
				<button type="button" class="taxocsie-support__button taxocsie-support__button--primary" data-support-close><?php esc_html_e( 'Close', 'taxonomy-csv-import-export' ); ?></button>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Sanitize and check a submitted question.
	 *
	 * @param array $raw Unslashed request values.
	 * @return array|\WP_Error Clean values, or the first problem found.
	 */
	public static function validate( array $raw ) {
		$email   = isset( $raw['email'] ) && is_string( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '';
		// Browsers submit textarea line breaks as CRLF; keep plain LF for the email body.
		$message = isset( $raw['message'] ) && is_string( $raw['message'] ) ? trim( str_replace( "\r\n", "\n", sanitize_textarea_field( $raw['message'] ) ) ) : '';
		$topic   = isset( $raw['topic'] ) && is_string( $raw['topic'] ) ? sanitize_key( $raw['topic'] ) : '';
		$name    = isset( $raw['name'] ) && is_string( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '';
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );

		if ( '' === $email || strlen( $email ) > 254 || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Enter a valid email address so the team can reply.', 'taxonomy-csv-import-export' ) );
		}
		if ( $length < self::MIN_MESSAGE ) {
			/* translators: %d: minimum number of characters. */
			return new \WP_Error( 'message_short', sprintf( __( 'Write at least %d characters so the team can help.', 'taxonomy-csv-import-export' ), self::MIN_MESSAGE ) );
		}
		if ( $length > self::MAX_MESSAGE ) {
			/* translators: %d: maximum number of characters. */
			return new \WP_Error( 'message_long', sprintf( __( 'Keep your question under %d characters.', 'taxonomy-csv-import-export' ), self::MAX_MESSAGE ) );
		}

		return array(
			'name'            => function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 100 ) : substr( $name, 0, 100 ),
			'email'           => $email,
			'topic'           => isset( self::topics()[ $topic ] ) ? $topic : 'question',
			'message'         => $message,
			'include_details' => isset( $raw['include_details'] ) && '1' === $raw['include_details'],
		);
	}

	/** Build the JSON body; site details are added only when the sender agreed. */
	public static function payload( array $input ): array {
		$payload = array(
			'name'    => $input['name'],
			'email'   => $input['email'],
			'topic'   => $input['topic'],
			'message' => $input['message'],
		);
		if ( $input['include_details'] ) {
			$payload['site_url']    = home_url( '/' );
			$payload['environment'] = array(
				'plugin_version' => defined( 'TAXOCSIE_VERSION' ) ? TAXOCSIE_VERSION : '',
				'plan'           => Freemius::can_use_premium_code() ? 'pro' : 'free',
				'wordpress'      => get_bloginfo( 'version' ),
				'php'            => PHP_VERSION,
				'locale'         => get_locale(),
			);
		}
		return $payload;
	}

	public static function handle(): void {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to send support questions.', 'taxonomy-csv-import-export' ) ), 403 );
		}

		$input = self::validate( wp_unslash( $_POST ) );
		if ( is_wp_error( $input ) ) {
			wp_send_json_error( array( 'message' => $input->get_error_message() ), 400 );
		}

		// Successful sends per administrator per hour, so the form cannot flood the support inbox.
		$limit_key = 'taxocsie_support_' . get_current_user_id();
		$sent      = (int) get_transient( $limit_key );
		if ( $sent >= self::HOURLY_LIMIT ) {
			wp_send_json_error( array( 'message' => __( 'You have sent several questions in the last hour. Please wait before sending another.', 'taxonomy-csv-import-export' ) ), 429 );
		}

		$endpoint = self::endpoint();
		if ( '' === $endpoint ) {
			wp_send_json_error( array( 'message' => __( 'Support questions are not available on this site.', 'taxonomy-csv-import-export' ) ), 503 );
		}

		$response = wp_safe_remote_post( $endpoint, array(
			'timeout'     => 15,
			'redirection' => 0,
			'headers'     => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'        => wp_json_encode( self::payload( $input ) ),
		) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not connect to the WP Taxonomy Manager support server. Please try again later.', 'taxonomy-csv-import-export' ) ), 502 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 && is_array( $data ) && true === ( $data['sent'] ?? null ) ) {
			set_transient( $limit_key, $sent + 1, HOUR_IN_SECONDS );
			wp_send_json_success( array(
				/* translators: %s: email address that will receive the reply. */
				'message' => sprintf( __( 'Thanks! The team will reply to %s.', 'taxonomy-csv-import-export' ), $input['email'] ),
			) );
		}

		$server_code = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
		$messages    = array(
			'rate_limited'   => __( 'The support server received several questions from this site recently. Please try again later.', 'taxonomy-csv-import-export' ),
			'busy'           => __( 'The support inbox is receiving many questions right now. Please try again later.', 'taxonomy-csv-import-export' ),
			'not_configured' => __( 'Support questions are temporarily unavailable.', 'taxonomy-csv-import-export' ),
			'invalid_email'  => __( 'Enter a valid email address so the team can reply.', 'taxonomy-csv-import-export' ),
			'invalid_message' => __( 'Check your question and try again.', 'taxonomy-csv-import-export' ),
			'mail_failed'    => __( 'The support server could not deliver your question. Please try again later.', 'taxonomy-csv-import-export' ),
		);
		$message = $messages[ $server_code ] ?? sprintf(
			/* translators: %d: HTTP status code. */
			__( 'The support server returned HTTP %d. Please try again later.', 'taxonomy-csv-import-export' ),
			$code
		);
		wp_send_json_error( array( 'message' => $message ), 502 );
	}
}
