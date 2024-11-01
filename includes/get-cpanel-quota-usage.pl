#The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
#The other contributors and developers of the [Source code] cited here (Version 1.11.3):
#https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

#!/usr/local/bin/perl

use strict;
use Env qw(SYLLABPLUSKEY);

if ($SYLLABPLUSKEY ne 'syllabplus') { die('Error'); }
BEGIN { unshift @INC, '/usr/local/cpanel'; }

use Cpanel::Quota ();

# Used, limit, remain, files used, files limit, files remain
my @homesize = ( Cpanel::Quota::displayquota( { 'bytes' => 1, 'include_sqldbs' => 1, 'include_mailman' => 1, }));
print 'RESULT: '.join(" ", @homesize)."\n";
