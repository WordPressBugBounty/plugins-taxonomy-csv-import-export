<?php
namespace TaxoCSIE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Preview {
    public static function init() {
        $self = new self();
        add_action( 'wp_ajax_taxo_preview_import', [ $self, 'get_taxo_data_preview' ], 10, 1 );
    }

    public function get_taxo_data_preview() {
    }
}