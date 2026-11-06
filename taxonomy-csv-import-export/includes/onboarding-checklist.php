<?php
/** Getting-started checklist on the Dashboard, plus a sample CSV download. */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class OnboardingChecklist {
	public const META_DISMISSED = 'taxocsie_checklist_dismissed';
	public const META_ADDONS    = 'taxocsie_checklist_addons_seen';
	private const DISMISS       = 'taxocsie_checklist_dismiss';
	private const SAMPLE        = 'taxocsie_sample_csv';

	public static function init(): void {
		add_action( 'admin_post_' . self::DISMISS, array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'admin_post_' . self::SAMPLE, array( __CLASS__, 'download_sample' ) );
	}

	/** Call from the Add-ons page so "look at add-ons" ticks itself. */
	public static function mark_addons_seen(): void {
		if ( get_current_user_id() && ! get_user_meta( get_current_user_id(), self::META_ADDONS, true ) ) {
			update_user_meta( get_current_user_id(), self::META_ADDONS, 1 );
		}
	}

	/** @return array<int,array{done:bool,title:string,hint:string,url:string,button:string}> */
	private static function steps( array $stats, string $import_url, string $export_url ): array {
		return array(
			array(
				'done'   => (int) ( $stats['imported_items'] ?? 0 ) > 0,
				'title'  => __( 'Import your first CSV', 'taxonomy-csv-import-export' ),
				'hint'   => __( 'Upload a file, map its columns and preview before anything is saved.', 'taxonomy-csv-import-export' ),
				'url'    => $import_url,
				'button' => __( 'Import CSV', 'taxonomy-csv-import-export' ),
			),
			array(
				'done'   => (int) ( $stats['exported_items'] ?? 0 ) > 0,
				'title'  => __( 'Export your terms', 'taxonomy-csv-import-export' ),
				'hint'   => __( 'Download existing terms as a backup or to edit them in a spreadsheet.', 'taxonomy-csv-import-export' ),
				'url'    => $export_url,
				'button' => __( 'Export terms', 'taxonomy-csv-import-export' ),
			),
			array(
				'done'   => (int) ( $stats['created_items'] ?? 0 ) > 0,
				'title'  => __( 'Create a term by hand', 'taxonomy-csv-import-export' ),
				'hint'   => __( 'Add a category, tag or custom term without a file.', 'taxonomy-csv-import-export' ),
				'url'    => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-create' ),
				'button' => __( 'Create term', 'taxonomy-csv-import-export' ),
			),
			array(
				'done'   => (bool) get_user_meta( get_current_user_id(), self::META_ADDONS, true ),
				'title'  => __( 'Look through the add-ons', 'taxonomy-csv-import-export' ),
				'hint'   => __( 'Turn on only the tools you need.', 'taxonomy-csv-import-export' ),
				'url'    => admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG . '-addons' ),
				'button' => __( 'Open add-ons', 'taxonomy-csv-import-export' ),
			),
		);
	}

	public static function render( array $stats, string $import_url, string $export_url ): void {
		if ( ! current_user_can( 'manage_options' ) || get_user_meta( get_current_user_id(), self::META_DISMISSED, true ) ) {
			return;
		}
		$steps = self::steps( $stats, $import_url, $export_url );
		$done  = count( array_filter( array_column( $steps, 'done' ) ) );
		if ( count( $steps ) === $done ) {
			return; // Finished: it goes away by itself.
		}
		$percent = (int) round( $done / count( $steps ) * 100 );
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS );
		$sample  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::SAMPLE ), self::SAMPLE );
		?>
		<style>
			.taxocsie-start{margin:0 0 20px;padding:20px 24px;border:1px solid #dbe3ec;border-radius:12px;background:#fff}
			.taxocsie-start__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
			.taxocsie-start__title{margin:0;font-size:16px;font-weight:700;color:#1e293b}
			.taxocsie-start__sub{margin:4px 0 0;color:#64748b}
			.taxocsie-start__bar{height:8px;margin:14px 0 4px;border-radius:99px;background:#eef2f7;overflow:hidden}
			.taxocsie-start__bar span{display:block;height:100%;background:#4b53bc;border-radius:99px}
			.taxocsie-start__list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0 0;padding:0;list-style:none}
			.taxocsie-start__item{display:flex;flex-direction:column;gap:6px;padding:14px;border:1px solid #e5eaf1;border-radius:10px;background:#f8fafc}
			.taxocsie-start__item.is-done{background:#f0fdf4;border-color:#bbf7d0}
			.taxocsie-start__item strong{display:flex;align-items:center;gap:6px;color:#1e293b}
			.taxocsie-start__item .dashicons{color:#94a3b8}
			.taxocsie-start__item.is-done .dashicons{color:#16a34a}
			.taxocsie-start__item p{margin:0;color:#64748b;font-size:13px}
			.taxocsie-start__item .button{align-self:flex-start;margin-top:auto}
			.taxocsie-start__links{display:flex;gap:14px;align-items:center}
		</style>
		<section class="taxocsie-start" aria-labelledby="taxocsie-start-title">
			<div class="taxocsie-start__head">
				<div>
					<h2 class="taxocsie-start__title" id="taxocsie-start-title"><?php esc_html_e( 'Get started', 'taxonomy-csv-import-export' ); ?></h2>
					<p class="taxocsie-start__sub"><?php echo esc_html( sprintf( /* translators: 1: finished steps, 2: total steps. */ __( '%1$d of %2$d steps done', 'taxonomy-csv-import-export' ), $done, count( $steps ) ) ); ?></p>
				</div>
				<div class="taxocsie-start__links">
					<a href="<?php echo esc_url( $sample ); ?>"><?php esc_html_e( 'Download a sample CSV', 'taxonomy-csv-import-export' ); ?></a>
					<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Hide this', 'taxonomy-csv-import-export' ); ?></a>
				</div>
			</div>
			<div class="taxocsie-start__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>"><span style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span></div>
			<ul class="taxocsie-start__list">
				<?php foreach ( $steps as $step ) : ?>
					<li class="taxocsie-start__item<?php echo $step['done'] ? ' is-done' : ''; ?>">
						<strong><span class="dashicons <?php echo $step['done'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span><?php echo esc_html( $step['title'] ); ?>
						<?php
						if ( $step['done'] ) :
							?>
							<span class="screen-reader-text"> <?php esc_html_e( '(done)', 'taxonomy-csv-import-export' ); ?></span><?php endif; ?></strong>
						<p><?php echo esc_html( $step['hint'] ); ?></p>
						<?php if ( ! $step['done'] ) : ?>
							<a class="button" href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['button'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	public static function handle_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::DISMISS );
		update_user_meta( get_current_user_id(), self::META_DISMISSED, 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . TAXOCSIE_PLUGIN_SLUG ) );
		exit;
	}

	/** A small file the import screen accepts, so first-time users see the expected columns. */
	public static function download_sample(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'taxonomy-csv-import-export' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::SAMPLE );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sample-terms.csv"' );
		echo "name,slug,description,parent\n";
		echo "News,news,Company news and announcements,\n";
		echo "Tutorials,tutorials,How-to guides,\n";
		echo "Beginner guides,beginner-guides,Start here,tutorials\n";
		echo "Advanced guides,advanced-guides,For experienced users,tutorials\n";
		exit;
	}
}
