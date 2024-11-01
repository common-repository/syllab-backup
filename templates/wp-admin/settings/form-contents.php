<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

$syllab_dir = $syllabplus->backups_dir_location();
$really_is_writable = SyllabPlus_Filesystem_Functions::really_is_writable($syllab_dir);

// $options is passed through
$default_options = array(
	'include_database_decrypter' => true,
	'include_adverts' => true,
	'include_save_button' => true
);

foreach ($default_options as $k => $v) {
	if (!isset($options[$k])) $options[$k] = $v;
}

?>
<table class="form-table backup-schedule">
	<tr>
		<th><?php _e('Files backup schedule', 'syllabplus'); ?>:</th>
		<td class="js-file-backup-schedule">
			<div>
				<select title="<?php echo __('Files backup interval', 'syllabplus'); ?>" class="syllab_interval" name="syllab_interval">
				<?php
				$intervals = $syllabplus_admin->get_intervals('files');
				$selected_interval = SyllabPlus_Options::get_syllab_option('syllab_interval', 'manual');
				foreach ($intervals as $cronsched => $descrip) {
					echo "<option value=\"$cronsched\" ";
					if ($cronsched == $selected_interval) echo 'selected="selected"';
					echo ">".esc_html($descrip)."</option>\n";
				}
				?>
				</select> <span class="syllab_files_timings"><?php echo apply_filters('syllabplus_schedule_showfileopts', '<input type="hidden" name="syllabplus_starttime_files" value="">', $selected_interval); ?></span>
			

				<?php

					$syllab_retain = max((int) SyllabPlus_Options::get_syllab_option('syllab_retain', 2), 1);

					$retain_files_config = __('and retain this many scheduled backups', 'syllabplus').': <input type="number" min="1" step="1" title="'.__('Retain this many scheduled file backups', 'syllabplus').'" name="syllab_retain" value="'.$syllab_retain.'" class="retain-files" />';

					echo $retain_files_config;

				?>
			</div>
			<?php
				do_action('syllabplus_incremental_cell', $selected_interval);
				do_action('syllabplus_after_filesconfig');
			?>
		</td>
	</tr>

	<?php apply_filters('syllabplus_after_file_intervals', false, $selected_interval); ?>
	<tr>
		<th>
			<?php _e('Database backup schedule', 'syllabplus'); ?>:
		</th>
		<td class="js-database-backup-schedule">
		<div>
			<select class="syllab_interval_database" title="<?php echo __('Database backup interval', 'syllabplus'); ?>" name="syllab_interval_database">
			<?php
			$intervals = $syllabplus_admin->get_intervals('db');
			$selected_interval_db = SyllabPlus_Options::get_syllab_option('syllab_interval_database', SyllabPlus_Options::get_syllab_option('syllab_interval'));
			foreach ($intervals as $cronsched => $descrip) {
				echo "<option value=\"$cronsched\" ";
				if ($cronsched == $selected_interval_db) echo 'selected="selected"';
				echo ">$descrip</option>\n";
			}
			?>
			</select> <span class="syllab_same_schedules_message"><?php echo apply_filters('syllabplus_schedule_sametimemsg', '');?></span><span class="syllab_db_timings"><?php echo apply_filters('syllabplus_schedule_showdbopts', '<input type="hidden" name="syllabplus_starttime_db" value="">', $selected_interval_db); ?></span>

			<?php
				$syllab_retain_db = max((int) SyllabPlus_Options::get_syllab_option('syllab_retain_db', $syllab_retain), 1);
				$retain_dbs_config = __('and retain this many scheduled backups', 'syllabplus').': <input type="number" min="1" step="1" title="'.__('Retain this many scheduled database backups', 'syllabplus').'" name="syllab_retain_db" value="'.$syllab_retain_db.'" class="retain-files" />';

				echo $retain_dbs_config;
			?>
			</div>
			<?php do_action('syllabplus_after_dbconfig'); ?>
		</td>
	</tr>
	<tr class="backup-interval-description">
		<th></th>
		<td><div>
		<?php
			echo apply_filters('syllabplus_fixtime_ftinfo', '<p>'.__('Conveniently set the backup schedule daily, weekly, or monthly or choose a manual option. You can repeat the scheduled action to adjust the backup according to your needs. To illustrate, when you select your daily backup and 2 times frequency, the backup will run for 2 days.').'</p>');
		?>
		</div></td>
	</tr>
</table>

<h2 class="syllab_settings_sectionheading"><?php _e('Sending Your Backup To Remote Storage', 'syllabplus');?></h2>

<?php
	$debug_mode = SyllabPlus_Options::get_syllab_option('syllab_debug_mode') ? 'checked="checked"' : "";
	$active_service = SyllabPlus_Options::get_syllab_option('syllab_service');
?>

<table id="remote-storage-holder" class="form-table width-900">
	<tr>
		<th><?php
			echo __('Choose your remote storage', 'syllabplus').'<br>'.apply_filters('syllabplus_after_remote_storage_heading_message', '<em>'.__('(tap on an icon to select or unselect)', 'syllabplus').'</em>');
		?>:</th>
		<td>
		<div id="remote-storage-container">
		<?php
			if (is_array($active_service)) $active_service = $syllabplus->just_one($active_service);
			
			// Change this to give a class that we can exclude
			$multi = apply_filters('syllabplus_storage_printoptions_multi', '');
			
			foreach ($syllabplus->backup_methods as $method => $description) {
				$backup_using = esc_attr(sprintf(__("Backup using %s?", 'syllabplus'), $description));
				
				echo "<input aria-label=\"$backup_using\" name=\"syllab_service[]\" class=\"syllab_servicecheckbox $method $multi\" id=\"syllab_servicecheckbox_$method\" type=\"checkbox\" value=\"$method\"";
				if ($active_service === $method || (is_array($active_service) && in_array($method, $active_service))) echo ' checked="checked"';
				echo " data-labelauty=\"".esc_attr($description)."\">";
			}
		?>
		
		<?php /*
			if (false === apply_filters('syllabplus_storage_printoptions', false, $active_service)) {
				echo '</div>';
				echo '<p><a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/morestorage/").'" target="_blank">'.esc_html(__('You can send a backup to more than one destination with an add-on.', 'syllabplus')).'</a></p>';
			} */
		?>
		
		</td>
	</tr>

	<tr class="syllabplusmethod none ud_nostorage" style="display:none;">
		<td></td>
		<td><em><?php echo esc_html(__('If you choose no remote storage, then the backups remain on the web-server. This is not recommended (unless you plan to manually copy them to your computer), as losing the web-server would mean losing both your website and the backups in one event.', 'syllabplus'));?></em></td>
	</tr>
</table>

<hr class="syllab_separator">

<h2 class="syllab_settings_sectionheading"><?php _e('File Options', 'syllabplus');?></h2>

<table class="form-table js-tour-settings-more width-900" >
	<tr>
		<th><?php _e('Include in files backup', 'syllabplus');?>:</th>
		<td>
			<?php echo $syllabplus_admin->files_selector_widgetry('', true, true); ?>
			<?php /* ?> <p><?php echo apply_filters('syllabplus_admin_directories_description', __('The above directories are everything, except for WordPress core itself which you can download afresh from WordPress.org.', 'syllabplus').' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/").'" target="_blank">'.esc_html(__('See also the "More Files" add-on from our shop.', 'syllabplus')).'</a>'); ?></p><?php */ ?>
		</td>
	</tr>
</table>

<h2 class="syllab_settings_sectionheading"><?php _e('Database Options', 'syllabplus');?></h2>

<?php /* ?>
<table class="form-table width-900">

	<tr>
		<th><?php _e('Database encryption phrase', 'syllabplus');?>:</th>

		<td>
		<?php
			echo apply_filters('syllab_database_encryption_config', '<a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/landing/syllabplus-premium").'" target="_blank">'.__("Don't want to be spied on? SyllabPlus Premium can encrypt your database backup.", 'syllabplus').'</a> '.__('It can also backup external databases.', 'syllabplus'));
		?>
		</td>
	</tr>
	
	<?php
		if (!empty($options['include_database_decrypter'])) {
		?>
	
		<tr class="backup-crypt-description">
			<td></td>

			<td>

			<a href="<?php echo SyllabPlus::get_current_clean_url();?>" class="syllab_show_decryption_widget"><?php _e('You can manually decrypt an encrypted database here.', 'syllabplus');?></a>

			<div id="syllab-manualdecrypt-modal" class="syllab-hidden" style="display:none;">
				<p><h3><?php _e("Manually decrypt a database backup file", 'syllabplus'); ?></h3></p>

				<?php
				if (version_compare($syllabplus->get_wordpress_version(), '3.3', '<')) {
					echo '<em>'.sprintf(__('This feature requires %s version %s or later', 'syllabplus'), 'WordPress', '3.3').'</em>';
				} else {
				?>

				<div id="plupload-upload-ui2">
					<div id="drag-drop-area2">
						<div class="drag-drop-inside">
							<p class="drag-drop-info"><?php _e('Drop encrypted database files (db.gz.crypt files) here to upload them for decryption', 'syllabplus'); ?></p>
							<p><?php _ex('or', 'Uploader: Drop db.gz.crypt files here to upload them for decryption - or - Select Files', 'syllabplus'); ?></p>
							<p class="drag-drop-buttons"><input id="plupload-browse-button2" type="button" value="<?php esc_attr_e('Select Files', 'syllabplus'); ?>" class="button" /></p>
							<p style="margin-top: 18px;"><?php _e('First, enter the decryption key', 'syllabplus'); ?>: <input id="syllabplus_db_decrypt" type="text" size="12"></p>
						</div>
					</div>
					<div id="filelist2">
					</div>
				</div>

				<?php } ?>

			</div>
			
			<?php
				$plugins = get_plugins();
				$wp_optimize_file = false;

				foreach ($plugins as $key => $value) {
					if ('wp-optimize' == $value['TextDomain']) {
						$wp_optimize_file = $key;
						break;
					}
				}
				
				if (!$wp_optimize_file) {
					?><br><a href="https://wordpress.org/plugins/wp-optimize/" target="_blank"><?php _e('Recommended: optimize your database with WP-Optimize.', 'syllabplus');?></a>
					<?php
				}
			?>
			



			</td>
		</tr>
	
	<?php
		}

		$moredbs_config = apply_filters('syllab_database_moredbs_config', false);
		if (!empty($moredbs_config)) {
		?>
			<tr>
				<th><?php _e('Backup more databases', 'syllabplus');?>:</th>
				<td><?php echo $moredbs_config; ?>
				</td>
			</tr>
		<?php
		}
	?>

</table>
<?php */ ?>

<?php /* ?>
<h2 class="syllab_settings_sectionheading"><?php _e('Reporting', 'syllabplus');?></h2>

<table class="form-table width-900">

<?php
	$report_rows = apply_filters('syllabplus_report_form', false);
	if (is_string($report_rows)) {
		echo $report_rows;
	} else {
	?>

	<tr id="syllab_report_row_no_addon">
		<th><?php _e('Email', 'syllabplus'); ?>:</th>
		<td>
			<?php
				$syllab_email = SyllabPlus_Options::get_syllab_option('syllab_email');
				// in case that premium users doesn't have the reporting addon, then the same email report setting's functionality will be applied to the premium version
				// since the free version allows only one service at a time, $active_service contains just a string name of particular service, in this case 'email'
				// so we need to make the checking a bit more universal by transforming it into an array of services in which we can check whether email is the only service (free onestorage) or one of the services (premium multistorage)
				$temp_services = $active_service;
				if (is_string($temp_services)) $temp_services = (array) $temp_services;
				$is_email_storage = !empty($temp_services) && in_array('email', $temp_services);
			?>
			<label for="syllab_email" class="syllab_checkbox email_report">
				<input type="checkbox" id="syllab_email" name="syllab_email" value="<?php esc_attr_e(get_bloginfo('admin_email')); ?>"<?php if ($is_email_storage || !empty($syllab_email)) echo ' checked="checked"';?> <?php if ($is_email_storage) echo 'disabled onclick="return false"'; ?>> 
				<?php
					// have to add this hidden input so that when the form is submited and if the udpraft_email checkbox is disabled, this hidden input will be passed to the server along with other active elements
					if ($is_email_storage) echo '<input type="hidden" name="syllab_email" value="'.esc_attr(get_bloginfo('admin_email')).'">';
				?>
				<div id="cb_not_email_storage_label" <?php echo ($is_email_storage) ? 'style="display: none"' : 'style="display: inline"'; ?>>
					<?php echo __("Check this box to have a basic report sent to", 'syllabplus').' <a href="'.admin_url('options-general.php').'">'.__("your site's admin address", 'syllabplus').'</a> ('.esc_html(get_bloginfo('admin_email')).")."; ?>
				</div>
				<div id="cb_email_storage_label" <?php echo (!$is_email_storage) ? 'style="display: none"' : 'style="display: inline"'; ?>>
					<?php echo __("Your email backup and a report will be sent to", 'syllabplus').' <a href="'.admin_url('options-general.php').'">'.__("your site's admin address", 'syllabplus').'</a> ('.esc_html(get_bloginfo('admin_email')).').'; ?>
				</div>
			</label>
			<?php
				if (!class_exists('SyllabPlus_Addon_Reporting')) echo '<a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/reporting/").'" target="_blank">'.__('For more reporting features, use the Reporting add-on.', 'syllabplus').'</a>';
			?>
		</td>
	</tr>

	<?php
	}
?>
</table>
<?php */ ?>

<script type="text/javascript">
/* <![CDATA[ */
<?php
	$storage_objects_and_ids = SyllabPlus_Storage_Methods_Interface::get_storage_objects_and_ids(array_keys($syllabplus->backup_methods));
	// In PHP 5.5+, there's array_column() for this
	$method_objects = array();
	foreach ($storage_objects_and_ids as $method => $method_information) {
		$method_objects[$method] = $method_information['object'];
	}

	echo $syllabplus_admin->get_settings_js($method_objects, $really_is_writable, $syllab_dir, $active_service);
?>
/* ]]> */
</script>
<?php /* ?>
<table class="form-table width-900">
	<tr>
		<td colspan="2"><h2 class="syllab_settings_sectionheading"><?php _e('Advanced / Debugging Settings', 'syllabplus'); ?></h2></td>
	</tr>

	<tr>
		<th><?php _e('Expert settings', 'syllabplus');?>:</th>
		<td><a class="enableexpertmode" href="<?php echo SyllabPlus::get_current_clean_url();?>#enableexpertmode"><?php _e('Show expert settings', 'syllabplus');?></a> - <?php _e("open this to show some further options; don't bother with this unless you have a problem or are curious.", 'syllabplus');?> <?php do_action('syllabplus_expertsettingsdescription'); ?></td>
	</tr>
	<?php
	$delete_local = SyllabPlus_Options::get_syllab_option('syllab_delete_local', 1);
	$split_every_mb = SyllabPlus_Options::get_syllab_option('syllab_split_every', 400);
	if (!is_numeric($split_every_mb)) $split_every_mb = 400;
	if ($split_every_mb < SYLLABPLUS_SPLIT_MIN) $split_every_mb = SYLLABPLUS_SPLIT_MIN;
	?>

	<tr class="expertmode syllab-hidden" style="display:none;">
		<th><?php _e('Debug mode', 'syllabplus');?>:</th>
		<td><input type="checkbox" id="syllab_debug_mode" data-syllab_settings_test="debug_mode" name="syllab_debug_mode" value="1" <?php echo $debug_mode; ?> /> <br><label for="syllab_debug_mode"><?php _e('Check this to receive more information and emails on the backup process - useful if something is going wrong.', 'syllabplus');?> <?php _e('This will also cause debugging output from all plugins to be shown upon this screen - please do not be surprised to see these.', 'syllabplus');?></label></td>
	</tr>

	<tr class="expertmode syllab-hidden" style="display:none;">
		<th><?php _e('Split archives every:', 'syllabplus');?></th>
		<td><input type="text" name="syllab_split_every" class="syllab_split_every" value="<?php echo $split_every_mb; ?>" size="5" /> MB<br><?php echo sprintf(__('SyllabPlus will split up backup archives when they exceed this file size. The default value is %s megabytes. Be careful to leave some margin if your web-server has a hard size limit (e.g. the 2 GB / 2048 MB limit on some 32-bit servers/file systems).', 'syllabplus'), 400).' '.__('The higher the value, the more server resources are required to create the archive.', 'syllabplus'); ?></td>
	</tr>

	<tr class="deletelocal expertmode syllab-hidden" style="display:none;">
		<th><?php _e('Delete local backup', 'syllabplus');?>:</th>
		<td><input type="checkbox" id="syllab_delete_local" name="syllab_delete_local" value="1" <?php if ($delete_local) echo 'checked="checked"'; ?>> <br><label for="syllab_delete_local"><?php _e('Check this to delete any superfluous backup files from your server after the backup run finishes (i.e. if you uncheck, then any files despatched remotely will also remain locally, and any files being kept locally will not be subject to the retention limits).', 'syllabplus');?></label></td>
	</tr>

	<tr class="expertmode backupdirrow syllab-hidden" style="display:none;">
		<th><?php _e('Backup directory', 'syllabplus');?>:</th>
		<td><input type="text" name="syllab_dir" id="syllab_dir" style="width:525px" value="<?php echo esc_html(SyllabPlus_Manipulation_Functions::prune_syllab_dir_prefix($syllab_dir)); ?>" /></td>
	</tr>
	<tr class="expertmode backupdirrow syllab-hidden" style="display:none;">
		<td></td>
		<td>
			<span id="syllab_writable_mess">
				<?php echo $syllabplus_admin->really_writable_message($really_is_writable, $syllab_dir); ?>
			</span>
			<?php
				echo __("This is where SyllabPlus will write the zip files it creates initially.  This directory must be writable by your web server. It is relative to your content directory (which by default is called wp-content).", 'syllabplus').' '.__("<b>Do not</b> place it inside your uploads or plugins directory, as that will cause recursion (backups of backups of backups of...).", 'syllabplus');
			?>
		</td>
	</tr>

	<tr class="expertmode syllab-hidden" style="display:none;">
		<th><?php _e("Use the server's SSL certificates", 'syllabplus');?>:</th>
		<td><input data-syllab_settings_test="useservercerts" type="checkbox" id="syllab_ssl_useservercerts" name="syllab_ssl_useservercerts" value="1" <?php if (SyllabPlus_Options::get_syllab_option('syllab_ssl_useservercerts')) echo 'checked="checked"'; ?>> <br><label for="syllab_ssl_useservercerts"><?php _e('By default SyllabPlus uses its own store of SSL certificates to verify the identity of remote sites (i.e. to make sure it is talking to the real Dropbox, Amazon S3, etc., and not an attacker). We keep these up to date. However, if you get an SSL error, then choosing this option (which causes SyllabPlus to use your web server\'s collection instead) may help.', 'syllabplus');?></label></td>
	</tr>

	<tr class="expertmode syllab-hidden" style="display:none;">
		<th><?php _e('Do not verify SSL certificates', 'syllabplus');?>:</th>
		<td><input data-syllab_settings_test="disableverify" type="checkbox" id="syllab_ssl_disableverify" name="syllab_ssl_disableverify" value="1" <?php if (SyllabPlus_Options::get_syllab_option('syllab_ssl_disableverify')) echo 'checked="checked"'; ?>> <br><label for="syllab_ssl_disableverify"><?php _e('Choosing this option lowers your security by stopping SyllabPlus from verifying the identity of encrypted sites that it connects to (e.g. Dropbox, Google Drive). It means that SyllabPlus will be using SSL only for encryption of traffic, and not for authentication.', 'syllabplus');?> <?php _e('Note that not all cloud backup methods are necessarily using SSL authentication.', 'syllabplus');?></label></td>
	</tr>

	<tr class="expertmode syllab-hidden" style="display:none;">
		<th><?php _e('Disable SSL entirely where possible', 'syllabplus');?>:</th>
		<td><input data-syllab_settings_test="nossl" type="checkbox" id="syllab_ssl_nossl" name="syllab_ssl_nossl" value="1" <?php if (SyllabPlus_Options::get_syllab_option('syllab_ssl_nossl')) echo 'checked="checked"'; ?>> <br><label for="syllab_ssl_nossl"><?php _e('Choosing this option lowers your security by stopping SyllabPlus from using SSL for authentication and encrypted transport at all, where possible. Note that some cloud storage providers do not allow this (e.g. Dropbox), so with those providers this setting will have no effect.', 'syllabplus');?> <a href="<?php echo apply_filters('syllabplus_com_link', "https://syllabplus.com/faqs/i-get-ssl-certificate-errors-when-backing-up-andor-restoring/");?>" target="_blank"><?php _e('See this FAQ also.', 'syllabplus');?></a></label></td>
	</tr>

	<tr class="expertmode syllab-hidden" style="display:none;">
		<th><?php _e('Automatic updates', 'syllabplus');?>:</th>
		<td><label><input type="checkbox" id="syllab_auto_updates" data-syllab_settings_test="syllab_auto_updates" name="syllab_auto_updates" value="1" <?php if ($syllabplus->is_automatic_updating_enabled()) echo 'checked="checked"'; ?>><br /><?php _e('Ask WordPress to automatically update SyllabPlus when it finds an available update.', 'syllabplus');?></label><p><a href="https://wordpress.org/plugins/stops-core-theme-and-plugin-updates/" target="_blank"><?php _e('Read more about Easy Updates Manager', 'syllabplus'); ?></a></p></td>
	</tr>

	<?php do_action('syllabplus_configprint_expertoptions'); ?>

	<tr>
		<td></td>
		<td>
			<?php
				if (!empty($options['include_adverts'])) {
					if (!class_exists('SyllabPlus_Notices')) include_once(UPDRAFTPLUS_DIR.'/includes/syllabplus-notices.php');
					global $syllabplus_notices;
					$syllabplus_notices->do_notice(false, 'bottom');
				}
			?>
		</td>
	</tr>
	
	
</table>
<?php */ ?>

<?php if (!empty($options['include_save_button'])) { ?>
	<tr>
		<td><p><b>Please save changes each time you make a change, connect the account, and choose remote storage. </b></p></td>
		<td>
			<input type="hidden" name="action" value="update" />
			<input type="submit" class="button-primary" id="syllabplus-settings-save" value="<?php _e('Save Changes', 'syllabplus');?>" />
		</td>
	</tr>
	<?php } ?>
