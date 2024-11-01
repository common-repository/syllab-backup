<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>

<?php if (!empty($button_meta) && 'review' == $button_meta) : ?>

	<div class="syllab-ad-container updated">
	<div class="syllab_notice_container syllab_review_notice_container">
		<div class="syllab_advert_content_left_extra">
			<img src="<?php echo SYLLABPLUS_URL.'/images/'.$image;?>" width="100" alt="<?php _e('notice image', 'syllabplus');?>" />
		</div>
		<div class="syllab_advert_content_right">
			<p>
				<?php echo $text; ?>
			</p>
					
			<?php if (!empty($button_link)) { ?>
				<div class="syllab_advert_button_container">
					<a class="button button-primary" href="<?php esc_attr_e(apply_filters('syllabplus_com_link', $button_link));?>" target="_blank" onclick="jQuery('.syllab-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'syllab_ajax', subaction: '<?php echo $dismiss_time;?>', nonce: '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>', dismiss_forever: '1' });">
						<?php _e('Ok, you deserve it', 'syllabplus'); ?>
					</a>
					<div class="dashicons dashicons-calendar"></div>
					<a class="syllab_notice_link" href="#" onclick="jQuery('.syllab-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'syllab_ajax', subaction: '<?php echo $dismiss_time;?>', nonce: '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>', dismiss_forever: '0' });">
						<?php _e('Maybe later', 'syllabplus'); ?>
					</a>
					<div class="dashicons dashicons-no-alt"></div>
					<a class="syllab_notice_link" href="#" onclick="jQuery('.syllab-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'syllab_ajax', subaction: '<?php echo $dismiss_time;?>', nonce: '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>', dismiss_forever: '1' });"><?php _e('Never', 'syllabplus'); ?></a>
				</div>
			<?php } ?>
		</div>
	</div>
	<div class="clear"></div>
</div>

<?php else : ?>

<?php

endif;