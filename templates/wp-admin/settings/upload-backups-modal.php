<?php 

/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>

<div id="syllab-upload-modal" title="SyllabPlus - <?php _e('Upload backup', 'syllabplus');?>">
	<p><?php _e("Select the remote storage destinations you want to upload this backup set to", 'syllabplus');?>:</p>
	<form id="syllab_upload_form" method="post">
		<fieldset>
			<input type="hidden" name="backup_timestamp" value="0" id="syllab_upload_timestamp">
			<input type="hidden" name="backup_nonce" value="0" id="syllab_upload_nonce">

			<?php
				global $syllabplus;
				
				$service = (array) $syllabplus->just_one($syllabplus->get_canonical_service_list());

				foreach ($service as $value) {
					if ('' == $value) continue;
					echo '<input class="syllab_remote_storage_destination" id="syllab_remote_'.$value.'" checked="checked" type="checkbox" name="syllab_remote_storage_destination_'. $value . '" value="'.$value.'"> <label for="syllab_remote_'.$value.'">'.$syllabplus->backup_methods[$value].'</label><br>';
				}
			?>
		</fieldset>
	</form>
	<p id="syllab-upload-modal-error"></p>
</div>
