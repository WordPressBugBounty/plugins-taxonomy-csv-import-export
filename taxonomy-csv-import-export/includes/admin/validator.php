<?php
/**
 * CSV Validator for Dry Run Preview
 */

namespace TaxoCSIE\Admin;

if (!defined('ABSPATH')) {
	exit;
}

class Validator {
	
	private $term_cache = array();
	private $slug_cache = array();
	private $name_cache = array();
	private $taxonomy = '';
	
	/**
	 * Validate all rows from CSV
	 *
	 * @param array $csv_data Parsed CSV data
	 * @param string $taxonomy Taxonomy to validate against
	 * @return array Validated rows
	 */
	public function validate_rows($csv_data, $taxonomy) {
		$this->taxonomy = $taxonomy;
		$this->reset_caches();
		
		$validated_rows = array();
		
		foreach ($csv_data as $row) {
			$validated = $this->validate_single_row($row, $taxonomy);
			$validated_rows[] = $validated;
		}
		
		return $validated_rows;
	}
	
	/**
	 * Validate a single row
	 *
	 * @param array $row Raw row data
	 * @param string $taxonomy Taxonomy
	 * @return array Validated row
	 */
	private function validate_single_row($row, $taxonomy) {
		$data = $row['data'];
		$row_number = $row['row_number'];
		
		$validated = array(
			'row_number' => $row_number,
			'name' => '',
			'slug' => '',
			'description' => '',
			'parent' => '',
			'taxonomy' => $taxonomy,
			'status' => 'new',
			'errors' => array(),
			'warnings' => array(),
			'existing_term' => null
		);
		
		// Validate name (required)
		if (empty($data['name'])) {
			$validated['errors'][] = array(
				'message' => esc_html__('Name is required', 'taxonomy-csv-import-export'),
				'suggestion' => esc_html__('Please provide a name for the taxonomy term.', 'taxonomy-csv-import-export')
			);
		} else {
			$validated['name'] = sanitize_text_field($data['name']);
		}
		
		// Validate slug (optional)
		if (!empty($data['slug'])) {
			$slug = sanitize_title($data['slug']);
			$validated['slug'] = $slug;
			
			if (!preg_match('/^[a-z0-9-_]+$/', $slug)) {
				$validated['errors'][] = array(
					'message' => esc_html__('Invalid slug format', 'taxonomy-csv-import-export'),
					'suggestion' => esc_html__('Slug should only contain lowercase letters, numbers, hyphens, and underscores.', 'taxonomy-csv-import-export')
				);
			}
			
			// Check if slug was modified
			$suggested_slug = sanitize_title($data['slug']);
			if ($slug !== $suggested_slug) {
				$validated['warnings'][] = array(
					'message' => sprintf(
						// translators: 1: Original slug, 2: Suggested slug
						esc_html__('Slug "%1$s" was sanitized to "%2$s"', 'taxonomy-csv-import-export'),
						$data['slug'],
						$suggested_slug
					)
				);
				$validated['slug'] = $suggested_slug;
			}
		}
		
		// Validate description (optional)
		if (!empty($data['description'])) {
			$validated['description'] = wp_kses_post($data['description']);
		}
		
		// Validate parent (optional)
		if (!empty($data['parent_slug'])) {
			$parent_name = sanitize_text_field($data['parent_slug']);
			$validated['parent'] = $parent_name;
			
			if (!empty($taxonomy)) {
				$parent_term = $this->find_term_by_slug($parent_name, $taxonomy);
				if (!$parent_term) {
					$validated['errors'][] = array(
						// translators: 1: Parent term name
						'message' => sprintf( esc_html__('Parent term "%1$s" not found', 'taxonomy-csv-import-export'), $parent_name),
						'suggestion' => esc_html__('Create the parent term first or check the spelling.', 'taxonomy-csv-import-export')
					);
				} else {
					$validated['parent_id'] = $parent_term->term_id;
				}
			}
		}
		
		// Check for duplicates within CSV
		$this->check_duplicates($validated, $row_number);
		
		// Check if term already exists in WordPress
		$this->check_existing_term($validated, $taxonomy);
		
		// Determine final status
		$this->determine_status($validated);
		
		return $validated;
	}
	
	/**
	 * Check for duplicates in CSV
	 *
	 * @param array &$validated Validated row data
	 * @param int $row_number Current row number
	 */
	private function check_duplicates(&$validated, $row_number) {
		if (!empty($validated['slug'])) {
			$cache_key = $validated['slug'];
			if (isset($this->slug_cache[$cache_key])) {
				$validated['warnings'][] = array(
					'message' => sprintf(
						// translators: 1: Slug, 2: Row number
						esc_html__('Duplicate slug "%1$s" found in row %2$d', 'taxonomy-csv-import-export'),
						$validated['slug'],
						$this->slug_cache[$cache_key]
					)
				);
			} else {
				$this->slug_cache[$cache_key] = $row_number;
			}
		}
		
		if (!empty($validated['name']) && !empty($validated['taxonomy'])) {
			$cache_key = $validated['name'] . '|' . $validated['taxonomy'];
			if (isset($this->name_cache[$cache_key])) {
				$validated['warnings'][] = array(
					'message' => sprintf(
						// translators: 1: Name, 2: Row number
						esc_html__('Duplicate name "%1$s" found in row %2$d', 'taxonomy-csv-import-export'),
						$validated['name'],
						$this->name_cache[$cache_key]
					)
				);
			} else {
				$this->name_cache[$cache_key] = $row_number;
			}
		}
	}
	
	/**
	 * Check if term already exists in WordPress
	 *
	 * @param array &$validated Validated row data
	 * @param string $taxonomy Taxonomy
	 */
	private function check_existing_term(&$validated, $taxonomy) {
		if (empty($validated['name']) || empty($taxonomy)) {
			return;
		}
		
		$cache_key = $taxonomy . '|' . $validated['name'];
		if (isset($this->term_cache[$cache_key])) {
			$validated['existing_term'] = $this->term_cache[$cache_key];
			return;
		}
		
		$term = term_exists($validated['name'], $taxonomy);
		if ($term) {
			if (is_array($term)) {
				$term_obj = get_term($term['term_id'], $taxonomy);
				if ($term_obj && !is_wp_error($term_obj)) {
					$this->term_cache[$cache_key] = $term_obj;
					$validated['existing_term'] = $term_obj;
					
					// Check if slug matches
					if (!empty($validated['slug']) && $validated['slug'] !== $term_obj->slug) {
						$validated['warnings'][] = array(
							'message' => sprintf(
								// translators: 1: Existing term slug, 2: Current slug
								esc_html__('Term exists with slug "%1$s". Current slug "%2$s" will be ignored.', 'taxonomy-csv-import-export'),
								$term_obj->slug,
								$validated['slug']
							)
						);
						$validated['slug'] = $term_obj->slug;
					}
				}
			}
		} else {
			// Check by slug if name not found
			if (!empty($validated['slug'])) {
				$term_by_slug = get_term_by('slug', $validated['slug'], $taxonomy);
				if ($term_by_slug && !is_wp_error($term_by_slug)) {
					$this->term_cache[$cache_key] = $term_by_slug;
					$validated['existing_term'] = $term_by_slug;
					
					$validated['warnings'][] = array(
						'message' => sprintf(
							// translators: 1: Slug, 2: Name
							esc_html__('Term exists with slug "%1$s" but different name "%2$s".', 'taxonomy-csv-import-export'),
							$validated['slug'],
							$term_by_slug->name
						)
					);
				}
			}
		}
	}
	
	/**
	 * Find term by slug
	 *
	 * @param string $slug Term slug
	 * @param string $taxonomy Taxonomy
	 * @return object|false Term object or false
	 */
	private function find_term_by_slug($slug, $taxonomy) {
		$term = get_term_by('slug', $slug, $taxonomy);
		if ($term && !is_wp_error($term)) {
			return $term;
		}
		return false;
	}
	
	/**
	 * Determine final status
	 *
	 * @param array &$validated Validated row data
	 */
	private function determine_status(&$validated) {
		if (!empty($validated['errors'])) {
			$validated['status'] = 'error';
			return;
		}
		
		if (!empty($validated['warnings'])) {
			$validated['status'] = 'warning';
		}
		
		if (isset($validated['existing_term'])) {
			$validated['status'] = 'update';
		} else {
			$validated['status'] = 'new';
		}
		
		// Check if duplicate warning should mark as skip
		foreach ($validated['warnings'] as $warning) {
			if (strpos($warning['message'], 'Duplicate') === 0) {
				$validated['status'] = 'skip';
				break;
			}
		}
	}
	
	/**
	 * Reset caches
	 */
	private function reset_caches() {
		$this->term_cache = array();
		$this->slug_cache = array();
		$this->name_cache = array();
	}
}