<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No direct access allowed');

if (!empty($prefix)) echo $prefix.' ';
echo $title.': ';

echo empty($text_plain) ? $text : $text_plain;

if (!empty($discount_code)) echo $discount_code.' ';

// if (isset($text2)) {
// echo "\r\n\r\n" . $text2 . "\r\n\r\n";
// }

if (!empty($button_link) && !empty($button_meta)) {

	echo ' ';

	$link = apply_filters('syllabplus_com_link', $button_link);

	if ('syllabcentral' == $button_meta) {
		_e('Get SyllabCentral', 'syllabplus');
	} elseif ('review' == $button_meta) {
		_e('Review SyllabPlus', 'syllabplus');
	} elseif ('syllabplus' == $button_meta) {
		_e('Get Premium', 'syllabplus');
	} elseif ('signup' == $button_meta) {
		_e('Sign up', 'syllabplus');
	} elseif ('go_there' == $button_meta) {
		_e('Go there', 'syllabplus');
	} else {
		_e('Read more', 'syllabplus');
	}

	echo ' - '.esc_html($link);
	echo "\r\n";
	
}
