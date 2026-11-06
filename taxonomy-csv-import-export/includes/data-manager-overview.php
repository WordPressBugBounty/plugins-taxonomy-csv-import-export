<?php
/** Visual overview of what the Data Manager can do, shown before a task is chosen. */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class DataManagerOverview {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Sample labels used in the illustrations.
		$label_news   = __( 'News', 'taxonomy-csv-import-export' );
		$label_guides = __( 'Guides', 'taxonomy-csv-import-export' );
		$label_sale   = __( 'Sale', 'taxonomy-csv-import-export' );
		$users_link   = add_query_arg(
			array(
				'manager_view'   => 'users',
				'manager_action' => 'export',
			)
		);
		$link         = static function ( array $args ): string {
			return add_query_arg( $args );
		};
		?>
		<style>
			.taxocsie-dm{margin:0 0 22px}
			.taxocsie-dm__head h2{margin:0 0 4px;font-size:20px;color:#0f172a}
			.taxocsie-dm__head p{margin:0 0 16px;color:#64748b;font-size:14px}
			.taxocsie-dm__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
			.taxocsie-dm__card{display:flex;flex-direction:column;border:1px solid #dbe3ec;border-radius:14px;background:#fff;overflow:hidden;text-decoration:none;color:inherit;transition:box-shadow .15s,border-color .15s}
			.taxocsie-dm__card:hover,.taxocsie-dm__card:focus{border-color:#4b53bc;box-shadow:0 6px 18px rgba(75,83,188,.15);color:inherit}
			.taxocsie-dm__art{display:flex;align-items:center;justify-content:center;gap:8px;height:104px;padding:10px;background:linear-gradient(135deg,#f5f6ff,#fdf4ff)}
			.taxocsie-dm__body{display:flex;flex-direction:column;gap:4px;padding:14px 16px 16px;flex:1}
			.taxocsie-dm__title{display:flex;align-items:center;gap:8px;margin:0;font-size:15px;font-weight:700;color:#1e293b}
			.taxocsie-dm__pro{padding:1px 7px;border-radius:99px;background:#eef0ff;color:#4b53bc;font-size:10px;font-weight:700}
			.taxocsie-dm__text{margin:0;color:#64748b;font-size:13px}
			.taxocsie-dm__use{margin:4px 0 0;color:#334155;font-size:12px}
			.taxocsie-dm__use strong{color:#4b53bc}
			.taxocsie-dm__go{margin-top:auto;padding-top:10px;color:#4b53bc;font-weight:600;font-size:13px}
			.taxocsie-dm__chip{padding:3px 9px;border-radius:99px;background:#fff;border:1px solid #c7cbf5;color:#4b53bc;font-size:11px;font-weight:600}
			.taxocsie-dm__file{display:inline-flex;align-items:center;justify-content:center;width:44px;height:56px;border-radius:6px;background:#fff;border:1px solid #cbd5e1;box-shadow:0 2px 6px rgba(15,23,42,.08);color:#334155;font-size:11px;font-weight:700}
			.taxocsie-dm__file--hot{border-color:#4b53bc;color:#4b53bc}
			.taxocsie-dm__arrow{color:#4b53bc;font-size:20px;width:20px;height:20px}
			.taxocsie-dm__stack{display:flex;flex-direction:column;gap:4px}
			.taxocsie-dm__bar{display:block;height:9px;border-radius:5px;background:#c7cbf5}
			.taxocsie-dm__mini{display:grid;grid-template-columns:1.2fr 1fr;gap:4px;width:150px}
			.taxocsie-dm__cell{height:16px;border-radius:4px;background:#fff;border:1px solid #dbe3ec}
			.taxocsie-dm__cell--edit{border-color:#4b53bc;background:#eef0ff}
			.taxocsie-dm__row{display:flex;align-items:center;gap:6px;width:150px;padding:4px 8px;border-radius:6px;background:#fff;border:1px solid #dbe3ec;font-size:11px;color:#334155}
			.taxocsie-dm__row .dashicons{width:14px;height:14px;font-size:14px;color:#94a3b8}
			.taxocsie-dm__row--lift{border-color:#4b53bc;box-shadow:0 4px 10px rgba(75,83,188,.25);transform:translateX(6px)}
			.taxocsie-dm__avatar{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#4b53bc;color:#fff}
			.taxocsie-dm__avatar:nth-child(2){background:#db2777}
			.taxocsie-dm__avatar:nth-child(3){background:#f97316}
		</style>
		<section class="taxocsie-dm" aria-labelledby="taxocsie-dm-title">
			<div class="taxocsie-dm__head">
				<h2 id="taxocsie-dm-title"><?php esc_html_e( 'What you can do in Data Manager', 'taxonomy-csv-import-export' ); ?></h2>
				<p><?php esc_html_e( 'Move terms and users in and out of WordPress, fix them in bulk, and set their order. Pick one to start.', 'taxonomy-csv-import-export' ); ?></p>
			</div>
			<div class="taxocsie-dm__grid">

				<a class="taxocsie-dm__card" href="<?php echo esc_url( $link( array( 'manager_view' => 'export' ) ) ); ?>">
					<span class="taxocsie-dm__art" aria-hidden="true">
						<span class="taxocsie-dm__stack"><span class="taxocsie-dm__chip"><?php echo esc_html( $label_news ); ?></span><span class="taxocsie-dm__chip"><?php echo esc_html( $label_guides ); ?></span><span class="taxocsie-dm__chip"><?php echo esc_html( $label_sale ); ?></span></span>
						<span class="dashicons dashicons-arrow-right-alt taxocsie-dm__arrow"></span>
						<span class="taxocsie-dm__file taxocsie-dm__file--hot">CSV</span>
					</span>
					<span class="taxocsie-dm__body">
						<h3 class="taxocsie-dm__title"><?php esc_html_e( 'Export terms', 'taxonomy-csv-import-export' ); ?></h3>
						<p class="taxocsie-dm__text"><?php esc_html_e( 'Download your categories, tags or custom terms as a file.', 'taxonomy-csv-import-export' ); ?></p>
						<p class="taxocsie-dm__use"><strong><?php esc_html_e( 'Use it to:', 'taxonomy-csv-import-export' ); ?></strong> <?php esc_html_e( 'back up before a big change, or edit in Excel. Excel and JSON files are Pro.', 'taxonomy-csv-import-export' ); ?></p>
						<span class="taxocsie-dm__go"><?php esc_html_e( 'Export →', 'taxonomy-csv-import-export' ); ?></span>
					</span>
				</a>

				<a class="taxocsie-dm__card" href="<?php echo esc_url( $link( array( 'manager_view' => 'import' ) ) ); ?>">
					<span class="taxocsie-dm__art" aria-hidden="true">
						<span class="taxocsie-dm__file">CSV</span>
						<span class="dashicons dashicons-arrow-right-alt taxocsie-dm__arrow"></span>
						<span class="taxocsie-dm__stack"><span class="taxocsie-dm__chip"><?php echo esc_html( $label_news ); ?></span><span class="taxocsie-dm__chip">└ <?php echo esc_html( $label_guides ); ?></span><span class="taxocsie-dm__chip">└ <?php echo esc_html( $label_sale ); ?></span></span>
					</span>
					<span class="taxocsie-dm__body">
						<h3 class="taxocsie-dm__title"><?php esc_html_e( 'Import terms', 'taxonomy-csv-import-export' ); ?></h3>
						<p class="taxocsie-dm__text"><?php esc_html_e( 'Create or update many terms from a file, with parents and descriptions.', 'taxonomy-csv-import-export' ); ?></p>
						<p class="taxocsie-dm__use"><strong><?php esc_html_e( 'Use it to:', 'taxonomy-csv-import-export' ); ?></strong> <?php esc_html_e( 'load a whole category tree at once. You map the columns and preview first, and can undo an import.', 'taxonomy-csv-import-export' ); ?></p>
						<span class="taxocsie-dm__go"><?php esc_html_e( 'Import →', 'taxonomy-csv-import-export' ); ?></span>
					</span>
				</a>

				<a class="taxocsie-dm__card" href="<?php echo esc_url( $link( array( 'manager_view' => 'edit' ) ) ); ?>">
					<span class="taxocsie-dm__art" aria-hidden="true">
						<span class="taxocsie-dm__mini">
							<span class="taxocsie-dm__cell"></span><span class="taxocsie-dm__cell"></span>
							<span class="taxocsie-dm__cell taxocsie-dm__cell--edit"></span><span class="taxocsie-dm__cell"></span>
							<span class="taxocsie-dm__cell"></span><span class="taxocsie-dm__cell taxocsie-dm__cell--edit"></span>
						</span>
					</span>
					<span class="taxocsie-dm__body">
						<h3 class="taxocsie-dm__title"><?php esc_html_e( 'Edit terms in bulk', 'taxonomy-csv-import-export' ); ?></h3>
						<p class="taxocsie-dm__text"><?php esc_html_e( 'Change many term names and slugs in one table, then save once.', 'taxonomy-csv-import-export' ); ?></p>
						<p class="taxocsie-dm__use"><strong><?php esc_html_e( 'Use it to:', 'taxonomy-csv-import-export' ); ?></strong> <?php esc_html_e( 'fix typos or rename a batch without opening each term.', 'taxonomy-csv-import-export' ); ?></p>
						<span class="taxocsie-dm__go"><?php esc_html_e( 'Edit →', 'taxonomy-csv-import-export' ); ?></span>
					</span>
				</a>

				<a class="taxocsie-dm__card" href="<?php echo esc_url( $link( array( 'manager_view' => 'order' ) ) ); ?>">
					<span class="taxocsie-dm__art" aria-hidden="true">
						<span class="taxocsie-dm__stack">
							<span class="taxocsie-dm__row"><span class="dashicons dashicons-menu"></span><?php echo esc_html( $label_guides ); ?></span>
							<span class="taxocsie-dm__row taxocsie-dm__row--lift"><span class="dashicons dashicons-menu"></span><?php echo esc_html( $label_news ); ?></span>
							<span class="taxocsie-dm__row"><span class="dashicons dashicons-menu"></span><?php echo esc_html( $label_sale ); ?></span>
						</span>
					</span>
					<span class="taxocsie-dm__body">
						<h3 class="taxocsie-dm__title"><?php esc_html_e( 'Order terms', 'taxonomy-csv-import-export' ); ?> <span class="taxocsie-dm__pro"><?php esc_html_e( 'PRO', 'taxonomy-csv-import-export' ); ?></span></h3>
						<p class="taxocsie-dm__text"><?php esc_html_e( 'Drag terms into the order you want, and move one under another parent.', 'taxonomy-csv-import-export' ); ?></p>
						<p class="taxocsie-dm__use"><strong><?php esc_html_e( 'Use it to:', 'taxonomy-csv-import-export' ); ?></strong> <?php esc_html_e( 'put your main categories first in a menu or shop filter.', 'taxonomy-csv-import-export' ); ?></p>
						<span class="taxocsie-dm__go"><?php esc_html_e( 'Order →', 'taxonomy-csv-import-export' ); ?></span>
					</span>
				</a>

				<a class="taxocsie-dm__card" href="<?php echo esc_url( $users_link ); ?>">
					<span class="taxocsie-dm__art" aria-hidden="true">
						<span class="taxocsie-dm__stack" style="flex-direction:row"><span class="taxocsie-dm__avatar"><span class="dashicons dashicons-admin-users"></span></span><span class="taxocsie-dm__avatar"><span class="dashicons dashicons-admin-users"></span></span><span class="taxocsie-dm__avatar"><span class="dashicons dashicons-admin-users"></span></span></span>
						<span class="dashicons dashicons-leftright taxocsie-dm__arrow"></span>
						<span class="taxocsie-dm__file taxocsie-dm__file--hot">CSV</span>
					</span>
					<span class="taxocsie-dm__body">
						<h3 class="taxocsie-dm__title"><?php esc_html_e( 'Users', 'taxonomy-csv-import-export' ); ?></h3>
						<p class="taxocsie-dm__text"><?php esc_html_e( 'Export WordPress user records to a file, or import them from one.', 'taxonomy-csv-import-export' ); ?></p>
						<p class="taxocsie-dm__use"><strong><?php esc_html_e( 'Use it to:', 'taxonomy-csv-import-export' ); ?></strong> <?php esc_html_e( 'move members to another site or keep a copy.', 'taxonomy-csv-import-export' ); ?></p>
						<span class="taxocsie-dm__go"><?php esc_html_e( 'Open users →', 'taxonomy-csv-import-export' ); ?></span>
					</span>
				</a>

			</div>
		</section>
		<?php
	}
}
