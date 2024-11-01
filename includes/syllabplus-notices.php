<?php

/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

if (!class_exists('Syllab_Notices')) require_once(SYLLABPLUS_DIR.'/includes/syllab-notices.php');

class SyllabPlus_Notices extends Syllab_Notices {

	protected static $_instance = null;

	private $initialized = false;

	protected $notices_content = array();
	
	protected $self_affiliate_id = 212;

	public static function instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected function populate_notices_content() {
		
		$parent_notice_content = parent::populate_notices_content();

		$child_notice_content = array(
			1 => array(
				'prefix' => __('SyllabPlus Premium:', 'syllabplus'),
				'title' => __('Support', 'syllabplus'),
				'text' => __('Enjoy professional, fast, and friendly help whenever you need it with Premium.', 'syllabplus'),
				'image' => 'notices/support.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'support',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			2 => array(
				'prefix' => __('SyllabPlus Premium:', 'syllabplus'),
				'title' => __('SyllabVault storage', 'syllabplus'),
				'text' => __('The ultimately secure and convenient place to store your backups.', 'syllabplus'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => 'https://syllabplus.com/landing/vault',
				'campaign' => 'vault',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			3 => array(
				'prefix' => __('SyllabPlus Premium:', 'syllabplus'),
				'title' => __('enhanced remote storage options', 'syllabplus'),
				'text' => __('Enhanced storage options for Dropbox, Google Drive and S3. Plus many more options.', 'syllabplus'),
				'image' => 'addons-images/morestorage.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'morestorage',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			4 => array(
				'prefix' => __('SyllabPlus Premium:', 'syllabplus'),
				'title' => __('advanced options', 'syllabplus'),
				'text' => __('Secure multisite installation, advanced reporting and much more.', 'syllabplus'),
				'image' => 'addons-images/reporting.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'reporting',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			5 => array(
				'prefix' => __('SyllabPlus Premium:', 'syllabplus'),
				'title' => __('secure your backups', 'syllabplus'),
				'text' => __('Add SFTP to send your data securely, lock settings and encrypt your database backups for extra security.', 'syllabplus'),
				'image' => 'addons-images/lockadmin.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'lockadmin',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			6 => array(
				'prefix' => __('SyllabPlus Premium:', 'syllabplus'),
				'title' => __('easily migrate or clone your site in minutes', 'syllabplus'),
				'text' => __('Copy your site to another domain directly. Includes find-and-replace tool for database references.', 'syllabplus'),
				'image' => 'addons-images/migrator.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'migrator',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
			),
			7 => array(
				'prefix' => '',
				'title' => __('Introducing SyllabCentral', 'syllabplus'),
				'text' => __('SyllabCentral is a highly efficient way to manage, update and backup multiple websites from one place.', 'syllabplus'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => 'https://syllabcentral.com',
				'button_meta' => 'syllabcentral',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			8 => array(
				'prefix' => '',
				'title' => __('Do you use SyllabPlus on multiple sites?', 'syllabplus'),
				'text' => __('Control all your WordPress installations from one place using SyllabCentral remote site management!', 'syllabplus'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => 'https://syllabcentral.com',
				'button_meta' => 'syllabcentral',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
			),
			'rate' => array(
				'text' => __("Hey - We noticed SyllabPlus has kept your site safe for a while.  If you like us, please consider leaving a positive review to spread the word.  Or if you have any issues or questions please leave us a support message", 'syllabplus') . ' <a href="https://wordpress.org/support/plugin/syllabplus/" target="_blank">' . __('here', 'syllabplus') . '.</a><br>' . __('Thank you so much!', 'syllabplus') . '<br><br> - <b>' . __('Team Syllab', 'syllabplus') . '</b><br>',
				'image' => 'notices/ud_smile.png',
				'button_link' => 'https://wordpress.org/support/plugin/syllabplus/reviews/?rate=5#new-post',
				'button_meta' => 'review',
				'dismiss_time' => 'dismiss_review_notice',
				'supported_positions' => $this->dashboard_top,
				'validity_function' => 'show_rate_notice'
			),
			'translation_needed' => array(
				'prefix' => '',
				'title' => 'Can you translate? Want to improve SyllabPlus for speakers of your language?',
				'text' => $this->url_start(true, 'syllabplus.com/translate/')."Please go here for instructions - it is easy.".$this->url_end(true, 'syllabplus.com/translate/'),
				'text_plain' => $this->url_start(false, 'syllabplus.com/translate/')."Please go here for instructions - it is easy.".$this->url_end(false, 'syllabplus.com/translate/'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => false,
				'dismiss_time' => false,
				'supported_positions' => $this->anywhere,
				'validity_function' => 'translation_needed',
			),
			'social_media' => array(
				'prefix' => '',
				'title' => __('SyllabPlus is on social media - check us out!', 'syllabplus'),
				'text' => $this->url_start(true, 'twitter.com/syllabplus', true). __('Twitter', 'syllabplus'). $this->url_end(true, 'twitter.com/syllabplus', true).
						' - '.
						$this->url_start(true, 'facebook.com/syllabplus', true). __('Facebook', 'syllabplus'). $this->url_end(true, 'facebook.com/syllabplus', true),
				'text_plain' => $this->url_start(false, 'twitter.com/syllabplus', true). __('Twitter', 'syllabplus'). $this->url_end(false, 'twitter.com/syllabplus', true).
						' - '.
						$this->url_start(false, 'facebook.com/syllabplus', true). __('Facebook', 'syllabplus'). $this->url_end(false, 'facebook.com/syllabplus', true),
				'image' => 'notices/syllab_logo.png',
				'dismiss_time' => false,
				'supported_positions' => $this->anywhere,
			),
			'newsletter' => array(
				'prefix' => '',
				'title' => __('SyllabPlus Newsletter', 'syllabplus'),
				'text' => __("Follow this link to sign up for the SyllabPlus newsletter.", 'syllabplus'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => 'https://syllabplus.com/newsletter-signup',
				'campaign' => 'newsletter',
				'button_meta' => 'signup',
				'supported_positions' => $this->anywhere,
				'dismiss_time' => false
			),
			'subscribe_blog' => array(
				'prefix' => '',
				'title' => __('SyllabPlus Blog - get up-to-date news and offers', 'syllabplus'),
				'text' => $this->url_start(true, 'syllabplus.com/news/').__("Blog link", 'syllabplus').$this->url_end(true, 'syllabplus.com/news/').' - '.$this->url_start(true, 'feeds.feedburner.com/SyllabPlus').__("RSS link", 'syllabplus').$this->url_end(true, 'feeds.feedburner.com/SyllabPlus'),
				'text_plain' => $this->url_start(false, 'syllabplus.com/news/').__("Blog link", 'syllabplus').$this->url_end(false, 'syllabplus.com/news/').' - '.$this->url_start(false, 'feeds.feedburner.com/SyllabPlus').__("RSS link", 'syllabplus').$this->url_end(false, 'feeds.feedburner.com/SyllabPlus'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => false,
				'supported_positions' => $this->anywhere,
				'dismiss_time' => false
			),
			'check_out_syllabplus_com' => array(
				'prefix' => '',
				'title' => __('SyllabPlus Blog - get up-to-date news and offers', 'syllabplus'),
				'text' => $this->url_start(true, 'syllabplus.com/news/').__("Blog link", 'syllabplus').$this->url_end(true, 'syllabplus.com/news/').' - '.$this->url_start(true, 'feeds.feedburner.com/SyllabPlus').__("RSS link", 'syllabplus').$this->url_end(true, 'feeds.feedburner.com/SyllabPlus'),
				'text_plain' => $this->url_start(false, 'syllabplus.com/news/').__("Blog link", 'syllabplus').$this->url_end(false, 'syllabplus.com/news/').' - '.$this->url_start(false, 'feeds.feedburner.com/SyllabPlus').__("RSS link", 'syllabplus').$this->url_end(false, 'feeds.feedburner.com/SyllabPlus'),
				'image' => 'notices/syllab_logo.png',
				'button_link' => false,
				'supported_positions' => $this->dashboard_bottom_or_report,
				'dismiss_time' => false
			),
			'autobackup' => array(
				'prefix' => '',
				'title' => __('Make updates easy with SyllabPlus', 'syllabplus'),
				'text' => __('Be safe', 'syllabplus') . ' - ' . $this->url_start(true, 'syllabplus.com/shop/syllabplus-premium/') . 'SyllabPlus Premium' . $this->url_end(true, 'syllabplus.com/shop/syllabplus-premium/') . ' ' . __('backs up automatically when you update plugins, themes or core', 'syllabplus'),
				'text2' => __('Save time', 'syllabplus') . ' - ' . $this->url_start(true, 'wordpress.org/plugins/stops-core-theme-and-plugin-updates/') . 'Easy Updates Manager' . $this->url_end(true, 'wordpress.org/plugins/stops-core-theme-and-plugin-updates/') . ' ' . __('handles updates automatically as you want them', 'syllabplus'),
				'text3' => __('Many sites?', 'syllabplus') . ' - ' . $this->url_start(true, 'syllabplus.com/syllabcentral/') . 'SyllabCentral' . $this->url_end(true, 'syllabplus.com/syllabcentral/') . ' ' . __('manages all your WordPress sites at once from one place', 'syllabplus'),
				'image' => 'addons-images/autobackup.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'autobackup',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismissautobackup',
				'supported_positions' => $this->autobackup_bottom_or_report,
			),
			'wp-optimize' => array(
				'prefix' => '',
				'title' => 'WP-Optimize',
				'text' => __("After you've backed up your database, we recommend you install our WP-Optimize plugin to streamline it for better website performance.", "syllabplus"),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://wordpress.org/plugins/wp-optimize/',
				'button_meta' => 'wp-optimize',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
				'validity_function' => 'wp_optimize_installed',
			),
			
			// The sale adverts content starts here
			'blackfriday' => array(
				'prefix' => '',
				'title' => __('Black Friday - 20% off SyllabPlus Premium until November 30th', 'syllabplus'),
				'text' => __('To benefit, use this discount code:', 'syllabplus').' ',
				'image' => 'notices/black_friday.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'blackfriday',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'blackfridaysale2021',
				'valid_from' => '2021-11-20 00:00:00',
				'valid_to' => '2021-11-30 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			'newyear' => array(
				'prefix' => '',
				'title' => __('Happy New Year - 20% off SyllabPlus Premium until January 14th', 'syllabplus'),
				'text' => __('To benefit, use this discount code:', 'syllabplus').' ',
				'image' => 'notices/new_year.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'newyear',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'newyearsale2022',
				'valid_from' => '2021-12-26 00:00:00',
				'valid_to' => '2022-01-14 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			'spring' => array(
				'prefix' => '',
				'title' => __('Spring sale - 20% off SyllabPlus Premium until May 31st', 'syllabplus'),
				'text' => __('To benefit, use this discount code:', 'syllabplus').' ',
				'image' => 'notices/spring.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'spring',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'springsale2021',
				'valid_from' => '2021-05-01 00:00:00',
				'valid_to' => '2021-05-31 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			'summer' => array(
				'prefix' => '',
				'title' => __('Summer sale - 20% off SyllabPlus Premium until July 31st', 'syllabplus'),
				'text' => __('To benefit, use this discount code:', 'syllabplus').' ',
				'image' => 'notices/summer.png',
				'button_link' => 'https://syllabplus.com/landing/syllabplus-premium',
				'campaign' => 'summer',
				'button_meta' => 'syllabplus',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'summersale2021',
				'valid_from' => '2021-07-01 00:00:00',
				'valid_to' => '2021-07-31 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
			),
			'collection' => array(
				'prefix' => '',
				'title' => __('The Syllab Plugin Collection Sale', 'syllabplus'),
				'text' => __('Get 20% off any of our plugins. But hurry - offer ends 30th September, use this discount code:', 'syllabplus').' ',
				'image' => 'notices/syllab_logo.png',
				'button_link' => 'https://teamsyllab.com',
				'campaign' => 'collection',
				'button_meta' => 'collection',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'SLP2021',
				'valid_from' => '2021-09-01 00:00:00',
				'valid_to' => '2021-09-30 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
			)
		);

		return array_merge($parent_notice_content, $child_notice_content);
	}
	
	/**
	 * Call this method to setup the notices
	 */
	public function notices_init() {
		if ($this->initialized) return;
		$this->initialized = true;
		// parent::notices_init();
		$this->notices_content = (defined('SYLLABPLUS_NOADS_B') && SYLLABPLUS_NOADS_B) ? array() : $this->populate_notices_content();
		global $syllabplus;
		$enqueue_version = $syllabplus->use_unminified_scripts() ? $syllabplus->version.'.'.time() : $syllabplus->version;
		$syllab_min_or_not = $syllabplus->get_syllabplus_file_version();

		wp_enqueue_style('syllabplus-notices-css',  SYLLABPLUS_URL.'/css/syllabplus-notices'.$syllab_min_or_not.'.css', array(), $enqueue_version);
	}

	protected function translation_needed($plugin_base_dir = null, $product_name = null) {// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter use
		return parent::translation_needed(SYLLABPLUS_DIR, 'syllabplus');
	}

	/**
	 * This function will check if we should display the rate notice or not
	 *
	 * @return boolean - to indicate if we should show the notice or not
	 */
	protected function show_rate_notice() {
		global $syllabplus;

		$backup_history = SyllabPlus_Backup_History::get_history();
		
		$backup_dir = $syllabplus->backups_dir_location();
		// N.B. Not an exact proxy for the installed time; they may have tweaked the expert option to move the directory
		$installed = @filemtime($backup_dir.'/index.html');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		$installed_for = time() - $installed;

		if (!empty($backup_history) && $installed && $installed_for > 28*86400) {
			return true;
		}

		return false;
	}
	
	protected function wp_optimize_installed($plugin_base_dir = null, $product_name = null) {// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Filter use
		if (!function_exists('get_plugins')) include_once(ABSPATH.'wp-admin/includes/plugin.php');
		$plugins = get_plugins();

		foreach ($plugins as $value) {
			if ('wp-optimize' == $value['TextDomain']) {
				return false;
			}
		}
		return true;
	}
	
	protected function url_start($html_allowed, $url, $https = false, $website_home = 'syllabplus.com') {
		return parent::url_start($html_allowed, $url, $https, $website_home);
	}

	protected function skip_seasonal_notices($notice_data) {
		global $syllabplus;

		$time_now = defined('SYLLABPLUS_NOTICES_FORCE_TIME') ? SYLLABPLUS_NOTICES_FORCE_TIME : time();
		// Do not show seasonal notices to people with an syllabplus.com version and no-addons yet
		if (!file_exists(SYLLABPLUS_DIR.'/udaddons') || $syllabplus->have_addons) {
			$valid_from = strtotime($notice_data['valid_from']);
			$valid_to = strtotime($notice_data['valid_to']);
			$dismiss = $this->check_notice_dismissed($notice_data['dismiss_time']);
			if (($time_now >= $valid_from && $time_now <= $valid_to) && !$dismiss) {
				// return true so that we return this notice to be displayed
				return true;
			}
		}
		
		return false;
	}
	
	protected function check_notice_dismissed($dismiss_time) {

		$time_now = defined('SYLLABPLUS_NOTICES_FORCE_TIME') ? SYLLABPLUS_NOTICES_FORCE_TIME : time();
	
		$notice_dismiss = ($time_now < SyllabPlus_Options::get_syllab_option('dismissed_general_notices_until', 0));
		$review_dismiss = ($time_now < SyllabPlus_Options::get_syllab_option('dismissed_review_notice', 0));
		$seasonal_dismiss = ($time_now < SyllabPlus_Options::get_syllab_option('dismissed_season_notices_until', 0));
		$autobackup_dismiss = ($time_now < SyllabPlus_Options::get_syllab_option('syllabplus_dismissedautobackup', 0));

		$dismiss = false;

		if ('dismiss_notice' == $dismiss_time) $dismiss = $notice_dismiss;
		if ('dismiss_review_notice' == $dismiss_time) $dismiss = $review_dismiss;
		if ('dismiss_season' == $dismiss_time) $dismiss = $seasonal_dismiss;
		if ('dismissautobackup' == $dismiss_time) $dismiss = $autobackup_dismiss;

		return $dismiss;
	}

	protected function render_specified_notice($advert_information, $return_instead_of_echo = false, $position = 'top') {
	
		if ('bottom' == $position) {
			$template_file = 'bottom-notice.php';
		} elseif ('report' == $position) {
			$template_file = 'report.php';
		} elseif ('report-plain' == $position) {
			$template_file = 'report-plain.php';
		} elseif ('autobackup' == $position) {
			$template_file = 'autobackup-notice.php';
		} else {
			$template_file = 'horizontal-notice.php';
		}
		
		/*
			Check to see if the syllabplus_com_link filter is being used, if it's not then add our tracking to the link.
		*/
	
		if (!has_filter('syllabplus_com_link') && isset($advert_information['button_link']) && false !== strpos($advert_information['button_link'], '//syllabplus.com')) {
			$advert_information['button_link'] = trailingslashit($advert_information['button_link']).'?afref='.$this->self_affiliate_id;
			if (isset($advert_information['campaign'])) $advert_information['button_link'] .= '&utm_source=syllabplus&utm_medium=banner&utm_campaign='.$advert_information['campaign'];
		}

		include_once(SYLLABPLUS_DIR.'/admin.php');
		global $syllabplus_admin;
		return $syllabplus_admin->include_template('wp-admin/notices/'.$template_file, $return_instead_of_echo, $advert_information);
	}
}

$GLOBALS['syllabplus_notices'] = SyllabPlus_Notices::instance();
