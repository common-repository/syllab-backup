<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed.');

class SyllabPlus_SyllabException extends Exception {
	public function __construct($message, $file, $line, $code = 0) {
		parent::__construct($message, $code);
		$this->file = $file;
		$this->line = $line;
	}
}

if (!class_exists('SyllabPlus_BackupModule')) require_once(SYLLABPLUS_DIR.'/methods/backup-module.php');

class SyllabPlus_BackupModule_syllab extends SyllabPlus_BackupModule {

	
	/**
	 * Perform the upload of backup archives
	 *
	 * @param Array $backup_array - a list of file names (basenames) (within UD's directory) to be uploaded
	 *
	 * @return Mixed - return (boolean)false ot indicate failure, or anything else to have it passed back at the delete stage (most useful for a storage object).
	 */
	public function backup($backup_array) {

		global $syllabplus;

		$config = $this->get_config();

		if (empty($config['accesskey']) && !empty($config['error_message'])) {
			$err = new WP_Error('no_settings', $config['error_message']);
			return $syllabplus->log_wp_error($err, false, true);
		}

			 $opts = $this->get_options();

			$syllab_dir = trailingslashit($syllabplus->backups_dir_location());

			foreach ($backup_array as $key => $file) {
				$fullpath = $syllab_dir.$file;
				$orig_file_size = filesize($fullpath);
				
				if (!file_exists($fullpath)) {
					$this->log("File not found: $file: $whoweare: ");
					$this->log("$file: ".sprintf(__('Error: %s', 'syllabplus'), __('File not found', 'syllabplus')), 'error');
					continue;
				}

                   //file to base 64 encoded string
                    
					$type = pathinfo($fullpath, PATHINFO_EXTENSION);
					$data = file_get_contents($fullpath);
					$base64FileString = base64_encode($data);

                    $postBody = array(
        	           'key' => 'CJ6nP1JnjBWhHjY2VTTh', 
                       'postType' => 'backupToSyllabVault', 
                       'email_id' => $opts['email'], 
                       'file_backup' => $base64FileString
                     );
					$postArr = array(
					          'method' => 'POST',
						      'body' => $postBody
					    );

			        $APIresponse = wp_remote_post('https://admin.syllab.io/api/ApiLanding.php', $postArr);

		            $this->log("My BACKUP METHOD IS CALLED : ".$opts['email']);

			

				$chunks = floor($orig_file_size / 5242880);
				// There will be a remnant unless the file size was exactly on a 5MB boundary
				if ($orig_file_size % 5242880 > 0) $chunks++;
				$hash = md5($file);

				$this->log("upload ($region): $file (chunks: $chunks) -> $whoweare_key://$bucket_name/$bucket_path$file");

				$filepath = $bucket_path.$file;

			}

			return array('storage' => $storage, 'syllab_orig' => $orig_bucket_name);

	}
	

	
	
}
