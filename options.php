<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
/**
 * Options handling
 */
if (!defined('ABSPATH')) die('No direct access allowed');

class SyllabPlus_Options {

	/**
	 * Whether or not the current user has permission to manage SyllabPlus
	 *
	 * @return Boolean
	 */
	public static function user_can_manage() {
		$user_can_manage = current_user_can(apply_filters('option_page_capability_syllab-options-group', 'manage_options'));
		// false: allows the filter to know that the request is not coming from the multisite add-on
		return apply_filters('syllab_user_can_manage', $user_can_manage, false);
	}

	public static function options_table() {
		return 'options';
	}

	/**
	 * Get the URL to the dashboard admin page
	 *
	 * @return String
	 */
	public static function admin_page_url() {
		return admin_url('options-general.php');
	}

	/**
	 * Get the base-name for the dashboard admin page
	 *
	 * @return String
	 */
	public static function admin_page() {
		return 'options-general.php';
	}

	/**
	 * Extracts the last logged message from syllabplus last process
	 *
	 * @return Mixed - Value set for the option or the default message
	 */
	public static function get_syllab_lastmessage() {
		return SyllabPlus_Options::get_syllab_option('syllab_lastmessage', __('(Nothing has been logged yet)', 'syllabplus'));
	}

	public static function get_syllab_option($option, $default = null) {
		$ret = get_option($option, $default);
		return apply_filters('syllabplus_get_option', $ret, $option, $default);
	}

	/**
	 * The apparently unused parameter is used in the alternative class in the Multisite add-on
	 *
	 * @param String  $option	 specify option name
	 * @param String  $value	 specify option value
	 * @param Boolean $use_cache whether or not to use the WP options cache
	 * @param String  $autoload	 whether to autoload (only takes effect on a change of value)
	 *
	 * @return Boolean - as from update_option()
	 */
	public static function update_syllab_option($option, $value, $use_cache = true, $autoload = 'yes') {
		return update_option($option, apply_filters('syllabplus_update_option', $value, $option, $use_cache), $autoload);
	}

	/**
	 * Delete an option
	 *
	 * @param String $option - the option name
	 */
	public static function delete_syllab_option($option) {
		delete_option($option);
	}

	/**
	 * Register the SyllabPlus admin menu entry
	 */
	public static function add_admin_pages() {
		global $syllabplus_admin;
		add_submenu_page('options-general.php', 'Syllab','SylLab', apply_filters('option_page_capability_syllab-options-group', 'manage_options'), 'syllabplus', array($syllabplus_admin, 'settings_output'));
	}

	public static function options_form_begin($settings_fields = 'syllab-options-group', $allow_autocomplete = true, $get_params = array(), $classes = '') {
		global $pagenow;
		echo '<form method="post"';
		
		if ('' != $classes) echo ' class="'.esc_html($classes).'"';
		
		$page = '';
		if ('options-general.php' == $pagenow) $page = "options.php";

		if (!empty($get_params)) {
			$page .= '?';
			$first_one = true;
			foreach ($get_params as $k => $v) {
				if ($first_one) {
					$first_one = false;
				} else {
					$page .= '&';
				}
				$page .= urlencode($k).'='.urlencode($v);
			}
		}

		if ($page) echo ' action="'.esc_html($page).'"';

		if (!$allow_autocomplete) echo ' autocomplete="off"';
		echo '>';
		if ($settings_fields) {
			// This is settings_fields('syllab-options-group'), but with the referer pruned
			echo "<input type='hidden' name='option_page' value='" . esc_attr('syllab-options-group') . "' />";
			echo '<input type="hidden" name="action" value="update" />';
			wp_nonce_field("syllab-options-group-options", '_wpnonce', false);

			$remove_query_args = array('state', 'action', 'oauth_verifier');
			
			$referer = SyllabPlus_Manipulation_Functions::wp_unslash(remove_query_arg($remove_query_args, $_SERVER['REQUEST_URI']));

			// Add back the page parameter if it looks like we were on the settings page via an OAuth callback that has now had all parameters removed. This is likely unnecessarily conservative, but there's nothing requiring more than this at the current time.
			if (substr($referer, -19, 19) == 'options-general.php' && false !== strpos($_SERVER['REQUEST_URI'], '?')) $referer .= '?page=syllabplus';

			$referer_field = '<input type="hidden" name="_wp_http_referer" value="'. esc_attr($referer) . '" />';
			echo $referer_field;
		}
	}

	/**
	 * Runs upon the WordPress action admin_init
	 */
	public static function admin_init() {

		static $already_inited = false;
		if ($already_inited) return;
		
		$already_inited = true;
	
		// If being called outside of the admin context, this may not be loaded yet
		if (!function_exists('register_setting')) include_once(ABSPATH.'wp-admin/includes/plugin.php');
	
		global $syllabplus, $syllabplus_admin;
		register_setting('syllab-options-group', 'syllab_interval', array($syllabplus, 'schedule_backup'));
		register_setting('syllab-options-group', 'syllab_interval_database', array($syllabplus, 'schedule_backup_database'));
		register_setting('syllab-options-group', 'syllab_interval_increments', array($syllabplus, 'schedule_backup_increments'));
		register_setting('syllab-options-group', 'syllab_retain', array('SyllabPlus_Manipulation_Functions', 'retain_range'));
		register_setting('syllab-options-group', 'syllab_retain_db', array('SyllabPlus_Manipulation_Functions', 'retain_range'));
		register_setting('syllab-options-group', 'syllab_retain_extrarules');

		register_setting('syllab-options-group', 'syllab_encryptionphrase');
		register_setting('syllab-options-group', 'syllab_service', array($syllabplus, 'just_one'));

		$services_to_register = array_keys($syllabplus->backup_methods);
		foreach ($services_to_register as $service) {
			register_setting('syllab-options-group', 'syllab_'.$service);
			// We have to add the filter manually in order to get the second parameter passed through (register_setting() only registers with one parameter)
			add_filter('sanitize_option_syllab_'.$service, array($syllabplus, 'storage_options_filter'), 10, 2);
		}
		
		register_setting('syllab-options-group', 'syllab_auto_updates', 'absint');
		register_setting('syllab-options-group', 'syllab_ssl_nossl', 'absint');
		register_setting('syllab-options-group', 'syllab_log_syslog', 'absint');
		register_setting('syllab-options-group', 'syllab_ssl_useservercerts', 'absint');
		register_setting('syllab-options-group', 'syllab_ssl_disableverify', 'absint');

		register_setting('syllab-options-group', 'syllab_split_every', array($syllabplus_admin, 'optionfilter_split_every'));

		register_setting('syllab-options-group', 'syllab_dir', array('SyllabPlus_Manipulation_Functions', 'prune_syllab_dir_prefix'));

		register_setting('syllab-options-group', 'syllab_report_warningsonly', array($syllabplus_admin, 'return_array'));
		register_setting('syllab-options-group', 'syllab_report_wholebackup', array($syllabplus_admin, 'return_array'));
		register_setting('syllab-options-group', 'syllab_report_dbbackup', array($syllabplus_admin, 'return_array'));

		register_setting('syllab-options-group', 'syllab_autobackup_default', 'absint');
		register_setting('syllab-options-group', 'syllab_delete_local', 'absint');
		register_setting('syllab-options-group', 'syllab_debug_mode', 'absint');
		register_setting('syllab-options-group', 'syllab_extradbs');
		register_setting('syllab-options-group', 'syllab_backupdb_nonwp', 'absint');

		register_setting('syllab-options-group', 'syllab_include_plugins', 'absint');
		register_setting('syllab-options-group', 'syllab_include_themes', 'absint');
		register_setting('syllab-options-group', 'syllab_include_uploads', 'absint');
		register_setting('syllab-options-group', 'syllab_include_others', 'absint');
		register_setting('syllab-options-group', 'syllab_include_wpcore', 'absint');
		register_setting('syllab-options-group', 'syllab_include_wpcore_exclude', array('SyllabPlus_Manipulation_Functions', 'strip_dirslash'));
		register_setting('syllab-options-group', 'syllab_include_more', 'absint');
		register_setting('syllab-options-group', 'syllab_include_more_path', array('SyllabPlus_Manipulation_Functions', 'remove_empties'));
		register_setting('syllab-options-group', 'syllab_include_uploads_exclude', array('SyllabPlus_Manipulation_Functions', 'strip_dirslash'));
		register_setting('syllab-options-group', 'syllab_include_others_exclude', array('SyllabPlus_Manipulation_Functions', 'strip_dirslash'));

		register_setting('syllab-options-group', 'syllab_starttime_files', array('SyllabPlus_Options', 'hourminute'));
		register_setting('syllab-options-group', 'syllab_starttime_db', array('SyllabPlus_Options', 'hourminute'));

		register_setting('syllab-options-group', 'syllab_startday_files', array('SyllabPlus_Options', 'week_or_month_day'));
		register_setting('syllab-options-group', 'syllab_startday_db', array('SyllabPlus_Options', 'week_or_month_day'));

		global $pagenow;
		if (is_multisite() && 'options-general.php' == $pagenow && isset($_REQUEST['page']) && 'syllabplus' == substr($_REQUEST['page'], 0, 11)) {
			add_action('all_admin_notices', array('SyllabPlus_Options', 'show_admin_warning_multisite'));
		}
	}

	public static function hourminute($pot) {
		if (preg_match("/^([0-2]?[0-9]):([0-5][0-9])$/", $pot, $matches)) return sprintf("%02d:%s", $matches[1], $matches[2]);
		if ('' == $pot) return date('H:i', time()+300);
		return '00:00';
	}

	public static function week_or_month_day($pot) {
		$pot = absint($pot);
		return ($pot>28) ? 1 : $pot;
	}

	/**
	 * Output information about the multisite add-on when relevant
	 */
	public static function show_admin_warning_multisite() {
		global $syllabplus_admin;
		$syllabplus_admin->show_admin_warning('<strong>'.__('SyllabPlus warning:', 'syllabplus').'</strong> '.__('This is a WordPress multi-site (a.k.a. network) installation.', 'syllabplus').' <a href="https://syllabplus.com/shop/" target="_blank">'.__('WordPress Multisite is supported, with extra features, by SyllabPlus Premium, or the Multisite add-on.', 'syllabplus').'</a> '.__('Without upgrading, SyllabPlus allows <strong>every</strong> blog admin who can modify plugin settings to backup (and hence access the data, including passwords, from) and restore (including with customized modifications, e.g. changed passwords) <strong>the entire network</strong>.', 'syllabplus').' '.__('(This applies to all WordPress backup plugins unless they have been explicitly coded for multisite compatibility).', 'syllabplus'), 'error');
	}
}

add_action('admin_init', array('SyllabPlus_Options', 'admin_init'));
add_action('admin_menu', array('SyllabPlus_Options', 'add_admin_pages'));
