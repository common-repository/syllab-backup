<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access.'); ?>
<div class="syllab_backup_content">
	<div id="syllab-insert-admin-warning"></div>
	<noscript>
		<div>
			<?php _e('JavaScript warning', 'syllabplus').': ';?><span style="color:red"><?php _e('This admin interface uses JavaScript heavily. You either need to activate it within your browser, or to use a JavaScript-capable browser.', 'syllabplus');?></span>
		</div>
	</noscript>
	
	<?php
	if ($backup_disabled) {
		$this->show_admin_warning(
			esc_html(__("The 'Backup Now' button is disabled as your backup directory is not writable (go to the 'Settings' tab and find the relevant option).", 'syllabplus')),
			'error'
		);
	}
	?>
	
	<h3 class="syllab_next_scheduled_backups_heading"><?php _e('Next scheduled backups', 'syllabplus');?>:</h3>
	<div class="syllab_next_scheduled_backups_wrapper postbox">
		<div class="schedule">
			<div class="syllab_next_scheduled_entity">
				<div class="syllab_next_scheduled_heading">
					<strong><?php echo __('Files', 'syllabplus').':';?></strong>
				</div>
				<div id="syllab-next-files-backup-inner">
					<?php
					$syllabplus_admin->next_scheduled_files_backups_output();
					?>
				</div>
			</div>
			<div class="syllab_next_scheduled_entity">
				<div class="syllab_next_scheduled_heading">
					<strong><?php echo __('Database', 'syllabplus').':';?></strong>
				</div>
				<div id="syllab-next-database-backup-inner">
					<?php
						$syllabplus_admin->next_scheduled_database_backups_output();
					?>
				</div>
			</div>
			<div class="syllab_time_now_wrapper">
				<?php
				// wp_date() is WP 5.3+, but performs translation into the site locale
				$current_time = function_exists('wp_date') ? wp_date('D, F j, Y H:i') : get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'D, F j, Y H:i');
				?>
				<span class="syllab_time_now_label"><?php echo __('Time now', 'syllabplus').': ';?></span>
				<span class="syllab_time_now"><?php echo esc_html($current_time);?></span>
			</div>
		</div>
		<div class="syllab_backup_btn_wrapper">
			<button id="syllab-backupnow-button" style="background: #f15b06f5!important;" type="button" <?php echo $backup_disabled; ?> class="button button-primary button-large button-hero" <?php if ($backup_disabled) echo 'title="'.esc_attr(__('This button is disabled because your backup directory is not writable (see the settings).', 'syllabplus')).'" ';?> onclick="syllab_backup_dialog_open(); return false;"><?php echo str_ireplace('Back Up', 'Backup', __('Backup Now', 'syllabplus'));?></button>
		</div>
		<div id="syllab_activejobs_table">
			<?php
			$active_jobs = $this->print_active_jobs();
			?>
			<div id="syllab_activejobsrow">
				<?php echo $active_jobs;?>
			</div>
		</div>
	</div>

	<?php /* ?>
	<div id="syllab_lastlogmessagerow">
		<h3><?php _e('Last log message', 'syllabplus');?>:</h3>
		<?php //$this->most_recently_modified_log_link(); ?>
		<div class="postbox">
			<span id="syllab_lastlogcontainer"><?php echo esc_html(SyllabPlus_Options::get_syllab_lastmessage()); ?></span>			
		</div>
	</div> <?php */ ?>
	
	<div id="syllab-iframe-modal">
		<div id="syllab-iframe-modal-innards">
		</div>
	</div>
	
	<div id="syllab-authenticate-modal" style="display:none;" title="<?php esc_attr_e('Remote storage authentication', 'syllabplus');?>">
		<p><?php _e('You have selected a remote storage option which has an authorization step to complete:', 'syllabplus'); ?></p>
		<div id="syllab-authenticate-modal-innards">
		</div>
	</div>
	
	<div id="syllab-backupnow-modal" title="Syllab - <?php _e('Perform a backup', 'syllabplus'); ?>">
		<?php echo $syllabplus_admin->backupnow_modal_contents(); ?>
	</div>
	
	<?php if (is_multisite() && !file_exists(SYLLABPLUS_DIR.'/addons/multisite.php')) { ?>
		<h2>SyllabPlus <?php _e('Multisite', 'syllabplus');?></h2>
		<table>
			<tr>
				<td>
					<p class="multisite-advert-width"><?php echo __('Do you need WordPress Multisite support?', 'syllabplus').' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/syllabplus-premium/").'" target="_blank">'. __('Please check out SyllabPlus Premium, or the stand-alone Multisite add-on.', 'syllabplus');?></a>.</p>
				</td>
			</tr>
		</table>
	<?php } ?>
</div>
