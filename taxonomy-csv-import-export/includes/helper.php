<?php
namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Helper {

	/** Prefix spreadsheet formulas before writing a CSV cell. */
	public static function csv_safe_cell( $value ): string {
		$value = (string) $value;
		return preg_match( '/^[\x00-\x20]*[=+\-@]/', $value ) ? "\t" . $value : $value;
	}

	/** Restore the value when importing a CSV created by this plugin. */
	public static function csv_original_cell( $value ): string {
		$value = (string) $value;
		if ( isset( $value[0] ) && "\t" === $value[0] && preg_match( '/^[\x00-\x20]*[=+\-@]/', substr( $value, 1 ) ) ) {
			return substr( $value, 1 );
		}
		return $value;
	}

    public static function import_users( string $file ): int {
		$processed = 0;

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return $processed;
        }

        set_time_limit(0);// phpcs:ignore WordPress.PHP.TimeLimit -- Large imports may require more time.

        $handle = fopen( $file, 'r' );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( ! $handle ) {
			return $processed;
        }

        $header = fgetcsv( $handle, 1000, ',', '"', '\\' );

        if ( empty( $header ) ) {
            fclose( $handle );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $processed;
        }

        // Cache roles (performance).
        $available_roles = array_keys( wp_roles()->roles );

        while ( ( $data = fgetcsv( $handle, 1000, ',', '"', '\\' ) ) !== false ) {
			$data = array_map( array( self::class, 'csv_original_cell' ), $data );

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

            $valid_roles = array_values( array_unique( array_intersect( array_map( 'sanitize_key', $roles ), $available_roles ) ) );

            // Check existing user.
            $user_id = username_exists( $username ) ?: email_exists( $email );
            // CREATE / UPDATE USER
            if ( ! $user_id ) {
				$is_password_hash = self::is_wp_password_hash( $password );

                $user_id = wp_insert_user( [
                    'user_login' => $username,
                    'user_email' => $email,
                    'user_pass'  => $is_password_hash || '' === $password ? wp_generate_password() : $password,
                ] );

                if ( is_wp_error( $user_id ) ) {
                    continue;
                }

				// wp_insert_user() hashes its input; restore exported hashes verbatim.
				if ( $is_password_hash ) {
					global $wpdb;
					$updated = $wpdb->update(
						$wpdb->users,
						array( 'user_pass' => $password ),
						array( 'ID' => $user_id ),
						array( '%s' ),
						array( '%d' )
					);
					if ( false === $updated ) {
						continue;
					}
					clean_user_cache( $user_id );
				}

				if ( ! empty( $valid_roles ) ) {
					$user = new \WP_User( $user_id );
					$user->set_role( array_shift( $valid_roles ) );
					foreach ( $valid_roles as $role ) {
						$user->add_role( $role );
					}
				}

            } else {

				// Existing users keep their passwords and every current role.
                wp_update_user( [
                    'ID'         => $user_id,
                    'user_email' => $email,
                ] );
            }

            // USER META
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );

            if ( ! empty( $meta ) ) {
                foreach ( $meta as $key => $value ) {
					if ( ! is_string( $key ) || self::is_protected_user_meta( $key ) ) {
						continue;
					}
                    update_user_meta(
                        $user_id,
                        sanitize_key( $key ),
                        is_array( $value ) ? wp_json_encode( $value ) : sanitize_text_field( $value )
                    );
                }
            }

			$processed++;
        }

        fclose( $handle );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $processed;
    }

	/**
	 * Keep role and authentication data out of the generic CSV meta field.
	 */
	private static function is_protected_user_meta( $key ): bool {
		return ! is_string( $key )
			|| is_protected_meta( $key, 'user' )
			|| in_array( $key, array( 'session_tokens', 'application_passwords' ), true )
			|| (bool) preg_match( '/(?:^|_)(?:capabilities|user_level)$/', $key );
	}

	/**
	 * Recognize hashes written by WordPress so a migrated user's password survives.
	 */
	private static function is_wp_password_hash( $password ): bool {
		return is_string( $password ) && (
			(bool) preg_match( '/^(?:\$wp\$2[aby]\$|\$2[aby]\$|\$P\$|\$argon2(?:i|id)\$)/', $password )
			|| ( 32 === strlen( $password ) && ctype_xdigit( $password ) )
		);
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
		$exported_count = count( $users );

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
            foreach ( array_keys( $meta ) as $key ) {
				if ( self::is_protected_user_meta( $key ) ) {
					unset( $meta[ $key ] );
				}
			}

            // Flatten meta values
            $clean_meta = [];
            foreach ( $meta as $key => $value ) {
                $clean_meta[$key] = maybe_unserialize( $value[0] ?? '' );
            }

            fputcsv( $output, array_map( array( self::class, 'csv_safe_cell' ), [
                $user->user_login,
                $user->user_email,
                $user->user_pass,
                implode( '|', $user_obj->roles ?? ['subscriber'] ),
                get_user_meta( $user->ID, 'first_name', true ),
                get_user_meta( $user->ID, 'last_name', true ),
                wp_json_encode( $clean_meta ),
            ] ), ',', '"', '\\' );
        }

		Dashboard::increment( Dashboard::EXPORTED_ITEMS, $exported_count );

        fclose( $output );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    public static function taxoscie_main_menu_title()
	{
		$menu = [];
		$menu[TAXOCSIE_PLUGIN_SLUG] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Dashboard', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
			'callback' => 'get_taxocsie_dashboard_function'
		];
		$menu[TAXOCSIE_PLUGIN_SLUG . '-create'] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Create Term', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
			'callback' => 'get_taxocsie_create_term_function'
		];
        $menu[TAXOCSIE_PLUGIN_SLUG . '-all-terms'] = [
            'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
            'title' => __('All Terms', 'taxonomy-csv-import-export'),
            'capability' => 'manage_options',
            'callback' => 'get_taxocsie_all_terms_function'
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
		$menu[TAXOCSIE_PLUGIN_SLUG . '-manager'] = [
			'parent_slug' => TAXOCSIE_PLUGIN_SLUG,
			'title' => __('Data Manager', 'taxonomy-csv-import-export'),
			'capability' => 'manage_options',
			'callback' => 'get_taxocsie_export_function'
		];
		return apply_filters('taxocsie_get_all_menu_title', $menu);
	}
}
