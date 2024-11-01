<?php

/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed.');

if (!class_exists('SyllabPlus_Login')) require_once('syllabplus-login.php');

class SyllabPlus_SyllabCentral_Cloud extends SyllabPlus_Login {

	/**
	 * Pulls the appropriate message for the given code and translate it before
	 * returning it to the caller
	 *
	 * @internal
	 * @param string $code The code of the message to pull
	 * @return string - The translated message
	 */
	protected function translate_message($code) {
		switch ($code) {
			case 'generic':
			default:
				return __('An error has occurred while processing your request. The server might be busy or you have lost your connection to the internet at the time of the request. Please try again later.', 'syllabplus');
				break;
		}
	}

	/**
	 * Executes login or registration process. Connects and sends request to the SyllabCentral Cloud
	 * and returns the response coming from the server
	 *
	 * @internal
	 * @param array   $data     The submitted form data
	 * @param boolean $register Indicates whether the current call is for a registration process or not. Defaults to false.
	 * @return array - The response from the request
	 */
	protected function login_or_register($data, $register = false) {
		global $syllabplus, $syllabcentral_main;

		$action = ($register) ? 'syllabcentral_cloud_register' : 'syllabcentral_cloud_login';
		if (empty($data['site_url'])) $data['site_url'] = trailingslashit(network_site_url());

		$response = $this->send_remote_request($data, $action);
		if (is_wp_error($response)) {
			$response = array('error' => true, 'code' => $response->get_error_code(), 'message' => $response->get_error_message());
		} else {
			if (isset($response['status'])) {
				if (in_array($response['status'], array('authenticated', 'registered'))) {
					$response['redirect_url'] = $syllabplus->get_url('mothership').'/?udm_action=syllabcentral_cloud_redirect';

					if (is_a($syllabcentral_main, 'SyllabCentral_Main')) {
						$response['keys_table'] = $syllabcentral_main->get_keys_table();
					}

					if (!empty($data['addons_options_connect']) && class_exists('SyllabPlus_Options')) {
						SyllabPlus_Options::update_syllab_option('syllabplus_com_and_udc_connection_success', 1, false);
					}

				} else {
					if ('error' === $response['status']) {
						$response = array(
							'error' => true,
							'code' => isset($response['code']) ? $response['code'] : -1,
							'message' => isset($response['message']) ? $response['message'] : $this->translate_message('generic'),
							'response' => $response
						);
					}
				}
			} else {
				$response = array('error' => true, 'message' => $this->translate_message('generic'));
			}
		}

		return $response;
	}
}
