<?php
/**
 * Session Handler for Dry Run Preview Data
 * Uses WordPress Transients for temporary storage
 */

namespace TaxoCSIE\Admin;

if (!defined('ABSPATH')) {
	exit;
}

class SessionHandler {
	
	private static $instance = null;
	private $transient_prefix = 'tcie_preview_';
	private $expiration = 2; // 2 hours
	
	/**
	 * Get instance (Singleton)
	 *
	 * @return SessionHandler
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		add_action('admin_init', array($this, 'cleanup_expired'));
	}
	
	/**
	 * Save preview data with compression for large datasets
	 *
	 * @param array $data Preview data
	 * @return string|false Transient key or false on failure
	 */
	public function save_preview_data($data) {
		$key = $this->generate_key();
		$transient_key = $this->transient_prefix . $key;
		
		// Compress large data
		$serialized = maybe_serialize($data);
		$compressed = false;
		
		if (strlen($serialized) > 500000) { // 500KB
			$serialized = gzcompress($serialized, 9);
			$compressed = true;
		}
		
		$saved = set_transient(
			$transient_key,
			array(
				'data' => $serialized,
				'compressed' => $compressed,
				'timestamp' => current_time('timestamp'),
				'user_id' => get_current_user_id()
			),
			$this->expiration * HOUR_IN_SECONDS
		);
		
		if ($saved) {
			update_user_meta(
				get_current_user_id(),
				'tcie_current_preview_key',
				$key
			);
			return $key;
		}
		
		return false;
	}
	
	/**
	 * Get preview data
	 *
	 * @return array|false Preview data or false
	 */
	public function get_preview_data() {
		$key = get_user_meta(
			get_current_user_id(),
			'tcie_current_preview_key',
			true
		);
		
		if (!$key) {
			return false;
		}
		
		$transient_key = $this->transient_prefix . $key;
		$stored = get_transient($transient_key);
		
		if (!$stored) {
			return false;
		}
		
		// Verify user ownership
		if (isset($stored['user_id']) && $stored['user_id'] !== get_current_user_id()) {
			return false;
		}
		
		$data = $stored['data'];
		if (!empty($stored['compressed'])) {
			$data = gzuncompress($data);
		}
		
		return maybe_unserialize($data);
	}
	
	/**
	 * Clear preview data
	 *
	 * @return bool
	 */
	public function clear_preview_data() {
		$key = get_user_meta(
			get_current_user_id(),
			'tcie_current_preview_key',
			true
		);
		
		if ($key) {
			$transient_key = $this->transient_prefix . $key;
			delete_transient($transient_key);
			delete_user_meta(
				get_current_user_id(),
				'tcie_current_preview_key'
			);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Generate unique key
	 *
	 * @return string
	 */
	private function generate_key() {
		return wp_hash(
			get_current_user_id() . '_' . current_time('timestamp') . '_' . wp_rand(100000, 999999)
		);
	}
	
	/**
	 * Clean up expired transients
	 */
	public function cleanup_expired() {
		global $wpdb;
		
		$last_cleanup = get_option('tcie_last_transient_cleanup', 0);
		if (current_time('timestamp') - $last_cleanup < DAY_IN_SECONDS) {
			return;
		}
		
		// Clean up expired transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_value LIKE %s",
				'_transient_' . $this->transient_prefix . '%',
				'%"timestamp"%'
			)
		);
		
		update_option('tcie_last_transient_cleanup', current_time('timestamp'));
	}
}