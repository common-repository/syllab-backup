<?php

/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed.');

abstract class SyllabPlus_Login {
	
	/**
	 * Pulls the appropriate message for the given code and translate it before
	 * returning it to the caller
	 *
	 * @internal
	 * @param string $code The code of the message to pull
	 * @return string - The translated message
	 */
	abstract protected function translate_message($code);

	/**
	 * Executes login or registration process. Connects and sends request to the SyllabCentral Cloud
	 * and returns the response coming from the server
	 *
	 * @internal
	 * @param array   $data     The submitted form data
	 * @param boolean $register Indicates whether the current call is for a registration process or not. Defaults to false.
	 * @return array - The response from the request
	 */
	abstract protected function login_or_register($data, $register = false);

	/**
	 * Handles the actual sending of the request to the remote server (SyllabCentral Cloud) and pre-checks the response
	 * and wraps it up for the caller before sending it back
	 *
	 * @internal
	 * @param array  $data   The submitted form data
	 * @param string $action The name of the action or service that will be triggered in SyllabCentral Cloud
	 * @return array - The response from the server
	 */
	protected function send_remote_request($data, $action) {
		global $syllabplus;
		$result = wp_remote_post($syllabplus->get_url('mothership').'/?udm_action='.$action,
			array(
				'timeout' => 20,
				'headers' => apply_filters('syllabplus_auth_headers', ''),
				'body' => $data
			)
		);

		// If we got an error then we return the WP_Error object itself
		// and let the caller handle it.
		if (is_wp_error($result)) return $result;

		$response = json_decode(wp_remote_retrieve_body($result), true);
		if (!is_array($response) || !isset($response['mothership']) || !isset($response['status'])) {

			if (preg_match('/has banned your IP address \(([\.:0-9a-f]+)\)/', $result['body'], $matches)) {
				return new WP_Error('banned_ip', sprintf(__("SyllabPlus.com has responded with 'Access Denied'.", 'syllabplus').'<br>'.__("It appears that your web server's IP Address (%s) is blocked.", 'syllabplus').' '.__('This most likely means that you share a webserver with a hacked website that has been used in previous attacks.', 'syllabplus').'<br> <a href="'.apply_filters("syllabplus_com_link", "https://syllabplus.com/unblock-ip-address/").'" target="_blank">'.__('To remove the block, please go here.', 'syllabplus').'</a> ', $matches[1]));
			} else {
				return new WP_Error('unknown_response', sprintf(__('SyllabPlus.Com returned a response which we could not understand (data: %s)', 'syllabplus'), wp_remote_retrieve_body($result)));
			}
		}

		return $response;
	}

	/**
	 * The ajax based request point of entry for the login process
	 *
	 * @param array   $data         - The submitted form data
	 * @param boolean $echo_results - Whether to echo/display the results directly to the user or assign it to a variable
	 * @return array - Response of the process
	 */
	public function ajax_process_login($data = array(), $echo_results = true) {
		try {
			if (isset($data['form_data'])) {
				if (is_string($data['form_data'])) {
					parse_str($data['form_data'], $form_data);
				} elseif (is_array($data['form_data'])) {
					$form_data = $data['form_data'];
				}
			}
			$response = $this->login_or_register($form_data);
		} catch (Exception $e) {
			$response = array('error' => true, 'message' => $e->getMessage());
		}

		if ($echo_results) {
			echo json_encode($response);
		} else {
			return $response;
		}
	}

	/**
	 * The ajax based request point of entry for the registration process
	 *
	 * @param array   $data         - The submitted form data
	 * @param boolean $echo_results - Whether to echo/display the results directly to the user or assign it to a variable
	 * @return array - Response of the process
	 */
	public function ajax_process_registration($data = array(), $echo_results = true) {
		try {
			if (isset($data['form_data'])) parse_str($data['form_data'], $form_data);

			$response = $this->login_or_register($form_data, true);
		} catch (Exception $e) {
			$response = array('error' => true, 'message' => $e->getMessage());
		}

		if ($echo_results) {
			echo json_encode($response);
		} else {
			return $response;
		}
	}
}
