<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

// $options is passed in
$default_options = array(
	'include_uploader' => true,
	'include_opera_warning' => false,
	'will_immediately_calculate_disk_space' => true,
	'include_whitespace_warning' => true,
	'include_header' => false,
);

foreach ($default_options as $k => $v) {
	if (!isset($options[$k])) $options[$k] = $v;
}

// $backup_history is passed in
if (false === $backup_history) $backup_history = SyllabPlus_Backup_History::get_history();

if (!empty($options['include_header'])) echo '<h2>'.__('Existing backups', 'syllabplus').' ('.count($backup_history).')</h2>';

?>
<div class="download-backups form-table">
	<?php if (!empty($options['include_whitespace_warning'])) { ?>
		<p class="ud-whitespace-warning syllab-hidden" style="display:none;">
			<?php echo '<strong>'.__('Warning', 'syllabplus').':</strong> '.__('Your WordPress installation has a problem with outputting extra whitespace. This can corrupt backups that you download from here.', 'syllabplus').' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/problems-with-extra-white-space/").'" target="_blank">'.__('Follow this link for more information', 'syllabplus').'</a>';?>
		</p>
	<?php }
	$bom_warning = $syllabplus_admin->get_bom_warning_text();
	if (!empty($bom_warning)) {
	?>
	<p class="ud-bom-warning">
	<?php
	echo esc_html($bom_warning);
	?>
	</p>
	<?php
	}
	$syllabplus_admin->take_backup_content();
	?>
	
	<div class="syllab_existing_backups_wrapper">
		<h3 id="syllab-existing-backups-heading"><?php echo __('Existing backups', 'syllabplus');?> <span class="syllab_existing_backups_count"><?php echo count($backup_history);?></span></h3>
		<ul class="syllab-disk-space-actions">
			<?php
				echo SyllabPlus_Filesystem_Functions::web_server_disk_space($options['will_immediately_calculate_disk_space']);
			?>
	
			<?php if (!empty($options['include_opera_warning'])) { ?>
				<li class="syllab-opera-warning"><strong><?php _e('Opera web browser', 'syllabplus');?>:</strong> <?php _e('If you are using this, then turn Turbo/Road mode off.', 'syllabplus');?></li>
			<?php } ?>
		</ul>
		<?php
			if (!empty($options['include_uploader'])) {
			?>
		
			<div id="syllab-plupload-modal" style="display:none;" title="<?php _e('SyllabPlus - Upload backup files', 'syllabplus'); ?>">
			<p class="upload"><em><?php _e("Upload files into SyllabPlus.", 'syllabplus');?> <?php echo esc_html(__('Or, you can place them manually into your SyllabPlus directory (usually wp-content/syllab), e.g. via FTP, and then use the "rescan" link above.', 'syllabplus'));?></em></p>
			<?php
			if (version_compare($syllabplus->get_wordpress_version(), '3.3', '<')) {
				echo '<em>'.sprintf(__('This feature requires %s version %s or later', 'syllabplus'), 'WordPress', '3.3').'</em>';
			} else {
				?>
				<div id="plupload-upload-ui">
				<div id="drag-drop-area">
					<div class="drag-drop-inside">
					<p class="drag-drop-info"><?php _e('Drop backup files here', 'syllabplus'); ?></p>
					<p><?php _ex('or', 'Uploader: Drop backup files here - or - Select Files'); ?></p>
					<p class="drag-drop-buttons"><input id="plupload-browse-button" type="button" value="<?php esc_attr_e('Select Files'); ?>" class="button" /></p>
					</div>
				</div>
				<div id="filelist">
				</div>
				</div>
				<?php
			}
			?>
			</div>
		<?php
			}
		?>		
		<div class="ud_downloadstatus"></div>
		<div class="syllab_existing_backups">
			<?php echo SyllabPlus_Backup_History::existing_backup_table($backup_history); ?>
		</div>
	</div>
</div>
