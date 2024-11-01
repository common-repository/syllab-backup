<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');
?>
<div class="syllab_exclude_entity_container" data-include-backup-file="<?php echo esc_attr($key);?>">
	<?php
	if (!empty($include_exclude)) {
	$include_exclude_entities = explode(',', $include_exclude);
		foreach ($include_exclude_entities as $include_exclude_entity) {
		?>
			<div class="syllab_exclude_entity_wrapper">
				<input type="text" class="syllab_exclude_entity_field" name="<?php echo esc_attr('syllab_include_'.$key.'_exclude_entity[]');?>" value="<?php echo esc_attr($include_exclude_entity);?>" data-val="<?php echo esc_attr($include_exclude_entity);?>" data-include-backup-file="<?php echo esc_attr($key);?>" readonly="readonly"/><a href="#" class="syllab_exclude_entity_edit dashicons dashicons-edit" data-include-backup-file="<?php echo esc_attr($key);?>" title="<?php _e('Edit', 'syllabplus'); ?>"></a><a href="#" class="syllab_exclude_entity_update dashicons dashicons-yes" data-include-backup-file="<?php echo esc_attr($key);?>" style="display: none;" title="<?php _e('Confirm change', 'syllabplus'); ?>"></a><a href="#" class="syllab_exclude_entity_delete dashicons dashicons-no" data-include-backup-file="<?php echo esc_attr($key);?>" title="<?php _e('Delete', 'syllabplus'); ?>"></a>
			</div>
		<?php
		}
	}
	?>
</div>
<?php /* ?>
<a href="#" class="syllab_add_exclude_item syllab_icon_link" data-include-backup-file="<?php echo esc_attr($key);?>" data-path="<?php echo esc_attr($path);?>" aria-label="<?php echo sprintf(__('Add an exclusion rule for %s', 'syllabplus'), esc_attr($key)); ?>"><span class="dashicons dashicons-plus"></span><?php echo __('Add an exclusion rule', 'syllabplus');?></a> <?php */ ?>
