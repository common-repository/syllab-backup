<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('ABSPATH')) die('No direct access allowed');
/**
 * Class SyllabPlus_Tour
 *
 * Adds the guided tour when activating the plugin for the first time.
 */
class SyllabPlus_Tour {

	/**
	 * The class instance
	 *
	 * @var object
	 */
	protected static $instance;

	/**
	 * Get the instance
	 *
	 * @return object
	 */
	public static function get_instance() {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * __construct
	 */
	private function __construct() {
	}
	
	/**
	 * Sets up the notices, security and loads assets for the admin page
	 */
	public function init() {
		// Add plugin action link
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);

		// only init and load assets if the tour hasn't been canceled
		if (isset($_REQUEST['syllabplus_tour']) && 0 === (int) $_REQUEST['syllabplus_tour']) {
			$this->set_tour_status(array('current_step' => 'start'));
			return;
		}
		
		// if backups already exist and
		if ($this->syllabplus_was_already_installed() && !isset($_REQUEST['syllabplus_tour'])) {
			return;
		}

		// if 'Take tour' link was used, reset tour
		if (isset($_REQUEST['syllabplus_tour']) && 1 === (int) $_REQUEST['syllabplus_tour']) {
			$this->reset_tour_status();
		}

		if (!SyllabPlus_Options::get_syllab_option('syllabplus_tour_cancelled_on')) {
			add_action('admin_enqueue_scripts', array($this, 'load_tour'));
		}
	}

	/**
	 * Loads in tour assets
	 *
	 * @param string $hook - current page
	 */
	public function load_tour($hook) {
		
		$pages = array('settings_page_syllabplus', 'plugins.php');

		if (!in_array($hook, $pages)) return;
		if (!SyllabPlus_Options::user_can_manage()) return;

		global $syllabplus, $syllabplus_addons2, $syllabplus_checkout_embed;

		$checkout_embed_5gb_attribute = '';
		if ($syllabplus_checkout_embed) {
			$checkout_embed_5gb_attribute = $syllabplus_checkout_embed->get_product('syllabplus-vault-storage-5-gb') ? 'data-embed-checkout="'.apply_filters('syllabplus_com_link', $syllabplus_checkout_embed->get_product('syllabplus-vault-storage-5-gb', SyllabPlus_Options::admin_page_url().'?page=syllabplus&tab=settings')).'"' : '';
		}

		$script_suffix = $syllabplus->use_unminified_scripts() ? '' : '.min';
		$syllab_min_or_not = $syllabplus->get_syllabplus_file_version();
		wp_enqueue_script('syllabplus-tether-js', trailingslashit(SYLLABPLUS_URL).'includes/tether/tether'.$script_suffix.'.js', $syllabplus->version, true);
		wp_enqueue_script('syllabplus-shepherd-js', trailingslashit(SYLLABPLUS_URL).'includes/tether-shepherd/shepherd'.$script_suffix.'.js', array('syllabplus-tether-js'), $syllabplus->version, true);
		wp_enqueue_style('syllabplus-shepherd-css', trailingslashit(SYLLABPLUS_URL).'css/tether-shepherd/shepherd-theme-arrows-plain-buttons'.$script_suffix.'.css', false, $syllabplus->version);
		wp_enqueue_style('syllabplus-tour-css', trailingslashit(SYLLABPLUS_URL).'css/syllabplus-tour'.$syllab_min_or_not.'.css', false, $syllabplus->version);
		wp_register_script('syllabplus-tour-js', trailingslashit(SYLLABPLUS_URL).'js/tour.js', array('syllabplus-tether-js'), $syllabplus->version, true);
		
		$tour_data = array(
			'nonce' => wp_create_nonce('syllabplus-credentialtest-nonce'),
			'show_tab_on_load' => '#syllab-navtab-status',
			'next' => __('Next', 'syllabplus'),
			'back' => __('Back', 'syllabplus'),
			'skip' => __('Skip this step', 'syllabplus'),
			'end_tour' => __('End tour', 'syllabplus'),
			'close' => __('Close', 'syllabplus'),
			'plugins_page' => array(
				'title' => __("SyllabPlus settings", 'syllabplus'),
				'text' => '<div class="syllabplus-welcome-logo"><img src="'.trailingslashit(SYLLABPLUS_URL).'images/ud-logo.png" alt="" /></div><strong>'.__('Welcome to SyllabPlus', 'syllabplus').'</strong>, '.__("the world’s most trusted backup plugin!", 'syllabplus'),
				'button' => array(
					'url' => SyllabPlus_Options::admin_page_url().'?page=syllabplus',
					'text' => __('Press here to start!', 'syllabplus')
				)
			),
			'backup_now' => array(
				'title' => __('Your first backup', 'syllabplus'),
				'text' => sprintf(_x('To make a simple backup to your server, press this button. Or to setup regular backups and remote storage, go to %s settings %s', 'syllabplus'), '<strong><a href="#settings" class="js--go-to-settings">', '</a></strong>')
			),
			'backup_options' => array(
				'title' => __("Manual backup options", 'syllabplus'),
				'text' => __('Select what you want to backup', 'syllabplus')
			),
			'backup_now_btn' => array(
				'title' => __("Creating your first backup", 'syllabplus'),
				'text' => __("Press here to run a manual backup.", 'syllabplus').'<br>'.sprintf(_x("But to avoid server-wide threats backup regularly to remote cloud storage in %s settings %s", 'Translators: %s is a bold tag.', 'syllabplus'), '<strong><a href="#settings" class="js--go-to-settings">', '</a></strong>'),
				'btn_text' => __('Go to settings', 'syllabplus')
			),
			'backup_now_btn_success' => array(
				'title' => __('Creating your first backup', 'syllabplus'),
				'text' => __('Congratulations! Your first backup is running.', 'syllabplus').'<br>'.sprintf(_x('But to avoid server-wide threats backup regularly to remote cloud storage in %s settings %s', 'Translators: %s is a bold tag.', 'syllabplus'), '<strong>', '</strong>'),
				'btn_text' => __('Go to settings', 'syllabplus')
			),
			'settings_timing' => array(
				'title' => __("Choose your backup schedule", 'syllabplus'),
				'text' => __("Choose the schedule that you want your backups to run on.", 'syllabplus')
			),
			'settings_remote_storage' => array(
				'title' => __("Remote storage", 'syllabplus'),
				'text' => __("Now select a remote storage destination to protect against server-wide threats. If not, your backups remain on the same server as your site.", 'syllabplus')
					.'<div class="ud-notice">'
					.'<h3>'.__('Try SyllabVault!').'</h3>'
					.__("SyllabVault is our remote storage which works seamlessly with SyllabPlus.", 'syllabplus')
					.' <a href="'.apply_filters('syllabplus_com_link', 'https://syllabplus.com/syllabvault/').'" target="_blank">'.__('Find out more here.', 'syllabplus').'</a>'
					.'<p><a href="'.apply_filters('syllabplus_com_link', $syllabplus->get_url('shop_vault_5')).'" target="_blank" '.$checkout_embed_5gb_attribute.' class="button button-primary">'.__('Try SyllabVault for 1 month for only $1!', 'syllabplus').'</a></p>'
					.'</div>'
			),
			'settings_more' => array(
				'title' => __("More settings", 'syllabplus'),
				'text' => __("Look through the other settings here, making any changes you’d like.", 'syllabplus')
			),
			'settings_save' => array(
				'title' => __("Save", 'syllabplus'),
				'text' => __('Press here to save your settings.', 'syllabplus')
			),
			'settings_saved' => array(
				'title' => __("Save", 'syllabplus'),
				'text' => __('Congratulations, your settings have successfully been saved.', 'syllabplus')
			),
			'syllab_central' => array(
				'title' => __("SyllabCentral", 'syllabplus'),
				'text' => '<div class="ud-notice">'
					.'<h3>'.__('Control all your backups in one place', 'syllabplus').'</h3>'
					.__('Do you have a few more WordPress sites you want to backup? If yes you can save hours by controlling all your backups in one place from SyllabCentral.', 'syllabplus')
					.'</div>'
			),
			'premium' => array(
				'title' => 'SyllabPlus Premium',
				'text' => __('Thank you for taking the tour.', 'syllabplus')
					.'<div class="ud-notice">'
					.'<h3>'.__('SyllabPlus Premium and addons', 'syllabplus').'</h3>'
					.__('SyllabPlus Premium has many more exciting features!', 'syllabplus').' <a href="'.apply_filters('syllabplus_com_link', 'https://syllabplus.com/shop/syllabplus-premium/').'" target="_blank">'.__('Find out more here.', 'syllabplus').'</a>'
					.'</div>',
				'attach_to' => '#syllab-navtab-addons top',
				'button' => __('Finish', 'syllabplus')
			),
			'vault_selected' => array(
				'title' => 'SyllabVault',
				'text' => _x('To get started with SyllabVault, select one of the options below:', 'Translators: SyllabVault is a product name and should not be translated.', 'syllabplus')
			)
		);

		if (isset($_REQUEST['tab'])) {
			$tour_data['show_tab_on_load'] = '#syllab-navtab-'.esc_attr($_REQUEST['tab']);
		}

		// Change the data for premium users
		if ($syllabplus_addons2 && method_exists($syllabplus_addons2, 'connection_status')) {

			$tour_data['settings_remote_storage'] = array(
				'title' => __("Remote storage", 'syllabplus'),
				'text' => __("Now select a remote storage destination to protect against server-wide threats. If not, your backups remain on the same server as your site.", 'syllabplus')
					.'<div class="ud-notice">'
					.'<h3>'.__('Try Syllab Vault!').'</h3>'
					.__("Syllab Vault is our remote storage which works seamlessly with SylLab Backup.", 'syllabplus')
					.' <a href="'.apply_filters('syllabplus_com_link', 'https://syllabplus.com/syllabvault/').'" target="_blank">'.__('Find out more here.', 'syllabplus').'</a>'
					.'<br>'
					.__("If you have a valid Premium license, you get 1GB of storage included.", 'syllabplus')
					.' <a href="javascript:void();" target="_blank" >'.__('Otherwise, Try SylLab Vault for only 6 dollars a month!', 'syllabplus').'</a>'
					.'</div>'
			);

			if ($syllabplus_addons2->connection_status() && !is_wp_error($syllabplus_addons2->connection_status())) {
				$tour_data['premium'] = array(
					'title' => 'SyllabPlus Premium',
					'text' => __('Thank you for taking the tour. You are now all set to use SyllabPlus!', 'syllabplus'),
					'attach_to' => '#syllab-navtab-addons top',
					'button' => __('Finish', 'syllabplus')
				);
			} else {
				$tour_data['premium'] = array(
					'title' => 'SyllabPlus Premium',
					'text' => __('Thank you for taking the tour.', 'syllabplus')
						.'<div class="ud-notice">'
						.'<h3>'.__('Connect to syllabplus.com', 'syllabplus').'</h3>'
						.__('Log in here to enable all the features you have access to.', 'syllabplus')
						.'</div>',
					'attach_to' => '#syllabplus-addons_options_email right',
					'button' => __('Finish', 'syllabplus')
				);
			}
		}

		wp_localize_script('syllabplus-tour-js', 'syllabplus_tour_i18n', $tour_data);
		wp_enqueue_script('syllabplus-tour-js');
	}

	/**
	 * Removes the tour status so the tour can be seen again
	 *
	 * @return string|WP_Error not visible by the user
	 */
	public function reset_tour_status() {

		// If the option isn't set, the tour hasn't been cancelled
		if (!SyllabPlus_Options::get_syllab_option('syllabplus_tour_cancelled_on')) {
			// string not visible by the user
			return 'The tour is still active. Everything should be ok.';
		}

		$result = SyllabPlus_Options::delete_syllab_option('syllabplus_tour_cancelled_on');
		// strings not visible by the user
		return $result ? 'The tour status was successfully reset' : new WP_Error('update_failed', 'The attempt to update the tour option failed.', array('status' => 409));
	}

	/**
	 * Updates the stored value for which step the tour ended on
	 *
	 * @param object $request - the http $_REQUEST obj
	 * @return bool
	 */
	public function set_tour_status($request) {
		if (!isset($request['current_step'])) return false;
		return SyllabPlus_Options::update_syllab_option('syllabplus_tour_cancelled_on', $request['current_step']);
	}

	/**
	 * Adds the Tour link under the plugin on the plugin screen.
	 *
	 * @param  Array  $links Set of links for the plugin, before being filtered
	 * @param  String $file  File name (relative to the plugin directory)
	 * @return Array filtered results
	 */
	public function plugin_action_links($links, $file) {
		if (is_array($links) && 'syllabplus/syllabplus.php' === $file) {
			$links['syllabplus_tour'] = '<a href="'.SyllabPlus_Options::admin_page_url().'?page=syllabplus&syllabplus_tour=1" class="js-syllabplus-tour">'.__("Take Tour", "syllabplus").'</a>';
		}
		return $links;
	}

	/**
	 * Checks if SLP was newly installed.
	 *
	 * Checks if there are backups, and if there are more than 1,
	 * checks if the folder is older than 1 day old
	 *
	 * @return bool
	 */
	public function syllabplus_was_already_installed() {
		// If backups already exist
		$backup_history = SyllabPlus_Backup_History::get_history();

		// No backup history
		if (!$backup_history) return false;
		if (is_array($backup_history) && 0 === count($backup_history)) {
			return false;
		}
		// If there is at least 1 backup, we check if the folder is older than 1 day old
		if (0 < count($backup_history)) {
			$backups_timestamps = array_keys($backup_history);
			$last_backlup_age = time() - end($backups_timestamps);
			if (DAY_IN_SECONDS < $last_backlup_age) {
				// the oldest backup is older than 1 day old, so it's likely that SLP was already installed, and the backups aren't a product of the user testing while doing the tour.
				return true;
			}
		}
		return false;
	}
}

add_action('admin_init', array(SyllabPlus_Tour::get_instance(), 'init'));
