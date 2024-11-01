<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>
<div id="syllab_exclude_modal" title="SyllabPlus - <?php _e('Exclude files/directories', 'syllabplus');?>">
	<input type="hidden" id="syllab_exclude_modal_for" value=""/>
	<input type="hidden" id="syllab_exclude_modal_path" value=""/>
	<div id="syllab_exclude_modal_main">
		<p><?php _e("Select a way to exclude files or directories from the backup", 'syllabplus');?>:</p>		
		<ol class="syllab_exclude_actions_list">
			<li>
				<a href="#" class="syllab-exclude-link" data-panel="file-dir"><?php _e('File/directory', 'syllabplus');?></a>
			</li>
			<li>
				<a href="#" class="syllab-exclude-link" data-panel="extension"><?php _e('All files with this extension', 'syllabplus');?></a>
			</li>
			<li>
				<a href="#" class="syllab-exclude-link" data-panel="begin-with"><?php _e('All files beginning with given characters', 'syllabplus');?></a>
			</li>
			<li>
				<a href="#" class="syllab-exclude-link" data-panel="contain-clause"><?php _e('Files/Directories containing the given characters in their names', 'syllabplus');?></a>
			</li>
		</ol>
	</div>
	<?php $panel = 'file-dir';?>
	<div class="syllab-exclude-panel syllab-hidden" data-panel="<?php echo esc_html($panel);?>" style="display:none;">
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-heading.php', false, array('title' => __('File/directory', 'syllabplus')));
		?>
		<div class="syllab-add-dir-file-cont">
			<div id="syllab_exclude_jstree_info_container" class="syllab-jstree-info-container">
				<p>
					<span id="syllab_exclude_jstree_path_text">
						<?php _e('Select a file/folder which you would like to exclude', 'syllabplus'); ?>
					</span>
				</p>
			</div>
			<div id="syllab_exclude_files_jstree_container">
				<div id="syllab_exclude_files_folders_jstree" class="syllab_jstree"></div>
			</div>
			<?php
			$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-submit.php', false, array('panel' => $panel));
			?>
		</div>
	</div>
	
	<?php $panel = 'extension';?>
	<div class="syllab-exclude-panel syllab-hidden" data-panel="<?php echo esc_html($panel);?>" style="display:none;">
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-heading.php', false, array('title' => __('All files with this extension', 'syllabplus')));
		?>
		<label for="syllab_exclude_extension_field"><?php _e('All files with this extension', 'syllabplus');?>: </label>
		<input type="text" name="syllab_exclude_extension_field" id="syllab_exclude_extension_field" size="25" placeholder="<?php _e('Type an extension like zip', 'syllabplus');?>" />
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-submit.php', false, array('panel' => $panel));
		?>
	</div>
	
	<?php $panel = 'begin-with';?>
	<div class="syllab-exclude-panel syllab-hidden" data-panel="<?php echo esc_html($panel);?>" style="display:none;">
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-heading.php', false, array('title' => __('All files beginning with these characters', 'syllabplus')));
		?>
		<label for="syllab_exclude_prefix_field"><?php _e('All files beginning with these characters', 'syllabplus');?>: </label>
		<input type="text" name="syllab_exclude_prefix_field" id="syllab_exclude_prefix_field" size="25" placeholder="<?php _e('Type a file prefix', 'syllabplus');?>" />
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-submit.php', false, array('panel' => $panel));
		?>
	</div>

	<?php $panel = 'contain-clause';?>
	<div class="syllab-exclude-panel syllab-hidden" data-panel="<?php echo esc_html($panel);?>" style="display:none;">
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-heading.php', false, array('title' => __('All files/directories containing the given characters in their names', 'syllabplus')));
		?>
		<div id="syllab_exclude_jstree_info_container" class="syllab-jstree-info-container">
			<p>
				<span id="syllab_exclude_jstree_path_text">
					<?php _e('Select the folder in which the files or sub-directories you would like to exclude are located', 'syllabplus'); ?>
				</span>
			</p>
		</div>
		<div id="syllab_exclude_files_jstree_container">
				<div id="syllab_exclude_files_folders_wildcards_jstree" class="syllab_jstree"></div>
		</div>
		<label for="syllab_exclude_prefix_field" class="contain-clause-sub-label"><?php _e('All files/directories containing ', 'syllabplus');?></label>
		<div class="clause-input-container">
			<input class="wildcards-input" type="text" size="25" placeholder="<?php _e('these characters', 'syllabplus');?>" />
			<select class="clause-options wildcards-input">
				<option value="beginning"><?php _e('at the beginning of their names', 'syllabplus');?></option>
				<option value="middle"><?php _e('anywhere in their names', 'syllabplus');?></option>
				<option value="end"><?php _e('at the end of their names', 'syllabplus');?></option>
			</select>
		</div>
		<?php
		$syllabplus_admin->include_template('wp-admin/settings/exclude-settings-modal/exclude-panel-submit.php', false, array('panel' => $panel, 'text_button' => __('Add exclusion rule', 'syllabplus')));
		?>
	</div>
</div>