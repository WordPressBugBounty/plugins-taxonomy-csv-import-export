<?php
/**
 * Shared taxonomy export-filter parsing and application.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps CSV, preview, JSON, and XLSX exports on identical filter rules. */
final class TermQueryFilters {

	/** Sanitize filter values from a request or prepared download URL. */
	public static function sanitize( array $source ): array {
		$match  = isset( $source['taxocsie_filter_name_match'] ) ? sanitize_key( wp_unslash( $source['taxocsie_filter_name_match'] ) ) : 'contains';
		$parent = isset( $source['taxocsie_filter_parent'] ) ? sanitize_text_field( wp_unslash( $source['taxocsie_filter_parent'] ) ) : 'any';
		if ( ! in_array( $match, array( 'contains', 'prefix' ), true ) ) {
			$match = 'contains';
		}
		if ( ! in_array( $parent, array( 'any', 'root' ), true ) && ! ctype_digit( $parent ) ) {
			$parent = 'any';
		}

		$minimum = isset( $source['taxocsie_filter_count_min'] ) && '' !== (string) $source['taxocsie_filter_count_min']
			? max( 0, absint( $source['taxocsie_filter_count_min'] ) )
			: null;
		$maximum = isset( $source['taxocsie_filter_count_max'] ) && '' !== (string) $source['taxocsie_filter_count_max']
			? max( 0, absint( $source['taxocsie_filter_count_max'] ) )
			: null;
		if ( null !== $minimum && null !== $maximum && $minimum > $maximum ) {
			$swap    = $minimum;
			$minimum = $maximum;
			$maximum = $swap;
		}

		return array(
			'hide_empty' => isset( $source['taxocsie_filter_hide_empty'] ) && '1' === (string) $source['taxocsie_filter_hide_empty'],
			'name'       => isset( $source['taxocsie_filter_name'] ) ? sanitize_text_field( wp_unslash( $source['taxocsie_filter_name'] ) ) : '',
			'name_match' => $match,
			'parent'     => $parent,
			'count_min'  => $minimum,
			'count_max'  => $maximum,
		);
	}

	/** Return sanitized request values suitable for carrying into a download URL. */
	public static function to_request_args( array $filters ): array {
		$args = array(
			'taxocsie_filter_name_match' => $filters['name_match'],
			'taxocsie_filter_parent'     => $filters['parent'],
		);
		if ( $filters['hide_empty'] ) {
			$args['taxocsie_filter_hide_empty'] = '1';
		}
		if ( '' !== $filters['name'] ) {
			$args['taxocsie_filter_name'] = $filters['name'];
		}
		if ( null !== $filters['count_min'] ) {
			$args['taxocsie_filter_count_min'] = (string) $filters['count_min'];
		}
		if ( null !== $filters['count_max'] ) {
			$args['taxocsie_filter_count_max'] = (string) $filters['count_max'];
		}
		return $args;
	}

	/** Load terms and apply every supported export filter consistently. */
	public static function get_terms( string $taxonomy, array $source ) {
		$filters = self::sanitize( $source );
		$args    = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => $filters['hide_empty'],
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( 'root' === $filters['parent'] ) {
			$args['parent'] = 0;
		} elseif ( ctype_digit( (string) $filters['parent'] ) ) {
			$args['parent'] = absint( $filters['parent'] );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$name       = self::lower( $filters['name'] );
		$name_match = $filters['name_match'];
		return array_values( array_filter( $terms, static function ( $term ) use ( $filters, $name, $name_match ) {
			if ( '' !== $name ) {
				$term_name = self::lower( (string) $term->name );
				$position  = false !== strpos( $term_name, $name ) ? strpos( $term_name, $name ) : false;
				if ( false === $position || ( 'prefix' === $name_match && 0 !== $position ) ) {
					return false;
				}
			}
			$count = (int) $term->count;
			if ( null !== $filters['count_min'] && $count < $filters['count_min'] ) {
				return false;
			}
			if ( null !== $filters['count_max'] && $count > $filters['count_max'] ) {
				return false;
			}
			return true;
		} ) );
	}

	private static function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}
}
