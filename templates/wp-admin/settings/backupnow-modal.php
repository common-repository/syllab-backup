<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
	if (!defined('SYLLABPLUS_DIR')) die('No direct access.');

	global $syllabplus;
	
	$premium_link = apply_filters('syllabplus_com_link', 'https://syllabplus.com/landing/syllabplus-premium');
	
	$free_ret = '';
?>

<p>
	<h3 class="new-backups-only"><?php _e('Take a new backup', 'syllabplus');?></h3>
	<h3 class="incremental-backups-only"><?php _e('Take an incremental backup', 'syllabplus');?></h3>
</p>

<div class="incremental-free-only">
	<p><?php echo sprintf(__('Incremental backups are a feature of %s (upgrade by following this link).', 'syllabplus'), '<a href="'.$syllabplus->get_url('premium').'" target="_blank">SyllabPlus Premium').'</a>'; ?>
	</a>
	<br>
	<a href="https://syllabplus.com/tell-me-more-about-incremental-backups/" target="_blank"><?php _e('Find out more about incremental backups here.', 'syllabplus'); ?></a></p>
</div>

<p id="backupnow_database_container" class="new-backups-only">

	<input type="checkbox" id="backupnow_includedb" checked="checked">
	<label for="backupnow_includedb"><?php _e('Include your database in the backup', 'syllabplus'); ?></label>

	<div id="backupnow_database_moreoptions" class="syllab-hidden" style="display:none;">

		<?php echo apply_filters('syllab_backupnow_database_showmoreoptions', $free_ret, '');?>

	</div>

</p>
	
<p>
	<input type="checkbox" class="new-backups-only" id="backupnow_includefiles" checked="checked">
	<label id="backupnow_includefiles_label" for="backupnow_includefiles"><?php _e("Include your files in the backup", 'syllabplus'); ?></label>
	
	(<a href="<?php echo $syllabplus->get_current_clean_url(); ?>" id="backupnow_includefiles_showmoreoptions">...</a>)<br>

	<div id="backupnow_includefiles_moreoptions" class="syllab-hidden" style="display:none;">
		<em><?php _e('Your saved settings also affect what is backed up - e.g. files excluded.', 'syllabplus'); ?></em><br>
		
		<?php echo $syllabplus_admin->files_selector_widgetry('backupnow_files_', false, 'sometimes'); ?>
	</div>
	
</p>

<div class="backupnow_modal_afterfileoptions">
	<?php echo apply_filters('syllab_backupnow_modal_afterfileoptions', '', ''); ?>
</div>

<span id="backupnow_remote_container"><?php echo $this->backup_now_remote_message(); ?></span>


<div class="backupnow_modal_afteroptions">
	<?php echo apply_filters('syllab_backupnow_modal_afteroptions', '', ''); ?>
</div>
<p class="incremental-backups-only">
	<a href="https://syllabplus.com/tell-me-more-about-incremental-backups/" target="_blank"><?php _e('Find out more about incremental backups here.', 'syllabplus'); ?></a>
</p>
