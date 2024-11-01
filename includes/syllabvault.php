<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No access.');

/**
 * Handles SyllabVault Commands to pull Amazon S3 Bucket credentials
 * from user's SyllabVault and some default filters for per page display
 *
 * @method array get_credentials()
 */
class SyllabCentral_SyllabVault_Commands extends SyllabCentral_Commands {
	
   /**
	* Gets the Amazon S3 Credentials
	*
	* Extract the needed credentials to connect to the user's Amazon S3 Bucket
	* by pulling this info from the SyllabVault server.
	*
	* @return array $result - An array containing the Amazon S3 settings/config if successful,
	*						  otherwise, it will contain the error details/info of the generated error.
	*/
	public function get_credentials() {
		$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array('syllabvault'));

		// SyllabVault isn't expected to have multiple options currently, so we just grab the first instance_id in the settings and use the options from that. If in future we do decide we want to make SyllabVault multiple options then we will need to update this part of the code e.g a instance_id needs to be passed in and used by the following lines of code.
		if (isset($storage_objects_and_ids['syllabvault']['instance_settings'])) {
			$instance_id = key($storage_objects_and_ids['syllabvault']['instance_settings']);
			$opts = $storage_objects_and_ids['syllabvault']['instance_settings'][$instance_id];
			$vault = $storage_objects_and_ids['syllabvault']['object'];
			$vault->set_options($opts, false, $instance_id);
		} else {
			if (!class_exists('SyllabPlus_BackupModule_syllabvault')) include_once(SYLLABPLUS_DIR.'/methods/syllabvault.php');
			$vault = new SyllabPlus_BackupModule_syllabvault();
		}

		$result = $vault->get_config();
		
		if (isset($result['error']) && !empty($result['error'])) {
			$result = array('error' => true, 'message' => $result['error']['message'], 'values' => $result['error']['values']);
		}
		
		return $this->_response($result);
	}
}
