<?php 
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed'); ?>

<h2 class="nav-tab-wrapper">
<?php
foreach ($main_tabs as $tab_slug => $tab_label) {
	$tab_slug_as_attr = esc_attr(sanitize_title($tab_slug));
?>
	<a class="nav-tab <?php if ($tabflag == $tab_slug) echo 'nav-tab-active'; ?>" id="syllab-navtab-<?php echo $tab_slug_as_attr;?>" href="<?php echo SyllabPlus::get_current_clean_url();?>#syllab-navtab-<?php echo $tab_slug_as_attr;?>-content" ><?php echo esc_html($tab_label);?></a>
<?php
}
?>
</h2>
