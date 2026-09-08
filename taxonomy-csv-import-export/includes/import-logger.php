<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportLogger {

    public static function init() {
        
    }

    public static function log( array $data ) {
        $logs   = get_option( 'academy_import_logs', [] );

        $logs[] = [
            'time' => current_time( 'mysql' ),
            'data' => $data,
        ];

        update_option( 'academy_import_logs', $logs );
    }

    public static function rollback_last() {
        $logs = get_option( 'academy_import_logs', [] );
        $last = end( $logs );

        if ( empty( $last['data']['created_terms'] ) ) {
            return;
        }

        foreach ( $last['data']['created_terms'] as $term_id ) {
            wp_delete_term( $term_id, 'category' );
        }
    }
}