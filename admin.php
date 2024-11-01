<?php

/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

// Admin-area code lives here. This gets called in admin_menu, earlier than admin_init

global $syllabplus_admin;
if (!is_a($syllabplus_admin, 'SyllabPlus_Admin')) $syllabplus_admin = new SyllabPlus_Admin();

class SyllabPlus_Admin {

	public $logged = array();

	private $template_directories;

	private $backups_instance_ids;

	private $auth_instance_ids = array('dropbox' => array(), 'onedrive' => array(), 'googledrive' => array(), 'googlecloud' => array());

	private $php_versions = array('5.4', '5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0');

	private $storage_service_without_settings;

	private $storage_service_with_partial_settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->admin_init();
	}
	
	/**
	 * Get the path to the UI templates directory
	 *
	 * @return String - a filesystem directory path
	 */
	public function get_templates_dir() {
		return apply_filters('syllabplus_templates_dir', SyllabPlus_Manipulation_Functions::wp_normalize_path(SYLLABPLUS_DIR.'/templates'));
	}
	
	private function register_template_directories() {

		$template_directories = array();

		$templates_dir = $this->get_templates_dir();

		if ($dh = opendir($templates_dir)) {
			while (($file = readdir($dh)) !== false) {
				if ('.' == $file || '..' == $file) continue;
				if (is_dir($templates_dir.'/'.$file)) {
					$template_directories[$file] = $templates_dir.'/'.$file;
				}
			}
			closedir($dh);
		}

		// This is the optimal hook for most extensions to hook into
		$this->template_directories = apply_filters('syllabplus_template_directories', $template_directories);

	}

	/**
	 * Output, or return, the results of running a template (from the 'templates' directory, unless a filter over-rides it). Templates are run with $syllabplus, $syllabplus_admin and $wpdb set.
	 *
	 * @param String  $path					  - path to the template
	 * @param Boolean $return_instead_of_echo - by default, the template is echo-ed; set this to instead return it
	 * @param Array	  $extract_these		  - variables to inject into the template's run context
	 *
	 * @return Void|String
	 */
	public function include_template($path, $return_instead_of_echo = false, $extract_these = array()) {
		if ($return_instead_of_echo) ob_start();

		if (preg_match('#^([^/]+)/(.*)$#', $path, $matches)) {
			$prefix = $matches[1];
			$suffix = $matches[2];
			if (isset($this->template_directories[$prefix])) {
				$template_file = $this->template_directories[$prefix].'/'.$suffix;
			}
		}

		if (!isset($template_file)) $template_file = SYLLABPLUS_DIR.'/templates/'.$path;

		$template_file = apply_filters('syllabplus_template', $template_file, $path);

		do_action('syllabplus_before_template', $path, $template_file, $return_instead_of_echo, $extract_these);

		if (!file_exists($template_file)) {
			error_log("SyllabPlus: template not found: $template_file");
			echo __('Error:', 'syllabplus').' '.__('template not found', 'syllabplus')." ($path)";
		} else {
			extract($extract_these);
			global $syllabplus, $wpdb;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$syllabplus_admin = $this;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			include $template_file;
		}

		do_action('syllabplus_after_template', $path, $template_file, $return_instead_of_echo, $extract_these);

		if ($return_instead_of_echo) return ob_get_clean();
	}
	
	/**
	 * Add actions for any needed dashboard notices for remote storage services
	 *
	 * @param String|Array $services - a list of services, or single service
	 */
	private function setup_all_admin_notices_global($services) {
		
		global $syllabplus;

		if ('googledrive' === $services || (is_array($services) && in_array('googledrive', $services))) {
			$settings = SyllabPlus_Storage_Methods_Interface::update_remote_storage_options_format('googledrive');
			
			if (is_wp_error($settings)) {
				if (!isset($this->storage_module_option_errors)) $this->storage_module_option_errors = '';
				$this->storage_module_option_errors .= "Google Drive (".$settings->get_error_code()."): ".$settings->get_error_message();
				add_action('all_admin_notices', array($this, 'show_admin_warning_multiple_storage_options'));
				$syllabplus->log_wp_error($settings, true, true);
			} elseif (!empty($settings['settings'])) {
				foreach ($settings['settings'] as $instance_id => $storage_options) {
					if ((defined('SYLLABPLUS_CUSTOM_GOOGLEDRIVE_APP') && SYLLABPLUS_CUSTOM_GOOGLEDRIVE_APP) || !empty($storage_options['clientid'])) {
						if (!empty($storage_options['clientid'])) {
							$clientid = $storage_options['clientid'];
							$token = empty($storage_options['token']) ? '' : $storage_options['token'];
						}
						if (!empty($clientid) && '' == $token) {
							if (!in_array($instance_id, $this->auth_instance_ids['googledrive'])) $this->auth_instance_ids['googledrive'][] = $instance_id;
							if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_googledrive'))) add_action('all_admin_notices', array($this, 'show_admin_warning_googledrive'));
						}
						unset($clientid);
						unset($token);
					} else {
						if (empty($storage_options['user_id'])) {
							if (!in_array($instance_id, $this->auth_instance_ids['googledrive'])) $this->auth_instance_ids['googledrive'][] = $instance_id;
							if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_googledrive'))) add_action('all_admin_notices', array($this, 'show_admin_warning_googledrive'));
						}
					}
				}
			}
		}
		if ('googlecloud' === $services || (is_array($services) && in_array('googlecloud', $services))) {
			$settings = SyllabPlus_Storage_Methods_Interface::update_remote_storage_options_format('googlecloud');
			
			if (is_wp_error($settings)) {
				if (!isset($this->storage_module_option_errors)) $this->storage_module_option_errors = '';
				$this->storage_module_option_errors .= "Google Cloud (".$settings->get_error_code()."): ".$settings->get_error_message();
				add_action('all_admin_notices', array($this, 'show_admin_warning_multiple_storage_options'));
				$syllabplus->log_wp_error($settings, true, true);
			} elseif (!empty($settings['settings'])) {
				foreach ($settings['settings'] as $instance_id => $storage_options) {
					$clientid = $storage_options['clientid'];
					$token = (empty($storage_options['token'])) ? '' : $storage_options['token'];
					
					if (!empty($clientid) && empty($token)) {
						if (!in_array($instance_id, $this->auth_instance_ids['googlecloud'])) $this->auth_instance_ids['googlecloud'][] = $instance_id;
						if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_googlecloud'))) add_action('all_admin_notices', array($this, 'show_admin_warning_googlecloud'));
					}
				}
			}
		}
		
		if ('dropbox' === $services || (is_array($services) && in_array('dropbox', $services))) {
			$settings = SyllabPlus_Storage_Methods_Interface::update_remote_storage_options_format('dropbox');
			
			if (is_wp_error($settings)) {
				if (!isset($this->storage_module_option_errors)) $this->storage_module_option_errors = '';
				$this->storage_module_option_errors .= "Dropbox (".$settings->get_error_code()."): ".$settings->get_error_message();
				add_action('all_admin_notices', array($this, 'show_admin_warning_multiple_storage_options'));
				$syllabplus->log_wp_error($settings, true, true);
			} elseif (!empty($settings['settings'])) {
				foreach ($settings['settings'] as $instance_id => $storage_options) {
					if (empty($storage_options['tk_access_token'])) {
						if (!in_array($instance_id, $this->auth_instance_ids['dropbox'])) $this->auth_instance_ids['dropbox'][] = $instance_id;
						if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_dropbox'))) add_action('all_admin_notices', array($this, 'show_admin_warning_dropbox'));
					}
				}
			}
		}
		
		if ('onedrive' === $services || (is_array($services) && in_array('onedrive', $services))) {
			$settings = SyllabPlus_Storage_Methods_Interface::update_remote_storage_options_format('onedrive');
			
			if (is_wp_error($settings)) {
				if (!isset($this->storage_module_option_errors)) $this->storage_module_option_errors = '';
				$this->storage_module_option_errors .= "OneDrive (".$settings->get_error_code()."): ".$settings->get_error_message();
				add_action('all_admin_notices', array($this, 'show_admin_warning_multiple_storage_options'));
				$syllabplus->log_wp_error($settings, true, true);
			} elseif (!empty($settings['settings'])) {
				foreach ($settings['settings'] as $instance_id => $storage_options) {
					if ((defined('SYLLABPLUS_CUSTOM_ONEDRIVE_APP') && SYLLABPLUS_CUSTOM_ONEDRIVE_APP)) {
						if (!empty($storage_options['clientid']) && !empty($storage_options['secret']) && empty($storage_options['refresh_token'])) {
								if (!in_array($instance_id, $this->auth_instance_ids['onedrive'])) $this->auth_instance_ids['onedrive'][] = $instance_id;
								if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_onedrive'))) add_action('all_admin_notices', array($this, 'show_admin_warning_onedrive'));
						} elseif (empty($storage_options['refresh_token'])) {
							if (!in_array($instance_id, $this->auth_instance_ids['onedrive'])) $this->auth_instance_ids['onedrive'][] = $instance_id;
							if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_onedrive'))) add_action('all_admin_notices', array($this, 'show_admin_warning_onedrive'));
						}
					} else {
						if (empty($storage_options['refresh_token'])) {
							if (!in_array($instance_id, $this->auth_instance_ids['onedrive'])) $this->auth_instance_ids['onedrive'][] = $instance_id;
							if (false === has_action('all_admin_notices', array($this, 'show_admin_warning_onedrive'))) add_action('all_admin_notices', array($this, 'show_admin_warning_onedrive'));
						}
					}
				}
			}
		}

		if ('syllabvault' === $services || (is_array($services) && in_array('syllabvault', $services))) {
			$settings = SyllabPlus_Storage_Methods_Interface::update_remote_storage_options_format('syllabvault');
			
			if (is_wp_error($settings)) {
				if (!isset($this->storage_module_option_errors)) $this->storage_module_option_errors = '';
				$this->storage_module_option_errors .= "SyllabVault (".$settings->get_error_code()."): ".$settings->get_error_message();
				add_action('all_admin_notices', array($this, 'show_admin_warning_multiple_storage_options'));
				$syllabplus->log_wp_error($settings, true, true);
			} elseif (!empty($settings['settings'])) {
				foreach ($settings['settings'] as $instance_id => $storage_options) {
					if (empty($storage_options['token']) && empty($storage_options['email'])) {
						add_action('all_admin_notices', array($this, 'show_admin_warning_syllabvault'));
					}
				}
			}
		}

		if ($this->disk_space_check(1048576*35) === false) add_action('all_admin_notices', array($this, 'show_admin_warning_diskspace'));

		$all_services = SyllabPlus_Storage_Methods_Interface::get_enabled_storage_objects_and_ids($syllabplus->get_canonical_service_list());
		
		$this->storage_service_without_settings = array();
		$this->storage_service_with_partial_settings = array();
		
		foreach ($all_services as $method => $sinfo) {
			if (empty($sinfo['object']) || empty($sinfo['instance_settings']) || !is_callable(array($sinfo['object'], 'options_exist'))) continue;
			foreach ($sinfo['instance_settings'] as $opt) {
				if (!$sinfo['object']->options_exist($opt)) {
					if (isset($opt['auth_in_progress'])) {
						$this->storage_service_with_partial_settings[$method] = $syllabplus->backup_methods[$method];
					} else {
						$this->storage_service_without_settings[] = $syllabplus->backup_methods[$method];
					}
				}
			}
		}
		
		if (!empty($this->storage_service_with_partial_settings)) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_if_remote_storage_with_partial_setttings'));
		}

		if (!empty($this->storage_service_without_settings)) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_if_remote_storage_settting_are_empty'));
		}

		if ($syllabplus->is_restricted_hosting('only_one_backup_per_month')) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_one_backup_per_month'));
		}
	}
	
	private function setup_all_admin_notices_udonly($service, $override = false) {// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Filter use
		global $syllabplus;

		if (SyllabPlus_Options::get_syllab_option('syllab_debug_mode')) {
			@ini_set('display_errors', 1);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			if (defined('E_DEPRECATED')) {
				@error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, PHPCompatibility.Constants.NewConstants.e_deprecatedFound --  Ok to ignore
			} else {
				@error_reporting(E_ALL & ~E_NOTICE);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
			add_action('all_admin_notices', array($this, 'show_admin_debug_warning'));
		}

		if (null === SyllabPlus_Options::get_syllab_option('syllab_interval')) {
			add_action('all_admin_notices', array($this, 'show_admin_nosettings_warning'));
			$this->no_settings_warning = true;
		}

		// Avoid false positives, by attempting to raise the limit (as happens when we actually do a backup)
		if (function_exists('set_time_limit')) @set_time_limit(SYLLABPLUS_SET_TIME_LIMIT);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		$max_execution_time = (int) @ini_get('max_execution_time');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ($max_execution_time>0 && $max_execution_time<20) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_execution_time'));
		}

		// LiteSpeed has a generic problem with terminating cron jobs
		if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false) {
			if (!is_file(ABSPATH.'.htaccess') || !preg_match('/noabort/i', file_get_contents(ABSPATH.'.htaccess'))) {
				add_action('all_admin_notices', array($this, 'show_admin_warning_litespeed'));
			}
		}

		if (version_compare($syllabplus->get_wordpress_version(), '3.2', '<')) add_action('all_admin_notices', array($this, 'show_admin_warning_wordpressversion'));
		
		// DreamObjects west cluster shutdown warning
		if ('dreamobjects' === $service || (is_array($service) && in_array('dreamobjects', $service))) {
			$settings = SyllabPlus_Storage_Methods_Interface::update_remote_storage_options_format('dreamobjects');
			
			if (is_wp_error($settings)) {
				if (!isset($this->storage_module_option_errors)) $this->storage_module_option_errors = '';
				$this->storage_module_option_errors .= "DreamObjects (".$settings->get_error_code()."): ".$settings->get_error_message();
				add_action('all_admin_notices', array($this, 'show_admin_warning_multiple_storage_options'));
				$syllabplus->log_wp_error($settings, true, true);
			} elseif (!empty($settings['settings'])) {
				foreach ($settings['settings'] as $storage_options) {
					if ('objects-us-west-1.dream.io' == $storage_options['endpoint']) {
						add_action('all_admin_notices', array($this, 'show_admin_warning_dreamobjects'));
					}
				}
			}
		}
		
		// If the plugin was not able to connect to a UDC account due to lack of licences
		if (isset($_GET['udc_connect']) && 0 == $_GET['udc_connect']) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_udc_couldnt_connect'));
		}
	}
	
	/**
	 * Used to output the information for the next scheduled backup.
	 * moved to function for the ajax saves
	 */
	public function next_scheduled_backups_output() {
		// UNIX timestamp
		$next_scheduled_backup = wp_next_scheduled('syllab_backup');
		if ($next_scheduled_backup) {
			// Convert to GMT
			$next_scheduled_backup_gmt = gmdate('Y-m-d H:i:s', $next_scheduled_backup);
			// Convert to blog time zone
			$next_scheduled_backup = get_date_from_gmt($next_scheduled_backup_gmt, 'D, F j, Y H:i');
			// $next_scheduled_backup = date_i18n('D, F j, Y H:i', $next_scheduled_backup);
		} else {
			$next_scheduled_backup = __('Nothing currently scheduled', 'syllabplus');
			$files_not_scheduled = true;
		}
		
		$next_scheduled_backup_database = wp_next_scheduled('syllab_backup_database');
		if (SyllabPlus_Options::get_syllab_option('syllab_interval_database', SyllabPlus_Options::get_syllab_option('syllab_interval')) == SyllabPlus_Options::get_syllab_option('syllab_interval')) {
			if (isset($files_not_scheduled)) {
				$next_scheduled_backup_database = $next_scheduled_backup;
				$database_not_scheduled = true;
			} else {
				$next_scheduled_backup_database = __("At the same time as the files backup", 'syllabplus');
				$next_scheduled_backup_database_same_time = true;
			}
		} else {
			if ($next_scheduled_backup_database) {
				// Convert to GMT
				$next_scheduled_backup_database_gmt = gmdate('Y-m-d H:i:s', $next_scheduled_backup_database);
				// Convert to blog time zone
				$next_scheduled_backup_database = get_date_from_gmt($next_scheduled_backup_database_gmt, 'D, F j, Y H:i');
				// $next_scheduled_backup_database = date_i18n('D, F j, Y H:i', $next_scheduled_backup_database);
			} else {
				$next_scheduled_backup_database = __('Nothing currently scheduled', 'syllabplus');
				$database_not_scheduled = true;
			}
		}
		
		if (isset($files_not_scheduled) && isset($database_not_scheduled)) {
		?>
			<span class="not-scheduled"><?php _e('Nothing currently scheduled', 'syllabplus'); ?></span>
		<?php
		} else {
			echo empty($next_scheduled_backup_database_same_time) ? __('Files', 'syllabplus') : __('Files and database', 'syllabplus');
			?>
			: 
			<span class="syllab_all-files">
				<?php
					echo esc_html($next_scheduled_backup);
				?>
			</span>
			<?php
			if (empty($next_scheduled_backup_database_same_time)) {
				_e('Database', 'syllabplus');
			?>
			: 
			<span class="syllab_all-files">
				<?php
				echo esc_html($next_scheduled_backup_database);
				?>
			</span>
			<?php
			}
		}
		
	}
	
	/**
	 * Used to output the information for the next scheduled  file backup.
	 * moved to function for the ajax saves
	 *
	 * @param Boolean $return_instead_of_echo Whether to return or echo the results. N.B. More than just the results to echo will be returned
	 * @return Void|String If $return_instead_of_echo parameter is true, It returns html string
	 */
	public function next_scheduled_files_backups_output($return_instead_of_echo = false) {
		if ($return_instead_of_echo) ob_start();
		// UNIX timestamp
		$next_scheduled_backup = wp_next_scheduled('syllab_backup');
		if ($next_scheduled_backup) {
			// Convert to blog time zone. wp_date() (WP 5.3+) also performs locale translation.
			$next_scheduled_backup = function_exists('wp_date') ? wp_date('D, F j, Y H:i', $next_scheduled_backup) : get_date_from_gmt(gmdate('Y-m-d H:i:s', $next_scheduled_backup), 'D, F j, Y H:i');
			$files_not_scheduled = false;
		} else {
			$next_scheduled_backup = __('Nothing currently scheduled', 'syllabplus');
			$files_not_scheduled = true;
		}
		
		if ($files_not_scheduled) {
			echo '<span>'.esc_html($next_scheduled_backup).'</span>';
		} else {
			echo '<span class="syllab_next_scheduled_date_time">'.esc_html($next_scheduled_backup).'</span>';
		}
		
		if ($return_instead_of_echo) return ob_get_clean();
	}
	
	/**
	 * Used to output the information for the next scheduled database backup.
	 * moved to function for the ajax saves
	 *
	 * @param Boolean $return_instead_of_echo Whether to return or echo the results. N.B. More than just the results to echo will be returned
	 * @return Void|String If $return_instead_of_echo parameter is true, It returns html string
	 */
	public function next_scheduled_database_backups_output($return_instead_of_echo = false) {
		if ($return_instead_of_echo) ob_start();
		
		$next_scheduled_backup_database = wp_next_scheduled('syllab_backup_database');
		if ($next_scheduled_backup_database) {
			// Convert to GMT
			$next_scheduled_backup_database_gmt = gmdate('Y-m-d H:i:s', $next_scheduled_backup_database);
			// Convert to blog time zone. wp_date() (WP 5.3+) also performs locale translation.
			$next_scheduled_backup_database = function_exists('wp_date') ? wp_date('D, F j, Y H:i', $next_scheduled_backup_database) : get_date_from_gmt($next_scheduled_backup_database_gmt, 'D, F j, Y H:i');
			$database_not_scheduled = false;
		} else {
			$next_scheduled_backup_database = __('Nothing currently scheduled', 'syllabplus');
			$database_not_scheduled = true;
		}
		
		if ($database_not_scheduled) {
			echo '<span>'.esc_html($next_scheduled_backup_database).'</span>';
		} else {
			echo '<span class="syllab_next_scheduled_date_time">'.esc_html($next_scheduled_backup_database).'</span>';
		}
		
		if ($return_instead_of_echo) return ob_get_clean();
	}
	
	/**
	 * Run upon the WP admin_init action
	 */
	private function admin_init() {

		add_action('admin_init', array($this, 'maybe_download_backup_from_email'));

		add_action('core_upgrade_preamble', array($this, 'core_upgrade_preamble'));
		add_action('admin_action_upgrade-plugin', array($this, 'admin_action_upgrade_pluginortheme'));
		add_action('admin_action_upgrade-theme', array($this, 'admin_action_upgrade_pluginortheme'));

		add_action('admin_head', array($this, 'admin_head'));
		add_filter((is_multisite() ? 'network_admin_' : '').'plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
		add_action('wp_ajax_syllab_download_backup', array($this, 'syllab_download_backup'));
		add_action('wp_ajax_syllab_ajax', array($this, 'syllab_ajax_handler'));
		add_action('wp_ajax_syllab_ajaxrestore', array($this, 'syllab_ajaxrestore'));
		add_action('wp_ajax_nopriv_syllab_ajaxrestore', array($this, 'syllab_ajaxrestore'));
		add_action('wp_ajax_syllab_ajaxrestore_continue', array($this, 'syllab_ajaxrestore'));
		add_action('wp_ajax_nopriv_syllab_ajaxrestore_continue', array($this, 'syllab_ajaxrestore'));
		
		add_action('wp_ajax_plupload_action', array($this, 'plupload_action'));
		add_action('wp_ajax_plupload_action2', array($this, 'plupload_action2'));

		add_action('wp_before_admin_bar_render', array($this, 'wp_before_admin_bar_render'));

		// Add a new Ajax action for saving settings
		add_action('wp_ajax_syllab_savesettings', array($this, 'syllab_ajax_savesettings'));
		
		// Ajax for settings import and export
		add_action('wp_ajax_syllab_importsettings', array($this, 'syllab_ajax_importsettings'));

		add_filter('heartbeat_received', array($this, 'process_status_in_heartbeat'), 10, 2);

		// SyllabPlus templates
		$this->register_template_directories();
		
		global $syllabplus, $pagenow;
		add_filter('syllabplus_dirlist_others', array($syllabplus, 'backup_others_dirlist'));
		add_filter('syllabplus_dirlist_uploads', array($syllabplus, 'backup_uploads_dirlist'));

		// First, the checks that are on all (admin) pages:

		$service = SyllabPlus_Options::get_syllab_option('syllab_service');

		if (SyllabPlus_Options::user_can_manage()) {

			$this->print_restore_in_progress_box_if_needed();

			// Main dashboard page advert
			// Since our nonce is printed, make sure they have sufficient credentials
			if ('index.php' == $pagenow && current_user_can('update_plugins') && (!file_exists(SYLLABPLUS_DIR.'/udaddons') || (defined('SYLLABPLUS_FORCE_DASHNOTICE') && SYLLABPLUS_FORCE_DASHNOTICE))) {

				$dismissed_until = SyllabPlus_Options::get_syllab_option('syllabplus_dismisseddashnotice', 0);
				
				$backup_dir = $syllabplus->backups_dir_location();
				// N.B. Not an exact proxy for the installed time; they may have tweaked the expert option to move the directory
				$installed = @filemtime($backup_dir.'/index.html');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				$installed_for = time() - $installed;

				if (($installed && time() > $dismissed_until && $installed_for > 28*86400 && !defined('SYLLABPLUS_NOADS_B')) || (defined('SYLLABPLUS_FORCE_DASHNOTICE') && SYLLABPLUS_FORCE_DASHNOTICE)) {
					add_action('all_admin_notices', array($this, 'show_admin_notice_upgradead'));
				}
			}
			
			// Moved out for use with Ajax saving
			$this->setup_all_admin_notices_global($service);
		}
		
		if (!class_exists('Syllab_Dashboard_News')) include_once(SYLLABPLUS_DIR.'/includes/class-syllab-dashboard-news.php');

		$news_translations = array(
			'product_title' => 'SyllabPlus',
			'item_prefix' => __('SyllabPlus', 'syllabplus'),
			'item_description' => __('SyllabPlus News', 'syllabplus'),
			'dismiss_tooltip' => __('Dismiss all SyllabPlus news', 'syllabplus'),
			'dismiss_confirm' => __('Are you sure you want to dismiss all SyllabPlus news forever?', 'syllabplus'),
		);
		
		add_filter('woocommerce_in_plugin_update_message', array($this, 'woocommerce_in_plugin_update_message'));
		
		new Syllab_Dashboard_News('https://feeds.feedburner.com/syllabplus/', 'https://syllabplus.com/news/', $news_translations);

		// New-install admin tour
		if ((!defined('SYLLABPLUS_ENABLE_TOUR') || SYLLABPLUS_ENABLE_TOUR) && (!defined('SYLLABPLUS_THIS_IS_CLONE') || !SYLLABPLUS_THIS_IS_CLONE)) {
			include_once(SYLLABPLUS_DIR.'/includes/syllabplus-tour.php');
		}

		if ('index.php' == $GLOBALS['pagenow'] && SyllabPlus_Options::user_can_manage()) {
			add_action('admin_print_footer_scripts', array($this, 'admin_index_print_footer_scripts'));
		}
		
		// Next, the actions that only come on the SyllabPlus page
		if (SyllabPlus_Options::admin_page() != $pagenow || empty($_REQUEST['page']) || 'syllabplus' != $_REQUEST['page']) return;
		$this->setup_all_admin_notices_udonly($service);

		global $syllabplus_checkout_embed;
		if (!class_exists('Syllab_Checkout_Embed')) include_once SYLLABPLUS_DIR.'/includes/checkout-embed/class-slp-checkout-embed.php';

		// Create an empty list (usefull for testing, thanks to the filter bellow)
		$checkout_embed_products = array();

		// get products from JSON file.
		$checkout_embed_product_file = SYLLABPLUS_DIR.'/includes/checkout-embed/products.json';
		if (file_exists($checkout_embed_product_file)) {
			$checkout_embed_products = json_decode(file_get_contents($checkout_embed_product_file));
		}

		$checkout_embed_products = apply_filters('syllabplus_checkout_embed_products', $checkout_embed_products);

		if (!empty($checkout_embed_products)) {
			$syllabplus_checkout_embed = new Syllab_Checkout_Embed(
				'syllabplus',                                              // plugin name
				SyllabPlus_Options::admin_page_url().'?page=syllabplus', 	// return url
				$checkout_embed_products,                                   // products list
				SYLLABPLUS_URL.'/includes'                                 // base_url
			);
		}

		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 99999);

		$slp_saved_version = SyllabPlus_Options::get_syllab_option('syllabplus_version');
		if (!$slp_saved_version || $slp_saved_version != $syllabplus->version) {
			if (!$slp_saved_version) {
				// slp was newly installed, or upgraded from an older version
				do_action('syllabplus_newly_installed', $syllabplus->version);
			} else {
				// slp was updated or downgraded
				do_action('syllabplus_version_changed', SyllabPlus_Options::get_syllab_option('syllabplus_version'), $syllabplus->version);
			}
			SyllabPlus_Options::update_syllab_option('syllabplus_version', $syllabplus->version);
		}

		if (isset($_POST['action']) && 'syllab_wipesettings' == $_POST['action'] && isset($_POST['nonce']) && SyllabPlus_Options::user_can_manage()) {
			if (wp_verify_nonce($_POST['nonce'], 'syllabplus-wipe-setting-nonce')) $this->wipe_settings();
		}
	}

	/**
	 * Runs upon the WP action woocommerce_in_plugin_update_message
	 *
	 * @param String $msg - the message that WooCommerce will print
	 *
	 * @return String - filtered value
	 */
	public function woocommerce_in_plugin_update_message($msg) {
		if (time() < SyllabPlus_Options::get_syllab_option('dismissed_clone_wc_notices_until', 0)) return $msg;
		return '<div class="syllab-ad-container"><br><strong>'.__('You can test upgrading your site on an instant copy using SyllabClone credits', 'syllabplus').' - <a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus&amp;tab=migrate#syllab-navtab-migrate-content">'.__('go here to learn more', 'syllabplus').'</a></strong><a href="#" onclick="jQuery(\'.syllab-ad-container\').slideUp(); jQuery.post(ajaxurl, {action: \'syllab_ajax\', subaction: \'dismiss_clone_wc_notice\', nonce: \''. wp_create_nonce('syllabplus-credentialtest-nonce') .'\' });return false;"> - '. __('dismiss notice', 'syllabplus') .'</a></div>'.$msg;
	}

	/**
	 * Runs upon the WP action admin_print_footer_scripts if an entitled user is on the main WP dashboard page
	 */
	public function admin_index_print_footer_scripts() {
		if (time() < SyllabPlus_Options::get_syllab_option('dismissed_clone_php_notices_until', 0)) return;
		?>
		<script>
			jQuery(function($) {
				if ($('#dashboard-widgets #dashboard_php_nag').length < 1) return;
				$('#dashboard-widgets #dashboard_php_nag .button-container').before('<div class="syllab-ad-container"><a href="<?php echo SyllabPlus_Options::admin_page_url(); ?>?page=syllabplus&amp;tab=migrate#syllab-navtab-migrate-content"><?php echo esc_js(__('You can test running your site on a different PHP (or WordPress) version using SyllabClone credits.', 'syllabplus')); ?></a> (<a href="#" onclick="jQuery(\'.syllab-ad-container\').slideUp(); jQuery.post(ajaxurl, {action: \'syllab_ajax\', subaction: \'dismiss_clone_php_notice\', nonce: \'<?php echo wp_create_nonce('syllabplus-credentialtest-nonce'); ?>\' });return false;"><?php echo esc_js(__('Dismiss notice', 'syllabplus')); ?></a>)</div>');
			});
		</script>
		<?php
	}
	
	/**
	 * Sets up what is needed to allow an in-page backup to be run. Will enqueue scripts and output appropriate HTML (so, should be run when at a suitable place). Not intended for use on the SyllabPlus settings page.
	 *
	 * @param string   $title    Text to use for the title of the modal
	 * @param callable $callback Callable function to output the contents of the syllab_inpage_prebackup element - i.e. what shows in the modal before a backup begins.
	 */
	public function add_backup_scaffolding($title, $callback) {
		$this->admin_enqueue_scripts();
		?>
		<script>
		// TODO: This is not the best way.
		var syllab_credentialtest_nonce='<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>';
		</script>
		<div id="syllab-poplog" >
			<pre id="syllab-poplog-content" style="white-space: pre-wrap;"></pre>
		</div>
		
		<div id="syllab-backupnow-inpage-modal" title="SyllabPlus - <?php echo $title; ?>">

			<div id="syllab_inpage_prebackup" style="float:left; clear:both;">
				<?php call_user_func($callback); ?>
			</div>

			<div id="syllab_inpage_backup">

				<h2><?php echo esc_html($title);?></h2>

				<div id="syllab_backup_started" class="updated" style="display:none; max-width: 560px; font-size:100%; line-height: 100%; padding:6px; clear:left;"></div>

				<?php $this->render_active_jobs_and_log_table(true, false); ?>

			</div>

		</div>
		<?php
	}
	
	/**
	 * Called via the ajax_restore actions to prepare the restore over AJAX
	 *
	 * @return void
	 */
	public function syllab_ajaxrestore() {
		$this->prepare_restore();
		die();
	}

	/**
	 * Runs upon the WP action wp_before_admin_bar_render
	 */
	public function wp_before_admin_bar_render() {
		global $wp_admin_bar;
		
		if (!SyllabPlus_Options::user_can_manage()) return;
		
		if (defined('SYLLABPLUS_ADMINBAR_DISABLE') && SYLLABPLUS_ADMINBAR_DISABLE) return;

		if (false == apply_filters('syllabplus_settings_page_render', true)) return;

		$option_location = SyllabPlus_Options::admin_page_url();
		
		// $args = array(
		// 	'id' => 'syllab_admin_node',
		// 	'title' => apply_filters('syllabplus_admin_node_title', 'SyllabPlus')
		// );
		//$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'syllab_admin_node_status',
			'title' => str_ireplace('Back Up', 'Backup', __('Backup', 'syllabplus')).' / '.__('Restore', 'syllabplus'),
			'parent' => 'syllab_admin_node',
			'href' => $option_location.'?page=syllabplus&tab=backups'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'syllab_admin_node_migrate',
			'title' => __('Migrate / Clone', 'syllabplus'),
			'parent' => 'syllab_admin_node',
			'href' => $option_location.'?page=syllabplus&tab=migrate'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'syllab_admin_node_settings',
			'title' => __('Settings', 'syllabplus'),
			'parent' => 'syllab_admin_node',
			'href' => $option_location.'?page=syllabplus&tab=settings'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'syllab_admin_node_expert_content',
			'title' => __('Advanced Tools', 'syllabplus'),
			'parent' => 'syllab_admin_node',
			'href' => $option_location.'?page=syllabplus&tab=expert'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'syllab_admin_node_addons',
			'title' => __('Extensions', 'syllabplus'),
			'parent' => 'syllab_admin_node',
			'href' => $option_location.'?page=syllabplus&tab=addons'
		);
		$wp_admin_bar->add_node($args);
		
		global $syllabplus;
		if (!$syllabplus->have_addons) {
			$args = array(
				'id' => 'syllab_admin_node_premium',
				'title' => 'SyllabPlus Premium',
				'parent' => 'syllab_admin_node',
				'href' => apply_filters('syllabplus_com_link', 'https://syllabplus.com/shop/syllabplus-premium/')
			);
			$wp_admin_bar->add_node($args);
		}
	}

	/**
	 * Output HTML for a dashboard notice highlighting the benefits of upgrading to Premium
	 */
	public function show_admin_notice_upgradead() {
		$this->include_template('wp-admin/notices/thanks-for-using-main-dash.php');
	}

	/**
	 * Enqueue sufficient versions of jQuery and our own scripts
	 */
	private function ensure_sufficient_jquery_and_enqueue() {
		global $syllabplus;
		
		$enqueue_version = $syllabplus->use_unminified_scripts() ? $syllabplus->version.'.'.time() : $syllabplus->version;
		$min_or_not = $syllabplus->use_unminified_scripts() ? '' : '.min';
		$syllab_min_or_not = $syllabplus->get_syllabplus_file_version();

		
		if (version_compare($syllabplus->get_wordpress_version(), '3.3', '<')) {
			// Require a newer jQuery (3.2.1 has 1.6.1, so we go for something not too much newer). We use .on() in a way that is incompatible with < 1.7
			wp_deregister_script('jquery');
			$jquery_enqueue_version = $syllabplus->use_unminified_scripts() ? '1.7.2'.'.'.time() : '1.7.2';
			wp_enqueue_script('jquery');
			// No plupload until 3.3
			wp_enqueue_script('syllab-admin-common', SYLLABPLUS_URL.'/includes/syllab-admin-common'.$syllab_min_or_not.'.js', array('jquery', 'jquery-ui-dialog', 'jquery-ui-core', 'jquery-ui-accordion'), $enqueue_version, true);
		} else {
			wp_enqueue_script('syllab-admin-common', SYLLABPLUS_URL.'/includes/syllab-admin-common'.$syllab_min_or_not.'.js', array('jquery', 'jquery-ui-dialog', 'jquery-ui-core', 'jquery-ui-accordion', 'plupload-all'), $enqueue_version);
		}
		
	}

	/**
	 * This is also called directly from the auto-backup add-on
	 */
	public function admin_enqueue_scripts() {

		global $syllabplus, $wp_locale, $syllabplus_checkout_embed;
		
		$enqueue_version = $syllabplus->use_unminified_scripts() ? $syllabplus->version.'.'.time() : $syllabplus->version;
		$min_or_not = $syllabplus->use_unminified_scripts() ? '' : '.min';
		$syllab_min_or_not = $syllabplus->get_syllabplus_file_version();

		// Defeat other plugins/themes which dump their jQuery UI CSS onto our settings page
		wp_deregister_style('jquery-ui');
		$jquery_ui_version = version_compare($syllabplus->get_wordpress_version(), '5.6', '>=') ? '1.12.1' : '1.11.4';
		$jquery_ui_css_enqueue_version = $syllabplus->use_unminified_scripts() ? $jquery_ui_version.'.0'.'.'.time() : $jquery_ui_version.'.0';
		wp_enqueue_style('jquery-ui', SYLLABPLUS_URL."/includes/jquery-ui.custom-v$jquery_ui_version$syllab_min_or_not.css", array(), $jquery_ui_css_enqueue_version);
	
		wp_enqueue_style('syllab-admin-css', SYLLABPLUS_URL.'/css/syllabplus-admin'.$syllab_min_or_not.'.css', array(), $enqueue_version);
		// add_filter('style_loader_tag', array($this, 'style_loader_tag'), 10, 2);

		$this->ensure_sufficient_jquery_and_enqueue();
		$jquery_blockui_enqueue_version = $syllabplus->use_unminified_scripts() ? '2.71.0'.'.'.time() : '2.71.0';
		wp_enqueue_script('jquery-blockui', SYLLABPLUS_URL.'/includes/blockui/jquery.blockUI'.$syllab_min_or_not.'.js', array('jquery'), $jquery_blockui_enqueue_version);
	
		wp_enqueue_script('jquery-labelauty', SYLLABPLUS_URL.'/includes/labelauty/jquery-labelauty'.$syllab_min_or_not.'.js', array('jquery'), $enqueue_version);
		wp_enqueue_style('jquery-labelauty', SYLLABPLUS_URL.'/includes/labelauty/jquery-labelauty'.$syllab_min_or_not.'.css', array(), $enqueue_version);
		$serialize_js_enqueue_version = $syllabplus->use_unminified_scripts() ? '3.2.0'.'.'.time() : '3.2.0';
		wp_enqueue_script('jquery.serializeJSON', SYLLABPLUS_URL.'/includes/jquery.serializeJSON/jquery.serializejson'.$min_or_not.'.js', array('jquery'), $serialize_js_enqueue_version);
		$handlebars_js_enqueue_version = $syllabplus->use_unminified_scripts() ? '4.1.2'.'.'.time() : '4.1.2';
		wp_enqueue_script('handlebars', SYLLABPLUS_URL.'/includes/handlebars/handlebars'.$min_or_not.'.js', array(), $handlebars_js_enqueue_version);
		$this->enqueue_jstree();

		$jqueryui_dialog_extended_version = $syllabplus->use_unminified_scripts() ? '1.0.4'.'.'.time() : '1.0.4';
		wp_enqueue_script('jquery-ui.dialog.extended', SYLLABPLUS_URL.'/includes/jquery-ui.dialog.extended/jquery-ui.dialog.extended'.$syllab_min_or_not.'.js', array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog'), $jqueryui_dialog_extended_version);
		
		do_action('syllabplus_admin_enqueue_scripts');
		
		$day_selector = '';
		for ($day_index = 0; $day_index <= 6; $day_index++) {
			// $selected = ($opt == $day_index) ? 'selected="selected"' : '';
			$selected = '';
			$day_selector .= "\n\t<option value='" . $day_index . "' $selected>" . $wp_locale->get_weekday($day_index) . '</option>';
		}

		$mday_selector = '';
		for ($mday_index = 1; $mday_index <= 28; $mday_index++) {
			// $selected = ($opt == $mday_index) ? 'selected="selected"' : '';
			$selected = '';
			$mday_selector .= "\n\t<option value='" . $mday_index . "' $selected>" . $mday_index . '</option>';
		}
		$backup_methods = $syllabplus->backup_methods;
		$remote_storage_options_and_templates = SyllabPlus_Storage_Methods_Interface::get_remote_storage_options_and_templates();
		$main_tabs = $this->get_main_tabs_array();

		$checkout_embed_5gb_trial_attribute = '';

		if (is_a($syllabplus_checkout_embed, 'Syllab_Checkout_Embed')) {
			$checkout_embed_5gb_trial_attribute = $syllabplus_checkout_embed->get_product('syllabplus-vault-storage-5-gb') ? 'data-embed-checkout="'.apply_filters('syllabplus_com_link', $syllabplus_checkout_embed->get_product('syllabplus-vault-storage-5-gb', SyllabPlus_Options::admin_page_url().'?page=syllabplus&tab=settings')).'"' : '';
		}

		$hosting_company = $syllabplus->get_hosting_info();

		wp_localize_script('syllab-admin-common', 'syllablion', array(
			'tab' => empty($_GET['tab']) ? 'backups' : sanitize_text_field($_GET['tab']),
			'sendonlyonwarnings' => __('Send a report only when there are warnings/errors', 'syllabplus'),
			'wholebackup' => __('When the Email storage method is enabled, also send the backup', 'syllabplus'),
			'emailsizelimits' => esc_attr(sprintf(__('Be aware that mail servers tend to have size limits; typically around %s Mb; backups larger than any limits will likely not arrive.', 'syllabplus'), '10-20')),
			'rescanning' => __('Rescanning (looking for backups that you have uploaded manually into the internal backup store)...', 'syllabplus'),
			'dbbackup' => __('Only email the database backup', 'syllabplus'),
			'rescanningremote' => __('Rescanning remote and local storage for backup sets...', 'syllabplus'),
			'enteremailhere' => esc_attr(__('To send to more than one address, separate each address with a comma.', 'syllabplus')),
			'excludedeverything' => __('If you exclude both the database and the files, then you have excluded everything!', 'syllabplus'),
			'nofileschosen' => __('You have chosen to backup files, but no file entities have been selected', 'syllabplus'),
			'notableschosen' => __('You have chosen to backup a database, but no tables have been selected', 'syllabplus'),
			'nocloudserviceschosen' => __('You have chosen to send this backup to remote storage, but no remote storage locations have been selected', 'syllabplus'),
			'restore_proceeding' => __('The restore operation has begun. Do not close your browser until it reports itself as having finished.', 'syllabplus'),
			'unexpectedresponse' => __('Unexpected response:', 'syllabplus'),
			'servererrorcode' => __('The web server returned an error code (try again, or check your web server logs)', 'syllabplus'),
			'newuserpass' => __("The new user's RackSpace console password is (this will not be shown again):", 'syllabplus'),
			'trying' => __('Trying...', 'syllabplus'),
			'fetching' => __('Fetching...', 'syllabplus'),
			'calculating' => __('calculating...', 'syllabplus'),
			'begunlooking' => __('Begun looking for this entity', 'syllabplus'),
			'stilldownloading' => __('Some files are still downloading or being processed - please wait.', 'syllabplus'),
			'processing' => __('Processing files - please wait...', 'syllabplus'),
			'emptyresponse' => __('Error: the server sent an empty response.', 'syllabplus'),
			'warnings' => __('Warnings:', 'syllabplus'),
			'errors' => __('Errors:', 'syllabplus'),
			'jsonnotunderstood' => __('Error: the server sent us a response which we did not understand.', 'syllabplus'),
			'errordata' => __('Error data:', 'syllabplus'),
			'error' => __('Error:', 'syllabplus'),
			'errornocolon' => __('Error', 'syllabplus'),
			'existing_backups' => __('Existing backups', 'syllabplus'),
			'fileready' => __('File ready.', 'syllabplus'),
			'actions' => __('Actions', 'syllabplus'),
			'deletefromserver' => __('Delete from your web server', 'syllabplus'),
			'downloadtocomputer' => __('Download to your computer', 'syllabplus'),
			'browse_contents' => __('Browse contents', 'syllabplus'),
			'notunderstood' => __('Download error: the server sent us a response which we did not understand.', 'syllabplus'),
			'requeststart' => __('Requesting start of backup...', 'syllabplus'),
			'phpinfo' => __('PHP information', 'syllabplus'),
			'delete_old_dirs' => __('Delete Old Directories', 'syllabplus'),
			'raw' => __('Raw backup history', 'syllabplus'),
			'notarchive' => __('This file does not appear to be an SyllabPlus backup archive (such files are .zip or .gz files which have a name like: backup_(time)_(site name)_(code)_(type).(zip|gz)).', 'syllabplus').' '.__('However, SyllabPlus archives are standard zip/SQL files - so if you are sure that your file has the right format, then you can rename it to match that pattern.', 'syllabplus'),
			'notarchive2' => '<p>'.__('This file does not appear to be an SyllabPlus backup archive (such files are .zip or .gz files which have a name like: backup_(time)_(site name)_(code)_(type).(zip|gz)).', 'syllabplus').'</p> '.apply_filters('syllabplus_if_foreign_then_premium_message', '<p><a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/syllabplus-premium/").'">'.__('If this is a backup created by a different backup plugin, then SyllabPlus Premium may be able to help you.', 'syllabplus').'</a></p>'),
			'makesure' => __('(make sure that you were trying to upload a zip file previously created by SyllabPlus)', 'syllabplus'),
			'uploaderror' => __('Upload error:', 'syllabplus'),
			'notdba' => __('This file does not appear to be an SyllabPlus encrypted database archive (such files are .gz.crypt files which have a name like: backup_(time)_(site name)_(code)_db.crypt.gz).', 'syllabplus'),
			'uploaderr' => __('Upload error', 'syllabplus'),
			'followlink' => __('Follow this link to attempt decryption and download the database file to your computer.', 'syllabplus'),
			'thiskey' => __('This decryption key will be attempted:', 'syllabplus'),
			'unknownresp' => __('Unknown server response:', 'syllabplus'),
			'ukrespstatus' => __('Unknown server response status:', 'syllabplus'),
			'uploaded' => __('The file was uploaded.', 'syllabplus'),
			// One of the translators has erroneously changed "Backup" into "Back up" (which means, "reverse" !)
			'backupnow' => str_ireplace('Back Up', 'Backup', __('Backup Now', 'syllabplus')),
			'cancel' => __('Cancel', 'syllabplus'),
			'deletebutton' => __('Delete', 'syllabplus'),
			'createbutton' => __('Create', 'syllabplus'),
			'uploadbutton' => __('Upload', 'syllabplus'),
			'youdidnotselectany' => __('You did not select any components to restore. Please select at least one, and then try again.', 'syllabplus'),
			'proceedwithupdate' => __('Proceed with update', 'syllabplus'),
			'close' => __('Close', 'syllabplus'),
			'restore' => __('Restore', 'syllabplus'),
			'downloadlogfile' => __('Download log file', 'syllabplus'),
			'automaticbackupbeforeupdate' => __('Automatic backup before update', 'syllabplus'),
			'unsavedsettings' => __('You have made changes to your settings, and not saved.', 'syllabplus'),
			'saving' => __('Saving...', 'syllabplus'),
			'connect' => __('Connect', 'syllabplus'),
			'connecting' => __('Connecting...', 'syllabplus'),
			'disconnect' => __('Disconnect', 'syllabplus'),
			'disconnecting' => __('Disconnecting...', 'syllabplus'),
			'counting' => __('Counting...', 'syllabplus'),
			'updatequotacount' => __('Update quota count', 'syllabplus'),
			'addingsite' => __('Adding...', 'syllabplus'),
			'addsite' => __('Add site', 'syllabplus'),
			// 'resetting' => __('Resetting...', 'syllabplus'),
			'creating_please_allow' => __('Creating...', 'syllabplus').(function_exists('openssl_encrypt') ? '' : ' ('.__('your PHP install lacks the openssl module; as a result, this can take minutes; if nothing has happened by then, then you should either try a smaller key size, or ask your web hosting company how to enable this PHP module on your setup.', 'syllabplus').')'),
			'sendtosite' => __('Send to site:', 'syllabplus'),
			'checkrpcsetup' => sprintf(__('You should check that the remote site is online, not firewalled, does not have security modules that may be blocking access, has SyllabPlus version %s or later active and that the keys have been entered correctly.', 'syllabplus'), '2.10.3'),
			'pleasenamekey' => __('Please give this key a name (e.g. indicate the site it is for):', 'syllabplus'),
			'key' => __('Key', 'syllabplus'),
			'nokeynamegiven' => sprintf(__("Failure: No %s was given.", 'syllabplus'), __('key name', 'syllabplus')),
			'deleting' => __('Deleting...', 'syllabplus'),
			'enter_mothership_url' => __('Please enter a valid URL', 'syllabplus'),
			'delete_response_not_understood' => __("We requested to delete the file, but could not understand the server's response", 'syllabplus'),
			'testingconnection' => __('Testing connection...', 'syllabplus'),
			'send' => __('Send', 'syllabplus'),
			'migratemodalheight' => class_exists('SyllabPlus_Addons_Migrator') ? 555 : 300,
			'migratemodalwidth' => class_exists('SyllabPlus_Addons_Migrator') ? 770 : 500,
			'download' => _x('Download', '(verb)', 'syllabplus'),
			'browse_download_link' => apply_filters('syllabplus_browse_download_link', '<a id="syllab_zip_download_notice" href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/landing/syllabplus-premium").'" target="_blank">'.__("With SyllabPlus Premium, you can directly download individual files from here.", "syllabplus").'</a>'),
			'unsavedsettingsbackup' => __('You have made changes to your settings, and not saved.', 'syllabplus')."\n".__('You should save your changes to ensure that they are used for making your backup.', 'syllabplus'),
			'unsaved_settings_export' => __('You have made changes to your settings, and not saved.', 'syllabplus')."\n".__('Your export file will be of your displayed settings, not your saved ones.', 'syllabplus'),
			'dayselector' => $day_selector,
			'mdayselector' => $mday_selector,
			'day' => __('day', 'syllabplus'),
			'inthemonth' => __('in the month', 'syllabplus'),
			'days' => __('day(s)', 'syllabplus'),
			'hours' => __('hour(s)', 'syllabplus'),
			'weeks' => __('week(s)', 'syllabplus'),
			'forbackupsolderthan' => __('For backups older than', 'syllabplus'),
			'ud_url' => SYLLABPLUS_URL,
			'processing' => __('Processing...', 'syllabplus'),
			'loading' => __('Loading...', 'syllabplus'),
			'pleasefillinrequired' => __('Please fill in the required information.', 'syllabplus'),
			'test_settings' => __('Test %s Settings', 'syllabplus'),
			'testing_settings' => __('Testing %s Settings...', 'syllabplus'),
			'settings_test_result' => __('%s settings test result:', 'syllabplus'),
			'nothing_yet_logged' => __('Nothing yet logged', 'syllabplus'),
			'import_select_file' => __('You have not yet selected a file to import.', 'syllabplus'),
			'import_invalid_json_file' => __('Error: The chosen file is corrupt. Please choose a valid SyllabPlus export file.', 'syllabplus'),
			'syllab_settings_url' => SyllabPlus_Options::admin_page_url().'?page=syllabplus',
			'network_site_url' => network_site_url(),
			'importing' => __('Importing...', 'syllabplus'),
			'importing_data_from' => __('This will import data from:', 'syllabplus'),
			'exported_on' => __('Which was exported on:', 'syllabplus'),
			'continue_import' => __('Do you want to carry out the import?', 'syllabplus'),
			'complete' => __('Complete', 'syllabplus'),
			'backup_complete' => __('The backup has finished running', 'syllabplus'),
			'backup_aborted' => __('The backup was aborted', 'syllabplus'),
			'remote_delete_limit' => defined('SYLLABPLUS_REMOTE_DELETE_LIMIT') ? SYLLABPLUS_REMOTE_DELETE_LIMIT : 15,
			'remote_files_deleted' => __('remote files deleted', 'syllabplus'),
			'http_code' => __('HTTP code:', 'syllabplus'),
			'makesure2' => __('The file failed to upload. Please check the following:', 'syllabplus')."\n\n - ".__('Any settings in your .htaccess or web.config file that affects the maximum upload or post size.', 'syllabplus')."\n - ".__('The available memory on the server.', 'syllabplus')."\n - ".__('That you are attempting to upload a zip file previously created by SyllabPlus.', 'syllabplus')."\n\n".__('Further information may be found in the browser JavaScript console, and the server PHP error logs.', 'syllabplus'),
			'zip_file_contents' => __('Browsing zip file', 'syllabplus'),
			'zip_file_contents_info' => __('Select a file to view information about it', 'syllabplus'),
			'search' => __('Search', 'syllabplus'),
			'download_timeout' => __('Unable to download file. This could be caused by a timeout. It would be best to download the zip to your computer.', 'syllabplus'),
			'loading_log_file' => __('Loading log file', 'syllabplus'),
			'syllabplus_version' => $syllabplus->version,
			'syllabcentral_wizard_empty_url' => __('Please enter the URL where your SyllabCentral dashboard is hosted.'),
			'syllabcentral_wizard_invalid_url' => __('Please enter a valid URL e.g http://example.com', 'syllabplus'),
			'export_settings_file_name' => 'syllabplus-settings-'.sanitize_title(get_bloginfo('name')).'.json',
			// For remote storage handlebarsjs template
			'remote_storage_options' => $remote_storage_options_and_templates['options'],
			'remote_storage_templates' => $remote_storage_options_and_templates['templates'],
			'remote_storage_methods' => $backup_methods,
			'instance_enabled' => __('Currently enabled', 'syllabplus'),
			'instance_disabled' => __('Currently disabled', 'syllabplus'),
			'local_upload_started' => __('Local backup upload has started; please check the log file to see the upload progress', 'syllabplus'),
			'local_upload_error' => __('You must select at least one remote storage destination to upload this backup set to.', 'syllabplus'),
			'already_uploaded' => __('(already uploaded)', 'syllabplus'),
			'onedrive_folder_url_warning' => __('Please specify the Microsoft OneDrive folder name, not the URL.', 'syllabplus'),
			'syllabcentral_cloud' => __('SyllabCentral Cloud', 'syllabplus'),
			'udc_cloud_connected' => __('Connected. Requesting SyllabCentral Key.', 'syllabplus'),
			'udc_cloud_key_created' => __('Key created. Adding site to SyllabCentral Cloud.', 'syllabplus'),
			'login_successful' => __('Login successful.', 'syllabplus').' '.__('Please follow this link to open %s in a new window.', 'syllabplus'),
			'login_successful_short' => __('Login successful; reloading information.', 'syllabplus'),
			'registration_successful' => __('Registration successful.', 'syllabplus').' '.__('Please follow this link to open %s in a new window.', 'syllabplus'),
			'username_password_required' => __('Both email and password fields are required.', 'syllabplus'),
			'valid_email_required' => __('An email is required and needs to be in a valid format.', 'syllabplus'),
			'trouble_connecting' => __('Trouble connecting? Try using an alternative method in the advanced security options.', 'syllabplus'),
			'checking_tfa_code' => __('Verifying one-time password...', 'syllabplus'),
			'perhaps_login' => __('Perhaps you would want to login instead.', 'syllabplus'),
			'generating_key' => __('Please wait while the system generates and registers an encryption key for your website with SyllabCentral Cloud.', 'syllabplus'),
			'syllabcentral_cloud_redirect' => __('Please wait while you are redirected to SyllabCentral Cloud.', 'syllabplus'),
			'data_consent_required' => __('You need to read and accept the SyllabCentral Cloud data and privacy policies before you can proceed.', 'syllabplus'),
			'close_wizard' => __('You can also close this wizard.', 'syllabplus'),
			'control_udc_connections' => __('For future control of all your SyllabCentral connections, go to the "Advanced Tools" tab.', 'syllabplus'),
			'main_tabs_keys' => array_keys($main_tabs),
			'clone_version_warning' => __('Warning: you have selected a lower version than your currently installed version. This may fail if you have components that are incompatible with earlier versions.', 'syllabplus'),
			'clone_backup_complete' => __('The clone has been provisioned, and its data has been sent to it. Once the clone has finished deploying it, you will receive an email.', 'syllabplus'),
			'clone_backup_aborted' => __('The preparation of the clone data has been aborted.', 'syllabplus'),
			'current_clean_url' => SyllabPlus::get_current_clean_url(),
			'exclude_rule_remove_conformation_msg' => __('Are you sure you want to remove this exclusion rule?', 'syllabplus'),
			'exclude_select_file_or_folder_msg' => __('Please select a file/folder which you would like to exclude', 'syllabplus'),
			'exclude_select_folder_wildcards_msg' => __('Please select a folder in which the files/directories you would like to exclude are located'),
			'exclude_type_ext_msg' => __('Please enter a file extension, like zip', 'syllabplus'),
			'exclude_ext_error_msg' => __('Please enter a valid file extension', 'syllabplus'),
			'exclude_type_prefix_msg' => __('Please enter characters that begin the filename which you would like to exclude', 'syllabplus'),
			'exclude_prefix_error_msg' => __('Please enter a valid file name prefix', 'syllabplus'),
			'exclude_contain_error_msg' => __('Please enter part of the file name', 'syllabplus'),
			'duplicate_exclude_rule_error_msg' => __('The exclusion rule which you are trying to add already exists', 'syllabplus'),
			'clone_key_required' => __('SyllabClone key is required.', 'syllabplus'),
			'files_new_backup' => __('Include your files in the backup', 'syllabplus'),
			'files_incremental_backup' => __('File backup options', 'syllabplus'),
			'ajax_restore_invalid_response' => __('HTML was detected in the response. You may have a security module on your webserver blocking the restoration operation.', 'syllabplus'),
			'emptyrestorepath' => __('You have not selected a restore path for your chosen backups', 'syllabplus'),
			'syllabvault_info' => '<h3>'.__('Try SyllabVault!', 'syllabplus').'</h3>'
				.'<p>'.__('SyllabVault is our remote storage which works seamlessly with SyllabPlus.', 'syllabplus')
				.'	<a href="'.apply_filters('syllabplus_com_link', 'https://syllabplus.com/syllabvault/').'" target="_blank">'.__('Find out more here.', 'syllabplus').'</a>'
				.'</p>'
				.'<p><a href="'.apply_filters('syllabplus_com_link', $syllabplus->get_url('shop_vault_5')).'" target="_blank" '.$checkout_embed_5gb_trial_attribute.' class="button button-primary">'.__('Try it - 1 month for $1!', 'syllabplus').'</a></p>',
			'login_udc_no_licences_short' => __('No SyllabCentral licences were available. Continuing to connect to account.'),
			'credentials' => __('credentials', 'syllabplus'),
			'username' => __('Username', 'syllabplus'),
			'password' => __('Password', 'syllabplus'),
			'last_activity' => __('last activity: %d seconds ago', 'syllabplus'),
			'no_recent_activity' => __('no recent activity; will offer resumption after: %d seconds', 'syllabplus'),
			'restore_files_progress' => __('Restoring %s1 files out of %s2', 'syllabplus'),
			'restore_db_table_progress' => __('Restoring table: %s', 'syllabplus'),
			'restore_db_stored_routine_progress' => __('Restoring stored routine: %s', 'syllabplus'),
			'finished' => __('Finished', 'syllabplus'),
			'begun' => __('Begun', 'syllabplus'),
			'maybe_downloading_entities' => __('Downloading backup files if needed', 'syllabplus'),
			'preparing_backup_files' => __('Preparing backup files', 'syllabplus'),
			'ajax_restore_contact_failed' => __('Attempts by the browser to contact the website failed.', 'syllabplus'),
			'ajax_restore_error' => __('Restore error:', 'syllabplus'),
			'ajax_restore_404_detected' => '<div class="notice notice-warning" style="margin: 0px; padding: 5px;"><p><span class="dashicons dashicons-warning"></span> <strong>'. __('Warning:', 'syllabplus') . '</strong></p><p>' . __('Attempts by the browser to access some pages have returned a "not found (404)" error. This could mean that your .htaccess file has incorrect contents, is missing, or that your webserver is missing an equivalent mechanism.', 'syllabplus'). '</p><p>'.__('Missing pages:', 'syllabplus').'</p><ul class="syllab_missing_pages"></ul><a target="_blank" href="https://syllabplus.com/faqs/migrating-site-front-page-works-pages-give-404-error/">'.__('Follow this link for more information', 'syllabplus').'.</a></div>',
			'delete_error_log_prompt' => __('Please check the error log for more details', 'syllabplus'),
			'existing_backups_limit' => defined('SYLLABPLUS_EXISTING_BACKUPS_LIMIT') ? SYLLABPLUS_EXISTING_BACKUPS_LIMIT : 100,
			'remote_scan_warning' => __('Warning: if you continue, you will add all backups stored in the configured remote storage directory (whichever site they were created by).'),
			'hosting_restriction_one_backup_permonth' => __("You have reached the monthly limit for the number of backups you can create at this time.", 'syllabplus').' '.__('Your hosting provider only allows you to take one backup per month.', 'syllabplus').' '.sprintf(__("Please contact your hosting company (%s) if you require further support.", 'syllabplus'), $hosting_company['name']),
			'hosting_restriction_one_incremental_perday' => __("You have reached the daily limit for the number of incremental backups you can create at this time.", 'syllabplus').' '.__("Your hosting provider only allows you to take one incremental backup per day.", 'syllabplus').' '.sprintf(__("Please contact your hosting company (%s) if you require further support.", 'syllabplus'), $hosting_company['name']),
			'hosting_restriction' => $syllabplus->is_hosting_backup_limit_reached(),
			'conditional_logic' => array(
				'day_of_the_week_options' => $syllabplus->list_days_of_the_week(),
				'logic_options' => array(
					array(
						'label' => __('on every backup', 'syllabplus'),
						'value' => '',
					),
					array(
						'label' => __('if any of the following conditions are matched:', 'syllabplus'),
						'value' => 'any',
					),
					array(
						'label' => __('if all of the following conditions are matched:', 'syllabplus'),
						'value' => 'all',
					),
				),
				'operand_options' => array(
					array(
						'label' => __('Day of the week', 'syllabplus'),
						'value' => 'day_of_the_week',
					),
					array(
						'label' => __('Day of the month', 'syllabplus'),
						'value' => 'day_of_the_month',
					),
				),
				'operator_options' => array(
					array(
						'label' => __('is', 'syllabplus'),
						'value' => 'is',
					),
					array(
						'label' => __('is not', 'syllabplus'),
						'value' => 'is_not',
					),
				)
			),
			'php_max_input_vars_detected_warning' => __('The number of restore options that will be sent exceeds the configured maximum in your PHP configuration (max_input_vars).', 'syllabplus').' '.__('If you proceed with the restoration then some of the restore options will be lost and you may get unexpected results. See the browser console log for more information.', 'syllabplus')
		));
	}
	
	/**
	 * Despite the name, this fires irrespective of what capabilities the user has (even none - so be careful)
	 */
	public function core_upgrade_preamble() {
		// They need to be able to perform backups, and to perform updates
		if (!SyllabPlus_Options::user_can_manage() || (!current_user_can('update_core') && !current_user_can('update_plugins') && !current_user_can('update_themes'))) return;

		if (!class_exists('SyllabPlus_Addon_Autobackup')) {
			if (defined('SYLLABPLUS_NOADS_B')) return;
		}

		?>
		<?php
			if (!class_exists('SyllabPlus_Addon_Autobackup')) {
				if (!class_exists('SyllabPlus_Notices')) include_once(SYLLABPLUS_DIR.'/includes/syllabplus-notices.php');
				global $syllabplus_notices;
				echo apply_filters('syllabplus_autobackup_blurb', $syllabplus_notices->do_notice('autobackup', 'autobackup', true));
			} else {
				echo apply_filters('syllabplus_autobackup_blurb', '');
			}
		?>
		<script>
		jQuery(function() {
			jQuery('.syllab-ad-container').appendTo(jQuery('.wrap p').first());
		});
		</script>
		<?php
	}

	/**
	 * Run upon the WP admin_head action
	 */
	public function admin_head() {

		global $pagenow;

		if (SyllabPlus_Options::admin_page() != $pagenow || !isset($_REQUEST['page']) || 'syllabplus' != sanitize_text_field($_REQUEST['page']) || !SyllabPlus_Options::user_can_manage()) return;

		$chunk_size = min(wp_max_upload_size()-1024, 1048576*2);

		// The multiple_queues argument is ignored in plupload 2.x (WP3.9+) - http://make.wordpress.org/core/2014/04/11/plupload-2-x-in-wordpress-3-9/
		// max_file_size is also in filters as of plupload 2.x, but in its default position is still supported for backwards-compatibility. Likewise, our use of filters.extensions below is supported by a backwards-compatibility option (the current way is filters.mime-types.extensions

		$plupload_init = array(
			'runtimes' => 'html5,flash,silverlight,html4',
			'browse_button' => 'plupload-browse-button',
			'container' => 'plupload-upload-ui',
			'drop_element' => 'drag-drop-area',
			'file_data_name' => 'async-upload',
			'multiple_queues' => true,
			'max_file_size' => '100Gb',
			'chunk_size' => $chunk_size.'b',
			'url' => admin_url('admin-ajax.php', 'relative'),
			'multipart' => true,
			'multi_selection' => true,
			'urlstream_upload' => true,
			// additional post data to send to our ajax hook
			'multipart_params' => array(
				'_ajax_nonce' => wp_create_nonce('syllab-uploader'),
				'action' => 'plupload_action'
			)
		);

		// WP 3.9 updated to plupload 2.0 - https://core.trac.wordpress.org/ticket/25663
		if (is_file(ABSPATH.WPINC.'/js/plupload/Moxie.swf')) {
			$plupload_init['flash_swf_url'] = includes_url('js/plupload/Moxie.swf');
		} else {
			$plupload_init['flash_swf_url'] = includes_url('js/plupload/plupload.flash.swf');
		}

		if (is_file(ABSPATH.WPINC.'/js/plupload/Moxie.xap')) {
			$plupload_init['silverlight_xap_url'] = includes_url('js/plupload/Moxie.xap');
		} else {
			$plupload_init['silverlight_xap_url'] = includes_url('js/plupload/plupload.silverlight.swf');
		}

		?><script>
			var syllab_credentialtest_nonce = '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>';
			var syllabplus_settings_nonce = '<?php echo wp_create_nonce('syllabplus-settings-nonce');?>';
			var syllab_siteurl = '<?php echo esc_js(site_url('', 'relative'));?>';
			var syllab_plupload_config = <?php echo json_encode($plupload_init); ?>;
			var syllab_download_nonce = '<?php echo wp_create_nonce('syllabplus_download');?>';
			var syllab_accept_archivename = <?php echo apply_filters('syllabplus_accept_archivename_js', "[]");?>;
			<?php
			$plupload_init['browse_button'] = 'plupload-browse-button2';
			$plupload_init['container'] = 'plupload-upload-ui2';
			$plupload_init['drop_element'] = 'drag-drop-area2';
			$plupload_init['multipart_params']['action'] = 'plupload_action2';
			$plupload_init['filters'] = array(array('title' => __('Allowed Files'), 'extensions' => 'crypt'));
			?>
			var syllab_plupload_config2 = <?php echo json_encode($plupload_init); ?>;
			var syllab_downloader_nonce = '<?php wp_create_nonce("syllabplus_download"); ?>'
			<?php
				$overdue = $this->howmany_overdue_crons();
				if ($overdue >= 4) {
					?>
					jQuery(function() {
						setTimeout(function(){ syllab_check_overduecrons(); }, 11000);
					});
				<?php } ?>
		</script>
		<?php
	}

	/**
	 * Check if available disk space is at least the specified number of bytes
	 *
	 * @param Integer $space - number of bytes
	 *
	 * @return Integer|Boolean - true or false to indicate if available; of -1 if the result is unknown
	 */
	private function disk_space_check($space) {
		// Allow checking by some other means (user request)
		if (null !== ($filtered_result = apply_filters('syllabplus_disk_space_check', null, $space))) return $filtered_result;
		global $syllabplus;
		$syllab_dir = $syllabplus->backups_dir_location();
		$disk_free_space = function_exists('disk_free_space') ? @disk_free_space($syllab_dir) : false;// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if (false == $disk_free_space) return -1;
		return ($disk_free_space > $space) ? true : false;
	}

	/**
	 * Adds the settings link under the plugin on the plugin screen.
	 *
	 * @param  Array  $links Set of links for the plugin, before being filtered
	 * @param  String $file  File name (relative to the plugin directory)
	 * @return Array filtered results
	 */
	public function plugin_action_links($links, $file) {
		if (is_array($links) && 'syllabplus/syllabplus.php' == $file) {
			$settings_link = '<a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus" class="js-syllabplus-settings">'.__("Settings", "syllabplus").'</a>';
			array_unshift($links, $settings_link);
			$settings_link = '<a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/").'" target="_blank">'.__("Add-Ons / Pro Support", "syllabplus").'</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}

	public function admin_action_upgrade_pluginortheme() {
		if (isset($_GET['action']) && ('upgrade-plugin' == $_GET['action'] || 'upgrade-theme' == $_GET['action']) && !class_exists('SyllabPlus_Addon_Autobackup') && !defined('SYLLABPLUS_NOADS_B')) {

			if ('upgrade-plugin' == $_GET['action']) {
				if (!current_user_can('update_plugins')) return;
			} else {
				if (!current_user_can('update_themes')) return;
			}

			$dismissed_until = SyllabPlus_Options::get_syllab_option('syllabplus_dismissedautobackup', 0);
			if ($dismissed_until > time()) return;

			if ('upgrade-plugin' == $_GET['action']) {
				$title = __('Update Plugin');// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Passed though to wp-admin/admin-header.php
				$parent_file = 'plugins.php';// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Passed though to wp-admin/admin-header.php
				$submenu_file = 'plugins.php';// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Passed though to wp-admin/admin-header.php
			} else {
				$title = __('Update Theme');// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Passed though to wp-admin/admin-header.php
				$parent_file = 'themes.php';// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Passed though to wp-admin/admin-header.php
				$submenu_file = 'themes.php';// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Passed though to wp-admin/admin-header.php
			}

			include_once(ABSPATH.'wp-admin/admin-header.php');
			
			if (!class_exists('SyllabPlus_Notices')) include_once(SYLLABPLUS_DIR.'/includes/syllabplus-notices.php');
			global $syllabplus_notices;
			$syllabplus_notices->do_notice('autobackup', 'autobackup');
		}
	}

	/**
	 * Show an administrative warning message, which can appear only on the SyllabPlus plugin page
	 *
	 * @param String $message the HTML for the message (already escaped)
	 * @param String $class   CSS class to use for the div
	 */
	public function show_plugin_page_admin_warning($message, $class = 'updated') {

		global $pagenow, $plugin_page;

		if (SyllabPlus_Options::admin_page() !== $pagenow || 'syllabplus' !== $plugin_page) return;

		$this->show_admin_warning($message, $class);
	}

	/**
	 * Paint a div for a dashboard warning
	 *
	 * @param String $message - the HTML for the message (already escaped)
	 * @param String $class	  - CSS class to use for the div
	 */
	public function show_admin_warning($message, $class = 'updated') {
		echo '<div class="syllabmessage '.esc_html($class).'">'."<p>$message</p></div>";
	}

	public function show_admin_warning_multiple_storage_options() {
		$this->show_admin_warning('<strong>SyllabPlus:</strong> '.__('An error occurred when fetching storage module options: ', 'syllabplus').esc_html($this->storage_module_option_errors), 'error');
	}

	public function show_admin_warning_unwritable() {
		// One of the translators has erroneously changed "Backup" into "Back up" (which means, "reverse" !)
		$unwritable_mess = esc_html(str_ireplace('Back Up', 'Backup', __("The 'Backup Now' button is disabled as your backup directory is not writable (go to the 'Settings' tab and find the relevant option).", 'syllabplus')));
		$this->show_admin_warning($unwritable_mess, "error");
	}
	
	public function show_admin_nosettings_warning() {
		$this->show_admin_warning('<strong>'.__('Welcome to Syllab!', 'syllabwp').'</strong>');
	}

	public function show_admin_warning_execution_time() {
		$this->show_admin_warning('<strong>'.__('Warning', 'syllabplus').':</strong> '.sprintf(__('The amount of time allowed for WordPress plugins to run is very low (%s seconds) - you should increase it to avoid backup failures due to time-outs (consult your web hosting company for more help - it is the max_execution_time PHP setting; the recommended value is %s seconds or more)', 'syllabplus'), (int) @ini_get('max_execution_time'), 90));// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	}

	public function show_admin_warning_disabledcron() {
		$ret = '<div class="syllabmessage updated"><p>';
		$ret .= '<strong>'.__('Warning', 'syllabplus').':</strong> '.__('The scheduler is disabled in your WordPress install, via the DISABLE_WP_CRON setting. No backups can run (even &quot;Backup Now&quot;) unless either you have set up a facility to call the scheduler manually, or until it is enabled.', 'syllabplus').' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/faqs/my-scheduled-backups-and-pressing-backup-now-does-nothing-however-pressing-debug-backup-does-produce-a-backup/#disablewpcron/").'" target="_blank">'.__('Go here for more information.', 'syllabplus').'</a>';
		$ret .= '</p></div>';
		return $ret;
	}

	public function show_admin_warning_diskspace() {
		$this->show_admin_warning('<strong>'.__('Warning', 'syllabplus').':</strong> '.sprintf(__('You have less than %s of free disk space on the disk which SyllabPlus is configured to use to create backups. SyllabPlus could well run out of space. Contact your the operator of your server (e.g. your web hosting company) to resolve this issue.', 'syllabplus'), '35 MB'));
	}

	public function show_admin_warning_wordpressversion() {
		$this->show_admin_warning('<strong>'.__('Warning', 'syllabplus').':</strong> '.sprintf(__('SyllabPlus does not officially support versions of WordPress before %s. It may work for you, but if it does not, then please be aware that no support is available until you upgrade WordPress.', 'syllabplus'), '3.2'));
	}

	public function show_admin_warning_litespeed() {
		$this->show_admin_warning('<strong>'.__('Warning', 'syllabplus').':</strong> '.sprintf(__('Your website is hosted using the %s web server.', 'syllabplus'), 'LiteSpeed').' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/faqs/i-am-having-trouble-backing-up-and-my-web-hosting-company-uses-the-litespeed-webserver/").'" target="_blank">'.__('Please consult this FAQ if you have problems backing up.', 'syllabplus').'</a>');
	}

	public function show_admin_debug_warning() {
		$this->show_admin_warning('<strong>'.__('Notice', 'syllabplus').':</strong> '.__('SyllabPlus\'s debug mode is on. You may see debugging notices on this page not just from SyllabPlus, but from any other plugin installed. Please try to make sure that the notice you are seeing is from SyllabPlus before you raise a support request.', 'syllabplus').'</a>');
	}

	public function show_admin_warning_overdue_crons($howmany) {
		$ret = '<div class="syllabmessage updated"><p>';
		$ret .= '<strong>'.__('Warning', 'syllabplus').':</strong> '.sprintf(__('WordPress has a number (%d) of scheduled tasks which are overdue. Unless this is a development site, this probably means that the scheduler in your WordPress install is not working.', 'syllabplus'), $howmany).' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/faqs/scheduler-wordpress-installation-working/").'" target="_blank">'.__('Read this page for a guide to possible causes and how to fix it.', 'syllabplus').'</a>';
		$ret .= '</p></div>';
		return $ret;
	}

	/**
	 * Output authorisation links for any un-authorised Dropbox settings instances
	 */
	public function show_admin_warning_dropbox() {
		$this->get_method_auth_link('dropbox');
	}

	/**
	 * Output authorisation links for any un-authorised OneDrive settings instances
	 */
	public function show_admin_warning_onedrive() {
		$this->get_method_auth_link('onedrive');
	}

	public function show_admin_warning_syllabvault() {
		$this->show_admin_warning('<strong>'.__('SylLab Backup Notice :', 'syllabplus').'</strong> '.sprintf(__('%s has been chosen for remote storage, but you are not currently connected.', 'syllabplus'), 'SylLab Backup').' '.__('Go to the remote storage settings in order to connect.', 'syllabplus'), 'updated');
	}

	/**
	 * Output authorisation links for any un-authorised Google Drive settings instances
	 */
	public function show_admin_warning_googledrive() {
		$this->get_method_auth_link('googledrive');
	}

	/**
	 * Output authorisation links for any un-authorised Google Cloud settings instances
	 */
	public function show_admin_warning_googlecloud() {
		$this->get_method_auth_link('googlecloud');
	}
	
	/**
	 * Show DreamObjects cluster migration warning
	 */
	public function show_admin_warning_dreamobjects() {
		$this->show_admin_warning('<strong>'.__('SyllabPlus notice:', 'syllabplus').'</strong> '.sprintf(__('The %s endpoint is scheduled to shut down on the 1st October 2018. You will need to switch to a different end-point and migrate your data before that date. %sPlease see this article for more information%s'), 'objects-us-west-1.dream.io', '<a href="https://help.dreamhost.com/hc/en-us/articles/360002135871-Cluster-migration-procedure" target="_blank">', '</a>'), 'updated');
	}
	
	/**
	 * Show notice if the account connection attempted to register with UDC Cloud but could not due to lack of licences
	 */
	public function show_admin_warning_udc_couldnt_connect() {
		$this->show_admin_warning('<strong>'.__('Notice', 'syllabplus').':</strong> '.sprintf(__('Connection to your %1$s account was successful. However, we were not able to register this site with %2$s, as there are no available %2$s licences on the account.', 'syllabplus'), 'SyllabPlus.com', 'SyllabCentral Cloud'), 'updated');
	}
	
	/**
	 * This method will setup the storage object and get the authentication link ready to be output with the notice
	 *
	 * @param  String $method - the remote storage method
	 */
	public function get_method_auth_link($method) {
		$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array($method));

		$object = $storage_objects_and_ids[$method]['object'];

		foreach ($this->auth_instance_ids[$method] as $instance_id) {
			
			$object->set_instance_id($instance_id);

			$this->show_admin_warning('<strong>'.__('SyllabPlus notice:', 'syllabplus').'</strong> '.$object->get_authentication_link(false, false), 'updated syllab_authenticate_'.$method);
		}
	}

	/**
	 * Start a download of a backup. This method is called via the AJAX action syllab_download_backup. May die instead of returning depending upon the mode in which it is called.
	 */
	public function syllab_download_backup() {
		try {
			if (empty(sanitize_text_field($_REQUEST['_wpnonce'])) || !wp_verify_nonce(sanitize_text_field($_REQUEST['_wpnonce']), 'syllabplus_download')) die;
	
			if (empty(sanitize_text_field($_REQUEST['timestamp'])) || !is_numeric(sanitize_text_field($_REQUEST['timestamp'])) || empty(sanitize_text_field($_REQUEST['type']))) exit;
	
			$findexes = empty(sanitize_text_field($_REQUEST['findex'])) ? array(0) : sanitize_text_field($_REQUEST['findex']);
			$stage = empty(sanitize_text_field($_REQUEST['stage'])) ? '' : sanitize_text_field($_REQUEST['stage']);
			$file_path = empty(sanitize_text_field($_REQUEST['filepath'])) ? '' : sanitize_text_field($_REQUEST['filepath']);
	
			// This call may not actually return, depending upon what mode it is called in
			$result = $this->do_syllab_download_backup($findexes, sanitize_text_field($_REQUEST['type']), sanitize_text_field($_REQUEST['timestamp']), $stage, false, $file_path);
			
			// In theory, if a response was already sent, then Connection: close has been issued, and a Content-Length. However, in https://syllabplus.com/forums/topic/pclzip_err_bad_format-10-invalid-archive-structure/ a browser ignores both of these, and then picks up the second output and complains.
			if (empty($result['already_closed'])) echo json_encode($result);
		} catch (Exception $e) {
			$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during download backup. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			echo json_encode(array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			));
		// @codingStandardsIgnoreLine
		} catch (Error $e) {
			$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during download backup. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			echo json_encode(array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			));
		}
		die();
	}
	
	/**
	 * Ensure that a specified backup is present, downloading if necessary (or delete it, if the parameters so indicate). N.B. This function may die(), depending on the request being made in $stage
	 *
	 * @param Array            $findexes                  - the index number of the backup archive requested
	 * @param String           $type                      - the entity type (e.g. 'plugins') being requested
	 * @param Integer          $timestamp                 - identifier for the backup being requested (UNIX epoch time)
	 * @param Mixed            $stage                     - the stage; valid values include (have not audited for other possibilities) at least 'delete' and 2.
	 * @param Callable|Boolean $close_connection_callable - function used to close the connection to the caller; an array of data to return is passed. If false, then SyllabPlus::close_browser_connection is called with a JSON version of the data.
	 * @param String           $file_path                 - an over-ride for where to download the file to (basename only)
	 *
	 * @return Array - sumary of the results. May also just die.
	 */
	public function do_syllab_download_backup($findexes, $type, $timestamp, $stage, $close_connection_callable = false, $file_path = '') {

		if (function_exists('set_time_limit')) @set_time_limit(SYLLABPLUS_SET_TIME_LIMIT);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

		global $syllabplus;
		
		if (!is_array($findexes)) $findexes = array($findexes);

		$connection_closed = false;

		// Check that it is a known entity type; if not, die
		if ('db' != substr($type, 0, 2)) {
			$backupable_entities = $syllabplus->get_backupable_file_entities(true);
			foreach ($backupable_entities as $t => $info) {
				if ($type == $t) $type_match = true;
			}
			if (empty($type_match)) return array('result' => 'error', 'code' => 'no_such_type');
		}

		$debug_mode = SyllabPlus_Options::get_syllab_option('syllab_debug_mode');

		// Retrieve the information from our backup history
		$backup_history = SyllabPlus_Backup_History::get_history();

		foreach ($findexes as $findex) {
			// This is a bit ugly; these variables get placed back into $_POST (where they may possibly have come from), so that SyllabPlus::log() can detect exactly where to log the download status.
			$_POST['findex'] = $findex;
			$_POST['type'] = $type;
			$_POST['timestamp'] = $timestamp;

			// We already know that no possible entities have an MD5 clash (even after 2 characters)
			// Also, there's nothing enforcing a requirement that nonces are hexadecimal
			$job_nonce = dechex($timestamp).$findex.substr(md5($type), 0, 3);

			// You need a nonce before you can set job data. And we certainly don't yet have one.
			$syllabplus->backup_time_nonce($job_nonce);

			// Set the job type before logging, as there can be different logging destinations
			$syllabplus->jobdata_set('job_type', 'download');
			$syllabplus->jobdata_set('job_time_ms', $syllabplus->job_time_ms);

			// Base name
			$file = $backup_history[$timestamp][$type];

			// Deal with multi-archive sets
			if (is_array($file)) $file = $file[$findex];

			if (false !== strpos($file_path, '..')) {
				error_log("SyllabPlus_Admin::do_syllab_download_backup : invalid file_path: $file_path");
				return array('result' => __('Error: invalid path', 'syllabplus'));
			}

			if (!empty($file_path)) $file = $file_path;

			// Where it should end up being downloaded to
			$fullpath = $syllabplus->backups_dir_location().'/'.$file;

			if (!empty($file_path) && strpos(realpath($fullpath), realpath($syllabplus->backups_dir_location())) === false) {
				error_log("SyllabPlus_Admin::do_syllab_download_backup : invalid fullpath: $fullpath");
				return array('result' => __('Error: invalid path', 'syllabplus'));
			}

			if (2 == $stage) {
				$syllabplus->spool_file($fullpath);
				// We only want to remove if it was a temp file from the zip browser
				if (!empty($file_path)) @unlink($fullpath);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				// Do not return - we do not want the caller to add any output
				die;
			}

			if ('delete' == $stage) {
				@unlink($fullpath);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				$syllabplus->log("The file has been deleted ($file)");
				return array('result' => 'deleted');
			}

			// TODO: FIXME: Failed downloads may leave log files forever (though they are small)
			if ($debug_mode) $syllabplus->logfile_open($syllabplus->nonce);

			set_error_handler(array($syllabplus, 'php_error'), E_ALL & ~E_STRICT);

			$syllabplus->log("Requested to obtain file: timestamp=$timestamp, type=$type, index=$findex");

			$itext = empty($findex) ? '' : $findex;
			$known_size = isset($backup_history[$timestamp][$type.$itext.'-size']) ? $backup_history[$timestamp][$type.$itext.'-size'] : 0;

			$services = isset($backup_history[$timestamp]['service']) ? $backup_history[$timestamp]['service'] : false;
			
			$services = $syllabplus->get_canonical_service_list($services);
			
			$syllabplus->jobdata_set('service', $services);

			// Fetch it from the cloud, if we have not already got it

			$needs_downloading = false;

			if (!file_exists($fullpath) && empty($services)) {
				$syllabplus->log('This file does not exist locally, and there is no remote storage for this file.');
			} elseif (!file_exists($fullpath)) {
				// If the file doesn't exist and they're using one of the cloud options, fetch it down from the cloud.
				$needs_downloading = true;
				$syllabplus->log('File does not yet exist locally - needs downloading');
			} elseif ($known_size > 0 && filesize($fullpath) < $known_size) {
				$syllabplus->log("The file was found locally (".filesize($fullpath).") but did not match the size in the backup history ($known_size) - will resume downloading");
				$needs_downloading = true;
			} elseif ($known_size > 0 && filesize($fullpath) > $known_size) {
				$syllabplus->log("The file was found locally (".filesize($fullpath).") but the size is larger than what is recorded in the backup history ($known_size) - will try to continue but if errors are encountered then check that the backup is correct");
			} elseif ($known_size > 0) {
				$syllabplus->log('The file was found locally and matched the recorded size from the backup history ('.round($known_size/1024, 1).' KB)');
			} else {
				$syllabplus->log('No file size was found recorded in the backup history. We will assume the local one is complete.');
				$known_size = filesize($fullpath);
			}
			
			// The AJAX responder that updates on progress wants to see this
			$syllabplus->jobdata_set('dlfile_'.$timestamp.'_'.$type.'_'.$findex, "downloading:$known_size:$fullpath");

			if ($needs_downloading) {

				// Update the "last modified" time to dissuade any other instances from thinking that no downloaders are active
				@touch($fullpath);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

				$msg = array(
					'result' => 'needs_download',
					'request' => array(
						'type' => $type,
						'timestamp' => $timestamp,
						'findex' => $findex
					)
				);
			
				if ($close_connection_callable && is_callable($close_connection_callable) && !$connection_closed) {
					$connection_closed = true;
					call_user_func($close_connection_callable, $msg);
				} elseif (!$connection_closed) {
					$connection_closed = true;
					$syllabplus->close_browser_connection(json_encode($msg));
				}
				SyllabPlus_Storage_Methods_Interface::get_remote_file($services, $file, $timestamp);
			}

			// Now, be ready to spool the thing to the browser
			if (is_file($fullpath) && is_readable($fullpath) && $needs_downloading) {

				// That message is then picked up by the AJAX listener
				$syllabplus->jobdata_set('dlfile_'.$timestamp.'_'.$type.'_'.$findex, 'downloaded:'.filesize($fullpath).":$fullpath");

				$result = 'downloaded';
				
			} elseif ($needs_downloading) {

				$syllabplus->jobdata_set('dlfile_'.$timestamp.'_'.$type.'_'.$findex, 'failed');
				$syllabplus->jobdata_set('dlerrors_'.$timestamp.'_'.$type.'_'.$findex, $syllabplus->errors);
				$syllabplus->log('Remote fetch failed. File '.$fullpath.' did not exist or was unreadable. If you delete local backups then remote retrieval may have failed.');
				
				$result = 'download_failed';
			} else {
				$result = 'no_local_file';
			}

			restore_error_handler();

			if ($debug_mode) @fclose($syllabplus->logfile_handle);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			if (!$debug_mode) @unlink($syllabplus->logfile_name);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}

		// The browser connection was possibly already closed, but not necessarily
		return array('result' => $result, 'already_closed' => $connection_closed);
	}

	/**
	 * This is used as a callback
	 *
	 * @param  Mixed $msg The data to be JSON encoded and sent back
	 */
	public function _syllabplus_background_operation_started($msg) {
		global $syllabplus;
		// The extra spaces are because of a bug seen on one server in handling of non-ASCII characters; see HS#11739
		$syllabplus->close_browser_connection(json_encode($msg).'        ');
	}
	
	public function syllab_ajax_handler() {

		$nonce = empty(sanitize_text_field($_REQUEST['nonce'])) ? '' : sanitize_text_field($_REQUEST['nonce']);

		if (!wp_verify_nonce($nonce, 'syllabplus-credentialtest-nonce') || empty(sanitize_text_field($_REQUEST['subaction']))) die('Security check');

		$subaction = sanitize_text_field($_REQUEST['subaction']);
		// Mitigation in case the nonce leaked to an unauthorised user
		if ('dismissautobackup' == $subaction) {
			if (!current_user_can('update_plugins') && !current_user_can('update_themes')) return;
		} elseif ('dismissexpiry' == $subaction || 'dismissdashnotice' == $subaction) {
			if (!current_user_can('update_plugins')) return;
		} else {
			if (!SyllabPlus_Options::user_can_manage()) return;
		}
		
		// All others use _POST
		$data_in_get = array('get_log', 'get_fragment');
		
		// SyllabPlus_WPAdmin_Commands extends SyllabPlus_Commands - i.e. all commands are in there
		if (!class_exists('SyllabPlus_WPAdmin_Commands')) include_once(SYLLABPLUS_DIR.'/includes/class-wpadmin-commands.php');
		$commands = new SyllabPlus_WPAdmin_Commands($this);
		
		if (method_exists($commands, $subaction)) {

			$data = in_array($subaction, $data_in_get) ? $_GET : $_POST;
			
			// Undo WP's slashing of GET/POST data
			$data = SyllabPlus_Manipulation_Functions::wp_unslash($data);
			
			// TODO: Once all commands come through here and through syllab_send_command(), the data should always come from this attribute (once syllab_send_command() is modified appropriately).
			if (isset($data['action_data'])) $data = $data['action_data'];
			try {
				$results = call_user_func(array($commands, $subaction), $data);
			} catch (Exception $e) {
				$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during '.$subaction.' subaction. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
				die;
			// @codingStandardsIgnoreLine
			} catch (Error $e) {
				$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during '.$subaction.' subaction. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
				die;
			}
			if (is_wp_error($results)) {
				$results = array(
					'result' => false,
					'error_code' => $results->get_error_code(),
					'error_message' => $results->get_error_message(),
					'error_data' => $results->get_error_data(),
				);
			}
			
			if (is_string($results)) {
				// A handful of legacy methods, and some which are directly the source for iframes, for which JSON is not appropriate.
				echo $results;
			} else {
				echo json_encode($results);
			}
			die;
		}
		
		// Below are all the commands not ported over into class-commands.php or class-wpadmin-commands.php

		if ('activejobs_list' == $subaction) {
			try {
				// N.B. Also called from autobackup.php
				// TODO: This should go into SyllabPlus_Commands, once the add-ons have been ported to use syllab_send_command()
				echo json_encode($this->get_activejobs_list(SyllabPlus_Manipulation_Functions::wp_unslash($_GET)));
			} catch (Exception $e) {
				$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during get active job list. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
			// @codingStandardsIgnoreLine
			} catch (Error $e) {
				$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during get active job list. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
			}
			
		} elseif ('httpget' == $subaction) {
			try {
				// httpget
				$curl = empty(sanitize_text_field($_REQUEST['curl'])) ? false : true;
				echo $this->http_get(SyllabPlus_Manipulation_Functions::wp_unslash($_REQUEST['uri']), $curl);
			// @codingStandardsIgnoreLine
			} catch (Error $e) {
				$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during http get. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
			} catch (Exception $e) {
				$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during http get. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
			}
			 
		} elseif ('doaction' == $subaction && !empty(sanitize_text_field($_REQUEST['subsubaction'])) && 'syllab_' == substr(sanitize_text_field($_REQUEST['subsubaction']), 0, 8)) {
			$subsubaction = sanitize_text_field($_REQUEST['subsubaction']);
			try {
					// These generally echo and die - they will need further work to port to one of the command classes. Some may already have equivalents in SyllabPlus_Commands, if they are used from SyllabCentral.
				do_action(SyllabPlus_Manipulation_Functions::wp_unslash($subsubaction), $_REQUEST);
			} catch (Exception $e) {
				$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during doaction subaction with '.$subsubaction.' subsubaction. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
				die;
			// @codingStandardsIgnoreLine
			} catch (Error $e) {
				$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during doaction subaction with '.$subsubaction.' subsubaction. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
				error_log($log_message);
				echo json_encode(array(
					'fatal_error' => true,
					'fatal_error_message' => $log_message
				));
				die;
			}
		}
		
		die;

	}
	
	/**
	 * Run a credentials test for the indicated remote storage module
	 *
	 * @param Array   $test_settings          The test parameters, including the method itself indicated in the key 'method'
	 * @param Boolean $return_instead_of_echo Whether to return or echo the results. N.B. More than just the results to echo will be returned
	 * @return Array|Void - the results, if they are being returned (rather than echoed). Keys: 'output' (the output), 'data' (other data)
	 */
	public function do_credentials_test($test_settings, $return_instead_of_echo = false) {
	
		$method = (!empty($test_settings['method']) && preg_match("/^[a-z0-9]+$/", $test_settings['method'])) ? $test_settings['method'] : "";
		
		$objname = "SyllabPlus_BackupModule_$method";
		
		$this->logged = array();
		// TODO: Add action for WP HTTP SSL stuff
		set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);
		
		if (!class_exists($objname)) include_once(SYLLABPLUS_DIR."/methods/$method.php");

		$ret = '';
		$data = null;
		
		// TODO: Add action for WP HTTP SSL stuff
		if (method_exists($objname, "credentials_test")) {
			$obj = new $objname;
			if ($return_instead_of_echo) ob_start();
			$data = $obj->credentials_test($test_settings);
			if ($return_instead_of_echo) $ret .= ob_get_clean();
		}
		
		if (count($this->logged) >0) {
			$ret .= "\n\n".__('Messages:', 'syllabplus')."\n";
			foreach ($this->logged as $err) {
				$ret .= "* $err\n";
			}
			if (!$return_instead_of_echo) echo $ret;
		}
		restore_error_handler();
		
		if ($return_instead_of_echo) return array('output' => $ret, 'data' => $data);
		
	}
	
	/**
	 * Delete a backup set, whilst respecting limits on how much to delete in one go
	 *
	 * @uses remove_backup_set_cleanup()
	 * @param Array $opts - deletion options; with keys backup_timestamp, delete_remote, [remote_delete_limit]
	 * @return Array - as from remove_backup_set_cleanup()
	 */
	public function delete_set($opts) {
		
		global $syllabplus;
		
		$backups = SyllabPlus_Backup_History::get_history();
		$timestamps = (string) $opts['backup_timestamp'];

		$remote_delete_limit = (isset($opts['remote_delete_limit']) && $opts['remote_delete_limit'] > 0) ? (int) $opts['remote_delete_limit'] : PHP_INT_MAX;
		
		$timestamps = explode(',', $timestamps);
		$deleted_timestamps = '';
		$delete_remote = empty($opts['delete_remote']) ? false : true;

		// You need a nonce before you can set job data. And we certainly don't yet have one.
		$syllabplus->backup_time_nonce();
		// Set the job type before logging, as there can be different logging destinations
		$syllabplus->jobdata_set('job_type', 'delete');
		$syllabplus->jobdata_set('job_time_ms', $syllabplus->job_time_ms);

		if (SyllabPlus_Options::get_syllab_option('syllab_debug_mode')) {
			$syllabplus->logfile_open($syllabplus->nonce);
			set_error_handler(array($syllabplus, 'php_error'), E_ALL & ~E_STRICT);
		}

		$syllab_dir = $syllabplus->backups_dir_location();
		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);

		$local_deleted = 0;
		$remote_deleted = 0;
		$sets_removed = 0;
		
		$deletion_errors = array();

		foreach ($timestamps as $i => $timestamp) {

			if (!isset($backups[$timestamp])) {
				return array('result' => 'error', 'message' => __('Backup set not found', 'syllabplus'));
			}

			$nonce = isset($backups[$timestamp]['nonce']) ? $backups[$timestamp]['nonce'] : '';

			$delete_from_service = array();

			if ($delete_remote) {
				// Locate backup set
				if (isset($backups[$timestamp]['service'])) {
					// Convert to an array so that there is no uncertainty about how to process it
					$services = is_string($backups[$timestamp]['service']) ? array($backups[$timestamp]['service']) : $backups[$timestamp]['service'];
					if (is_array($services)) {
						foreach ($services as $service) {
							if ($service && 'none' != $service && 'email' != $service) $delete_from_service[] = $service;
						}
					}
				}
			}

			$files_to_delete = array();
			foreach ($backupable_entities as $key => $ent) {
				if (isset($backups[$timestamp][$key])) {
					$files_to_delete[$key] = $backups[$timestamp][$key];
				}
			}
			// Delete DB
			foreach ($backups[$timestamp] as $key => $value) {
				if ('db' == strtolower(substr($key, 0, 2)) && '-size' != substr($key, -5, 5)) {
					$files_to_delete[$key] = $backups[$timestamp][$key];
				}
			}

			// Also delete the log
			if ($nonce && !SyllabPlus_Options::get_syllab_option('syllab_debug_mode')) {
				$files_to_delete['log'] = "log.$nonce.txt";
			}
			
			$syllabplus->register_wp_http_option_hooks();

			foreach ($files_to_delete as $key => $files) {

				if (is_string($files)) {
					$was_string = true;
					$files = array($files);
				} else {
					$was_string = false;
				}

				foreach ($files as $file) {
					if (is_file($syllab_dir.'/'.$file) && @unlink($syllab_dir.'/'.$file)) $local_deleted++;// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				}

				if ('log' != $key && count($delete_from_service) > 0) {

					$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids($delete_from_service);

					foreach ($delete_from_service as $service) {
					
						if ('email' == $service || 'none' == $service || !$service) continue;

						$deleted = -1;

						$remote_obj = $storage_objects_and_ids[$service]['object'];

						$instance_settings = $storage_objects_and_ids[$service]['instance_settings'];
						$this->backups_instance_ids = empty($backups[$timestamp]['service_instance_ids'][$service]) ? array() : $backups[$timestamp]['service_instance_ids'][$service];

						if (empty($instance_settings)) continue;
						
						uksort($instance_settings, array($this, 'instance_ids_sort'));

						foreach ($instance_settings as $instance_id => $options) {

							$remote_obj->set_options($options, false, $instance_id);

							if ($remote_obj->supports_feature('multi_delete')) {
								if ($remote_deleted == $remote_delete_limit) {
									$timestamps_list = implode(',', $timestamps);

									return $this->remove_backup_set_cleanup(false, $backups, $local_deleted, $remote_deleted, $sets_removed, $timestamps_list, $deleted_timestamps, $deletion_errors);
								}

								$deleted = $remote_obj->delete($files);

								if (true === $deleted) {
									$remote_deleted = $remote_deleted + count($files);

									unset($backups[$timestamp][$key]);

									// If we don't save the array back, then the above section will fire again for the same files - and the remote storage will be requested to delete already-deleted files, which then means no time is actually saved by the browser-backend loop method.
									SyllabPlus_Backup_History::save_history($backups);
								} else {
									// Handle abstracted error codes/return fail status. Including handle array/objects returned
									if (is_object($deleted) || is_array($deleted)) $deleted = false;
									
									if (!array_key_exists($instance_id, $deletion_errors)) {
										$deletion_errors[$instance_id] = array('error_code' => $deleted, 'service' => $service);
									}
								}

								continue;
							}
							foreach ($files as $index => $file) {
								if ($remote_deleted == $remote_delete_limit) {
									$timestamps_list = implode(',', $timestamps);

									return $this->remove_backup_set_cleanup(false, $backups, $local_deleted, $remote_deleted, $sets_removed, $timestamps_list, $deleted_timestamps, $deletion_errors);
								}

								$deleted = $remote_obj->delete($file);
								
								if (true === $deleted) {
									$remote_deleted++;
								} else {
									// Handle abstracted error codes/return fail status. Including handle array/objects returned
									if (is_object($deleted) || is_array($deleted)) $deleted = false;
									
									if (!array_key_exists($instance_id, $deletion_errors)) {
										$deletion_errors[$instance_id] = array('error_code' => $deleted, 'service' => $service);
									}
								}
								
								$itext = $index ? (string) $index : '';
								if ($was_string) {
									unset($backups[$timestamp][$key]);
									if ('db' == strtolower(substr($key, 0, 2))) unset($backups[$timestamp][$key][$index.'-size']);
								} else {
									unset($backups[$timestamp][$key][$index]);
									unset($backups[$timestamp][$key.$itext.'-size']);
									if (empty($backups[$timestamp][$key])) unset($backups[$timestamp][$key]);
								}
								if (isset($backups[$timestamp]['checksums']) && is_array($backups[$timestamp]['checksums'])) {
									foreach (array_keys($backups[$timestamp]['checksums']) as $algo) {
										unset($backups[$timestamp]['checksums'][$algo][$key.$index]);
									}
								}
								
								// If we don't save the array back, then the above section will fire again for the same files - and the remote storage will be requested to delete already-deleted files, which then means no time is actually saved by the browser-backend loop method.
								SyllabPlus_Backup_History::save_history($backups);
							}
						}
					}
				}
			}

			unset($backups[$timestamp]);
			unset($timestamps[$i]);
			if ('' != $deleted_timestamps) $deleted_timestamps .= ',';
			$deleted_timestamps .= $timestamp;
			SyllabPlus_Backup_History::save_history($backups);
			$sets_removed++;
		}

		$timestamps_list = implode(',', $timestamps);

		return $this->remove_backup_set_cleanup(true, $backups, $local_deleted, $remote_deleted, $sets_removed, $timestamps_list, $deleted_timestamps, $deletion_errors);

	}

	/**
	 * This function sorts the array of instance ids currently saved so that any instance id that is in both the saved settings and the backup history move to the top of the array, as these are likely to work. Then values that don't appear in the backup history move to the bottom.
	 *
	 * @param  String $a - the first instance id
	 * @param  String $b - the second instance id
	 * @return Integer   - returns an integer to indicate what position the $b value should be moved in
	 */
	public function instance_ids_sort($a, $b) {
		if (in_array($a, $this->backups_instance_ids)) {
			if (in_array($b, $this->backups_instance_ids)) return 0;
			return -1;
		}
		return in_array($b, $this->backups_instance_ids) ? 1 : 0;
	}

	/**
	 * Called by self::delete_set() to finish up before returning (whether the complete deletion is finished or not)
	 *
	 * @param Boolean $delete_complete    - whether the whole set is now gone (i.e. last round)
	 * @param Array	  $backups            - the backup history
	 * @param Integer $local_deleted      - how many backup archives were deleted from local storage
	 * @param Integer $remote_deleted     - how many backup archives were deleted from remote storage
	 * @param Integer $sets_removed       - how many complete sets were removed
	 * @param String  $timestamps         - a csv of remaining timestamps
	 * @param String  $deleted_timestamps - a csv of deleted timestamps
	 * @param Array   $deletion_errors    - an array of abstracted deletion errors, consisting of [error_code, service, instance]. For user notification purposes only, main error logging occurs at service.
	 *
	 * @return Array - information on the status, suitable for returning to the UI
	 */
	public function remove_backup_set_cleanup($delete_complete, $backups, $local_deleted, $remote_deleted, $sets_removed, $timestamps, $deleted_timestamps, $deletion_errors = array()) {// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $deletion_errors was used below but the code has been commented out.  Can both be removed?

		global $syllabplus;

		$syllabplus->register_wp_http_option_hooks(false);

		SyllabPlus_Backup_History::save_history($backups);

		$syllabplus->log("Local files deleted: $local_deleted. Remote files deleted: $remote_deleted");
		
		/*
		Disable until next release
		$error_messages = array();
		$storage_details = array();

		foreach ($deletion_errors as $instance => $entry) {
			$service = $entry['service'];

			if (!array_key_exists($service, $storage_details)) {
				// As errors from multiple instances of a service can be present, store the service storage object for possible use later
				$new_service = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array($service));
				$storage_details = array_merge($storage_details, $new_service);
			}

			$intance_label = !empty($storage_details[$service]['instance_settings'][$instance]['instance_label']) ? $storage_details[$service]['instance_settings'][$instance]['instance_label'] : $service;

			switch ($entry['error_code']) {
				case 'authentication_fail':
					$error_messages[] = sprintf(__("The authentication failed for '%s'.", 'syllabplus').' '.__('Please check your credentials.', 'syllabplus'), $intance_label);
					break;
				case 'service_unavailable':
					$error_messages[] = sprintf(__("We were unable to access '%s'.", 'syllabplus').' '.__('Service unavailable.', 'syllabplus'), $intance_label);
					break;
				case 'container_access_error':
					$error_messages[] = sprintf(__("We were unable to access the folder/container for '%s'.", 'syllabplus').' '.__('Please check your permissions.', 'syllabplus'), $intance_label);
					break;
				case 'file_access_error':
					$error_messages[] = sprintf(__("We were unable to access a file on '%s'.", 'syllabplus').' '.__('Please check your permissions.', 'syllabplus'), $intance_label);
					break;
				case 'file_delete_error':
					$error_messages[] = sprintf(__("We were unable to delete a file on '%s'.", 'syllabplus').' '.__('The file may no longer exist or you may not have permission to delete.', 'syllabplus'), $intance_label);
					break;
				default:
					$error_messages[] = sprintf(__("An error occurred while attempting to delete from '%s'.", 'syllabplus'), $intance_label);
					break;
			}
		}
		*/
		
		// $error_message_string = implode("\n", $error_messages);
		$error_message_string = '';

		if ($delete_complete) {
			$set_message = __('Backup sets removed:', 'syllabplus');
			$local_message = __('Local files deleted:', 'syllabplus');
			$remote_message = __('Remote files deleted:', 'syllabplus');

			if (SyllabPlus_Options::get_syllab_option('syllab_debug_mode')) {
				restore_error_handler();
			}
			
			return array('result' => 'success', 'set_message' => $set_message, 'local_message' => $local_message, 'remote_message' => $remote_message, 'backup_sets' => $sets_removed, 'backup_local' => $local_deleted, 'backup_remote' => $remote_deleted, 'error_messages' => $error_message_string);
		} else {
		
			return array('result' => 'continue', 'backup_local' => $local_deleted, 'backup_remote' => $remote_deleted, 'backup_sets' => $sets_removed, 'timestamps' => $timestamps, 'deleted_timestamps' => $deleted_timestamps, 'error_messages' => $error_message_string);
		}
	}

	/**
	 * Get the history status HTML and other information
	 *
	 * @param Boolean $rescan       - whether to rescan local storage first
	 * @param Boolean $remotescan   - whether to rescan remote storage first
	 * @param Boolean $debug        - whether to return debugging information also
	 * @param Integer $backup_count - a count of the total backups we want to display on the front end for use by SyllabPlus_Backup_History::existing_backup_table()
	 *
	 * @return Array - the information requested
	 */
	public function get_history_status($rescan, $remotescan, $debug = false, $backup_count = 0) {
	
		global $syllabplus;
	
		if ($rescan) $messages = SyllabPlus_Backup_History::rebuild($remotescan, false, $debug);
		$backup_history = SyllabPlus_Backup_History::get_history();
		$output = SyllabPlus_Backup_History::existing_backup_table($backup_history, $backup_count);
		
		$data = array();

		if (!empty($messages) && is_array($messages)) {
			$noutput = '';
			foreach ($messages as $msg) {
				if (empty($msg['code']) || 'file-listing' != $msg['code']) {
					$noutput .= '<li>'.(empty($msg['desc']) ? '' : $msg['desc'].': ').'<em>'.$msg['message'].'</em></li>';
				}
				if (!empty($msg['data'])) {
					$key = $msg['method'].'-'.$msg['service_instance_id'];
					$data[$key] = $msg['data'];
				}
			}
			if ($noutput) {
				$output = '<div style="margin-left: 100px; margin-top: 10px;"><ul style="list-style: disc inside;">'.$noutput.'</ul></div>'.$output;
			}
		}
		
		$logs_exist = (false !== strpos($output, 'downloadlog'));
		if (!$logs_exist) {
			list($mod_time, $log_file, $nonce) = $syllabplus->last_modified_log();// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ($mod_time) $logs_exist = true;
		}
		
		return apply_filters('syllabplus_get_history_status_result', array(
			'n' => __('Existing backups', 'syllabplus').' <span class="syllab_existing_backups_count">'.count($backup_history).'</span>',
			't' => $output,  // table
			'data' => $data,
			'cksum' => md5($output),
			'logs_exist' => $logs_exist,
			'web_server_disk_space' => SyllabPlus_Filesystem_Functions::web_server_disk_space(true),
		));
	}
	
	/**
	 * Stop an active backup job
	 *
	 * @param String $job_id - job ID of the job to stop
	 *
	 * @return Array - information on the outcome of the attempt
	 */
	public function activejobs_delete($job_id) {
			
		if (preg_match("/^[0-9a-f]{12}$/", $job_id)) {
		
			global $syllabplus;
			$cron = get_option('cron', array());
			$found_it = false;

			$jobdata = $syllabplus->jobdata_getarray($job_id);
			
			if (!empty($jobdata['clone_job']) && !empty($jobdata['clone_id']) && !empty($jobdata['secret_token'])) {
				$clone_id = $jobdata['clone_id'];
				$secret_token = $jobdata['secret_token'];
				$syllabplus->get_syllabplus_clone()->clone_failed_delete(array('clone_id' => $clone_id, 'secret_token' => $secret_token));
			}

			$syllab_dir = $syllabplus->backups_dir_location();
			if (file_exists($syllab_dir.'/log.'.$job_id.'.txt')) touch($syllab_dir.'/deleteflag-'.$job_id.'.txt');
			
			foreach ($cron as $time => $job) {
				if (!isset($job['syllab_backup_resume'])) continue;
				foreach ($job['syllab_backup_resume'] as $hook => $info) {
					if (isset($info['args'][1]) && $info['args'][1] == $job_id) {
						$args = $cron[$time]['syllab_backup_resume'][$hook]['args'];
						wp_unschedule_event($time, 'syllab_backup_resume', $args);
						if (!$found_it) return array('ok' => 'Y', 'c' => 'deleted', 'm' => __('Job deleted', 'syllabplus'));
						$found_it = true;
					}
				}
			}
		}

		if (!$found_it) return array('ok' => 'N', 'c' => 'not_found', 'm' => __('Could not find that job - perhaps it has already finished?', 'syllabplus'));

	}

	/**
	 * Input: an array of items
	 * Each item is in the format: <base>,<timestamp>,<type>(,<findex>)
	 * The 'base' is not for us: we just pass it straight back
	 *
	 * @param  array $downloaders Array of Items to download
	 * @return array
	 */
	public function get_download_statuses($downloaders) {
		global $syllabplus;
		$download_status = array();
		foreach ($downloaders as $downloader) {
			// prefix, timestamp, entity, index
			if (preg_match('/^([^,]+),(\d+),([-a-z]+|db[0-9]+),(\d+)$/', $downloader, $matches)) {
				$findex = (empty($matches[4])) ? '0' : $matches[4];
				$syllabplus->nonce = dechex($matches[2]).$findex.substr(md5($matches[3]), 0, 3);
				$syllabplus->jobdata_reset();
				$status = $this->download_status($matches[2], $matches[3], $matches[4]);
				if (is_array($status)) {
					$status['base'] = $matches[1];
					$status['timestamp'] = $matches[2];
					$status['what'] = $matches[3];
					$status['findex'] = $findex;
					$download_status[] = $status;
				}
			}
		}
		return $download_status;
	}
	
	/**
	 * Get, as HTML output, a list of active jobs
	 *
	 * @param Array $request - details on the request being made (e.g. extra info to include)
	 *
	 * @return String
	 */
	public function get_activejobs_list($request) {

		global $syllabplus;
	
		$download_status = empty($request['downloaders']) ? array() : $this->get_download_statuses(explode(':', $request['downloaders']));

		if (!empty($request['oneshot'])) {
			$job_id = get_site_option('syllab_oneshotnonce', false);
			// print_active_job() for one-shot jobs that aren't in cron
			$active_jobs = (false === $job_id) ? '' : $this->print_active_job($job_id, true);
		} elseif (!empty($request['thisjobonly'])) {
			// print_active_jobs() is for resumable jobs where we want the cron info to be included in the output
			$active_jobs = $this->print_active_jobs($request['thisjobonly']);
		} else {
			$active_jobs = $this->print_active_jobs();
		}
		$logupdate_array = array();
		if (!empty($request['log_fetch'])) {
			if (isset($request['log_nonce'])) {
				$log_nonce = $request['log_nonce'];
				$log_pointer = isset($request['log_pointer']) ? absint($request['log_pointer']) : 0;
				$logupdate_array = $this->fetch_log($log_nonce, $log_pointer);
			}
		}
		$res = array(
			// We allow the front-end to decide what to do if there's nothing logged - we used to (up to 1.11.29) send a pre-defined message
			'l' => esc_html(SyllabPlus_Options::get_syllab_lastmessage()),
			'j' => $active_jobs,
			'ds' => $download_status,
			'u' => $logupdate_array,
			'automatic_updates' => $syllabplus->is_automatic_updating_enabled()
		);

		$res['hosting_restriction'] = $syllabplus->is_hosting_backup_limit_reached();

		return $res;
	}
	
	/**
	 * Start a new backup
	 *
	 * @param Array			   $request
	 * @param Boolean|Callable $close_connection_callable
	 */
	public function request_backupnow($request, $close_connection_callable = false) {
		global $syllabplus;

		$abort_before_booting = false;
		$backupnow_nocloud = !empty($request['backupnow_nocloud']);
		
		$request['incremental'] = !empty($request['incremental']);

		$entities = !empty($request['onlythisfileentity']) ? explode(',', $request['onlythisfileentity']) : array();

		$remote_storage_instances = array();

		// if only_these_cloud_services is not an array then all connected remote storage locations are being backed up to and we don't need to do this
		if (!empty($request['only_these_cloud_services']) && is_array($request['only_these_cloud_services'])) {
			$remote_storage_locations = $request['only_these_cloud_services'];
			
			foreach ($remote_storage_locations as $key => $value) {
				/*
					This name key inside the value array is the remote storage method name prefixed by 31 characters (syllab_include_remote_service_) so we need to remove them to get the actual name, then the value key inside the value array has the instance id.
				*/
				$remote_storage_instances[substr($value['name'], 30)][$key] = $value['value'];
			}
		}

		$incremental = $request['incremental'] ? apply_filters('syllabplus_prepare_incremental_run', false, $entities) : false;

		// The call to backup_time_nonce() allows us to know the nonce in advance, and return it
		$nonce = $syllabplus->backup_time_nonce();

		$msg = array(
			'nonce' => $nonce,
			'm' => apply_filters('syllabplus_backupnow_start_message', '<strong>'.__('Start backup', 'syllabplus').':</strong> '.esc_html(__('OK. You should soon see activity in the "Last log message" field below.', 'syllabplus')), $nonce)
		);

		if (!empty($request['backup_nonce']) && 'current' != $request['backup_nonce']) $msg['nonce'] = $request['backup_nonce'];

		if (!empty($request['incremental']) && !$incremental) {
			$msg = array(
				'error' => __('No suitable backup set (that already contains a full backup of all the requested file component types) was found, to add increments to. Aborting this backup.', 'syllabplus')
			);
			$abort_before_booting = true;
		}

		if ($close_connection_callable && is_callable($close_connection_callable)) {
			call_user_func($close_connection_callable, $msg);
		} else {
			$syllabplus->close_browser_connection(json_encode($msg));
		}

		if ($abort_before_booting) die;
		
		$options = array('nocloud' => $backupnow_nocloud, 'use_nonce' => $nonce);
		if (!empty($request['onlythisfileentity']) && is_string($request['onlythisfileentity'])) {
			// Something to see in the 'last log' field when it first appears, before the backup actually starts
			$syllabplus->log(__('Start backup', 'syllabplus'));
			$options['restrict_files_to_override'] = explode(',', $request['onlythisfileentity']);
		}

		if ($request['incremental'] && !$incremental) {
			$syllabplus->log('An incremental backup was requested but no suitable backup found to add increments to; will proceed with a new backup');
			$request['incremental'] = false;
		}

		if (!empty($request['extradata'])) $options['extradata'] = $request['extradata'];

		if (!empty($remote_storage_instances)) $options['remote_storage_instances'] = $remote_storage_instances;
		
		$options['always_keep'] = !empty($request['always_keep']);

		$event = empty($request['backupnow_nofiles']) ? (empty($request['backupnow_nodb']) ? 'syllab_backupnow_backup_all' : 'syllab_backupnow_backup') : 'syllab_backupnow_backup_database';
		
		do_action($event, apply_filters('syllab_backupnow_options', $options, $request));
	}
	
	/**
	 * Get the contents of a log file
	 *
	 * @param String  $backup_nonce	 - the backup id; or empty, for the most recently modified
	 * @param Integer $log_pointer	 - the byte count to fetch from
	 * @param String  $output_format - the format to return in; allowed as 'html' (which will escape HTML entities in what is returned) and 'raw'
	 *
	 * @return String
	 */
	public function fetch_log($backup_nonce = '', $log_pointer = 0, $output_format = 'html') {
		global $syllabplus;

		if (empty($backup_nonce)) {
			list($mod_time, $log_file, $nonce) = $syllabplus->last_modified_log();// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		} else {
			$nonce = $backup_nonce;
		}

		if (!preg_match('/^[0-9a-f]+$/', $nonce)) die('Security check');
		
		$log_content = '';
		$new_pointer = $log_pointer;
		
		if (!empty($nonce)) {
			$syllab_dir = $syllabplus->backups_dir_location();

			$potential_log_file = $syllab_dir."/log.".$nonce.".txt";

			if (is_readable($potential_log_file)) {
				
				$templog_array = array();
				$log_file = fopen($potential_log_file, "r");
				if ($log_pointer > 0) fseek($log_file, $log_pointer);
				
				while (($buffer = fgets($log_file, 4096)) !== false) {
					$templog_array[] = $buffer;
				}
				if (!feof($log_file)) {
					$templog_array[] = __('Error: unexpected file read fail', 'syllabplus');
				}
				
				$new_pointer = ftell($log_file);
				$log_content = implode("", $templog_array);

				
			} else {
				$log_content .= __('The log file could not be read.', 'syllabplus');
			}

		} else {
			$log_content .= __('The log file could not be read.', 'syllabplus');
		}
		
		if ('html' == $output_format) $log_content = esc_html($log_content);
		
		$ret_array = array(
			'log' => $log_content,
			'nonce' => $nonce,
			'pointer' => $new_pointer
		);
		
		return $ret_array;
	}

	/**
	 * Get a count for the number of overdue cron jobs
	 *
	 * @return Integer - how many cron jobs are overdue
	 */
	public function howmany_overdue_crons() {
		$how_many_overdue = 0;
		if (function_exists('_get_cron_array') || (is_file(ABSPATH.WPINC.'/cron.php') && include_once(ABSPATH.WPINC.'/cron.php') && function_exists('_get_cron_array'))) {
			$crons = _get_cron_array();
			if (is_array($crons)) {
				$timenow = time();
				foreach ($crons as $jt => $job) {
					if ($jt < $timenow) $how_many_overdue++;
				}
			}
		}
		return $how_many_overdue;
	}

	public function get_php_errors($errno, $errstr, $errfile, $errline) {
		global $syllabplus;
		if (0 == error_reporting()) return true;
		$logline = $syllabplus->php_error_to_logline($errno, $errstr, $errfile, $errline);
		if (false !== $logline) $this->logged[] = $logline;
		// Don't pass it up the chain (since it's going to be output to the user always)
		return true;
	}

	private function download_status($timestamp, $type, $findex) {
		global $syllabplus;
		$response = array('m' => $syllabplus->jobdata_get('dlmessage_'.$timestamp.'_'.$type.'_'.$findex).'<br>');
		if ($file = $syllabplus->jobdata_get('dlfile_'.$timestamp.'_'.$type.'_'.$findex)) {
			if ('failed' == $file) {
				$response['e'] = __('Download failed', 'syllabplus').'<br>';
				$response['failed'] = true;
				$errs = $syllabplus->jobdata_get('dlerrors_'.$timestamp.'_'.$type.'_'.$findex);
				if (is_array($errs) && !empty($errs)) {
					$response['e'] .= '<ul class="disc">';
					foreach ($errs as $err) {
						if (is_array($err)) {
							$response['e'] .= '<li>'.esc_html($err['message']).'</li>';
						} else {
							$response['e'] .= '<li>'.esc_html($err).'</li>';
						}
					}
					$response['e'] .= '</ul>';
				}
			} elseif (preg_match('/^downloaded:(\d+):(.*)$/', $file, $matches) && file_exists($matches[2])) {
				$response['p'] = 100;
				$response['f'] = $matches[2];
				$response['s'] = (int) $matches[1];
				$response['t'] = (int) $matches[1];
				$response['m'] = __('File ready.', 'syllabplus');
				if ('db' != substr($type, 0, 2)) $response['can_show_contents'] = true;
			} elseif (preg_match('/^downloading:(\d+):(.*)$/', $file, $matches) && file_exists($matches[2])) {
				// Convert to bytes
				$response['f'] = $matches[2];
				$total_size = (int) max($matches[1], 1);
				$cur_size = filesize($matches[2]);
				$response['s'] = $cur_size;
				$file_age = time() - filemtime($matches[2]);
				if ($file_age > 20) $response['a'] = time() - filemtime($matches[2]);
				$response['t'] = $total_size;
				$response['m'] .= __("Download in progress", 'syllabplus').' ('.round($cur_size/1024).' / '.round(($total_size/1024)).' KB)';
				$response['p'] = round(100*$cur_size/$total_size);
			} else {
				$response['m'] .= __('No local copy present.', 'syllabplus');
				$response['p'] = 0;
				$response['s'] = 0;
				$response['t'] = 1;
			}
		}
		return $response;
	}

	/**
	 * Used with the WP filter upload_dir to adjust where uploads go to when uploading a backup
	 *
	 * @param Array $uploads - pre-filter array
	 *
	 * @return Array - filtered array
	 */
	public function upload_dir($uploads) {
		global $syllabplus;
		$syllab_dir = $syllabplus->backups_dir_location();
		if (is_writable($syllab_dir)) $uploads['path'] = $syllab_dir;
		return $uploads;
	}

	/**
	 * We do actually want to over-write
	 *
	 * @param  String $dir  Directory
	 * @param  String $name Name
	 * @param  String $ext  File extension
	 *
	 * @return String
	 */
	public function unique_filename_callback($dir, $name, $ext) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter use
		return $name.$ext;
	}

	public function sanitize_file_name($filename) {
		// WordPress 3.4.2 on multisite (at least) adds in an unwanted underscore
		return preg_replace('/-db(.*)\.gz_\.crypt$/', '-db$1.gz.crypt', $filename);
	}

	/**
	 * Runs upon the WordPress action plupload_action
	 */
	public function plupload_action() {

		global $syllabplus;
		if (function_exists('set_time_limit')) @set_time_limit(SYLLABPLUS_SET_TIME_LIMIT);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

		if (!SyllabPlus_Options::user_can_manage()) return;
		check_ajax_referer('syllab-uploader');

		$syllab_dir = $syllabplus->backups_dir_location();
		if (!@SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir)) {// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			echo json_encode(array('e' => sprintf(__("Backup directory (%s) is not writable, or does not exist.", 'syllabplus'), $syllab_dir).' '.__('You will find more information about this in the Settings section.', 'syllabplus')));
			exit;
		}
		
		add_filter('upload_dir', array($this, 'upload_dir'));
		add_filter('sanitize_file_name', array($this, 'sanitize_file_name'));
		// handle file upload

		$farray = array('test_form' => true, 'action' => 'plupload_action');

		$farray['test_type'] = false;
		$farray['ext'] = 'x-gzip';
		$farray['type'] = 'application/octet-stream';

		if (!isset($_POST['chunks'])) {
			$farray['unique_filename_callback'] = array($this, 'unique_filename_callback');
		}

		$status = wp_handle_upload(
			$_FILES['async-upload'],
			$farray
		);
		remove_filter('upload_dir', array($this, 'upload_dir'));
		remove_filter('sanitize_file_name', array($this, 'sanitize_file_name'));

		if (isset($status['error'])) {
			echo json_encode(array('e' => $status['error']));
			exit;
		}

		// If this was the chunk, then we should instead be concatenating onto the final file
		if (isset($_POST['chunks']) && isset($_POST['chunk']) && preg_match('/^[0-9]+$/', $_POST['chunk'])) {
		
			$final_file = basename(sanitize_text_field($_POST['name']));
			
			if (!rename($status['file'], $syllab_dir.'/'.$final_file.'.'.sanitize_text_field($_POST['chunk']).'.zip.tmp')) {
				@unlink($status['file']);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				echo json_encode(array('e' => sprintf(__('Error: %s', 'syllabplus'), __('This file could not be uploaded', 'syllabplus'))));
				exit;
			}
			
			$status['file'] = $syllab_dir.'/'.$final_file.'.'.sanitize_text_field($_POST['chunk']).'.zip.tmp';

		}

		$response = array();
		if (!isset($_POST['chunks']) || (isset($_POST['chunk']) && preg_match('/^[0-9]+$/', $_POST['chunk']) && $_POST['chunk'] == $_POST['chunks']-1) && isset($final_file)) {
			if (!preg_match('/^log\.[a-f0-9]{12}\.txt/i', $final_file) && !preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-([\-a-z]+)([0-9]+)?(\.(zip|gz|gz\.crypt))?$/i', $final_file, $matches)) {
				$accept = apply_filters('syllabplus_accept_archivename', array());
				if (is_array($accept)) {
					foreach ($accept as $acc) {
						if (preg_match('/'.$acc['pattern'].'/i', $final_file)) {
							$response['dm'] = sprintf(__('This backup was created by %s, and can be imported.', 'syllabplus'), $acc['desc']);
						}
					}
				}
				if (empty($response['dm'])) {
					if (isset($status['file'])) @unlink($status['file']);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
					echo json_encode(array('e' => sprintf(__('Error: %s', 'syllabplus'), __('Bad filename format - this does not look like a file created by SyllabPlus', 'syllabplus'))));
					exit;
				}
			} else {
				$backupable_entities = $syllabplus->get_backupable_file_entities(true);
				$type = isset($matches[3]) ? $matches[3] : '';
				if (!preg_match('/^log\.[a-f0-9]{12}\.txt/', $final_file) && 'db' != $type && !isset($backupable_entities[$type])) {
					if (isset($status['file'])) @unlink($status['file']);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
					echo json_encode(array('e' => sprintf(__('Error: %s', 'syllabplus'), sprintf(__('This looks like a file created by SyllabPlus, but this install does not know about this type of object: %s. Perhaps you need to install an add-on?', 'syllabplus'), esc_html($type)))));
					exit;
				}
			}
			
			// Final chunk? If so, then stich it all back together
			if (isset($_POST['chunk']) && $_POST['chunk'] == $_POST['chunks']-1 && !empty($final_file)) {
				if ($wh = fopen($syllab_dir.'/'.$final_file, 'wb')) {
					for ($i = 0; $i < $_POST['chunks']; $i++) {
						$rf = $syllab_dir.'/'.$final_file.'.'.$i.'.zip.tmp';
						if ($rh = fopen($rf, 'rb+')) {

							// April 1st 2020 - Due to a bug during uploads to Dropbox some backups had string "null" appended to the end which caused warnings, this removes the string "null" from these backups
							fseek($rh, -4, SEEK_END);
							$data = fgets($rh, 5);
							
							if ("null" === $data) {
								ftruncate($rh, filesize($rf) - 4);
							}

							fseek($rh, 0, SEEK_SET);
							
							while ($line = fread($rh, 262144)) {
								fwrite($wh, $line);
							}
							fclose($rh);
							@unlink($rf);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
						}
					}
					fclose($wh);
					$status['file'] = $syllab_dir.'/'.$final_file;
					if ('.tar' == substr($final_file, -4, 4)) {
						if (file_exists($status['file'].'.gz')) unlink($status['file'].'.gz');
						if (file_exists($status['file'].'.bz2')) unlink($status['file'].'.bz2');
					} elseif ('.tar.gz' == substr($final_file, -7, 7)) {
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-3))) unlink(substr($status['file'], 0, strlen($status['file'])-3));
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-3).'.bz2')) unlink(substr($status['file'], 0, strlen($status['file'])-3).'.bz2');
					} elseif ('.tar.bz2' == substr($final_file, -8, 8)) {
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-4))) unlink(substr($status['file'], 0, strlen($status['file'])-4));
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-4).'.gz')) unlink(substr($status['file'], 0, strlen($status['file'])-3).'.gz');
					}
				}
			}
			
		}

		// send the uploaded file url in response
		$response['m'] = $status['url'];
		echo json_encode($response);
		exit;
	}

	/**
	 * Database decrypter - runs upon the WP action plupload_action2
	 */
	public function plupload_action2() {

		if (function_exists('set_time_limit')) @set_time_limit(SYLLABPLUS_SET_TIME_LIMIT);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		global $syllabplus;

		if (!SyllabPlus_Options::user_can_manage()) return;
		check_ajax_referer('syllab-uploader');

		$syllab_dir = $syllabplus->backups_dir_location();
		if (!is_writable($syllab_dir)) exit;

		add_filter('upload_dir', array($this, 'upload_dir'));
		add_filter('sanitize_file_name', array($this, 'sanitize_file_name'));
		// handle file upload

		$farray = array('test_form' => true, 'action' => 'plupload_action2');

		$farray['test_type'] = false;
		$farray['ext'] = 'crypt';
		$farray['type'] = 'application/octet-stream';

		if (isset($_POST['chunks'])) {
			// $farray['ext'] = 'zip';
			// $farray['type'] = 'application/zip';
		} else {
			$farray['unique_filename_callback'] = array($this, 'unique_filename_callback');
		}

		$status = wp_handle_upload(
			$_FILES['async-upload'],
			$farray
		);
		remove_filter('upload_dir', array($this, 'upload_dir'));
		remove_filter('sanitize_file_name', array($this, 'sanitize_file_name'));

		if (isset($status['error'])) die('ERROR: '.$status['error']);

		// If this was the chunk, then we should instead be concatenating onto the final file
		if (isset($_POST['chunks']) && isset($_POST['chunk']) && preg_match('/^[0-9]+$/', $_POST['chunk'])) {
			$final_file = basename(sanitize_text_field($_POST['name']));
			rename($status['file'], $syllab_dir.'/'.$final_file.'.'.sanitize_text_field($_POST['chunk']).'.zip.tmp');
			$status['file'] = $syllab_dir.'/'.$final_file.'.'.sanitize_text_field($_POST['chunk']).'.zip.tmp';
		}

		if (!isset($_POST['chunks']) || (isset($_POST['chunk']) && $_POST['chunk'] == $_POST['chunks']-1)) {
			if (!preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-db([0-9]+)?\.(gz\.crypt)$/i', $final_file)) {

				@unlink($status['file']);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				echo 'ERROR:'.__('Bad filename format - this does not look like an encrypted database file created by SyllabPlus', 'syllabplus');
				exit;
			}
			
			// Final chunk? If so, then stich it all back together
			if (isset($_POST['chunk']) && $_POST['chunk'] == $_POST['chunks']-1 && isset($final_file)) {
				if ($wh = fopen($syllab_dir.'/'.$final_file, 'wb')) {
					for ($i=0; $i<$_POST['chunks']; $i++) {
						$rf = $syllab_dir.'/'.$final_file.'.'.$i.'.zip.tmp';
						if ($rh = fopen($rf, 'rb')) {
							while ($line = fread($rh, 32768)) {
								fwrite($wh, $line);
							}
							fclose($rh);
							@unlink($rf);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
						}
					}
					fclose($wh);
				}
			}
			
		}

		// send the uploaded file url in response
		if (isset($final_file)) echo 'OK:'.$final_file;
		exit;
	}

	/**
	 * Include the settings header template
	 */
	public function settings_header() {
		$this->include_template('wp-admin/settings/header.php');
	}

	/**
	 * Include the settings footer template
	 */
	public function settings_footer() {
		$this->include_template('wp-admin/settings/footer.php');
	}

	/**
	 * Output the settings page content. Will also run a restore if $_REQUEST so indicates.
	 */
	public function settings_output() {

		if (false == ($render = apply_filters('syllabplus_settings_page_render', true))) {
			do_action('syllabplus_settings_page_render_abort', $render);
			return;
		}

		do_action('syllabplus_settings_page_init');

		global $syllabplus;

		/**
		 * We use request here because the initial restore is triggered by a POSTed form. we then may need to obtain credential for the WP_Filesystem. to do this WP outputs a form, but we don't pass our parameters via that. So the values are passed back in as GET parameters.
		 */
		if (isset($_REQUEST['action']) && (('syllab_restore' == sanitize_text_field($_REQUEST['action']) && isset($_REQUEST['backup_timestamp'])) || ('syllab_restore_continue' == sanitize_text_field($_REQUEST['action']) && !empty($_REQUEST['job_id'])))) {
			$this->prepare_restore();
			return;
		}

		if (isset($_REQUEST['action']) && 'syllab_delete_old_dirs' == sanitize_text_field($_REQUEST['action'])) {
			$nonce = empty(sanitize_text_field($_REQUEST['syllab_delete_old_dirs_nonce'])) ? '' : sanitize_text_field($_REQUEST['syllab_delete_old_dirs_nonce']);
			if (!wp_verify_nonce($nonce, 'syllabplus-credentialtest-nonce')) die('Security check');
			$this->delete_old_dirs_go();
			return;
		}

		if (!empty($_REQUEST['action']) && 'syllabplus_broadcastaction' == $_REQUEST['action'] && !empty($_REQUEST['subaction'])) {
			$nonce = (empty(sanitize_text_field($_REQUEST['nonce']))) ? "" : sanitize_text_field($_REQUEST['nonce']);
			if (!wp_verify_nonce($nonce, 'syllabplus-credentialtest-nonce')) die('Security check');
			do_action(sanitize_text_field($_REQUEST['subaction']));
			return;
		}

		if (isset($_GET['error'])) {
			// This is used by Microsoft OneDrive authorisation failures (May 15). I am not sure what may have been using the 'error' GET parameter otherwise - but it is harmless.
			if (!empty($_GET['error_description'])) {
				$this->show_admin_warning(esc_html($_GET['error_description']).' ('.esc_html($_GET['error']).')', 'error');
			} else {
				$this->show_admin_warning(esc_html(sanitize_text_field($_GET['error'])), 'error');
			}
		}

		if (isset($_GET['message'])) $this->show_admin_warning(esc_html(sanitize_text_field($_GET['message'])));

		if (isset($_GET['action']) && 'syllab_create_backup_dir' == sanitize_text_field($_GET['action']) && isset($_GET['nonce']) && wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'create_backup_dir')) {
			$created = $this->create_backup_dir();
			if (is_wp_error($created)) {
				echo '<p>'.__('Backup directory could not be created', 'syllabplus').'...<br>';
				echo '<ul class="disc">';
				foreach ($created->get_error_messages() as $msg) {
					echo '<li>'.esc_html($msg).'</li>';
				}
				echo '</ul></p>';
			} elseif (false !== $created) {
				echo '<p>'.__('Backup directory successfully created.', 'syllabplus').'</p><br>';
			}
			echo '<b>'.__('Actions', 'syllabplus').':</b> <a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus">'.__('Return to SyllabPlus configuration', 'syllabplus').'</a>';
			return;
		}

		echo '<div id="syllab_backup_started" class="updated syllab-hidden" style="display:none;"></div>';

		// This opens a div
		$this->settings_header();
		?>

			<div id="syllab-hidethis">
			<p>
			<strong><?php _e('Warning:', 'syllabplus'); ?> <?php _e("If you can still read these words after the page finishes loading, then there is a JavaScript or jQuery problem in the site.", 'syllabplus'); ?></strong>

			<?php if (false !== strpos(basename(SYLLABPLUS_URL), ' ')) { ?>
				<strong><?php _e('The SyllabPlus directory in wp-content/plugins has white-space in it; WordPress does not like this. You should rename the directory to wp-content/plugins/syllabplus to fix this problem.', 'syllabplus');?></strong>
			<?php } else { ?>
				<a href="<?php echo apply_filters('syllabplus_com_link', "https://syllabplus.com/do-you-have-a-javascript-or-jquery-error/");?>" target="_blank"><?php _e('Go here for more information.', 'syllabplus'); ?></a>
			<?php } ?>
			</p>
			</div>

			<?php

			$include_deleteform_div = true;

			// Opens a div, which needs closing later
			if (isset($_GET['syllab_restore_success'])) {

				if (get_template() === 'optimizePressTheme' || is_plugin_active('optimizePressPlugin') || is_plugin_active_for_network('optimizePressPlugin')) {
					$this->show_admin_warning("<a href='https://optimizepress.zendesk.com/hc/en-us/articles/203699826-Update-URL-References-after-moving-domain' target='_blank'>" . __("OptimizePress 2.0 encodes its contents, so search/replace does not work.", "syllabplus") . ' ' . __("To fix this problem go here.", "syllabplus") . "</a>", "notice notice-warning");
				}
				$success_advert = (isset($_GET['pval']) && 0 == $_GET['pval'] && !$syllabplus->have_addons) ? '<p>'.__('For even more features and personal support, check out ', 'syllabplus').'<strong><a href="'.apply_filters("syllabplus_com_link", 'https://syllabplus.com/shop/syllabplus-premium/').'" target="_blank">SyllabPlus Premium</a>.</strong></p>' : "";

				echo "<div class=\"updated backup-restored\"><span><strong>".__('Your backup has been restored.', 'syllabplus').'</strong></span><br>';
				// Unnecessary - will be advised of this below
				// if (2 == $_GET['syllab_restore_success']) echo ' '.__('Your old (themes, uploads, plugins, whatever) directories have been retained with "-old" appended to their name. Remove them when you are satisfied that the backup worked properly.');
				echo $success_advert;
				$include_deleteform_div = false;

			}

			if ($this->scan_old_dirs(true)) $this->print_delete_old_dirs_form(true, $include_deleteform_div);

			// Close the div opened by the earlier section
			if (isset($_GET['syllab_restore_success'])) echo '</div>';

			if (empty($success_advert) && empty($this->no_settings_warning)) {

				if (!class_exists('SyllabPlus_Notices')) include_once(SYLLABPLUS_DIR.'/includes/syllabplus-notices.php');
				global $syllabplus_notices;
				
				$backup_history = SyllabPlus_Backup_History::get_history();
				$review_dismiss = SyllabPlus_Options::get_syllab_option('dismissed_review_notice', 0);
				$backup_dir = $syllabplus->backups_dir_location();
				// N.B. Not an exact proxy for the installed time; they may have tweaked the expert option to move the directory
				$installed = @filemtime($backup_dir.'/index.html');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				$installed_for = time() - $installed;

				$advert = false;
				if (!empty($backup_history) && $installed && time() > $review_dismiss && $installed_for > 28*86400 && $installed_for < 84*86400) {
					$advert = 'rate';
				}

				$syllabplus_notices->do_notice($advert);
			}

			if (!$syllabplus->memory_check(64)) {
				// HS8390 - A case where SyllabPlus::memory_check_current() returns -1
				$memory_check_current = $syllabplus->memory_check_current();
				if ($memory_check_current > 0) {
				?>
					<div class="updated memory-limit"><?php _e('Your PHP memory limit (set by your web hosting company) is very low. SyllabPlus attempted to raise it but was unsuccessful. This plugin may struggle with a memory limit of less than 64 Mb  - especially if you have very large files uploaded (though on the other hand, many sites will be successful with a 32Mb limit - your experience may vary).', 'syllabplus');?> <?php _e('Current limit is:', 'syllabplus');?> <?php echo $syllabplus->memory_check_current(); ?> MB</div>
				<?php }
			}


			if (!empty($syllabplus->errors)) {
				echo '<div class="error syllab_list_errors">';
				$syllabplus->list_errors();
				echo '</div>';
			}

			$backup_history = SyllabPlus_Backup_History::get_history();
			if (empty($backup_history)) {
				SyllabPlus_Backup_History::rebuild();
				$backup_history = SyllabPlus_Backup_History::get_history();
			}

			$tabflag = 'backups';
			$main_tabs = $this->get_main_tabs_array();
			
			if (isset($_REQUEST['tab'])) {
				$request_tab = sanitize_text_field($_REQUEST['tab']);
				$valid_tabflags = array_keys($main_tabs);
				if (in_array($request_tab, $valid_tabflags)) {
					$tabflag = $request_tab;
				} else {
					$tabflag = 'backups';
				}
			}
			
			$this->include_template('wp-admin/settings/tab-bar.php', false, array('main_tabs' => $main_tabs, 'backup_history' => $backup_history, 'tabflag' => $tabflag));
		?>
		
		<div id="syllab-poplog" >
			<pre id="syllab-poplog-content"></pre>
		</div>
		
		<?php
			$this->include_template('wp-admin/settings/delete-and-restore-modals.php');
		?>
		
		<div id="syllab-navtab-backups-content" <?php if ('backups' != $tabflag) echo 'class="syllab-hidden"'; ?> style="<?php if ('backups' != $tabflag) echo 'display:none;'; ?>">
			<?php
				$is_opera = (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') || false !== strpos($_SERVER['HTTP_USER_AGENT'], 'OPR/'));
				$tmp_opts = array('include_opera_warning' => $is_opera);
				$this->include_template('wp-admin/settings/tab-backups.php', false, array('backup_history' => $backup_history, 'options' => $tmp_opts));
				$this->include_template('wp-admin/settings/upload-backups-modal.php');
			?>
		</div>
		
		<div id="syllab-navtab-migrate-content"<?php if ('migrate' != $tabflag) echo ' class="syllab-hidden"'; ?> style="<?php if ('migrate' != $tabflag) echo 'display:none;'; ?>">
			<?php
			if (has_action('syllabplus_migrate_tab_output')) {
				do_action('syllabplus_migrate_tab_output');
			} else {
				$this->include_template('wp-admin/settings/migrator-no-migrator.php');
			}
			?>
		</div>
		
		<div id="syllab-navtab-settings-content" <?php if ('settings' != $tabflag) echo 'class="syllab-hidden"'; ?> style="<?php if ('settings' != $tabflag) echo 'display:none;'; ?>">
			<h2 class="syllab_settings_sectionheading"><?php _e('Backup Contents And Schedule', 'syllabplus');?></h2>
			<?php SyllabPlus_Options::options_form_begin(); ?>
				<?php $this->settings_formcontents(); ?>
			</form>
			<?php
				$our_keys = SyllabPlus_Options::get_syllab_option('syllab_central_localkeys');
				if (!is_array($our_keys)) $our_keys = array();

				// Hide the SyllabCentral Cloud wizard If the user already has a key created for either
				// syllabplus.com or self hosted version.
				if (empty($our_keys)) {
			?>
			<?php
				}
			?>
		</div>

		<div id="syllab-navtab-expert-content"<?php if ('expert' != $tabflag) echo ' class="syllab-hidden"'; ?> style="<?php if ('expert' != $tabflag) echo 'display:none;'; ?>">
			<?php $this->settings_advanced_tools(); ?>
		</div>

		<div id="syllab-navtab-addons-content"<?php if ('addons' != $tabflag) echo ' class="syllab-hidden"'; ?> style="<?php if ('addons' != $tabflag) echo 'display:none;'; ?>">
		
			<?php
				$tab_addons = $this->include_template('wp-admin/settings/tab-addons.php', true, array('tabflag' => $tabflag));
				
				echo apply_filters('syllabplus_addonstab_content', $tab_addons);
				
			?>
		
		</div>
		
		<?php
		do_action('syllabplus_after_main_tab_content', $tabflag);
		// settings_header() opens a div
		$this->settings_footer();
	}
	
	/**
	 * Get main tabs array
	 *
	 * @return Array Array which have key as a tab key and value as tab label
	 */
	private function get_main_tabs_array() {
		return apply_filters(
			'syllabplus_main_tabs',
			array(
				'backups' => __('Backup', 'syllabplus'),
				'settings' => __('Settings', 'syllabplus')
			)
		);
	}

	/**
	 * Potentially register an action for showing restore progress
	 */
	private function print_restore_in_progress_box_if_needed() {
		global $syllabplus;
		$check_restore_progress = $syllabplus->check_restore_progress();
		// Check to see if the restore is still in progress
		if (is_array($check_restore_progress) && true == $check_restore_progress['status']) {

			$restore_jobdata = $check_restore_progress['restore_jobdata'];
			$restore_jobdata['jobid'] = $check_restore_progress['restore_in_progress'];
			$this->restore_in_progress_jobdata = $restore_jobdata;

			add_action('all_admin_notices', array($this, 'show_admin_restore_in_progress_notice'));
		}
	}

	/**
	 * This function is called via the command class, it will get the resume restore notice to be shown when a restore is taking place over AJAX
	 *
	 * @param string $job_id - the id of the job
	 *
	 * @return WP_Error|string - can return a string containing html or a WP_Error
	 */
	public function get_restore_resume_notice($job_id) {
		global $syllabplus;

		if (empty($job_id)) return new WP_Error('missing_parameter', 'Missing parameters.');
		
		$restore_jobdata = $syllabplus->jobdata_getarray($job_id);

		if (!is_array($restore_jobdata) && empty($restore_jobdata)) return new WP_Error('missing_jobdata', 'Job data not found.');

		$restore_jobdata['jobid'] = $job_id;
		$this->restore_in_progress_jobdata = $restore_jobdata;

		$html = $this->show_admin_restore_in_progress_notice(true, true);

		if (empty($html)) return new WP_Error('job_aborted', 'Job aborted.');

		return $html;
	}

	/**
	 * If added, then runs upon the WP action all_admin_notices, or can be called via get_restore_resume_notice() for when a restore is running over AJAX
	 *
	 * @param Boolean $return_instead_of_echo - indicates if we want to add the tfa UI
	 * @param Boolean $exclude_js             - indicates if we want to exclude the js in the returned html
	 *
	 * @return void|string - can return a string containing html or echo the html to page
	 */
	public function show_admin_restore_in_progress_notice($return_instead_of_echo = false, $exclude_js = false) {
	
		if (isset($_REQUEST['action']) && 'syllab_restore_abort' === $_REQUEST['action'] && !empty($_REQUEST['job_id'])) {
			delete_site_option('syllab_restore_in_progress');
			return;
		}
	
		$restore_jobdata = $this->restore_in_progress_jobdata;
		$seconds_ago = time() - (int) $restore_jobdata['job_time_ms'];
		$minutes_ago = floor($seconds_ago/60);
		$seconds_ago = $seconds_ago - $minutes_ago*60;
		$time_ago = sprintf(__("%s minutes, %s seconds", 'syllabplus'), $minutes_ago, $seconds_ago);

		$html = '<div class="updated show_admin_restore_in_progress_notice">';
		$html .= '<span class="unfinished-restoration"><strong>SyllabPlus: '.__('Unfinished restoration', 'syllabplus').'</strong></span><br>';
		$html .= '<p>'.sprintf(__('You have an unfinished restoration operation, begun %s ago.', 'syllabplus'), $time_ago).'</p>';
		$html .= '<form method="post" action="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus">';
		$html .= wp_nonce_field('syllabplus-credentialtest-nonce');
		$html .= '<input id="syllab_restore_continue_action" type="hidden" name="action" value="syllab_restore_continue">';
		$html .= '<input type="hidden" name="syllabplus_ajax_restore" value="continue_ajax_restore">';
		$html .= '<input type="hidden" name="job_id" value="'.$restore_jobdata['jobid'].'" value="'.esc_attr($restore_jobdata['jobid']).'">';

		if ($exclude_js) {
			$html .= '<button id="syllab_restore_resume" type="submit" class="button-primary">'.__('Continue restoration', 'syllabplus').'</button>';
		} else {
			$html .= '<button id="syllab_restore_resume" onclick="jQuery(\'#syllab_restore_continue_action\').val(\'syllab_restore_continue\'); jQuery(this).parent(\'form\').trigger(\'submit\');" type="submit" class="button-primary">'.__('Continue restoration', 'syllabplus').'</button>';
		}
		$html .= '<button id="syllab_restore_abort" onclick="jQuery(\'#syllab_restore_continue_action\').val(\'syllab_restore_abort\'); jQuery(this).parent(\'form\').trigger(\'submit\');" class="button-secondary">'.__('Dismiss', 'syllabplus').'</button>';

		$html .= '</form></div>';

		if ($return_instead_of_echo) return $html;

		echo $html;
	}

	/**
	 * This method will build the SyllabPlus.com login form and echo it to the page.
	 *
	 * @param String  $option_page			  - the option page this form is being output to
	 * @param Boolean $tfa					  - indicates if we want to add the tfa UI
	 * @param Boolean $include_form_container - indicates if we want the form container
	 * @param Array	  $further_options		  - other options (see below for the possibilities + defaults)
	 *
	 * @return void
	 */
	public function build_credentials_form($option_page, $tfa = false, $include_form_container = true, $further_options = array()) {
	
		global $syllabplus;

		$further_options = wp_parse_args($further_options, array(
			'under_username' => __("Not yet got an account (it's free)? Go get one!", 'syllabplus'),
			'under_username_link' => $syllabplus->get_url('my-account')
		));
		
		if ($include_form_container) {
			$enter_credentials_begin = SyllabPlus_Options::options_form_begin('', false, array(), 'syllabplus_com_login');
			if (is_multisite()) $enter_credentials_begin .= '<input type="hidden" name="action" value="update">';
		} else {
			$enter_credentials_begin = '<div class="syllabplus_com_login">';
		}

		$interested = esc_html(__('Interested in knowing about your SyllabPlus.Com password security? Read about it here.', 'syllabplus'));

		$connect = esc_html(__('Connect', 'syllabplus'));

		$enter_credentials_end = '<p class="syllab-after-form-table">';
		
		if ($include_form_container) {
			$enter_credentials_end .= '<input type="submit" class="button-primary ud_connectsubmit" value="'.$connect.'"  />';
		} else {
			$enter_credentials_end .= '<button class="button-primary ud_connectsubmit">'.$connect.'</button>';
		}
		
		$enter_credentials_end .= '<span class="syllabplus_spinner spinner">' . __('Processing', 'syllabplus') . '...</span></p>';

		$enter_credentials_end .= '<p class="syllab-after-form-table" style="font-size: 70%"><em><a href="https://syllabplus.com/faqs/tell-me-about-my-syllabplus-com-account/" target="_blank">'.$interested.'</a></em></p>';

		$enter_credentials_end .= $include_form_container ? '</form>' : '</div>';

		echo $enter_credentials_begin;

		$options = apply_filters('syllabplus_com_login_options', array("email" => "", "password" => ""));

		if ($include_form_container) {
			// We have to duplicate settings_fields() in order to set our referer
			// settings_fields(UDADDONS2_SLUG.'_options');

			$option_group = $option_page.'_options';
			echo "<input type='hidden' name='option_page' value='" . esc_attr($option_group) . "' />";
			echo '<input type="hidden" name="action" value="update" />';

			// wp_nonce_field("$option_group-options");

			// This one is used on multisite
			echo '<input type="hidden" name="tab" value="addons" />';

			$name = "_wpnonce";
			$action = esc_attr($option_group."-options");
			$nonce_field = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce($action) . '" />';
		
			echo esc_html($nonce_field);

			$referer = esc_attr(SyllabPlus_Manipulation_Functions::wp_unslash($_SERVER['REQUEST_URI']));

			// This one is used on single site installs
			if (false === strpos($referer, '?')) {
				$referer .= '?tab=addons';
			} else {
				$referer .= '&tab=addons';
			}

			echo '<input type="hidden" name="_wp_http_referer" value="'.esc_html($referer).'" />';
			// End of duplication of settings-fields()
		}
		?>

		<h2> <?php _e('Connect with your SyllabPlus.Com account', 'syllabplus'); ?></h2>
		<p class="syllabplus_com_login_status"></p>

		<table class="form-table">
			<tbody>
				<tr class="non_tfa_fields">
					<th><?php _e('Email', 'syllabplus'); ?></th>
					<td>
						<label for="<?php echo esc_html($option_page); ?>_options_email">
							<input id="<?php echo esc_html($option_page); ?>_options_email" type="text" size="36" name="<?php echo esc_html($option_page); ?>_options[email]" value="<?php echo esc_html($options['email']); ?>" />
							<br/>
							<a target="_blank" href="<?php echo esc_html($further_options['under_username_link']); ?>"><?php echo $further_options['under_username']; ?></a>
						</label>
					</td>
				</tr>
				<tr class="non_tfa_fields">
					<th><?php _e('Password', 'syllabplus'); ?></th>
					<td>
						<label for="<?php echo esc_html($option_page); ?>_options_password">
							<input id="<?php echo esc_html($option_page); ?>_options_password" type="password" size="36" name="<?php echo esc_html($option_page); ?>_options[password]" value="<?php echo empty($options['password']) ? '' : esc_html($options['password']); ?>" />
							<br/>
							<a target="_blank" href="<?php echo $syllabplus->get_url('lost-password'); ?>"><?php _e('Forgotten your details?', 'syllabplus'); ?></a>
						</label>
					</td>
				</tr>
				<?php
				if ('syllabplus-addons' == $option_page) {
				?>
					<tr class="non_tfa_fields">
						<th></th>
						<td>
							<label>
								<input type="checkbox" id="<?php echo esc_html($option_page); ?>_options_auto_updates" data-syllab_settings_test="syllab_auto_updates" name="<?php echo esc_html($option_page); ?>_options[syllab_auto_update]" value="1" <?php if ($syllabplus->is_automatic_updating_enabled()) echo 'checked="checked"'; ?> />
								<?php _e('Ask WordPress to update SyllabPlus automatically when an update is available', 'syllabplus');?>
							</label>
							<?php
								$our_keys = SyllabPlus_Options::get_syllab_option('syllab_central_localkeys');
								if (!is_array($our_keys)) $our_keys = array();
				
								if (empty($our_keys)) :
								?>
									<p class="<?php echo esc_html($option_page); ?>-connect-to-udc">
										<label>
											<input type="checkbox" id="<?php echo esc_html($option_page); ?>_options_auto_udc_connect" name="<?php echo $option_page; ?>_options[syllab_auto_udc_connect]" value="1" checked="checked" />
											<?php _e('Add this website to SyllabCentral (remote, centralised control) - free for up to 5 sites.', 'syllabplus'); ?> <a target="_blank" href="https://syllabcentral.com"><?php _e('Learn more about SyllabCentral', 'syllabplus'); ?></a>
										</label>
									</p>

								<?php endif; ?>

						</td>
					</tr>
					<?php
				}
				?>
				<?php
				if (isset($further_options['terms_and_conditions']) && isset($further_options['terms_and_conditions_link'])) {
				?>
					<tr class="non_tfa_fields">
						<th></th>
						<td>
							<input type="checkbox" class="<?php echo esc_html($option_page); ?>_terms_and_conditions" name="<?php echo esc_html($option_page); ?>_terms_and_conditions" value="1">
							<a target="_blank" href="<?php echo esc_html($further_options['terms_and_conditions_link']); ?>"><?php echo esc_html($further_options['terms_and_conditions']); ?></a>
						</td>
					</tr>
					<?php
				}
				?>
				<?php if ($tfa) { ?>
				<tr class="tfa_fields" style="display:none;">
					<th><?php _e('One Time Password (check your OTP app to get this password)', 'syllabplus'); ?></th>
					<td>
						<label for="<?php echo esc_html($option_page); ?>_options_two_factor_code">
							<input id="<?php echo esc_html($option_page); ?>_options_two_factor_code" type="text" size="10" name="<?php echo $option_page; ?>_options[two_factor_code]" />
						</label>
					</td>
				</tr>	
				<?php } ?>
			</tbody>
		</table>

		<?php

		echo $enter_credentials_end;
	}

	/**
	 * Return widgetry for the 'backup now' modal.
	 * Don't optimise this method away; it's used by third-party plugins (e.g. EUM).
	 *
	 * @return String
	 */
	public function backupnow_modal_contents() {
		return $this->include_template('wp-admin/settings/backupnow-modal.php', true);
	}
	
	/**
	 * Also used by the auto-backups add-on
	 *
	 * @param  Boolean $wide_format       Whether to return data in a wide format
	 * @param  Boolean $print_active_jobs Whether to include currently active jobs
	 * @return String - the HTML output
	 */
	public function render_active_jobs_and_log_table($wide_format = false, $print_active_jobs = true) {
		global $syllabplus;
	?>
		<div id="syllab_activejobs_table">
			<?php $active_jobs = ($print_active_jobs) ? $this->print_active_jobs() : '';?>
			<div id="syllab_activejobsrow" class="<?php
				if (!$active_jobs && !$wide_format) {
					echo 'hidden';
				}
				if ($wide_format) {
					echo ".minimum-height";
				}
			?>">
				<div id="syllab_activejobs" class="<?php echo esc_html($wide_format) ? 'wide-format' : ''; ?>">
					<?php echo esc_html($active_jobs);?>
				</div>
			</div>
		<?php /* ?>	<div id="syllab_lastlogmessagerow" >
				<?php if ($wide_format) {
					// Hide for now - too ugly
					?>
					<div class="last-message"><strong><?php _e('Last log message', 'syllabplus');?>:</strong><br>
						<span id="syllab_lastlogcontainer"><?php echo esc_html(SyllabPlus_Options::get_syllab_lastmessage()); ?></span><br>
						<?php //$this->most_recently_modified_log_link(); ?>
					</div>
				<?php } else { ?>
					<div>
						<strong><?php _e('Last log message', 'syllabplus');?>:</strong>
						<span id="syllab_lastlogcontainer"><?php echo esc_html(SyllabPlus_Options::get_syllab_lastmessage()); ?></span><br>
						<?php //$this->most_recently_modified_log_link(); ?>
					</div>
				<?php } ?>
			</div> <?php */ ?>
			<?php
			// Currently disabled - not sure who we want to show this to
			if (1==0 && !defined('SYLLABPLUS_NOADS_B')) {
				$feed = $syllabplus->get_syllabplus_rssfeed();
				if (is_a($feed, 'SimplePie')) {
					echo '<tr><th style="vertical-align:top;">'.__('Latest SyllabPlus.com news:', 'syllabplus').'</th><td class="syllab_simplepie">';
					echo '<ul class="disc;">';
					foreach ($feed->get_items(0, 5) as $item) {
						echo '<li>';
						echo '<a href="'.esc_attr($item->get_permalink()).'">';
						echo esc_html($item->get_title());
						// D, F j, Y H:i
						echo "</a> (".esc_html($item->get_date('j F Y')).")";
						echo '</li>';
					}
					echo '</ul></td></tr>';
				}
			}
		?>
		</div>
		<?php
	}

	/**
	 * Output directly a link allowing download of the most recently modified log file
	 */
	private function most_recently_modified_log_link() {

		global $syllabplus;
		list($mod_time, $log_file, $nonce) = $syllabplus->last_modified_log();// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		
		?>
			<a href="?page=syllabplus&amp;action=downloadlatestmodlog&amp;wpnonce=<?php echo wp_create_nonce('syllabplus_download'); ?>" <?php if (!$mod_time) echo 'style="display:none;"'; ?> class="syllab-log-link" onclick="event.preventDefault(); syllab_popuplog('');"><?php _e('Download most recently modified log file', 'syllabplus');?></a>
		<?php
	}
	
	public function settings_downloading_and_restoring($backup_history = array(), $return_result = false, $options = array()) {
		return $this->include_template('wp-admin/settings/downloading-and-restoring.php', $return_result, array('backup_history' => $backup_history, 'options' => $options));
	}
	
	/**
	 * Renders take backup content
	 */
	public function take_backup_content() {
		global $syllabplus;
		$syllab_dir = $syllabplus->backups_dir_location();
		$backup_disabled = SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir) ? '' : 'disabled="disabled"';
		$this->include_template('wp-admin/settings/take-backup.php', false, array('backup_disabled' => $backup_disabled));
	}

	/**
	 * Output a table row using the syllab_debugrow class
	 *
	 * @param String $head	  - header cell contents
	 * @param String $content - content cell contents
	 */
	public function settings_debugrow($head, $content) {
		echo "<tr class=\"syllab_debugrow\"><th>$head</th><td>$content</td></tr>";
	}

	public function settings_advanced_tools($return_instead_of_echo = false, $pass_through = array()) {
		return $this->include_template('wp-admin/advanced/advanced-tools.php', $return_instead_of_echo, $pass_through);
	}

	/**
	 * Paint the HTML for the form for deleting old directories
	 *
	 * @param Boolean $include_blurb - whether to include explanatory text
	 * @param Boolean $include_div	 - whether to wrap inside a div tag
	 */
	public function print_delete_old_dirs_form($include_blurb = true, $include_div = true) {
		if ($include_blurb) {
			if ($include_div) {
				echo '<div id="syllab_delete_old_dirs_pagediv" class="updated delete-old-directories">';
			}
			echo '<p>'.__('Your WordPress install has old directories from its state before you restored/migrated (technical information: these are suffixed with -old). You should press this button to delete them as soon as you have verified that the restoration worked.', 'syllabplus').'</p>';
		}
		?>
		<form method="post" action="<?php echo esc_url(add_query_arg(array('error' => false, 'syllab_restore_success' => false, 'action' => false, 'page' => 'syllabplus'), SyllabPlus_Options::admin_page_url())); ?>">
			<?php wp_nonce_field('syllabplus-credentialtest-nonce', 'syllab_delete_old_dirs_nonce'); ?>
			<input type="hidden" name="action" value="syllab_delete_old_dirs">
			<input type="submit" class="button-primary" value="<?php echo esc_attr(__('Delete Old Directories', 'syllabplus'));?>">
		</form>
		<?php
		if ($include_blurb && $include_div) echo '</div>';
	}

	/**
	 * Return cron status information about a specified in-progress job
	 *
	 * @param Boolean|String $job_id - the job to get information about; or, if not specified, all jobs
	 *
	 * @return Array|Boolean - the requested information, or false if it was not found. Format differs depending on whether info on all jobs, or a single job, was requested.
	 */
	public function get_cron($job_id = false) {
	
		$cron = get_option('cron');
		if (!is_array($cron)) $cron = array();
		if (false === $job_id) return $cron;

		foreach ($cron as $time => $job) {
			if (!isset($job['syllab_backup_resume'])) continue;
			foreach ($job['syllab_backup_resume'] as $info) {
				if (isset($info['args'][1]) && $job_id == $info['args'][1]) {
					global $syllabplus;
					$jobdata = $syllabplus->jobdata_getarray($job_id);
					return is_array($jobdata) ? array($time, $jobdata) : false;
				}
			}
		}
	}

	/**
	 * Gets HTML describing the active jobs
	 *
	 * @param  Boolean $this_job_only A value for $this_job_only also causes something non-empty to always be returned (to allow detection of the job having started on the front-end)
	 *
	 * @return String - the HTML
	 */
	private function print_active_jobs($this_job_only = false) {
		$cron = $this->get_cron();
		$ret = '';

		foreach ($cron as $time => $job) {
			if (!isset($job['syllab_backup_resume'])) continue;
			foreach ($job['syllab_backup_resume'] as $info) {
				if (isset($info['args'][1])) {
					$job_id = $info['args'][1];
					if (false === $this_job_only || $job_id == $this_job_only) {
						$ret .= $this->print_active_job($job_id, false, $time, $info['args'][0]);
					}
				}
			}
		}
		// A value for $this_job_only implies that output is required
		if (false !== $this_job_only && !$ret) {
			$ret = $this->print_active_job($this_job_only);
			if ('' == $ret) {
				global $syllabplus;
				$log_file = $syllabplus->get_logfile_name($this_job_only);
				// if the file exists, the backup was booted. Check if the information about completion is found in the log, or if it was modified at least 2 minutes ago.
				if (file_exists($log_file) && ($syllabplus->found_backup_complete_in_logfile($this_job_only) || (time() - filemtime($log_file)) > 120)) {
					// The presence of the exact ID matters to the front-end - indicates that the backup job has at least begun
					//$ret = '<div class="active-jobs syllab_finished" id="syllab-jobid-'.$this_job_only.'"><em>'.__('The backup has finished running', 'syllabplus').'</em> - <a class="syllab-log-link" data-jobid="'.$this_job_only.'">'.__('View Log', 'syllabplus').'</a></div>';
					$ret = '';
				}
			}
		}

		return $ret;
	}

	/**
	 * Print the HTML for a particular job
	 *
	 * @param String		  $job_id		   - the job identifier/nonce
	 * @param Boolean		  $is_oneshot	   - whether this backup should be 'one shot', i.e. no resumptions
	 * @param Boolean|Integer $time
	 * @param Integer		  $next_resumption
	 *
	 * @return String
	 */
	private function print_active_job($job_id, $is_oneshot = false, $time = false, $next_resumption = false) {

		$ret = '';
		
		global $syllabplus;
		$jobdata = $syllabplus->jobdata_getarray($job_id);

		if (false == apply_filters('syllabplus_print_active_job_continue', true, $is_oneshot, $next_resumption, $jobdata)) return '';

		if (!isset($jobdata['backup_time'])) return '';

		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);

		$began_at = isset($jobdata['backup_time']) ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $jobdata['backup_time']), 'D, F j, Y H:i') : '?';

		$backup_label = !empty($jobdata['label']) ? $jobdata['label'] : '';

		$remote_sent = (!empty($jobdata['service']) && ((is_array($jobdata['service']) && in_array('remotesend', $jobdata['service'])) || 'remotesend' === $jobdata['service'])) ? true : false;

		$jobstatus = empty($jobdata['jobstatus']) ? 'unknown' : $jobdata['jobstatus'];
		$stage = 0;
		switch ($jobstatus) {
			// Stage 0
			case 'begun':
			$curstage = __('Backup begun', 'syllabplus');
				break;
			// Stage 1
			case 'filescreating':
			$stage = 1;
			$curstage = __('Creating file backup zips', 'syllabplus');
			if (!empty($jobdata['filecreating_substatus']) && isset($backupable_entities[$jobdata['filecreating_substatus']['e']]['description'])) {
			
				$sdescrip = preg_replace('/ \(.*\)$/', '', $backupable_entities[$jobdata['filecreating_substatus']['e']]['description']);
				if (strlen($sdescrip) > 20 && isset($jobdata['filecreating_substatus']['e']) && is_array($jobdata['filecreating_substatus']['e']) && isset($backupable_entities[$jobdata['filecreating_substatus']['e']]['shortdescription'])) $sdescrip = $backupable_entities[$jobdata['filecreating_substatus']['e']]['shortdescription'];
				$curstage .= ' ('.$sdescrip.')';
				if (isset($jobdata['filecreating_substatus']['i']) && isset($jobdata['filecreating_substatus']['t'])) {
					$stage = min(2, 1 + ($jobdata['filecreating_substatus']['i']/max($jobdata['filecreating_substatus']['t'], 1)));
				}
			}
				break;
			case 'filescreated':
			$stage = 2;
			$curstage = __('Created file backup zips', 'syllabplus');
				break;
			// Stage 4
			case 'clonepolling':
				$stage = 4;
				$curstage = __('Clone server being provisioned and booted (can take several minutes)', 'syllabplus');
				break;
			case 'clouduploading':
			$stage = 4;
			$curstage = __('Uploading files to remote storage', 'syllabplus');
			if ($remote_sent) $curstage = __('Sending files to remote site', 'syllabplus');
			if (isset($jobdata['uploading_substatus']['t']) && isset($jobdata['uploading_substatus']['i'])) {
				$t = max((int) $jobdata['uploading_substatus']['t'], 1);
				$i = min($jobdata['uploading_substatus']['i']/$t, 1);
				$p = min($jobdata['uploading_substatus']['p'], 1);
				$pd = $i + $p/$t;
				$stage = 4 + $pd;
				$curstage .= ' '.sprintf(__('(%s%%, file %s of %s)', 'syllabplus'), floor(100*$pd), $jobdata['uploading_substatus']['i']+1, $t);
			}
				break;
			case 'pruning':
			$stage = 5;
			$curstage = __('Pruning old backup sets', 'syllabplus');
				break;
			case 'resumingforerrors':
			$stage = -1;
			$curstage = __('Waiting until scheduled time to retry because of errors', 'syllabplus');
				break;
			// Stage 6
			case 'finished':
			$stage = 6;
			$curstage = __('Backup finished', 'syllabplus');
				break;
			default:
			// Database creation and encryption occupies the space from 2 to 4. Databases are created then encrypted, then the next database is created/encrypted, etc.
			if ('dbcreated' == substr($jobstatus, 0, 9)) {
				$jobstatus = 'dbcreated';
				$whichdb = substr($jobstatus, 9);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = max((empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']), 1);
				$perdbspace = 2/$howmanydbs;

				$stage = min(4, 2 + ($whichdb+2)*$perdbspace);

				$curstage = __('Created database backup', 'syllabplus');

			} elseif ('dbcreating' == substr($jobstatus, 0, 10)) {
				$whichdb = substr($jobstatus, 10);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = (empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']);
				$perdbspace = 2/$howmanydbs;
				$jobstatus = 'dbcreating';

				$stage = min(4, 2 + $whichdb*$perdbspace);

				$curstage = __('Creating database backup', 'syllabplus');
				if (!empty($jobdata['dbcreating_substatus']['t'])) {
					$curstage .= ' ('.sprintf(__('table: %s', 'syllabplus'), $jobdata['dbcreating_substatus']['t']).')';
					if (!empty($jobdata['dbcreating_substatus']['i']) && !empty($jobdata['dbcreating_substatus']['a'])) {
						$substage = max(0.001, ($jobdata['dbcreating_substatus']['i'] / max($jobdata['dbcreating_substatus']['a'], 1)));
						$stage += $substage * $perdbspace * 0.5;
					}
				}
			} elseif ('dbencrypting' == substr($jobstatus, 0, 12)) {
				$whichdb = substr($jobstatus, 12);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = (empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']);
				$perdbspace = 2/$howmanydbs;
				$stage = min(4, 2 + $whichdb*$perdbspace + $perdbspace*0.5);
				$jobstatus = 'dbencrypting';
				$curstage = __('Encrypting database', 'syllabplus');
			} elseif ('dbencrypted' == substr($jobstatus, 0, 11)) {
				$whichdb = substr($jobstatus, 11);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = (empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']);
				$jobstatus = 'dbencrypted';
				$perdbspace = 2/$howmanydbs;
				$stage = min(4, 2 + $whichdb*$perdbspace + $perdbspace);
				$curstage = __('Encrypted database', 'syllabplus');
			} else {
				$curstage = __('Unknown', 'syllabplus');
			}
		}

		$runs_started = empty($jobdata['runs_started']) ? array() : $jobdata['runs_started'];
		$time_passed = empty($jobdata['run_times']) ? array() : $jobdata['run_times'];
		$last_checkin_ago = -1;
		if (is_array($time_passed)) {
			foreach ($time_passed as $run => $passed) {
				if (isset($runs_started[$run])) {
					$time_ago = microtime(true) - ($runs_started[$run] + $time_passed[$run]);
					if ($time_ago < $last_checkin_ago || -1 == $last_checkin_ago) $last_checkin_ago = $time_ago;
				}
			}
		}

		$next_res_after = (int) $time-time();
		$next_res_txt = ($is_oneshot) ? '' : sprintf(__("next resumption: %d (after %ss)", 'syllabplus'), $next_resumption, $next_res_after). ' ';
		$last_activity_txt = ($last_checkin_ago >= 0) ? sprintf(__('last activity: %ss ago', 'syllabplus'), floor($last_checkin_ago)).' ' : '';

		if (($last_checkin_ago < 50 && $next_res_after>30) || $is_oneshot) {
			$show_inline_info = $last_activity_txt;
			$title_info = $next_res_txt;
		} else {
			$show_inline_info = $next_res_txt;
			$title_info = $last_activity_txt;
		}
		
		$ret .= '<div class="syllab_row">';
		
		$ret .= '<div class="syllab_col"><div class="syllab_jobtimings next-resumption';

		if (!empty($jobdata['is_autobackup'])) $ret .= ' isautobackup';

		$is_clone = empty($jobdata['clone_job']) ? '0' : '1';

		$clone_url = empty($jobdata['clone_url']) ? false : true;
		
		$ret .= '" data-jobid="'.$job_id.'" data-lastactivity="'.(int) $last_checkin_ago.'" data-nextresumption="'.$next_resumption.'" data-nextresumptionafter="'.$next_res_after.'" title="'.esc_attr(sprintf(__('Job ID: %s', 'syllabplus'), $job_id)).$title_info.'">'.(!empty($backup_label) ? esc_html($backup_label) : $began_at).
		'</div></div>';

		$ret .= '<div class="syllab_col syllab_progress_container">';
			// Existence of the 'syllab-jobid-(id)' id is checked for in other places, so do not modify this
			$ret .= '<div class="job-id" data-isclone="'.$is_clone.'" id="syllab-jobid-'.$job_id.'">';

			if ($clone_url) $ret .= '<div class="syllab_clone_url" data-clone_url="' . $jobdata['clone_url'] . '"></div>';
	
			$ret .= apply_filters('syllab_printjob_beforewarnings', '', $jobdata, $job_id);
	
			if (!empty($jobdata['warnings']) && is_array($jobdata['warnings'])) {
				$ret .= '<ul class="disc">';
				foreach ($jobdata['warnings'] as $warning) {
					$ret .= '<li>'.sprintf(__('Warning: %s', 'syllabplus'), make_clickable(esc_html($warning))).'</li>';
				}
				$ret .= '</ul>';
			}
	
			$ret .= '<div class="curstage">';
			// $ret .= '<span class="curstage-info">'.esc_html($curstage).'</span>';
			$ret .= esc_html($curstage);
			// we need to add this data-progress attribute in order to be able to update the progress bar in UDC

			$ret .= '<div class="syllab_percentage" data-info="'.esc_attr($curstage).'" data-progress="'.(($stage>0) ? (ceil((100/6)*$stage)) : '0').'" style="background: #f15b06f5!important;height: 100%; width:'.(($stage>0) ? (ceil((100/6)*$stage)) : '0').'%"></div>';
			$ret .= '</div></div>';
	
			$ret .= '<div class="syllab_last_activity">';
			
			$ret .= $show_inline_info;
			if (!empty($show_inline_info)) 
				//$ret .= ' - ';

			$file_nonce = empty($jobdata['file_nonce']) ? $job_id : $jobdata['file_nonce'];
			
		/*	$ret .= '<a data-fileid="'.$file_nonce.'" data-jobid="'.$job_id.'" href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus&action=downloadlog&syllabplus_backup_nonce='.$file_nonce.'" class="syllab-log-link">'.__('show log', 'syllabplus').'</a>';
				if (!$is_oneshot) $ret .=' - <a href="#" data-jobid="'.$job_id.'" title="'.esc_attr(__('Note: the progress bar below is based on stages, NOT time. Do not stop the backup simply because it seems to have remained in the same place for a while - that is normal.', 'syllabplus')).'" class="syllab_jobinfo_delete">'.__('stop', 'syllabplus').'</a>'; */
			$ret .= '</div>';
		
		$ret .= '</div></div>';

		return $ret;

	}

	private function delete_old_dirs_go($show_return = true) {
		echo esc_html($show_return) ? '<h1>SyllabPlus - '.__('Remove old directories', 'syllabplus').'</h1>' : '<h2>'.__('Remove old directories', 'syllabplus').'</h2>';

		if ($this->delete_old_dirs()) {
			echo '<p>'.__('Old directories successfully removed.', 'syllabplus').'</p><br>';
		} else {
			echo '<p>',__('Old directory removal failed for some reason. You may want to do this manually.', 'syllabplus').'</p><br>';
		}
		if ($show_return) echo '<b>'.__('Actions', 'syllabplus').':</b> <a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus">'.__('Return to SyllabPlus configuration', 'syllabplus').'</a>';
	}

	/**
	 * Deletes the -old directories that are created when a backup is restored.
	 *
	 * @return Boolean. Can also exit (something we ought to probably review)
	 */
	private function delete_old_dirs() {
		global $wp_filesystem, $syllabplus;
		$credentials = request_filesystem_credentials(wp_nonce_url(SyllabPlus_Options::admin_page_url()."?page=syllabplus&action=syllab_delete_old_dirs", 'syllabplus-credentialtest-nonce', 'syllab_delete_old_dirs_nonce'));
		$wpfs = WP_Filesystem($credentials);
		if (!empty($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
			foreach ($wp_filesystem->errors->get_error_messages() as $message) show_message($message);
			exit;
		}
		if (!$wpfs) exit;
		
		// From WP_CONTENT_DIR - which contains 'themes'
		$ret = $this->delete_old_dirs_dir($wp_filesystem->wp_content_dir());

		$syllab_dir = $syllabplus->backups_dir_location();
		if ($syllab_dir) {
			$ret4 = $syllab_dir ? $this->delete_old_dirs_dir($syllab_dir, false) : true;
		} else {
			$ret4 = true;
		}

		$plugs = untrailingslashit($wp_filesystem->wp_plugins_dir());
		if ($wp_filesystem->is_dir($plugs.'-old')) {
			echo "<strong>".__('Delete', 'syllabplus').": </strong>plugins-old: ";
			if (!$wp_filesystem->delete($plugs.'-old', true)) {
				$ret3 = false;
				echo "<strong>".__('Failed', 'syllabplus')."</strong><br>";
				echo $syllabplus->log_permission_failure_message($wp_filesystem->wp_content_dir(), 'Delete '.$plugs.'-old');
			} else {
				$ret3 = true;
				echo "<strong>".__('OK', 'syllabplus')."</strong><br>";
			}
		} else {
			$ret3 = true;
		}

		return $ret && $ret3 && $ret4;
	}

	private function delete_old_dirs_dir($dir, $wpfs = true) {

		$dir = trailingslashit($dir);

		global $wp_filesystem, $syllabplus;

		if ($wpfs) {
			$list = $wp_filesystem->dirlist($dir);
		} else {
			$list = scandir($dir);
		}
		if (!is_array($list)) return false;

		$ret = true;
		foreach ($list as $item) {
			$name = (is_array($item)) ? $item['name'] : $item;
			if ("-old" == substr($name, -4, 4)) {
				// recursively delete
				print "<strong>".__('Delete', 'syllabplus').": </strong>".esc_html($name).": ";

				if ($wpfs) {
					if (!$wp_filesystem->delete($dir.$name, true)) {
						$ret = false;
						echo "<strong>".__('Failed', 'syllabplus')."</strong><br>";
						echo $syllabplus->log_permission_failure_message($dir, 'Delete '.$dir.$name);
					} else {
						echo "<strong>".__('OK', 'syllabplus')."</strong><br>";
					}
				} else {
					if (SyllabPlus_Filesystem_Functions::remove_local_directory($dir.$name)) {
						echo "<strong>".__('OK', 'syllabplus')."</strong><br>";
					} else {
						$ret = false;
						echo "<strong>".__('Failed', 'syllabplus')."</strong><br>";
						echo esc_html($syllabplus->log_permission_failure_message($dir, 'Delete '.$dir.$name));
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * The aim is to get a directory that is writable by the webserver, because that's the only way we can create zip files
	 *
	 * @return Boolean|WP_Error true if successful, otherwise false or a WP_Error
	 */
	private function create_backup_dir() {

		global $wp_filesystem, $syllabplus;

		if (false === ($credentials = request_filesystem_credentials(SyllabPlus_Options::admin_page().'?page=syllabplus&action=syllab_create_backup_dir&nonce='.wp_create_nonce('create_backup_dir')))) {
			return false;
		}

		if (!WP_Filesystem($credentials)) {
			// our credentials were no good, ask the user for them again
			request_filesystem_credentials(SyllabPlus_Options::admin_page().'?page=syllabplus&action=syllab_create_backup_dir&nonce='.wp_create_nonce('create_backup_dir'), '', true);
			return false;
		}

		$syllab_dir = $syllabplus->backups_dir_location();

		$default_backup_dir = $wp_filesystem->find_folder(dirname($syllab_dir)).basename($syllab_dir);

		$syllab_dir = ($syllab_dir) ? $wp_filesystem->find_folder(dirname($syllab_dir)).basename($syllab_dir) : $default_backup_dir;

		if (!$wp_filesystem->is_dir($default_backup_dir) && !$wp_filesystem->mkdir($default_backup_dir, 0775)) {
			$wperr = new WP_Error;
			if ($wp_filesystem->errors->get_error_code()) {
				foreach ($wp_filesystem->errors->get_error_messages() as $message) {
					$wperr->add('mkdir_error', $message);
				}
				return $wperr;
			} else {
				return new WP_Error('mkdir_error', __('The request to the filesystem to create the directory failed.', 'syllabplus'));
			}
		}

		if ($wp_filesystem->is_dir($default_backup_dir)) {

			if (SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir)) return true;

			@$wp_filesystem->chmod($default_backup_dir, 0775);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			if (SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir)) return true;

			@$wp_filesystem->chmod($default_backup_dir, 0777);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

			if (SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir)) {
				echo '<p>'.__('The folder was created, but we had to change its file permissions to 777 (world-writable) to be able to write to it. You should check with your hosting provider that this will not cause any problems', 'syllabplus').'</p>';
				return true;
			} else {
				@$wp_filesystem->chmod($default_backup_dir, 0775);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				$show_dir = (0 === strpos($default_backup_dir, ABSPATH)) ? substr($default_backup_dir, strlen(ABSPATH)) : $default_backup_dir;
				return new WP_Error('writable_error', __('The folder exists, but your webserver does not have permission to write to it.', 'syllabplus').' '.__('You will need to consult with your web hosting provider to find out how to set permissions for a WordPress plugin to write to the directory.', 'syllabplus').' ('.$show_dir.')');
			}
		}

		return true;
	}

	/**
	 * scans the content dir to see if any -old dirs are present
	 *
	 * @param  Boolean $print_as_comment Echo information in an HTML comment
	 * @return Boolean
	 */
	private function scan_old_dirs($print_as_comment = false) {
		global $syllabplus;
		$dirs = scandir(untrailingslashit(WP_CONTENT_DIR));
		if (!is_array($dirs)) $dirs = array();
		$dirs_u = @scandir($syllabplus->backups_dir_location());// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if (!is_array($dirs_u)) $dirs_u = array();
		foreach (array_merge($dirs, $dirs_u) as $dir) {
			if (preg_match('/-old$/', $dir)) {
				if ($print_as_comment) echo '<!--'.esc_html($dir).'-->';
				return true;
			}
		}
		// No need to scan ABSPATH - we don't backup there
		if (is_dir(untrailingslashit(WP_PLUGIN_DIR).'-old')) {
			if ($print_as_comment) echo '<!--'.esc_html(untrailingslashit(WP_PLUGIN_DIR).'-old').'-->';
			return true;
		}
		return false;
	}

	/**
	 * Outputs html for a storage method using the parameters passed in, this version should be removed when all remote storages use the multi version
	 *
	 * @param String $method   a list of methods to be used when
	 * @param String $header   the table header content
	 * @param String $contents the table contents
	 */
	public function storagemethod_row($method, $header, $contents) {
		?>
			<tr class="syllabplusmethod <?php echo esc_html($method);?>">
				<th><?php echo esc_html($header);?></th>
				<td><?php echo esc_html($contents);?></td>
			</tr>
		<?php
	}

	/**
	 * Outputs html for a storage method using the parameters passed in, this version of the method is compatible with multi storage options
	 *
	 * @param  string $classes  a list of classes to be used when
	 * @param  string $header   the table header content
	 * @param  string $contents the table contents
	 */
	public function storagemethod_row_multi($classes, $header, $contents) {
		?>
			<tr class="<?php echo esc_html($classes);?>">
				<th><?php echo esc_html($header);?></th>
				<td><?php echo esc_html($contents);?></td>
			</tr>
		<?php
	}
	
	/**
	 * Returns html for a storage method using the parameters passed in, this version of the method is compatible with multi storage options
	 *
	 * @param  string $classes  a list of classes to be used when
	 * @param  string $header   the table header content
	 * @param  string $contents the table contents
	 * @return string handlebars html template
	 */
	public function get_storagemethod_row_multi_configuration_template($classes, $header, $contents) {
		return '<tr class="'.esc_attr($classes).'">
					<th>'.esc_attr($header).'</th>
					<td>'.esc_attr($contents).'</td>
				</tr>';
	}

	/**
	 * Get HTML suitable for the admin area for the status of the last backup
	 *
	 * @return String
	 */
	public function last_backup_html() {

		global $syllabplus;

		$syllab_last_backup = SyllabPlus_Options::get_syllab_option('syllab_last_backup');

		if ($syllab_last_backup) {

			// Convert to GMT, then to blog time
			$backup_time = (int) $syllab_last_backup['backup_time'];

			$print_time = get_date_from_gmt(gmdate('Y-m-d H:i:s', $backup_time), 'D, F j, Y H:i');

			if (empty($syllab_last_backup['backup_time_incremental'])) {
				$last_backup_text = "<span style=\"color:".(($syllab_last_backup['success']) ? 'green' : 'black').";\">".$print_time.'</span>';
			} else {
				$inc_time = get_date_from_gmt(gmdate('Y-m-d H:i:s', $syllab_last_backup['backup_time_incremental']), 'D, F j, Y H:i');
				$last_backup_text = "<span style=\"color:".(($syllab_last_backup['success']) ? 'green' : 'black').";\">$inc_time</span> (".sprintf(__('incremental backup; base backup: %s', 'syllabplus'), $print_time).')';
			}

			$last_backup_text .= '<br>';

			// Show errors + warnings
			if (is_array($syllab_last_backup['errors'])) {
				foreach ($syllab_last_backup['errors'] as $err) {
					$level = (is_array($err)) ? $err['level'] : 'error';
					$message = (is_array($err)) ? $err['message'] : $err;
					$last_backup_text .= ('warning' == $level) ? "<span style=\"color:orange;\">" : "<span style=\"color:red;\">";
					if ('warning' == $level) {
						$message = sprintf(__("Warning: %s", 'syllabplus'), make_clickable(esc_html($message)));
					} else {
						$message = esc_html($message);
					}
					$last_backup_text .= $message;
					$last_backup_text .= '</span><br>';
				}
			}

			// Link log
			if (!empty($syllab_last_backup['backup_nonce'])) {
				$syllab_dir = $syllabplus->backups_dir_location();

				$potential_log_file = $syllab_dir."/log.".$syllab_last_backup['backup_nonce'].".txt";
				if (is_readable($potential_log_file)) $last_backup_text .= "<a href=\"?page=syllabplus&action=downloadlog&syllabplus_backup_nonce=".$syllab_last_backup['backup_nonce']."\" class=\"syllab-log-link\" onclick=\"event.preventDefault(); syllab_popuplog('".$syllab_last_backup['backup_nonce']."');\">".__('Download log file', 'syllabplus')."</a>";
			}

		} else {
			$last_backup_text = "<span style=\"color:blue;\">".__('No backup has been completed', 'syllabplus')."</span>";
		}

		return $last_backup_text;

	}

	/**
	 * Get a list of backup intervals
	 *
	 * @param String $what_for - 'files' or 'db'
	 *
	 * @return Array - keys are used as identifiers in the UI drop-down; values are user-displayed text describing the interval
	 */
	public function get_intervals($what_for = 'db') {
		global $syllabplus;

		if ($syllabplus->is_restricted_hosting('only_one_backup_per_month')) {
			$intervals = array(
				'manual' => _x('Manual', 'i.e. Non-automatic', 'syllabplus'),
				'monthly' => __('Monthly', 'syllabplus')
			);
		} else {
			$intervals = array(
				'manual' => _x('Manual', 'i.e. Non-automatic', 'syllabplus'),
				'daily' => __('Daily', 'syllabplus'),
				'weekly' => __('Weekly', 'syllabplus'),
				'monthly' => __('Monthly', 'syllabplus'),
			);
			
			if ('files' == $what_for) unset($intervals['everyhour']);
		}

		return apply_filters('syllabplus_backup_intervals', $intervals, $what_for);
	}
	
	public function really_writable_message($really_is_writable, $syllab_dir) {
		if ($really_is_writable) {
			$dir_info = '<span style="color:green;">'.__('Backup directory specified is writable, which is good.', 'syllabplus').'</span>';
		} else {
			$dir_info = '<span style="color:red;">';
			if (!is_dir($syllab_dir)) {
				$dir_info .= __('Backup directory specified does <b>not</b> exist.', 'syllabplus');
			} else {
				$dir_info .= __('Backup directory specified exists, but is <b>not</b> writable.', 'syllabplus');
			}
			$dir_info .= '<span class="syllab-directory-not-writable-blurb"><span class="directory-permissions"><a class="syllab_create_backup_dir" href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus&action=syllab_create_backup_dir&nonce='.wp_create_nonce('create_backup_dir').'">'.__('Follow this link to attempt to create the directory and set the permissions', 'syllabplus').'</a></span>, '.__('or, to reset this option', 'syllabplus').' <a href="'.SyllabPlus::get_current_clean_url().'" class="syllab_backup_dir_reset">'.__('press here', 'syllabplus').'</a>. '.__('If that is unsuccessful check the permissions on your server or change it to another directory that is writable by your web server process.', 'syllabplus').'</span>';
		}
		return $dir_info;
	}

	/**
	 * Directly output the settings form (suitable for the admin area)
	 *
	 * @param Array $options current options (passed on to the template)
	 */
	public function settings_formcontents($options = array()) {
		$this->include_template('wp-admin/settings/form-contents.php', false, array(
			'options' => $options
		));
		if (!(defined('SYLLABCENTRAL_COMMAND') && SYLLABCENTRAL_COMMAND)) {
			$this->include_template('wp-admin/settings/exclude-modal.php', false);
		}
	}

	public function get_settings_js($method_objects, $really_is_writable, $syllab_dir, $active_service) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter use

		global $syllabplus;
		
		ob_start();
		?>
		jQuery(function() {
			<?php
				if (!$really_is_writable) echo "jQuery('.backupdirrow').show();\n";
			?>
			<?php
				if (!empty($active_service)) {
					if (is_array($active_service)) {
						foreach ($active_service as $serv) {
							echo "jQuery('.${serv}').show();\n";
						}
					} else {
						echo "jQuery('.${active_service}').show();\n";
					}
				} else {
					echo "jQuery('.none').show();\n";
				}
				foreach ($syllabplus->backup_methods as $method => $description) {
					// already done: require_once(SYLLABPLUS_DIR.'/methods/'.$method.'.php');
					$call_method = "SyllabPlus_BackupModule_$method";
					if (method_exists($call_method, 'config_print_javascript_onready')) {
						$method_objects[$method]->config_print_javascript_onready();
					}
				}
			?>
		});
		<?php
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	}
	
	/**
	 * Return the HTML for the files selector widget
	 *
	 * @param  String		  $prefix                 Prefix for the ID
	 * @param  Boolean		  $show_exclusion_options True or False for exclusion options
	 * @param  Boolean|String $include_more           $include_more can be (bool) or (string)"sometimes"
	 *
	 * @return String
	 */
	public function files_selector_widgetry($prefix = '', $show_exclusion_options = true, $include_more = true) {

		$ret = '';

		global $syllabplus;
		$for_syllabcentral = defined('SYLLABCENTRAL_COMMAND') && SYLLABCENTRAL_COMMAND;
		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);
		// The true (default value if non-existent) here has the effect of forcing a default of on.
		$include_more_paths = SyllabPlus_Options::get_syllab_option('syllab_include_more_path');
		foreach ($backupable_entities as $key => $info) {
			$included = (SyllabPlus_Options::get_syllab_option("syllab_include_$key", apply_filters("syllabplus_defaultoption_include_".$key, true))) ? 'checked="checked"' : "";
			if ('others' == $key || 'uploads' == $key) {

				$data_toggle_exclude_field = $show_exclusion_options ? 'data-toggle_exclude_field="'.$key.'"' : '';
			
				$ret .= '<label '.(('others' == $key) ? 'title="'.sprintf(__('Your wp-content directory server path: %s', 'syllabplus'), WP_CONTENT_DIR).'" ' : '').' for="'.$prefix.'syllab_include_'.$key.'" class="syllab_checkbox"><input class="syllab_include_entity" id="'.$prefix.'syllab_include_'.$key.'" '.$data_toggle_exclude_field.' type="checkbox" name="syllab_include_'.$key.'" value="1" '.$included.'> '.(('others' == $key) ? __('Any other directories found inside wp-content', 'syllabplus') : esc_html($info['description'])).'</label>';
				
				if ($show_exclusion_options) {
					$include_exclude = SyllabPlus_Options::get_syllab_option('syllab_include_'.$key.'_exclude', ('others' == $key) ? SYLLAB_DEFAULT_OTHERS_EXCLUDE : SYLLAB_DEFAULT_UPLOADS_EXCLUDE);

					$display = ($included) ? '' : 'class="syllab-hidden" style="display:none;"';
					$exclude_container_class = $prefix.'syllab_include_'.$key.'_exclude';
					if (!$for_syllabcentral)  $exclude_container_class .= '_container';

					$ret .= "<div id=\"".$exclude_container_class."\" $display class=\"syllab_exclude_container\">";

					$ret .= '<label class="syllab-exclude-label" for="'.$prefix.'syllab_include_'.$key.'_exclude">'.__('Exclude these from', 'syllabplus').' '.esc_html($info['description']).':</label> <span class="syllab-fs-italic">'.__('(the asterisk character matches zero or more characters)', 'syllabplus').'</span>';

					$exclude_input_type = $for_syllabcentral ? "text" : "hidden";
					$exclude_input_extra_attr = $for_syllabcentral ? 'title="'.__('If entering multiple files/directories, then separate them with commas. For entities at the top level, you can use a * at the start or end of the entry as a wildcard.', 'syllabplus').'" size="54"' : '';
					$ret .= '<input type="'.$exclude_input_type.'" id="'.$prefix.'syllab_include_'.$key.'_exclude" name="syllab_include_'.$key.'_exclude" '.$exclude_input_extra_attr.' value="'.esc_html($include_exclude).'" />';
					
					if (!$for_syllabcentral) {
						global $syllabplus;
						$backupable_file_entities = $syllabplus->get_backupable_file_entities();
						
						if ('uploads' == $key) {
							$path = SyllabPlus_Manipulation_Functions::wp_normalize_path($backupable_file_entities['uploads']);
						} elseif ('others' == $key) {
							$path = SyllabPlus_Manipulation_Functions::wp_normalize_path($backupable_file_entities['others']);
						}
						$ret .= $this->include_template('wp-admin/settings/file-backup-exclude.php', true, array(
							'key' => $key,
							'include_exclude' => $include_exclude,
							'path' => $path,
							'show_exclusion_options' => $show_exclusion_options,
						));
					}
					$ret .= '</div>';
				}

			} else {

				if ('more' != $key || true === $include_more || ('sometimes' === $include_more && !empty($include_more_paths))) {
				
					$data_toggle_exclude_field = $show_exclusion_options ? 'data-toggle_exclude_field="'.$key.'"' : '';
				
					$ret .= "<label for=\"".$prefix."syllab_include_$key\"".((isset($info['htmltitle'])) ? ' title="'.esc_html($info['htmltitle']).'"' : '')." class=\"syllab_checkbox\"><input class=\"syllab_include_entity\" $data_toggle_exclude_field id=\"".$prefix."syllab_include_$key\" type=\"checkbox\" name=\"syllab_include_$key\" value=\"1\" $included /> ".esc_html($info['description']);

					$ret .= "</label>";
					$ret .= apply_filters("syllabplus_config_option_include_$key", '', $prefix, $for_syllabcentral);
				}
			}
		}

		return $ret;
	}

	/**
	 * Output or echo HTML for an error condition relating to a remote storage method
	 *
	 * @param String  $text		  - the text of the message; this should already be escaped (no more is done)
	 * @param String  $extraclass - a CSS class for the resulting DOM node
	 * @param Integer $echo		  - if set, then the results will be echoed as well as returned
	 *
	 * @return String - the results
	 */
	public function show_double_warning($text, $extraclass = '', $echo = true) {

		$ret = "<div class=\"error syllabplusmethod $extraclass\"><p>$text</p></div>";
		$ret .= "<div class=\"notice error below-h2\"><p>$text</p></div>";

		if ($echo) echo $ret;
		return $ret;

	}

	public function optionfilter_split_every($value) {
		return max(absint($value), SYLLABPLUS_SPLIT_MIN);
	}

	/**
	 * Check if curl exists; if not, print or return appropriate error messages
	 *
	 * @param String  $service                the service description (used only for user-visible messages - so, use the description)
	 * @param Boolean $has_fallback           set as true if the lack of Curl only affects the ability to connect over SSL
	 * @param String  $extraclass             an extra CSS class for any resulting message, passed on to show_double_warning()
	 * @param Boolean $echo_instead_of_return whether the result should be echoed or returned
	 * @return String                         any resulting message, if $echo_instead_of_return was set
	 */
	

	public function curl_check($service, $has_fallback = false, $extraclass = '', $echo_instead_of_return = true) {
		$ret = '';
		$ret .= '<p><em>'.sprintf(__("Use SylLab Vault to encrypt your website's backup securely. SylLab Vault provides you with reliable, easy-to-use, secure backup storage for a great price. Choose the option below to get started. First, register through our portal and follow the steps to set up your account. SylLab Vault brings you storage that is reliable, easy to use, and at a great price. After you create the account, you can manage your account and select upgrades that fit your needs. Press a button to get started.", 'syllabplus'), $service).'</em></p>';
		return $ret;
	}

	/**
	 * Get backup information in HTML format for a specific backup
	 *
	 * @param Array		 $backup_history all backups history
	 * @param String	 $key		     backup timestamp
	 * @param String	 $nonce			 backup nonce (job ID)
	 * @param Array|Null $job_data		 if an array, then use this as the job data (if null, then it will be fetched directly)
	 *
	 * @return string HTML-formatted backup information
	 */
	public function raw_backup_info($backup_history, $key, $nonce, $job_data = null) {

		global $syllabplus;

		$backup = $backup_history[$key];

		$only_remote_sent = !empty($backup['service']) && (array('remotesend') === $backup['service'] || 'remotesend' === $backup['service']);

		$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $key), 'M d, Y G:i');

		$rawbackup = "<h2 title=\"$key\">$pretty_date</h2>";

		if (!empty($backup['label'])) $rawbackup .= '<span class="raw-backup-info">'.$backup['label'].'</span>';

		if (null === $job_data) $job_data = empty($nonce) ? array() : $syllabplus->jobdata_getarray($nonce);
		

		$rawbackup .= '<hr><p>';

		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);

		$checksums = $syllabplus->which_checksums();

		foreach ($backupable_entities as $type => $info) {
			if (!isset($backup[$type])) continue;

			$rawbackup .= $syllabplus->printfile($info['description'], $backup, $type, $checksums, $job_data, true);
		}

		$total_size = 0;
		foreach ($backup as $ekey => $files) {
			if ('db' == strtolower(substr($ekey, 0, 2)) && '-size' != substr($ekey, -5, 5)) {
				$rawbackup .= $syllabplus->printfile(__('Database', 'syllabplus'), $backup, $ekey, $checksums, $job_data, true);
			}
			if (!isset($backupable_entities[$ekey]) && ('db' != substr($ekey, 0, 2) || '-size' == substr($ekey, -5, 5))) continue;
			if (is_string($files)) $files = array($files);
			foreach ($files as $findex => $file) {
				$size_key = (0 == $findex) ? $ekey.'-size' : $ekey.$findex.'-size';
				$total_size = (false === $total_size || !isset($backup[$size_key]) || !is_numeric($backup[$size_key])) ? false : $total_size + $backup[$size_key];
			}
		}

		$services = empty($backup['service']) ? array('none') : $backup['service'];
		if (!is_array($services)) $services = array('none');

		$rawbackup .= '<strong>'.__('Uploaded to:', 'syllabplus').'</strong> ';

		$show_services = '';
		foreach ($services as $serv) {
			if ('none' == $serv || '' == $serv) {
				$add_none = true;
			} elseif (isset($syllabplus->backup_methods[$serv])) {
				$show_services .= $show_services ? ', '.$syllabplus->backup_methods[$serv] : $syllabplus->backup_methods[$serv];
			} else {
				$show_services .= $show_services ? ', '.$serv : $serv;
			}
		}
		if ('' == $show_services && $add_none) $show_services .= __('None', 'syllabplus');

		$rawbackup .= $show_services;

		if (false !== $total_size) {
			$rawbackup .= '</p><strong>'.__('Total backup size:', 'syllabplus').'</strong> '.SyllabPlus_Manipulation_Functions::convert_numeric_size_to_text($total_size).'<p>';
		}
		
		$rawbackup .= '</p><hr><p><pre>'.print_r($backup, true).'</pre></p>';

		if (!empty($job_data) && is_array($job_data)) {
			$rawbackup .= '<p><pre>'.esc_html(print_r($job_data, true)).'</pre></p>';
		}

		return esc_attr($rawbackup);
	}

	private function download_db_button($bkey, $key, $esc_pretty_date, $backup, $accept = array()) {

		if (!empty($backup['meta_foreign']) && isset($accept[$backup['meta_foreign']])) {
			$desc_source = $accept[$backup['meta_foreign']]['desc'];
		} else {
			$desc_source = __('unknown source', 'syllabplus');
		}

		$ret = '';

		if ('db' == $bkey) {
			$dbt = empty($backup['meta_foreign']) ? esc_attr(__('Database', 'syllabplus')) : esc_attr(sprintf(__('Database (created by %s)', 'syllabplus'), $desc_source));
		} else {
			$dbt = __('External database', 'syllabplus').' ('.substr($bkey, 2).')';
		}

		$ret .= $this->download_button($bkey, $key, 0, null, '', $dbt, $esc_pretty_date, '0');
		
		return $ret;
	}

	/**
	 * Go through each of the file entities
	 *
	 * @param Array   $backup          An array of meta information
	 * @param Integer $key             Backup timestamp (epoch time)
	 * @param Array   $accept          An array of values to be accepted from vaules within $backup
	 * @param String  $entities        Entities to be added
	 * @param String  $esc_pretty_date Whether the button needs to escape the pretty date format
	 * @return String - the resulting HTML
	 */
	public function download_buttons($backup, $key, $accept, &$entities, $esc_pretty_date) {
		global $syllabplus;
		$ret = '';
		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);

		$first_entity = true;

		foreach ($backupable_entities as $type => $info) {
			if (!empty($backup['meta_foreign']) && 'wpcore' != $type) continue;

			$ide = '';
			
			if (empty($backup['meta_foreign'])) {
				$sdescrip = preg_replace('/ \(.*\)$/', '', $info['description']);
				if (strlen($sdescrip) > 20 && isset($info['shortdescription'])) $sdescrip = $info['shortdescription'];
			} else {
				$info['description'] = 'WordPress';

				if (isset($accept[$backup['meta_foreign']])) {
					$desc_source = $accept[$backup['meta_foreign']]['desc'];
					$ide .= sprintf(__('Backup created by: %s.', 'syllabplus'), $accept[$backup['meta_foreign']]['desc']).' ';
				} else {
					$desc_source = __('unknown source', 'syllabplus');
					$ide .= __('Backup created by unknown source (%s) - cannot be restored.', 'syllabplus').' ';
				}

				$sdescrip = (empty($accept[$backup['meta_foreign']]['separatedb'])) ? sprintf(__('Files and database WordPress backup (created by %s)', 'syllabplus'), $desc_source) : sprintf(__('Files backup (created by %s)', 'syllabplus'), $desc_source);
			}
			if (isset($backup[$type])) {
				if (!is_array($backup[$type])) $backup[$type] = array($backup[$type]);
				$howmanyinset = count($backup[$type]);
				$expected_index = 0;
				$index_missing = false;
				$set_contents = '';
				$entities .= "/$type=";
				$whatfiles = $backup[$type];
				ksort($whatfiles);
				$total_file_size = 0;
				foreach ($whatfiles as $findex => $bfile) {
					$set_contents .= ('' == $set_contents) ? $findex : ",$findex";
					if ($findex != $expected_index) $index_missing = true;
					$expected_index++;

					if ($howmanyinset > 0) {
						if (!empty($backup[$type.(($findex > 0) ? $findex : '')."-size"]) && $findex < $howmanyinset) $total_file_size += $backup[$type.(($findex > 0) ? $findex : '')."-size"];
					}
				}

				$ide = __('Press here to download or browse', 'syllabplus').' '.strtolower($info['description']);
				$ide .= ' '.sprintf(__('(%d archive(s) in set, total %s).', 'syllabplus'), $howmanyinset, '%UP_backups_total_file_size%');
				if ($index_missing) $ide .= ' '.__('You appear to be missing one or more archives from this multi-archive set.', 'syllabplus');

				$entities .= $set_contents.'/';
				if (!empty($backup['meta_foreign'])) {
					$entities .= '/plugins=0//themes=0//uploads=0//others=0/';
				}

				$ret .= $this->download_button($type, $key, 0, null, $ide, $sdescrip, $esc_pretty_date, $set_contents);

				$ret = str_replace('%UP_backups_total_file_size%', SyllabPlus_Manipulation_Functions::convert_numeric_size_to_text($total_file_size), $ret);
			}
		}
		return $ret;
	}

	public function date_label($pretty_date, $key, $backup, $jobdata, $nonce, $simple_format = false) {

		$pretty_date = $simple_format ? $pretty_date : '<div class="clear-right">'.$pretty_date.'</div>';

		$ret = apply_filters('syllabplus_showbackup_date', $pretty_date, $backup, $jobdata, (int) $key, $simple_format);
		if (is_array($jobdata) && !empty($jobdata['resume_interval']) && (empty($jobdata['jobstatus']) || 'finished' != $jobdata['jobstatus'])) {
			if ($simple_format) {
				$ret .= ' '.__('(Not finished)', 'syllabplus');
			} else {
				$ret .= apply_filters('syllabplus_msg_unfinishedbackup', "<br><span title=\"".esc_attr(__('If you are seeing more backups than you expect, then it is probably because the deletion of old backup sets does not happen until a fresh backup completes.', 'syllabplus'))."\">".__('(Not finished)', 'syllabplus').'</span>', $jobdata, $nonce);
			}
		}
		return $ret;
	}

	public function download_button($type, $backup_timestamp, $findex, $info, $title, $pdescrip, $esc_pretty_date, $set_contents) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter use
	
		$ret = '';

		$wp_nonce = wp_create_nonce('syllabplus_download');
		
		// syllab_downloader(base, backup_timestamp, what, whicharea, set_contents, prettydate, async)
		$ret .= '<button data-wp_nonce="'.esc_attr($wp_nonce).'" data-backup_timestamp="'.esc_attr($backup_timestamp).'" data-what="'.esc_attr($type).'" data-set_contents="'.esc_attr($set_contents).'" data-prettydate="'.esc_attr($esc_pretty_date).'" type="button" class="button syllab_download_button '."uddownloadform_${type}_${backup_timestamp}_${findex}".'" title="'.$title.'">'.$pdescrip.'</button>';
		// onclick="'."return syllab_downloader('uddlstatus_', '$backup_timestamp', '$type', '.ud_downloadstatus', '$set_contents', '$esc_pretty_date', true)".'"
		
		return $ret;
	}

	public function restore_button($backup, $key, $pretty_date, $entities = '') {
		$ret = '<div class="restore-button">';

		if ($entities) {
			$show_data = $pretty_date;
			if (isset($backup['native']) && false == $backup['native']) {
				$show_data .= ' '.__('(backup set imported from remote location)', 'syllabplus');
			}

			$ret .= '<button data-showdata="'.esc_attr($show_data).'" data-backup_timestamp="'.$key.'" data-entities="'.esc_attr($entities).'" title="'.__('After pressing this button, you will be given the option to choose which components you wish to restore', 'syllabplus').'" type="button" class="button button-primary choose-components-button">'.__('Restore', 'syllabplus').'</button>';
		}
		$ret .= "</div>\n";
		return $ret;
	}

	/**
	 * Get HTML for the 'Upload' button for a particular backup in the 'Existing backups' tab
	 *
	 * @param Integer	 $backup_time - backup timestamp (epoch time)
	 * @param String	 $nonce       - backup nonce
	 * @param Array		 $backup      - backup information array
	 * @param Null|Array $jobdata	  - if not null, then use as the job data instead of fetching
	 *
	 * @return String - the resulting HTML
	 */
	public function upload_button($backup_time, $nonce, $backup, $jobdata = null) {
		global $syllabplus;

		// Check the job is not still running.
		if (null === $jobdata) $jobdata = $syllabplus->jobdata_getarray($nonce);
		
		if (!empty($jobdata) && 'finished' != $jobdata['jobstatus']) return '';

		// Check that the user has remote storage setup.
		$services = (array) $syllabplus->just_one($syllabplus->get_canonical_service_list());
		if (empty($services)) return '';

		$show_upload = false;

		// Check that the backup has not already been sent to remote storage before.
		if (empty($backup['service']) || array('none') == $backup['service'] || array('') == $backup['service'] || 'none' == $backup['service']) {
			$show_upload = true;
		// If it has been uploaded then check if there are any new remote storage options that it has not yet been sent to.
		} elseif (!empty($backup['service']) && array('none') != $backup['service'] && array('') != $backup['service'] && 'none' != $backup['service']) {
			
			foreach ($services as $key => $value) {
				if (in_array($value, $backup['service'])) unset($services[$key]);
			}

			if (!empty($services)) $show_upload = true;
		}

		if ($show_upload) {
			
			$backup_local = $this->check_backup_is_complete($backup, false, false, true);

			if ($backup_local) {
				$service_list = '';
				$service_list_display = '';
				$is_first_service = true;
				
				foreach ($services as $key => $service) {
					if (!$is_first_service) {
						$service_list .= ',';
						$service_list_display .= ', ';
					}
					$service_list .= $service;
					$service_list_display .= $syllabplus->backup_methods[$service];

					$is_first_service = false;
				}

				return '<div class="syllabplus-upload">
				<button data-nonce="'.$nonce.'" data-key="'.$backup_time.'" data-services="'.$service_list.'" title="'.__('After pressing this button, you can select where to upload your backup from a list of your currently saved remote storage locations', 'syllabplus').' ('.$service_list_display.')." type="button" class="button button-primary syllab-upload-link">'.__('Upload', 'syllabplus').'</button>
				</div>';
			}

			return '';
		}
	}

	/**
	 * Get HTML for the 'Delete' button for a particular backup in the 'Existing backups' tab
	 *
	 * @param Integer $backup_time - backup timestamp (epoch time)
	 * @param String  $nonce	   - backup nonce
	 * @param Array	  $backup	   - backup information array
	 *
	 * @return String - the resulting HTML
	 */
	public function delete_button($backup_time, $nonce, $backup) {
		$sval = (!empty($backup['service']) && 'email' != $backup['service'] && 'none' != $backup['service'] && array('email') !== $backup['service'] && array('none') !== $backup['service'] && array('remotesend') !== $backup['service']) ? '1' : '0';
		return '<div class="syllabplus-remove" data-hasremote="'.$sval.'">
			<a data-hasremote="'.$sval.'" data-nonce="'.$nonce.'" data-key="'.$backup_time.'" class="button button-remove no-decoration syllab-delete-link" href="'.SyllabPlus::get_current_clean_url().'" title="'.esc_attr(__('Delete this backup set', 'syllabplus')).'">'.__('Delete', 'syllabplus').'</a>
		</div>';
	}

	public function log_button($backup) {
		global $syllabplus;
		$syllab_dir = $syllabplus->backups_dir_location();
		$ret = '';
		if (isset($backup['nonce']) && preg_match("/^[0-9a-f]{12}$/", $backup['nonce']) && is_readable($syllab_dir.'/log.'.$backup['nonce'].'.txt')) {
			$nval = $backup['nonce'];
			$lt = __('View Log', 'syllabplus');
			$url = esc_attr(SyllabPlus_Options::admin_page()."?page=syllabplus&action=downloadlog&amp;syllabplus_backup_nonce=$nval");
			$ret .= <<<ENDHERE
				<div style="clear:none;" class="syllab-viewlogdiv">
					<a class="button no-decoration syllab-log-link" href="$url" data-jobid="$nval">
						$lt
					</a>
					<!--
					<form action="$url" method="get">
						<input type="hidden" name="action" value="downloadlog" />
						<input type="hidden" name="page" value="syllabplus" />
						<input type="hidden" name="syllabplus_backup_nonce" value="$nval" />
						<input type="submit" value="$lt" class="syllab-log-link" onclick="event.preventDefault(); syllab_popuplog('$nval');" />
					</form>
					-->
				</div>
ENDHERE;
			return $ret;
		}
	}

	/**
	 * This function will check that a backup is complete depending on the parameters passed in.
	 * A backup is complete in the case of a "clone" if it contains a db, plugins, themes, uploads and others.
	 * A backup is complete in the case of a "full backup" when it contains everything the user has set in their options to be backed up.
	 * It can also check if the backup is local on the filesystem.
	 *
	 * @param array   $backup      - the backup array we want to check
	 * @param boolean $full_backup - a boolean to indicate if the backup should also be a full backup
	 * @param boolean $clone       - a boolean to indicate if the backup is for a clone, if so it does not need to be a full backup it only needs to include everything a clone can restore
	 * @param boolean $local       - a boolean to indicate if the backup should be present on the local file system or not
	 *
	 * @return boolean - returns true if the backup is complete and if specified is found on the local system otherwise false
	 */
	private function check_backup_is_complete($backup, $full_backup, $clone, $local) {

		global $syllabplus;

		if (empty($backup)) return false;

		if ($clone) {
			$entities = array('db' => '', 'plugins' => '', 'themes' => '', 'uploads' => '', 'others' => '');
		} else {
			$entities = $syllabplus->get_backupable_file_entities(true, true);
			
			// Add the database to the entities array ready to loop over
			$entities['db'] = '';

			foreach ($entities as $key => $info) {
				if (!SyllabPlus_Options::get_syllab_option("syllab_include_$key", false)) {
					unset($entities[$key]);
				}
			}
		}
		
		$syllab_dir = trailingslashit($syllabplus->backups_dir_location());

		foreach ($entities as $type => $info) {

			if ($full_backup) {
				if (SyllabPlus_Options::get_syllab_option("syllab_include_$type", false) && !isset($backup[$type])) return false;
			}

			if (!isset($backup[$type])) return false;

			if ($local) {
				// Cast this to an array so that a warning is not thrown when we encounter a Database.
				foreach ((array) $backup[$type] as $value) {
					if (!file_exists($syllab_dir . DIRECTORY_SEPARATOR . $value)) return false;
				}
			}
		}

		return true;
	}

	/**
	 * This function will set up the backup job data for when we are uploading a local backup to remote storage. It changes the initial jobdata so that SyllabPlus knows about what files it's uploading and so that it skips directly to the upload stage.
	 *
	 * @param array $jobdata - the initial job data that we want to change
	 * @param array $options - options sent from the front end includes backup timestamp and nonce
	 *
	 * @return array         - the modified jobdata
	 */
	public function upload_local_backup_jobdata($jobdata, $options) {
		global $syllabplus;

		if (!is_array($jobdata)) return $jobdata;
		
		$backup_history = SyllabPlus_Backup_History::get_history();
		$services = !empty($options['services']) ? $options['services'] : array();
		$backup = $backup_history[$options['use_timestamp']];

		/*
			The initial job data is not set up in a key value array instead it is set up so key "x" is the name of the key and then key "y" is the value.
			e.g array[0] = 'backup_name' array[1] = 'my_backup'
		*/
		$jobstatus_key = array_search('jobstatus', $jobdata) + 1;
		$backup_time_key = array_search('backup_time', $jobdata) + 1;
		$backup_database_key = array_search('backup_database', $jobdata) + 1;
		$backup_files_key = array_search('backup_files', $jobdata) + 1;
		$service_key = array_search('service', $jobdata) + 1;

		$db_backups = $jobdata[$backup_database_key];
		$db_backup_info = $syllabplus->update_database_jobdata($db_backups, $backup);
		$file_backups = $syllabplus->update_files_jobdata($backup);
		
		// Next we need to build the services array using the remote storage destinations the user has selected to upload this backup set to
		$selected_services = array();
		
		foreach ($services as $storage_info) {
			$selected_services[] = $storage_info['value'];
		}
		
		$jobdata[$jobstatus_key] = 'clouduploading';
		$jobdata[$backup_time_key] = $options['use_timestamp'];
		$jobdata[$backup_files_key] = 'finished';
		$jobdata[] = 'backup_files_array';
		$jobdata[] = $file_backups;
		$jobdata[] = 'blog_name';
		$jobdata[] = $db_backup_info['blog_name'];
		$jobdata[$backup_database_key] = $db_backup_info['db_backups'];
		$jobdata[] = 'local_upload';
		$jobdata[] = true;
		if (!empty($selected_services)) $jobdata[$service_key] = $selected_services;
		
		
		return $jobdata;
	}

	/**
	 * This function allows us to change the backup name, this is needed when uploading a local database backup to remote storage when the backup has come from another site.
	 *
	 * @param string $backup_name - the current name of the backup file
	 * @param string $use_time    - the current timestamp we are using
	 * @param string $blog_name   - the blog name of the current site
	 *
	 * @return string             - the new filename or the original if the blog name from the job data is not set
	 */
	public function upload_local_backup_name($backup_name, $use_time, $blog_name) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter use
		global $syllabplus;

		$backup_blog_name = $syllabplus->jobdata_get('blog_name', '');

		if ('' != $blog_name && '' != $backup_blog_name) {
			return str_replace($blog_name, $backup_blog_name, $backup_name);
		}

		return $backup_name;
	}

	/**
	 * This function starts the syllabplus restore process it processes $_REQUEST
	 * (keys: syllab_*, meta_foreign, backup_timestamp and job_id)
	 *
	 * @return void
	 */
	public function prepare_restore() {

		global $syllabplus;

		// on restore start job_id is empty but if we needed file system permissions or this is a resumption then we have already started a job so reuse it
		$restore_job_id = empty(sanitize_text_field($_REQUEST['job_id'])) ? false : sanitize_text_field($_REQUEST['job_id']);

		// Set up nonces, log files etc.
		$syllabplus->initiate_restore_job($restore_job_id);
		
		// If this is the start of a restore then get the restore data from the posted data and put it into jobdata.
		if (isset($_REQUEST['action']) && 'syllab_restore' == $_REQUEST['action']) {
			
			if (empty($restore_job_id)) {
				$jobdata_to_save = array();
				foreach ($_REQUEST as $key => $value) {
					if (false !== strpos($key, 'syllab_') || 'backup_timestamp' == $key || 'meta_foreign' == $key) {
						if ('syllab_restorer_restore_options' == $key) parse_str(stripslashes($value), $value);
						$jobdata_to_save[$key] = $value;
					}
				}

				if (isset($jobdata_to_save['syllab_restorer_restore_options']['syllab_restore_table_options']) && !empty($jobdata_to_save['syllab_restorer_restore_options']['syllab_restore_table_options'])) {
					
					$restore_table_options = $jobdata_to_save['syllab_restorer_restore_options']['syllab_restore_table_options'];
					
					$include_unspecified_tables = false;
					$tables_to_restore = array();
					$tables_to_skip = array();

					foreach ($restore_table_options as $table) {
						if ('slp_all_other_tables' == $table) {
							$include_unspecified_tables = true;
						} elseif ('slp-skip-table-' == substr($table, 0, 15)) {
							$tables_to_skip[] = substr($table, 15);
						} else {
							$tables_to_restore[] = $table;
						}
					}

					$jobdata_to_save['syllab_restorer_restore_options']['include_unspecified_tables'] = $include_unspecified_tables;
					$jobdata_to_save['syllab_restorer_restore_options']['tables_to_restore'] = $tables_to_restore;
					$jobdata_to_save['syllab_restorer_restore_options']['tables_to_skip'] = $tables_to_skip;
					unset($jobdata_to_save['syllab_restorer_restore_options']['syllab_restore_table_options']);
				}

				$syllabplus->jobdata_set_multi($jobdata_to_save);

				// Use a site option, as otherwise on multisite when all the array of options is updated via SyllabPlus_Options::update_site_option(), it will over-write any restored UD options from the backup
				update_site_option('syllab_restore_in_progress', $syllabplus->nonce);
			}
		}

		// If this is the start of an ajax restore then end execution here so it can then be booted over ajax
		if (isset($_REQUEST['syllabplus_ajax_restore']) && 'start_ajax_restore' == $_REQUEST['syllabplus_ajax_restore']) {
			// return to prevent any more code from running
			return $this->prepare_ajax_restore();

		} elseif (isset($_REQUEST['syllabplus_ajax_restore']) && 'continue_ajax_restore' == $_REQUEST['syllabplus_ajax_restore']) {
			// If we enter here then in order to restore we needed to require the filesystem credentials we should save these before returning back to the browser and load them back after the AJAX call, this prevents us asking for the filesystem credentials again
			$filesystem_credentials = array(
				'hostname' => '',
				'username' => '',
				'password' => '',
				'connection_type' => '',
				'upgrade' => '',
			);

			$credentials_found = false;

			foreach ($_REQUEST as $key => $value) {
				if (array_key_exists($key, $filesystem_credentials)) {
					$filesystem_credentials[$key] = stripslashes($value);
					$credentials_found = true;
				}
			}

			if ($credentials_found) $syllabplus->jobdata_set('filesystem_credentials', $filesystem_credentials);

			// return to prevent any more code from running
			return $this->prepare_ajax_restore();
		}

		if (!empty($_REQUEST['syllabplus_ajax_restore'])) add_filter('syllabplus_logline', array($this, 'syllabplus_logline'), 10, 5);
		
		$is_continuation = ('syllab_ajaxrestore_continue' == sanitize_text_field($_REQUEST['action'])) ? true : false;

		if ($is_continuation) {
			$restore_in_progress = get_site_option('syllab_restore_in_progress');
			if ($restore_in_progress != $_REQUEST['job_id']) {
				$abort_restore_already = true;
				$syllabplus->log(__('Sufficient information about the in-progress restoration operation could not be found.', 'syllabplus') . ' (job_id_mismatch)', 'error', 'job_id_mismatch');
			} else {
				$restore_jobdata = $syllabplus->jobdata_getarray($restore_in_progress);
				if (is_array($restore_jobdata) && isset($restore_jobdata['job_type']) && 'restore' == $restore_jobdata['job_type'] && isset($restore_jobdata['second_loop_entities']) && !empty($restore_jobdata['second_loop_entities']) && isset($restore_jobdata['job_time_ms']) && isset($restore_jobdata['backup_timestamp'])) {
					$backup_timestamp = $restore_jobdata['backup_timestamp'];
					$continuation_data = $restore_jobdata;
					$continuation_data['syllabplus_ajax_restore'] = 'continue_ajax_restore';
				} else {
					$abort_restore_already = true;
					$syllabplus->log(__('Sufficient information about the in-progress restoration operation could not be found.', 'syllabplus') . ' (job_id_nojobdata)', 'error', 'job_id_nojobdata');
				}
			}
		} elseif (isset($_REQUEST['syllabplus_ajax_restore']) && 'do_ajax_restore' == $_REQUEST['syllabplus_ajax_restore']) {
			$backup_timestamp = $syllabplus->jobdata_get('backup_timestamp');
			$continuation_data = array('syllabplus_ajax_restore' => 'do_ajax_restore');
		} else {
			$backup_timestamp = sanitize_text_field($_REQUEST['backup_timestamp']);
			$continuation_data = null;
		}

		$filesystem_credentials = $syllabplus->jobdata_get('filesystem_credentials', array());

		if (!empty($filesystem_credentials)) {
			$continuation_data['syllabplus_ajax_restore'] = 'continue_ajax_restore';
			// If the filesystem credentials are not empty then we now need to load these back into $_POST so that WP_Filesystem can access them
			foreach ($filesystem_credentials as $key => $value) {
				$_POST[$key] = $value;
			}
		}

		if (empty($abort_restore_already)) {
			$backup_success = $this->restore_backup($backup_timestamp, $continuation_data);
		} else {
			$backup_success = false;
		}

		if (empty($syllabplus->errors) && true === $backup_success) {
			// TODO: Deal with the case of some of the work having been deferred
			echo '<p class="syllab_restore_successful"><strong>';
			$syllabplus->log_e('Restore successful!');
			echo '</strong></p>';
			$syllabplus->log('Restore successful');
			$s_val = 1;
			if (!empty($this->entities_to_restore) && is_array($this->entities_to_restore)) {
				foreach ($this->entities_to_restore as $v) {
					if ('db' != $v) $s_val = 2;
				}
			}
			$pval = $syllabplus->have_addons ? 1 : 0;

			echo '<strong>' . __('Actions', 'syllabplus') . ':</strong> <a href="' . SyllabPlus_Options::admin_page_url() . '?page=syllabplus&syllab_restore_success=' . $s_val . '&pval=' . $pval . '">' . __('Return to SyllabPlus configuration', 'syllabplus') . '</a>';
			return;

		} elseif (is_wp_error($backup_success)) {
			echo '<p class="syllab_restore_error">';
			$syllabplus->log_e('Restore failed...');
			echo '</p>';
			$syllabplus->log_wp_error($backup_success);
			$syllabplus->log('Restore failed');
			echo '<div class="syllab_restore_errors">';
			$syllabplus->list_errors();
			echo '</div>';
			echo '<strong>' . __('Actions', 'syllabplus') . ':</strong> <a href="' . SyllabPlus_Options::admin_page_url() . '?page=syllabplus">' . __('Return to SyllabPlus configuration', 'syllabplus') . '</a>';
			return;
		} elseif (false === $backup_success) {
			// This means, "not yet - but stay on the page because we may be able to do it later, e.g. if the user types in the requested information"
			echo '<p class="syllab_restore_error">';
			$syllabplus->log_e('Restore failed...');
			echo '</p>';
			$syllabplus->log("Restore failed");
			echo '<div class="syllab_restore_errors">';
			$syllabplus->list_errors();
			echo '</div>';
			echo '<strong>' . __('Actions', 'syllabplus') . ':</strong> <a href="' . SyllabPlus_Options::admin_page_url() . '?page=syllabplus">' . __('Return to SyllabPlus configuration', 'syllabplus') . '</a>';
			return;
		}
	}

	/**
	 * This function will load the required ajax and output any relevant html for the ajax restore
	 *
	 * @return void
	 */
	private function prepare_ajax_restore() {
		global $syllabplus;

		$debug = $syllabplus->use_unminified_scripts();
		$enqueue_version = $debug ? $syllabplus->version . '.' . time() : $syllabplus->version;
		$syllab_min_or_not = $syllabplus->get_syllabplus_file_version();
		$ajax_action = isset($_REQUEST['syllabplus_ajax_restore']) && 'continue_ajax_restore' == $_REQUEST['syllabplus_ajax_restore'] && 'syllab_restore' != $_REQUEST['action'] ? 'syllab_ajaxrestore_continue' : 'syllab_ajaxrestore';

		// get the entities info
		$jobdata = $syllabplus->jobdata_getarray($syllabplus->nonce);
		$restore_components = $jobdata['syllab_restore'];
		usort($restore_components, array('SyllabPlus_Manipulation_Functions', 'sort_restoration_entities'));

		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);
		$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $jobdata['backup_timestamp']), 'M d, Y G:i');

		wp_enqueue_script('syllab-admin-restore', SYLLABPLUS_URL . '/js/syllab-admin-restore' . $syllab_min_or_not . '.js', array(), $enqueue_version);

		$syllabplus->log("Restore setup, now closing connection and starting restore over AJAX.");

		echo '<div class="syllab_restore_container">';
		echo '<div class="error" id="syllab-restore-hidethis">';
		echo '<p><strong>'. __('Warning: If you can still read these words after the page finishes loading, then there is a JavaScript or jQuery problem in the site.', 'syllabplus') .' '.__('This may prevent the restore procedure from being able to proceed.', 'syllabplus').'</strong>';
		echo ' <a href="'. apply_filters('syllabplus_com_link', "https://syllabplus.com/do-you-have-a-javascript-or-jquery-error/") .'" target="_blank">'. __('Go here for more information.', 'syllabplus') .'</a></p>';
		echo '</div>';
		echo '<div class="syllab_restore_main--header">'.__('SyllabPlus Restoration', 'syllabplus').' - '.__('Backup', 'syllabplus').' '.esc_html($pretty_date).'</div>';
		echo '<div class="syllab_restore_main">';
		
		if ($debug) echo '<input type="hidden" id="syllabplus_ajax_restore_debug" name="syllabplus_ajax_restore_debug" value="1">';
		echo '<input type="hidden" id="syllabplus_ajax_restore_job_id" name="syllabplus_restore_job_id" value="' . esc_html($syllabplus->nonce) . '">';
		echo '<input type="hidden" id="syllabplus_ajax_restore_action" name="syllabplus_restore_action" value="' . esc_html($ajax_action) . '">';
		echo '<div id="syllabplus_ajax_restore_progress" style="display: none;"></div>';

		echo '<div class="syllab_restore_main--components">';
		echo '	<p>'.sprintf(__('The restore operation has begun (%s). Do not close this page until it reports itself as having finished.', 'syllabplus'), esc_html($syllabplus->nonce)).'</p>';
		echo '	<h2>'.__('Restoration progress:', 'syllabplus').'</h2>';
		echo '	<div class="syllab_restore_result"><span class="dashicons"></span><pan class="syllab_restore_result--text"></span></div>';
		echo '	<ul class="syllab_restore_components_list">';
		echo '		<li data-component="verifying" class="active"><span class="syllab_component--description">'.__('Verifying', 'syllabplus').'</span><span class="syllab_component--progress"></span></li>';
		foreach ($restore_components as $restore_component) {
			// Set Database description
			if ('db' == $restore_component && !isset($backupable_entities[$restore_component]['description'])) $backupable_entities[$restore_component]['description'] = __('Database', 'syllabplus');
			echo '		<li data-component="'.esc_attr($restore_component).'"><span class="syllab_component--description">'.(isset($backupable_entities[$restore_component]['description']) ? esc_html($backupable_entities[$restore_component]['description']) : esc_html($restore_component)).'</span><span class="syllab_component--progress"></span></li>';
		}
		echo '		<li data-component="cleaning"><span class="syllab_component--description">'.__('Cleaning', 'syllabplus').'</span><span class="syllab_component--progress"></span></li>';
		echo '		<li data-component="finished"><span class="syllab_component--description">'.__('Finished', 'syllabplus').'</span><span class="syllab_component--progress"></span></li>';
		echo '	</ul>'; // end ul.syllab_restore_components_list
		// Provide download link for the log file
		echo '	<p><a target="_blank" href="?action=downloadlog&page=syllabplus&syllabplus_backup_nonce='.esc_html($syllabplus->nonce).'">'.__('Follow this link to download the log file for this restoration (needed for any support requests).', 'syllabplus').'</a></p>';
		echo '</div>'; // end .syllab_restore_main--components
		echo '<div class="syllab_restore_main--activity">';
		echo '	<h2 class="syllab_restore_main--activity-title">'.__('Activity log', 'syllabplus').' <span id="syllabplus_ajax_restore_last_activity"></span></h2>';
		echo '	<div id="syllabplus_ajax_restore_output"></div>';
		echo '</div>'; // end .syllab_restore_main--activity
		echo '
			<div class="syllab-restore--footer">
				<ul class="syllab-restore--stages">
					<li><span>'.__('1. Component selection', 'syllabplus').'</span></li>
					<li><span>'.__('2. Verifications', 'syllabplus').'</span></li>
					<li class="active"><span>'.__('3. Restoration', 'syllabplus').'</span></li>
				</ul>
			</div>';
		echo '</div>'; // end .syllab_restore_main
		echo '</div>'; // end .syllab_restore_container
	}

	/**
	 * Processes the jobdata to build an array of entities to restore.
	 *
	 * @param Array $backup_set - information on the backup to restore
	 *
	 * @return Array - the entities to restore built from the restore jobdata
	 */
	private function get_entities_to_restore_from_jobdata($backup_set) {

		global $syllabplus;

		$syllab_restore = $syllabplus->jobdata_get('syllab_restore');

		if (empty($syllab_restore) || (!is_array($syllab_restore))) $syllab_restore = array();

		$entities_to_restore = array();
		$foreign_known = apply_filters('syllabplus_accept_archivename', array());

		foreach ($syllab_restore as $entity) {
			if (empty($backup_set['meta_foreign'])) {
				$entities_to_restore[$entity] = $entity;
			} else {
				if ('db' == $entity && !empty($foreign_known[$backup_set['meta_foreign']]) && !empty($foreign_known[$backup_set['meta_foreign']]['separatedb'])) {
					$entities_to_restore[$entity] = 'db';
				} else {
					$entities_to_restore[$entity] = 'wpcore';
				}
			}
		}

		return $entities_to_restore;
	}
	
	/**
	 * Processes the jobdata to build an array of restoration options
	 *
	 * @return Array - the restore options built from the restore jobdata
	 */
	private function get_restore_options_from_jobdata() {
	
		global $syllabplus;

		$restore_options = $syllabplus->jobdata_get('syllab_restorer_restore_options');
		$syllab_encryptionphrase = $syllabplus->jobdata_get('syllab_encryptionphrase');
		$include_wpconfig = $syllabplus->jobdata_get('syllab_restorer_wpcore_includewpconfig');

		$restore_options['syllab_encryptionphrase'] = empty($syllab_encryptionphrase) ? '' : $syllab_encryptionphrase;
		
		$restore_options['syllab_restorer_wpcore_includewpconfig'] = !empty($include_wpconfig);
		
		$restore_options['syllab_incremental_restore_point'] = empty($restore_options['syllab_incremental_restore_point']) ? -1 : (int) $restore_options['syllab_incremental_restore_point'];
		
		return $restore_options;
	}
	
	/**
	 * Carry out the restore process within the WP admin dashboard, using data from $_POST
	 *
	 * @param  Array	  $timestamp         Identifying the backup to be restored
	 * @param  Array|null $continuation_data For continuing a multi-stage restore; this is the saved jobdata for the job; in this method the keys used are second_loop_entities, restore_options; but it is also passed on to Syllab_Restorer::perform_restore()
	 * @return Boolean|WP_Error - a WP_Error indicates a terminal failure; false indicates not-yet complete (not necessarily terminal); true indicates complete.
	 */
	private function restore_backup($timestamp, $continuation_data = null) {

		global $syllabplus, $syllabplus_restorer;

		$second_loop_entities = empty($continuation_data['second_loop_entities']) ? array() : $continuation_data['second_loop_entities'];

		// If this is a resumption and we still need to restore the database we should rebuild the backup history to ensure the database is in there.
		if (!empty($second_loop_entities['db'])) SyllabPlus_Backup_History::rebuild();
		
		$backup_set = SyllabPlus_Backup_History::get_history($timestamp);

		if (empty($backup_set)) {
			echo '<p>'.__('This backup does not exist in the backup history - restoration aborted. Timestamp:', 'syllabplus')." $timestamp</p><br>";
			return new WP_Error('does_not_exist', __('Backup does not exist in the backup history', 'syllabplus')." ($timestamp)");
		}

		$backup_set['timestamp'] = $timestamp;

		$url_parameters = array(
			'backup_timestamp' => $timestamp,
			'job_id' => $syllabplus->nonce
		);

		if (!empty($continuation_data['syllabplus_ajax_restore'])) {
			$url_parameters['syllabplus_ajax_restore'] = 'continue_ajax_restore';
			$syllabplus->output_to_browser(''); // Start timer
			// Force output buffering off so that we get log lines sent to the browser as they come not all at once at the end of the ajax restore
			// zlib creates an output buffer, and waits for the entire page to be generated before it can send it to the client try to turn it off
			@ini_set("zlib.output_compression", 0);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			// Turn off PHP output buffering for NGINX
			header('X-Accel-Buffering: no');
			header('Content-Encoding: none');
			while (ob_get_level()) {
				ob_end_flush();
			}
			ob_implicit_flush(1);
		}

		$syllabplus->log("Ensuring WP_Filesystem is setup for a restore");
		
		// This will print HTML and die() if necessary
		SyllabPlus_Filesystem_Functions::ensure_wp_filesystem_set_up_for_restore($url_parameters);

		$syllabplus->log("WP_Filesystem is setup and ready for a restore");

		$entities_to_restore = $this->get_entities_to_restore_from_jobdata($backup_set);

		if (empty($entities_to_restore)) {
			$restore_jobdata = $syllabplus->jobdata_getarray($syllabplus->nonce);
			echo '<p>'.__('ABORT: Could not find the information on which entities to restore.', 'syllabplus').'</p><p>'.__('If making a request for support, please include this information:', 'syllabplus').' '.count($restore_jobdata).' : '.esc_html(serialize($restore_jobdata)).'</p>';
			return new WP_Error('missing_info', 'Backup information not found');
		}

		// This is used in painting the admin page after a successful restore
		$this->entities_to_restore = $entities_to_restore;

		// This will be removed by Syllab_Restorer::post_restore_clean_up()
		set_error_handler(array($syllabplus, 'php_error'), E_ALL & ~E_STRICT);

		// Set $restore_options, either from the continuation data, or from $_POST
		if (!empty($continuation_data['restore_options'])) {
			$restore_options = $continuation_data['restore_options'];
		} else {
			// Gather the restore options into one place - code after here should read the options
			$restore_options = $this->get_restore_options_from_jobdata();
			$syllabplus->jobdata_set('restore_options', $restore_options);
		}
			
		add_action('syllabplus_restoration_title', array($this, 'restoration_title'));

		$syllabplus->log_restore_update(array('type' => 'state', 'stage' => 'started', 'data' => array()));
		
		// We use a single object for each entity, because we want to store information about the backup set
		$syllabplus_restorer = new Syllab_Restorer(new Syllab_Restorer_Skin, $backup_set, false, $restore_options, $continuation_data);
		
		$restore_result = $syllabplus_restorer->perform_restore($entities_to_restore, $restore_options);
		
		$syllabplus_restorer->post_restore_clean_up($restore_result);
		
		$pval = $syllabplus->have_addons ? 1 : 0;
		$sval = (true === $restore_result) ? 1 : 0;

		$pages = get_pages(array('number' => 2));
		$page_urls = array(
			'home' => get_home_url(),
		);

		foreach ($pages as $page_info) {
			$page_urls[$page_info->post_name] = get_page_link($page_info->ID);
		}

		$syllabplus->log_restore_update(
			array(
				'type' => 'state',
				'stage' => 'finished',
				'data' => array(
					'actions' => array(
						__('Return to SyllabPlus configuration', 'syllabplus') => SyllabPlus_Options::admin_page_url() . '?page=syllabplus&syllab_restore_success=' . $sval . '&pval=' . $pval
					),
					'urls' => $page_urls,
				)
			)
		);

		return $restore_result;
	}
	
	/**
	 * Called when the restore process wants to print a title
	 *
	 * @param String $title - title
	 */
	public function restoration_title($title) {
		echo '<h2>'.$title.'</h2>';
	}

	/**
	 * Logs a line from the restore process, being called from SyllabPlus::log().
	 * Hooks the WordPress filter syllabplus_logline
	 * In future, this can get more sophisticated. For now, things are funnelled through here, giving the future possibility.
	 *
	 * @param String         $line        - the line to be logged
	 * @param String         $nonce       - the job ID of the restore job
	 * @param String         $level       - the level of the log notice
	 * @param String|Boolean $uniq_id     - a unique ID for the log if it should only be logged once; or false otherwise
	 * @param String         $destination - the type of job ongoing. If it is not 'restore', then we will skip the logging.
	 *
	 * @return String|Boolean - the filtered value. If set to false, then SyllabPlus::log() will stop processing the log line.
	 */
	public function syllabplus_logline($line, $nonce, $level, $uniq_id, $destination) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	
		if ('progress' != $destination || (defined('WP_CLI') && WP_CLI) || false === $line || false === strpos($line, 'RINFO:')) return $line;

		global $syllabplus;

		$syllabplus->output_to_browser($line);

		// Indicate that we have completely handled all logging needed
		return false;
	}
	
	/**
	 * Ensure that what is returned is an array. Used as a WP options filter.
	 *
	 * @param Array $input - input
	 *
	 * @return Array
	 */
	public function return_array($input) {
		return is_array($input) ? $input : array();
	}
	
	/**
	 * Called upon the WP action wp_ajax_syllab_savesettings. Will die().
	 */
	public function syllab_ajax_savesettings() {
		try {
			if (empty($_POST) || empty(sanitize_text_field($_POST['subaction'])) || 'savesettings' != $_POST['subaction'] || !isset($_POST['nonce']) || !is_user_logged_in() || !SyllabPlus_Options::user_can_manage() || !wp_verify_nonce($_POST['nonce'], 'syllabplus-settings-nonce')) die('Security check');
	
			if (empty(sanitize_text_field($_POST['settings'])) || !is_string(sanitize_text_field($_POST['settings']))) die('Invalid data');
	
			parse_str(stripslashes($_POST['settings']), $posted_settings);
			// We now have $posted_settings as an array
			if (!empty($_POST['syllabplus_version'])) $posted_settings['syllabplus_version'] = sanitize_text_field($_POST['syllabplus_version']);
			
			echo json_encode($this->save_settings($posted_settings));
		} catch (Exception $e) {
			$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during save settings. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			echo json_encode(array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			));
		// @codingStandardsIgnoreLine
		} catch (Error $e) {
			$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during save settings. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			echo json_encode(array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			));
		}
		die;
	}
	
	public function syllab_ajax_importsettings() {
		try {
			if (empty(sanitize_text_field($_POST)) || empty(sanitize_text_field($_POST['subaction'])) || 'importsettings' != $_POST['subaction'] || !isset($_POST['nonce']) || !is_user_logged_in() || !SyllabPlus_Options::user_can_manage() || !wp_verify_nonce($_POST['nonce'], 'syllabplus-settings-nonce')) die('Security check');
			 
			if (empty(sanitize_text_field($_POST['settings'])) || !is_string(sanitize_text_field($_POST['settings']))) die('Invalid data');
	
			$this->import_settings($_POST);
		} catch (Exception $e) {
			$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during import settings. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			echo json_encode(array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			));
		// @codingStandardsIgnoreLine 
		} catch (Error $e) { 
			$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during import settings. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			echo json_encode(array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			));
		}
	}
	
	/**
	 * This method handles the imported json settings it will convert them into a readable format for the existing save settings function, it will also update some of the options to match the new remote storage options format (Apr 2017)
	 *
	 * @param  Array $settings - The settings from the imported json file
	 */
	public function import_settings($settings) {
		// A bug in UD releases around 1.12.40 - 1.13.3 meant that it was saved in URL-string format, instead of JSON
		$perhaps_not_yet_parsed = json_decode(stripslashes($settings['settings']), true);

		if (!is_array($perhaps_not_yet_parsed)) {
			parse_str($perhaps_not_yet_parsed, $posted_settings);
		} else {
			$posted_settings = $perhaps_not_yet_parsed;
		}

		if (!empty($settings['syllabplus_version'])) $posted_settings['syllabplus_version'] = $settings['syllabplus_version'];

		// Handle the settings name change of WebDAV and SFTP (Apr 2017) if someone tries to import an old settings to this version
		if (isset($posted_settings['syllab_webdav_settings'])) {
			$posted_settings['syllab_webdav'] = $posted_settings['syllab_webdav_settings'];
			unset($posted_settings['syllab_webdav_settings']);
		}

		if (isset($posted_settings['syllab_sftp_settings'])) {
			$posted_settings['syllab_sftp'] = $posted_settings['syllab_sftp_settings'];
			unset($posted_settings['syllab_sftp_settings']);
		}

		// We also need to wrap some of the options in the new style settings array otherwise later on we will lose the settings if this information is missing
		if (empty($posted_settings['syllab_webdav']['settings'])) $posted_settings['syllab_webdav'] = SyllabPlus_Storage_Methods_Interface::wrap_remote_storage_options($posted_settings['syllab_webdav']);
		if (empty($posted_settings['syllab_googledrive']['settings'])) $posted_settings['syllab_googledrive'] = SyllabPlus_Storage_Methods_Interface::wrap_remote_storage_options($posted_settings['syllab_googledrive']);
		if (empty($posted_settings['syllab_googlecloud']['settings'])) $posted_settings['syllab_googlecloud'] = SyllabPlus_Storage_Methods_Interface::wrap_remote_storage_options($posted_settings['syllab_googlecloud']);
		if (empty($posted_settings['syllab_onedrive']['settings'])) $posted_settings['syllab_onedrive'] = SyllabPlus_Storage_Methods_Interface::wrap_remote_storage_options($posted_settings['syllab_onedrive']);
		if (empty($posted_settings['syllab_azure']['settings'])) $posted_settings['syllab_azure'] = SyllabPlus_Storage_Methods_Interface::wrap_remote_storage_options($posted_settings['syllab_azure']);
		if (empty($posted_settings['syllab_dropbox']['settings'])) $posted_settings['syllab_dropbox'] = SyllabPlus_Storage_Methods_Interface::wrap_remote_storage_options($posted_settings['syllab_dropbox']);

		echo json_encode($this->save_settings($posted_settings));

		die;
	}
	
	/**
	 * This function will get a list of remote storage methods with valid connection details and create a HTML list of checkboxes
	 *
	 * @return String - HTML checkbox list of remote storage methods with valid connection details
	 */
	private function backup_now_remote_message() {
		global $syllabplus;
		
		$services = (array) $syllabplus->just_one($syllabplus->get_canonical_service_list());
		$all_services = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids($services);
		$active_remote_storage_list = '';
		
		foreach ($all_services as $method => $sinfo) {
			if ('email' == $method) {
				$possible_emails = $syllabplus->just_one_email(SyllabPlus_Options::get_syllab_option('syllab_email'));
				if (!empty($possible_emails)) {
					$active_remote_storage_list .= '<input class="syllab_remote_service_entity" id="'.$method.'syllab_service" checked="checked" type="checkbox" name="syllab_include_remote_service_'. $method . '" value=""> <label for="'.$method.'syllab_service">'.$syllabplus->backup_methods[$method].'</label><br>';
				}
			} elseif (empty($sinfo['object']) || empty($sinfo['instance_settings']) || !is_callable(array($sinfo['object'], 'options_exist'))) {
				continue;
			}

			$instance_count = 1;
			foreach ($sinfo['instance_settings'] as $instance => $opt) {
				if ($sinfo['object']->options_exist($opt)) {
					$instance_count_label = (1 == $instance_count) ? '' : ' ('.$instance_count.')';
					$label = empty($opt['instance_label']) ? $sinfo['object']->get_description() . $instance_count_label : $opt['instance_label'];
					if (!isset($opt['instance_enabled'])) $opt['instance_enabled'] = 1;
					$checked = empty($opt['instance_enabled']) ? '' : 'checked="checked"';
					$active_remote_storage_list .= '<input class="syllab_remote_service_entity" id="'.$method.'syllab_service_'.$instance.'" ' . $checked . ' type="checkbox" name="syllab_include_remote_service_'. $method . '" value="'.$instance.'"> <label for="'.$method.'syllab_service_'.$instance.'">'.$label.'</label><br>';
					$instance_count++;
				}
			}
		}

		$service = $syllabplus->just_one(SyllabPlus_Options::get_syllab_option('syllab_service'));
		if (is_string($service)) $service = array($service);
		if (!is_array($service)) $service = array();

		$no_remote_configured = (empty($service) || array('none') === $service || array('') === $service) ? true : false;

		

		if (empty($active_remote_storage_list)) {
			$active_remote_storage_list = '<p>'.__('No remote storage locations with valid options found.', 'syllabplus').'</p>';
		}

	}
	
	/**
	 * This method works through the passed in settings array and saves the settings to the database clearing old data and setting up a return array with content to update the page via ajax
	 *
	 * @param  array $settings An array of settings taking from the admin page ready to be saved to the database
	 * @return array           An array response containing the status of the update along with content to be used to update the admin page.
	 */
	public function save_settings($settings) {
	
		global $syllabplus;
		
		// Make sure that settings filters are registered
		SyllabPlus_Options::admin_init();
		
		$more_files_path_updated = false;

		if (isset($settings['syllabplus_version']) && $syllabplus->version == $settings['syllabplus_version']) {

			$return_array = array('saved' => true);
			
			$add_to_post_keys = array('syllab_interval', 'syllab_interval_database', 'syllab_interval_increments', 'syllab_starttime_files', 'syllab_starttime_db', 'syllab_startday_files', 'syllab_startday_db');
			
			// If database and files are on same schedule, override the db day/time settings
			if (isset($settings['syllab_interval_database']) && isset($settings['syllab_interval_database']) && $settings['syllab_interval_database'] == $settings['syllab_interval'] && isset($settings['syllab_starttime_files'])) {
				$settings['syllab_starttime_db'] = $settings['syllab_starttime_files'];
				$settings['syllab_startday_db'] = $settings['syllab_startday_files'];
			}
			foreach ($add_to_post_keys as $key) {
				// For add-ons that look at $_POST to find saved settings, add the relevant keys to $_POST so that they find them there
				if (isset($settings[$key])) {
					$_POST[$key] = $settings[$key];
				}
			}

			// Check if syllab_include_more_path is set, if it is then we need to update the page, if it's not set but there's content already in the database that is cleared down below so again we should update the page.
			$more_files_path_updated = false;

			// i.e. If an option has been set, or if it was currently active in the settings
			if (isset($settings['syllab_include_more_path']) || SyllabPlus_Options::get_syllab_option('syllab_include_more_path')) {
				$more_files_path_updated = true;
			}
			
			// Wipe the extra retention rules, as they are not saved correctly if the last one is deleted
			SyllabPlus_Options::update_syllab_option('syllab_retain_extrarules', array());
			SyllabPlus_Options::update_syllab_option('syllab_email', array());
			SyllabPlus_Options::update_syllab_option('syllab_report_warningsonly', array());
			SyllabPlus_Options::update_syllab_option('syllab_report_wholebackup', array());
			SyllabPlus_Options::update_syllab_option('syllab_extradbs', array());
			SyllabPlus_Options::update_syllab_option('syllab_include_more_path', array());
			
			$relevant_keys = $syllabplus->get_settings_keys();

			if (isset($settings['syllab_auto_updates']) && in_array('syllab_auto_updates', $relevant_keys)) {
				$syllabplus->set_automatic_updates($settings['syllab_auto_updates']);
				unset($settings['syllab_auto_updates']); // unset the key and its value to prevent being processed the second time
			}

			if (method_exists('SyllabPlus_Options', 'mass_options_update')) {
				$original_settings = $settings;
				$settings = SyllabPlus_Options::mass_options_update($settings);
				$mass_updated = true;
			}

			foreach ($settings as $key => $value) {

				if (in_array($key, $relevant_keys)) {
					if ('syllab_service' == $key && is_array($value)) {
						foreach ($value as $subkey => $subvalue) {
							if ('0' == $subvalue) unset($value[$subkey]);
						}
					}

					// This flag indicates that either the stored database option was changed, or that the supplied option was changed before being stored. It isn't comprehensive - it's only used to update some UI elements with invalid input.
					$updated = empty($mass_updated) ? (is_string($value) && SyllabPlus_Options::get_syllab_option($key) != $value) : (is_string($value) && (!isset($original_settings[$key]) || $original_settings[$key] != $value));

					if (empty($mass_updated)) SyllabPlus_Options::update_syllab_option($key, $value);
					
					// Add information on what has changed to array to loop through to update links etc.
					// Restricting to strings for now, to prevent any unintended leakage (since this is just used for UI updating)
					if ($updated) {
						$value = SyllabPlus_Options::get_syllab_option($key);
						if (is_string($value)) $return_array['changed'][$key] = $value;
					}
				// @codingStandardsIgnoreLine
				} else {
					// This section is ignored by CI otherwise it will complain the ELSE is empty.
					
					// When last active, it was catching: option_page, action, _wpnonce, _wp_http_referer, syllab_s3_endpoint, syllab_dreamobjects_endpoint. The latter two are empty; probably don't need to be in the page at all.
					// error_log("Non-UD key when saving from POSTed data: ".$key);
				}
			}
		} else {
			$return_array = array('saved' => false, 'error_message' => sprintf(__('SyllabPlus seems to have been updated to version (%s), which is different to the version running when this settings page was loaded. Please reload the settings page before trying to save settings.', 'syllabplus'), $syllabplus->version));
		}
		
		// Checking for various possible messages
		$syllab_dir = $syllabplus->backups_dir_location(false);
		$really_is_writable = SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir);
		$dir_info = $this->really_writable_message($really_is_writable, $syllab_dir);
		$button_title = esc_attr(__('This button is disabled because your backup directory is not writable (see the settings).', 'syllabplus'));
		
		$return_array['backup_now_message'] = $this->backup_now_remote_message();
		
		$return_array['backup_dir'] = array('writable' => $really_is_writable, 'message' => $dir_info, 'button_title' => $button_title);

		// Check if $more_files_path_updated is true, is so then there's a change and we should update the backup modal
		if ($more_files_path_updated) {
			$return_array['syllab_include_more_path'] = $this->files_selector_widgetry('backupnow_files_', false, 'sometimes');
		}
		
		// Because of the single AJAX call, we need to remove the existing UD messages from the 'all_admin_notices' action
		remove_all_actions('all_admin_notices');
		
		// Moving from 2 to 1 ajax call
		ob_start();

		$service = SyllabPlus_Options::get_syllab_option('syllab_service');
		
		$this->setup_all_admin_notices_global($service);
		$this->setup_all_admin_notices_udonly($service);
		
		do_action('all_admin_notices');
		
		if (!$really_is_writable) { // Check if writable
			$this->show_admin_warning_unwritable();
		}
		
		if ($return_array['saved']) { //
			$this->show_admin_warning(__('Your settings have been saved.', 'syllabplus'), 'updated fade');
		} else {
			if (isset($return_array['error_message'])) {
				$this->show_admin_warning($return_array['error_message'], 'error');
			} else {
				$this->show_admin_warning(__('Your settings failed to save. Please refresh the settings page and try again', 'syllabplus'), 'error');
			}
		}
		
		$messages_output = ob_get_contents();
		
		ob_clean();
		
		// Backup schedule output
		$this->next_scheduled_backups_output('line');
		
		$scheduled_output = ob_get_clean();
		
		$return_array['messages'] = $messages_output;
		$return_array['scheduled'] = $scheduled_output;
		$return_array['files_scheduled'] = $this->next_scheduled_files_backups_output(true);
		$return_array['database_scheduled'] = $this->next_scheduled_database_backups_output(true);
		
		
		// Add the updated options to the return message, so we can update on screen
		return $return_array;
		
	}

	/**
	 * Authenticate remote storage instance
	 *
	 * @param array - $data It consists of below key elements:
	 *                $remote_method - Remote storage service
	 *                $instance_id - Remote storage instance id
	 * @return array An array response containing the status of the authentication
	 */
	public function auth_remote_method($data) {
		global $syllabplus;
		
		$response = array();
		
		if (isset($data['remote_method']) && isset($data['instance_id'])) {
			$response['result'] = 'success';
			$remote_method = $data['remote_method'];
			$instance_id = $data['instance_id'];
			
			$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array($remote_method));
			
			try {
				$storage_objects_and_ids[$remote_method]['object']->authenticate_storage($instance_id);
			} catch (Exception $e) {
				$response['result'] = 'error';
				$response['message'] = $syllabplus->backup_methods[$remote_method] . ' ' . __('authentication error', 'syllabplus') . ' ' . $e->getMessage();
			}
		} else {
			$response['result'] = 'error';
			$response['message'] = __('Remote storage method and instance id are required for authentication.', 'syllabplus');
		}

		return $response;
	}
	
	/**
	 * Deauthenticate remote storage instance
	 *
	 * @param array - $data It consists of below key elements:
	 *                $remote_method - Remote storage service
	 *                $instance_id - Remote storage instance id
	 * @return array An array response containing the status of the deauthentication
	 */
	public function deauth_remote_method($data) {
		global $syllabplus;
		
		$response = array();
		
		if (isset($data['remote_method']) && isset($data['instance_id'])) {
			$response['result'] = 'success';
			$remote_method = $data['remote_method'];
			$instance_id = $data['instance_id'];
			
			$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array($remote_method));
			
			try {
				$storage_objects_and_ids[$remote_method]['object']->deauthenticate_storage($instance_id);
			} catch (Exception $e) {
				$response['result'] = 'error';
				$response['message'] = $syllabplus->backup_methods[$remote_method] . ' deauthentication error ' . $e->getMessage();
			}
		} else {
			$response['result'] = 'error';
			$response['message'] = 'Remote storage method and instance id are required for deauthentication.';
		}

		return $response;
	}
	
	/**
	 * A method to remove SyllabPlus settings from the options table.
	 *
	 * @param  boolean $wipe_all_settings Set to true as default as we want to remove all options, set to false if calling from SyllabCentral, as we do not want to remove the SyllabCentral key or we will lose connection to the site.
	 * @return boolean
	 */
	public function wipe_settings($wipe_all_settings = true) {
		
		global $syllabplus;

		$settings = $syllabplus->get_settings_keys();

		// if this is false the UDC has called it we don't want to remove the UDC key other wise we will lose connection to the remote site.
		if (false == $wipe_all_settings) {
			$key = array_search('syllab_central_localkeys', $settings);
			unset($settings[$key]);
		}

		foreach ($settings as $s) SyllabPlus_Options::delete_syllab_option($s);

		$syllabplus->wipe_state_data(true);

		$site_options = array('syllab_oneshotnonce');
		foreach ($site_options as $s) delete_site_option($s);

		$this->show_admin_warning(__("Your settings have been wiped.", 'syllabplus'));

		return true;
	}

	/**
	 * This get the details for syllab vault and to be used globally
	 *
	 * @param  string $instance_id - the instance_id of the current instance being used
	 * @return object              - the SyllabVault option setup to use the passed in instance id or if one wasn't passed then use the default set of options
	 */
	public function get_syllabvault($instance_id = '') {
		$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array('syllabvault'));

		if (isset($storage_objects_and_ids['syllabvault']['instance_settings'][$instance_id])) {
			$opts = $storage_objects_and_ids['syllabvault']['instance_settings'][$instance_id];
			$vault = $storage_objects_and_ids['syllabvault']['object'];
			$vault->set_options($opts, false, $instance_id);
		} else {
			include_once(SYLLABPLUS_DIR.'/methods/syllabvault.php');
			$vault = new SyllabPlus_BackupModule_syllabvault();
		}

		return $vault;
	}

	/**
	 * http_get will allow the HTTP Fetch execute available in advanced tools
	 *
	 * @param  String  $uri  Specific URL passed to curl
	 * @param  Boolean $curl True or False if cURL is to be used
	 * @return String - JSON encoded results
	 */
	 public function http_get($uri = null, $curl = false) {

		if (!preg_match('/^https?/', $uri)) return json_encode(array('e' => 'Non-http URL specified'));
			$response = wp_remote_get($uri, array('timeout' => 10));
			if (is_wp_error($response)) {
				return json_encode(array('e' => esc_html($response->get_error_message())));
			}
			return json_encode(
				array(
					'r' => wp_remote_retrieve_response_code($response).': '.esc_html(substr(wp_remote_retrieve_body($response), 0, 2048)),
					'code' => wp_remote_retrieve_response_code($response),
					'html_response' => esc_html(substr(wp_remote_retrieve_body($response), 0, 2048)),
					'response' => $response
				)
			);
	}

	/**
	 * This will return all the details for raw backup and file list, in HTML format
	 *
	 * @param Boolean $no_pre_tags - if set, then <pre></pre> tags will be removed from the output
	 *
	 * @return String
	 */
	public function show_raw_backups($no_pre_tags = false) {
		global $syllabplus;
		
		$response = array();
		
		$response['html'] = '<h3 id="ud-debuginfo-rawbackups">'.__('Known backups (raw)', 'syllabplus').'</h3><pre>';
		ob_start();
		$history = SyllabPlus_Backup_History::get_history();
		var_dump($history);
		$response["html"] .= ob_get_clean();
		$response['html'] .= '</pre>';

		$response['html'] .= '<h3 id="ud-debuginfo-files">'.__('Files', 'syllabplus').'</h3><pre>';
		$syllab_dir = $syllabplus->backups_dir_location();
		$raw_output = array();
		$d = dir($syllab_dir);
		while (false !== ($entry = $d->read())) {
			$fp = $syllab_dir.'/'.$entry;
			$mtime = filemtime($fp);
			if (is_dir($fp)) {
				$size = '       d';
			} elseif (is_link($fp)) {
				$size = '       l';
			} elseif (is_file($fp)) {
				$size = sprintf("%8.1f", round(filesize($fp)/1024, 1)).' '.gmdate('r', $mtime);
			} else {
				$size = '       ?';
			}
			if (preg_match('/^log\.(.*)\.txt$/', $entry, $lmatch)) $entry = '<a target="_top" href="?action=downloadlog&amp;page=syllabplus&amp;syllabplus_backup_nonce='.esc_html($lmatch[1]).'">'.$entry.'</a>';
			$raw_output[$mtime] = empty($raw_output[$mtime]) ? sprintf("%s %s\n", $size, $entry) : $raw_output[$mtime].sprintf("%s %s\n", $size, $entry);
		}
		@$d->close();// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		krsort($raw_output, SORT_NUMERIC);

		foreach ($raw_output as $line) {
			$response['html'] .= $line;
		}

		$response['html'] .= '</pre>';

		$response['html'] .= '<h3 id="ud-debuginfo-options">'.__('Options (raw)', 'syllabplus').'</h3>';
		$opts = $syllabplus->get_settings_keys();
		asort($opts);
		// <tr><th>'.__('Key', 'syllabplus').'</th><th>'.__('Value', 'syllabplus').'</th></tr>
		$response['html'] .= '<table><thead></thead><tbody>';
		foreach ($opts as $opt) {
			$response['html'] .= '<tr><td>'.esc_html($opt).'</td><td>'.esc_html(print_r(SyllabPlus_Options::get_syllab_option($opt), true)).'</td>';
		}
		
		// Get the option saved by yahnis-elsts/plugin-update-checker
		$response['html'] .= '<tr><td>external_updates-syllabplus</td><td><pre>'.esc_html(print_r(get_site_option('external_updates-syllabplus'), true)).'</pre></td>';
		
		$response['html'] .= '</tbody></table>';

		ob_start();
		do_action('syllabplus_showrawinfo');
		$response['html'] .= ob_get_clean();

		if (true == $no_pre_tags) {
			$response['html'] = str_replace('<pre>', '', $response['html']);
			$response['html'] = str_replace('</pre>', '', $response['html']);
		}

		return $response;
	}

	/**
	 * This will call any wp_action
	 *
	 * @param  Array|Null		$data                      The array of data with the vaules for wpaction
	 * @param  Callable|Boolean	$close_connection_callable A callable to call to close the browser connection, or true for a default suitable for internal use, or false for none
	 * @return Array - results
	 */
	public function call_wp_action($data = null, $close_connection_callable = false) {
		global $syllabplus;

		ob_start();

		$res = '<em>Request received: </em>';

		if (preg_match('/^([^:]+)+:(.*)$/', $data['wpaction'], $matches)) {
			$action = $matches[1];
			if (null === ($args = json_decode($matches[2], true))) {
				$res .= "The parameters (should be JSON) could not be decoded";
				$action = false;
			} else {
				if (is_string($args)) $args = array($args);
				$res .= "Will despatch action: ".esc_html($action).", parameters: ".esc_html(implode(',', $args));
			}
		} else {
			$action = $data['wpaction'];
			$res .= "Will despatch action: ".esc_html($action).", no parameters";
		}

		ob_get_clean();

		// Need to add this as the close browser should only work for SLP
		if ($close_connection_callable) {
			if (is_callable($close_connection_callable)) {
				call_user_func($close_connection_callable, array('r' => $res));
			} else {
				$syllabplus->close_browser_connection(json_encode(array('r' => $res)));
			}
		}

		if (!empty($action)) {
			if (!empty($args)) {
				ob_start();
				$returned = do_action_ref_array($action, $args);
				$output = ob_get_clean();
				$res .= " - do_action_ref_array Trigger ";
			} else {
				ob_start();
				do_action($action);
				$output = ob_get_contents();
				ob_end_clean();
				$res .= " - do_action Trigger ";
			}
		}
		$response 				= array();
		$response['response'] 	= $res;
		$response['log'] 		= $output;

		// Check if response is empty
		if (!empty($returned)) $response['status'] = $returned;

		return $response;
	}

	/**
	 * Enqueue JSTree JavaScript and CSS, taking into account whether it is already enqueued, and current debug settings
	 */
	public function enqueue_jstree() {
		global $syllabplus;

		static $already_enqueued = false;
		if ($already_enqueued) return;
		
		$already_enqueued = true;
		$jstree_enqueue_version = $syllabplus->use_unminified_scripts() ? '3.3.12-rc.0'.'.'.time() : '3.3.12-rc.0';
		$min_or_not = $syllabplus->use_unminified_scripts() ? '' : '.min';
		
		wp_enqueue_script('jstree', SYLLABPLUS_URL.'/includes/jstree/jstree'.$min_or_not.'.js', array('jquery'), $jstree_enqueue_version);
		wp_enqueue_style('jstree', SYLLABPLUS_URL.'/includes/jstree/themes/default/style'.$min_or_not.'.css', array(), $jstree_enqueue_version);
	}
	
	/**
	 * Detects byte-order mark at the start of common files and change waning message texts
	 *
	 * @return string|boolean BOM warning text or false if not bom characters detected
	 */

    public function get_bom_warning_text() { 
      return false;
    }


	/**
	 * Gets an instance of the "SyllabPlus_SyllabCentral_Cloud" class which will be
	 * used to login or register the user to the SyllabCentral cloud
	 *
	 * @return object
	 */
	public function get_syllabcentral_cloud() {
		if (!class_exists('SyllabPlus_SyllabCentral_Cloud')) include_once(SYLLABPLUS_DIR.'/includes/syllabcentral.php');
		return new SyllabPlus_SyllabCentral_Cloud();
	}

	/**
	 * This function will build and return the SyllabPlus tempoaray clone ui widget
	 *
	 * @param boolean $include_testing_ui	 - a boolean to indicate if testing-only UI elements should be shown (N.B. they can only work if the user also has testing permissions)
	 * @param array   $supported_wp_versions - an array of supported WordPress versions
	 * @param array   $supported_packages    - an array of supported clone packages
	 * @param array   $supported_regions     - an array of supported clone regions
	 *
	 * @return string - the clone UI widget
	 */
	public function syllabplus_clone_ui_widget($include_testing_ui, $supported_wp_versions, $supported_packages, $supported_regions) {
		global $syllabplus;

		$output = '<p class="syllabplus-option syllabplus-option-inline php-version">';
		$output .= '<span class="syllabplus-option-label">'.sprintf(__('%s version:', 'syllab-backup'), 'PHP').'</span> ';
		$output .= $this->output_select_data($this->php_versions, 'php');
		$output .= '</p>';
		$output .= '<p class="syllabplus-option syllabplus-option-inline wp-version">';
		$output .= ' <span class="syllabplus-option-label">'.sprintf(__('%s version:', 'syllab-backup'), 'WordPress').'</span> ';
		$output .= $this->output_select_data($this->get_wordpress_versions($supported_wp_versions), 'wp');
		$output .= '</p>';
		$output .= '<p class="syllabplus-option syllabplus-option-inline region">';
		$output .= ' <span class="syllabplus-option-label">'.__('Clone region:', 'syllabplus').'</span> ';
		$output .= $this->output_select_data($supported_regions, 'region');
		$output .= '</p>';
		
		$backup_history = SyllabPlus_Backup_History::get_history();
		
		foreach ($backup_history as $key => $backup) {
			$backup_complete = $this->check_backup_is_complete($backup, false, true, false);
			$remote_sent = !empty($backup['service']) && ((is_array($backup['service']) && in_array('remotesend', $backup['service'])) || 'remotesend' === $backup['service']);
			if (!$backup_complete || $remote_sent) unset($backup_history[$key]);
		}

		
		$output .= '<p class="syllabplus-option syllabplus-option-inline syllabclone-backup">';
		$output .= ' <span class="syllabplus-option-label">'.__('Clone:', 'syllabplus').'</span> ';
		$output .= '<select id="syllabplus_clone_backup_options" name="syllabplus_clone_backup_options">';
		$output .= '<option value="current" data-nonce="current" data-timestamp="current" selected="selected">'. __('This current site', 'syllabplus') .'</option>';
		$output .= '<option value="wp_only" data-nonce="wp_only" data-timestamp="wp_only">'. __('An empty WordPress install', 'syllabplus') .'</option>';

		if (!empty($backup_history)) {
			foreach ($backup_history as $key => $backup) {
				$total_size = round($syllabplus->get_total_backup_size($backup) / 1073741824, 1);
				$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $key), 'M d, Y G:i');
				$label = isset($backup['label']) ? ' ' . $backup['label'] : '';
				$output .= '<option value="'.$key. '" data-nonce="'.$backup['nonce'].'" data-timestamp="'.$key.'" data-size="'.$total_size.'">' . $pretty_date . $label . '</option>';
			}
		}
		$output .= '</select>';
		$output .= '</p>';
		$output .= '<p class="syllabplus-option syllabplus-option-inline package">';
		$output .= ' <span class="syllabplus-option-label">'.__('Clone package:', 'syllabplus').'</span> ';
		$output .= '<select id="syllabplus_clone_package_options" name="syllabplus_clone_package_options" data-package_version="starter">';
		foreach ($supported_packages as $key => $value) {
			$output .= '<option value="'.esc_attr($key).'" data-size="'.esc_attr($value).'"';
			if ('starter' == $key) $output .= 'selected="selected"';
			$output .= ">".esc_html($key) . ('starter' == $key ? ' ' . __('(current version)', 'syllabplus') : '')."</option>\n";
		}
		$output .= '</select>';
		$output .= '</p>';

		if ((defined('SYLLABPLUS_SYLLABCLONE_DEVELOPMENT') && SYLLABPLUS_SYLLABCLONE_DEVELOPMENT) || $include_testing_ui) {
			$output .= '<p class="syllabplus-option syllabplus-option-inline syllabclone-branch">';
			$output .= ' <span class="syllabplus-option-label">SyllabClone Branch:</span> ';
			$output .= '<input id="syllabplus_clone_syllabclone_branch" type="text" size="36" name="syllabplus_clone_syllabclone_branch" value="">';
			$output .= '</p>';
			$output .= '<p class="syllabplus-option syllabplus-option-inline syllabplus-branch">';
			$output .= ' <span class="syllabplus-option-label">SyllabPlus Branch:</span> ';
			$output .= '<input id="syllabplus_clone_syllabplus_branch" type="text" size="36" name="syllabplus_clone_syllabplus_branch" value="">';
			$output .= '</p>';
			$output .= '<p><input type="checkbox" id="syllabplus_clone_use_queue" name="syllabplus_clone_use_queue" value="1" checked="checked"><label for="syllabplus_clone_use_queue" class="syllabplus_clone_use_queue">Use the SyllabClone queue</label></p>';
		}
		$output .= '<p class="syllabplus-option limit-to-admins">';
		$output .= '<input type="checkbox" class="syllabplus_clone_admin_login_options" id="syllabplus_clone_admin_login_options" name="syllabplus_clone_admin_login_options" value="1" checked="checked">';
		$output .= '<label for="syllabplus_clone_admin_login_options" class="syllabplus_clone_admin_login_options_label">'.__('Forbid non-administrators to login to WordPress on your clone', 'syllabplus').'</label>';
		$output .= '</p>';

		$output = apply_filters('syllabplus_clone_additional_ui', $output);

		return $output;
	}

	/**
	 * This function will output a select input using the passed in values.
	 *
	 * @param array  $data     - the keys and values for the select
	 * @param string $name     - the name of the items in the select input
	 * @param string $selected - the value we want selected by default
	 *
	 * @return string          - the output of the select input
	 */
	public function output_select_data($data, $name, $selected = '') {
		
		$name_version = empty($selected) ? $this->get_current_version($name) : $selected;
		
		$output = '<select id="syllabplus_clone_'.$name.'_options" name="syllabplus_clone_'.$name.'_options" data-'.$name.'_version="'.$name_version.'">';

		foreach ($data as $value) {
			$output .= "<option value=\"$value\" ";
			if ($value == $name_version) $output .= 'selected="selected"';
			$output .= ">".esc_html($value) . ($value == $name_version ? ' ' . __('(current version)', 'syllabplus') : '')."</option>\n";
		}
			
		$output .= '</select>';

		return $output;
	}

	/**
	 * This function will output the clones network information
	 *
	 * @param string $url - the clone URL
	 *
	 * @return string     - the clone network information
	 */
	public function syllabplus_clone_info($url) {
		global $syllabplus;
		
		if (!empty($url)) {
			$content = '<div class="syllabclone_network_info">';
			$content .= '<p>' . __('Your clone has started and will be available at the following URLs once it is ready.', 'syllabplus') . '</p>';
			$content .= '<p><strong>' . __('Front page:', 'syllabplus') . '</strong> <a target="_blank" href="' . esc_html($url) . '">' . esc_html($url) . '</a></p>';
			$content .= '<p><strong>' . __('Dashboard:', 'syllabplus') . '</strong> <a target="_blank" href="' . esc_html(trailingslashit($url)) . 'wp-admin">' . esc_html(trailingslashit($url)) . 'wp-admin</a></p>';
			$content .= '</div>';
			$content .= '<p><a target="_blank" href="'.$syllabplus->get_url('my-account').'">'.__('You can find your temporary clone information in your syllabplus.com account here.', 'syllabplus').'</a></p>';
		} else {
			$content = '<p>' . __('Your clone has started, network information is not yet available but will be displayed here and at your syllabplus.com account once it is ready.', 'syllabplus') . '</p>';
			$content .= '<p><a target="_blank" href="' . $syllabplus->get_url('my-account') . '">' . __('You can find your temporary clone information in your syllabplus.com account here.', 'syllabplus') . '</a></p>';
		}

		return $content;
	}

	/**
	 * This function will build and return an array of major WordPress versions, the array is built by calling the WordPress version API once every 24 hours and adding any new entires to our existing array of versions.
	 *
	 * @param array $supported_wp_versions - an array of supported WordPress versions
	 *
	 * @return array - an array of WordPress major versions
	 */
	private function get_wordpress_versions($supported_wp_versions) {

		if (empty($supported_wp_versions)) $supported_wp_versions[] = $this->get_current_version('wp');
		
		$key = array_search($this->get_current_version('wp'), $supported_wp_versions);
		
		if ($key) {
			$supported_wp_versions = array_slice($supported_wp_versions, $key);
		}

		$version_array = $supported_wp_versions;

		return $version_array;
	}

	/**
	 * This function will get the current version the server is running for the passed in item e.g WordPress or PHP
	 *
	 * @param string $name - the thing we want to get the version for e.g WordPress or PHP
	 *
	 * @return string      - returns the current version of the passed in item
	 */
	public function get_current_version($name) {
		
		$version = '';

		if ('php' == $name) {
			$parts = explode(".", PHP_VERSION);
			$version = $parts[0] . "." . $parts[1];
		} elseif ('wp' == $name) {
			global $syllabplus;
			$wp_version = $syllabplus->get_wordpress_version();
			$parts = explode(".", $wp_version);
			$version = $parts[0] . "." . $parts[1];
		}
		
		return $version;
	}

	/**
	 * Show which remote storage settings are partially setup error, or if manual auth is supported show the manual auth UI
	 */
	public function show_admin_warning_if_remote_storage_with_partial_setttings() {
		if ((isset($_REQUEST['page']) && 'syllabplus' == $_REQUEST['page']) || (defined('DOING_AJAX') && DOING_AJAX)) {
			$enabled_services = SyllabPlus_Storage_Methods_Interface::get_enabled_storage_objects_and_ids(array_keys($this->storage_service_with_partial_settings));
			foreach ($this->storage_service_with_partial_settings as $method => $method_name) {
				if (empty($enabled_services[$method]['object']) || empty($enabled_services[$method]['instance_settings']) || !$enabled_services[$method]['object']->supports_feature('manual_authentication')) {
					$this->show_admin_warning(sprintf(__('The following remote storage (%s) have only been partially configured, manual authorization is not supported with this remote storage, please try again and if the problem persists contact support.', 'syllabplus'), $method), 'error');
				} else {
					$this->show_admin_warning($enabled_services[$method]['object']->get_manual_authorisation_template(), 'error');
				}
			}
		} else {
			$this->show_admin_warning('SyllabPlus: '.sprintf(__('The following remote storage (%s) have only been partially configured, if you are having problems you can try to manually authorise at the SyllabPlus settings page.', 'syllabplus'), implode(', ', $this->storage_service_with_partial_settings)).' <a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus&amp;tab=settings">'.__('Return to SyllabPlus configuration', 'syllabplus').'</a>', 'error');
		}
	}

	/**
	 * Show remote storage settings are empty warning
	 */
	public function show_admin_warning_if_remote_storage_settting_are_empty() {
		if ((isset($_REQUEST['page']) && 'syllabplus' == $_REQUEST['page']) || (defined('DOING_AJAX') && DOING_AJAX)) {
			$this->show_admin_warning(sprintf(__('You have requested saving to remote storage (%s), but without entering any settings for that storage.', 'syllabplus'), implode(', ', $this->storage_service_without_settings)), 'error');
		} else {
			$this->show_admin_warning('SylLab Backup: '.sprintf(__('You have requested saving to remote storage (%s), but without entering any settings for that storage.', 'syllabplus'), implode(', ', $this->storage_service_without_settings)).' <a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus&amp;tab=settings">'.__('Return to SylLab Backup configuration', 'syllabplus').'</a>', 'error');
		}
	}

	/**
	 * Receive Heartbeat data and respond.
	 *
	 * Processes data received via a Heartbeat request, and returns additional data to pass back to the front end.
	 *
	 * @param array $response - Heartbeat response data to pass back to front end.
	 * @param array $data     - Data received from the front end (unslashed).
	 */
	public function process_status_in_heartbeat($response, $data) {
		if (!is_array($response) || empty($data['syllabplus'])) return $response;
		try {
			$response['syllabplus'] = $this->get_activejobs_list(SyllabPlus_Manipulation_Functions::wp_unslash($data['syllabplus']));
		} catch (Exception $e) {
			$log_message = 'PHP Fatal Exception error ('.get_class($e).') has occurred during get active job list. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			$response['syllabplus'] = array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			);
		// @codingStandardsIgnoreLine
		} catch (Error $e) {
			$log_message = 'PHP Fatal error ('.get_class($e).') has occurred during get active job list. Error Message: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			$response['syllabplus'] = array(
				'fatal_error' => true,
				'fatal_error_message' => $log_message
			);
		}

		if (SyllabPlus_Options::user_can_manage() && isset($data['syllabplus']['syllab_credentialtest_nonce'])) {
			if (!wp_verify_nonce($data['syllabplus']['syllab_credentialtest_nonce'], 'syllabplus-credentialtest-nonce')) {
				$response['syllabplus']['syllab_credentialtest_nonce'] = wp_create_nonce('syllabplus-credentialtest-nonce');
			}
		}

		$response['syllabplus']['time_now'] = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'D, F j, Y H:i');

		return $response;
	}

	/**
	 * Show warning about restriction implied by the hosting company (can only perform a full backup once per month, incremental backup should not go above one per day)
	 */
	public function show_admin_warning_one_backup_per_month() {

		global $syllabplus;

		$hosting_company = $syllabplus->get_hosting_info();

		$txt1 = __('Your website is hosted with %s (%s).', 'syllabplus');
		$txt2 = __('%s permits SyllabPlus to perform only one backup per month. Thus, we recommend you choose a full backup when performing a manual backup and to use that option when creating a scheduled backup.', 'syllabplus');
		$txt3 = __('Due to the restriction, some settings can be automatically adjusted, disabled or not available.', 'syllabplus');

		$this->show_plugin_page_admin_warning('<strong>'.__('Warning', 'syllabplus').':</strong> '.sprintf("$txt1 $txt2 $txt3", $hosting_company['name'], $hosting_company['website'], $hosting_company['name']), 'update-nag notice notice-warning', true);
	}

	/**
	 * Find out if the current request is a backup download request, and proceed with the download if it is
	 */
	public function maybe_download_backup_from_email() {
		global $pagenow;
		if ((!defined('DOING_AJAX') || !DOING_AJAX) && SyllabPlus_Options::admin_page() === $pagenow && isset($_REQUEST['page']) && 'syllabplus' === $_REQUEST['page'] && isset($_REQUEST['action']) && 'syllab_download_backup' === $_REQUEST['action']) {
			$findexes = empty(sanitize_text_field($_REQUEST['findex'])) ? array(0) : sanitize_text_field($_REQUEST['findex']);
			$timestamp = empty(sanitize_text_field($_REQUEST['timestamp'])) ? '' : sanitize_text_field($_REQUEST['timestamp']);
			$nonce = empty(sanitize_text_field($_REQUEST['nonce'])) ? '' : sanitize_text_field($_REQUEST['nonce']);
			$type = empty($_REQUEST['type']) ? '' : sanitize_text_field($_REQUEST['type']);
			if (empty($timestamp) || empty($nonce) || empty($type)) wp_die(__('The download link is broken, you may have clicked the link from untrusted source', 'syllabplus'), '', array('back_link' => true));
			$backup_history = SyllabPlus_Backup_History::get_history();
			if (!isset($backup_history[$timestamp]['nonce']) || $backup_history[$timestamp]['nonce'] !== $nonce) wp_die(__("The download link is broken or the backup file is no longer available", 'syllabplus'), '', array('back_link' => true));
			$this->do_syllab_download_backup($findexes, $type, $timestamp, 2, false, '');
			exit; // we don't need anything else but an exit
		}
	}
}
