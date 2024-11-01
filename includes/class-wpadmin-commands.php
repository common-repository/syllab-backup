<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No access.');

/*
	See class-commands.php for explanation about how these classes work.
*/

if (!class_exists('SyllabPlus_Commands')) require_once(SYLLABPLUS_DIR.'/includes/class-commands.php');

/**
 * An extension, because commands available via wp-admin are a super-set of those which are available through all mechanisms
 */
class SyllabPlus_WPAdmin_Commands extends SyllabPlus_Commands {

	private $_uc_helper;

	private $_syllabplus_admin;

	private $_syllabplus;

	/**
	 * Constructor
	 *
	 * @param string $uc_helper The 'helper' needs to provide the method _syllabplus_background_operation_started
	 */
	public function __construct($uc_helper) {
		$this->_uc_helper = $uc_helper;
		global $syllabplus_admin, $syllabplus;
		$this->_syllabplus_admin = $syllabplus_admin;
		$this->_syllabplus = $syllabplus;
		parent::__construct($uc_helper);
	}
	
	/**
	 * Forces a resumption of a backup where the resumption is overdue (so apparently cron is not working)
	 *
	 * @param Array $info - keys 'job_id' and 'resumption'
	 *
	 * @return Array - if there is an error. Otherwise, dies.
	 */
	public function forcescheduledresumption($info) {
	
		// Casting $resumption to int is absolutely necessary, as the WP cron system uses a hashed serialisation of the parameters for identifying jobs. Different type => different hash => does not match
		$resumption = (int) $info['resumption'];
		$job_id = $info['job_id'];
		$get_cron = $this->_syllabplus_admin->get_cron($job_id);
		if (!is_array($get_cron)) {
			return array('r' => false);
		} else {
			$this->_syllabplus->log("Forcing resumption: job id=$job_id, resumption=$resumption");
			wp_clear_scheduled_hook('syllab_backup_resume', array($resumption, $job_id));
			$this->_syllabplus->close_browser_connection(json_encode(array('r' => true)));
			$this->_syllabplus->jobdata_set_from_array($get_cron[1]);
			$this->_syllabplus->backup_resume($resumption, $job_id);
			// We don't want to return. The close_browser_connection call already returned a result.
			die;
		}
	}
	
	/**
	 * Calls a WordPress action and dies
	 *
	 * @param Array $data - must have at least the key 'wpaction' with a string value
	 *
	 * @return WP_Error if no command was included
	 */
	public function call_wordpress_action($data) {

		if (empty($data['wpaction'])) return new WP_Error('error', '', 'no command sent');
		
		$response = $this->_syllabplus_admin->call_wp_action($data, array($this->_uc_helper, '_syllabplus_background_operation_started'));// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		die;

		// return array('response' => $response['response'], 'status' => $response['status'], 'log' => $response['log'] );
	}
	
	/**
	 * Function to retrieve raw backup history given a timestamp and nonce
	 *
	 * @param Array $data - Data parameter; keys: timestamp, nonce
	 *
	 * @return String if empty result will be empty string
	 */
	public function rawbackup_history($data) {

		$history = SyllabPlus_Backup_History::get_history();

		return $this->_syllabplus_admin->raw_backup_info($history, $data['timestamp'], $data['nonce'], null);
	}
	
	public function syllabcentral_delete_key($params) {
		global $syllabcentral_main;
		if (!is_a($syllabcentral_main, 'SyllabCentral_Main')) {
			return array('error' => 'SyllabCentral_Main object not found');
		}
		
		return $syllabcentral_main->delete_key($params['key_id']);
	}
	
	public function syllabcentral_get_log($params) {
		global $syllabcentral_main;
		if (!is_a($syllabcentral_main, 'SyllabCentral_Main')) {
			return array('error' => 'SyllabCentral_Main object not found');
		}
		return call_user_func(array($syllabcentral_main, 'get_log'), $params);
	}

	 public function syllabcentral_create_key($params) {
		global $syllabcentral_main;
		if (!is_a($syllabcentral_main, 'SyllabCentral_Main')) {
			return array('error' => 'SyllabCentral_Main object not found');
		}
		return call_user_func(array($syllabcentral_main, 'create_key'), $params);
	 }
		
	public function restore_alldownloaded($params) {

		$backups = SyllabPlus_Backup_History::get_history();
		$syllab_dir = $this->_syllabplus->backups_dir_location();

		$timestamp = (int) $params['timestamp'];
		if (!isset($backups[$timestamp])) {
			return array('m' => '', 'w' => '', 'e' => __('No such backup set exists', 'syllabplus'));
		}

		$mess = array();
		parse_str(stripslashes($params['restoreopts']), $res);

		if (isset($res['syllab_restore'])) {

			set_error_handler(array($this->_syllabplus_admin, 'get_php_errors'), E_ALL & ~E_STRICT);

			$elements = array_flip($res['syllab_restore']);

			$warn = array();
			$err = array();

			if (function_exists('set_time_limit')) @set_time_limit(SYLLABPLUS_SET_TIME_LIMIT);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$max_execution_time = (int) @ini_get('max_execution_time');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

			if ($max_execution_time>0 && $max_execution_time<61) {
				$warn[] = sprintf(__('The PHP setup on this webserver allows only %s seconds for PHP to run, and does not allow this limit to be raised. If you have a lot of data to import, and if the restore operation times out, then you will need to ask your web hosting company for ways to raise this limit (or attempt the restoration piece-by-piece).', 'syllabplus'), $max_execution_time);
			}

			if (isset($backups[$timestamp]['native']) && false == $backups[$timestamp]['native']) {
				$warn[] = __('This backup set was not known by SyllabPlus to be created by the current WordPress installation, but was either found in remote storage, or was sent from a remote site.', 'syllabplus').' '.__('You should make sure that this really is a backup set intended for use on this website, before you restore (rather than a backup set of an unrelated website).', 'syllabplus');
			}

			if (isset($elements['db'])) {
				
				// Analyse the header of the database file + display results
				list ($mess2, $warn2, $err2, $info) = $this->_syllabplus->analyse_db_file($timestamp, $res);
				$mess = array_merge($mess, $mess2);
				$warn = array_merge($warn, $warn2);
				$err = array_merge($err, $err2);
				foreach ($backups[$timestamp] as $bid => $bval) {
					if ('db' != $bid && 'db' == substr($bid, 0, 2) && '-size' != substr($bid, -5, 5)) {
						$warn[] = __('Only the WordPress database can be restored; you will need to deal with the external database manually.', 'syllabplus');
						break;
					}
				}
			}

			$backupable_entities = $this->_syllabplus->get_backupable_file_entities(true, true);
			$backupable_plus_db = $backupable_entities;
			$backupable_plus_db['db'] = array('path' => 'path-unused', 'description' => __('Database', 'syllabplus'));

			if (!empty($backups[$timestamp]['meta_foreign'])) {
				$foreign_known = apply_filters('syllabplus_accept_archivename', array());
				if (!is_array($foreign_known) || empty($foreign_known[$backups[$timestamp]['meta_foreign']])) {
					$err[] = sprintf(__('Backup created by unknown source (%s) - cannot be restored.', 'syllabplus'), $backups[$timestamp]['meta_foreign']);
				} else {
					// For some reason, on PHP 5.5 passing by reference in a single array stopped working with apply_filters_ref_array (though not with do_action_ref_array).
					$backupable_plus_db = apply_filters_ref_array("syllabplus_importforeign_backupable_plus_db", array($backupable_plus_db, array($foreign_known[$backups[$timestamp]['meta_foreign']], &$mess, &$warn, &$err)));
				}
			}

			foreach ($backupable_plus_db as $type => $entity_info) {
				if (!isset($elements[$type]) || (isset($entity_info['restorable']) && !$entity_info['restorable'])) continue;
				$whatwegot = $backups[$timestamp][$type];
				if (is_string($whatwegot)) $whatwegot = array($whatwegot);
				$expected_index = 0;
				$missing = '';
				ksort($whatwegot);
				$outof = false;
				foreach ($whatwegot as $index => $file) {
					if (preg_match('/\d+of(\d+)\.zip/', $file, $omatch)) {
						$outof = max($omatch[1], 1);
					}
					while ($expected_index < $index) {
						$missing .= ('' == $missing) ? (1+$expected_index) : ",".(1+$expected_index);
						$expected_index++;
					}
					if (!file_exists($syllab_dir.'/'.$file)) {
						$err[] = sprintf(__('File not found (you need to upload it): %s', 'syllabplus'), $syllab_dir.'/'.$file);
					} elseif (filesize($syllab_dir.'/'.$file) == 0) {
						$err[] = sprintf(__('File was found, but is zero-sized (you need to re-upload it): %s', 'syllabplus'), $file);
					} else {
						$itext = (0 == $index) ? '' : $index;
						if (!empty($backups[$timestamp][$type.$itext.'-size']) && filesize($syllab_dir.'/'.$file) != $backups[$timestamp][$type.$itext.'-size']) {
							if (empty($warn['doublecompressfixed'])) {
								$warn[] = sprintf(__('File (%s) was found, but has a different size (%s) from what was expected (%s) - it may be corrupt.', 'syllabplus'), $file, filesize($syllab_dir.'/'.$file), $backups[$timestamp][$type.$itext.'-size']);
							}
						}
						do_action_ref_array("syllabplus_checkzip_$type", array($syllab_dir.'/'.$file, &$mess, &$warn, &$err));
					}
					$expected_index++;
				}
				do_action_ref_array("syllabplus_checkzip_end_$type", array(&$mess, &$warn, &$err));
				// Detect missing archives where they are missing from the end of the set
				if ($outof>0 && $expected_index < $outof) {
					for ($j = $expected_index; $j<$outof; $j++) {
						$missing .= ('' == $missing) ? (1+$j) : ",".(1+$j);
					}
				}
				if ('' != $missing) {
					$warn[] = sprintf(__("This multi-archive backup set appears to have the following archives missing: %s", 'syllabplus'), $missing.' ('.$entity_info['description'].')');
				}
			}

			// Check this backup set has a incremental_sets array e.g may have been created before this array was introduced
			if (isset($backups[$timestamp]['incremental_sets'])) {
				$incremental_sets = array_keys($backups[$timestamp]['incremental_sets']);
				// Check if there are more than one timestamp in the incremental set
				if (1 < count($incremental_sets)) {
					$incremental_select_html = '<div class="notice syllab-restore-option"><label>'.__('This backup set contains incremental backups of your files; please select the time you wish to restore your files to', 'syllabplus').': </label>';
					$incremental_select_html .= '<select name="syllab_incremental_restore_point" id="syllab_incremental_restore_point">';
					$incremental_sets = array_reverse($incremental_sets);
					$first_timestamp = $incremental_sets[0];
					
					foreach ($incremental_sets as $timestamp) {
						$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $timestamp), 'M d, Y G:i');
						$esc_pretty_date = esc_attr($pretty_date);
						$incremental_select_html .= '<option value="'.$timestamp.'" '.selected($timestamp, $first_timestamp, false).'>'.$esc_pretty_date.'</option>';
					}

					$incremental_select_html .= '</select>';
					$incremental_select_html .= '</div>';
					$info['addui'] = empty($info['addui']) ? $incremental_select_html : $info['addui'].'<br>'.$incremental_select_html;
				}
			}

			if (0 == count($err) && 0 == count($warn)) {
				$mess_first = __('The backup archive files have been successfully processed. Now press Restore to proceed.', 'syllabplus');
			} elseif (0 == count($err)) {
				$mess_first = __('The backup archive files have been processed, but with some warnings. If all is well, then press Restore to proceed. Otherwise, cancel and correct any problems first.', 'syllabplus');
			} else {
				$mess_first = __('The backup archive files have been processed, but with some errors. You will need to cancel and correct any problems before retrying.', 'syllabplus');
			}

			if (count($this->_syllabplus_admin->logged) >0) {
				foreach ($this->_syllabplus_admin->logged as $lwarn) $warn[] = $lwarn;
			}
			restore_error_handler();

			// Get the info if it hasn't already come from the DB scan
			if (!isset($info) || !is_array($info)) $info = array();

			// Not all characters can be json-encoded, and we don't need this potentially-arbitrary user-supplied info.
			unset($info['label']);

			if (!isset($info['created_by_version']) && !empty($backups[$timestamp]['created_by_version'])) $info['created_by_version'] = $backups[$timestamp]['created_by_version'];

			if (!isset($info['multisite']) && !empty($backups[$timestamp]['is_multisite'])) $info['multisite'] = $backups[$timestamp]['is_multisite'];
			
			do_action_ref_array('syllabplus_restore_all_downloaded_postscan', array($backups, $timestamp, $elements, &$info, &$mess, &$warn, &$err));

			if (0 == count($err) && 0 == count($warn)) {
				$mess_first = __('The backup archive files have been successfully processed. Now press Restore again to proceed.', 'syllabplus');
			} elseif (0 == count($err)) {
				$mess_first = __('The backup archive files have been processed, but with some warnings. If all is well, then now press Restore again to proceed. Otherwise, cancel and correct any problems first.', 'syllabplus');
			} else {
				$mess_first = __('The backup archive files have been processed, but with some errors. You will need to cancel and correct any problems before retrying.', 'syllabplus');
			}

			$warn_result = '';
			foreach ($warn as $warning) {
				if (!$warn_result) $warn_result = '<ul id="syllab_restore_warnings">';
				$warn_result .= '<li>'.$warning.'</li>';
			}
			if ($warn_result) $warn_result .= '</ul>';
			
			return array('m' => '<p>'.$mess_first.'</p>'.implode('<br>', $mess), 'w' => $warn_result, 'e' => implode('<br>', $err), 'i' => json_encode($info));
		}
	
	}
	
	/**
	 * The purpose of this is to detect brokenness caused by extra line feeds in plugins/themes - before it breaks other AJAX operations and leads to support requests
	 *
	 * @return string
	 */
	public function ping() {
		return 'pong';
	}
	
	/**
	 * This function is called via ajax and will update the autobackup notice dismiss time
	 *
	 * @return array - an empty array
	 */
	public function dismissautobackup() {
		SyllabPlus_Options::update_syllab_option('syllabplus_dismissedautobackup', time() + 84*86400);
		return array();
	}

	/**
	 * This function is called via ajax and will update the general notice dismiss time
	 *
	 * @return array - an empty array
	 */
	public function dismiss_notice() {
		SyllabPlus_Options::update_syllab_option('dismissed_general_notices_until', time() + 84*86400);
		return array();
	}

	/**
	 * This function is called via ajax and will update the review notice dismiss time
	 *
	 * @param array $data - an array that contains the dismiss notice for time
	 *
	 * @return array - an empty array
	 */
	public function dismiss_review_notice($data) {
		if (empty($data['dismiss_forever'])) {
			SyllabPlus_Options::update_syllab_option('dismissed_review_notice', time() + 84*86400);
		} else {
			SyllabPlus_Options::update_syllab_option('dismissed_review_notice', 100 * (365.25 * 86400));
		}
		return array();
	}

	/**
	 * This function is called via ajax and will update the season notice dismiss time
	 *
	 * @return array - an empty array
	 */
	public function dismiss_season() {
		SyllabPlus_Options::update_syllab_option('dismissed_season_notices_until', time() + 366*86400);
		return array();
	}

	/**
	 * This function is called via ajax and will update the clone php notice dismiss time
	 *
	 * @return array - an empty array
	 */
	public function dismiss_clone_php_notice() {
		SyllabPlus_Options::update_syllab_option('dismissed_clone_php_notices_until', time() + 180 * 86400);
		return array();
	}

	/**
	 * This function is called via ajax and will update the WooCommerce clone notice dismiss time
	 *
	 * @return array - an empty array
	 */
	public function dismiss_clone_wc_notice() {
		SyllabPlus_Options::update_syllab_option('dismissed_clone_wc_notices_until', time() + 90 * 86400);
		return array();
	}
	
	public function set_autobackup_default($params) {
		$default = empty($params['default']) ? 0 : 1;
		SyllabPlus_Options::update_syllab_option('syllab_autobackup_default', $default);
		return array();
	}
	
	public function dismissexpiry() {
		SyllabPlus_Options::update_syllab_option('syllabplus_dismissedexpiry', time() + 14*86400);
		return array();
	}
	
	public function dismissdashnotice() {
		SyllabPlus_Options::update_syllab_option('syllabplus_dismisseddashnotice', time() + 366*86400);
		return array();
	}
	
	public function rawbackuphistory() {
		// This is used for iframe source; hence, returns a string
		$show_raw_data = $this->_syllabplus_admin->show_raw_backups();
		return $show_raw_data['html'];
	}
	
	/**
	 * N.B. Not exactly the same as the phpinfo method in the SyllabCentral core class
	 * Returns a string, as it is directly fetched as the source of an iframe
	 *
	 * @return String - returns the resulting HTML
	 */
	public function phpinfo() {
	
		ob_start();
	
		if (function_exists('phpinfo')) phpinfo(INFO_ALL ^ (INFO_CREDITS | INFO_LICENSE));

		echo '<h3 id="ud-debuginfo-constants">'.__('Constants', 'syllabplus').'</h3>';
		$opts = @get_defined_constants();// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		ksort($opts);
		echo '<table><thead></thead><tbody>';
		foreach ($opts as $key => $opt) {
			// Administrators can already read these in other ways, but we err on the side of caution
			if (false !== stripos($opt, 'api_key')) $opt = '***';
			echo '<tr><td>'.esc_html($key).'</td><td>'.esc_html(print_r($opt, true)).'</td>';
		}
		echo '</tbody></table>';
		
		$ret = ob_get_contents();
		ob_end_clean();

		return $ret;

	}
	
	/**
	 * Return messages if there are more than 4 overdue cron jobs
	 *
	 * @return Array - the messages are stored in an associative array and are indexed with key 'm'
	 */
	public function check_overdue_crons() {
		$messages = array();
		$how_many_overdue = $this->_syllabplus_admin->howmany_overdue_crons();
		if ($how_many_overdue >= 4) {
			$messages['m'] = array();
			$messages['m'][] = $this->_syllabplus_admin->show_admin_warning_overdue_crons($how_many_overdue);
			if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && (!defined('SYLLABPLUS_DISABLE_WP_CRON_NOTICE') || !SYLLABPLUS_DISABLE_WP_CRON_NOTICE)) $messages['m'][] = $this->_syllabplus_admin->show_admin_warning_disabledcron();
		}
		return $messages;
	}
	
	public function whichdownloadsneeded($params) {
		// The purpose of this is to look at the list of indicated downloads, and indicate which are not already fully downloaded. i.e. Which need further action.
		$send_back = array();
		$backup = SyllabPlus_Backup_History::get_history($params['timestamp']);
		$syllab_dir = $this->_syllabplus->backups_dir_location();
		$backupable_entities = $this->_syllabplus->get_backupable_file_entities();

		if (empty($backup)) return array('result' => 'asyouwere');

		if (isset($params['syllabplus_clone']) && empty($params['downloads'])) {
			$entities = array('db', 'plugins', 'themes', 'uploads', 'others');
			foreach ($entities as $entity) {
				
				foreach ($backup as $key => $data) {
					if ($key != $entity) continue;
					
					$set_contents = '';
					$entity_array = array();
					$entity_array[] = $key;
					
					if ('db' == $key) {
						$set_contents = "0";
					} else {
						foreach (array_keys($data) as $findex) {
							$set_contents .= ('' == $set_contents) ? $findex : ",$findex";
						}
					}

					$entity_array[] = $set_contents;
					$params['downloads'][] = $entity_array;
				}
			}
		}
		
		foreach ($params['downloads'] as $i => $download) {
			if (is_array($download) && 2 == count($download) && isset($download[0]) && isset($download[1])) {
				$entity = $download[0];
				if (('db' == $entity || isset($backupable_entities[$entity])) && isset($backup[$entity])) {
					$indexes = explode(',', $download[1]);
					$retain_string = '';
					foreach ($indexes as $index) {
						$retain = true; // default
						$findex = (0 == $index) ? '' : (string) $index;
						$files = $backup[$entity];
						if (!is_array($files)) $files = array($files);
						$size_key = $entity.$findex.'-size';
						if (isset($files[$index]) && isset($backup[$size_key])) {
							$file = $syllab_dir.'/'.$files[$index];
							if (file_exists($file) && filesize($file) >= $backup[$size_key]) {
								$retain = false;
							}
						}
						if ($retain) {
							$retain_string .= ('' === $retain_string) ? $index : ','.$index;
							$send_back[$i][0] = $entity;
							$send_back[$i][1] = $retain_string;
						}
					}
				} else {
					$send_back[$i][0] = $entity;
					$send_back[$i][1] = $download[$i][1];
				}
			} else {
				// Format not understood. Just send it back as-is.
				$send_back[$i] = $download[$i];
			}
		}
		// Finally, renumber the keys (to usual PHP style - 0, 1, ...). Otherwise, in order to preserve the indexes, json_encode() will create an object instead of an array in the case where $send_back only has one element (and is indexed with an index > 0)
		$send_back = array_values($send_back);
		return array('downloads' => $send_back);
	}

	/**
	 * This is an handler function that checks what entity has been specified in the $params and calls the required method
	 *
	 * @param  [array] $params this is an array of parameters sent via ajax it can include various things depending on what has called this method, this method only cares about the entity parameter which is used to call the correct method and return tree nodes based on that
	 * @return [array] returns an array of jstree nodes
	 */
	public function get_jstree_directory_nodes($params) {

		if ('filebrowser' == $params['entity']) {
			$node_array = $this->_syllab_jstree_directory($params);
		} elseif ('zipbrowser' == $params['entity']) {
			$node_array = $this->_syllab_jstree_zip($params);
		}
		return empty($node_array['error']) ? array('nodes' => $node_array) : $node_array;
	}

	/**
	 * This creates an array of nodes, built from either ABSPATH or the given directory ready to be returned to the jstree object.
	 *
	 * @param  [array] $params this is an array of parameters sent via ajax it can include the following:
	 * node - this is a jstree node object containing information about the selected node
	 * path - this is a path if provided this will be used to build the tree otherwise ABSPATH is used
	 * drop_directory - this is a boolean that if set to true will drop one directory level off the path this is used so that you can move above the current root directory
	 * @return [array] returns an array of jstree nodes
	 */
	private function _syllab_jstree_directory($params) {
		$node_array = array();

		// # is the root node if it's the root node then this is the first call so create a parent node otherwise it's a child node and we should get the path from the node id
		if ('#' == $params['node']['id']) {
				$path = ABSPATH;
				
				if (!empty($params['path'])) $path = $params['path'];

				if (!empty($params['drop_directory']) && true == $params['drop_directory']) $path = dirname($path);
				if (empty($params['skip_root_node'])) {
					$node_array[] = array(
						'text' => basename($path),
						'children' => true,
						'id' => $path,
						'icon' => 'jstree-folder',
						'state' => array(
							'opened' => true
						)
					);
				}
		} else {
			$path = $params['node']['id'];
		}

		$page = empty($params['page']) ? '' : $params['page'];

		if ($dh = opendir($path)) {
			$path = rtrim($path, DIRECTORY_SEPARATOR);

			$skip_paths = array(".", "..");

			while (($value = readdir($dh)) !== false) {
				if (!in_array($value, $skip_paths)) {
					if (is_dir($path . DIRECTORY_SEPARATOR . $value)) {
						$node_array[] = array(
							'text' => $value,
							'children' => true,
							'id' => SyllabPlus_Manipulation_Functions::wp_normalize_path($path . DIRECTORY_SEPARATOR . $value),
							'icon' => 'jstree-folder'
						);
					} elseif (empty($params['directories_only']) && 'restore' != $page && is_file($path . DIRECTORY_SEPARATOR . $value)) {
						$node_array[] = array(
							'text' => $value,
							'children' => false,
							'id' => SyllabPlus_Manipulation_Functions::wp_normalize_path($path . DIRECTORY_SEPARATOR . $value),
							'type' => 'file',
							'icon' => 'jstree-file'
						);
					}
				}
			}
		} else {
			$node_array['error'] = sprintf(__('Failed to open directory: %s. This is normally caused by file permissions.', 'syllabplus'), $path);
		}

		return $node_array;
	}

	/**
	 * This creates an array of nodes, built from a unzipped zip file structure.
	 *
	 * @param  [array] $params this is an array of parameters sent via ajax it can include the following:
	 * node - this is a jstree node object containing information about the selected node
	 * timestamp - this is the backup timestamp and is used to get the backup archive
	 * type - this is the type of backup and is used to get the backup archive
	 * findex - this is the index used to get the correct backup archive if theres more than one of a single archive type
	 * @return [array] returns an array of jstree nodes
	 */
	private function _syllab_jstree_zip($params) {
		
		$syllabplus = $this->_syllabplus;

		$node_array = array();

		$zip_object = $syllabplus->get_zip_object_name();

		// Retrieve the information from our backup history
		$backup_history = SyllabPlus_Backup_History::get_history();

		if (!isset($backup_history[$params['timestamp']][$params['type']])) {
			return array('error' => __('Backup set not found', 'syllabplus'));
		}
		
		// Base name
		$file = $backup_history[$params['timestamp']][$params['type']];

		// Get date in human readable form
		$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $params['timestamp']), 'M d, Y G:i');

		$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);
		
		// Check the file type and set the name in a more friendly way
		$archive_name = isset($backupable_entities[$params['type']]['description']) ? $backupable_entities[$params['type']]['description'] : $params['type'];

		if (substr($params['type'], 0, 2) === 'db') $archive_name = __('Extra database', 'syllabplus') . ' ' . substr($params['type'], 3, 1);
		if ('db' == $params['type']) $archive_name = __('Database', 'syllabplus');
		if ('more' == $params['type']) $archive_name = $backupable_entities[$params['type']]['shortdescription'];
		if ('wpcore' == $params['type']) $archive_name = __('WordPress Core', 'syllabplus');

		$archive_set = ($params['findex'] + 1) . '/' . sizeof($file);

		if ('1/1' == $archive_set) $archive_set = '';

		$parent_name = $archive_name . ' ' . __('archive', 'syllabplus') . ' ' . $archive_set . ' ' . $pretty_date;

		// Deal with multi-archive sets
		if (is_array($file)) $file = $file[$params['findex']];

		// Where it should end up being downloaded to
		$fullpath = $syllabplus->backups_dir_location().'/'.$file;

		if (file_exists($fullpath) && is_readable($fullpath) && filesize($fullpath)>0) {

			$node_array[] = array(
				'text' => $parent_name,
				'parent' => '#',
				'id' => $parent_name,
				'icon' => 'jstree-folder',
				'state' => array('opened' => true),
				'li_attr' => array('path' => $parent_name)
			);

			$zip = new $zip_object;
			
			$zip_opened = $zip->open($fullpath);

			if (true !== $zip_opened) {
				return array('error' => 'SyllabPlus: opening zip (' . $fullpath . '): failed to open this zip file (object='.$zip_object.', code: '.$zip_opened.')');
			} else {
			
				$numfiles = $zip->numFiles;

				if (false === $numfiles) return array('error' => 'SyllabPlus: reading zip: '.$zip->last_error);
					
				for ($i=0; $i < $numfiles; $i++) {
					$si = $zip->statIndex($i);

					// Fix for windows being unable to build jstree due to different directory separators being used
					$si['name'] = str_replace("/", DIRECTORY_SEPARATOR, $si['name']);

					// if it's a dot then we don't want to append this as it will break the ids and the tree structure
					if ('.' == dirname($si['name'])) {
						$node_id = $parent_name;
					} else {
						$node_id = $parent_name . DIRECTORY_SEPARATOR . dirname($si['name']) . DIRECTORY_SEPARATOR;
					}

					$extension = substr(strrchr($si['name'], "."), 1);
					
					if (0 == $si['size'] && empty($extension)) {
						$node_array[] = array(
							'text' => basename($si['name']),
							'parent' => $node_id,
							'id' => $parent_name . DIRECTORY_SEPARATOR . $si['name'],
							'icon' => 'jstree-folder',
							'li_attr' => array(
								'path' => $parent_name . DIRECTORY_SEPARATOR . $si['name']
							)
						);
					} else {
						$node_array[] = array(
							'text' => basename($si['name']),
							'parent' => $node_id,
							'id' => $parent_name . DIRECTORY_SEPARATOR . $si['name'],
							'type' => 'file',
							'icon' => 'jstree-file',
							'li_attr' => array(
								'path' => $parent_name . DIRECTORY_SEPARATOR . $si['name'],
								'size' => SyllabPlus_Manipulation_Functions::convert_numeric_size_to_text($si['size'])
							)
						);
					}
				}

				// check if this is an upload archive if it is add a 'uploads' folder so that the children can attach to it
				if ('uploads' == $params['type']) $node_array[] = array(
					'text' => 'uploads',
					'parent' => $parent_name,
					'id' => $parent_name . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
					'icon' => 'jstree-folder',
					'li_attr' => array(
						'path' => $parent_name . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
					)
				);

				@$zip->close();// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
		}

		return $node_array;
	}

	/**
	 * Return information on the zipfile download
	 *
	 * @param Array $params - details on the download; keys: type, findex, path, timestamp
	 *
	 * @return Array
	 */
	public function get_zipfile_download($params) {
		return apply_filters('syllabplus_command_get_zipfile_download', array('error' => 'SyllabPlus: command (get_zipfile_download) not installed (are you missing an add-on?)'), $params);
	}
	
	/**
	 * Dismiss the notice  which will if .htaccess have any old migrated site reference.
	 *
	 * @return Boolean Return true if migration notice is dismissed
	 */
	public function dismiss_migration_notice_for_old_site_reference() {
		delete_site_option('syllabplus_migrated_site_domain');
		return true;
	}

	/**
	 * When character set and collate both are unsupported at restoration time and if user change anyone substitution dropdown from both, Other substitution select box value should be change respectively. To achieve this functionality, Ajax calls comes here.
	 *
	 * @param  Array $params this is an array of parameters sent via ajax it can include the following:
	 * collate_change_on_charset_selection_data - It is data in serialize form which is need for choose other dropdown option value. It contains below elemts data:
	 * 	db_supported_collations - All collations supported by current database. This is result of 'SHOW COLLATION' query
	 * 	db_unsupported_collate_unique - Unsupported collates unique array
	 * 	db_collates_found - All collates found in database backup file
	 * event_source_elem - Dropdown elemtn id which trigger the ajax request
	 * syllab_restorer_charset - Charset dropdown selected value option
	 * syllab_restorer_collate - Collate dropdown selected value option
	 *
	 * @return array - $action_data which contains following data:
	 * is_action_required - 1 or 0 Whether or not change other dropdown value
	 * elem_id - Dropdown element id which value need to change. The other dropdown element id
	 * elem_val - Dropdown element value which should be selected for other drodown
	 */
	public function collate_change_on_charset_selection($params) {
		$collate_change_on_charset_selection_data = json_decode(SyllabPlus_Manipulation_Functions::wp_unslash($params['collate_change_on_charset_selection_data']), true);
		$syllab_restorer_collate = $params['syllab_restorer_collate'];
		$syllab_restorer_charset = $params['syllab_restorer_charset'];

		$db_supported_collations = $collate_change_on_charset_selection_data['db_supported_collations'];
		$db_unsupported_collate_unique = $collate_change_on_charset_selection_data['db_unsupported_collate_unique'];
		$db_collates_found = $collate_change_on_charset_selection_data['db_collates_found'];

		$action_data = array(
			'is_action_required' => 0,
		);
		// No need to change other dropdown value
		if (isset($db_supported_collations[$syllab_restorer_collate]->Charset) && $syllab_restorer_charset == $db_supported_collations[$syllab_restorer_collate]->Charset) {
			return $action_data;
		}
		$similar_type_collate = $this->_syllabplus->get_similar_collate_related_to_charset($db_supported_collations, $db_unsupported_collate_unique, $syllab_restorer_charset);
		if (empty($similar_type_collate)) {
			$similar_type_collate = $this->_syllabplus->get_similar_collate_based_on_ocuurence_count($db_collates_found, $db_supported_collations, $syllab_restorer_collate);
		}
		// Default collation for changed charcter set
		if (empty($similar_type_collate)) {
			$charset_row = $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare("SHOW CHARACTER SET LIKE '%s'", $syllab_restorer_charset));
			if (null !== $charset_row && !empty($charset_row->{'Default collation'})) {
				$similar_type_collate = $charset_row->{'Default collation'};
			}
		}
		if (empty($similar_type_collate)) {
			foreach ($db_supported_collations as $db_supported_collation => $db_supported_collation_info) {
				if (isset($db_supported_collation_info->Charset) && $syllab_restorer_charset == $db_supported_collation_info->Charset) {
					$similar_type_collate = $db_supported_collation;
					break;
				}
			}
		}
		if (!empty($similar_type_collate)) {
			$action_data['is_action_required'] = 1;
			$action_data['similar_type_collate'] = $similar_type_collate;
		}
		return $action_data;
	}

	/**
	 * Set the Tour status
	 *
	 * @param array $params - the $_REQUEST. We're looking for 'current_step'
	 * @return bool
	 */
	public function set_tour_status($params) {
		return class_exists('SyllabPlus_Tour') ? SyllabPlus_Tour::get_instance()->set_tour_status($params) : false;
	}

	/**
	 * Resets the tour status
	 *
	 * @return bool
	 */
	public function reset_tour_status() {
		return class_exists('SyllabPlus_Tour') ? SyllabPlus_Tour::get_instance()->reset_tour_status() : false;
	}
}
