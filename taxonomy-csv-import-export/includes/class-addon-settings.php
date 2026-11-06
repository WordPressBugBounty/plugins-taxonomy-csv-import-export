<?php
/** Settings for the Pro tools bundled with WP Taxonomy Manager. */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AddonSettings {
	private const OPTION = 'taxocsie_addons_enabled';

	public static function features(): array {
		return array(
			'acf'             => 'advanced_csv_import',
			'duplicate_terms' => 'duplicate_term_merge',
			'remote_sync'     => 'scheduled_remote_csv_sync',
			'woocommerce'     => 'dynamic_term_meta',
			'post_order'      => 'post_ordering',
			'bulk_assign'     => 'bulk_term_assign',
		);
	}

	/** Add-ons anyone can switch on; only some of their parts (like drag and drop) need Pro. */
	public static function is_free( string $key ): bool {
		return 'post_order' === $key;
	}

	public static function is_supported( string $key ): bool {
		return isset( self::features()[ $key ] );
	}

	/** Add-ons that stay off, with no menu or settings, until someone turns them on. */
	private const OFF_BY_DEFAULT = array( 'acf', 'post_order', 'bulk_assign' );

	/** Older add-ons keep their current state; the newer ones start off. */
	public static function is_enabled( string $key ): bool {
		if ( ! self::is_supported( $key ) ) {
			return false;
		}
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return ! in_array( $key, self::OFF_BY_DEFAULT, true );
		}
		return 1 === (int) $settings[ $key ];
	}

	/**
	 * Keep Post Order on for sites that already used it before it became an add-on (2.3.0).
	 * Fresh installs set the version on activation, so they skip this and start Off.
	 */
	public static function migrate(): void {
		$stored = get_option( 'taxocsie_version', '' );
		if ( is_string( $stored ) && '' !== $stored && version_compare( $stored, '2.3.0', '>=' ) ) {
			return;
		}
		if ( get_option( 'taxocsie_installed' ) ) {
			$settings = get_option( self::OPTION, array() );
			if ( ! is_array( $settings ) || ! array_key_exists( 'post_order', $settings ) ) {
				self::set_enabled( 'post_order', true );
			}
		}
		update_option( 'taxocsie_version', TAXOCSIE_VERSION );
	}

	/** Whether the ACF functions used by this add-on are available. */
	public static function is_acf_available(): bool {
		return function_exists( 'acf_get_field_groups' )
			&& function_exists( 'acf_get_fields' )
			&& function_exists( 'get_field' )
			&& function_exists( 'update_field' );
	}

	/** ACF-backed controls need both the add-on and ACF's field APIs. */
	public static function is_acf_ready(): bool {
		return self::is_enabled( 'acf' ) && self::is_acf_available();
	}

	public static function set_enabled( string $key, bool $enabled ): bool {
		if ( ! self::is_supported( $key ) ) {
			return false;
		}
		$settings         = get_option( self::OPTION, array() );
		$settings         = is_array( $settings ) ? $settings : array();
		$settings[ $key ] = $enabled ? 1 : 0;
		update_option( self::OPTION, $settings, false );
		return true;
	}
}
