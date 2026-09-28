<?php

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores lightweight activity totals and prepares Dashboard statistics.
 */
final class Dashboard {

	private const OPTION_KEY = 'taxocsie_dashboard_stats';

	public const EXPORTED_ITEMS = 'exported_items';
	public const IMPORTED_ITEMS = 'imported_items';
	public const CREATED_ITEMS  = 'created_items';

	/**
	 * Increase a tracked activity total.
	 *
	 * @param string $metric Metric key.
	 * @param int    $amount Amount to add.
	 */
	public static function increment( string $metric, int $amount = 1 ): void {
		$allowed = array( self::EXPORTED_ITEMS, self::IMPORTED_ITEMS, self::CREATED_ITEMS );
		if ( $amount < 1 || ! in_array( $metric, $allowed, true ) ) {
			return;
		}

		$stats            = self::get_activity_totals();
		$stats[ $metric ] = absint( $stats[ $metric ] ) + $amount;

		update_option( self::OPTION_KEY, $stats, false );
		if ( self::IMPORTED_ITEMS === $metric ) {
			update_option( 'taxocsie_successful_imports', absint( get_option( 'taxocsie_successful_imports', 0 ) ) + 1, false );
		}
	}

	/**
	 * Get all statistics displayed on the Dashboard.
	 *
	 * @return array<string,int>
	 */
	public static function get_stats(): array {
		$stats                            = self::get_activity_totals();
		$stats['total_terms']             = self::get_total_terms();
		$stats['registered_taxonomies']   = count( TaxonomyCreator::get_saved() );
		$stats['available_taxonomies']    = count( get_taxonomies( array( 'public' => true ) ) );

		return $stats;
	}

	/**
	 * Get persisted activity totals with defaults.
	 *
	 * @return array<string,int>
	 */
	private static function get_activity_totals(): array {
		$saved = get_option( self::OPTION_KEY, array() );

		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				self::EXPORTED_ITEMS => 0,
				self::IMPORTED_ITEMS => 0,
				self::CREATED_ITEMS  => 0,
			)
		);
	}

	/**
	 * Count terms across public taxonomies.
	 */
	private static function get_total_terms(): int {
		$total      = 0;
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$count = wp_count_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( ! is_wp_error( $count ) ) {
				$total += absint( $count );
			}
		}

		return $total;
	}
}
