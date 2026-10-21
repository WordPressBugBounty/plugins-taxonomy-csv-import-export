<?php
/**
 * Open uploaded CSV files saved by Excel and other spreadsheet tools.
 *
 * Detects comma, semicolon, or tab delimiters from the header line and
 * converts UTF-16 and Windows-1252 files to UTF-8. The column mapper in
 * assets/dev-js/ajax.js uses the same detection rules so its column
 * indexes match the server.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvReader {

	/** Checked in this order; a later delimiter wins only with a higher count. */
	public const DELIMITERS = array( ',', ';', "\t" );

	/**
	 * Open a CSV file as a UTF-8 stream.
	 *
	 * @param string $path Readable file path.
	 * @return array{handle: resource, delimiter: string}|false
	 */
	public static function open( string $path ) {
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $bytes ) {
			return false;
		}

		$text   = self::to_utf8( $bytes );
		$handle = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}
		fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $handle );

		return array(
			'handle'    => $handle,
			'delimiter' => self::detect_delimiter( $text ),
		);
	}

	/** Convert file bytes to UTF-8 without a byte-order mark. */
	public static function to_utf8( string $bytes ): string {
		if ( 0 === strncmp( $bytes, "\xEF\xBB\xBF", 3 ) ) {
			return substr( $bytes, 3 );
		}
		if ( 0 === strncmp( $bytes, "\xFF\xFE", 2 ) ) {
			return self::convert( substr( $bytes, 2 ), 'UTF-16LE' );
		}
		if ( 0 === strncmp( $bytes, "\xFE\xFF", 2 ) ) {
			return self::convert( substr( $bytes, 2 ), 'UTF-16BE' );
		}
		if ( '' === $bytes || 1 === preg_match( '//u', $bytes ) ) {
			return $bytes;
		}

		// Excel's default "CSV" on Western-language Windows.
		return self::convert( $bytes, 'Windows-1252' );
	}

	/** Pick the delimiter used most often outside quotes in the first line. */
	public static function detect_delimiter( string $text ): string {
		$counts = array_fill_keys( self::DELIMITERS, 0 );
		$quoted = false;
		$length = strlen( $text );

		for ( $index = 0; $index < $length; $index++ ) {
			$character = $text[ $index ];
			if ( '"' === $character ) {
				$quoted = ! $quoted;
				continue;
			}
			if ( $quoted ) {
				continue;
			}
			if ( "\n" === $character || "\r" === $character ) {
				break;
			}
			if ( isset( $counts[ $character ] ) ) {
				++$counts[ $character ];
			}
		}

		$best = self::DELIMITERS[0];
		foreach ( $counts as $delimiter => $count ) {
			if ( $count > $counts[ $best ] ) {
				$best = (string) $delimiter;
			}
		}

		return $best;
	}

	private static function convert( string $bytes, string $from ): string {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $bytes, 'UTF-8', $from ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Unsupported encodings fall through.
			if ( is_string( $converted ) ) {
				return $converted;
			}
		}
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( $from, 'UTF-8//IGNORE', $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid bytes are dropped.
			if ( is_string( $converted ) ) {
				return $converted;
			}
		}

		return $bytes;
	}
}
