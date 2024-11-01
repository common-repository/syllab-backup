<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/
*/
// @codingStandardsIgnoreStart
/*
Plugin Name: SylLab Backup – HIPAA, GDPR, CCPA Framework
Plugin URI: https://syllab.io/
Description: Backup : Backups made easy - manual or scheduled backup to SylLab Vault. We provide high-grade security and compliance framework storage. schedules.
Author: SylLab Systems, Inc
Version: 1.0.2
License: GPLv3 or later
*/
// @codingStandardsIgnoreEnd


if (!defined('ABSPATH')) die('No direct access allowed');

if ((isset($syllabplus) && is_object($syllabplus) && is_a($syllabplus, 'SyllabPlus')) || function_exists('syllabplus_modify_cron_schedules')) return; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable

define('SYLLABPLUS_DIR', dirname(__FILE__));
define('SYLLABPLUS_URL', plugins_url('', __FILE__));
define('SYLLAB_DEFAULT_OTHERS_EXCLUDE', 'upgrade,cache,syllab,backup*,*backups,mysql.sql,debug.log');
define('SYLLAB_DEFAULT_UPLOADS_EXCLUDE', 'backup*,*backups,backwpup*,wp-clone,snapshots');

// The following can go in your wp-config.php
// Tables whose data can be skipped without significant loss, if (and only if) the attempt to back them up fails (e.g. bwps_log, from WordPress Better Security, is log data; but individual entries can be huge and cause out-of-memory fatal errors on low-resource environments). Comma-separate the table names (without the WordPress table prefix).
if (!defined('SYLLABPLUS_DATA_OPTIONAL_TABLES')) define('SYLLABPLUS_DATA_OPTIONAL_TABLES', 'bwps_log,statpress,slim_stats,redirection_logs,Counterize,Counterize_Referers,Counterize_UserAgents,wbz404_logs,wbz404_redirects,tts_trafficstats,tts_referrer_stats,wponlinebackup_generations,svisitor_stat,simple_feed_stats,itsec_log,relevanssi_log,blc_instances,wysija_email_user_stat,woocommerce_sessions,et_bloom_stats,redirection_404,lbakut_activity_log,stream_meta,wfFileMods,wffilemods,wfBlockedIPLog,wfblockediplog,page_visit_history,strack_st');
if (!defined('SYLLABPLUS_ZIP_EXECUTABLE')) define('SYLLABPLUS_ZIP_EXECUTABLE', "/usr/bin/zip,/bin/zip,/usr/local/bin/zip,/usr/sfw/bin/zip,/usr/xdg4/bin/zip,/opt/bin/zip");
if (!defined('SYLLABPLUS_MYSQLDUMP_EXECUTABLE')) define('SYLLABPLUS_MYSQLDUMP_EXECUTABLE', syllabplus_build_mysqldump_list());
// If any individual file size is greater than this, then a warning is given
if (!defined('SYLLABPLUS_WARN_FILE_SIZE')) define('SYLLABPLUS_WARN_FILE_SIZE', 1024*1024*250);
// On a test on a Pentium laptop, 100,000 rows needed ~ 1 minute to write out - so 150,000 is around the CPanel default of 90 seconds execution time.
if (!defined('SYLLABPLUS_WARN_DB_ROWS')) define('SYLLABPLUS_WARN_DB_ROWS', 150000);

// The smallest value (in megabytes) that the "split zip files at" setting is allowed to be set to
if (!defined('SYLLABPLUS_SPLIT_MIN')) define('SYLLABPLUS_SPLIT_MIN', 25);

// The maximum number of files to batch at one time when writing to the backup archive. You'd only be likely to want to raise (not lower) this.
if (!defined('SYLLABPLUS_MAXBATCHFILES')) define('SYLLABPLUS_MAXBATCHFILES', 1000);

// If any individual email attachment is greater than this, then a warning is given (and then removed if the email actually succeeds)
if (!defined('SYLLABPLUS_WARN_EMAIL_SIZE')) define('SYLLABPLUS_WARN_EMAIL_SIZE', 20*1048576);

// Filetypes that should be stored inside the zip without any attempt at further compression. By default, we mark several extensions that refer to filetypes that are already compressed as not needing further compression - which saves time/resources. This option only applies to zip engines that support varying the compression method. Specify in lower-case, and upper-case variants (and for some zip engines, all variants) will automatically be included.
if (!defined('SYLLABPLUS_ZIP_NOCOMPRESS')) define('SYLLABPLUS_ZIP_NOCOMPRESS', '.jpg,.jpeg,.png,.gif,.zip,.gz,.bz2,.xz,.rar,.mp3,.mp4,.mpeg,.avi,.mov');

// This is passed to set_time_limit() at various points, to try to maximise run-time. (UD resumes if it gets killed, but more in one stretch always helps). The effect of this varies according to the hosting setup - it can't necessarily always be controlled.
if (!defined('SYLLABPLUS_SET_TIME_LIMIT')) define('SYLLABPLUS_SET_TIME_LIMIT', 900);

// Options to pass to the zip binary (if that method happens to be used). By default, we mark the extensions specified in SYLLABPLUS_ZIP_NOCOMPRESS for non-compression via the -n flag
if (!defined('SYLLABPLUS_BINZIP_OPTS')) {
	$zip_nocompress = array_map('trim', explode(',', SYLLABPLUS_ZIP_NOCOMPRESS));
	$zip_binzip_opts = '';
	foreach ($zip_nocompress as $ext) {
		if (empty($zip_binzip_opts)) {
			$zip_binzip_opts = "-n $ext:".strtoupper($ext);
		} else {
			$zip_binzip_opts .= ':'.$ext.':'.strtoupper($ext);
		}
	}
	define('SYLLABPLUS_BINZIP_OPTS', $zip_binzip_opts);
}

// Load add-ons and files that may or may not be present, depending on where the plugin was distributed
if (is_file(SYLLABPLUS_DIR.'/autoload.php')) require_once(SYLLABPLUS_DIR.'/autoload.php');

if (!function_exists('syllabplus_modify_cron_schedules')) :
/**
 * wp-cron only has hourly, daily and twicedaily, so we need to add some of our own
 *
 * @param  array $schedules an array of schedule types
 * @return array
 */
function syllabplus_modify_cron_schedules($schedules) {
		$schedules['weekly'] = array('interval' => 604800, 'display' => 'Once Weekly');
		$schedules['fortnightly'] = array('interval' => 1209600, 'display' => 'Once Each Fortnight');
		$schedules['monthly'] = array('interval' => 2592000, 'display' => 'Once Monthly');
		$schedules['everyhour'] = array('interval' => 3600, 'display' => __('Every hour', 'syllabplus'));
		$schedules['every2hours'] = array('interval' => 7200, 'display' => sprintf(__('Every %s hours', 'syllabplus'), 2));
		$schedules['every4hours'] = array('interval' => 14400, 'display' => sprintf(__('Every %s hours', 'syllabplus'), 4));
		$schedules['every8hours'] = array('interval' => 28800, 'display' => sprintf(__('Every %s hours', 'syllabplus'), 8));
		return $schedules;
}
endif;
// http://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules. Raised priority because some plugins wrongly over-write all prior schedule changes (including BackupBuddy!)
add_filter('cron_schedules', 'syllabplus_modify_cron_schedules', 30);

// The checks here before loading are for performance only - unless one of those conditions is met, then none of the hooks will ever be used
if (!is_admin() && (!defined('DOING_CRON') || !DOING_CRON) && (!defined('XMLRPC_REQUEST') || !XMLRPC_REQUEST) && empty($_SERVER['SHELL']) && empty($_SERVER['USER']) && empty($_POST['slrpc_message']) && empty($_GET['udcentral_action']) && (defined('SYLLABPLUS_THIS_IS_CLONE') && "1" != SYLLABPLUS_THIS_IS_CLONE) && empty($_GET['uc_auto_login']) && (empty($_SERVER['REQUEST_METHOD']) || 'OPTIONS' != $_SERVER['REQUEST_METHOD']) && (!defined('WP_CLI') || !WP_CLI)) {
	// There is no good way to work out if the cron event is likely to be called under the ALTERNATE_WP_CRON system, other than re-running the calculation
	// If ALTERNATE_WP_CRON is not active (and a few other things), then we are done
	if (!defined('ALTERNATE_WP_CRON') || !ALTERNATE_WP_CRON || !empty($_POST) || defined('DOING_AJAX') || isset($_GET['doing_wp_cron'])) return;

	// The check below is the one used by spawn_cron() to decide whether cron events should be run
	$gmt_time = microtime(true);
	$lock = get_transient('doing_cron');
	if ($lock > $gmt_time + 10 * 60) $lock = 0;
	if ((defined('WP_CRON_LOCK_TIMEOUT') && $lock + WP_CRON_LOCK_TIMEOUT > $gmt_time) || (!defined('WP_CRON_LOCK_TIMEOUT') && $lock + 60 > $gmt_time)) return;
	if (function_exists('_get_cron_array')) {
		$crons = _get_cron_array();
	} else {
		$crons = get_option('cron');
	}
	if (!is_array($crons)) return;

	$keys = array_keys($crons);
	if (isset($keys[0]) && $keys[0] > $gmt_time) return;
	// If we got this far, then cron is going to be fired, so we do want to load all our hooks
}

$syllabplus_have_addons = 0;
if (is_dir(SYLLABPLUS_DIR.'/addons') && $dir_handle = opendir(SYLLABPLUS_DIR.'/addons')) {
	while (false !== ($e = readdir($dir_handle))) {
		if (is_file(SYLLABPLUS_DIR.'/addons/'.$e) && preg_match('/\.php$/', $e)) {
			// We used to have 1024 bytes here - but this meant that if someone's site was hacked and a lot of code added at the top, and if they were running a too-low PHP version, then they might just see the symptom rather than the cause - and raise the support request with us.
			$header = file_get_contents(SYLLABPLUS_DIR.'/addons/'.$e, false, null, 0, 16384);
			$phprequires = preg_match("/RequiresPHP: (\d[\d\.]+)/", $header, $matches) ? $matches[1] : false;
			$phpinclude = preg_match("/IncludePHP: (\S+)/", $header, $matches) ? $matches[1] : false;
			if (false === $phprequires || version_compare(PHP_VERSION, $phprequires, '>=')) {
				$syllabplus_have_addons++;
				if ($phpinclude) include_once(SYLLABPLUS_DIR.'/'.$phpinclude);
				include_once(SYLLABPLUS_DIR.'/addons/'.$e);
			}
		}
	}
	@closedir($dir_handle);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
}

if (is_file(SYLLABPLUS_DIR.'/udaddons/syllabplus-addons.php')) require_once(SYLLABPLUS_DIR.'/udaddons/syllabplus-addons.php');

if (!file_exists(SYLLABPLUS_DIR.'/class-syllabplus.php') || !file_exists(SYLLABPLUS_DIR.'/options.php')) {
	/**
	 * Warn if they've not got the whole plugin - can happen if WP crashes (e.g. out of disk space) when upgrading the plugin
	 */
	function syllabplus_incomplete_install_warning() {
		echo '<div class="syllabmessage error"><p><strong>'.__('Error', 'syllabplus').':</strong> '.__("You do not have SyllabPlus completely installed - please de-install and install it again. Most likely, WordPress malfunctioned when copying the plugin files.", 'syllabplus').' <a href="https://syllabplus.com/faqs/wordpress-crashed-when-updating-syllabplus-what-can-i-do/">'.__('Go here for more information.', 'syllabplus').'</a></p></div>';
	}
	add_action('all_admin_notices', 'syllabplus_incomplete_install_warning');
} else {

	include_once(SYLLABPLUS_DIR.'/class-syllabplus.php');
	$syllabplus = new SyllabPlus();
	$GLOBALS['syllabplus'] = $syllabplus;
	$syllabplus->have_addons = $syllabplus_have_addons;

	if (!$syllabplus->memory_check(192)) {
	// Experience appears to show that the memory limit is only likely to be hit (unless it is very low) by single files that are larger than available memory (when compressed)
		// Add sanity checks - found someone who'd set WP_MAX_MEMORY_LIMIT to 256K !
		if (!$syllabplus->memory_check($syllabplus->memory_check_current(WP_MAX_MEMORY_LIMIT))) {
			$new = absint($syllabplus->memory_check_current(WP_MAX_MEMORY_LIMIT));
			if ($new>32 && $new<100000) {
				@ini_set('memory_limit', $new.'M');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	// Blocking updates during restore; placed here so that it still runs e.g. under WP-CLI
//	$syllabplus->block_updates_during_restore_progress();
}

// Ubuntu bug - https://bugs.launchpad.net/ubuntu/+source/php5/+bug/1315888
if (!function_exists('gzopen') && function_exists('gzopen64')) {
	function gzopen($filename, $mode, $use_include_path = 0) {
		return gzopen64($filename, $mode, $use_include_path);
	}
}

/**
 * For finding mysqldump. Added to include Windows locations.
 */
function syllabplus_build_mysqldump_list() {
	if ('win' == strtolower(substr(PHP_OS, 0, 3)) && function_exists('glob')) {
		$drives = array('C', 'D', 'E');
		
		if (!empty($_SERVER['DOCUMENT_ROOT'])) {
			// Get the drive that this is running on
			$current_drive = strtoupper(substr($_SERVER['DOCUMENT_ROOT'], 0, 1));
			if (!in_array($current_drive, $drives)) array_unshift($drives, $current_drive);
		}
		
		$directories = array();
		
		foreach ($drives as $drive_letter) {
			$dir = glob("$drive_letter:\\{Program Files\\MySQL\\{,MySQL*,etc}{,\\bin,\\?},mysqldump}\\mysqldump*", GLOB_BRACE);
			if (is_array($dir)) $directories = array_merge($directories, $dir);
		}
		
		$drive_string = implode(',', $directories);
		return $drive_string;
		
	} else {
		return "/usr/bin/mysqldump,/bin/mysqldump,/usr/local/bin/mysqldump,/usr/sfw/bin/mysqldump,/usr/xdg4/bin/mysqldump,/opt/bin/mysqldump";
	}
}

// Do this even if the missing files detection above fired, as the "missing files" detection above has a greater chance of showing the user useful info
if (!class_exists('SyllabPlus_Options')) require_once(SYLLABPLUS_DIR.'/options.php');
