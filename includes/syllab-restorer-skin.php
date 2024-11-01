<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

if (!class_exists('WP_Upgrader_Skin')) require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');

abstract class Syllab_Restorer_Skin_Main extends WP_Upgrader_Skin {

	// @codingStandardsIgnoreStart
	public function header() {}
	public function footer() {}
	public function bulk_header() {}
	public function bulk_footer() {}
	// @codingStandardsIgnoreEnd

	/**
	 * return error
	 *
	 * @param  string $error error message
	 * @return string
	 */
	public function error($error) {
		if (!$error) return;
		global $syllabplus;
		if (is_wp_error($error)) {
			$syllabplus->log_wp_error($error, true);
		} elseif (is_string($error)) {
			$syllabplus->log($error);
			$syllabplus->log($error, 'warning-restore');
		}
	}

	protected function syllab_feedback($string) {

		if (isset($this->upgrader->strings[$string])) {
			$string = $this->upgrader->strings[$string];
		}

		if (false !== strpos($string, '%')) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ($args) {
				$args = array_map('strip_tags', $args);
				$args = array_map('esc_html', $args);
				$string = vsprintf($string, $args);
			}
		}
		if (empty($string)) return;

		global $syllabplus;
		$syllabplus->log_e($string);
	}
}

global $syllabplus;
$wp_version = $syllabplus->get_wordpress_version();

if (version_compare($wp_version, '5.3', '>=')) {
	if (!class_exists('Syllab_Restorer_Skin')) require_once(SYLLABPLUS_DIR.'/includes/syllab-restorer-skin-compatibility.php');
} else {
	class Syllab_Restorer_Skin extends Syllab_Restorer_Skin_Main {

		public function feedback($string) {
			parent::syllab_feedback($string);
		}
	}
}
