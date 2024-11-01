<!--
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/
--> 

<div id="syllab-dashnotice" class="updated">
	<div style="float:right;"><a href="#" onclick="jQuery('#syllab-dashnotice').slideUp(); jQuery.post(ajaxurl, {action: 'syllab_ajax', subaction: 'dismissdashnotice', nonce: '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>' });"><?php printf(__('Dismiss (for %s months)', 'syllabplus'), 12); ?></a></div>

	<h3><?php _e('Thank you for installing SyllabPlus!', 'syllabplus');?></h3>
	
	<a href="<?php echo apply_filters('syllabplus_com_link', 'https://syllabplus.com/');?>"><img style="border: 0px; float: right; height: 150px; width: 150px; margin: 20px 15px 15px 35px;" alt="SyllabPlus" src="<?php echo SYLLABPLUS_URL.'/images/ud-logo-150.png'; ?>"></a>

	<?php
		echo '<p>'.__('Super-charge and secure your WordPress site with our other top plugins:', 'syllabplus').'</p>';
	?>
	<p>
		<?php echo '<strong><a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/syllabplus-premium/").'" target="_blank">'.__('SyllabPlus Premium', 'syllabplus').'</a>: </strong>'.__("For personal support, the ability to copy sites, more storage destinations, encrypted backups for security, multiple backup destinations, better reporting, no adverts and plenty more, take a look at the premium version of SyllabPlus - the world's most popular backup plugin.", 'syllabplus');
		echo ' <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/comparison-syllabplus-free-syllabplus-premium/").'" target="_blank">'.__('Compare with the free version', 'syllabplus').'</a> / <a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/shop/syllabplus-premium/").'" target="_blank">'.__('Go to the shop.', 'syllabplus').'</a>';
	?>
	</p>
	<p>
		<?php echo '<strong><a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/syllabcentral/").'" target="_blank">'.__('SyllabCentral', 'syllabplus').'</a> </strong>'.__('is a highly efficient way to manage, update and backup multiple websites from one place.', 'syllabplus'); ?>
	</p>
	<p>
		<?php echo '<strong><a href="https://getwpo.com" target="_blank">WP-Optimize</a>: </strong>'.__('Makes your site fast and efficient. It cleans the database, compresses images and caches pages for ultimate speed.', 'syllabplus'); ?>
	</p>
	<p>
		<?php echo '<strong><a href="'.apply_filters('syllabplus_com_link', "https://syllabplus.com/newsletter-signup").'" target="_blank">'.__('Free Newsletter', 'syllabplus').'</a>: </strong>'.__('SyllabPlus news, high-quality training materials for WordPress developers and site-owners, and general WordPress news. You can de-subscribe at any time.', 'syllabplus'); ?>
	</p>
	<p>
		<?php echo '<strong>'.__('More quality plugins', 'syllabplus').' :</strong>';?>
		<a href="https://www.simbahosting.co.uk/s3/shop/" target="_blank"><?php echo __('Premium WooCommerce plugins', 'syllabplus').'</a> | <a href="https://wordpress.org/plugins/two-factor-authentication/" target="_blank">'.__('Free two-factor security plugin', 'syllabplus');?></a>
	</p>
	<div style="float:right;"><a href="#>" onclick="jQuery('#syllab-dashnotice').slideUp(); jQuery.post(ajaxurl, {action: 'syllab_ajax', subaction: 'dismissdashnotice', nonce: '<?php echo wp_create_nonce('syllabplus-credentialtest-nonce');?>' });"><?php printf(__('Dismiss (for %s months)', 'syllabplus'), 12); ?></a></div>
	<p>&nbsp;</p>
</div>
