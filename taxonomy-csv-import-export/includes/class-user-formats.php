<?php
/** CSV, JSON, and Excel transfers for Data Manager user records. */
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UserFormats {
	public const COLUMNS = array( 'username', 'email', 'password_hash', 'roles', 'first_name', 'last_name', 'meta' );
	private const MAX_UPLOAD_SIZE = 10485760;
	private const MAX_UNCOMPRESSED_SIZE = 33554432;
	private const MAX_ROWS = 20000;

	public static function supported( string $format ): bool {
		return in_array( $format, array( 'csv', 'xlsx', 'json' ), true );
	}

	/** Stream an authenticated export. */
	public static function download( array $rows, string $format ): void {
		if ( ! self::supported( $format ) ) {
			wp_die( esc_html__( 'Unsupported user export format.', 'taxonomy-csv-import-export' ), '', array( 'response' => 400 ) );
		}
		if ( 'xlsx' === $format && ! class_exists( '\\ZipArchive' ) ) {
			wp_die( esc_html__( 'The PHP Zip extension is required for Excel exports.', 'taxonomy-csv-import-export' ) );
		}
		if ( 'json' === $format ) {
			$content = wp_json_encode( array( 'format' => 'taxocsie-users-v1', 'users' => $rows ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $content ) {
				wp_die( esc_html__( 'The JSON export could not be encoded.', 'taxonomy-csv-import-export' ) );
			}
			self::headers( 'json', 'application/json; charset=utf-8', strlen( $content ) );
			Dashboard::increment( Dashboard::EXPORTED_ITEMS, count( $rows ) );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
			exit;
		}
		if ( 'xlsx' === $format ) {
			$file = wp_tempnam( 'users-export.xlsx' );
			if ( ! is_string( $file ) || ! self::write_xlsx( $file, $rows ) ) {
				if ( is_string( $file ) ) { wp_delete_file( $file ); }
				wp_die( esc_html__( 'The Excel export could not be created.', 'taxonomy-csv-import-export' ) );
			}
			$size = filesize( $file );
			self::headers( 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', false === $size ? null : (int) $size );
			Dashboard::increment( Dashboard::EXPORTED_ITEMS, count( $rows ) );
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Download stream.
			wp_delete_file( $file );
			exit;
		}
		self::headers( 'csv', 'text/csv; charset=utf-8' );
		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $output, self::COLUMNS, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $output, self::csv_cells( $row ), ',', '"', '' );
		}
		Dashboard::increment( Dashboard::EXPORTED_ITEMS, count( $rows ) );
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private static function headers( string $format, string $type, ?int $size = null ): void {
		if ( headers_sent() ) {
			wp_die( esc_html__( 'The download could not start because output was already sent.', 'taxonomy-csv-import-export' ) );
		}
		while ( ob_get_level() > 0 ) { ob_end_clean(); }
		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="users-export.' . $format . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		if ( null !== $size ) { header( 'Content-Length: ' . $size ); }
	}

	private static function write_xlsx( string $path, array $rows ): bool {
		$sheet = wp_tempnam( 'taxocsie-users-sheet.xml' );
		if ( ! is_string( $sheet ) ) { return false; }
		$handle = fopen( $sheet, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) { wp_delete_file( $sheet ); return false; }
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streams rows to a temporary file; WP_Filesystem cannot append.
		fwrite( $handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' );
		$all_rows = array_merge( array( array_combine( self::COLUMNS, self::COLUMNS ) ), $rows );
		foreach ( $all_rows as $index => $row ) {
			$number = $index + 1;
			fwrite( $handle, '<row r="' . $number . '">' );
			foreach ( self::COLUMNS as $column => $key ) {
				$value = (string) ( $row[ $key ] ?? '' );
				$value = (string) preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );
				fwrite( $handle, '<c r="' . chr( 65 + $column ) . $number . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</t></is></c>' );
			}
			fwrite( $handle, '</row>' );
		}
		fwrite( $handle, '</sheetData></worksheet>' );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) { wp_delete_file( $sheet ); return false; }
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Users" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
		$zip->addFile( $sheet, 'xl/worksheets/sheet1.xml' );
		$ok = $zip->close();
		wp_delete_file( $sheet );
		return $ok;
	}

	/** Validate the selected upload against its extension and content. */
	public static function uploaded_file( string $field, string $format ) {
		if ( ! self::supported( $format ) || ! isset( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { return false; }
		$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked below.
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_basename( wp_unslash( $file['name'] ) ) ) : '';
		$path = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE )
			|| $format !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) )
			|| '' === $path || ! is_uploaded_file( $path ) || ! is_readable( $path )
			|| ! isset( $file['size'] ) || (int) $file['size'] < 1 || (int) $file['size'] > self::MAX_UPLOAD_SIZE
			|| false === filesize( $path ) || filesize( $path ) > self::MAX_UPLOAD_SIZE ) {
			return false;
		}
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$magic = false === $handle ? false : fread( $handle, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reads the 4-byte file signature only.
		if ( false !== $handle ) { fclose( $handle ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ( 'xlsx' === $format && "PK\x03\x04" !== $magic ) || ( 'xlsx' !== $format && "PK\x03\x04" === $magic ) ) { return false; }
		return $path;
	}

	/** Convert JSON/Excel rows to the existing CSV user importer. */
	public static function import( string $path, string $format ) {
		if ( ! self::supported( $format ) ) { return new \WP_Error( 'user_format', __( 'Unsupported user import format.', 'taxonomy-csv-import-export' ) ); }
		if ( 'csv' === $format ) { return Helper::import_users( $path ); }
		$rows = 'json' === $format ? self::read_json( $path ) : self::read_xlsx( $path );
		if ( is_wp_error( $rows ) ) { return $rows; }
		$tmp = wp_tempnam( 'taxocsie-user-import.csv' );
		if ( ! is_string( $tmp ) ) { return new \WP_Error( 'user_temp_file', __( 'The import could not create a temporary file.', 'taxonomy-csv-import-export' ) ); }
		$handle = fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) { wp_delete_file( $tmp ); return new \WP_Error( 'user_temp_file', __( 'The import could not create a temporary file.', 'taxonomy-csv-import-export' ) ); }
		fputcsv( $handle, self::COLUMNS, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, self::csv_cells( $row ), ',', '"', '' );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		try { return Helper::import_users( $tmp ); }
		finally { wp_delete_file( $tmp ); }
	}

	private static function csv_cells( array $row ): array {
		$values = array();
		foreach ( self::COLUMNS as $key ) { $values[] = Helper::csv_safe_cell( $row[ $key ] ?? '' ); }
		return $values;
	}

	private static function normalize_rows( array $rows ) {
		if ( count( $rows ) > self::MAX_ROWS ) { return new \WP_Error( 'user_row_limit', __( 'The user file contains too many rows.', 'taxonomy-csv-import-export' ) ); }
		$result = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { return new \WP_Error( 'user_invalid_row', __( 'The user file contains an invalid row.', 'taxonomy-csv-import-export' ) ); }
			$normalized = array();
			foreach ( self::COLUMNS as $key ) {
				$value = $row[ $key ] ?? '';
				if ( 'roles' === $key && is_array( $value ) ) { $value = implode( '|', $value ); }
				if ( 'meta' === $key && is_array( $value ) ) { $value = wp_json_encode( $value ); }
				if ( ! is_scalar( $value ) && null !== $value ) { return new \WP_Error( 'user_invalid_value', __( 'The user file contains an invalid field value.', 'taxonomy-csv-import-export' ) ); }
				$normalized[ $key ] = (string) $value;
			}
			$result[] = $normalized;
		}
		return $result;
	}

	private static function read_json( string $path ) {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || strlen( $contents ) > self::MAX_UPLOAD_SIZE ) { return new \WP_Error( 'user_json_read', __( 'The JSON file could not be read.', 'taxonomy-csv-import-export' ) ); }
		$decoded = json_decode( $contents, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || ! isset( $decoded['users'] ) || ! is_array( $decoded['users'] ) ) {
			return new \WP_Error( 'user_json_schema', __( 'The JSON file must contain a users array.', 'taxonomy-csv-import-export' ) );
		}
		return self::normalize_rows( $decoded['users'] );
	}

	private static function read_xlsx( string $path ) {
		if ( ! class_exists( '\\ZipArchive' ) || ! class_exists( '\\DOMDocument' ) ) {
			return new \WP_Error( 'user_xlsx_extensions', __( 'PHP Zip and DOM extensions are required to import Excel files.', 'taxonomy-csv-import-export' ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::RDONLY ) ) { return new \WP_Error( 'user_xlsx_open', __( 'The Excel file is unreadable.', 'taxonomy-csv-import-export' ) ); }
		if ( $zip->numFiles < 1 || $zip->numFiles > 128 ) { $zip->close(); return new \WP_Error( 'user_xlsx_limit', __( 'The Excel file has an unsafe number of entries.', 'taxonomy-csv-import-export' ) ); }
		$total = 0;
		for ( $i = 0; $i < $zip->numFiles; ++$i ) {
			$entry = $zip->statIndex( $i );
			if ( ! is_array( $entry ) || empty( $entry['name'] ) || false !== strpos( str_replace( '\\', '/', $entry['name'] ), '../' ) ) { $zip->close(); return new \WP_Error( 'user_xlsx_entry', __( 'The Excel file contains an unsafe entry.', 'taxonomy-csv-import-export' ) ); }
			$total += (int) ( $entry['size'] ?? 0 );
			if ( $total > self::MAX_UNCOMPRESSED_SIZE ) { $zip->close(); return new \WP_Error( 'user_xlsx_size', __( 'The Excel file is too large.', 'taxonomy-csv-import-export' ) ); }
		}
		$workbook = self::xml( $zip->getFromName( 'xl/workbook.xml' ) );
		$relations = self::xml( $zip->getFromName( 'xl/_rels/workbook.xml.rels' ) );
		if ( ! $workbook || ! $relations ) { $zip->close(); return new \WP_Error( 'user_xlsx_structure', __( 'The Excel workbook is incomplete.', 'taxonomy-csv-import-export' ) ); }
		$sheet = $workbook->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheet' )->item( 0 );
		$id = $sheet instanceof \DOMElement ? $sheet->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id' ) : '';
		$sheet_path = '';
		foreach ( $relations->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship' ) as $relation ) {
			if ( $id && $relation->getAttribute( 'Id' ) === $id ) {
				$target = str_replace( '\\', '/', $relation->getAttribute( 'Target' ) );
				$sheet_path = 0 === strpos( $target, '/xl/' ) || 0 === strpos( $target, 'xl/' )
					? ltrim( $target, '/' ) : 'xl/' . ltrim( $target, '/' );
				break;
			}
		}
		if ( '' === $sheet_path || false !== strpos( $sheet_path, '../' ) ) { $zip->close(); return new \WP_Error( 'user_xlsx_sheet', __( 'The first Excel worksheet was not found.', 'taxonomy-csv-import-export' ) ); }
		$sheet_xml = self::xml( $zip->getFromName( $sheet_path ) );
		$shared_xml = self::xml( $zip->getFromName( 'xl/sharedStrings.xml' ) );
		$zip->close();
		if ( ! $sheet_xml ) { return new \WP_Error( 'user_xlsx_sheet', __( 'The first Excel worksheet was not found.', 'taxonomy-csv-import-export' ) ); }
		$shared = array();
		if ( $shared_xml ) {
			foreach ( $shared_xml->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'si' ) as $item ) {
				$shared[] = $item->textContent;
			}
		}
		$grid = array();
		foreach ( $sheet_xml->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row' ) as $row ) {
			if ( count( $grid ) > self::MAX_ROWS ) { return new \WP_Error( 'user_row_limit', __( 'The Excel file contains too many rows.', 'taxonomy-csv-import-export' ) ); }
			$cells = array();
			foreach ( $row->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c' ) as $cell ) {
				$reference = $cell->getAttribute( 'r' );
				$letters = strtoupper( (string) preg_replace( '/[^A-Z]/i', '', $reference ) );
				$column = 0;
				foreach ( str_split( $letters ) as $letter ) { $column = $column * 26 + ord( $letter ) - 64; }
				$column = max( 0, $column - 1 );
				if ( $column >= 64 ) { continue; }
				$type = $cell->getAttribute( 't' );
				$value = 'inlineStr' === $type ? $cell->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'is' )->item( 0 ) : $cell->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v' )->item( 0 );
				$text = $value ? $value->textContent : '';
				$cells[ $column ] = 's' === $type ? ( $shared[ (int) $text ] ?? '' ) : $text;
			}
			$grid[] = $cells;
		}
		if ( ! $grid ) { return new \WP_Error( 'user_xlsx_empty', __( 'The Excel worksheet is empty.', 'taxonomy-csv-import-export' ) ); }
		$headers = array();
		foreach ( array_shift( $grid ) as $index => $value ) { $headers[ $index ] = sanitize_key( str_replace( array( ' ', '-' ), '_', trim( $value ) ) ); }
		if ( ! in_array( 'username', $headers, true ) || ! in_array( 'email', $headers, true ) ) {
			return new \WP_Error( 'user_xlsx_headers', __( 'The Excel worksheet needs username and email columns.', 'taxonomy-csv-import-export' ) );
		}
		$rows = array();
		foreach ( $grid as $values ) {
			$row = array();
			foreach ( $headers as $index => $header ) { if ( in_array( $header, self::COLUMNS, true ) ) { $row[ $header ] = $values[ $index ] ?? ''; } }
			$rows[] = $row;
		}
		return self::normalize_rows( $rows );
	}

	private static function xml( $source ): ?\DOMDocument {
		if ( ! is_string( $source ) || false !== stripos( $source, '<!DOCTYPE' ) ) { return null; }
		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$ok = $dom->loadXML( $source, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $ok ? $dom : null;
	}
}
