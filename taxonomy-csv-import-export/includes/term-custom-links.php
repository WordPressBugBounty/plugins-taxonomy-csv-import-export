<?php
/**
 * Custom links for taxonomy terms.
 *
 * @package TaxoCSIE
 */

namespace TaxoCSIE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a URL field to taxonomy term forms and replaces generated term links.
 */
final class TermCustomLinks {

	/** Term meta key used to store the custom URL. */
	public const META_KEY = '_taxocsie_custom_link';

	/** Form field name. */
	private const FIELD_NAME = 'taxocsie_custom_link';

	/** Nonce action and field. */
	private const NONCE_ACTION = 'taxocsie_save_term_custom_link';
	private const NONCE_FIELD  = 'taxocsie_custom_link_nonce';

	/** Register hooks. */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_term_form_fields' ), 99 );
		add_action( 'created_term', array( __CLASS__, 'save' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'save' ), 10, 3 );
		add_filter( 'term_link', array( __CLASS__, 'filter_term_link' ), 10, 3 );
	}

	/** Attach fields to every taxonomy that has a WordPress admin UI. */
	public static function register_term_form_fields(): void {
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy instanceof \WP_Taxonomy ) {
				continue;
			}

			add_action( $taxonomy->name . '_add_form_fields', array( __CLASS__, 'render_add_field' ) );
			add_action( $taxonomy->name . '_edit_form_fields', array( __CLASS__, 'render_edit_field' ), 10, 2 );
			add_filter( 'manage_edit-' . $taxonomy->name . '_columns', array( __CLASS__, 'add_list_table_column' ) );
			add_filter( 'manage_' . $taxonomy->name . '_custom_column', array( __CLASS__, 'render_list_table_column' ), 10, 3 );
		}
	}

	/** Add a Custom Link column to taxonomy term list tables. */
	public static function add_list_table_column( array $columns ): array {
		$updated = array();
		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'slug' === $key ) {
				$updated['taxocsie_custom_link'] = __( 'Custom Link', 'taxonomy-csv-import-export' );
			}
		}

		if ( ! isset( $updated['taxocsie_custom_link'] ) ) {
			$updated['taxocsie_custom_link'] = __( 'Custom Link', 'taxonomy-csv-import-export' );
		}

		return $updated;
	}

	/** Render the Custom Link taxonomy list-table cell. */
	public static function render_list_table_column( $content, $column_name, $term_id ): string {
		if ( 'taxocsie_custom_link' !== $column_name ) {
			return (string) $content;
		}

		$url = self::get( (int) $term_id );
		if ( '' === $url ) {
			return '&mdash;';
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( $url ),
			esc_html( $url ),
			esc_html__( '(opens in a new tab)', 'taxonomy-csv-import-export' )
		);
	}

	/** Render the field on the Add Term screen. */
	public static function render_add_field(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="form-field term-taxocsie-custom-link-wrap">
			<label for="taxocsie-custom-link"><?php esc_html_e( 'Custom Link', 'taxonomy-csv-import-export' ); ?></label>
			<input type="url" id="taxocsie-custom-link" name="<?php echo esc_attr( self::FIELD_NAME ); ?>" value="" placeholder="https://example.com/page">
			<p><?php esc_html_e( 'Optional. WordPress will use this HTTP or HTTPS URL instead of the normal taxonomy archive link.', 'taxonomy-csv-import-export' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the field on the Edit Term screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public static function render_edit_field( $term ): void {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$value = self::get( (int) $term->term_id );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<tr class="form-field term-taxocsie-custom-link-wrap">
			<th scope="row"><label for="taxocsie-custom-link"><?php esc_html_e( 'Custom Link', 'taxonomy-csv-import-export' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" id="taxocsie-custom-link" name="<?php echo esc_attr( self::FIELD_NAME ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="https://example.com/page">
				<p class="description"><?php esc_html_e( 'Optional. WordPress will use this HTTP or HTTPS URL instead of the normal taxonomy archive link.', 'taxonomy-csv-import-export' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save or delete the custom term URL.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term-taxonomy ID (unused).
	 * @param string $taxonomy Taxonomy name.
	 */
	public static function save( $term_id, $tt_id, $taxonomy ): void {
		unset( $tt_id );

		if ( ! isset( $_POST[ self::NONCE_FIELD ], $_POST[ self::FIELD_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$taxonomy_object = get_taxonomy( sanitize_key( $taxonomy ) );
		$capability      = $taxonomy_object instanceof \WP_Taxonomy ? (string) $taxonomy_object->cap->manage_terms : 'manage_categories';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$raw_url = trim( (string) wp_unslash( $_POST[ self::FIELD_NAME ] ) );
		if ( '' === $raw_url ) {
			delete_term_meta( (int) $term_id, self::META_KEY );
			return;
		}

		$url = self::sanitize_link( $raw_url );
		if ( '' !== $url ) {
			update_term_meta( (int) $term_id, self::META_KEY, $url );
		}
	}

	/** Get a stored custom URL. */
	public static function get( int $term_id ): string {
		$url = get_term_meta( $term_id, self::META_KEY, true );

		return is_string( $url ) ? self::sanitize_link( $url ) : '';
	}

	/** Set a custom URL from a trusted plugin workflow such as CSV import. */
	public static function set( int $term_id, string $url ): bool {
		$url = self::sanitize_link( $url );
		if ( '' === $url ) {
			return false;
		}

		return false !== update_term_meta( $term_id, self::META_KEY, $url );
	}

	/**
	 * Validate and normalize an HTTP(S) link without fetching it.
	 *
	 * Site-relative paths beginning with a single slash are converted into an
	 * absolute home URL. Protocol-relative URLs are rejected.
	 */
	public static function sanitize_link( string $url ): string {
		$url = trim( $url );
		if ( '' === $url || 0 === strpos( $url, '//' ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}

		$url    = esc_url_raw( $url, array( 'http', 'https' ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return '';
		}

		return $url;
	}

	/** Replace the generated archive URL when the system and term URL are set. */
	public static function filter_term_link( $term_link, $term, $taxonomy ) {
		unset( $taxonomy );

		if ( ! $term instanceof \WP_Term || ! Settings::custom_term_links_enabled() ) {
			return $term_link;
		}

		$custom_link = self::get( (int) $term->term_id );

		return '' !== $custom_link ? $custom_link : $term_link;
	}
}
