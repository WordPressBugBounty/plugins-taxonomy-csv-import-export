<?php
/**
 * Preview Table Renderer for Dry Run
 */

namespace TaxoCSIE\Admin;

if (!defined('ABSPATH')) {
	exit;
}

class PreviewTable {
	
	/**
	 * Render preview table
	 *
	 * @param array $preview_data Preview data
	 * @return string HTML
	 */
	public function render($preview_data) {
		if (empty($preview_data['rows'])) {
			return '<p>' . esc_html__('No data to preview.', 'taxonomy-csv-import-export') . '</p>';
		}
		
		ob_start();
		?>
		<div class="tcie-preview-container">
			<?php $this->render_summary($preview_data['summary']); ?>
			
			<div class="tcie-table-wrapper">
				<table class="wp-list-table widefat fixed striped taxocsie-enhanced-table">
					<thead>
						<tr>
							<th class="column-row"><?php esc_html_e('Row', 'taxonomy-csv-import-export'); ?></th>
							<th class="column-name"><?php esc_html_e('Name', 'taxonomy-csv-import-export'); ?></th>
							<th class="column-slug"><?php esc_html_e('Slug', 'taxonomy-csv-import-export'); ?></th>
							<th class="column-parent"><?php esc_html_e('Parent', 'taxonomy-csv-import-export'); ?></th>
							<th class="column-status"><?php esc_html_e('Status', 'taxonomy-csv-import-export'); ?></th>
							<th class="column-message"><?php esc_html_e('Message', 'taxonomy-csv-import-export'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($preview_data['rows'] as $row) : ?>
							<tr class="status-<?php echo esc_attr($row['status']); ?>">
								<td><?php echo esc_html($row['row_number']); ?></td>
								<td><?php echo esc_html($row['name']); ?></td>
								<td><?php echo esc_html($row['slug']); ?></td>
								<td><?php echo esc_html($row['parent']); ?></td>
								<td>
									<span class="status-badge status-<?php echo esc_attr($row['status']); ?>">
										<?php echo esc_html(ucfirst($row['status'])); ?>
									</span>
								</td>
								<td>
									<?php if (!empty($row['errors'])) : ?>
										<?php foreach ($row['errors'] as $error) : ?>
											<div class="error-message">
												<span class="dashicons dashicons-warning"></span>
												<?php echo esc_html($error['message']); ?>
												<?php if (isset($error['suggestion'])) : ?>
													<br>
													<span class="suggestion">
														<strong><?php esc_html_e('Suggestion:', 'taxonomy-csv-import-export'); ?></strong>
														<?php echo esc_html($error['suggestion']); ?>
													</span>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
									
									<?php if (!empty($row['warnings'])) : ?>
										<?php foreach ($row['warnings'] as $warning) : ?>
											<div class="warning-message">
												<span class="dashicons dashicons-info"></span>
												<?php echo esc_html($warning['message']); ?>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
									
									<?php if (empty($row['errors']) && empty($row['warnings'])) : ?>
										<span class="ready-message">
											<?php esc_html_e('Ready', 'taxonomy-csv-import-export'); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			
			<?php if ($preview_data['summary']['error'] > 0) : ?>
				<div class="tcie-error-report">
					<button type="button" class="button" id="tcie-download-errors">
						<?php esc_html_e('Download Error Report', 'taxonomy-csv-import-export'); ?>
					</button>
				</div>
			<?php endif; ?>
			
			<?php if ($preview_data['summary']['ready'] > 0) : ?>
				<div class="tcie-import-actions">
					<button type="button" class="button button-primary" id="tcie-start-import">
						<?php esc_html_e('Start Import', 'taxonomy-csv-import-export'); ?>
					</button>
					<button type="button" class="button" id="tcie-cancel-preview">
						<?php esc_html_e('Cancel', 'taxonomy-csv-import-export'); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Render summary statistics
	 *
	 * @param array $summary Summary data
	 */
	private function render_summary($summary) {
		?>
		<div class="tcie-summary">
			<h3><?php esc_html_e('Import Summary', 'taxonomy-csv-import-export'); ?></h3>
			<div class="tcie-summary-stats">
				<div class="stat-item">
					<span class="stat-label"><?php esc_html_e('Total Rows:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['total']); ?></span>
				</div>
				<div class="stat-item status-new">
					<span class="stat-label"><?php esc_html_e('New:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['new']); ?></span>
				</div>
				<div class="stat-item status-update">
					<span class="stat-label"><?php esc_html_e('Update:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['update']); ?></span>
				</div>
				<div class="stat-item status-skip">
					<span class="stat-label"><?php esc_html_e('Skip:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['skip']); ?></span>
				</div>
				<div class="stat-item status-warning">
					<span class="stat-label"><?php esc_html_e('Warnings:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['warning']); ?></span>
				</div>
				<div class="stat-item status-error">
					<span class="stat-label"><?php esc_html_e('Errors:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['error']); ?></span>
				</div>
				<div class="stat-item status-ready">
					<span class="stat-label"><?php esc_html_e('Ready to Import:', 'taxonomy-csv-import-export'); ?></span>
					<span class="stat-value"><?php echo esc_html($summary['ready']); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
