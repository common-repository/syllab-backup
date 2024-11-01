<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>

<div id="syllab-message-modal" title="SyllabPlus">
	<div id="syllab-message-modal-innards">
	</div>
</div>

<div id="syllab-delete-modal" title="<?php _e('Delete backup set', 'syllabplus');?>">
	<form id="syllab_delete_form" method="post">
		<p id="syllab_delete_question_singular">
			<?php printf(__('Are you sure that you wish to remove')); ?>
		</p>
		<p id="syllab_delete_question_plural" class="syllab-hidden" style="display:none;">
			<?php printf(__('Are you sure that you wish to remove')); ?>
		</p>
		<fieldset>
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>">
			<input type="hidden" name="action" value="syllab_ajax">
			<input type="hidden" name="subaction" value="deleteset">
			<input type="hidden" name="backup_timestamp" value="0" id="syllab_delete_timestamp">
			<input type="hidden" name="backup_nonce" value="0" id="syllab_delete_nonce">
			<div id="syllab-delete-remote-section">
				<input checked="checked" type="checkbox" name="delete_remote" id="syllab_delete_remote" value="1"> <label for="syllab_delete_remote"><?php _e('Also delete from remote storage', 'syllabplus');?></label><br>
			</div>
		</fieldset>
	</form>
</div>

<div class="syllab_restore_container" style="display: none;">
	<div class="syllab_restore_main--header"><?php _e('SyllabPlus Restoration', 'syllabplus'); ?> — <strong><?php _e('Restore files from', 'syllabplus');?>:</strong> <span class="syllab_restore_date"></span></div>
	<div class="syllab_restore_main">
		<div id="syllab-restore-modal" title="SyllabPlus - <?php _e('Restore backup', 'syllabplus');?>">

			<div class="syllab-restore-modal--stage syllab--flex" id="syllab-restore-modal-stage2">
				<div class="syllab--two-halves">
					<p><strong><?php _e('Retrieving (if necessary) and preparing backup files...', 'syllabplus');?></strong></p>
					<div id="syllab-restore-modal-stage2a"></div>
					<div id="ud_downloadstatus2"></div>
				</div>
			</div>

			<div class="syllab-restore-modal--stage syllab--flex" id="syllab-restore-modal-stage1">
				<div class="syllab--one-half syllab-color--very-light-grey">
					<p><?php _e("Restoring will replace this site's themes, plugins, uploads, database and/or other content directories (according to what is contained in the backup set, and your selection).", 'syllabplus');?> <?php _e('Choose the components to restore', 'syllabplus');?>:</p>
					<p><em><a href="<?php echo apply_filters('syllabplus_com_link', "https://syllabplus.com/faqs/what-should-i-understand-before-undertaking-a-restoration/");?>" target="_blank"><?php _e('Do read this helpful article of useful things to know before restoring.', 'syllabplus');?></a></em></p>
				</div>
				<div class="syllab--one-half">
					<form id="syllab_restore_form" method="post">
						<fieldset>
							<input type="hidden" name="action" value="syllab_restore">
							<input type="hidden" name="syllabplus_ajax_restore" value="start_ajax_restore">
							<input type="hidden" name="backup_timestamp" value="0" id="syllab_restore_timestamp">
							<input type="hidden" name="meta_foreign" value="0" id="syllab_restore_meta_foreign">
							<input type="hidden" name="syllab_restorer_backup_info" value="" id="syllab_restorer_backup_info">
							<input type="hidden" name="syllab_restorer_restore_options" value="" id="syllab_restorer_restore_options">
							<?php

								// The 'off' check is for badly configured setups - http://wordpress.org/support/topic/plugin-wp-super-cache-warning-php-safe-mode-enabled-but-safe-mode-is-off
								if ($syllabplus->detect_safe_mode()) {
									echo "<p><em>".__("Your web server has PHP's so-called safe_mode active.", 'syllabplus').' '.__('This makes time-outs much more likely. You are recommended to turn safe_mode off, or to restore only one entity at a time', 'syllabplus').' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/faqs/i-want-to-restore-but-have-either-cannot-or-have-failed-to-do-so-from-the-wp-admin-console/").'" target="_blank">'.__('or to restore manually', 'syllabplus').'.</a></em></p>';
								}
							?>
							<p><strong><?php _e('Choose the components to restore:', 'syllabplus'); ?></strong></p>
							<?php
								$backupable_entities = $syllabplus->get_backupable_file_entities(true, true);

								foreach ($backupable_entities as $type => $info) {
									if (!isset($info['restorable']) || true == $info['restorable']) {
										$sdescrip = isset($info['shortdescription']) ? $info['shortdescription'] : $info['description'];
										echo '<div class="syllab-restore-item"><input id="syllab_restore_'.esc_html($type).'" type="checkbox" name="syllab_restore[]" value="'.esc_html($type).'"> <label id="syllab_restore_label_'.$type.'" for="syllab_restore_'.esc_html($type).'">'.esc_html($sdescrip).'</label><br>';
										do_action("syllabplus_restore_form_$type");
										echo '</div>';
									} else {
										$sdescrip = isset($info['shortdescription']) ? $info['shortdescription'] : $info['description'];
										echo "<div class=\"syllab-restore-item cannot-restore\"><em>".esc_html(sprintf(__('The following entity cannot be restored automatically: "%s".', 'syllabplus'), esc_html($sdescrip)))." ".__('You will need to restore it manually.', 'syllabplus')."</em><br>".'<input id="syllab_restore_'.esc_html($type).'" type="hidden" name="syllab_restore[]" value="'.esc_html($type).'">';
										echo '</div>';
									}
								}
							?>
							<div class="syllab-restore-item">
								<input id="syllab_restore_db" type="checkbox" name="syllab_restore[]" value="db"> <label for="syllab_restore_db"><?php _e('Database', 'syllabplus'); ?></label>
								<div id="syllab_restorer_dboptions" class="notice below-h2 syllab-restore-option syllab-hidden"><h4><?php echo sprintf(__('%s restoration options:', 'syllabplus'), __('Database', 'syllabplus')); ?></h4>
									<?php
									do_action("syllabplus_restore_form_db");
									?>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>

			<div class="syllab-restore--footer">
				<button type="button" class="button syllab-restore--cancel"><?php _e('Cancel', 'syllabplus'); ?></button>
				<ul class="syllab-restore--stages">
					<li class="active"><span><?php _e('1. Component selection', 'syllabplus'); ?></span></li>
					<li><span><?php _e('2. Verifications', 'syllabplus'); ?></span></li>
					<li><span><?php _e('3. Restoration', 'syllabplus'); ?></span></li>
				</ul>
				<button type="button" class="button button-primary syllab-restore--next-step"><?php _e('Next', 'syllabplus'); ?></button>
			</div>

		</div>
	</div>
</div>
