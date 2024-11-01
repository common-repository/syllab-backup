<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>

<div class="syllab-ad-container updated">
	<div class="syllab_notice_container">
		<div class="syllab_advert_content_left">
			<img src="<?php echo SYLLABPLUS_URL.'/images/'.$image;?>" width="60" height="60" alt="<?php _e('notice image', 'syllabplus');?>" />
		</div>
		<div class="syllab_advert_content_right">
			<h3 class="syllab_advert_heading">
				<?php
					if (!empty($prefix)) echo $prefix.' ';
					echo $title;
				?>
				<div class="syllab-advert-dismiss">
				<?php if (!empty($dismiss_time)) { ?>
					<a href="#" onclick="jQuery('.syllab-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'syllab_ajax', subaction: '<?php echo $dismiss_time;?>', nonce: '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>' });"><?php _e('Dismiss', 'syllabplus'); ?></a>
				<?php } else { ?>
					<a href="#" onclick="jQuery('.syllab-ad-container').slideUp();"><?php _e('Dismiss', 'syllabplus'); ?></a>
				<?php } ?>
				</div>
			</h3>
			<p>
				<?php
					echo esc_html($text);
				?>
			</p>
			<p>
				<?php
					echo esc_html($text2);
				?>
			</p>
			<p>
				<?php
					echo esc_html($text3);
				?>
			</p>
		</div>
	</div>
	<div class="clear"></div>
</div>
