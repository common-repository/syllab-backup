<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('ABSPATH')) die('No direct access.');

class Syllab_Restorer_Skin extends Syllab_Restorer_Skin_Main {

	public function feedback($string, ...$args) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ellipsisFound -- spread operator is not supported in PHP < 5.5 but WP 5.3 supports PHP 5.6 minimum
		parent::syllab_feedback($string);
	}
}
