<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

$accept = apply_filters('syllabplus_accept_archivename', array());
if (!is_array($accept)) $accept = array();
$image_folder = SYLLABPLUS_DIR.'/images/icons/';
$image_folder_url = SYLLABPLUS_URL.'/images/icons/';

?>
<table class="existing-backups-table wp-list-table widefat striped">
	<thead>
		<tr style="margin-bottom: 4px;">
			<?php if (!defined('SYLLABCENTRAL_COMMAND')) : ?>
			<th class="check-column"><label class="screen-reader-text" for="cb-select-all"><?php _e('Select All'); ?></label><input id="cb-select-all" type="checkbox"></th>
			<?php endif; ?>
			<th class="backup-date"><?php _e('Backup date', 'syllabplus');?></th>
			<th class="backup-data"><?php _e('Backup data (click to download)', 'syllabplus');?></th>
			<th class="syllab_backup_actions"><?php _e('Actions', 'syllabplus');?></th>
		</tr>		
	</thead>
	<tbody>
		<?php
		
		if (!defined('SYLLABCENTRAL_COMMAND') && $backup_count <= count($backup_history) - 1) {
			$backup_history = array_slice($backup_history, 0, $backup_count, true);
		} else {
			$show_paging_actions = true;
		}
		
		foreach ($backup_history as $key => $backup) {

			$remote_sent = !empty($backup['service']) && ((is_array($backup['service']) && in_array('remotesend', $backup['service'])) || 'remotesend' === $backup['service']);

			// https://core.trac.wordpress.org/ticket/25331 explains why the following line is wrong
			// $pretty_date = date_i18n('Y-m-d G:i',$key);
			// Convert to blog time zone
			// $pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int)$key), 'Y-m-d G:i');
			$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $key), 'M d, Y G:i');

			$esc_pretty_date = esc_attr($pretty_date);
			$entities = '';

			$nonce = $backup['nonce'];

			$jobdata = isset($backup['jobdata']) ? $backup['jobdata'] : $syllabplus->jobdata_getarray($nonce);

			$rawbackup = $syllabplus_admin->raw_backup_info($backup_history, $key, $nonce, $jobdata);

			$delete_button = $syllabplus_admin->delete_button($key, $nonce, $backup);

			$upload_button = $syllabplus_admin->upload_button($key, $nonce, $backup, $jobdata);

			$date_label = $syllabplus_admin->date_label($pretty_date, $key, $backup, $jobdata, $nonce);

			$log_button = $syllabplus_admin->log_button($backup);

			// Remote backups with no log result in useless empty rows. However, not showing anything messes up the "Existing backups (14)" display, until we tweak that code to count differently
			// if ($remote_sent && !$log_button) continue;

			?>
			<tr class="syllab_existing_backups_row syllab_existing_backups_row_<?php echo esc_html($key);?>" data-key="<?php echo esc_html($key);?>" data-nonce="<?php echo esc_html($nonce);?>">
				<?php if (!defined('SYLLABCENTRAL_COMMAND')) : ?>
				<td class="backup-select">
					<label class="screen-reader-text"><?php _e('Select All'); ?></label><input type="checkbox">
				</td>
				<?php endif; ?>
				<td class="syllab_existingbackup_date " data-nonce="<?php echo wp_create_nonce("syllabplus-credentialtest-nonce"); ?>" data-timestamp="<?php echo $key; ?>" data-label="<?php _e('Backup date', 'syllabplus');?>">
					<div tabindex="0" class="backup_date_label">
						<?php
							echo $date_label;
							if (!empty($backup['always_keep'])) {
								$wp_version = $syllabplus->get_wordpress_version();
								if (version_compare($wp_version, '3.8.0', '<')) {
									$image_url = $image_folder_url.'lock.png';
									?>
									<img class="stored_icon" src="<?php echo esc_attr($image_url);?>" title="<?php echo esc_attr(__('Only allow this backup to be deleted manually (i.e. keep it even if retention limits are hit).', 'syllabplus'));?>">
									<?php
								} else {
									echo '<span class="dashicons dashicons-lock"  title="'.esc_attr(__('Only allow this backup to be deleted manually (i.e. keep it even if retention limits are hit).', 'syllabplus')).'"></span>';
								}
							}
							if (!isset($backup['service'])) $backup['service'] = array();
							if (!is_array($backup['service'])) $backup['service'] = array($backup['service']);
							foreach ($backup['service'] as $service) {
								if ('none' === $service || '' === $service || (is_array($service) && (empty($service) || array('none') === $service || array('') === $service))) {
									// Do nothing
								} else {
									$image_url = file_exists($image_folder.$service.'.png') ? $image_folder_url.$service.'.png' : $image_folder_url.'folder.png';

									$remote_storage = ('remotesend' === $service) ? __('remote site', 'syllabplus') : $syllabplus->backup_methods[$service];
									?>
									<img class="stored_icon" src="<?php echo esc_attr($image_url);?>" title="<?php echo esc_attr(sprintf(__('Remote storage: %s', 'syllabplus'), $remote_storage));?>">
									<?php
								}
							}
						?>
					</div>
				</td>
				
				<td data-label="<?php _e('Backup data (click to download)', 'syllabplus');?>"><?php

				if ($remote_sent) {

					_e('Backup sent to remote site - not available for download.', 'syllabplus');
					if (!empty($backup['remotesend_url'])) echo '<br>'.__('Site', 'syllabplus').': <a href="'.esc_attr($backup['remotesend_url']).'">'.esc_html($backup['remotesend_url']).'</a>';

				} else {

					if (empty($backup['meta_foreign']) || !empty($accept[$backup['meta_foreign']]['separatedb'])) {

						if (isset($backup['db'])) {
							$entities .= '/db=0/';

							// Set a flag according to whether or not $backup['db'] ends in .crypt, then pick this up in the display of the decrypt field.
							$db = is_array($backup['db']) ? $backup['db'][0] : $backup['db'];
							if (SyllabPlus_Encryption::is_file_encrypted($db)) $entities .= '/dbcrypted=1/';

							echo $syllabplus_admin->download_db_button('db', $key, $esc_pretty_date, $backup, $accept);
						}

						// External databases
						foreach ($backup as $bkey => $binfo) {
							if ('db' == $bkey || 'db' != substr($bkey, 0, 2) || '-size' == substr($bkey, -5, 5)) continue;
							echo $syllabplus_admin->download_db_button($bkey, $key, $esc_pretty_date, $backup);
						}

					} else {
						// Foreign without separate db
						$entities = '/db=0/meta_foreign=1/';
					}

					if (!empty($backup['meta_foreign']) && !empty($accept[$backup['meta_foreign']]) && !empty($accept[$backup['meta_foreign']]['separatedb'])) {
						$entities .= '/meta_foreign=2/';
					}

					echo wp_kses($syllabplus_admin->download_buttons($backup, $key, $accept, $entities, $esc_pretty_date),('post'));

				}

				?>
				</td>
				<td class="before-restore-button" data-label="<?php _e('Actions', 'syllabplus');?>">
					<?php
					echo wp_kses($upload_button,('post'));
					echo wp_kses($delete_button,('post'));
					?>
				</td>
			</tr>
		<?php } ?>	

	</tbody>
	<?php if (!$show_paging_actions) : ?>
	<tfoot>
		<tr class="syllab_existing_backups_page_actions">
			<td colspan="4" style="text-align: center;">
				<a class="syllab-load-more-backups"><?php _e('Show more backups...', 'syllabplus');?></a> | <a class="syllab-load-all-backups"><?php _e('Show all backups...', 'syllabplus');?></a>
			</td>
		</tr>
	</tfoot>
	<?php endif; ?>
</table>
<?php if (!defined('SYLLABCENTRAL_COMMAND')) : ?>
<div id="ud_massactions">
	<strong><?php _e('Actions upon selected backups', 'syllabplus');?></strong>
	<div class="syllabplus-remove"><button title="<?php _e('Delete selected backups', 'syllabplus');?>" type="button" class="button button-remove js--delete-selected-backups"><?php _e('Delete', 'syllabplus');?></button></div>
	<div class="syllab-viewlogdiv"><button title="<?php _e('Select all backups', 'syllabplus');?>" type="button" class="button js--select-all-backups" href="#"><?php _e('Select all', 'syllabplus');?></button></div>
	<div class="syllab-viewlogdiv"><button title="<?php _e('Deselect all backups', 'syllabplus');?>" type="button" class="button js--deselect-all-backups" href="#"><?php _e('Deselect', 'syllabplus');?></button></div>
	<small class="ud_massactions-tip"><?php _e('Use ctrl / cmd + press to select several items, or ctrl / cmd + shift + press to select all in between', 'syllabplus'); ?></small>
</div>
<div id="syllab-delete-waitwarning" class="syllab-hidden" style="display:none;">
	<span class="spinner"></span> <em><?php _e('Deleting...', 'syllabplus');?> <span class="syllab-deleting-remote"><?php _e('Please allow time for the communications with the remote storage to complete.', 'syllabplus');?><span></em>
	<p id="syllab-deleted-files-total"></p>
</div>
<?php endif;
