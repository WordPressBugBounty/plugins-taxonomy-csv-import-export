<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class ImportPreview {

    public static function init() {
        add_action( 'wp_ajax_academy_preview_import', [ __CLASS__, 'handle_preview' ] );
    }

    public static function handle_preview() {
        check_ajax_referer( 'taxocsie_import_preview', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized.', 'taxonomy-csv-import-export' ) );
        }

        if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
            wp_send_json_error( __( 'No file uploaded.', 'taxonomy-csv-import-export' ) );
        }

        $file = sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) );
        $rows = \TaxoCSIE\Helper::taxo_parse_csv( $file );

        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : 'category';

        $preview = [];

        foreach ( $rows as $row ) {
            $check = \TaxoCSIE\Helper::taxo_validate_row( $row, $taxonomy );

            $preview[] = [
                'name'   => $row['name'] ?? '',
                'status' => $check['status'],
                'errors' => $check['errors'],
            ];
        }

        wp_send_json_success( $preview );
    }
}