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
			'duplicate_terms' => 'duplicate_term_merge',
			'remote_sync'     => 'scheduled_remote_csv_sync',
			'woocommerce'     => 'dynamic_term_meta',
		);
	}

	public static function is_supported( string $key ): bool {
		return isset( self::features()[ $key ] );
	}

	/** Existing installations start with their current features on. */
	public static function is_enabled( string $key ): bool {
		if ( ! self::is_supported( $key ) ) {
			return false;
		}
		$settings = get_option( self::OPTION, array() );
		return ! is_array( $settings ) || ! array_key_exists( $key, $settings ) || 1 === (int) $settings[ $key ];
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
