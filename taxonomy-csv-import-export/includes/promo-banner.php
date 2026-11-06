<?php
/** Dismissible Pro discount banner shown on WP Taxonomy Manager screens. */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class PromoBanner {
	private const META_DISMISSED = 'taxocsie_promo_dismissed';
	private const ACTION         = 'taxocsie_promo_dismiss';

	/** Prevents a second copy when the notice is re-added after third-party notices are removed. */
	private static bool $rendered = false;

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_dismiss' ) );
	}

	/**
	 * The current offer, or an empty array when no banner should show.
	 *
	 * The filter can change the text or link, set an `ends` timestamp, or return an empty array to turn the banner off.
	 *
	 * @return array{id:string,title:string,text:string,button:string,url:string,ends:int}|array{}
	 */
	private static function offer(): array {
		$offer = array(
			'id'     => '50-off',
			'title'  => __( '50% off WP Taxonomy Manager Pro', 'taxonomy-csv-import-export' ),
			'text'   => __( 'Unlock Advanced Import, term images, drag-and-drop ordering, duplicate merge and more.', 'taxonomy-csv-import-export' ),
			'button' => __( 'Claim 50% off', 'taxonomy-csv-import-export' ),
			'url'    => Freemius::get_upgrade_url(),
			'ends'   => 0,
		);

		/**
		 * Filters the Pro discount banner. Return an empty array to hide it.
		 *
		 * @param array $offer Offer details; `ends` is a Unix timestamp, 0 for no end date.
		 */
		$offer = apply_filters( 'taxocsie_promo_banner', $offer );
		if ( ! is_array( $offer ) || empty( $offer['id'] ) || empty( $offer['url'] ) ) {
			return array();
		}
		if ( ! empty( $offer['ends'] ) && time() > (int) $offer['ends'] ) {
			return array();
		}
		return $offer;
	}

	private static function on_plugin_screen(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Screen routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( isset( $_GET['taxocsie_embed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['taxocsie_embed'] ) ) ) {
			return false;
		}
		// phpcs:enable
		// Only where people decide about Pro, so the offer does not follow them through every tool.
		return in_array( $page, array( TAXOCSIE_PLUGIN_SLUG, TAXOCSIE_PLUGIN_SLUG . '-addons' ), true );
	}

	/**
	 * Print the banner. The plugin navigation calls this with $in_header = true so every plugin
	 * screen shows it in the same place; admin_notices stays as a fallback for other screens.
	 */
	public static function render( bool $in_header = false ): void {
		if ( self::$rendered || ! current_user_can( 'manage_options' ) || ! self::on_plugin_screen() ) {
			return;
		}
		// Pro users do not need an upgrade offer.
		if ( Freemius::can_use_premium_code() || Freemius::has_active_license() ) {
			return;
		}
		$offer = self::offer();
		if ( ! $offer || (string) get_user_meta( get_current_user_id(), self::META_DISMISSED, true ) === (string) $offer['id'] ) {
			return;
		}
		self::$rendered = true;

		$dismiss = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'offer'  => $offer['id'],
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION
		);
		?>
		<style>
			.taxocsie-promo{display:flex;align-items:center;gap:16px;flex-wrap:wrap;position:relative;overflow:hidden;margin:15px 20px 5px 2px;padding:14px 76px 14px 18px !important;border:0;border-radius:12px;color:#fff;background:linear-gradient(120deg,#7c3aed 0%,#db2777 55%,#f97316 100%);box-shadow:0 6px 18px rgba(124,58,237,.35)}
			.taxocsie-promo.taxocsie-promo--header{margin:16px var(--taxocsie-gutter,20px) 0 !important;display:flex}
			.taxocsie-promo::after{content:"";position:absolute;right:-40px;top:-60px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none}
			.taxocsie-promo__badge{display:inline-flex;align-items:center;justify-content:center;min-width:56px;height:56px;border-radius:50%;background:#fff;color:#db2777;font-size:17px;font-weight:800;line-height:1;box-shadow:0 2px 8px rgba(0,0,0,.2);transform:rotate(-8deg)}
			.taxocsie-promo__body{flex:1;min-width:240px;position:relative;z-index:1}
			.taxocsie-promo__title{display:block;font-size:16px;font-weight:700;color:#fff}
			.taxocsie-promo__text{margin-top:2px;color:rgba(255,255,255,.92)}
			.taxocsie-promo .button.taxocsie-promo__cta{position:relative;z-index:1;border:0;border-radius:999px;padding:4px 20px;min-height:36px;line-height:28px;font-weight:700;color:#be185d;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.2)}
			.taxocsie-promo .button.taxocsie-promo__cta:hover,.taxocsie-promo .button.taxocsie-promo__cta:focus{color:#fff;background:#111827}
			.taxocsie-promo .notice-dismiss{z-index:2;text-decoration:none;top:50%;right:10px;left:auto;width:32px;height:32px;margin:0;padding:0;transform:translateY(-50%)}
			.taxocsie-promo .notice-dismiss::before{color:#fff}
			.taxocsie-promo .notice-dismiss:hover::before,.taxocsie-promo .notice-dismiss:focus::before{color:#111827}
		</style>
		<div class="notice taxocsie-promo<?php echo $in_header ? ' taxocsie-promo--header inline' : ''; ?>" id="taxocsie-promo">
			<span class="taxocsie-promo__badge" aria-hidden="true"><?php echo esc_html_x( '50%', 'discount badge', 'taxonomy-csv-import-export' ); ?></span>
			<div class="taxocsie-promo__body">
				<strong class="taxocsie-promo__title"><?php echo esc_html( $offer['title'] ); ?></strong>
				<?php if ( ! empty( $offer['text'] ) ) : ?>
					<div class="taxocsie-promo__text"><?php echo esc_html( $offer['text'] ); ?></div>
				<?php endif; ?>
			</div>
			<a class="button taxocsie-promo__cta" href="<?php echo esc_url( $offer['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $offer['button'] ); ?></a>
			<a class="notice-dismiss taxocsie-promo__close" href="<?php echo esc_url( $dismiss ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this offer', 'taxonomy-csv-import-export' ); ?></span></a>
		</div>
		<script>
		(function () {
			var banner = document.getElementById('taxocsie-promo');
			var close = banner && banner.querySelector('.taxocsie-promo__close');
			if (!close) { return; }
			// Hide right away and save in the background; the link still works without JavaScript.
			close.addEventListener('click', function (event) {
				event.preventDefault();
				banner.style.display = 'none';
				fetch(close.href, { credentials: 'same-origin', redirect: 'manual' });
			});
		}());
		</script>
		<?php
	}

	public static function handle_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		$offer = isset( $_GET['offer'] ) ? sanitize_key( wp_unslash( $_GET['offer'] ) ) : '';
		if ( '' !== $offer ) {
			update_user_meta( get_current_user_id(), self::META_DISMISSED, $offer );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG ) );
		exit;
	}
}
