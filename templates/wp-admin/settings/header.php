<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>

<div class="wrap" id="syllab-wrap">

	<h1><?php echo esc_html($syllabplus->plugin_title); ?></h1>
	<div class="syllabplus-top-menu">
		<a href="<?php echo apply_filters('syllabplus_com_link', "https://syllab.io/");?>" target="_blank">SylLab System</a> | 
		<?php _e('Version', 'syllabplus');?>: 1.0.2</span>
	</div>