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
		);
	}

	public static function is_supported( string $key ): bool {
		return isset( self::features()[ $key ] );
	}

	/** Existing add-ons keep their current state; the new ACF add-on starts off. */
	public static function is_enabled( string $key ): bool {
		if ( ! self::is_supported( $key ) ) {
			return false;
		}
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return 'acf' !== $key;
		}
		return 1 === (int) $settings[ $key ];
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
		$settings = get_option( self::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings[ $key ] = $enabled ? 1 : 0;
		update_option( self::OPTION, $settings, false );
		return true;
	}
}
