<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportProcessor {

    public static function init() {
        add_action( 'wp_ajax_academy_run_import', [ __CLASS__, 'run_import' ] );
    }

    public static function run_import() {
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( __( 'No file uploaded.', 'taxonomy-csv-import-export' ) );
        }

        $settings = [
            'skip_duplicates' => ! empty( $_POST['skip_duplicates'] ),
            'update_existing' => ! empty( $_POST['update_existing'] ),
        ];

        $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? 'category' );

        $file = $_FILES['file']['tmp_name'];
        $rows = \TaxoCSIE\Helper::taxo_parse_csv( $file );

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'created_terms' => [],
        ];

        foreach ( $rows as $row ) {
            $check = \TaxoCSIE\Helper::taxo_validate_row( $row, $taxonomy );

            if ( ! empty( $check['errors'] ) ) {
                $results['failed']++;
                continue;
            }

            if ( 'duplicate' === $check['status'] && $settings['skip_duplicates'] ) {
                $results['skipped']++;
                continue;
            }

            if ( 'update' === $check['status'] && $settings['update_existing'] ) {
                wp_update_term( $check['term_id'], $taxonomy, [
                    'name' => $row['name']
                ]);
                $results['updated']++;
                continue;
            }

            if ( 'create' === $check['status'] ) {
                $term = wp_insert_term( $row['name'], $taxonomy );

                if ( ! is_wp_error( $term ) ) {
                    $results['created']++;
                    $results['created_terms'][] = $term['term_id'];
                }
            }
        }

        ImportLogger::log( $results );

        wp_send_json_success( $results );
    }
}