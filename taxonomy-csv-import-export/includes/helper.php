<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Helper {

    public static function import_users( string $file ) {

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return;
        }

        set_time_limit(0);// phpcs:ignore WordPress.PHP.TimeLimit -- Large imports may require more time.

        $handle = fopen( $file, 'r' );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( ! $handle ) {
            return;
        }

        $header = fgetcsv( $handle, 1000, ',', '"', '\\' );

        if ( empty( $header ) ) {
            fclose( $handle );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            return;
        }

        // Cache roles (performance)
        global $wp_roles;
        $available_roles = array_keys( $wp_roles->roles );

        while ( ( $data = fgetcsv( $handle, 1000, ',', '"', '\\' ) ) !== false ) {

            // Safe mapping
            $username   = ! empty( $data[0] ) ? sanitize_user( $data[0] ) : '';
            $email      = ! empty( $data[1] ) ? sanitize_email( $data[1] ) : '';
            $password   = $data[2] ?? '';
            $roles      = ! empty( $data[3] ) ? explode( '|', $data[3] ) : [];
            $first_name = sanitize_text_field( $data[4] ?? '' );
            $last_name  = sanitize_text_field( $data[5] ?? '' );

            // Proper JSON decode
            $meta = [];
            if ( ! empty( $data[6] ) ) {
                $decoded = json_decode( wp_unslash( $data[6] ), true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                    $meta = $decoded;
                }
            }

            if ( empty( $username ) || empty( $email ) ) {
                continue;
            }

            // Check existing user
            $user_id = username_exists( $username ) ?: email_exists( $email );

            // CREATE / UPDATE USER
            if ( ! $user_id ) {

                $user_id = wp_insert_user( [
                    'user_login' => $username,
                    'user_email' => $email,
                    'user_pass'  => ! empty( $password ) ? $password : wp_generate_password(),
                ] );

                if ( is_wp_error( $user_id ) ) {
                    continue;
                }

            } else {

                wp_update_user( [
                    'ID'         => $user_id,
                    'user_email' => $email,
                ] );
            }

            // ROLE ASSIGNMENT
            $role = ! empty( $roles[0] ) ? sanitize_key( $roles[0] ) : 'subscriber';

            if ( ! in_array( $role, $available_roles, true ) ) {
                $role = 'subscriber';
            }

            ( new \WP_User( $user_id ) )->set_role( $role );

            // USER META
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );

            if ( ! empty( $meta ) ) {
                foreach ( $meta as $key => $value ) {
                    update_user_meta(
                        $user_id,
                        sanitize_key( $key ),
                        is_array( $value ) ? wp_json_encode( $value ) : sanitize_text_field( $value )
                    );
                }
            }
        }

        fclose( $handle );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    }

    public static function export_users( $selected_roles = [] ) {

        set_time_limit(0);// phpcs:ignore WordPress.PHP.TimeLimit -- Large exports may require more time.

        $args = [
            'number' => -1,
            'fields' => [ 'ID', 'user_login', 'user_email', 'user_pass' ],
        ];

        if ( ! empty( $selected_roles ) ) {
            $args['role__in'] = array_map( 'sanitize_key', $selected_roles );
        }

        $users = get_users( $args );

        if ( empty( $users ) ) {
            $users = [
                (object) [
                    'ID' => '',
                    'user_login' => '',
                    'user_email' => '',
                    'user_pass' => '',
                ]
            ];
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=users-export.csv' );

        $output = fopen( 'php://output', 'w' );

        // Header
        fputcsv( $output, [
            'username',
            'email',
            'password_hash',
            'roles',
            'first_name',
            'last_name',
            'meta'
        ], ',', '"', '\\' );

        foreach ( $users as $user ) {

            $user_obj = get_userdata( $user->ID );

            $meta = get_user_meta( $user->ID );

            // Clean meta (remove system junk)
            unset( $meta['session_tokens'], $meta['wp_user_level'] );

            // Flatten meta values
            $clean_meta = [];
            foreach ( $meta as $key => $value ) {
                $clean_meta[$key] = maybe_unserialize( $value[0] ?? '' );
            }

            fputcsv( $output, [
                $user->user_login,
                $user->user_email,
                $user->user_pass,
                implode( '|', $user_obj->roles ?? ['subscriber'] ),
                get_user_meta( $user->ID, 'first_name', true ),
                get_user_meta( $user->ID, 'last_name', true ),
                wp_json_encode( $clean_meta ),
            ], ',', '"', '\\' );
        }

        fclose( $output );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    public static function taxoscie_main_menu_title()
	{
		$menu = [];
		$menu[TAXOCSIE_PLUGIN_SLUG] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Taxonomy', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
            'callback' => 'get_taxocsie_export_function'
		];
		$menu[TAXOCSIE_PLUGIN_SLUG . '-users'] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Users', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
			'callback' => 'taxocsie_users_export_import_function'
		];
		$menu[TAXOCSIE_PLUGIN_SLUG . '-create'] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Create Term', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
			'callback' => 'get_taxocsie_create_term_function'
		];
		$menu[TAXOCSIE_PLUGIN_SLUG . '-taxonomy'] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Register Taxonomy', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
			'callback' => 'get_taxocsie_register_taxonomy_function'
		];
        // NEW: Demo Generator Menu
		$menu['taxonomy-csv-import-export-demo'] = array(
            'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
            'title'       => __('Demo Generator', 'taxonomy-csv-import-export'),
            'capability'  => 'manage_options',
            'callback'    => 'get_taxocsie_demo_generator_function',
        );
		return apply_filters('taxocsie_get_all_menu_title', $menu);
	}

    public static function taxo_parse_csv( string $file ) {
        $rows = [];
        $handle = fopen( $file, 'r' );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        $header = fgetcsv( $handle, 0, ',', '"', '' );

        while ( ( $data = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
            $rows[] = array_combine( $header, $data );
        }

        fclose( $handle );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        return $rows;
    }

    public static function taxo_validate_row( array $row, string $taxonomy ) {
        $errors = [];

        if ( empty( $row['name'] ) ) {
            $errors[] = 'Name is required';
        }

        $term = get_term_by( 'name', $row['name'], $taxonomy );

        if ( $term ) {
            return [
                'status'  => 'update',
                'term_id' => $term->term_id,
                'errors'  => $errors,
            ];
        }

        return [
            'status' => 'create',
            'errors' => $errors,
        ];
    }

    
}