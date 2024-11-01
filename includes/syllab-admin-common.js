/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/
*/
/**
 * Send an action over AJAX. A wrapper around jQuery.ajax. In future, all consumers can be reviewed to simplify some of the options, where there is historical cruft.
 * N.B. syllab_iframe_modal() below uses the AJAX URL for the iframe's src attribute
 *
 * @param {string}   action   - the action to send
 * @param {*}        data     - data to send
 * @param {Function} callback - will be called with the results
 * @param {object}   options  -further options. Relevant properties include:
 * - [json_parse=true] - whether to JSON parse the results
 * - [alert_on_error=true] - whether to show an alert box if there was a problem (otherwise, suppress it)
 * - [action='syllab_ajax'] - what to send as the action parameter on the AJAX request (N.B. action parameter to this function goes as the 'subaction' parameter on the AJAX request)
 * - [nonce=syllab_credentialtest_nonce] - the nonce value to send.
 * - [nonce_key='nonce'] - the key value for the nonce field
 * - [timeout=null] - set a timeout after this number of seconds (or if null, none is set)
 * - [async=true] - control whether the request is asynchronous (almost always wanted) or blocking (would need to have a specific reason)
 * - [type='POST'] - GET or POST
 */
function syllab_send_command(action, data, callback, options) {
	
	default_options = {
		json_parse: true,
		alert_on_error: true,
		action: 'syllab_ajax',
		nonce: syllab_credentialtest_nonce,
		nonce_key: 'nonce',
		timeout: null,
		async: true,
		type: 'POST'
	}
	
	if ('undefined' === typeof options) options = {};

	for (var opt in default_options) {
		if (!options.hasOwnProperty(opt)) { options[opt] = default_options[opt]; }
	}
	
	var ajax_data = {
		action: options.action,
		subaction: action,
	};
	
	ajax_data[options.nonce_key] = options.nonce;
	
	// TODO: Once all calls are routed through here, change the listener in admin.php to always take the data from the 'data' attribute, instead of in the naked $_POST/$_GET
	if (typeof data == 'object') {
		for (var attrname in data) { ajax_data[attrname] = data[attrname]; }
	} else {
		ajax_data.action_data = data;
	}
	
	var ajax_opts = {
		type: options.type,
		url: ajaxurl,
		data: ajax_data,
		success: function(response, status) {
			if (options.json_parse) {
				try {
					var resp = ud_parse_json(response);
				} catch (e) {
					if ('function' == typeof options.error_callback) {
						return options.error_callback(response, e, 502, resp);
					} else {
						console.log(e);
						console.log(response);
						if (options.alert_on_error) { alert(syllablion.unexpectedresponse+' '+response); }
						return;
					}
				}
				if (resp.hasOwnProperty('fatal_error')) {
					if ('function' == typeof options.error_callback) {
						// 500 is internal server error code
						return options.error_callback(response, status, 500, resp);
					} else {
						console.error(resp.fatal_error_message);
						if (options.alert_on_error) { alert(resp.fatal_error_message); }
						return false;
					}
				}
				if ('function' == typeof callback) callback(resp, status, response);
			} else {
				if ('function' == typeof callback) callback(response, status);
			}
		},
		error: function(response, status, error_code) {
			if ('function' == typeof options.error_callback) {
				options.error_callback(response, status, error_code);
			} else {
				console.log("syllab_send_command: error: "+status+" ("+error_code+")");
				console.log(response);
			}
		},
		dataType: 'text',
		async: options.async
	};
	
	if (null != options.timeout) { ajax_opts.timeout = options.timeout; }
	
	jQuery.ajax(ajax_opts);
	
}

/**
 * Opens the dialog box for confirmation of whether to delete a backup, plus options if relevant
 *
 * @param {string}  key        - The UNIX timestamp of the backup
 * @param {string}  nonce      - The backup job ID
 * @param {boolean} showremote - Whether or not to show the "also delete from remote storage?" checkbox
 */
function syllab_delete(key, nonce, showremote) {
	jQuery('#syllab_delete_timestamp').val(key);
	jQuery('#syllab_delete_nonce').val(nonce);
	if (showremote) {
		jQuery('#syllab-delete-remote-section, #syllab_delete_remote').prop('disabled', false).show();
	} else {
		jQuery('#syllab-delete-remote-section, #syllab_delete_remote').hide().attr('disabled','disabled');
	}
	if (key.indexOf(',') > -1) {
		jQuery('#syllab_delete_question_singular').hide();
		jQuery('#syllab_delete_question_plural').show();
	} else {
		jQuery('#syllab_delete_question_plural').hide();
		jQuery('#syllab_delete_question_singular').show();
	}
	jQuery('#syllab-delete-modal').dialog('open');
}

function syllab_remote_storage_tab_activation(the_method){
	jQuery('.syllabplusmethod').hide();
	jQuery('.remote-tab').data('active', false);
	jQuery('.remote-tab').removeClass('nav-tab-active');
	jQuery('.syllabplusmethod.'+the_method).show();
	jQuery('.remote-tab-'+the_method).data('active', true);
	jQuery('.remote-tab-'+the_method).addClass('nav-tab-active');
}

/**
 * Set the email report's setting to a different interface when email storage is selected
 *
 * @param {boolean} value True to set the email report setting to another interface, false otherwise
 */
function set_email_report_storage_interface(value) {
	jQuery('#cb_not_email_storage_label').css('display', true === value ? 'none' : 'inline');
	jQuery('#cb_email_storage_label').css('display', true === value ? 'inline' : 'none');
	if (true === value) {
		jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon input#syllab_email').on('click', function(e) {
			return false;
		});
	} else {
		jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon input#syllab_email').prop("onclick", null).off("click");
	}
	if (!jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon input#syllab_email').is(':checked')) {
		jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon input#syllab_email').prop('checked', value);
	}
	jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon input#syllab_email').prop('disabled', value);

	var syllab_email = jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon input#syllab_email').val();

	jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon label.email_report input[type="hidden"]').remove();
	if (true === value) {
		jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon label.email_report input#syllab_email').after('<input type="hidden" name="syllab_email" value="'+syllab_email+'">');
	}
}

/**
 * Check how many cron jobs are overdue, and display a message if it is several (as determined by the back-end)
 */
function syllab_check_overduecrons() {
	syllab_send_command('check_overdue_crons', null, function(response) {
		if (response && response.hasOwnProperty('m') && Array.isArray(response.m)) {
			for (var i in response.m) {
				jQuery('#syllab-insert-admin-warning').append(response.m[i]);
			}
		}
	}, { alert_on_error: false });
}

function syllab_remote_storage_tabs_setup() {
	
	var anychecked = 0;
	var set = jQuery('.syllab_servicecheckbox:checked');
	
	jQuery(set).each(function(ind, obj) {
		var ser = jQuery(obj).val();
		console.log('111111111');
		console.log(ser);
		
		if (jQuery(obj).attr('id') != 'syllab_servicecheckbox_none') {
			anychecked++;
		}
		
		jQuery('.remote-tab-'+ser).show();
		if (ind == jQuery(set).length-1) {
			syllab_remote_storage_tab_activation(ser);
		}
	});
	
	if (anychecked > 0) {
		jQuery('.syllabplusmethod.none').hide();
		jQuery('#remote_storage_tabs').show();
	} else {
		jQuery('#remote_storage_tabs').hide();
	}
	
	// To allow labelauty remote storage buttons to be used with keyboard
	jQuery(document).on('keyup', function(event) {
		if (32 === event.keyCode || 13 === event.keyCode) {
			if (jQuery(document.activeElement).is("input.labelauty + label")) {
				var for_box = jQuery(document.activeElement).attr("for");
				if (for_box) {
					jQuery("#"+for_box).trigger('change');
				}
			}
		}
	});
	
	jQuery('.syllab_servicecheckbox').on('change', function() {
		var sclass = jQuery(this).attr('id');
		if ('syllab_servicecheckbox_' == sclass.substring(0,24)) {
			var serv = sclass.substring(24);
			if (null != serv && '' != serv) {
				if (jQuery(this).is(':checked')) {
					anychecked++;
					jQuery('.remote-tab-'+serv).fadeIn();
					console.log('2222222');
		            console.log(serv);
					syllab_remote_storage_tab_activation(serv);
					if (jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon').length && 'email' === serv) set_email_report_storage_interface(true);
				} else {
					anychecked--;
					jQuery('.remote-tab-'+serv).hide();
					// Check if this was the active tab, if yes, switch to another
					if (jQuery('.remote-tab-'+serv).data('active') == true) {
						syllab_remote_storage_tab_activation(jQuery('.remote-tab:visible').last().attr('name'));
					}
					if (jQuery('#syllab-navtab-settings-content #syllab_report_row_no_addon').length && 'email' === serv) set_email_report_storage_interface(false);
				}
			}
		}

		if (anychecked <= 0) {
			jQuery('.syllabplusmethod.none').fadeIn();
			jQuery('#remote_storage_tabs').hide();
		} else {
			jQuery('.syllabplusmethod.none').hide();
			jQuery('#remote_storage_tabs').show();
		}
	});
	
	// Add stuff for free version
	jQuery('.syllab_servicecheckbox:not(.multi)').on('change', function() {
		set_email_report_storage_interface(false);
		var svalue = jQuery(this).attr('value');
		if (jQuery(this).is(':not(:checked)')) {
			jQuery('.syllabplusmethod.'+svalue).hide();
			jQuery('.syllabplusmethod.none').fadeIn();
		} else {
			jQuery('.syllab_servicecheckbox').not(this).prop('checked', false);
			if ('email' === svalue) {
				set_email_report_storage_interface(true);
			}
		}
	});
	
	var servicecheckbox = jQuery('.syllab_servicecheckbox');
	if (typeof servicecheckbox.labelauty === 'function') {
		servicecheckbox.labelauty();
		var $vault_label = jQuery('label[for=syllab_servicecheckbox_syllabvault]');
		var $vault_info = jQuery('<div class="slp-info"><span class="info-trigger">?</span><div class="info-content-wrapper"><div class="info-content">'+syllablion.syllabvault_info+'</div></div></div>');
		//$vault_label.append($vault_info);
	}
	
}

/**
 * Carries out a remote storage test
 *
 * @param {string}   method          - The identifier for the remote storage
 * @param {callback} result_callback - A callback function to be called with the result
 * @param {string}   instance_id     - The particular instance (if any) of the remote storage to be tested (for methods supporting multiple instances)
 */
function syllab_remote_storage_test(method, result_callback, instance_id) {
	
	var $the_button;
	var settings_selector;
		
	if (instance_id) {
		$the_button = jQuery('#syllab-'+method+'-test-'+instance_id);
		settings_selector = '.syllabplusmethod.'+method+'-'+instance_id;
	} else {
		$the_button = jQuery('#syllab-'+method+'-test');
		settings_selector = '.syllabplusmethod.'+method;
	}
	
	var method_label = $the_button.data('method_label');
	
	$the_button.html(syllablion.testing_settings.replace('%s', method_label));
	
	var data = {
		method: method
	};
	
	// Add the other items to the data object. The expert mode settings are for the generic SSL options.
	jQuery('#syllab-navtab-settings-content '+settings_selector+' input[data-syllab_settings_test], #syllab-navtab-settings-content .expertmode input[data-syllab_settings_test]').each(function(index, item) {
		var item_key = jQuery(item).data('syllab_settings_test');
		var input_type = jQuery(item).attr('type');
		if (!item_key) { return; }
		if (!input_type) {
			console.log("SyllabPlus: settings test input item with no type found");
			console.log(item);
			// A default
			input_type = 'text';
		}
		var value = null;
		if ('checkbox' == input_type) {
			value = jQuery(item).is(':checked') ? 1 : 0;
		} else if ('text' == input_type || 'password' == input_type || 'hidden' == input_type) {
			value = jQuery(item).val();
		} else {
			console.log("SyllabPlus: settings test input item with unrecognised type ("+input_type+") found");
			console.log(item);
		}
		data[item_key] = value;
	});
	// Data from any text areas or select drop-downs
	jQuery('#syllab-navtab-settings-content '+settings_selector+' textarea[data-syllab_settings_test], #syllab-navtab-settings-content '+settings_selector+' select[data-syllab_settings_test]').each(function(index, item) {
		var item_key = jQuery(item).data('syllab_settings_test');
		data[item_key] = jQuery(item).val();
	});

	syllab_send_command('test_storage_settings', data, function(response, status) {
		$the_button.html(syllablion.test_settings.replace('%s', method_label));
		if ('undefined' !== typeof result_callback && false != result_callback) {
			result_callback = result_callback.call(this, response, status, data);
		}
		if ('undefined' !== typeof result_callback && false === result_callback) {
			alert(syllablion.settings_test_result.replace('%s', method_label)+' '+response.output);
			if (response.hasOwnProperty('data')) {
				console.log(response.data);
			}
		}
	}, { error_callback: function(response, status, error_code, resp) {
				$the_button.html(syllablion.test_settings.replace('%s', method_label));
				if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
			console.error(resp.fatal_error_message);
			alert(resp.fatal_error_message);
				} else {
			var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
			console.log(error_message);
			alert(error_message);
			console.log(response);
				}
			}
		});
}

function backupnow_whichfiles_checked(onlythesefileentities){
	jQuery('#backupnow_includefiles_moreoptions input[type="checkbox"]').each(function(index) {
		if (!jQuery(this).is(':checked')) { return; }
		var name = jQuery(this).attr('name');
		if (name.substring(0, 16) != 'syllab_include_') { return; }
		var entity = name.substring(16);
		if (onlythesefileentities != '') { onlythesefileentities += ','; }
		onlythesefileentities += entity;
	});
// console.log(onlythesefileentities);
	return onlythesefileentities;
}

/**
 * A method to get all the selected table values from the backup now modal
 *
 * @param {string} onlythesetableentities an empty string to append values to
 *
 * @return {string} a string that contains the values of all selected table entities and the database the belong to
 */
function backupnow_whichtables_checked(onlythesetableentities){
	var send_list = false;
	jQuery('#backupnow_database_moreoptions .syllab_db_entity').each(function(index) {
		if (!jQuery(this).is(':checked')) { send_list = true; return; }
		if (jQuery(this).is(':checked') && jQuery(this).data('non_wp_table')) { send_list = true; return; }
	});

	onlythesetableentities = jQuery("input[name^='syllab_include_tables_']").serializeArray();

	if (send_list) {
		return onlythesetableentities;
	} else {
		return true;
	}
}

function syllab_deleteallselected() {
	var howmany = 0;
	var remote_exists = 0;
	var key_all = '';
	var nonce_all = '';
	var remote_all = 0;
	jQuery('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row.backuprowselected').each(function(index) {
		howmany++;
		var nonce = jQuery(this).data('nonce');
		if (nonce_all) { nonce_all += ','; }
		nonce_all += nonce;
		var key = jQuery(this).data('key');
		if (key_all) { key_all += ','; }
		key_all += key;
		var has_remote = jQuery(this).find('.syllabplus-remove').data('hasremote');
		if (has_remote) remote_all++;
	});
	syllab_delete(key_all, nonce_all, remote_all);
}

/**
 * Open main tab which is given as argument
 *
 * @param {string} active_tab_key A tab key which you would like to open
 */
function syllab_open_main_tab(active_tab_key) {
	syllablion.main_tabs_keys.forEach(function(tab_key) {
		if (active_tab_key == tab_key) {
			jQuery('#syllab-navtab-' + tab_key + '-content').show();
			jQuery('#syllab-navtab-' + tab_key).addClass('nav-tab-active');
		} else {
			jQuery('#syllab-navtab-' + tab_key + '-content').hide();
			jQuery('#syllab-navtab-' + tab_key).removeClass('nav-tab-active');
		}
		syllab_console_focussed_tab = active_tab_key;
	});
}

/**
 * Open an existing backups tab
 *
 * @param {Boolean} toggly Whether switch on syllab_historytimer or not
 */
function syllab_openrestorepanel(toggly) {
	// jQuery('.download-backups').slideDown(); syllab_historytimertoggle(1); jQuery('html,body').animate({scrollTop: jQuery('#syllab_lastlogcontainer').offset().top},'slow');
	syllab_historytimertoggle(toggly);
	syllab_open_main_tab('backups');
}

function syllab_delete_old_dirs() {
	return true;
}

function syllab_initiate_restore(whichset) {
	jQuery('#syllab-navtab-backups-content .syllab_existing_backups button[data-backup_timestamp="'+whichset+'"]').trigger('click');
}

function syllab_restore_setoptions(entities) {
	var howmany = 0;
	jQuery('input[name="syllab_restore[]"]').each(function(x,y) {
		var entity = jQuery(y).val();
		var epat = entity+'=([0-9,]+)';
		var eregex = new RegExp(epat);
		var ematch = entities.match(eregex);
		if (ematch) {
			jQuery(y).prop('disabled', false).data('howmany', ematch[1]).parent().show();
			howmany++;
			if ('db' == entity) { howmany += 4.5;}
			if (jQuery(y).is(':checked')) {
				// This element may or may not exist. The purpose of explicitly calling show() is that Firefox, when reloading (including via forwards/backwards navigation) will remember checkbox states, but not which DOM elements were showing/hidden - which can result in some being hidden when they should be shown, and the user not seeing the options that are/are not checked.
				jQuery('#syllab_restorer_'+entity+'options').show();
			}
		} else {
			jQuery(y).attr('disabled','disabled').parent().hide();
		}
	});
	var cryptmatch = entities.match(/dbcrypted=1/);
	if (cryptmatch) {
		jQuery('#syllab_restore_db').data('encrypted', 1);
		jQuery('.syllab_restore_crypteddb').show();
	} else {
		jQuery('#syllab_restore_db').data('encrypted', 0);
		jQuery('.syllab_restore_crypteddb').hide();
	}
	jQuery('#syllab_restore_db').trigger('change');
	var dmatch = entities.match(/meta_foreign=([12])/);
	if (dmatch) {
		jQuery('#syllab_restore_meta_foreign').val(dmatch[1]);
	} else {
		jQuery('#syllab_restore_meta_foreign').val('0');
	}
}

/**
 * Open the 'Backup Now' dialog box
 *
 * @param {string} type - the backup type; either "new" or "incremental"
 */
function syllab_backup_dialog_open(type) {
	
	type = ('undefined' === typeof type) ? 'new' : type;

	if (0 == jQuery('#syllabplus_incremental_backup_link').data('incremental') && 'incremental' == type) {
		jQuery('#syllab-backupnow-modal .incremental-free-only').show();
		type = 'new';
	} else {
		jQuery('#syllab-backupnow-modal .incremental-backups-only, #syllab-backupnow-modal .incremental-free-only').hide();
	}
	
	jQuery('#backupnow_includefiles_moreoptions').hide();
	if (!syllab_settings_form_changed || window.confirm(syllablion.unsavedsettingsbackup)) {
		jQuery('#backupnow_label').val('');
		if ('incremental' == type) {
			update_file_entities_checkboxes(true, impossible_increment_entities);
			jQuery('#backupnow_includedb').prop('checked', false);
			jQuery('#backupnow_includefiles').prop('checked', true);
			jQuery('#backupnow_includefiles_label').text(syllablion.files_incremental_backup);
			jQuery('#syllab-backupnow-modal .new-backups-only').hide();
			jQuery('#syllab-backupnow-modal .incremental-backups-only').show();
		} else {
			update_file_entities_checkboxes(false, impossible_increment_entities);
			jQuery('#backupnow_includedb').prop('checked', true);
			jQuery('#backupnow_includefiles_label').text(syllablion.files_new_backup);
			jQuery('#syllab-backupnow-modal .new-backups-only').show();
			jQuery('#syllab-backupnow-modal .incremental-backups-only').hide();
		}
		jQuery('#syllab-backupnow-modal').data('backup-type', type);
		jQuery('#syllab-backupnow-modal').dialog('open');
	}
}
/**
 * Open the 'Backup Now' dialog box
 *
 * @param {string} type - the backup type; either "new" or "incremental"
 */
/**
 * This function will enable and disable the file entity options depending on what entities increments can be added to and if this is a new backup or not.
 *
 * @param {boolean} incremental - a boolean to indicate if this is an incremental backup or not
 * @param {array}   entities    - an array of entities to disable
 */
function update_file_entities_checkboxes(incremental, entities) {
	if (incremental) {
		jQuery(entities).each(function (index, entity) {
			jQuery('#backupnow_files_syllab_include_' + entity).prop('checked', false);
			jQuery('#backupnow_files_syllab_include_' + entity).prop('disabled', true);
		});
	} else {
		jQuery('#backupnow_includefiles_moreoptions input[type="checkbox"]').each(function (index) {
			var name = jQuery(this).attr('name');
			if (name.substring(0, 16) != 'syllab_include_') { return; }
			var entity = name.substring(16);
			jQuery('#backupnow_files_syllab_include_' + entity).prop('disabled', false);
			if (jQuery('#syllab_include_' + entity).is(':checked')) {
				jQuery('#backupnow_files_syllab_include_' + entity).prop('checked', true);
			}
		});
	}
}

var onlythesefileentities = backupnow_whichfiles_checked('');
if ('' == onlythesefileentities) {
	jQuery("#backupnow_includefiles_moreoptions").show();
} else {
	jQuery("#backupnow_includefiles_moreoptions").hide();
}

var impossible_increment_entities;
var syllab_restore_stage = 1;
var lastlog_lastmessage = "";
var lastlog_lastdata = "";
var lastlog_jobs = "";
// var lastlog_sdata = { action: 'syllab_ajax', subaction: 'lastlog' };
var syllab_activejobs_nextupdate = (new Date).getTime() + 1000;
// Bits: main tab displayed (1); restore dialog open (uses downloader) (2); tab not visible (4)
var syllab_page_is_visible = 1;
var syllab_console_focussed_tab = syllablion.tab;
var php_max_input_vars = 0;
var skipped_db_scan = 0;

var syllab_settings_form_changed = false;
window.onbeforeunload = function(e) {
	if (syllab_settings_form_changed) return syllablion.unsavedsettings;
}

/**
 * N.B. This function works on both the UD settings page and elsewhere
 *
 * @param {boolean} firstload Check if this is first load
 */
function syllab_check_page_visibility(firstload) {
	if ('hidden' == document["visibilityState"]) {
		syllab_page_is_visible = 0;
	} else {
		syllab_page_is_visible = 1;
		if (1 !== firstload) {
			if (jQuery('#syllab-navtab-backups-content').length) {
				syllab_activejobs_update(true);
			}
		}
	};
}

// See http://caniuse.com/#feat=pagevisibility for compatibility (we don't bother with prefixes)
if (typeof document.hidden !== "undefined") {
	document.addEventListener('visibilitychange', function() {
syllab_check_page_visibility(0);}, false);
}

syllab_check_page_visibility(1);

var syllab_poplog_log_nonce;
var syllab_poplog_log_pointer = 0;
var syllab_poplog_lastscroll = -1;
var syllab_last_forced_jobid = -1;
var syllab_last_forced_resumption = -1;
var syllab_last_forced_when = -1;

var syllab_backupnow_nonce = '';
var syllab_activejobslist_backupnownonce_only = 0;
var syllab_inpage_hasbegun = 0;
var syllab_activejobs_update_timer;
var syllab_aborted_jobs = [];
var syllab_clone_jobs = [];
var temporary_clone_timeout;

// Manage backups table selection
var syllab_backups_selection = {};

// @codingStandardsIgnoreStart - to keep the doc blocks, as they're considered block comments by phpcs
(function($) {
	/**
	 * Toggle row seletion
	 *
	 * @param {HTMLDomElement|jQuery} el - row element
	 */
	syllab_backups_selection.toggle = function(el) {
		var $el = $(el);
		if ($el.is('.backuprowselected')) {
			this.deselect(el);
		} else {
			this.select(el);
		}
	};

	/**
	 * Select row
	 *
	 * @param {HTMLDomElement|jQuery} el - row element
	 */
	syllab_backups_selection.select = function(el) {
		$(el).addClass('backuprowselected');
		$(el).find('.backup-select input').prop('checked', true);
		this.checkSelectionStatus();
	};

	/**
	 * Deselect row
	 *
	 * @param {HTMLDomElement|jQuery} el - row element
	 */
	syllab_backups_selection.deselect = function(el) {
		$(el).removeClass('backuprowselected');
		$(el).find('.backup-select input').prop('checked', false);
		this.checkSelectionStatus();
	};

	/**
	 * Select all rows
	 */
	syllab_backups_selection.selectAll = function() {
		$('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').each(function(index, el) {
			syllab_backups_selection.select(el);
		})
	};

	/**
	 * Deselect all rows
	 */
	syllab_backups_selection.deselectAll = function() {
		$('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').each(function(index, el) {
			syllab_backups_selection.deselect(el);
		})
	};

	/**
	 * Actions after a row selection/deselection
	 */
	syllab_backups_selection.checkSelectionStatus = function() {
		var num_rows = $('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').length;
		var num_selected = $('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row.backuprowselected').length;
		// toggles actions upon seleted items
		if (num_selected > 0) {
			$('#ud_massactions').addClass('active');
			$('.js--deselect-all-backups, .js--delete-selected-backups').prop('disabled', false);
		} else {
			$('#ud_massactions').removeClass('active');
			$('.js--deselect-all-backups, .js--delete-selected-backups').prop('disabled', true);
		}
		// if all rows are selected, check the headind's checkbox
		if (num_rows === num_selected) {
			$('#cb-select-all').prop('checked', true);
		} else {
			$('#cb-select-all').prop('checked', false);
		}
		// if no backups, hide massaction
		if (!num_rows) {
			$('#ud_massactions').hide();
		} else {
			$('#ud_massactions').show();
		}
	}

	/**
	 * Multiple range selection
	 *
	 * @param {HTMLDomElement|jQuery} el - row element
	 */
	syllab_backups_selection.selectAllInBetween = function(el) {
		var idx_start = this.firstMultipleSelectionIndex, idx_end = el.rowIndex-1;
		if (this.firstMultipleSelectionIndex > el.rowIndex-1) {
			idx_start = el.rowIndex-1; idx_end = this.firstMultipleSelectionIndex;
		}
		for (i=idx_start; i<=idx_end; i++) {
			this.select($('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').eq(i));
		}
	}

	/**
	 * Multiple range selection event handler that gets executed when hovering the mouse over the row of existing backups. This function highlights the rows with color
	 */
	syllab_backups_selection.hightlight_backup_rows = function() {
		if ("undefined" === typeof syllab_backups_selection.firstMultipleSelectionIndex) return;
		if (!$(this).hasClass('range-selection') && !$(this).hasClass('backuprowselected')) $(this).addClass('range-selection');
		$(this).siblings().removeClass('range-selection');
		if (syllab_backups_selection.firstMultipleSelectionIndex+1 > this.rowIndex) {
			$(this).nextUntil('.syllab_existing_backups_row.range-selection-start').addClass('range-selection');
		} else if (syllab_backups_selection.firstMultipleSelectionIndex+1 < this.rowIndex) {
			$(this).prevUntil('.syllab_existing_backups_row.range-selection-start').addClass('range-selection');
		}
	}

	/**
	 * Multiple range selection event handler that gets executed when the user releases the ctrl+shift button, it also gets executed when the mouse pointer is moved out from the browser page
	 * This function clears all the highlighted rows and removes hover and mouseleave event handlers
	 */
	syllab_backups_selection.unregister_highlight_mode = function() {
		if ("undefined" === typeof syllab_backups_selection.firstMultipleSelectionIndex) return;
		delete syllab_backups_selection.firstMultipleSelectionIndex;
		$('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').removeClass('range-selection range-selection-start');
		$('#syllab-navtab-backups-content').off('mouseenter', '.syllab_existing_backups .syllab_existing_backups_row', this.hightlight_backup_rows);
		$('#syllab-navtab-backups-content').off('mouseleave', '.syllab_existing_backups .syllab_existing_backups_row', this.hightlight_backup_rows);
		$(document).off('mouseleave', this.unregister_highlight_mode);
	}

	/**
	 * Register mouseleave and hover event handlers for highlighting purposes
	 */
	syllab_backups_selection.register_highlight_mode = function() {
		$(document).on('mouseleave', syllab_backups_selection.unregister_highlight_mode);
		$('#syllab-navtab-backups-content').on('mouseenter', '.syllab_existing_backups .syllab_existing_backups_row', syllab_backups_selection.hightlight_backup_rows);
		$('#syllab-navtab-backups-content').on('mouseleave', '.syllab_existing_backups .syllab_existing_backups_row', syllab_backups_selection.hightlight_backup_rows);
	}
})(jQuery);
// @codingStandardsIgnoreEnd

/**
 * Setup migration sections
 */
function setup_migrate_tabs() {
	// sets up the section buttons
	jQuery('#syllab_migrate .syllab_migrate_widget_module_content').each(function(ind, el) {
		var title = jQuery(el).find('h3').first().html();
		var intro_container = jQuery('.syllab_migrate_intro');
		var button = jQuery('<button class="button button-primary button-hero" />').html(title).appendTo(intro_container);
		button.on('click', function(e) {
			e.preventDefault();
			jQuery(el).show();
			intro_container.hide();
		});
	});
}

/**
 * Run a backup with show modal with progress.
 *
 * @param {Function} success_callback   callback function after backup
 * @param {String}   onlythisfileentity csv list of file entities to be backed up
 * @param {Array}    extradata          any extra data to be added
 * @param {Integer}  backupnow_nodb      Indicate whether the database should be backed up. Valid values: 0, 1
 * @param {Integer}  backupnow_nofiles  Indicate whether any files should be backed up. Valid values: 0, 1
 * @param {Integer}  backupnow_nocloud  Indicate whether the backup should be uploaded to cloud storage. Valid values: 0, 1
 * @param {String}   label              An optional label to be added to a backup
 */
function syllab_backupnow_inpage_go(success_callback, onlythisfileentity, extradata, backupnow_nodb, backupnow_nofiles, backupnow_nocloud, label) {
	
	backupnow_nodb = ('undefined' === typeof backupnow_nodb) ? 0 : backupnow_nodb;
	backupnow_nofiles = ('undefined' === typeof backupnow_nofiles) ? 0 : backupnow_nofiles;
	backupnow_nocloud = ('undefined' === typeof backupnow_nocloud) ? 0 : backupnow_nocloud;
	label = ('undefined' === typeof label) ? syllablion.automaticbackupbeforeupdate : label;
	
	// N.B. This function should never be called on the SyllabPlus settings page - it is assumed we are elsewhere. So, it is safe to fake the console-focussing parameter.
	syllab_console_focussed_tab = 'backups';
	syllab_inpage_success_callback = success_callback;
	syllab_activejobs_update_timer = setInterval(function () {
		syllab_activejobs_update(false);
	}, 1250);
	var syllab_inpage_modal_buttons = {};
	var inpage_modal_exists = jQuery('#syllab-backupnow-inpage-modal').length;
	if (inpage_modal_exists) {
		jQuery('#syllab-backupnow-inpage-modal').dialog('option', 'buttons', syllab_inpage_modal_buttons);
	}
	jQuery('#syllab_inpage_prebackup').hide();
	if (inpage_modal_exists) {
		jQuery('#syllab-backupnow-inpage-modal').dialog('open');
	}
	jQuery('#syllab_inpage_backup').show();
	syllab_activejobslist_backupnownonce_only = 1;
	syllab_inpage_hasbegun = 0;
	syllab_backupnow_go(backupnow_nodb, backupnow_nofiles, backupnow_nocloud, onlythisfileentity, extradata, label, '');
}

function syllab_get_downloaders() {
	var downloaders = '';
	jQuery('.ud_downloadstatus .syllabplus_downloader, #ud_downloadstatus2 .syllabplus_downloader, #ud_downloadstatus3 .syllabplus_downloader').each(function(x,y) {
		var dat = jQuery(y).data('downloaderfor');
		if (typeof dat == 'object') {
			if (downloaders != '') { downloaders = downloaders + ':'; }
			downloaders = downloaders + dat.base + ',' + dat.nonce + ',' + dat.what + ',' + dat.index;
		}
	});
	return downloaders;
}

function syllab_poll_get_parameters() {
	
	var gdata = {
		downloaders: syllab_get_downloaders()
	}
	
	try {
		if (jQuery('#syllab-poplog').dialog('isOpen')) {
			gdata.log_fetch = 1;
			gdata.log_nonce = syllab_poplog_log_nonce;
			gdata.log_pointer = syllab_poplog_log_pointer
		}
	} catch (err) {
		console.log(err);
	}
	
	if (syllab_activejobslist_backupnownonce_only && typeof syllab_backupnow_nonce !== 'undefined' && '' != syllab_backupnow_nonce) {
		gdata.thisjobonly = syllab_backupnow_nonce;
	}

	if (0 !== jQuery('#syllabplus_ajax_restore_job_id').length) gdata.syllab_credentialtest_nonce = syllab_credentialtest_nonce;
	
	return gdata;
}

var syllabplus_activejobs_list_fatal_error_alert = true;
function syllab_activejobs_update(force) {
	
	var $ = jQuery;
	
	var timenow = (new Date).getTime();
	if (false == force && timenow < syllab_activejobs_nextupdate) { return; }
	syllab_activejobs_nextupdate = timenow + 5500;
	
	var gdata = syllab_poll_get_parameters();

	syllab_send_command('activejobs_list', gdata, function(resp, status, response_raw) {
		syllab_process_status_check(resp, response_raw, gdata);
	}, {
		type: 'GET',
		error_callback: function(response, status, error_code, resp) {
			if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				if (true === syllabplus_activejobs_list_fatal_error_alert) {
					syllabplus_activejobs_list_fatal_error_alert = false;
					alert(this.alert_done + ' ' +resp.fatal_error_message);
				}
			} else {
				var msg = (status == error_code) ? error_code : error_code+" ("+status+")";
				console.error(msg);
				console.log(response);
			}
			return false;
		}
	});
}

/**
 * Shows a modal on success
 *
 * @param {string|obj} args The message to display or an object of parameters
 */
function syllab_show_success_modal(args) {
	if ('string' == typeof args) {
		args = {
			message: args
		};
	}
	var data = jQuery.extend(
		{
			icon: 'yes',
			close: syllablion.close,
			message: '',
			classes: 'success'
		},
		args
	);
	jQuery.blockUI({
		css: {
			width: '300px',
			border: 'none',
			'border-radius': '10px',
			left: 'calc(50% - 150px)'
		},
		message: '<div class="syllab_success_popup '+data.classes+'"><span class="dashicons dashicons-'+data.icon+'"></span><div class="syllab_success_popup--message">'+data.message+'</div><button class="button syllab-close-overlay"><span class="dashicons dashicons-no-alt"></span>'+data.close+'</button></div>'
	});
	// close success popup
	setTimeout(jQuery.unblockUI, 5000);
	jQuery('.blockUI .syllab-close-overlay').on('click', function() {
		jQuery.unblockUI();
	})
}

/**
 * Opens a dialog window showing the requested (or latest) log file, plus an option to download it
 *
 * @param {string} backup_nonce - the nonce of the log to display, or empty for the latest one
 */
function syllab_popuplog(backup_nonce) {
		
		var loading_message = syllablion.loading_log_file;
		
		if (backup_nonce) { loading_message += ' (log.'+backup_nonce+'.txt)'; }
	
		jQuery('#syllab-poplog').dialog("option", "title", loading_message);
		jQuery('#syllab-poplog-content').html('<em>'+loading_message+' ...</em> ');
		jQuery('#syllab-poplog').dialog("open");
		
		syllab_send_command('get_log', backup_nonce, function(resp) {

			syllab_poplog_log_pointer = resp.pointer;
			syllab_poplog_log_nonce = resp.nonce;
			
			var download_url = '?page=syllabplus&action=downloadlog&force_download=1&syllabplus_backup_nonce='+resp.nonce;
			
			jQuery('#syllab-poplog-content').html(resp.log);
			
			var log_popup_buttons = {};
			log_popup_buttons[syllablion.downloadlogfile] = function() {
 window.location.href = download_url; };
			log_popup_buttons[syllablion.close] = function() {
 jQuery(this).dialog("close"); };
			
			// Set the dialog buttons: Download log, Close log
			jQuery('#syllab-poplog').dialog("option", "buttons", log_popup_buttons);
			jQuery('#syllab-poplog').dialog("option", "title", 'log.'+resp.nonce+'.txt');
			
			syllab_poplog_lastscroll = -1;
			
		}, { type: 'GET', timeout: 60000, error_callback: function(response, status, error_code, resp) {
				if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				jQuery('#syllab-poplog-content').append(resp.fatal_error_message);
				} else {
				var msg = (status == error_code) ? error_code : error_code+" ("+status+")";
				jQuery('#syllab-poplog-content').append(msg);
				console.log(response);
				}
			}
		});
}

function syllab_showlastbackup() {
	
	syllab_send_command('get_fragment', 'last_backup_html', function(resp) {
		
		response = resp.output;
		
		if (lastbackup_laststatus == response) {
			setTimeout(function() {
 syllab_showlastbackup(); }, 7000);
		} else {
			jQuery('#syllab_last_backup').html(response);
		}
		lastbackup_laststatus = response;
		
	}, { type: 'GET' });
	
}

var syllab_historytimer = 0;
var calculated_diskspace = 0;
var syllab_historytimer_notbefore = 0;
var syllab_history_lastchecksum = false;

function syllab_historytimertoggle(forceon) {
	if (!syllab_historytimer || forceon == 1) {
		syllab_updatehistory(0, 0);
		syllab_historytimer = setInterval(function() {
syllab_updatehistory(0, 0);}, 30000);
		if (!calculated_diskspace) {
			syllabplus_diskspace();
			calculated_diskspace = 1;
		}
	} else {
		clearTimeout(syllab_historytimer);
		syllab_historytimer = 0;
	}
}

/**
 * Update the HTML for the 'existing backups' table; optionally, after local/remote re-scanning.
 * Nothing is returned; any update necessary is performed directly on the DOM.
 *
 * @param {Integer} rescan	     - first, re-scan the local storage (0 or 1)
 * @param {Integer} remotescan   - first, re-scan the remote storage (you must also set rescan to 1 to use this)
 * @param {Integer} debug	     - if 1, then also request debugging information and log it to the console
 * @param {Integer} backup_count - the amount of backups we want to display
 */
function syllab_updatehistory(rescan, remotescan, debug, backup_count) {

	if ('undefined' != typeof syllab_restore_screen && syllab_restore_screen) return;

	if ('undefined' === typeof debug) {
		debug = jQuery('#syllab_debug_mode').is(':checked') ? 1 : 0;
	}

	var unixtime = Math.round(new Date().getTime() / 1000);
	
	if (1 == rescan || 1 == remotescan) {
		syllab_historytimer_notbefore = unixtime + 30;
	} else {
		if (unixtime < syllab_historytimer_notbefore && 'undefined' === typeof backup_count) {
			console.log("Update history skipped: "+unixtime.toString()+" < "+syllab_historytimer_notbefore.toString());
			return;
		}
	}

	if ('undefined' === typeof backup_count) {
		backup_count = jQuery('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').length;
	}
	
	
	if (rescan == 1) {
		if (remotescan == 1) {
			syllab_history_lastchecksum = false;
			jQuery('#syllab-navtab-backups-content .syllab_existing_backups').html('<p style="text-align:center;"><em>'+syllablion.rescanningremote+'</em></p>');
		} else {
			syllab_history_lastchecksum = false;
			jQuery('#syllab-navtab-backups-content .syllab_existing_backups').html('<p style="text-align:center;"><em>'+syllablion.rescanning+'</em></p>');
		}
	}
	
	var what_op = remotescan ? 'remotescan' : (rescan ? 'rescan' : false);
	
	var data = {
		operation: what_op,
		debug: debug,
		backup_count: backup_count,
	}
	
	syllab_send_command('rescan', data, function(resp) {
		if (resp.hasOwnProperty('logs_exist') && resp.logs_exist) {
			// Show the "most recently modified log" link, in case it was previously hidden (if there were no logs until now)
			jQuery('#syllab_lastlogmessagerow .syllab-log-link').show();
		}
		
		if (resp.hasOwnProperty('migrate_tab') && resp.migrate_tab) {
			if (!jQuery('#syllab-navtab-migrate').hasClass('nav-tab-active')) {
				jQuery('#syllab_migrate_tab_alt').html('');
				jQuery('#syllab_migrate').replaceWith(jQuery(resp.migrate_tab).find('#syllab_migrate'));
				setup_migrate_tabs();
			}
		}
		
		if (resp.hasOwnProperty('web_server_disk_space')) {
			if ('' == resp.web_server_disk_space) {
				console.log("SyllabPlus: web_server_disk_space is empty");
				if (jQuery('#syllab-navtab-backups-content .syllab-server-disk-space').length) {
					jQuery('#syllab-navtab-backups-content .syllab-server-disk-space').slideUp('slow',  function() {
						jQuery(this).remove();
					});
				}
			} else {
				if (jQuery('#syllab-navtab-backups-content .syllab-server-disk-space').length) {
					jQuery('#syllab-navtab-backups-content  .syllab-server-disk-space').replaceWith(resp.web_server_disk_space);
				} else {
					jQuery('#syllab-navtab-backups-content .syllab-disk-space-actions').prepend(resp.web_server_disk_space);
				}
			}
		}

		update_backupnow_modal(resp);
		
		if (resp.hasOwnProperty('backupnow_file_entities')) {
			impossible_increment_entities = resp.backupnow_file_entities;
		}
		
		if (resp.n != null) { jQuery('#syllab-existing-backups-heading').html(resp.n); }
		
		if (resp.t != null) {
			if (resp.cksum != null) {
				if (resp.cksum == syllab_history_lastchecksum) {
					// Avoid unnecessarily refreshing the HTML if the data is the same. This helps avoid resetting the DOM (annoying when debugging), and keeps user row selections.
					return;
				}
				syllab_history_lastchecksum = resp.cksum;
			}
			jQuery('#syllab-navtab-backups-content .syllab_existing_backups').html(resp.t);
			syllab_backups_selection.checkSelectionStatus();
			if (resp.data) {
				console.log(resp.data);
			}
		}
	});
}

/**
 * This function will check if the passed in response contains content for the backup now modal that needs updating on page
 *
 * @param {array} response - an array that may contain backupnow_modal content that needs updating
 */
function update_backupnow_modal(response) {
	if (response.hasOwnProperty('modal_afterfileoptions')) {
		jQuery('.backupnow_modal_afterfileoptions').html(response.modal_afterfileoptions);
	}
}

/**
 * Exclude entities hidden input field update
 *
 * @param {string} include_entity_name - backup entity name
 */
function syllab_exclude_entity_update(include_entity_name) {
	var exclude_entities = [];
	jQuery('#syllab_include_'+include_entity_name+'_exclude_container .syllab_exclude_entity_wrapper .syllab_exclude_entity_field').each(function() {
		var data_val = jQuery(this).data('val').toString().trim();
		if ('' != data_val) {
			exclude_entities.push(data_val);
		}
	});
	jQuery('#syllab_include_'+include_entity_name+'_exclude').val(exclude_entities.join(','));
}

/**
 * Check uniqueness of exclude rule in include_backup_file
 *
 * @param {string} exclude_rule - exclude rule
 * @param {string} include_backup_file - the backup file type on which the exclude_rule will be applied
 *
 * @return {boolean} true if exclude_rule is unique otherwise false
 */
function syllab_is_unique_exclude_rule(exclude_rule, include_backup_file) {
	existing_exclude_rules_str = jQuery('#syllab_include_'+include_backup_file+'_exclude').val();
	existing_exclude_rules = existing_exclude_rules_str.split(',');
	
	if (jQuery.inArray(exclude_rule, existing_exclude_rules) > -1) {
		alert(syllablion.duplicate_exclude_rule_error_msg)
		return false;
	} else {
		return true;
	}
}


var syllab_interval_week_val = false;
var syllab_interval_month_val = false;

function syllab_intervals_monthly_or_not(selector_id, now_showing) {
	var selector = '#syllab-navtab-settings-content #'+selector_id;
	var current_length = jQuery(selector+' option').length;
	var is_monthly = ('monthly' == now_showing) ? true : false;
	var existing_is_monthly = false;
	if (current_length > 10) { existing_is_monthly = true; }
	if (!is_monthly && !existing_is_monthly) {
		return;
	}
	if (is_monthly && existing_is_monthly) {
		if ('monthly' == now_showing) {
			// existing_is_monthly does not mean the same as now_showing=='monthly'. existing_is_monthly refers to the drop-down, not whether the drop-down is being displayed. We may need to add these words back.
			jQuery('.syllab_monthly_extra_words_'+selector_id).remove();
			jQuery(selector).before('<span class="syllab_monthly_extra_words_'+selector_id+'">'+syllablion.day+' </span>').after('<span class="syllab_monthly_extra_words_'+selector_id+'"> '+syllablion.inthemonth+' </span>');
		}
		return;
	}
	jQuery('.syllab_monthly_extra_words_'+selector_id).remove();
	if (is_monthly) {
		// Save the old value
		syllab_interval_week_val = jQuery(selector+' option:selected').val();
		jQuery(selector).html(syllablion.mdayselector).before('<span class="syllab_monthly_extra_words_'+selector_id+'">'+syllablion.day+' </span>').after('<span class="syllab_monthly_extra_words_'+selector_id+'"> '+syllablion.inthemonth+' </span>');
		var select_mday = (syllab_interval_month_val === false) ? 1 : syllab_interval_month_val;
		// Convert from day of the month (ordinal) to option index (starts at 0)
		select_mday = select_mday - 1;
		jQuery(selector+" option").eq(select_mday).prop('selected', true);
	} else {
		// Save the old value
		syllab_interval_month_val = jQuery(selector+' option:selected').val();
		jQuery(selector).html(syllablion.dayselector);
		var select_day = (syllab_interval_week_val === false) ? 1 : syllab_interval_week_val;
		jQuery(selector+" option").eq(select_day).prop('selected', true);
	}
}

function syllab_check_same_times() {
	var dbmanual = 0;
	var file_interval = jQuery('#syllab-navtab-settings-content .syllab_interval').val();
	if (file_interval == 'manual') {
// jQuery('#syllab_files_timings').css('opacity', '0.25');
		jQuery('#syllab-navtab-settings-content .syllab_files_timings').hide();
	} else {
// jQuery('#syllab_files_timings').css('opacity', 1);
		jQuery('#syllab-navtab-settings-content .syllab_files_timings').show();
	}
	
	if ('weekly' == file_interval || 'fortnightly' == file_interval || 'monthly' == file_interval) {
		syllab_intervals_monthly_or_not('syllab_startday_files', file_interval);
		jQuery('#syllab-navtab-settings-content #syllab_startday_files').show();
	} else {
		jQuery('.syllab_monthly_extra_words_syllab_startday_files').remove();
		jQuery('#syllab-navtab-settings-content #syllab_startday_files').hide();
	}
	
	var db_interval = jQuery('#syllab-navtab-settings-content .syllab_interval_database').val();
	if (db_interval == 'manual') {
		dbmanual = 1;
// jQuery('#syllab_db_timings').css('opacity', '0.25');
		jQuery('#syllab-navtab-settings-content .syllab_db_timings').hide();
	}
	
	if ('weekly' == db_interval || 'fortnightly' == db_interval || 'monthly' == db_interval) {
		syllab_intervals_monthly_or_not('syllab_startday_db', db_interval);
		jQuery('#syllab-navtab-settings-content #syllab_startday_db').show();
	} else {
		jQuery('.syllab_monthly_extra_words_syllab_startday_db').remove();
		jQuery('#syllab-navtab-settings-content #syllab_startday_db').hide();
	}
	
	if (db_interval == file_interval) {
// jQuery('#syllab_db_timings').css('opacity','0.25');
		jQuery('#syllab-navtab-settings-content .syllab_db_timings').hide();
// jQuery('#syllab_same_schedules_message').show();
		if (0 == dbmanual) {
			jQuery('#syllab-navtab-settings-content .syllab_same_schedules_message').show();
		} else {
			jQuery('#syllab-navtab-settings-content .syllab_same_schedules_message').hide();
		}
	} else {
		jQuery('#syllab-navtab-settings-content .syllab_same_schedules_message').hide();
		if (0 == dbmanual) {
// jQuery('#syllab_db_timings').css('opacity', '1');
			jQuery('#syllab-navtab-settings-content .syllab_db_timings').show();
		}
	}
}

// Visit the site in the background every 3.5 minutes - ensures that backups can progress if you've got the UD settings page open
if ('undefined' !== typeof syllab_siteurl) {
	setInterval(function() {
jQuery.get(syllab_siteurl+'/wp-cron.php');}, 210000);
}
	
function syllab_activejobs_delete(jobid) {
	syllab_aborted_jobs[jobid] = 1;
	jQuery('#syllab-jobid-'+jobid).closest('.syllab_row').addClass('deleting');
	syllab_send_command('activejobs_delete', jobid, function(resp) {
		var job_row = jQuery('#syllab-jobid-'+jobid).closest('.syllab_row');
		job_row.addClass('deleting');

		if (resp.ok == 'Y') {
			jQuery('#syllab-jobid-'+jobid).html(resp.m);
			job_row.remove();

			// inpage backup - Close modal if canceling backup
			if (jQuery('#syllab-backupnow-inpage-modal').dialog('isOpen')) jQuery('#syllab-backupnow-inpage-modal').dialog('close');

			syllab_show_success_modal({
				message: syllab_active_job_is_clone(jobid) ? syllablion.clone_backup_aborted : syllablion.backup_aborted,
				icon: 'no-alt',
				classes: 'warning'
			});
		} else if ('N' == resp.ok) {
			job_row.removeClass('deleting');
			alert(resp.m);
		} else {
			job_row.removeClass('deleting');
			alert(syllablion.unexpectedresponse);
			console.log(resp);
		}
	});
}

function syllabplus_diskspace_entity(key) {
	jQuery('#syllab_diskspaceused_'+key).html('<em>'+syllablion.calculating+'</em>');
	syllab_send_command('get_fragment', { fragment: 'disk_usage', data: key }, function(response) {
		jQuery('#syllab_diskspaceused_'+key).html(response.output);
	}, { type: 'GET' });
}

/**
 * Checks if the specified job is a clone
 *
 * @param {string} job_id The job ID
 *
 * @return {int}
 */
function syllab_active_job_is_clone(job_id) {
	return syllab_clone_jobs.filter(function(val) {
		return val == job_id;
	}).length;
}

/**
 * Open a modal with content fetched from an iframe
 *
 * @param {String} getwhat - the subaction parameter to pass to UD's AJAX handler
 * @param {String} title   - the title for the modal
 */
function syllab_iframe_modal(getwhat, title) {
	var width = 780;
	var height = 500;
	jQuery('#syllab-iframe-modal-innards').html('<iframe width="100%" height="430px" src="'+ajaxurl+'?action=syllab_ajax&subaction='+getwhat+'&nonce='+syllab_credentialtest_nonce+'"></iframe>');
	jQuery('#syllab-iframe-modal').dialog({
		title: title, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			jQuery(this).dialog('option', 'width', width),
			jQuery(this).dialog('option', 'minHeight', 260);
			if (jQuery(window).height() > height) {
				jQuery(this).dialog('option', 'height', height);
			} else {
				jQuery(this).dialog('option', 'height', jQuery(window).height()-30);
			}
		}
	}).dialog('open');
}

function syllab_html_modal(showwhat, title, width, height) {
	jQuery('#syllab-iframe-modal-innards').html(showwhat);
	var syllab_html_modal_buttons = {};
	if (width < 450) {
		syllab_html_modal_buttons[syllablion.close] = function() {
 jQuery(this).dialog("close"); };
	}
	jQuery('#syllab-iframe-modal').dialog({
		title: title, buttons: syllab_html_modal_buttons, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			jQuery(this).dialog('option', 'width', width),
			jQuery(this).dialog('option', 'minHeight', 260);
			if (jQuery(window).height() > height) {
				jQuery(this).dialog('option', 'height', height);
			} else {
				jQuery(this).dialog('option', 'height', jQuery(window).height()-30);
			}
		}
	}).dialog('open');
}

function syllabplus_diskspace() {
	jQuery('#syllab-navtab-backups-content .syllab_diskspaceused').html('<em>'+syllablion.calculating+'</em>');
	syllab_send_command('get_fragment', { fragment: 'disk_usage', data: 'syllab' }, function(response) {
		jQuery('#syllab-navtab-backups-content .syllab_diskspaceused').html(response.output);
	}, { type: 'GET' });
}
var lastlog_lastmessage = "";
function syllabplus_deletefromserver(timestamp, type, findex) {
	if (!findex) findex=0;
	var pdata = {
		stage: 'delete',
		timestamp: timestamp,
		type: type,
		findex: findex
	};
	syllab_send_command('syllab_download_backup', pdata, null, { action: 'syllab_download_backup', nonce: syllab_download_nonce, nonce_key: '_wpnonce' });
}

function syllabplus_downloadstage2(timestamp, type, findex) {
	location.href =ajaxurl+'?_wpnonce='+syllab_download_nonce+'&timestamp='+timestamp+'&type='+type+'&stage=2&findex='+findex+'&action=syllab_download_backup';
}

function syllabplus_show_contents(timestamp, type, findex) {
	var modal_content = '<div id="syllab_zip_files_container" class="hidden-in-syllabcentral" style="clear:left;"><div id="syllab_zip_info_container" class="syllab_jstree_info_container"><p><span id="syllab_zip_path_text">' + syllablion.zip_file_contents_info + '</span> - <span id="syllab_zip_size_text"></span></p>'+syllablion.browse_download_link+'</div><div id="syllab_zip_files_jstree_container"><input type="search" id="zip_files_jstree_search" name="zip_files_jstree_search" placeholder="' + syllablion.search + '"><div id="syllab_zip_files_jstree" class="syllab_jstree"></div></div></div>';

	syllab_html_modal(modal_content, syllablion.zip_file_contents, 780, 500);

	zip_files_jstree('zipbrowser', timestamp, type, findex);
}

/**
 * Creates the jstree and makes a call to the backend to dynamically get the tree nodes
 *
 * @param {string} entity     Entity for the jstree
 * @param {integer} timestamp Timestamp of the jstree
 * @param {string} type       Type of file to display in the JS tree
 * @param {array} findex      Index of Zip
 */
function zip_files_jstree(entity, timestamp, type, findex) {

	jQuery('#syllab_zip_files_jstree').jstree({
		"core": {
			"multiple": false,
			"data": function (nodeid, callback) {
				syllab_send_command('get_jstree_directory_nodes', {entity:entity, node:nodeid, timestamp:timestamp, type:type, findex:findex}, function(response) {
					if (response.hasOwnProperty('error')) {
						alert(response.error);
					} else {
						callback.call(this, response.nodes);
					}
				}, { error_callback: function(response, status, error_code, resp) {
						if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
						console.error(resp.fatal_error_message);
						jQuery('#syllab_zip_files_jstree').html('<p style="color:red; margin: 5px;">'+resp.fatal_error_message+'</p>');
						alert(resp.fatal_error_message);
						} else {
						var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
						jQuery('#syllab_zip_files_jstree').html('<p style="color:red; margin: 5px;">'+error_message+'</p>');
						console.log(error_message);
						alert(error_message);
						console.log(response);
						}
					}
				});
			},
			"error": function(error) {
				alert(error);
				console.log(error);
			},
		},
		"search": {
			"show_only_matches": true
		},
		"plugins": ["search", "sort"],
	});

	// Update modal title once tree loads
	jQuery('#syllab_zip_files_jstree').on('ready.jstree', function(e, data) {
		jQuery('#syllab-iframe-modal').dialog('option', 'title', syllablion.zip_file_contents + ': ' + data.instance.get_node('#').children[0])
	});

	// Search function for jstree, this will hide nodes that don't match the search
	var timeout = false;
	jQuery('#zip_files_jstree_search').on('keyup', function () {
		if (timeout) { clearTimeout(timeout); }
		timeout = setTimeout(function () {
			var value = jQuery('#zip_files_jstree_search').val();
			jQuery('#syllab_zip_files_jstree').jstree(true).search(value);
		}, 250);
	});

	// Detect change on the tree and update the input that has been marked as editing
	jQuery('#syllab_zip_files_jstree').on("changed.jstree", function (e, data) {
		jQuery('#syllab_zip_path_text').text(data.node.li_attr.path);
		
		if (data.node.li_attr.size) {
			jQuery('#syllab_zip_size_text').text(data.node.li_attr.size);
			jQuery('#syllab_zip_download_item').show();
		} else {
			jQuery('#syllab_zip_size_text').text('');
			jQuery('#syllab_zip_download_item').hide();
		}
	});

	jQuery('#syllab_zip_download_item').on('click', function(event) {
		
		event.preventDefault();
		
		var path = jQuery('#syllab_zip_path_text').text();

		syllab_send_command('get_zipfile_download', {path:path, timestamp:timestamp, type:type, findex:findex}, function(response) {
			if (response.hasOwnProperty('error')) {
				alert(response.error);
			} else if (response.hasOwnProperty('path')) {
				location.href =ajaxurl+'?_wpnonce='+syllab_download_nonce+'&timestamp='+timestamp+'&type='+type+'&stage=2&findex='+findex+'&filepath='+response.path+'&action=syllab_download_backup';
			} else {
				alert(syllablion.download_timeout);
			}
		}, { error_callback: function(response, status, error_code, resp) {
				if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				alert(resp.fatal_error_message);
				} else {
				var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
				console.log(error_message);
				alert(error_message);
				console.log(response);
				}
			}
		});
	});
}

/**
 * This function will clean up the syllab downloader UI
 *
 * @param {object} item - the object pressed in the UI
 * @param {string} what - the file entity
 */
function remove_syllab_downloader(item, what) {
	jQuery(item).closest('.syllabplus_downloader').fadeOut().remove();
	if (0 == jQuery('.syllabplus_downloader_container_'+what+' .syllabplus_downloader').length) jQuery('.syllabplus_downloader_container_'+what).remove();
}

/**
 * This function will prepare the downloader UI and kick of the request to download the file entities.
 *
 * @param {string}  base             - the base string for the id
 * @param {integer} backup_timestamp - the backup timestamp
 * @param {string}  what             - the file entity
 * @param {string}  whicharea        - the area we want to append the downloader
 * @param {string}  set_contents     - the contents we want to download
 * @param {string}  prettydate       - the pretty backup date
 * @param {boolean} async            - boolean to indicate if this is a async request or not
 */
function syllab_downloader(base, backup_timestamp, what, whicharea, set_contents, prettydate, async) {
	
	if (typeof set_contents !== "string") set_contents = set_contents.toString();

	jQuery('.ud_downloadstatus').show();

	var set_contents = set_contents.split(',');
	var prdate = (prettydate) ? prettydate : backup_timestamp;

	// Old-style, from when it was a form
	// var data = jQuery('#syllab-navtab-backups-content .uddownloadform_'+what+'_'+backup_timestamp+'_'+set_contents[i]).serialize();
	var nonce = jQuery('#syllab-navtab-backups-content .uddownloadform_'+what+'_'+backup_timestamp+'_'+set_contents[0]).data('wp_nonce').toString();
	
	if (!jQuery('.syllabplus_downloader_container_'+what).length) {
		jQuery(whicharea).append('<div class="syllabplus_downloader_container_' + what + ' postbox"></div>');
		jQuery('.syllabplus_downloader_container_' + what).append('<strong style="clear:left; padding: 8px; margin-top: 4px;">' + syllablion.download + ' ' + what + ' (' + prdate + '):</strong>');
	}

	for (var i = 0; i < set_contents.length; i++) {
		// Create somewhere for the status to be found
		var stid = base+backup_timestamp+'_'+what+'_'+set_contents[i];
		var stid_selector = '.'+stid;
		var show_index = parseInt(set_contents[i]); show_index++;
		var itext = (0 == set_contents[i]) ? '' : ' ('+show_index+')';
		if (!jQuery(stid_selector).length) {
			jQuery('.syllabplus_downloader_container_'+what).append('<div style="clear:left; padding: 8px; margin-top: 4px;" class="'+stid+' syllabplus_downloader"><button onclick="remove_syllab_downloader(this, \''+what+'\');" type="button" style="float:right; margin-bottom: 8px;" class="ud_downloadstatus__close" aria-label="Close"><span class="dashicons dashicons-no-alt"></span></button><strong>'+what+itext+'</strong>:<div class="raw">'+syllablion.begunlooking+'</div><div class="file '+stid+'_st"><div class="dlfileprogress" style="width: 0;"></div></div></div>');
			jQuery(stid_selector).data('downloaderfor', { base: base, nonce: backup_timestamp, what: what, index: set_contents[i] });
			setTimeout(function() {
					syllab_activejobs_update(true);
				},
			1500);
		}
		jQuery(stid_selector).data('lasttimebegan', (new Date).getTime());
	}

	// Now send the actual request to kick it all off
	async = async ? true : false;

	var data = {
		type: what,
		timestamp: backup_timestamp,
		findex: set_contents
	};

	var options = {
		action: 'syllab_download_backup',
		nonce_key: '_wpnonce',
		nonce: nonce,
		timeout: 10000,
		async: async
	}

	syllab_send_command('syllab_download_backup', data, function (response) {}, options);

	// We don't want the form to submit as that replaces the document
	return false;
}

/**
 * Parse JSON string, including automatically detecting unwanted extra input and skipping it
 *
 * @param {string}  json_mix_str - JSON string which need to parse and convert to object
 * @param {boolean} analyse		 - if true, then the return format will contain information on the parsing, and parsing will skip attempting to JSON.parse() the entire string (will begin with trying to locate the actual JSON)
 *
 * @throws SyntaxError|String (including passing on what JSON.parse may throw) if a parsing error occurs.
 *
 * @returns Mixed parsed JSON object. Will only return if parsing is successful (otherwise, will throw). If analyse is true, then will rather return an object with properties (mixed)parsed, (integer)json_start_pos and (integer)json_end_pos
 */
function ud_parse_json(json_mix_str, analyse) {

	analyse = ('undefined' === typeof analyse) ? false : true;
	
	// Just try it - i.e. the 'default' case where things work (which can include extra whitespace/line-feeds, and simple strings, etc.).
	if (!analyse) {
		try {
			var result = JSON.parse(json_mix_str);
			return result;
		} catch (e) {
			console.log('SyllabPlus: Exception when trying to parse JSON (1) - will attempt to fix/re-parse based upon first/last curly brackets');
			console.log(json_mix_str);
		}
	}

	var json_start_pos = json_mix_str.indexOf('{');
	var json_last_pos = json_mix_str.lastIndexOf('}');
	
	// Case where some php notice may be added after or before json string
	if (json_start_pos > -1 && json_last_pos > -1) {
		var json_str = json_mix_str.slice(json_start_pos, json_last_pos + 1);
		try {
			var parsed = JSON.parse(json_str);
			if (!analyse) { console.log('SyllabPlus: JSON re-parse successful'); }
			return analyse ? { parsed: parsed, json_start_pos: json_start_pos, json_last_pos: json_last_pos + 1 } : parsed;
		} catch (e) {
			console.log('SyllabPlus: Exception when trying to parse JSON (2) - will attempt to fix/re-parse based upon bracket counting');
			 
			var cursor = json_start_pos;
			var open_count = 0;
			var last_character = '';
			var inside_string = false;
			
			// Don't mistake this for a real JSON parser. Its aim is to improve the odds in real-world cases seen, not to arrive at universal perfection.
			while ((open_count > 0 || cursor == json_start_pos) && cursor <= json_last_pos) {
				
				var current_character = json_mix_str.charAt(cursor);
				
				if (!inside_string && '{' == current_character) {
					open_count++;
				} else if (!inside_string && '}' == current_character) {
					open_count--;
				} else if ('"' == current_character && '\\' != last_character) {
					inside_string = inside_string ? false : true;
				}
					
				last_character = current_character;
				cursor++;
			}
			console.log("Started at cursor="+json_start_pos+", ended at cursor="+cursor+" with result following:");
			console.log(json_mix_str.substring(json_start_pos, cursor));
			
			try {
				var parsed = JSON.parse(json_mix_str.substring(json_start_pos, cursor));
				console.log('SyllabPlus: JSON re-parse successful');
				return analyse ? { parsed: parsed, json_start_pos: json_start_pos, json_last_pos: cursor } : parsed;
			} catch (e) {
				// Throw it again, so that our function works just like JSON.parse() in its behaviour.
				throw e;
			}
		}
	}

	throw "SyllabPlus: could not parse the JSON";
	
}

// Catch HTTP errors if the download status check returns them
jQuery(document).ajaxError(function(event, jqxhr, settings, exception) {
	if (exception == null || exception == '') return;
	if (jqxhr.responseText == null || jqxhr.responseText == '') return;
	console.log("Error caught by SyllabPlus ajaxError handler (follows) for "+settings.url);
	console.log(exception);
	if (settings.url.search(ajaxurl) == 0) {
		// TODO subaction=downloadstatus is no longer used. This should be adjusted to the current set-up.
		if (settings.url.search('subaction=downloadstatus') >= 0) {
			var timestamp = settings.url.match(/timestamp=\d+/);
			var type = settings.url.match(/type=[a-z]+/);
			var findex = settings.url.match(/findex=\d+/);
			var base = settings.url.match(/base=[a-z_]+/);
			findex = (findex instanceof Array) ? parseInt(findex[0].substr(7)) : 0;
			type = (type instanceof Array) ? type[0].substr(5) : '';
			base = (base instanceof Array) ? base[0].substr(5) : '';
			timestamp = (timestamp instanceof Array) ? parseInt(timestamp[0].substr(10)) : 0;
			if ('' != base && '' != type && timestamp >0) {
				var stid = base+timestamp+'_'+type+'_'+findex;
				jQuery('.'+stid+' .raw').html('<strong>'+syllablion.error+'</strong> '+syllablion.servererrorcode);
			}
		} else if (settings.url.search('subaction=restore_alldownloaded') >= 0) {
			// var timestamp = settings.url.match(/timestamp=\d+/);
			jQuery('#syllab-restore-modal-stage2a').append('<br><strong>'+syllablion.error+'</strong> '+syllablion.servererrorcode+': '+exception);
		}
	}
});

function syllab_restorer_checkstage2(doalert) {
	// How many left?
	var stilldownloading = jQuery('#ud_downloadstatus2 .file').length;
	if (stilldownloading > 0) {
		if (doalert) { alert(syllablion.stilldownloading); }
		return;
	}
	// Allow pressing 'Restore' to proceed
	jQuery('.syllab-restore--next-step').prop('disabled', true);
	jQuery('#syllab-restore-modal-stage2a').html('<span class="dashicons dashicons-update rotate"></span> '+syllablion.preparing_backup_files);
	syllab_send_command('restore_alldownloaded', {
		timestamp: jQuery('#syllab_restore_timestamp').val(),
		restoreopts: jQuery('#syllab_restore_form').serialize()
	}, function(resp, status, data) {
		var info = null;
		jQuery('#syllab_restorer_restore_options').val('');
		jQuery('.syllab-restore--next-step').prop('disabled', false);
		try {
			// var resp = ud_parse_json(data);
			if (null == resp) {
				jQuery('#syllab-restore-modal-stage2a').html(syllablion.emptyresponse);
				return;
			}
			var report = resp.m;
			if (resp.w != '') {
				report = report + '<div class="notice notice-warning"><p><span class="dashicons dashicons-warning"></span> <strong>' + syllablion.warnings +'</strong></p>' + resp.w + '</div>';
			}
			if (resp.e != '') {
				report = report + '<div class="notice notice-error"><p><span class="dashicons dashicons-dismiss"></span> <strong>' + syllablion.errors+'</strong></p>' + resp.e + '</div>';
			} else {
				syllab_restore_stage = 3;
			}
			if (resp.hasOwnProperty('i')) {
				// Store the information passed back from the backup scan
				try {
					info = ud_parse_json(resp.i);
// if (info.hasOwnProperty('multisite') && info.multisite && info.hasOwnProperty('same_url') && info.same_url) {
					if (info.hasOwnProperty('addui')) {
						console.log("Further UI options are being displayed");
						var addui = info.addui;
						report += '<div id="syllab_restoreoptions_ui">'+addui+'</div>';
						if (typeof JSON == 'object' && typeof JSON.stringify == 'function') {
							// If possible, remove from the stored info, to prevent passing back potentially large amounts of unwanted data
							delete info.addui;
							resp.i = JSON.stringify(info);
						}
					}
					if (info.hasOwnProperty('php_max_input_vars')) {
						php_max_input_vars = parseInt(info.php_max_input_vars);
					}
					if (info.hasOwnProperty('skipped_db_scan')) {
						skipped_db_scan = parseInt(info.skipped_db_scan);
					}
				} catch (err) {
					console.log(err);
					console.log(resp);
				}
				jQuery('#syllab_restorer_backup_info').val(resp.i);
			} else {
				jQuery('#syllab_restorer_backup_info').val();
			}
			jQuery('#syllab-restore-modal-stage2a').html(report);
			jQuery('.syllab-restore--next-step').text(syllablion.restore);
			if (jQuery('#syllab-restore-modal-stage2a .syllab_select2').length > 0) {
				jQuery('#syllab-restore-modal-stage2a .syllab_select2').select2();
			}
		} catch (err) {
			console.log(data);
			console.log(err);
			jQuery('#syllab-restore-modal-stage2a').text(syllablion.jsonnotunderstood+' '+syllablion.errordata+": "+data).html();
		}
	}, { error_callback: function(response, status, error_code, resp) {
			if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				jQuery('#syllab-restore-modal-stage2a').html('<p style="color: red;">'+resp.fatal_error_message+'</p>');
				alert(resp.fatal_error_message);
			} else {
				var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
				jQuery('#syllab-restore-modal-stage2a').html('<p style="color: red;">'+error_message+'</p>');
				console.log(error_message);
				alert(error_message);
				console.log(response);
			}
		}
	});
}


function syllab_downloader_status(base, nonce, what, findex) {
	// Short-circuit. See previous versions for the old code.
	return;
}

function syllab_downloader_status_update(download_status, response_raw) {
	
	var cancel_repeat = 0;

	jQuery(download_status).each(function (x, dstatus) {
		if (dstatus.base == '') return;
		
		var stid = dstatus.base + dstatus.timestamp + '_' + dstatus.what + '_' + dstatus.findex;
		var stid_selector = '.' + stid;
		
		if (dstatus.e != null) {
			jQuery(stid_selector + ' .raw').html('<strong>' + syllablion.error + '</strong> ' + dstatus.e);
			console.log(dstatus);
		} else if (dstatus.p != null) {
			jQuery(stid_selector + '_st .dlfileprogress').width(dstatus.p + '%');
			// jQuery(stid_selector+'_st .dlsofar').html(Math.round(dstatus.s/1024));
			// jQuery(stid_selector+'_st .dlsize').html(Math.round(dstatus.t/1024));

			// Is a restart appropriate?
			// dstatus.a, if set, indicates that a) the download is incomplete and b) the value is the number of seconds since the file was last modified...
			if (dstatus.a != null && dstatus.a > 0) {
				var timenow = (new Date).getTime();
				var lasttimebegan = jQuery(stid_selector).data('lasttimebegan');
				// Remember that this is in milliseconds
				var sincelastrestart = timenow - lasttimebegan;
				if (dstatus.a > 90 && sincelastrestart > 60000) {
					console.log(dstatus.timestamp + " " + dstatus.what + " " + dstatus.findex + ": restarting download: file_age=" + dstatus.a + ", sincelastrestart_ms=" + sincelastrestart);
					jQuery(stid_selector).data('lasttimebegan', (new Date).getTime());

					var $original_button = jQuery('#syllab-navtab-backups-content .uddownloadform_' + dstatus.what + '_' + dstatus.timestamp + '_' + dstatus.findex);

					var data = {
						type: dstatus.what,
						timestamp: dstatus.timestamp,
						findex: dstatus.findex
					};

					var options = {
						action: 'syllab_download_backup',
						nonce_key: '_wpnonce',
						nonce: $original_button.data('wp_nonce').toString(),
						timeout: 10000
					};

					syllab_send_command('syllab_download_backup', data, function (response) {}, options);

					jQuery(stid_selector).data('lasttimebegan', (new Date).getTime());
				}
			}

			if (dstatus.m != null) {
				if (dstatus.p >= 100 && 'udrestoredlstatus_' == dstatus.base) {
					jQuery(stid_selector + ' .raw').html(dstatus.m);
					jQuery(stid_selector).fadeOut('slow', function () {
						remove_syllab_downloader(this, dstatus.what);
						syllab_restorer_checkstage2(0);
					});
				} else if (dstatus.p >= 100 && dstatus.base == 'udclonedlstatus_') {
					jQuery(stid_selector + ' .raw').html(dstatus.m);
					jQuery(stid_selector).fadeOut('slow', function () {
						remove_syllab_downloader(this, dstatus.what);
					});
				} else if (dstatus.p < 100 || dstatus.base != 'uddlstatus_') {
					jQuery(stid_selector + ' .raw').html(dstatus.m);
				} else {
					var file_ready_actions = syllablion.fileready + ' ' + syllablion.actions + ': \
				<button class="button" type="button" onclick="syllabplus_downloadstage2(\''+ dstatus.timestamp + '\', \'' + dstatus.what + '\', \'' + dstatus.findex + '\')\">' + syllablion.downloadtocomputer + '</button> \
				<button class="button" id="uddownloaddelete_'+ dstatus.timestamp + '_' + dstatus.what + '" type="button" onclick="syllabplus_deletefromserver(\'' + dstatus.timestamp + '\', \'' + dstatus.what + '\', \'' + dstatus.findex + '\')\">' + syllablion.deletefromserver + '</button>';

					if (dstatus.hasOwnProperty('can_show_contents') && dstatus.can_show_contents) {
						file_ready_actions += ' <button class="button" type="button" onclick="syllabplus_show_contents(\'' + dstatus.timestamp + '\', \'' + dstatus.what + '\', \'' + dstatus.findex + '\')\">' + syllablion.browse_contents + '</button>';
					}
					jQuery(stid_selector + ' .raw').html(file_ready_actions);
					jQuery(stid_selector + '_st').remove();
				}
			}
			// dlstatus_lastlog = response_raw;
		} else if (dstatus.m != null) {
			jQuery(stid_selector + ' .raw').html(dstatus.m);
		} else {
			jQuery(stid_selector + ' .raw').html(syllablion.jsonnotunderstood + ' (' + response_raw + ')');
			cancel_repeat = 1;
		}
	});

	return cancel_repeat;
}

/**
 * Function that sets up a ajax call to start a backup
 *
 * @param {Integer} backupnow_nodb            Indicate whether the database should be backed up: valid values are 0, 1
 * @param {Integer} backupnow_nofiles         Indicate whether any files should be backed up: valid values are 0, 1
 * @param {Integer} backupnow_nocloud         Indicate whether the backup should be uploaded to cloud storage: valid values are 0, 1
 * @param {String}  onlythesefileentities     A csv list of file entities to be backed up
 * @param {String}  onlythesetableentities    A csv list of table entities to be backed up
 * @param {Array}   extradata                 any extra data to be added
 * @param {String}  label                     A optional label to be added to a backup
 * @param {String}  only_these_cloud_services An array of remote sorage locations to be backed up to
 */
function syllab_backupnow_go(backupnow_nodb, backupnow_nofiles, backupnow_nocloud, onlythesefileentities, extradata, label, onlythesetableentities, only_these_cloud_services) {

	var params = {
		backupnow_nodb: backupnow_nodb,
		backupnow_nofiles: backupnow_nofiles,
		backupnow_nocloud: backupnow_nocloud,
		backupnow_label: label,
		extradata: extradata
	};
	
	if ('' != onlythesefileentities) {
		params.onlythisfileentity = onlythesefileentities;
	}

	if ('' != onlythesetableentities) {
		params.onlythesetableentities = onlythesetableentities;
	}

	if ('' != only_these_cloud_services) {
		params.only_these_cloud_services = only_these_cloud_services;
	}
	
	params.always_keep = (typeof extradata.always_keep !== 'undefined') ? extradata.always_keep : 0;
	delete extradata.always_keep;

	params.incremental = (typeof extradata.incremental !== 'undefined') ? extradata.incremental : 0;
	delete extradata.incremental;

	params.db_anon_all = (typeof extradata.db_anon_all !== 'undefined') ? extradata.db_anon_all : 0;
	delete extradata.db_anon_all;

	params.db_anon_non_staff = (typeof extradata.db_anon_non_staff !== 'undefined') ? extradata.db_anon_non_staff : 0;
	delete extradata.db_anon_non_staff;

	// Display Request start message
	if (!jQuery('.syllab_requeststart').length) {
		var requeststart_el = jQuery('<div class="syllab_requeststart" />').html('<span class="spinner"></span>'+syllablion.requeststart);
		requeststart_el.data('remove', false);
		setTimeout(
			function() {
				requeststart_el.data('remove', true);
			},
			3000
		);
		setTimeout(
			function() {
				requeststart_el.remove();
			},
			75000
		);
		jQuery('#syllab_activejobsrow').before(requeststart_el);
	}
	
	syllab_activejobslist_backupnownonce_only = 1;
	syllab_send_command('backupnow', params, function(resp) {
		if (resp.hasOwnProperty('error')) {
			jQuery('.syllab_requeststart').remove();
			alert(resp.error);
			return;
		}
		jQuery('#syllab_backup_started').html(resp.m);
		if (resp.hasOwnProperty('nonce')) {
			// Can't return it from this context
			syllab_backupnow_nonce = resp.nonce;
			console.log("SyllabPlus: ID of started job: "+syllab_backupnow_nonce);
		}
		setTimeout(function() {
			syllab_activejobs_update(true);}, 500);
	});
}

jQuery(function($) {
	
	// actioned When the checkout embed is complete
	$(document).on('slp/checkout/done', function(e, data) {
		if (data.hasOwnProperty('product') && 'syllabpremium' === data.product && 'complete' === data.status) {
			$('.premium-upgrade-purchase-success').show();
			$('.syllab_feat_table').closest('section').hide();
			$('.syllab_premium_cta__action').hide();
		}
	});

	// Advanced settings new menu button listeners
	$('.expertmode .advanced_settings_container .advanced_tools_button').on('click', function() {
		advanced_tool_hide($(this).attr("id"));
	});
	
	function advanced_tool_hide(show_tool) {
		
		$('.expertmode .advanced_settings_container .advanced_tools:not(".'+show_tool+'")').hide();
		$('.expertmode .advanced_settings_container .advanced_tools.'+show_tool).fadeIn('slow');
		
		$('.expertmode .advanced_settings_container .advanced_tools_button:not(#'+show_tool+')').removeClass('active');
		$('.expertmode .advanced_settings_container .advanced_tools_button#'+show_tool).addClass('active');
		
	}
	// https://github.com/select2/select2/issues/1246#issuecomment-71710835
	if (jQuery.ui && jQuery.ui.dialog && jQuery.ui.dialog.prototype._allowInteraction) {
		var ui_dialog_interaction = jQuery.ui.dialog.prototype._allowInteraction;
		jQuery.ui.dialog.prototype._allowInteraction = function(e) {
			if (jQuery(e.target).closest('.select2-dropdown').length) return true;
					   return ui_dialog_interaction.apply(this, arguments);
		};
	}

	$('#syllabcentral_keys').on('click', 'a.syllabcentral_keys_show', function(e) {
		e.preventDefault();
		$(this).remove();
		$('#syllabcentral_keys_table').slideDown();
	});
	
	$('#syllabcentral_keycreate_altmethod_moreinfo_get').on('click', function(e) {
		e.preventDefault();
		$(this).remove();
		$('#syllabcentral_keycreate_altmethod_moreinfo').slideDown();
	});
	
	// Update WebDAV URL as user edits
	$('#syllab-navtab-settings-content #remote-storage-holder').on('change keyup paste', '.syllab_webdav_settings', function() {
		var syllab_webdav_settings = [];
		$('.syllab_webdav_settings').each(function(index, item) {
			
			var id = $(item).attr('id');
			
			if (id && 'syllab_webdav_' == id.substring(0, 15)) {
				var which_one = id.substring(15);
				id_split = which_one.split('_');
				which_one = id_split[0];
				var instance_id = id_split[1];
				if ('undefined' == typeof syllab_webdav_settings[instance_id]) syllab_webdav_settings[instance_id] = [];
				syllab_webdav_settings[instance_id][which_one] = this.value;
			}
		});

		var syllab_webdav_url = "";
		var host = "@";
		var slash = "/";
		var colon = ":";
		var colon_port = ":";

		for (var instance_id in syllab_webdav_settings) {
			
			if (syllab_webdav_settings[instance_id]['host'].indexOf("@") >= 0 || "" === syllab_webdav_settings[instance_id]['host']) {
				host = "";
			}
			if (syllab_webdav_settings[instance_id]['host'].indexOf("/") >= 0) {
				$('#syllab_webdav_host_error').show();
			} else {
				$('#syllab_webdav_host_error').hide();
			}
			
			if (0 == syllab_webdav_settings[instance_id]['path'].indexOf("/") || "" === syllab_webdav_settings[instance_id]['path']) {
				slash = "";
			}
			
			if ("" === syllab_webdav_settings[instance_id]['user'] || "" === syllab_webdav_settings[instance_id]['pass']) {
				colon = "";
			}
			
			if ("" === syllab_webdav_settings[instance_id]['host'] || "" === syllab_webdav_settings[instance_id]['port']) {
				colon_port = "";
			}
			
			syllab_webdav_url = syllab_webdav_settings[instance_id]['webdav'] + syllab_webdav_settings[instance_id]['user'] + colon + syllab_webdav_settings[instance_id]['pass'] + host +encodeURIComponent(syllab_webdav_settings[instance_id]['host']) + colon_port + syllab_webdav_settings[instance_id]['port'] + slash + syllab_webdav_settings[instance_id]['path'];
			masked_webdav_url = syllab_webdav_settings[instance_id]['webdav'] + syllab_webdav_settings[instance_id]['user'] + colon + syllab_webdav_settings[instance_id]['pass'].replace(/./gi,'*') + host +encodeURIComponent(syllab_webdav_settings[instance_id]['host']) + colon_port + syllab_webdav_settings[instance_id]['port'] + slash + syllab_webdav_settings[instance_id]['path'];
			$('#syllab_webdav_url_' + instance_id).val(syllab_webdav_url);
			$('#syllab_webdav_masked_url_' + instance_id).val(masked_webdav_url);
		}
	});
	

	// Delete button
	$('#syllab-navtab-backups-content').on('click', '.js--delete-selected-backups', function(e) {
		e.preventDefault();
		syllab_deleteallselected();
	});

	$('#syllab-navtab-backups-content').on('click', '.syllab_existing_backups .backup-select input', function(e) {
		// e.preventDefault();
		syllab_backups_selection.toggle($(this).closest('.syllab_existing_backups_row'));
	});

	$('#syllab-navtab-backups-content').on('click', '#cb-select-all', function(e) {
		if ($(this).is(':checked')) {
			syllab_backups_selection.selectAll();
		} else {
			syllab_backups_selection.deselectAll();
		}
	});

	$('#syllab-wrap').on('click', '[id^=syllabplus_manual_authorisation_submit_]', function(e) {
		e.preventDefault();
		var method = $(this).data('method');
		var auth_data = $('#syllabplus_manual_authentication_data_'+method).val();
		$('#syllabplus_manual_authentication_error_'+method).text();
		$('#syllab-wrap #syllabplus_manual_authorisation_template_'+method+' .syllabplus_spinner.spinner').addClass('visible');
		$('#syllabplus_manual_authorisation_submit_'+method).prop('disabled', true);
		manual_remote_storage_auth(method, auth_data);
	});

	/**
	 * This method will send the ajax request to manually authenticate the remote storage method and then update the page with the response
	 *
	 * @param {string} method    - the remote storage method
	 * @param {string} auth_data - the auth data as a base64 json encoded string
	 */
	function manual_remote_storage_auth(method, auth_data) {
		syllab_send_command('manual_remote_storage_authentication', {method: method, auth_data: auth_data}, function(response) {
			$('#syllab-wrap #syllabplus_manual_authorisation_template_'+method+' .syllabplus_spinner.spinner').removeClass('visible');
			if (response.hasOwnProperty('result') && 'success' === response.result) {
				$('#syllab-wrap .syllabplus-top-menu').before(response.data);
				$('#syllab-wrap #syllabplus_manual_authorisation_template_'+method).parent().remove();
				$('#syllab-wrap .syllab_authenticate_'+method).remove();
			} else if (response.hasOwnProperty('result') && 'error' === response.result) {
				$('#syllabplus_manual_authentication_error_'+method).text(response.data);
				$('#syllabplus_manual_authorisation_submit_'+method).prop('disabled', false);
			}
		});
	}
	
	
	$('#syllab-navtab-backups-content').on('click', '.js--select-all-backups', function(e) {
		syllab_backups_selection.selectAll();
	});
	
	$('#syllab-navtab-backups-content').on('click', '.js--deselect-all-backups', function(e) {
		syllab_backups_selection.deselectAll();
	});
	
	$('#syllab-navtab-backups-content').on('click', '.syllab_existing_backups .syllab_existing_backups_row', function(e) {
		if (!e.ctrlKey && !e.metaKey) return;
		if (e.shiftKey) {
			// it's multiple range selection, it requires the user to hold shift+ctrl buttons during the range selection, the initial and the new starting index is saved in firstMultipleSelectionIndex variable
			if ("undefined" == typeof syllab_backups_selection.firstMultipleSelectionIndex) {
				// if all the above conditions are fulfilled then we need to set up the keyup event handler only for range selection operation. By doing it, we also ignore the Apple Command (metaKey) keycode checking which varies among the browser https://unixpapa.com/js/key.html
				$(document).on('keyup.MultipleSelection', function(e) {
					// multiple range selection operation requires the user to hold ctrl/cmd + shift buttons all the time during the selections, the range selection operation will be canceled if the user releases one of the held buttons (shitf or ctrl/cmd) and if that happens the highlight mode will stop working
					syllab_backups_selection.unregister_highlight_mode();
					// once this event handler has been triggered and the highlight mode has been turned off, this event handler needs to be removed by using its namespace .MultipleSelection
					$(document).off('.MultipleSelection');
				});
				syllab_backups_selection.select(this);
				$(this).addClass('range-selection-start');
				syllab_backups_selection.register_highlight_mode();
			} else {
				syllab_backups_selection.selectAllInBetween(this);
				jQuery('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').removeClass('range-selection');
			}
			// set the new starting index to the ending range index
			syllab_backups_selection.firstMultipleSelectionIndex = this.rowIndex - 1;
		} else {
			syllab_backups_selection.toggle(this);
		}
	});

	syllab_backups_selection.checkSelectionStatus();
	
	$('#syllab-navtab-addons-content .wrap').on('click', '.syllabplus_com_login .ud_connectsubmit', function (e) {
		e.preventDefault();
		var email = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_email').val();
		var password = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_password').val();
		var auto_update = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_auto_updates').is(':checked') ? 1: 0;
		var auto_udc_connect = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_auto_udc_connect').is(':checked') ? 1: 0;
		var options = {
			email: email,
			password: password,
			auto_update: auto_update,
			auto_udc_connect: auto_udc_connect
		};
		syllabplus_com_login.submit(options);
	});

	$('#syllab-navtab-addons-content .wrap').on('keydown', '.syllabplus_com_login input', function (e) {
		if (13 == e.which) {
			e.preventDefault();
			var email = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_email').val();
			var password = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_password').val();
			var auto_update = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_auto_updates').is(':checked') ? 1: 0;
			var auto_udc_connect = $('#syllab-navtab-addons-content .wrap .syllabplus_com_login #syllabplus-addons_options_auto_udc_connect').is(':checked') ? 1: 0;
			var options = {
				email: email,
				password: password,
				auto_update: auto_update,
				auto_udc_connect: auto_udc_connect
			};
			syllabplus_com_login.submit(options);
		}
	});

	$('#syllab-navtab-migrate-content').on('click', '.syllabclone_show_step_1', function (e) {
		$('.syllabplus-clone').addClass('opened');
		$('.syllabclone_show_step_1').hide();
		$('.syllab_migrate_widget_temporary_clone_stage1').show();
		$('.syllab_migrate_widget_temporary_clone_stage0').hide();
	});
	
	$('#syllab-navtab-migrate-content').on('click', '.syllab_migrate_widget_temporary_clone_show_stage0', function(e) {
		e.preventDefault();
		$('.syllab_migrate_widget_temporary_clone_stage0').toggle();
	});

	// First tab setup
	setup_migrate_tabs();

	// hide section when clicking the close button
	$('#syllab-navtab-migrate-content').on('click', '.syllab_migrate_widget_module_content .close', function (e) {
		$('.syllab_migrate_intro').show();
		$(this).closest('.syllab_migrate_widget_module_content').hide();
	});

	// Migrate show Add site button
	$('#syllab-navtab-migrate-content').on('click', '.syllab_migrate_add_site--trigger', function (e) {
		e.preventDefault();
		$('.syllab_migrate_add_site').toggle();
	});
	
	$('#syllab-navtab-migrate-content').on('click', '.syllab_migrate_widget_module_content .syllabplus_com_login .ud_connectsubmit', function (e) {
		e.preventDefault();
		var email = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_email').val();
		var password = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_password').val();
		var tfa = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_two_factor_code').val();
		var consent = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login .temporary_clone_terms_and_conditions').is(':checked') ? 1 : 0;
		var options = {
			form_data: {
				email: email,
				password: password,
				two_factor_code: tfa,
				consent: consent
			}
		};
		if (!email || !password) {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login_status').html('<b>' + syllablion.error + '</b> ' + syllablion.username_password_required).show();
		} else {
			temporary_clone_submit(options);
		}
	});

	$('#syllab-navtab-migrate-content').on('keydown', '.syllab_migrate_widget_module_content .syllabplus_com_login input', function (e) {
		if (13 == e.which) {
			e.preventDefault();
			var email = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_email').val();
			var password = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_password').val();
			var tfa = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_two_factor_code').val();
			var consent = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login .temporary_clone_terms_and_conditions').is(':checked') ? 1 : 0;
			var options = {
				form_data: {
					email: email,
					password: password,
					two_factor_code: tfa,
					consent: consent
				}
			};
			if (!email || !password) {
				$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login_status').html('<b>' + syllablion.error + '</b> ' + syllablion.username_password_required).show();
			} else {
				temporary_clone_submit(options);
			}
		}
	});

	$('#syllab-navtab-migrate-content').on('click', '.syllab_migrate_widget_module_content .syllabplus_com_key .ud_key_connectsubmit', function (e) {
		e.preventDefault();
		var clone_key = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key #temporary_clone_options_key').val();
		var consent = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key .temporary_clone_terms_and_conditions').is(':checked') ? 1 : 0;
		var options = {
			form_data: {
				clone_key: clone_key,
				consent: consent
			}
		};
		if (!clone_key) {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key_status').html('<b>' + syllablion.error + '</b> ' + syllablion.clone_key_required).show();
		} else {
			temporary_clone_key_submit(options);
		}
	});

	$('#syllab-navtab-migrate-content').on('keydown', '.syllab_migrate_widget_module_content .syllabplus_com_key input', function (e) {
		if (13 == e.which) {
			e.preventDefault();
			var clone_key = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key #temporary_clone_options_key').val();
			var consent = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key .temporary_clone_terms_and_conditions').is(':checked') ? 1 : 0;
			var options = {
				form_data: {
					clone_key: clone_key,
					consent: consent
				}
			};
			if (!clone_key) {
				$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key_status').html('<b>' + syllablion.error + '</b> ' + syllablion.clone_key_required).show();
			} else {
				temporary_clone_key_submit(options);
			}
		}
	});
	
	$('#syllab-navtab-migrate-content').on('change', '.syllab_migrate_widget_module_content #syllabplus_clone_php_options', function () {
		var php_version = $(this).data('php_version');
		var selected_version = $(this).val();

		if (selected_version < php_version) {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html(syllablion.clone_version_warning);
		} else {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html('');
		}
	});

	$('#syllab-navtab-migrate-content').on('change', '.syllab_migrate_widget_module_content #syllabplus_clone_wp_options', function () {
		var wp_version = $(this).data('wp_version');
		var selected_version = $(this).val();

		if (selected_version < wp_version) {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html(syllablion.clone_version_warning);
		} else {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html('');
		}
	});

	$('#syllab-navtab-migrate-content').on('change', '.syllab_migrate_widget_module_content #syllabplus_clone_backup_options', function() {

		// reset the package list
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options > option').each(function() {
			var value = $(this).val();
			if ('starter' == value) $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options  option[value="'+value+'"]').prop('selected', true);
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options  option[value="'+value+'"]').prop("disabled", false);
		});
		
		var clone_backup_select = $(this).find('option:selected');

		if ('current' == $(clone_backup_select).data('nonce') || 'wp_only' == $(clone_backup_select).data('nonce')) return;
		
		var total_size = $(clone_backup_select).data('size');

		// Disable packages that are to small for this backup set, then set the first available package as the selected option
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options > option').each(function() {
			var size = $(this).data('size');
			var value = $(this).val();
			if (total_size >= size) {
				$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options  option[value="'+value+'"]').prop("disabled", true);
			} else {
				$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options  option[value="'+value+'"]').prop('selected', true);
				return false;
			}
		});
	});

	$('#syllab-navtab-migrate-content').on('click', '.syllab_migrate_widget_module_content #syllab_migrate_createclone', function (e) {
		e.preventDefault();

		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllab_migrate_createclone').prop('disabled', true);
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html('');
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_spinner.spinner').addClass('visible');

		var clone_id = $(this).data('clone_id');
		var secret_token = $(this).data('secret_token');
		var php_version = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_php_options').val();
		var wp_version = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_wp_options').val();
		var region = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_region_options').val();
		var package = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_package_options').val();
		var syllabclone_branch = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_syllabclone_branch').val();
		var syllabplus_branch = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_syllabplus_branch').val();
		var admin_only = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_admin_login_options').is(':checked');
		var use_queue = $('#syllabplus_clone_use_queue').is(':checked') ? 1 : 0;
		var db_anon_all = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_backupnow_db_anon_all').is(':checked') ? 1 : 0;
		var db_anon_non_staff = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_backupnow_db_anon_non_staff').is(':checked') ? 1 : 0;

		var backup_nonce = 'current';
		var backup_timestamp = 'current';
		var clone_backup_select_length = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_backup_options').length;
		var clone_backup_select = $('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllabplus_clone_backup_options').find('option:selected');
		if (0 !== clone_backup_select_length && 'undefined' !== typeof clone_backup_select) {
			backup_nonce = clone_backup_select.data('nonce');
			backup_timestamp = clone_backup_select.data('timestamp');
		}
		
		var options = {
			form_data: {
				clone_id: clone_id,
				secret_token: secret_token,
				install_info: {
					php_version: php_version,
					wp_version: wp_version,
					region: region,
					package: package,
					admin_only: admin_only,
					syllabclone_branch: ('undefined' === typeof syllabclone_branch) ? '' : syllabclone_branch,
					syllabplus_branch: ('undefined' === typeof syllabplus_branch) ? '' : syllabplus_branch,
					use_queue: ('undefined' === typeof use_queue) ? 1 : use_queue
				}
			}
		};

		var backup_options = {
			db_anon_all: db_anon_all,
			db_anon_non_staff: db_anon_non_staff
		}

		if ('wp_only' === backup_nonce) {
			options['form_data']['install_info']['wp_only'] = 1;
		}

		temporary_clone_process_create(options, backup_timestamp, backup_nonce, backup_options);
	});

	// Create a syllabplus_com_login object, to store functions and variables
	var syllabplus_com_login = {};

	syllabplus_com_login.set_status = function(status) {
		$('#syllab-navtab-addons-content .wrap').find('.syllabplus_spinner.spinner').text(status);
	}

	syllabplus_com_login.show_loader = function() {
		$('#syllab-navtab-addons-content .wrap').find('.syllabplus_spinner.spinner').addClass('visible');
		$('#syllab-navtab-addons-content .wrap').find('.ud_connectsubmit').prop('disabled', 'disabled');
	}

	syllabplus_com_login.hide_loader = function() {
		$('#syllab-navtab-addons-content .wrap').find('.syllabplus_spinner.spinner').removeClass('visible').text(syllablion.processing);
		$('#syllab-navtab-addons-content .wrap').find('.ud_connectsubmit').prop('disabled', false);
	}

	/*
		This function will send an AJAX request to the backend to check the users credentials, then it will either inform the user of any errors or if there are none it will submit the form.
		@param {array} options - an array that includes the users email and password
	*/

	syllabplus_com_login.submit = function(options) {

		$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html('').hide();

		if (this.stage) {
			switch (this.stage) {
				case 'connect_udc':
				case 'connect_udc_TFA':
				// update data in object
				var email = $('#syllabplus-addons_options_email').val();
				var password = $('#syllabplus-addons_options_password').val();
				this.login_data.email = email;
				this.login_data.password = password;
				// connect_udc again
				this.connect_udc();
					break;
				case 'create_key':
				this.create_key();
					break;
				default:
				this.stage = null;
				syllabplus_com_login.submit();
					break;
			}

			return;
		}

		this.set_status(syllablion.connecting);
		this.show_loader();

		syllab_send_command('syllabplus_com_login_submit', {
			data: options,
		}, function (response) {
			if (response.hasOwnProperty('success')) {

				// logged in was successful, so create a key if the checkbox was checked.
				if ($('#syllabplus-addons_options_auto_udc_connect').is(':checked')) {

					this.login_data = {
						email: options.email,
						password: options.password,
						i_consent: 1,
						two_factor_code: ''
					};

					// CREATE KEY
					syllabplus_com_login.create_key();

				} else {
					syllabplus_com_login.hide_loader();
					$('#syllab-navtab-addons-content .wrap .syllabplus_com_login').trigger('submit');
				}
			} else if (response.hasOwnProperty('error')) {
				syllabplus_com_login.hide_loader();
				$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html(response.message).show();
			}
		}.bind(this));
	}

	syllabplus_com_login.create_key = function() {

		this.stage = 'create_key';

		this.set_status(syllablion.udc_cloud_connected);
		this.show_loader();

		var command_data = {
			where_send: '__syllabpluscom',
			key_description: '',
			key_size: null,
			mothership_firewalled: 0
		};

		// syllabcentral_cloud_show_spinner(modal);
		syllab_send_command('syllabcentral_create_key', command_data, function(response) {
			// syllabcentral_cloud_hide_spinner(modal);
			try {
				var data = ud_parse_json(response);
				if (data.hasOwnProperty('error')) {
					console.log(data);
					return;
				}

				if (data.hasOwnProperty('bundle')) {
					
					console.log('bundle', data.bundle);

					this.login_data.key = data.bundle,
					this.stage = 'connect_udc';

					syllabplus_com_login.connect_udc();

				} else {
					if (data.hasOwnProperty('r')) {
						$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html(syllablion.trouble_connecting).show();
						alert(data.r);
					} else {
						$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html(syllablion.trouble_connecting).show();
						console.log(data);
					}
					syllabplus_com_login.hide_loader();
				}
			} catch (err) {
				console.log(err);
				syllabplus_com_login.hide_loader();
			}
		}.bind(this), { json_parse: false });

	}

	syllabplus_com_login.connect_udc = function() {

		var container = $('#syllab-navtab-addons-content .wrap');

		syllabplus_com_login.set_status(syllablion.udc_cloud_key_created);
		syllabplus_com_login.show_loader();

		if ('connect_udc_TFA' == this.stage) {
			this.login_data.two_factor_code = container.find('input#syllabplus-addons_options_two_factor_code').val();
			syllabplus_com_login.set_status(syllablion.checking_tfa_code);
		}

		var login_data = { form_data: this.login_data };
		login_data.form_data.addons_options_connect = 1;

		// Final step, connect UDC with the Key and all.
		syllab_send_command('process_syllabcentral_login', login_data, function(login_response) {
			try {

				var data = ud_parse_json(login_response);

				if (data.hasOwnProperty('error')) {
					if ('incorrect_password' === data.code) {
						container.find('.tfa_fields').hide();
						container.find('.non_tfa_fields').show();
						container.find('input#syllabplus-addons_options_two_factor_code').val('');
						container.find('input#syllabplus-addons_options_password').val('').trigger('focus');
					}
					if ('no_key_found' === data.code) {
						this.stage = 'create_key';
					}
					
					// Continue with SyllabPlus account even if the user has used all SyllabCentral licences
					if ('no_licences_available' === data.code) {
						$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html(syllablion.login_udc_no_licences_short).show();
						data.status = 'authenticated';
						container.find('input[name="_wp_http_referer"]').val(function(index, val) {
							return val + '&udc_connect=0';
						});
					} else {
						$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html(data.message).show();
						$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').find('a').attr('target', '_blank');
						console.log(data);
						syllabplus_com_login.hide_loader();
						return;
					}
				}

				if (data.hasOwnProperty('tfa_enabled') && true == data.tfa_enabled) {
					$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html('').hide();
					container.find('.non_tfa_fields').hide();
					container.find('.tfa_fields').show();
					container.find('input#syllabplus-addons_options_two_factor_code').trigger('focus');
					this.stage = 'connect_udc_TFA';
				}

				if ('authenticated' === data.status) {
					container.find('.non_tfa_fields').hide();
					container.find('.tfa_fields').hide();
					container.find('.syllab-after-form-table').hide();

					this.stage = null;

					$('#syllab-navtab-addons-content .wrap .syllabplus_com_login_status').html(syllablion.login_successful_short).show().addClass('success');

					// submit the form (to reload the page).
					setTimeout(function() {
						$('#syllab-navtab-addons-content .wrap form.syllabplus_com_login').trigger('submit');
					}, 1000);
				}

			} catch (err) {
				console.log(err);
			}
			syllabplus_com_login.hide_loader();
		}.bind(this), { json_parse: false });


	}

	/**
	 * This function will send an AJAX request to the backend to check the users credentials, then it will either inform the user of any errors or display UI elements that include their token count and a way to create new clones.
	 *
	 * @param {array} options - an array that includes the users email and password
	 */
	function temporary_clone_submit(options) {
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login_status').html('').hide();
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login .syllabplus_spinner.spinner').addClass('visible');
		syllab_send_command('process_syllabplus_clone_login', options, function (response) {
			try {
				$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login .syllabplus_spinner.spinner').removeClass('visible');
				
				if (response.hasOwnProperty('status') && 'error' == response.status) {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login_status').html(response.message).show();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 .tfa_fields').hide();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 .non_tfa_fields').show();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_login #temporary_clone_options_two_factor_code').val('');
					return;
				}

				if (response.hasOwnProperty('tfa_enabled') && true == response.tfa_enabled) {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 .non_tfa_fields').hide();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 .tfa_fields').show();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 input#temporary_clone_options_two_factor_code').trigger('focus');
				}

				if ('authenticated' === response.status) {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1').hide();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 .non_tfa_fields').show();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 .tfa_fields').hide();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1 input#temporary_clone_options_two_factor_code').val('');
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').show();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').html(response.html);
					if (response.hasOwnProperty('clone_info') && response.clone_info.hasOwnProperty('expires_after')) temporary_clone_timer(response.clone_info.expires_after);
				}
			} catch (err) {
				console.log(err);
			}
		});
	}

	/**
	 * This function will send an AJAX request to the backend to check the clone key, then it will either inform the user of any errors or display UI elements that include their token count and a way to create new clones.
	 *
	 * @param {array} options - an array that includes the clone key
	 */
	function temporary_clone_key_submit(options) {
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key_status').html('').hide();
		$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key .syllabplus_spinner.spinner').addClass('visible');
		syllab_send_command('process_syllabplus_clone_login', options, function (response) {
			try {
				$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key .syllabplus_spinner.spinner').removeClass('visible');

				if (response.hasOwnProperty('status') && 'error' == response.status) {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_com_key_status').html(response.message).show();
					return;
				}

				if ('authenticated' === response.status) {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1').hide();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').show();
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').html(response.html);
					if (response.hasOwnProperty('clone_info') && response.clone_info.hasOwnProperty('expires_after')) temporary_clone_timer(response.clone_info.expires_after);
				}
			} catch (err) {
				console.log(err);
			}
		});
	}

	/**
	 * This function will add a timer to reset the UI if the user does not create the clone before it expires
	 *
	 * @param {integer} expires_after - the clone expires time in seconds
	 */
	function temporary_clone_timer(expires_after) {

		// the expires_after time is in seconds we need it in milliseconds for the setTimeout function
		var timeout = expires_after * 1000;

		temporary_clone_timeout = setTimeout(function () {
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').hide();
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').html('');
			$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage1').show();
		}, timeout);
	}

	/**
	 * This function will check if we are using an existing backup and if anything needs downloading before proceeding to process the clone create command
	 *
	 * @param {array}  options          - an array of options to create the clone
	 * @param {string} backup_timestamp - the timestamp of the backup we want to use or 'current' to create a new backup
	 * @param {string} backup_nonce     - the backup nonce of the backup we want to use or 'current' to create a new backup
	 * @param {array}  backup_options   - an array of options for the backup
	 */
	function temporary_clone_process_create(options, backup_timestamp, backup_nonce, backup_options) {

		var which_to_download = '';
		if ('current' != backup_timestamp) {
			syllab_send_command('whichdownloadsneeded', {
				syllabplus_clone: true,
				timestamp: backup_timestamp
			}, function (response) {
				if (response.hasOwnProperty('downloads')) {
					console.log('SyllabPlus: items which still require downloading follow');
					which_to_download = response.downloads;
					console.log(which_to_download);
				}

				// Kick off any downloads, if needed
				if (0 == which_to_download.length) return;

				for (var i = 0; i < which_to_download.length; i++) {
					// syllab_downloader(base, backup_timestamp, what, whicharea, set_contents, prettydate, async)
					syllab_downloader('udclonedlstatus_', backup_timestamp, which_to_download[i][0], '#ud_downloadstatus3', which_to_download[i][1], '', false);
				}

			}, {
				alert_on_error: false, error_callback: function (response, status, error_code, resp) {
					if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
						console.error(resp.fatal_error_message);
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html('<p style="color:red;">' + resp.fatal_error_message + '</p>');
					} else {
						var error_message = "syllab_send_command: error: " + status + " (" + error_code + ")";
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html('<p style="color:red; margin: 5px;">' + error_message + '</p>');
						console.log(error_message);
						console.log(response);
					}
				}
			});
		}

		setTimeout(function () {
			if (0 != which_to_download.length) {
				temporary_clone_process_create(options, backup_timestamp, backup_nonce, backup_options);
				return;
			}
			var clone_id = options['form_data']['clone_id'];
			var secret_token = options['form_data']['secret_token'];
			syllab_send_command('process_syllabplus_clone_create', options, function (response) {
				try {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllab_migrate_createclone').prop('disabled', false);
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_spinner.spinner').removeClass('visible');

					if (response.hasOwnProperty('status') && 'error' == response.status) {
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html(syllablion.error + ' ' + response.message).show();
						return;
					}

					if ('success' === response.status) {
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage2').hide();
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage3').show();
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllab_migrate_widget_temporary_clone_stage3').html(response.html);

						// remove the clone timeout as the clone has now been created
						if (temporary_clone_timeout) clearTimeout(temporary_clone_timeout);

						// check if the response includes a secret token, if it does we have claimed a clone from the queue and need to update our current secret token to the one that belongs to the claimed clone
						if (response.hasOwnProperty('secret_token')) {
							secret_token = response.secret_token;
						}

						if ('wp_only' === backup_nonce) {
							jQuery('#syllab_clone_progress .syllabplus_spinner.spinner').addClass('visible');
							temporary_clone_poll(clone_id, secret_token);
						} else {
							jQuery('#syllab_clone_progress .syllabplus_spinner.spinner').addClass('visible');
							temporary_clone_boot_backup(clone_id, secret_token, response.url, response.key, backup_nonce, backup_timestamp, backup_options);
						}
					}
				} catch (err) {
					$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllab_migrate_createclone').prop('disabled', false);
					console.log("Error when processing the response of process_syllabplus_clone_create (as follows)");
					console.log(err);
				}
			});
		}, 5000);
	}

	/**
	 * This function will send an AJAX request to the backend to start a clone backup job
	 *
	 * @param {string} clone_id         - the clone id
	 * @param {string} secret_token     - the clone secret
	 * @param {string} clone_url        - the clone url
	 * @param {string} key              - the migration key
	 * @param {string} backup_nonce     - the nonce for the backup we want to use or 'current' for a fresh backup
	 * @param {string} backup_timestamp - the timestamp for the backup we want to use or 'current' for a fresh backup
	 * @param {array}  backup_options   - an array of options for the backup
	 */
	function temporary_clone_boot_backup(clone_id, secret_token, clone_url, key, backup_nonce, backup_timestamp, backup_options) {
		
		var params = {
			syllabplus_clone_backup: 1,
			backupnow_nodb: 0,
			backupnow_nofiles: 0,
			backupnow_nocloud: 0,
			backupnow_label: 'SyllabPlus Clone',
			extradata: '',
			onlythisfileentity: 'plugins,themes,uploads,others',
			clone_id: clone_id,
			secret_token: secret_token,
			clone_url: clone_url,
			key: key,
			backup_nonce: backup_nonce,
			backup_timestamp: backup_timestamp,
			db_anon_all: backup_options['db_anon_all'],
			db_anon_non_staff: backup_options['db_anon_non_staff']
		};

		syllab_activejobslist_backupnownonce_only = 1;

		syllab_send_command('backupnow', params, function (response) {
			jQuery('#syllab_clone_progress .syllabplus_spinner.spinner').removeClass('visible');
			jQuery('#syllab_backup_started').html(response.m);
			if (response.hasOwnProperty('nonce')) {
				// Can't return it from this context
				syllab_backupnow_nonce = response.nonce;
				syllab_clone_jobs.push(syllab_backupnow_nonce);
				syllab_inpage_success_callback = function () {
					jQuery('#syllab_clone_activejobsrow').hide();
					// If user aborts the job
					if (syllab_aborted_jobs[syllab_backupnow_nonce]) {
						jQuery('#syllab_clone_progress').html(syllablion.clone_backup_aborted);
					} else {
						jQuery('#syllab_clone_progress').html(syllablion.clone_backup_complete);
					}
				};
				console.log("SyllabPlus: ID of started job: " + syllab_backupnow_nonce);
			}
			
			syllab_activejobs_update(true);
		});
	}

	/**
	 * This function will send an AJAX request to the backend to poll for the clones install information
	 *
	 * @param {string} clone_id     - the clone id
	 * @param {string} secret_token - the clone secret
	 */
	function temporary_clone_poll(clone_id, secret_token) {
		var options = {
			clone_id: clone_id,
			secret_token: secret_token,
		};

		setTimeout(function () {
			syllab_send_command('process_syllabplus_clone_poll', options, function (response) {
				if (response.hasOwnProperty('status')) {
					
					if ('error' == response.status) {
						$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_clone_status').html(syllablion.error + ' ' + response.message).show();
						return;
					}

					if ('success' === response.status) {
						if (response.hasOwnProperty('data') && response.data.hasOwnProperty('wordpress_credentials')) {
							$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content .syllabplus_spinner.spinner').removeClass('visible');
							$('#syllab-navtab-migrate-content .syllab_migrate_widget_module_content #syllab_clone_progress').append('<br>WordPress ' + syllablion.credentials + ':<br>' + syllablion.username + ': ' + response.data.wordpress_credentials.username + '<br>' + syllablion.password + ': ' + response.data.wordpress_credentials.password);
							return;
						}
					}
				} else {
					console.log(response);
				}
				temporary_clone_poll(clone_id, secret_token);
			});
		}, 60000);
	}

	$('#syllab-navtab-settings-content #remote-storage-holder').on('click', '.syllabplusmethod a.syllab_add_instance', function(e) {
		e.preventDefault();

		syllab_settings_form_changed = true;
		
		var method = $(this).data('method');
		add_new_instance(method);
	});

	$('#syllab-navtab-settings-content #remote-storage-holder').on('click', '.syllabplusmethod a.syllab_delete_instance', function(e) {
		e.preventDefault();

		syllab_settings_form_changed = true;

		var method = $(this).data('method');
		var instance_id = $(this).data('instance_id');

		if (1 === $('.' + method + '_syllab_remote_storage_border').length) {
			add_new_instance(method);
		}

		$('.' + method + '-' + instance_id).hide('slow', function() {
			$(this).remove();
		});
	});

	$('#syllab-navtab-settings-content #remote-storage-holder').on('click', '.syllabplusmethod .syllab_edit_label_instance', function(e) {
		$(this).find('span').hide();
		$(this).attr('contentEditable', true).trigger('focus');
	});
	
	$('#syllab-navtab-settings-content #remote-storage-holder').on('keyup', '.syllabplusmethod .syllab_edit_label_instance', function(e) {
		var method = jQuery(this).data('method');
		var instance_id = jQuery(this).data('instance_id');
		var content = jQuery(this).text();

		$('#syllab_' + method + '_instance_label_' + instance_id).val(content);
	});

	$('#syllab-navtab-settings-content #remote-storage-holder').on('blur', '.syllabplusmethod .syllab_edit_label_instance', function(e) {
		$(this).attr('contentEditable', false);
		$(this).find('span').show();
	});

	$('#syllab-navtab-settings-content #remote-storage-holder').on('keypress', '.syllabplusmethod .syllab_edit_label_instance', function(e) {
		if (13 === e.which) {
			$(this).attr('contentEditable', false);
			$(this).find('span').show();
			$(this).trigger('blur');
		}
	});

	/**
	 * This method will get the default options and compile a template with them
	 *
	 * @param {string} method - the remote storage name
	 * @param {boolean} first_instance - indicates if this is the first instance of this type
	 */
	function add_new_instance(method) {
		var template = Handlebars.compile(syllablion.remote_storage_templates[method]);
		var context = syllablion.remote_storage_options[method]['default'];
		var method_name = syllablion.remote_storage_methods[method];
		context['instance_id'] = 's-' + generate_instance_id(32);
		context['instance_enabled'] = 1;
		context['instance_label'] = method_name + ' (' + (jQuery('.' + method + '_syllab_remote_storage_border').length + 1) + ')';
		context['instance_conditional_logic'] = {
			type: '', // always by default
			rules: [],
			day_of_the_week_options: syllablion.conditional_logic.day_of_the_week_options,
			logic_options: syllablion.conditional_logic.logic_options,
			operand_options: syllablion.conditional_logic.operand_options,
			operator_options: syllablion.conditional_logic.operator_options,
		};
		var html = template(context);
		jQuery(html).hide().insertAfter(jQuery('.' + method + '_add_instance_container').first()).show('slow');
	}

	/**
	 * This method will return a random instance id string
	 *
	 * @param {integer} length - the length of the string to be generated
	 *
	 * @return string         - the instance id
	 */
	function generate_instance_id(length) {
		var uuid = '';
		var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		for (var i = 0; i < length; i++) {
			uuid += characters.charAt(Math.floor(Math.random() * characters.length));
		}

		return uuid;
	}

	jQuery('#syllab-navtab-settings-content #remote-storage-holder').on("change", "input[class='syllab_instance_toggle']", function () {
		syllab_settings_form_changed = true;
		if (jQuery(this).is(':checked')) {
			jQuery(this).siblings('label').html(syllablion.instance_enabled);
		} else {
			jQuery(this).siblings('label').html(syllablion.instance_disabled);
		}
	});

	jQuery('#syllab-navtab-settings-content #remote-storage-holder').on("change", "select[class='logic_type']", function () {
		syllab_settings_form_changed = true;
		if ('' !== this.value) {
			jQuery('div.logic', jQuery(this).parents('tr.syllabplusmethod')).show();
			jQuery(this).parents('tr.syllabplusmethod').find('div.logic ul.rules > li').each(function() {
				jQuery(this).find('select').each(function() {
					jQuery(this).prop('disabled', false);
				})
			});
		} else {
			jQuery(this).parents('tr.syllabplusmethod').find('div.logic ul.rules > li').each(function() {
				jQuery(this).find('select').each(function() {
					jQuery(this).prop('disabled', true);
				})
			});
			jQuery(this).parents('tr.syllabplusmethod').find('div.logic').hide();
		}
	});

	jQuery('#syllab-navtab-settings-content #remote-storage-holder').on("change", "select[class='conditional_logic_operand']", function () {
		syllab_settings_form_changed = true;
		jQuery(this).parent().find('select:nth(2)').empty();
		if ('day_of_the_week' === jQuery(this).val()) {
			for (i=0; i<syllablion.conditional_logic.day_of_the_week_options.length; i++) {
				jQuery(this).parent().find('select:nth(2)').append(jQuery('<option value="'+syllablion.conditional_logic.day_of_the_week_options[i].index+'"></option>').text(syllablion.conditional_logic.day_of_the_week_options[i].value));
			}
		} else if ('day_of_the_month' === jQuery(this).val()) {
			for (i=1; i<=31; i++) {
				jQuery(this).parent().find('select:nth(2)').append(jQuery('<option value="'+i+'"></option>').text(i));
			}
		}
	});

	jQuery('#syllab-navtab-settings-content #remote-storage-holder').on("click", "div.conditional_remote_backup ul.rules li span", function () {
		syllab_settings_form_changed = true;
		var $ul = jQuery(this).parents('ul.rules');
		if (jQuery(this).hasClass('remove-rule')) {
			jQuery(this).parent().slideUp(function() {
				jQuery(this).remove();
				if (jQuery($ul).find('> li').length < 2) {
					jQuery('li:nth(0) span.remove-rule', $ul).remove();
				}
			});
		}
	});

	jQuery('#syllab-navtab-settings-content #remote-storage-holder').on("click", "div.conditional_remote_backup input.add-new-rule", function () {
		var $ul = jQuery(this).parent().find('ul.rules');
		if (jQuery($ul).find('> li').length < 2) {
			jQuery($ul).find('li:nth(0)').append('<span class="remove-rule"><svg viewbox="0 0 25 25"><line x1="6.5" y1="18.5" x2="18.5" y2="6.5" fill="none" stroke="#FF6347" stroke-width="3" vector-effect="non-scaling-stroke" ></line><line y1="6.5" x1="6.5" y2="18.5" x2="18.5" fill="none" stroke="#FF6347" stroke-width="3" vector-effect="non-scaling-stroke" ></line></svg></span>');
		}
		$cloned_item = jQuery($ul).find('> li').last().clone();
		jQuery($cloned_item).find('> select').each(function() {
			jQuery(this).prop('name', jQuery(this).prop('name').replace(/\[instance_conditional_logic\]\[rules\]\[[0-9]+\]/gi, '[instance_conditional_logic][rules]['+jQuery($ul).data('rules')+']'));
		});
		jQuery($ul).append($cloned_item);
		jQuery($ul).data('rules', parseInt(jQuery($ul).data('rules')) + 1);
		jQuery($cloned_item).find('select[name*="[operand]"]').trigger('change');
	});
	
	jQuery('#syllab-navtab-settings-content #remote-storage-holder').on('click', '.syllabplusmethod button.syllab-test-button', function() {

		var method = jQuery(this).data('method');
		var instance_id = jQuery(this).data('instance_id');
		syllab_remote_storage_test(method, function(response, status, data) {
			if ('sftp' != method) { return false; }
			
			if (data.hasOwnProperty('scp') && data.scp) {
				alert(syllablion.settings_test_result.replace('%s', 'SCP')+' '+response.output);
			} else {
				alert(syllablion.settings_test_result.replace('%s', 'SFTP')+' '+response.output);
			}
			
			if (response.hasOwnProperty('data') && response.data) {
				if (response.data.hasOwnProperty('valid_md5_fingerprint') && response.data.valid_md5_fingerprint) {
					$('#syllab_sftp_fingerprint_'+instance_id).val(response.data.valid_md5_fingerprint);
				}
			}
			
			return true;
			
		}, instance_id);
	});
	
	$('#syllab-navtab-settings-content select.syllab_interval, #syllab-navtab-settings-content select.syllab_interval_database').on('change', function() {
		syllab_check_same_times();
	});
	
	$('#backupnow_includefiles_showmoreoptions').on('click', function(e) {
		e.preventDefault();
		$('#backupnow_includefiles_moreoptions').toggle();
	});

	$('#backupnow_database_showmoreoptions').on('click', function(e) {
		e.preventDefault();
		$('#backupnow_database_moreoptions').toggle();
	});

	$('#backupnow_db_anon_all').on('click', function(e) {
		if ($('#backupnow_db_anon_non_staff').prop('checked')) $('#backupnow_db_anon_non_staff').prop("checked", false);
	});

	$('#backupnow_db_anon_non_staff').on('click', function(e) {
		if ($('#backupnow_db_anon_all').prop('checked')) $('#backupnow_db_anon_all').prop("checked", false);
	});

	$('#syllab-navtab-migrate-content').on('click', '#syllabplus_clone_backupnow_db_anon_all', function() {
		if ($('#syllabplus_clone_backupnow_db_anon_non_staff').prop('checked')) $('#syllabplus_clone_backupnow_db_anon_non_staff').prop("checked", false);
	});

	$('#syllab-navtab-migrate-content').on('click', '#syllabplus_clone_backupnow_db_anon_non_staff', function() {
		if ($('#syllabplus_clone_backupnow_db_anon_all').prop('checked')) $('#syllabplus_clone_backupnow_db_anon_all').prop("checked", false);
	});

	$('#syllab-backupnow-modal').on('click', '#backupnow_includecloud_showmoreoptions', function(e) {
		e.preventDefault();
		$('#backupnow_includecloud_moreoptions').toggle();
	});
	
	$('#syllab-navtab-backups-content').on('click', 'a.syllab_diskspaceused_update',function(e) {
		e.preventDefault();
		syllabplus_diskspace();
	});
	
	// For Advanced Tools > Site information > Web-server disk space in use by SyllabPlus
	$('.advanced_settings_content a.syllab_diskspaceused_update').on('click', function(e) {
		e.preventDefault();
		jQuery('.advanced_settings_content .syllab_diskspaceused').html('<em>'+syllablion.calculating+'</em>');
		syllab_send_command('get_fragment', { fragment: 'disk_usage', data: 'syllab' }, function(response) {
			jQuery('.advanced_settings_content .syllab_diskspaceused').html(response.output);
		}, { type: 'GET' });
	});
	
	$('#syllab-navtab-backups-content a.syllab_uploader_toggle').on('click', function(e) {
		e.preventDefault();
		$('#syllab-plupload-modal').slideToggle();
	});
	
	$('#syllab-navtab-backups-content a.syllab_rescan_local').on('click', function(e) {
		e.preventDefault();
		syllab_updatehistory(1, 0);
	});
	
	$('#syllab-navtab-backups-content a.syllab_rescan_remote').on('click', function(e) {
		e.preventDefault();
		if (!confirm(syllablion.remote_scan_warning)) return;
		syllab_updatehistory(1, 1);
	});
	
	$('#syllabplus-remote-rescan-debug').on('click', function(e) {
		e.preventDefault();
		syllab_updatehistory(1, 1, 1);
	});

	function syllabcentral_keys_setupform(on_page_load) {
		var is_other = jQuery('#syllabcentral_mothership_other').is(':checked') ? true : false;
		if (is_other) {
			jQuery('#syllabcentral_keycreate_mothership').prop('disabled', false);
			if (on_page_load) {
				jQuery('#syllabcentral_keycreate_mothership_firewalled_container').show();
			} else {
				jQuery('.syllabcentral_wizard_self_hosted_stage2').show();
				jQuery('#syllabcentral_keycreate_mothership_firewalled_container').slideDown();
				jQuery('#syllabcentral_keycreate_mothership').trigger('focus');
			}
		} else {
			jQuery('#syllabcentral_keycreate_mothership').prop('disabled', true);
			if (!on_page_load) {
				jQuery('.syllabcentral_wizard_self_hosted_stage2').hide();
				syllabcentral_stage2_go();
			}
		}
	}
	
	function syllabcentral_stage2_go() {
		// Reset the error message before we continue
		jQuery('#syllabcentral_wizard_stage1_error').text('');

		var host = '';

		if (jQuery('#syllabcentral_mothership_syllabpluscom').is(':checked')) {
			jQuery('.syllabcentral_keycreate_description').hide();
			host = 'syllabplus.com';
		} else if (jQuery('#syllabcentral_mothership_other').is(':checked')) {
			jQuery('.syllabcentral_keycreate_description').show();
			var mothership = jQuery('#syllabcentral_keycreate_mothership').val();
			if ('' == mothership) {
				jQuery('#syllabcentral_wizard_stage1_error').text(syllablion.syllabcentral_wizard_empty_url);
				return;
			}
			try {
				var url = new URL(mothership);
				host = url.hostname;
			} catch (e) {
				// Try and grab the host name a different way if it failed because of no URL object (e.g. IE 11).
				if ('undefined' === typeof URL) {
					host = jQuery('<a>').prop('href', mothership).prop('hostname');
				}
				if (!host || 'undefined' !== typeof URL) {
					jQuery('#syllabcentral_wizard_stage1_error').text(syllablion.syllabcentral_wizard_invalid_url);
					return;
				}
			}
		}

		jQuery('#syllabcentral_keycreate_description').val(host);

		jQuery('.syllabcentral_wizard_stage1').hide();
		jQuery('.syllabcentral_wizard_stage2').show();
	}

	jQuery('#syllabcentral_keys').on('click', 'input[type="radio"]', function() {
		syllabcentral_keys_setupform(false);
	});
	// Initial setup (for browsers, e.g. Firefox, that remember form selection state but not DOM state, which can leave an inconsistent state)
	syllabcentral_keys_setupform(true);
	
	jQuery('#syllabcentral_keys').on('click', '#syllabcentral_view_log', function(e) {
		e.preventDefault();
		jQuery('#syllabcentral_view_log_container').block({ message: '<div style="margin: 8px; font-size:150%;"><img src="'+syllablion.ud_url+'/images/udlogo-rotating.gif" height="80" width="80" style="padding-bottom:10px;"><br>'+syllablion.fetching+'</div>'});
		try {
			syllab_send_command('syllabcentral_get_log', null, function(response) {
				jQuery('#syllabcentral_view_log_container').unblock();
				if (response.hasOwnProperty('log_contents')) {
					jQuery('#syllabcentral_view_log_contents').html('<div style="border:1px solid;padding: 2px;max-height: 400px; overflow-y:scroll;">'+response.log_contents+'</div>');
				} else {
					console.response(resp);
				}
			}, { error_callback: function(response, status, error_code, resp) {
					jQuery('#syllabcentral_view_log_container').unblock();
					if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
					console.error(resp.fatal_error_message);
					alert(resp.fatal_error_message);
					} else {
					var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
					console.log(error_message);
					alert(error_message);
					console.log(response);
					}
				}
			});
		} catch (err) {
			jQuery('#syllab_central_key').html();
			console.log(err);
		}
	});
	
	// SyllabCentral
	jQuery('#syllabcentral_keys').on('click', '#syllabcentral_wizard_go', function(e) {
		jQuery('#syllabcentral_wizard_go').hide();
		jQuery('.syllabcentral_wizard_success').remove();
		jQuery('.create_key_container').show();
	});
	
	jQuery('#syllabcentral_keys').on('click', '#syllabcentral_stage1_go', function(e) {
		e.preventDefault();
		jQuery('.syllabcentral_wizard_stage2').hide();
		jQuery('.syllabcentral_wizard_stage1').show();
	});

	jQuery('#syllabcentral_keys').on('click', '#syllabcentral_stage2_go', function(e) {
		e.preventDefault();

		syllabcentral_stage2_go();
	});
	
	jQuery('#syllabcentral_keys').on('click', '#syllabcentral_keycreate_go', function(e) {
		e.preventDefault();
		
		var is_other = jQuery('#syllabcentral_mothership_other').is(':checked') ? true : false;
		
		var key_description = jQuery('#syllabcentral_keycreate_description').val();
		var key_size = jQuery('#syllabcentral_keycreate_keysize').val();

		var where_send = '__syllabpluscom';
		
		data = {
			key_description: key_description,
			key_size: key_size,
		};
		
		if (is_other) {
			where_send = jQuery('#syllabcentral_keycreate_mothership').val();
			if (where_send.substring(0, 4) != 'http') {
				alert(syllablion.enter_mothership_url);
				return;
			}
		}
		
		data.mothership_firewalled = jQuery('#syllabcentral_keycreate_mothership_firewalled').is(':checked') ? 1 : 0;
		data.where_send = where_send;

		jQuery('.create_key_container').hide();
		jQuery('.syllabcentral_wizard_stage1').show();
		jQuery('.syllabcentral_wizard_stage2').hide();
		
		jQuery('#syllabcentral_keys').block({ message: '<div style="margin: 8px; font-size:150%;"><img src="'+syllablion.ud_url+'/images/udlogo-rotating.gif" height="80" width="80" style="padding-bottom:10px;"><br>'+syllablion.creating_please_allow+'</div>'});

		try {
			syllab_send_command('syllabcentral_create_key', data, function(resp) {
				jQuery('#syllabcentral_keys').unblock();
				try {
					if (resp.hasOwnProperty('error')) {
						alert(resp.error);
						console.log(resp);
						return;
					}
					alert(resp.r);

					if (resp.hasOwnProperty('bundle') && resp.hasOwnProperty('keys_guide')) {
						jQuery('#syllabcentral_keys_content').html(resp.keys_guide);
						jQuery('#syllabcentral_keys_content').append('<div class="syllabcentral_wizard_success">'+resp.r+'<br><textarea onclick="this.select();" style="width:620px; height:165px; word-wrap:break-word; border: 1px solid #aaa; border-radius: 3px; padding:4px;">'+resp.bundle+'</textarea></div>');
					} else {
						console.log(resp);
					}

					if (resp.hasOwnProperty('keys_table')) {
						jQuery('#syllabcentral_keys_content').append(resp.keys_table);
					}
					
					jQuery('#syllabcentral_wizard_go').show();

				} catch (err) {
					alert(syllablion.unexpectedresponse+' '+response);
					console.log(err);
				}
			}, { error_callback: function(response, status, error_code, resp) {
					jQuery('#syllabcentral_keys').unblock();
					if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
					console.error(resp.fatal_error_message);
					alert(resp.fatal_error_message);
					} else {
					var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
					console.log(error_message);
					alert(error_message);
					console.log(response);
					}
				}
			});
		} catch (err) {
			jQuery('#syllab_central_key').html();
			console.log(err);
		}
	});
	
	jQuery('#syllabcentral_keys').on('click', '.syllabcentral_key_delete', function(e) {
		e.preventDefault();
		var key_id = jQuery(this).data('key_id');
		if ('undefined' == typeof key_id) {
			console.log("SyllabPlus: .syllabcentral_key_delete clicked, but no key ID found");
			return;
		}

		jQuery('#syllabcentral_keys').block({ message: '<div style="margin: 8px; font-size:150%;"><img src="'+syllablion.ud_url+'/images/udlogo-rotating.gif" height="80" width="80" style="padding-bottom:10px;"><br>'+syllablion.deleting+'</div>'});
		
		syllab_send_command('syllabcentral_delete_key', { key_id: key_id }, function(response) {
			jQuery('#syllabcentral_keys').unblock();
			if (response.hasOwnProperty('keys_table')) {
				jQuery('#syllabcentral_keys_content').html(response.keys_table);
			}
		}, { error_callback: function(response, status, error_code, resp) {
				jQuery('#syllabcentral_keys').unblock();
				if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				alert(resp.fatal_error_message);
				} else {
				var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
				console.log(error_message);
				alert(error_message);
				console.log(response);
				}
			}
		});
	});
	
	jQuery('#syllab_reset_sid').on('click', function(e) {
		e.preventDefault();
		syllab_send_command('reset_site_id', null, function(response) {
			jQuery('#syllab_show_sid').html(response);
		}, { json_parse: false });
	});
	
	jQuery("#syllab-navtab-settings-content form input:not('.udignorechange'), #syllab-navtab-settings-content form select").on('change', function(e) {
		syllab_settings_form_changed = true;
	});
	jQuery("#syllab-navtab-settings-content form input[type='submit']").on('click', function (e) {
		syllab_settings_form_changed = false;
	});
	
	var bigbutton_width = 180;
	jQuery('.syllab-bigbutton').each(function(x,y) {
		var bwid = jQuery(y).width();
		if (bwid > bigbutton_width) bigbutton_width = bwid;
	});
	if (bigbutton_width > 180) jQuery('.syllab-bigbutton').width(bigbutton_width);

	if (jQuery('#syllab-navtab-backups-content').length) {
		// setTimeout(function(){syllab_showlastlog(true);}, 1200);
		setInterval(function() {
			syllab_activejobs_update(false);}, 1250);
	}
	
	// Prevent profusion of notices
	setTimeout(function() {
		jQuery('#setting-error-settings_updated').slideUp();}, 5000);
	
	jQuery('#syllab_restore_db').on('change', function() {
		if (jQuery('#syllab_restore_db').is(':checked') && 1 == jQuery(this).data('encrypted')) {
			jQuery('#syllab_restorer_dboptions').slideDown();
		} else {
			jQuery('#syllab_restorer_dboptions').slideUp();
		}
	});

	syllab_check_same_times();

	var syllab_message_modal_buttons = {};
	syllab_message_modal_buttons[syllablion.close] = function() {
		 jQuery(this).dialog("close");
	};
	jQuery("#syllab-message-modal").dialog({
		autoOpen: false, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			$(this).dialog('option', 'width', 520);
			$(this).dialog('option', 'minHeight', 260);
			if ($(window).height() > 360 ) {
				$(this).dialog('option', 'height', 360);
			} else {
				$(this).dialog('option', 'height', $(window).height()-30);
			}
		},
		modal: true,
		buttons: syllab_message_modal_buttons
	});
	
	var syllab_delete_modal_buttons = {};
	syllab_delete_modal_buttons[syllablion.deletebutton] = function() {
		syllab_remove_backup_sets(0, 0, 0, 0);
	};


	function syllab_remove_backup_sets(deleted_counter, backup_local, backup_remote, backup_sets) {
		jQuery("#syllab-delete-modal").dialog('close');
		var deleted_files_counter = deleted_counter;
		var local_deleted = backup_local;
		var remote_deleted = backup_remote;
		var sets_deleted = backup_sets;
		var timestamps = jQuery('#syllab_delete_timestamp').val().split(',');
		var error_log_prompt = '';
		
		var form_data = jQuery('#syllab_delete_form').serializeArray();
		var data = {};

		$.each(form_data, function() {
			if (undefined !== data[this.name]) {
				if (!data[this.name].push) {
					data[this.name] = [data[this.name]];
				}
				data[this.name].push(this.value || '');
			} else {
				data[this.name] = this.value || '';
			}
		});

		if (data.delete_remote) {
			jQuery('#syllab-delete-waitwarning').find('.syllab-deleting-remote').show();
		} else {
			jQuery('#syllab-delete-waitwarning').find('.syllab-deleting-remote').hide();
		}
		
		jQuery('#syllab-delete-waitwarning').slideDown().addClass('active');

		data.remote_delete_limit = syllablion.remote_delete_limit;
		
		delete data.action;
		delete data.subaction;
		delete data.nonce;
		
		syllab_send_command('deleteset', data, function(resp) {
			if (!resp.hasOwnProperty('result') || resp.result == null) {
				jQuery('#syllab-delete-waitwarning').slideUp();
				return;
			}
			if (resp.result == 'error') {
				jQuery('#syllab-delete-waitwarning').slideUp();
				alert(syllablion.error+' '+resp.message);
			} else if (resp.result == 'continue') {
				deleted_files_counter = deleted_files_counter + resp.backup_local + resp.backup_remote;
				local_deleted = local_deleted + resp.backup_local;
				remote_deleted = remote_deleted + resp.backup_remote;
				sets_deleted = sets_deleted + resp.backup_sets;
				var deleted_timestamps = resp.deleted_timestamps.split(',');
				for (var i = 0; i < deleted_timestamps.length; i++) {
					var timestamp = deleted_timestamps[i];
					jQuery('#syllab-navtab-backups-content .syllab_existing_backups_row_' + timestamp).slideUp().remove();
				}
				jQuery('#syllab_delete_timestamp').val(resp.timestamps);
				jQuery('#syllab-deleted-files-total').text(deleted_files_counter + ' ' + syllablion.remote_files_deleted);
				syllab_remove_backup_sets(deleted_files_counter, local_deleted, remote_deleted, sets_deleted);
			} else if (resp.result == 'success') {

				setTimeout(function() {
					jQuery('#syllab-deleted-files-total').text('');
					jQuery('#syllab-delete-waitwarning').slideUp();
				}, 500);
				
				update_backupnow_modal(resp);

				if (resp.hasOwnProperty('backupnow_file_entities')) {
					impossible_increment_entities = resp.backupnow_file_entities;
				}

				if (resp.hasOwnProperty('count_backups')) {
					jQuery('#syllab-existing-backups-heading').html(syllablion.existing_backups+' <span class="syllab_existing_backups_count">'+resp.count_backups+'</span>');
				}
				for (var i = 0; i < timestamps.length; i++) {
					var timestamp = timestamps[i];
					jQuery('#syllab-navtab-backups-content .syllab_existing_backups_row_'+timestamp).slideUp().remove();
				}

				syllab_backups_selection.checkSelectionStatus();

				syllab_history_lastchecksum = false;

				local_deleted = local_deleted + resp.backup_local;
				remote_deleted = remote_deleted + resp.backup_remote;
				sets_deleted = sets_deleted + resp.backup_sets;
				if ('' != resp.error_messages) {
					error_log_prompt = syllablion.delete_error_log_prompt;
				}
					
				setTimeout(function() {
					alert(resp.set_message + " " + sets_deleted + "\n" + resp.local_message + " " + local_deleted + "\n" + resp.remote_message + " " + remote_deleted + "\n\n" + resp.error_messages + "\n" + error_log_prompt);
				}, 900);
			}
		});
	};

	syllab_delete_modal_buttons[syllablion.cancel] = function() {
 jQuery(this).dialog("close"); };
	jQuery("#syllab-delete-modal").dialog({
		autoOpen: false, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			$(this).css('minHeight', 83);
		},
		modal: true,
		buttons: syllab_delete_modal_buttons
	});

	var syllab_restore_modal = {
		initialized: false,
		init: function() {
			if (this.initialized) return;
			
			this.initialized = true;

			// Setup cancel button events
			$('.syllab-restore--cancel').on('click', function(e) {
				e.preventDefault();
				this.close();
			}.bind(this));

			this.default_next_text = $('.syllab-restore--next-step').eq(0).text();

			// Setup next button event
			$('.syllab-restore--next-step').on('click', function(e) {
				e.preventDefault();
				this.process_next_action();
			}.bind(this));
		},
		close: function() {
			$('.syllab_restore_container').hide();
			$('body').removeClass('syllab-modal-is-opened');
		},
		open: function() {
			this.init();
			// reset elements
			$('#syllab-restore-modal-stage1').show();
			$('#syllab-restore-modal-stage2').hide();
			$('#syllab-restore-modal-stage2a').html('');
			$('.syllab-restore--next-step').text(this.default_next_text);
			$('.syllab-restore--stages li').removeClass('active').first().addClass('active');
			// Show restoration window
			$('.syllab_restore_container').show();
			$('body').addClass('syllab-modal-is-opened');
		},
		process_next_action: function() {
			var anyselected = 0;
			var moreselected = 0;
			var dbselected = 0;
			var whichselected = [];
			// Make a list of what files we want
			var already_added_wpcore = 0;
			var meta_foreign = $('#syllab_restore_meta_foreign').val();
			$('input[name="syllab_restore[]"]').each(function(x, y) {
				if ($(y).is(':checked') && !$(y).is(':disabled')) {
					anyselected = 1;
					var howmany = $(y).data('howmany');
					var type = $(y).val();
					if ('more' == type) moreselected = 1;
					if ('db' == type) dbselected = 1;
					if (1 == meta_foreign || (2 == meta_foreign && 'db' != type)) {
						if ('wpcore' != type) {
							howmany = $('#syllab_restore_form #syllab_restore_wpcore').data('howmany');
						}
						type = 'wpcore';
					}
					if ('wpcore' != type || already_added_wpcore == 0) {
						var restobj = [ type, howmany ];
						whichselected.push(restobj);
						// alert($(y).val());
						if ('wpcore' == type) { already_added_wpcore = 1; }
					}
				}
			});
			if (1 == anyselected) {
				// Work out what to download
				if (1 == syllab_restore_stage) {
					// meta_foreign == 1 : All-in-one format: the only thing to download, always, is wpcore
					// if ('1' == meta_foreign) {
					// whichselected = [];
					// whichselected.push([ 'wpcore', 0 ]);
					// } else if ('2' == meta_foreign) {
					// $(whichselected).each(function(x,y) {
					// restobj = whichselected[x];
					// });
					// whichselected = [];
					// whichselected.push([ 'wpcore', 0 ]);
					// }
					$('.syllab-restore--stages li').removeClass('active').eq(1).addClass('active');
					$('#syllab-restore-modal-stage1').slideUp('slow');
					$('#syllab-restore-modal-stage2').show();
					syllab_restore_stage = 2;
					var pretty_date = $('.syllab_restore_date').first().text();
					// Create the downloader active widgets
	
					// See if we some are already known to be downloaded - in which case, skip creating the download widget. (That saves on HTTP round-trips, as each widget creates a new POST request. Of course, this is at the expense of one extra one here).
					var which_to_download = whichselected;
					var backup_timestamp = $('#syllab_restore_timestamp').val();
	
					try {
						$('.syllab-restore--next-step').prop('disabled', true);
						$('#syllab-restore-modal-stage2a').html('<span class="dashicons dashicons-update rotate"></span> '+syllablion.maybe_downloading_entities);
						syllab_send_command('whichdownloadsneeded', {
							downloads: whichselected,
							timestamp: backup_timestamp
						}, function(response) {
							$('.syllab-restore--next-step').prop('disabled', false);
							if (response.hasOwnProperty('downloads')) {
								console.log('SyllabPlus: items which still require downloading follow');
								which_to_download = response.downloads;
								console.log(which_to_download);
							}
	
							// Kick off any downloads, if needed
							if (0 == which_to_download.length) {
								syllab_restorer_checkstage2(0);
							} else {
								for (var i=0; i<which_to_download.length; i++) {
									// syllab_downloader(base, backup_timestamp, what, whicharea, set_contents, prettydate, async)
									syllab_downloader('udrestoredlstatus_', backup_timestamp, which_to_download[i][0], '#ud_downloadstatus2', which_to_download[i][1], pretty_date, false);
								}
							}

						}, { alert_on_error: false, error_callback: function(response, status, error_code, resp) {
								if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
								console.error(resp.fatal_error_message);
								$('#syllab-restore-modal-stage2a').html('<p style="color:red;">'+resp.fatal_error_message+'</p>');
								} else {
								var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
								$('#syllab-restore-modal-stage2a').html('<p style="color:red; margin: 5px;">'+error_message+'</p>');
								console.log(error_message);
								console.log(response);
								}
							}
						});
					} catch (err) {
						console.log("SyllabPlus: error (follows) when looking for items needing downloading");
						console.log(err);
						alert(syllablion.jsonnotunderstood);
					}
	
					// Make sure all are downloaded
				} else if (2 == syllab_restore_stage) {
					syllab_restorer_checkstage2(1);
				} else if (3 == syllab_restore_stage) {
					var continue_restore = 1;
					jQuery('.syllab-restore--next-step, .syllab-restore--cancel').prop('disabled', true);
					$('#syllab_restoreoptions_ui input.required').each(function(index) {
						if (continue_restore == 0) return;
						var sitename = $(this).val();
						if (sitename == '') {
							alert(syllablion.pleasefillinrequired);
							continue_restore = 0;
						} else if ($(this).attr('pattern') != '') {
							var pattern = $(this).attr('pattern');
							var re = new RegExp(pattern, "g");
							if (!re.test(sitename)) {
								alert($(this).data('invalidpattern'));
								continue_restore = 0;
							}
						}
					});

					if (1 == dbselected) {
						
						anyselected = 0;
					
						jQuery('input[name="syllab_restore_table_options[]"').each(function (x, y) {
							if (jQuery(y).is(':checked') && !jQuery(y).is(':disabled')) anyselected = 1;
						});
						
						if (0 == anyselected && !skipped_db_scan) {
							alert(syllablion.youdidnotselectany);
							jQuery('.syllab-restore--next-step, .syllab-restore--cancel').prop('disabled', false);
							return;
						}
					}

					if (1 == moreselected) {
					
						anyselected = 0;
					
						jQuery('input[name="syllab_include_more_index[]"').each(function (x, y) {
							if (jQuery(y).is(':checked') && !jQuery(y).is(':disabled')) {
								anyselected = 1;
								if ('' == jQuery('#syllab_include_more_path_restore' + x).val()) {
									alert(syllablion.emptyrestorepath);
								}
							}
						});
						
						if (0 == anyselected) {
							alert(syllablion.youdidnotselectany);
							jQuery('.syllab-restore--next-step, .syllab-restore--cancel').prop('disabled', false);
							return;
						}
					}

					if (!continue_restore) return;
					var restore_options = $('#syllab_restoreoptions_ui select, #syllab_restoreoptions_ui input').serialize();

					// jQuery serialize does not pick up unchecked checkboxes, but we want to include these so that we have a list of tables the user does not want to backup we prepend these with slp-skip-table- and check this on the backend
					jQuery.each(jQuery('input[name="syllab_restore_table_options[]').filter(function(idx) {
						return jQuery(this).prop('checked') === false
					}), function(idx, el) {
						restore_options += '&' + jQuery(el).attr('name') + '=' + 'slp-skip-table-' + jQuery(el).val();
					});

					console.log("Restore options: "+restore_options);

					if (typeof php_max_input_vars !== 'undefined') {
						var restore_options_length = restore_options.split("&").length;
						var warning_template_start = '<div class="notice notice-warning"><p><span class="dashicons dashicons-warning"></span> <strong>' + syllablion.warnings +'</strong></p><ul id="syllab_restore_warnings">';
						var warning_template_end = '</ul></div>';

						// If we can't detect the php_max_input_vars assume the PHP default of 1000
						if (!php_max_input_vars && 1000 <= restore_options_length) {
							console.log('Restore options: ' + restore_options_length + ' PHP max input vars not detected; using default: 1000');
						} else if (php_max_input_vars && restore_options_length >= php_max_input_vars) {
							var warning = '<li>' + syllablion.php_max_input_vars_detected_warning + '</li>';
							if (1 != jQuery('#syllab-restore-modal-stage2a .notice-warning').length) {
								var final_warning = warning_template_start + warning + warning_template_end;
								jQuery('#syllab_restoreoptions_ui').prepend(final_warning);
							} else {
								jQuery('#syllab-restore-modal-stage2a #syllab_restore_warnings').append(warning);
							}
							console.log('Restore options: ' + restore_options_length + ' PHP max input vars: ' + php_max_input_vars);
							jQuery('.syllab-restore--next-step, .syllab-restore--cancel').prop('disabled', false);
							php_max_input_vars = undefined;
							return;
						}
					}

					$('#syllab_restorer_restore_options').val(restore_options);
					// This must be done last, as it wipes out the section with #syllab_restoreoptions_ui
					$('#syllab-restore-modal-stage2a').html(syllablion.restore_proceeding);
					$('#syllab_restore_form').trigger('submit');
					// In progress; prevent the button being pressed again
					syllab_restore_stage = 4;
				}
			} else {
				alert(syllablion.youdidnotselectany);
			}
		}
	}

	jQuery("#syllab-iframe-modal").dialog({
		autoOpen: false, height: 500, width: 780, modal: true
	});

	jQuery("#syllab-backupnow-inpage-modal").dialog({
		autoOpen: false, modal: true, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			$(this).dialog('option', 'width', 580);
			$(this).dialog('option', 'minHeight', 261);
			$(this).dialog('option', 'height', 380);
		},
	});
	
	var backupnow_modal_buttons = {};
	backupnow_modal_buttons[syllablion.backupnow] = function() {

		var backupnow_nodb = jQuery('#backupnow_includedb').is(':checked') ? 0 : 1;
		var backupnow_nofiles = jQuery('#backupnow_includefiles').is(':checked') ? 0 : 1;
		var backupnow_nocloud = jQuery('#backupnow_includecloud').is(':checked') ? 0 : 1;
		var db_anon_all = jQuery('#backupnow_db_anon_all').is(':checked') ? 1 : 0;
		var db_anon_non_staff = jQuery('#backupnow_db_anon_non_staff').is(':checked') ? 1 : 0;
		var onlythesetableentities = backupnow_whichtables_checked('');
		var always_keep = jQuery('#always_keep').is(':checked') ? 1 : 0;
		var incremental = ('incremental' == jQuery('#syllab-backupnow-modal').data('backup-type')) ? 1 : 0;

		if (syllablion.hosting_restriction.includes('only_one_backup_per_month') && !incremental) {
			alert(syllablion.hosting_restriction_one_backup_permonth);
			return;
		}

		if (syllablion.hosting_restriction.includes('only_one_incremental_per_day') && incremental) {
			alert(syllablion.hosting_restriction_one_incremental_perday);
			return;
		}

		if ('' == onlythesetableentities && 0 == backupnow_nodb) {
			alert(syllablion.notableschosen);
			jQuery('#backupnow_database_moreoptions').show();
			return;
		}

		if (typeof onlythesetableentities === 'boolean') {
			onlythesetableentities = null;
		}

		var onlythesefileentities = backupnow_whichfiles_checked('');

		if ('' == onlythesefileentities && 0 == backupnow_nofiles) {
			alert(syllablion.nofileschosen);
			jQuery('#backupnow_includefiles_moreoptions').show();
			return;
		}

		var only_these_cloud_services = jQuery("input[name^='syllab_include_remote_service_']").serializeArray();

		if ('' == only_these_cloud_services && 0 == backupnow_nocloud) {
			alert(syllablion.nocloudserviceschosen);
			jQuery('#backupnow_includecloud_moreoptions').show();
			return;
		}

		if (typeof only_these_cloud_services === 'boolean') {
			only_these_cloud_services = null;
		}
		
		if (backupnow_nodb && backupnow_nofiles) {
			alert(syllablion.excludedeverything);
			return;
		}
		
		jQuery(this).dialog("close");

		setTimeout(function() {
			jQuery('#syllab_lastlogmessagerow').fadeOut('slow', function() {
				jQuery(this).fadeIn('slow');
			});
		}, 1700);
	
		syllab_backupnow_go(backupnow_nodb, backupnow_nofiles, backupnow_nocloud, onlythesefileentities, {always_keep: always_keep, incremental: incremental, db_anon_all: db_anon_all, db_anon_non_staff: db_anon_non_staff}, jQuery('#backupnow_label').val(), onlythesetableentities, only_these_cloud_services);
	};
	backupnow_modal_buttons[syllablion.cancel] = function() {
	jQuery(this).dialog("close"); };

	jQuery("#syllab-backupnow-modal").dialog({
		autoOpen: false, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			$(this).dialog('option', 'width', 610);
			$(this).dialog('option', 'minHeight', 300);
			$(this).dialog('option', 'height', 472);
		},
		modal: true,
		buttons: backupnow_modal_buttons,
		create: function () {
			$(this).closest(".ui-dialog")
				.find(".ui-dialog-buttonpane .ui-button").first() // the first button
				.addClass("js-tour-backup-now-button");
		}
	});

	jQuery("#syllab-poplog").dialog({
		autoOpen: false, modal: true, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			$(this).dialog('option', 'width', 860);
			$(this).dialog('option', 'minHeight', 260);
			if ($(window).height() > 600) {
				$(this).dialog('option', 'height', 600);
			} else {
				$(this).dialog('option', 'height', $(window).height()-50);
			}
		},
	});
	
	jQuery('#syllab-navtab-settings-content .enableexpertmode').on('click', function() {
		jQuery('#syllab-navtab-settings-content .expertmode').fadeIn();
		jQuery('#syllab-navtab-settings-content .enableexpertmode').off('click');
		return false;
	});
	
	jQuery('#syllab-navtab-settings-content .backupdirrow').on('click', 'a.syllab_backup_dir_reset', function() {
		jQuery('#syllab_dir').val('syllab'); return false;
	});

	function setup_file_entity_exclude_field(field, instant) {
		if (jQuery('#syllab-navtab-settings-content #syllab_include_'+field).is(':checked')) {
			if (instant) {
				jQuery('#syllab-navtab-settings-content #syllab_include_'+field+'_exclude_container').show();
			} else {
				jQuery('#syllab-navtab-settings-content #syllab_include_'+field+'_exclude_container').slideDown();
			}
		} else {
			if (instant) {
				jQuery('#syllab-navtab-settings-content #syllab_include_'+field+'_exclude').hide();
			} else {
				jQuery('#syllab-navtab-settings-content #syllab_include_'+field+'_exclude_container').slideUp();
			}
		}
	}
	
	jQuery('#syllab-navtab-settings-content .syllab_include_entity').on('click', function() {
		var has_exclude_field = jQuery(this).data('toggle_exclude_field');
		if (has_exclude_field) {
			setup_file_entity_exclude_field(has_exclude_field, false);
		}
	});
	
	jQuery('.syllab_exclude_entity_container').on('click', '.syllab_exclude_entity_delete', function(event) {
		event.preventDefault();
		if (!confirm(syllablion.exclude_rule_remove_conformation_msg))  return;
		
		var include_entity_name = jQuery(this).data('include-backup-file');
		jQuery.when(
			jQuery(this).closest('.syllab_exclude_entity_wrapper').remove()
		).then(
			syllab_exclude_entity_update(include_entity_name)
		);
	});
	
	jQuery('.syllab_exclude_entity_container').on('click', '.syllab_exclude_entity_edit', function(event) {
		event.preventDefault();
		var wrapper = jQuery(this).hide().closest('.syllab_exclude_entity_wrapper');
		var input = wrapper.find('input');
		input.prop('readonly', false).trigger('focus');

		// place carret at the end of the text
		var input_val = input.val();
		input.val('');
		input.val(input_val);

		wrapper.find('.syllab_exclude_entity_update').addClass('is-active').show();
	});
	
	jQuery('.syllab_exclude_entity_container').on('click', '.syllab_exclude_entity_update', function(event) {
		event.preventDefault();
		var wrapper = jQuery(this).closest('.syllab_exclude_entity_wrapper');
		var include_backup_file = jQuery(this).data('include-backup-file')
		var exclude_item_val = wrapper.find('input').val().trim();
		
		var should_be_updated = false;
		if (exclude_item_val == wrapper.find('input').data('val')) {
			should_be_updated = true;
		} else if (syllab_is_unique_exclude_rule(exclude_item_val, include_backup_file)) {
			should_be_updated = true;
		}
		
		if (should_be_updated) {
			jQuery(this).hide().removeClass('is-active');
			jQuery.when(
				wrapper.find('input').prop('readonly', 'readonly').data('val', exclude_item_val)
			).then(function() {
				wrapper.find('.syllab_exclude_entity_edit').show();
				syllab_exclude_entity_update(include_backup_file);
			});
		}
	});
	
	jQuery('#syllab_exclude_modal').dialog({
		autoOpen: false, modal: true, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event,ui) {
			$(this).parent().trigger('focus');
			$(this).dialog('option', 'width', 520);
			$(this).dialog('option', 'minHeight', 260);
			if ($(window).height() > 579) {
				$(this).css('height', 'auto');
			} else if ($(window).height() < 580 && $(window).height() > 410) {
				$(this).dialog('option', 'height', 410);
				$(this).css('height', 'auto');
			} else {
				$(this).dialog('option', 'height', $(window).height()-20);
			}
		}
	});
	
	jQuery('.syllab_exclude_container .syllab_add_exclude_item').on('click', function(event) {
		event.preventDefault();
		var backup_entity = jQuery(this).data('include-backup-file');
		jQuery('#syllab_exclude_modal_for').val(backup_entity);
		jQuery('#syllab_exclude_modal_path').val(jQuery(this).data('path'));
		if ('uploads' == backup_entity) {
			jQuery('#syllab-exclude-file-dir-prefix').html(jQuery('#syllab-exclude-upload-base-dir').val());
		}
		jQuery('.syllab-exclude-modal-reset').trigger('click');
		jQuery('#syllab_exclude_modal').dialog('open');
	});
	
	jQuery('.syllab-exclude-link').on('click', function(event) {
		event.preventDefault();
		var panel = jQuery(this).data('panel');
		if ('file-dir' == panel) {
			jQuery('#syllab_exclude_files_folders_jstree').jstree({
				"core": {
					"multiple": false,
					"data": function (nodeid, callback) {
						syllab_send_command('get_jstree_directory_nodes', {entity: 'filebrowser', node:nodeid, path: jQuery('#syllab_exclude_modal_path').val(), findex: 0, skip_root_node: true}, function(response) {
						if (response.hasOwnProperty('error')) {
									alert(response.error);
													   } else {
		 callback.call(this, response.nodes);
													   }
						}, { error_callback: function(response, status, error_code, resp) {
								if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
								console.error(resp.fatal_error_message);
								jQuery('#syllab_zip_files_jstree').html('<p style="color:red; margin: 5px;">'+resp.fatal_error_message+'</p>');
								alert(resp.fatal_error_message);
									} else {
								var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
								jQuery('#syllab_zip_files_jstree').html('<p style="color:red; margin: 5px;">'+error_message+'</p>');
								console.log(error_message);
								alert(error_message);
								console.log(response);
									}
								}
						});
					},
					"error": function(error) {
						alert(error);
						console.log(error);
					},
				},
				"search": {
					"show_only_matches": true
				},
				"plugins": ["sort"],
			});
		} else if ('contain-clause' == panel) {
			jQuery('#syllab_exclude_files_folders_wildcards_jstree').jstree({
				"core": {
					"multiple": false,
					"data": function (nodeid, callback) {
						syllab_send_command('get_jstree_directory_nodes', {entity: 'filebrowser', directories_only: 1, node:nodeid, path: jQuery('#syllab_exclude_modal_path').val(), findex: 0, skip_root_node: 0}, function(response) {
						if (response.hasOwnProperty('error')) {
									alert(response.error);
													   } else {
		 callback.call(this, response.nodes);
													   }
						}, { error_callback: function(response, status, error_code, resp) {
								if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
								console.error(resp.fatal_error_message);
								jQuery('#syllab_zip_files_jstree').html('<p style="color:red; margin: 5px;">'+resp.fatal_error_message+'</p>');
								alert(resp.fatal_error_message);
									} else {
								var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
								jQuery('#syllab_zip_files_jstree').html('<p style="color:red; margin: 5px;">'+error_message+'</p>');
								console.log(error_message);
								alert(error_message);
								console.log(response);
									}
								}
						});
					},
					"error": function(error) {
						alert(error);
						console.log(error);
					},
				},
				"search": {
					"show_only_matches": true
				},
				"plugins": ["sort"],
			});
		}
		jQuery('#syllab_exclude_modal_main').slideUp();
		jQuery('.syllab-exclude-panel').hide();
		jQuery('.syllab-exclude-panel[data-panel='+panel+']').slideDown();
	});
	
	jQuery('.syllab-exclude-modal-reset').on('click', function(event) {
		event.preventDefault();
		jQuery('#syllab_exclude_files_folders_jstree').jstree("destroy");
		jQuery('#syllab_exclude_files_folders_wildcards_jstree').jstree("destroy");
		jQuery('#syllab_exclude_extension_field').val('');
		jQuery('#syllab_exclude_prefix_field').val('');
		jQuery('.syllab-exclude-panel').slideUp();
		jQuery('#syllab_exclude_modal_main').slideDown();
	});
	
	jQuery('.syllab-exclude-submit').on('click', function() {
		var panel = jQuery(this).data('panel');
		var exclude_item_val = '';
		switch (panel) {
			case 'file-dir':
			var exclude_jstree_selected = jQuery("#syllab_exclude_files_folders_jstree").jstree("get_selected");
			if (0 == exclude_jstree_selected.length) {
					alert(syllablion.exclude_select_file_or_folder_msg);
					return;
				}
			var selected_file_or_folder = exclude_jstree_selected[0];
			var prefix_path = jQuery('#syllab_exclude_modal_path').val();
			if (selected_file_or_folder.substr(0, prefix_path.length) == prefix_path) {
					selected_file_or_folder = selected_file_or_folder.substr(prefix_path.length, selected_file_or_folder.length);
				}
			if ('/' == selected_file_or_folder.charAt(0))  selected_file_or_folder = selected_file_or_folder.substr(1);
			if ('/' == selected_file_or_folder.charAt(selected_file_or_folder.length - 1))  selected_file_or_folder = selected_file_or_folder.substr(0, selected_file_or_folder.length - 1);
			exclude_item_val = selected_file_or_folder;
				break;
				
			case 'extension':
			var exclude_extension = jQuery('#syllab_exclude_extension_field').val();
			if ('' == exclude_extension) {
					alert(syllablion.exclude_type_ext_msg);
					return;
				}
			if (!exclude_extension.match(/^[0-9a-zA-Z]+$/)) {
					alert(syllablion.exclude_ext_error_msg);
					return;
				}
			exclude_item_val = 'ext:'+exclude_extension;
				break;
				
			case 'begin-with':
			var prefix = jQuery('#syllab_exclude_prefix_field').val();
			if ('' == prefix) {
					alert(syllablion.exclude_type_prefix_msg);
					return;
				}
			if (!prefix.match(/^\s*[a-z-_\d,\s]+\s*$/i)) {
					alert(syllablion.exclude_prefix_error_msg);
					return;
				}
			exclude_item_val = 'prefix:'+prefix;
				break;
				
			case 'contain-clause':
				var exclude_jstree_selected = jQuery("#syllab_exclude_files_folders_wildcards_jstree").jstree("get_selected");
				if (0 == exclude_jstree_selected.length) {
					alert(syllablion.exclude_select_folder_wildcards_msg);
					return;
				}
				var clause_val = jQuery(this).parents('div.syllab-exclude-panel').find('div.clause-input-container input').val();
				jQuery(this).parents('div.syllab-exclude-panel').find('div.clause-input-container input').val('');
				var clause_type = jQuery(this).parents('div.syllab-exclude-panel').find('div.clause-input-container select').val();
				if ('' == clause_val) {
					alert(syllablion.exclude_contain_error_msg);
					return;
				}
				jQuery(this).parents('div.syllab-exclude-panel').find('div.clause-input-container select option').eq(0).prop('selected', true);
				var selected_file_or_folder = exclude_jstree_selected[0];
				var prefix_path = jQuery('#syllab_exclude_modal_path').val();
				if (selected_file_or_folder.substr(0, prefix_path.length) == prefix_path) {
						selected_file_or_folder = selected_file_or_folder.substr(prefix_path.length, selected_file_or_folder.length);
				}
				if ('/' == selected_file_or_folder.charAt(0))  selected_file_or_folder = selected_file_or_folder.substr(1);
				if ('/' == selected_file_or_folder.charAt(selected_file_or_folder.length - 1))  selected_file_or_folder = selected_file_or_folder.substr(0, selected_file_or_folder.length - 1);
				exclude_item_val = selected_file_or_folder;
				if ('' !== exclude_item_val) exclude_item_val += '/';
				clause_val = clause_val.replace(/\*/g, '\\*');
				if ('beginning' === clause_type) {
					exclude_item_val += clause_val + '*';
				} else if ('middle' === clause_type) {
					exclude_item_val += '*' + clause_val + '*';
				} else if ('end' === clause_type) {
					exclude_item_val += '*' + clause_val;
				}
				break;
		
			default:
				return;
		}
		
		var include_backup_file = jQuery('#syllab_exclude_modal_for').val();
		if (!syllab_is_unique_exclude_rule(exclude_item_val, include_backup_file))  return;
		
		var exclude_entity_html = '<div class="syllab_exclude_entity_wrapper"><input type="text" class="syllab_exclude_entity_field syllab_include_' + include_backup_file + '_exclude_entity" name="syllab_include_' + include_backup_file + '_exclude_entity[]" value="' + exclude_item_val + '" data-val="' + exclude_item_val + '" data-include-backup-file="' + include_backup_file + '" readonly="readonly"><a href="#" class="syllab_exclude_entity_edit dashicons dashicons-edit" data-include-backup-file="' + include_backup_file + '"></a><a href="#" class="syllab_exclude_entity_update dashicons dashicons-yes" data-include-backup-file="' + include_backup_file + '" style="display: none;"></a><a href="#" class="syllab_exclude_entity_delete dashicons dashicons-no" data-include-backup-file="' + include_backup_file + '"></a></div>';
		jQuery('.syllab_exclude_entity_container[data-include-backup-file="' + include_backup_file + '"]').append(exclude_entity_html);
		syllab_exclude_entity_update(include_backup_file);
		jQuery('#syllab_exclude_modal').dialog('close');
	});
	
	// TODO: This is suspected to be obsolete. Confirm + remove.
	jQuery('#syllab-navtab-settings-content .syllab-service').on('change', function() {
		var active_class = jQuery(this).val();
		jQuery('#syllab-navtab-settings-content .syllabplusmethod').hide();
		jQuery('#syllab-navtab-settings-content .'+active_class).show();
	});

	jQuery('#syllab-navtab-settings-content a.syllab_show_decryption_widget').on('click', function(e) {
		e.preventDefault();
		jQuery('#syllabplus_db_decrypt').val(jQuery('#syllab_encryptionphrase').val());
		jQuery('#syllab-manualdecrypt-modal').slideToggle();
	});
	
	jQuery('#syllabplus-phpinfo').on('click', function(e) {
		e.preventDefault();
		syllab_iframe_modal('phpinfo', syllablion.phpinfo);
	});

	jQuery('#syllabplus-rawbackuphistory').on('click', function(e) {
		e.preventDefault();
		syllab_iframe_modal('rawbackuphistory', syllablion.raw);
	});

	// + Added addons navtab
	jQuery('#syllab-navtab-status').on('click', function(e) {
		e.preventDefault();
		syllab_open_main_tab('status');
		syllab_page_is_visible = 1;
		syllab_console_focussed_tab = 'status';
		// Refresh the console, as its next update might be far away
		syllab_activejobs_update(true);
	});
	jQuery('#syllab-navtab-expert').on('click', function(e) {
		e.preventDefault();
		syllab_open_main_tab('expert');
		syllab_page_is_visible = 1;
	});
	jQuery('#syllab-navtab-settings, #syllab-navtab-settings2, #syllab_backupnow_gotosettings').on('click', function(e) {
		e.preventDefault();
		// These next two should only do anything if the relevant selector was clicked
		jQuery(this).parents('.syllabmessage').remove();
		jQuery('#syllab-backupnow-modal').dialog('close');
		syllab_open_main_tab('settings');
		syllab_page_is_visible = 1;
	});
	jQuery('#syllab-navtab-addons').on('click', function(e) {
		e.preventDefault();
		jQuery(this).addClass('b#nav-tab-active');
		syllab_open_main_tab('addons');
		syllab_page_is_visible = 1;
	});

	jQuery('#syllab-navtab-backups').on('click', function(e) {
		e.preventDefault();
		syllab_console_focussed_tab = 'backups';
		syllab_historytimertoggle(1);
		syllab_open_main_tab('backups');
	});
	
	jQuery('#syllab-navtab-migrate').on('click', function(e) {
		e.preventDefault();
		jQuery('#syllab_migrate_tab_alt').html('').hide();
		syllab_open_main_tab('migrate');
		syllab_page_is_visible = 1;
		if (!jQuery('#syllab_migrate .syllab_migrate_widget_module_content').is(':visible')) {
			jQuery('.syllab_migrate_intro').show();
		}
	});
	
	if ('migrate' == syllablion.tab)  jQuery('#syllab-navtab-migrate').trigger('click');

	syllab_send_command('ping', null, function(data, response) {
		if ('success' == response && data != 'pong' && data.indexOf('pong')>=0) {
			jQuery('#syllab-navtab-backups-content .ud-whitespace-warning').show();
			console.log("SyllabPlus: Extra output warning: response (which should be just (string)'pong') follows.");
			console.log(data);
		}
	}, { json_parse: false, type: 'GET' });

	// Section: Plupload
	try {
		if (typeof syllab_plupload_config !== 'undefined') {
			plupload_init();
		}
	} catch (err) {
		console.log(err);
	}
	
	function plupload_init() {
	
		// create the uploader and pass the config from above
		var uploader = new plupload.Uploader(syllab_plupload_config);

		// checks if browser supports drag and drop upload, makes some css adjustments if necessary
		uploader.bind('Init', function(up) {
			var uploaddiv = jQuery('#plupload-upload-ui');
			
			if (up.features.dragdrop) {
				uploaddiv.addClass('drag-drop');
				jQuery('#drag-drop-area')
				.on('dragover.wp-uploader', function() {
 uploaddiv.addClass('drag-over'); })
				.on('dragleave.wp-uploader, drop.wp-uploader', function() {
 uploaddiv.removeClass('drag-over'); });
				
			} else {
				uploaddiv.removeClass('drag-drop');
				jQuery('#drag-drop-area').off('.wp-uploader');
			}
		});
					
		uploader.init();

		// a file was added in the queue
		uploader.bind('FilesAdded', function(up, files) {
		// var hundredmb = 100 * 1024 * 1024, max = parseInt(up.settings.max_file_size, 10);
		
		plupload.each(files, function(file) {
				// @codingStandardsIgnoreLine
				if (! /^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-[\-a-z]+([0-9]+?)?(\.(zip|gz|gz\.crypt))?$/i.test(file.name) && ! /^log\.([0-9a-f]{12})\.txt$/.test(file.name)) {
					var accepted_file = false;
					for (var i = 0; i<syllab_accept_archivename.length; i++) {
						if (syllab_accept_archivename[i].test(file.name)) {
							var accepted_file = true;
						}
					}
					if (!accepted_file) {
						if (/\.(zip|tar|tar\.gz|tar\.bz2)$/i.test(file.name) || /\.sql(\.gz)?$/i.test(file.name)) {
							jQuery('#syllab-message-modal-innards').html('<p><strong>'+file.name+"</strong></p> "+syllablion.notarchive2);
							jQuery('#syllab-message-modal').dialog('open');
						} else {
							alert(file.name+": "+syllablion.notarchive);
						}
						uploader.removeFile(file);
						return;
					}
					}
			
				// a file was added, you may want to update your DOM here...
				jQuery('#filelist').append(
					'<div class="file" id="' + file.id + '"><b>' +
					file.name + '</b> (<span>' + plupload.formatSize(0) + '</span>/' + plupload.formatSize(file.size) + ') ' +
				'<div class="fileprogress"></div></div>');
		});
			
			up.refresh();
			up.start();
		});
			
		uploader.bind('UploadProgress', function(up, file) {
			jQuery('#' + file.id + " .fileprogress").width(file.percent + "%");
			jQuery('#' + file.id + " span").html(plupload.formatSize(parseInt(file.size * file.percent / 100)));

			if (file.size == file.loaded) {
				jQuery('#' + file.id).html('<div class="file" id="' + file.id + '"><b>' +
				file.name + '</b> (<span>' + plupload.formatSize(parseInt(file.size * file.percent / 100)) + '</span>/' + plupload.formatSize(file.size) + ') - ' + syllablion.complete +
				'</div>'); // Removed <div class="fileprogress"></div> (just before closing </div>) to make clearer it's complete.
				jQuery('#' + file.id + " .fileprogress").width(file.percent + "%");
			}
		});

		uploader.bind('Error', function(up, error) {

			console.log(error);
			
			var err_makesure;
			if (error.code == "-200") {
				err_makesure = '\n'+syllablion.makesure2;
			} else {
				err_makesure = syllablion.makesure;
			}
			
			var msg = syllablion.uploaderr+' (code '+error.code+') : '+error.message;
			
			if (error.hasOwnProperty('status') && error.status) {
				msg += ' ('+syllablion.http_code+' '+error.status+')';
			}
			
			if (error.hasOwnProperty('response')) {
				console.log('SyllabPlus: plupload error: '+error.response);
				if (error.response.length < 100) msg += ' '+syllablion.error+' '+error.response+'\n';
			}
			
			msg += ' '+err_makesure;
			
			alert(msg);
		});

		// a file was uploaded
		uploader.bind('FileUploaded', function(up, file, response) {
			
			if (response.status == '200') {
				// this is your ajax response, update the DOM with it or something...
				try {
					resp = ud_parse_json(response.response);
					if (resp.e) {
						alert(syllablion.uploaderror+" "+resp.e);
					} else if (resp.dm) {
						alert(resp.dm);
						syllab_updatehistory(1, 0);
					} else if (resp.m) {
						syllab_updatehistory(1, 0);
					} else {
						alert('Unknown server response: '+response.response);
					}
					
				} catch (err) {
					console.log(response);
					alert(syllablion.jsonnotunderstood);
				}

			} else {
				alert('Unknown server response status: '+response.code);
				console.log(response);
			}

		});
	}
	
	// Functions in the debugging console
	jQuery('#syllabplus_httpget_go').on('click', function(e) {
		e.preventDefault();
		syllabplus_httpget_go(0);
	});

	jQuery('#syllabplus_httpget_gocurl').on('click', function(e) {
		e.preventDefault();
		syllabplus_httpget_go(1);
	});
	
	jQuery('#syllabplus_callwpaction_go').on('click', function(e) {
		e.preventDefault();
		params = { wpaction: jQuery('#syllabplus_callwpaction').val() };
		syllab_send_command('call_wordpress_action', params, function(response) {
			if (response.e) {
				alert(response.e);
			} else if (response.s) {
				// Silence
			} else if (response.r) {
				jQuery('#syllabplus_callwpaction_results').html(response.r);
			} else {
				console.log(response);
				alert(syllablion.jsonnotunderstood);
			}
		});
	});
	
	function syllabplus_httpget_go(curl) {
		params = { uri: jQuery('#syllabplus_httpget_uri').val() };
		params.curl = curl;
		syllab_send_command('httpget', params, function(resp) {
			if (resp.e) { alert(resp.e); }
			if (resp.r) {
				jQuery('#syllabplus_httpget_results').html('<pre>'+resp.r+'</pre>');
			} else {
				console.log(resp);
			}
		}, { type: 'GET' });
	}
	
	jQuery('#syllab_activejobs_table, #syllab-navtab-migrate-content').on('click', '.syllab_jobinfo_delete', function(e) {
		e.preventDefault();
		var job_id = jQuery(this).data('jobid');
		if (job_id) {
			$(this).addClass('disabled');
			syllab_activejobs_delete(job_id);
		} else {
			console.log("SyllabPlus: A stop job link was clicked, but the Job ID could not be found");
		}
	});
	
	jQuery('#syllab_activejobs_table, #syllab-navtab-backups-content .syllab_existing_backups, #syllab-backupnow-inpage-modal, #syllab-navtab-migrate-content').on('click', '.syllab-log-link', function(e) {
		e.preventDefault();
		var file_id = jQuery(this).data('fileid');
		var job_id = jQuery(this).data('jobid');
		if (file_id) {
			syllab_popuplog(file_id);
		} else if (job_id) {
			syllab_popuplog(job_id);
		} else {
			console.log("SyllabPlus: A log link was clicked, but the Job ID could not be found");
		}
	});
	
	function syllab_restore_setup(entities, key, show_data) {
		syllab_restore_setoptions(entities);
		jQuery('#syllab_restore_timestamp').val(key);
		jQuery('.syllab_restore_date').html(show_data);
		
		syllab_restore_stage = 1;
		
		// jQuery('#syllab-restore-modal').dialog('open');
		syllab_restore_modal.open();
		syllab_activejobs_update(true);
	}
	
	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('click', 'button.choose-components-button', function(e) {
		var entities = jQuery(this).data('entities');
		var backup_timestamp = jQuery(this).data('backup_timestamp');
		var show_data = jQuery(this).data('showdata');
		syllab_restore_setup(entities, backup_timestamp, show_data);
	});
	
	/**
	 * Get the value of a named URL parameter - https://stackoverflow.com/questions/4548487/jquery-read-query-string
	 *
	 * @param {string} name - URL parameter to return the value of
	 *
	 * @returns {string}
	 */
	function get_parameter_by_name(name) {
		name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
		var regex_s = "[\\?&]"+name+"=([^&#]*)";
		var regex = new RegExp(regex_s);
		var results = regex.exec(window.location.href);
		if (results == null) {
			return '';
		} else {
			return decodeURIComponent(results[1].replace(/\+/g, ' '));
		}
	}
	
	if (get_parameter_by_name('udaction') == 'initiate_restore') {
		var entities = get_parameter_by_name('entities');
		var backup_timestamp = get_parameter_by_name('backup_timestamp');
		var show_data = get_parameter_by_name('showdata');
		syllab_restore_setup(entities, backup_timestamp, show_data);
	}

	var syllab_upload_modal_buttons = {};

	syllab_upload_modal_buttons[syllablion.uploadbutton] = function () {
		var key = jQuery('#syllab_upload_timestamp').val();
		var nonce = jQuery('#syllab_upload_nonce').val();
		var services = '';
		var send_list = false;
		
		jQuery('.syllab_remote_storage_destination').each(function (index) {
			if (jQuery(this).is(':checked')) { send_list = true; }
		});

		if (!send_list) {
			jQuery('#syllab-upload-modal-error').html(syllablion.local_upload_error);
			return;
		} else {
			services = jQuery("input[name^='syllab_remote_storage_destination_']").serializeArray();
		}

		jQuery(this).dialog("close");
		alert(syllablion.local_upload_started);
		syllab_send_command('upload_local_backup', {
			use_nonce: nonce,
			use_timestamp: key,
			services: services
		}, function (response) {});

	};

	syllab_upload_modal_buttons[syllablion.cancel] = function () {
		jQuery(this).dialog("close");
	};

	jQuery("#syllab-upload-modal").dialog({
		autoOpen: false, modal: true, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
		open: function(event, ui) {
			$(this).parent().trigger('focus');
			$(this).dialog('option', 'width', 308);
			if (jQuery(window).height() > 460) {
				$(this).dialog('option', 'height', 218);
				$(this).css('height', 'auto');
			} else if (jQuery(window).height() > 250 && jQuery(window).height() < 461) {
				$(this).dialog('option', 'height', 460);
				$(this).css('height', 'auto');
			} else {
				$(this).dialog('option', 'height', jQuery(window).height() - 20);
			}
		},
		buttons: syllab_upload_modal_buttons
	});

	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('click', 'button.syllab-upload-link', function (e) {
		e.preventDefault();
		var nonce = jQuery(this).data('nonce').toString();
		var key = jQuery(this).data('key').toString();
		var services = jQuery(this).data('services').toString();
		if (nonce) {
			syllab_upload(key, nonce, services);
		} else {
			console.log("SyllabPlus: A upload link was clicked, but the Job ID could not be found");
		}
	});

	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('click', '.syllab-load-more-backups', function (e) {
		e.preventDefault();
		var backup_count = parseInt(jQuery('#syllab-navtab-backups-content .syllab_existing_backups .syllab_existing_backups_row').length) + parseInt(syllablion.existing_backups_limit);
		syllab_updatehistory(0, 0, 0, backup_count);
	});

	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('click', '.syllab-load-all-backups', function (e) {
		e.preventDefault();
		syllab_updatehistory(0, 0, 0, 9999999);
	});

	/**
	 * Opens the dialog box for confirmation of where to upload the backup
	 *
	 * @param {string}  key        - The UNIX timestamp of the backup
	 * @param {string}  nonce      - The backup job ID
	 * @param {string}  services   - A list of services that have not been uploaded to yet
	 */
	function syllab_upload(key, nonce, services) {
		jQuery('#syllab_upload_timestamp').val(key);
		jQuery('#syllab_upload_nonce').val(nonce);
		var services_array = services.split(",");
		jQuery('.syllab_remote_storage_destination').each(function (index) {
			var name = jQuery(this).val();
			if (jQuery.inArray(name, services_array) == -1) {
				jQuery(this).prop('checked', false);
				jQuery(this).prop('disabled', true);
				var label = $(this).prop("labels");
				jQuery(label).append(' ' + syllablion.already_uploaded);
			}
		});
		jQuery('#syllab-upload-modal').dialog('open');
	}
	
	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('click', '.syllab-delete-link', function(e) {
		e.preventDefault();
		var hasremote = jQuery(this).data('hasremote');
		var nonce = jQuery(this).data('nonce').toString();
		var key = jQuery(this).data('key').toString();
		if (nonce) {
			syllab_delete(key, nonce, hasremote);
		} else {
			console.log("SyllabPlus: A delete link was clicked, but the Job ID could not be found");
		}
	});
	
	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('click', 'button.syllab_download_button', function(e) {
		e.preventDefault();
		var base = 'uddlstatus_';
		var backup_timestamp = jQuery(this).data('backup_timestamp');
		var what = jQuery(this).data('what');
		var whicharea = '.ud_downloadstatus';
		var set_contents = jQuery(this).data('set_contents');
		var prettydate = jQuery(this).data('prettydate');
		var async = true;
		syllab_downloader(base, backup_timestamp, what, whicharea, set_contents, prettydate, async);
	});
	
	jQuery('#syllab-navtab-backups-content .syllab_existing_backups').on('dblclick', '.syllab_existingbackup_date', function (e) {
		e.preventDefault();
		var nonce = jQuery(this).data('nonce').toString();
		var timestamp = jQuery(this).data('timestamp').toString();
		syllab_send_command('rawbackup_history', { timestamp: timestamp, nonce: nonce }, function (response) {
			var textArea = document.createElement('textarea');
			textArea.innerHTML = response;
			syllab_html_modal(textArea.value, syllablion.raw, 780, 500);
		}, { type: 'POST', json_parse: false });

		syllab_html_modal('<div style="margin:auto;text-align:center;margin-top:150px;"><img src="' + syllablion.ud_url + '/images/udlogo-rotating.gif" /> <br>'+ syllablion.loading +'</div>', syllablion.raw, 780, 500);
	});

	jQuery('#backupnow_database_moreoptions').on('click', 'div.backupnow-db-tables > a', function(e) {
		e.preventDefault();
		jQuery('> input', jQuery(this).parents('div.backupnow-db-tables')).prop('checked', false);
		if (jQuery(this).hasClass('backupnow-select-all-table')) {
			jQuery('> input', jQuery(this).parents('div.backupnow-db-tables')).prop('checked', true);
		} else if (jQuery(this).hasClass('backupnow-select-all-this-site')) {
			jQuery('> input', jQuery(this).parents('div.backupnow-db-tables')).not('[data-non_wp_table]').prop('checked', true);
			
		}
	});
});

// SyllabPlus Vault
jQuery(function($) {
	
	var settings_css_prefix = '#syllab-navtab-settings-content ';
	
	$(settings_css_prefix+'#remote-storage-holder').on('click', '.syllabvault_backtostart', function(e) {
		e.preventDefault();
		$(settings_css_prefix+'#syllabvault_settings_showoptions').slideUp();
		$(settings_css_prefix+'#syllabvault_settings_connect').slideUp();
		$(settings_css_prefix+'#syllabvault_settings_connected').slideUp();
		$(settings_css_prefix+'#syllabvault_settings_default').slideDown();
	});
	
	// Prevent default event when pressing return in the form
	$(settings_css_prefix).on('keypress','#syllabvault_settings_connect input', function(e) {
		if (13 == e.which) {
			$(settings_css_prefix+'#syllabvault_connect_go').trigger('click');
			return false;
		}
	});
	
	$(settings_css_prefix+'#remote-storage-holder').on('click', '#syllabvault_recountquota', function(e) {
		e.preventDefault();
		$(settings_css_prefix+'#syllabvault_recountquota').html(syllablion.counting);
		try {
			syllab_send_command('vault_recountquota', { instance_id: $('#syllabvault_settings_connect').data('instance_id') }, function(response) {
				$(settings_css_prefix+'#syllabvault_recountquota').html(syllablion.updatequotacount);
				if (response.hasOwnProperty('html')) {
					$(settings_css_prefix+'#syllabvault_settings_connected').html(response.html);
					if (response.hasOwnProperty('connected')) {
						if (response.connected) {
							$(settings_css_prefix+'#syllabvault_settings_default').hide();
							$(settings_css_prefix+'#syllabvault_settings_connected').show();
						} else {
							$(settings_css_prefix+'#syllabvault_settings_connected').hide();
							$(settings_css_prefix+'#syllabvault_settings_default').show();
						}
					}
				}
			}, { error_callback: function(response, status, error_code, resp) {
					$(settings_css_prefix+'#syllabvault_recountquota').html(syllablion.updatequotacount);
					if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
					console.error(resp.fatal_error_message);
					alert(resp.fatal_error_message);
					} else {
					var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
					console.log(error_message);
					alert(error_message);
					console.log(response);
					}
				}
			});
		} catch (err) {
			$(settings_css_prefix+'#syllabvault_recountquota').html(syllablion.updatequotacount);
			console.log(err);
		}
	});
	
	$(settings_css_prefix+'#remote-storage-holder').on('click', '#syllabvault_disconnect', function(e) {
		e.preventDefault();
		$(settings_css_prefix+'#syllabvault_disconnect').html(syllablion.disconnecting);
		try {
			syllab_send_command('vault_disconnect', { immediate_echo: true, instance_id: $('#syllabvault_settings_connect').data('instance_id') }, function(response) {
				$(settings_css_prefix+'#syllabvault_disconnect').html(syllablion.disconnect);
				if (response.hasOwnProperty('html')) {
					$(settings_css_prefix+'#syllabvault_settings_connected').html(response.html).slideUp();
					$(settings_css_prefix+'#syllabvault_settings_default').slideDown();
				}
			}, { error_callback: function(response, status, error_code, resp) {
					$(settings_css_prefix+'#syllabvault_disconnect').html(syllablion.disconnect);
					if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
					console.error(resp.fatal_error_message);
					alert(resp.fatal_error_message);
					} else {
					var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
					console.log(error_message);
					alert(error_message);
					console.log(response);
					}
				}
			});
		} catch (err) {
			$(settings_css_prefix+'#syllabvault_disconnect').html(syllablion.disconnect);
			console.log(err);
		}
	});
	
	$(settings_css_prefix+'#remote-storage-holder').on('click', '#syllabvault_connect', function(e) {
		e.preventDefault();
		$(settings_css_prefix+'#syllabvault_settings_default').slideUp();
		$(settings_css_prefix+'#syllabvault_settings_connect').slideDown();
	});
	
	$(settings_css_prefix+'#remote-storage-holder').on('click', '#syllabvault_showoptions', function(e) {
		e.preventDefault();
		$(settings_css_prefix+'#syllabvault_settings_default').slideUp();
		$(settings_css_prefix+'#syllabvault_settings_showoptions').slideDown();
	});
	
	$('#remote-storage-holder').on('keyup', '.syllabplus_onedrive_folder_input', function(e) {
		var folder = $(this).val();
		var td_container = $(this).closest('td')
		if (0 == folder.indexOf('https:') || 0 == folder.indexOf('http:')) {
			if (!td_container.find('.onedrive_folder_error').length) {
				td_container.append('<div class="onedrive_folder_error">'+syllablion.onedrive_folder_url_warning+'</div>');
			}
		} else {
			td_container.find('.onedrive_folder_error').slideUp('slow', function() {
				td_container.find('.onedrive_folder_error').remove();
			});
		}
	});
	
	$(settings_css_prefix+'#remote-storage-holder').on('click', '#syllabvault_connect_go', function(e) {
		$(settings_css_prefix+'#syllabvault_connect_go').html(syllablion.connecting);
		syllab_send_command('vault_connect', {
			email: $('#syllabvault_email').val(),
			pass: $('#syllabvault_pass').val(),
			instance_id: $('#syllabvault_settings_connect').data('instance_id'),
		}, function(resp, status, response) {
			$(settings_css_prefix+'#syllabvault_connect_go').html(syllablion.connect);
			if (resp.hasOwnProperty('e')) {
				syllab_html_modal('<h4 style="margin-top:0px; padding-top:0px;">'+syllablion.errornocolon+'</h4><p>'+resp.e+'</p>', syllablion.disconnect, 400, 250);
				if (resp.hasOwnProperty('code') && resp.code == 'no_quota') {
					$(settings_css_prefix+'#syllabvault_settings_connect').slideUp();
					$(settings_css_prefix+'#syllabvault_settings_default').slideDown();
				}
			} else if (resp.hasOwnProperty('connected') && resp.connected && resp.hasOwnProperty('html')) {
				$(settings_css_prefix+'#syllabvault_settings_connect').slideUp();
				$(settings_css_prefix+'#syllabvault_settings_connected').html(resp.html).slideDown();
			} else {
				console.log(resp);
				alert(syllablion.unexpectedresponse+' '+response);
			}
		}, { error_callback: function(response, status, error_code, resp) {
				$(settings_css_prefix+'#syllabvault_connect_go').html(syllablion.connect);
				if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				alert(resp.fatal_error_message);
				} else {
				var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
				console.log(error_message);
				alert(error_message);
				console.log(response);
				}
			}
		});
		return false;
	});

	// Mark a backup as always keep (do not delete)
	$('#syllab-iframe-modal').on('change', '#always_keep_this_backup', function() {
		var backup_key = $(this).data('backup_key');
		var params = {
			backup_key: backup_key,
			always_keep: $(this).is(':checked') ? 1 : 0,
		};
		syllab_send_command('always_keep_this_backup', params, function(resp) {
			if (resp.hasOwnProperty('rawbackup')) {
				jQuery('#syllab-iframe-modal').dialog('close');
				jQuery('.syllab_existing_backups_row_'+backup_key+' .syllab_existingbackup_date').data('rawbackup', resp.rawbackup);
				syllab_html_modal(jQuery('.syllab_existing_backups_row_'+backup_key+' .syllab_existingbackup_date').data('rawbackup'), syllablion.raw, 780, 500);
			}
		});
	});


}); // End ready Vault

// Next: the encrypted database pluploader
jQuery(function($) {
	
	try {
		if (typeof syllab_plupload_config2 !== 'undefined') {
			plupload_init();
		}
	} catch (err) {
		console.log(err);
	}
		
	function plupload_init() {
		// create the uploader and pass the config from above
		var uploader = new plupload.Uploader(syllab_plupload_config2);
		
		// checks if browser supports drag and drop upload, makes some css adjustments if necessary
		uploader.bind('Init', function(up) {
			var uploaddiv = jQuery('#plupload-upload-ui2');

			if (up.features.dragdrop) {
				uploaddiv.addClass('drag-drop');
				jQuery('#drag-drop-area2')
				.on('dragover.wp-uploader', function() {
 uploaddiv.addClass('drag-over'); })
				.on('dragleave.wp-uploader, drop.wp-uploader', function() {
 uploaddiv.removeClass('drag-over'); });
			} else {
				uploaddiv.removeClass('drag-drop');
				jQuery('#drag-drop-area2').off('.wp-uploader');
			}
		});
		
		uploader.init();
		
		// a file was added in the queue
		uploader.bind('FilesAdded', function(up, files) {
			// var hundredmb = 100 * 1024 * 1024, max = parseInt(up.settings.max_file_size, 10);
			
			plupload.each(files, function(file) {
				// @codingStandardsIgnoreLine
				if (!/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-db([0-9]+)?\.(gz\.crypt)$/i.test(file.name)) {
					alert(file.name+': '+syllablion.notdba);
					uploader.removeFile(file);
					return;
				}
				
				// a file was added, you may want to update your DOM here...
				jQuery('#filelist2').append(
					'<div class="file" id="' + file.id + '"><b>' +
					file.name + '</b> (<span>' + plupload.formatSize(0) + '</span>/' + plupload.formatSize(file.size) + ') ' +
				'<div class="fileprogress"></div></div>');
			});
		
			up.refresh();
			up.start();
		});
		
		uploader.bind('UploadProgress', function(up, file) {
			jQuery('#' + file.id + " .fileprogress").width(file.percent + "%");
			jQuery('#' + file.id + " span").html(plupload.formatSize(parseInt(file.size * file.percent / 100)));
		});
		
		uploader.bind('Error', function(up, error) {
			if ('-200' == error.code) {
				err_makesure = '\n'+syllablion.makesure2;
			} else {
				err_makesure = syllablion.makesure;
			}
			alert(syllablion.uploaderr+' (code '+error.code+") : "+error.message+" "+err_makesure);
		});
		
		// a file was uploaded
		uploader.bind('FileUploaded', function(up, file, response) {
			
			if (response.status == '200') {
				// this is your ajax response, update the DOM with it or something...
				if (response.response.substring(0,6) == 'ERROR:') {
					alert(syllablion.uploaderror+" "+response.response.substring(6));
				} else if (response.response.substring(0,3) == 'OK:') {
					bkey = response.response.substring(3);
					jQuery('#' + file.id + " .fileprogress").hide();
					jQuery('#' + file.id).append(syllablion.uploaded+' <a href="?page=syllabplus&action=downloadfile&syllabplus_file='+bkey+'&decrypt_key='+encodeURIComponent(jQuery('#syllabplus_db_decrypt').val())+'">'+syllablion.followlink+'</a> '+syllablion.thiskey+' '+jQuery('#syllabplus_db_decrypt').val().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"));
				} else {
					alert(syllablion.unknownresp+' '+response.response);
				}
			} else {
				alert(syllablion.ukrespstatus+' '+response.code);
			}
			
		});
	}

	jQuery('#syllab-hidethis').remove();
	
	/*
		* A Handlebarsjs helper function that is used to compare
		* two values if they are equal. Please refer to the example below.
		* Assuming "comment_status" contains the value of "spam".
		*
		* @param {mixed} a The first value to compare
		* @param {mixed} b The second value to compare
		*
		* @example
		* // returns "<span>I am spam!</span>", otherwise "<span>I am not a spam!</span>"
		* {{#ifeq "spam" comment_status}}
		*      <span>I am spam!</span>
		* {{else}}
		*      <span>I am not a spam!</span>
		* {{/ifeq}}
		*
		* @return {string}
	*/

	Handlebars.registerHelper('ifeq', function (a, b, opts) {
		if ('string' !== typeof a && 'undefined' !== typeof a && null !== a) a = a.toString();
		if ('string' !== typeof b && 'undefined' !== typeof b && null !== b) b = b.toString();
		if (a === b) {
			return opts.fn(this);
		} else {
			return opts.inverse(this);
		}
	});

	/*
		* Handlebars helper function to replace all password chars into asterisk char
		*
		* @param {string} password Required. The plain-text password
		*
		* @return {string}
	*/
	Handlebars.registerHelper('maskPassword', function(password) {
		return password.replace(/./gi,'*');
	});

	/*
		 * Handlebars helper function that wraps javascript encodeURIComponent so that it could encode the following characters: , / ? : @ & = + $ #
		 *
		 * @param {string} uri Required. The URI to be encoded
	 */
	Handlebars.registerHelper('encodeURIComponent', function(uri) {
		return encodeURIComponent(uri);
	});

	/**
	 * Handlebars helper function to compare two values using a specifed operator
	 *
	 * @see https://stackoverflow.com/questions/8853396/logical-operator-in-a-handlebars-js-if-conditional#answer-16315366
	 *
	 * @param {mixed} v1 the first value to compare
	 * @param {mixed} v2 the second value to compare
	 *
	 * @return {boolean} true if the first value matched against the second value, false otherwise
	 */
	Handlebars.registerHelper('ifCond', function(v1, operator, v2, options) {
		switch (operator) {
			case '==':
				return (v1 == v2) ? options.fn(this) : options.inverse(this);
			case '===':
				return (v1 === v2) ? options.fn(this) : options.inverse(this);
			case '!=':
				return (v1 != v2) ? options.fn(this) : options.inverse(this);
			case '!==':
				return (v1 !== v2) ? options.fn(this) : options.inverse(this);
			case '<':
				return (v1 < v2) ? options.fn(this) : options.inverse(this);
			case '<=':
				return (v1 <= v2) ? options.fn(this) : options.inverse(this);
			case '>':
				return (v1 > v2) ? options.fn(this) : options.inverse(this);
			case '>=':
				return (v1 >= v2) ? options.fn(this) : options.inverse(this);
			case '&&':
				return (v1 && v2) ? options.fn(this) : options.inverse(this);
			case '||':
				return (v1 || v2) ? options.fn(this) : options.inverse(this);
			case 'typeof':
				return (v1 === typeof v2) ? options.fn(this) : options.inverse(this);
			case 'not_typeof':
				return (v1 !== typeof v2) ? options.fn(this) : options.inverse(this);
			default:
				return options.inverse(this);
		}
	});

	/**
	 * Handlebars helper function for looping through a block of code a specified number of times
	 *
	 * @param {mixed} from the start value
	 * @param {mixed} to   the end value where the loop will stop
	 * @param {mixed} incr the increment number
	 *
	 * @return {mixed} the current processing number
	 */
	Handlebars.registerHelper('for', function(from, to, incr, block) {
		var accum = '';
		for (var i = from; i < to; i += incr)
			accum += block.fn(i);
		return accum;
	});

	/**
	 * Assign value into a variable
	 *
	 * @param {string} name the variable name
	 * @param {mixed}  val  the value
	 */
	Handlebars.registerHelper('set_var', function(name, val, options) {
		if (!options.data.root) {
			options.data.root = {};
		}
		options.data.root[name] = val;
	});

	/**
	 * Get length of an array/object
	 *
	 * @param {mixed} object the object
	 */
	Handlebars.registerHelper('get_length', function(object) {
		if ("undefined" !== typeof object && false === object instanceof Array) {
			return Object.keys(object).length;
		} else if (true === object instanceof Array) {
			return object.length;
		} else {
			return 0;
		}
	});

	// Add remote methods html using handlebarjs
	if ($('#remote-storage-holder').length) {
		var html = '';
		for (var method in syllablion.remote_storage_templates) {
			if ('undefined' != typeof syllablion.remote_storage_options[method] && 1 < Object.keys(syllablion.remote_storage_options[method]).length) {
				var template = Handlebars.compile(syllablion.remote_storage_templates[method]);
				var first_instance = true;
				var instance_count = 1;
				for (var instance_id in syllablion.remote_storage_options[method]) {
					if ('default' === instance_id) continue;
					
					var context = syllablion.remote_storage_options[method][instance_id];

					if ('undefined' == typeof context['instance_conditional_logic']) {
						context['instance_conditional_logic'] = {
							type: '', // always by default
							rules: [],
						};
					}
					context['instance_conditional_logic'].day_of_the_week_options = syllablion.conditional_logic.day_of_the_week_options;
					context['instance_conditional_logic'].logic_options = syllablion.conditional_logic.logic_options;
					context['instance_conditional_logic'].operand_options = syllablion.conditional_logic.operand_options;
					context['instance_conditional_logic'].operator_options = syllablion.conditional_logic.operator_options;

					context['first_instance'] = first_instance;
					if ('undefined' == typeof context['instance_enabled']) {
						context['instance_enabled'] = 1;
					}
					if ('undefined' == typeof context['instance_label'] || '' == context['instance_label']) {
						var method_name = syllablion.remote_storage_methods[method];
						var instance_label = ' (' + instance_count + ')';
						if (1 == instance_count) {
							instance_label = '';
						}
						context['instance_label'] = method_name + instance_label;
					}
					html += template(context);
					first_instance = false;
					instance_count++;
				}
			} else {
				html += syllablion.remote_storage_templates[method];
			}
		}
		$('#remote-storage-holder').append(html).ready(function () {
			$('.syllabplusmethod').not('.none').hide();
			syllab_remote_storage_tabs_setup();
			// Displays warning to the user of their mistake if they try to enter a URL in the OneDrive settings and saved
			$('#remote-storage-holder .syllabplus_onedrive_folder_input').trigger('keyup');
		});
	}

});

// Save/Export/Import settings via AJAX
jQuery(function($) {
	// Pre-load the image so that it doesn't jerk when first used
	var my_image = new Image();
	my_image.src = syllablion.ud_url+'/images/notices/syllab_logo.png';

	// When inclusion options for file entities in the settings tab, reflect that in the "Backup Now" dialog, to prevent unexpected surprises
	$('#syllab-navtab-settings-content input.syllab_include_entity').on('change', function(e) {
		var event_target = $(this).attr('id');
		var checked = $(this).is(':checked');
		var backup_target = '#backupnow_files_'+event_target;
		$(backup_target).prop('checked', checked);
	});

	$('#syllabplus-settings-save').on('click', function(e) {
		e.preventDefault();
		$.blockUI({
			css: {
				width: '300px',
				border: 'none',
				'border-radius': '10px',
				left: 'calc(50% - 150px)',
				padding: '20px',
			},
			message: '<div style="margin: 8px; font-size:150%;" class="syllab_saving_popup"><img src="'+syllablion.ud_url+'/images/notices/syllab_logo.png" height="80" width="80" style="padding-bottom:10px;"><br>'+syllablion.saving+'</div>'
		});
		
		var form_data = gather_syllab_settings('string');
		// POST the settings back to the AJAX handler
		syllab_send_command('savesettings', {
			settings: form_data,
			syllabplus_version: syllablion.syllabplus_version
		}, function(resp, status, response) {
			// Add page updates etc based on response
			syllab_handle_page_updates(resp, response);
			
			$('#syllab-wrap .fade').delay(6000).fadeOut(2000);
			if (window.syllab_main_tour && !window.syllab_main_tour.canceled) {
				window.syllab_main_tour.show('settings_saved');
				check_cloud_authentication();
			} else {
				$('html, body').animate({
					scrollTop: $("#syllab-wrap").offset().top
				}, 1000, function() {
				  check_cloud_authentication()
				});
			}

			$.unblockUI();
		}, { action: 'syllab_savesettings', error_callback: function(response, status, error_code, resp) {
				$.unblockUI();
				if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
				console.error(resp.fatal_error_message);
				alert(resp.fatal_error_message);
				} else {
				var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
				console.log(error_message);
				alert(error_message);
				console.log(response);
				}
			}, nonce: syllabplus_settings_nonce});
	});

	$('#syllabplus-settings-export').on('click', function() {
		if (syllab_settings_form_changed) {
			alert(syllablion.unsaved_settings_export);
		}
		export_settings();
	});

	$('#syllabplus-settings-import').on('click', function() {
		$.blockUI({
			css: {
				width: '300px',
				border: 'none',
				'border-radius': '10px',
				left: 'calc(50% - 150px)',
				padding: '20px',
			},
			message: '<div style="margin: 8px; font-size:150%;" class="syllab_saving_popup"><img src="'+syllablion.ud_url+'/images/notices/syllab_logo.png" height="80" width="80" style="padding-bottom:10px;"><br>'+syllablion.importing+'</div>'
		});
		var syllab_import_file_input = document.getElementById('import_settings');
		if (syllab_import_file_input.files.length == 0) {
			alert(syllablion.import_select_file);
			$.unblockUI();
			return;
		}
		var syllab_import_file_file = syllab_import_file_input.files[0];
		var syllab_import_file_reader = new FileReader();
		syllab_import_file_reader.onload = function() {
			import_settings(this.result);
		};
		syllab_import_file_reader.readAsText(syllab_import_file_file);
	});
	
	function export_settings() {
		var form_data = gather_syllab_settings('object');
		
		var date_now = new Date();
		
		// The 'version' attribute indicates the last time the format changed - i.e. do not update this unless there is a format change
		form_data = JSON.stringify({
			version: '1.12.40',
			epoch_date: date_now.getTime(),
			local_date: date_now.toLocaleString(),
			network_site_url: syllablion.network_site_url,
			data: form_data
		});
		
		// Attach this data to an anchor on page
		var link = document.body.appendChild(document.createElement('a'));
		link.setAttribute('download', syllablion.export_settings_file_name);
		link.setAttribute('style', "display:none;");
		link.setAttribute('href', 'data:text/json' + ';charset=UTF-8,' + encodeURIComponent(form_data));
		link.click();
	}
	
	function import_settings(syllab_file_result) {
		var parsed;
		try {
			parsed = ud_parse_json(syllab_file_result);
		} catch (e) {
			$.unblockUI();
			jQuery('#import_settings').val('');
			console.log(syllab_file_result);
			console.log(e);
			alert(syllablion.import_invalid_json_file);
			return;
		}
		if (window.confirm(syllablion.importing_data_from + ' ' + parsed['network_site_url'] + "\n" + syllablion.exported_on + ' ' + parsed['local_date'] + "\n" + syllablion.continue_import)) {
			// GET the settings back to the AJAX handler
			var stringified = JSON.stringify(parsed['data']);
			syllab_send_command('importsettings', {
				settings: stringified,
				syllabplus_version: syllablion.syllabplus_version,
			}, function(decoded_response, status, response) {
				var resp = syllab_handle_page_updates(decoded_response);
				if (!resp.hasOwnProperty('saved') || resp.saved) {
					// Prevent the user being told they have unsaved settings
					syllab_settings_form_changed = false;
					// Add page updates etc based on response
					location.replace(syllablion.syllab_settings_url);
				} else {
					$.unblockUI();
					if (resp.hasOwnProperty('error_message') && resp.error_message) {
						alert(resp.error_message);
					}
				}
			}, { action: 'syllab_importsettings', nonce: syllabplus_settings_nonce, error_callback: function(response, status, error_code, resp) {
					$.unblockUI();
					if (typeof resp !== 'undefined' && resp.hasOwnProperty('fatal_error')) {
					console.error(resp.fatal_error_message);
					alert(resp.fatal_error_message);
					} else {
					var error_message = "syllab_send_command: error: "+status+" ("+error_code+")";
					console.log(error_message);
					console.log(response);
					alert(error_message);
						
					}
				}
			 });
		} else {
			$.unblockUI();
		}
	}
	
	/**
	 * Retrieve the current settings from the DOM
	 *
	 * @param {string} output_format - the output format; valid values are 'string' or 'object'
	 *
	 * @returns String|Object
	 */
	function gather_syllab_settings(output_format) {

		var form_data = '';
		var output_format = ('undefined' === typeof output_format) ? 'string' : output_format;
		
		if ('object' == output_format) {
			// Excluding the unnecessary 'action' input avoids triggering a very mis-conceived mod_security rule seen on one user's site
			form_data = $("#syllab-navtab-settings-content form input[name!='action'][name!='option_page'][name!='_wpnonce'][name!='_wp_http_referer'], #syllab-navtab-settings-content form textarea, #syllab-navtab-settings-content form select, #syllab-navtab-settings-content form input[type=checkbox]").serializeJSON({checkboxUncheckedValue: '0', useIntKeysAsArrayIndex: true});
		} else {
			// Excluding the unnecessary 'action' input avoids triggering a very mis-conceived mod_security rule seen on one user's site
			form_data = $("#syllab-navtab-settings-content form input[name!='action'], #syllab-navtab-settings-content form textarea, #syllab-navtab-settings-content form select").serialize();
			
			// include unchecked checkboxes. user filter to only include unchecked boxes.
			$.each($('#syllab-navtab-settings-content form input[type=checkbox]')
				.filter(function(idx) {
				return $(this).prop('checked') == false
				}),
				function(idx, el) {
					// attach matched element names to the form_data with chosen value.
					var empty_val = '0';
					form_data += '&' + $(el).attr('name') + '=' + empty_val;
				}
			);
		}

		return form_data;
	}
	
	/**
	 * Method to parse the response from the backend and update the page with the returned content or display error messages if failed
	 *
	 * @param {array}  resp     - the JSON-decoded response containing information to update the settings page with
	 * @param {string} response - the JSON-encoded response containing information to update the settings page with
	 *
	 * @return {object} - the decoded response (empty if decoding was not successful)
	 */
	function syllab_handle_page_updates(resp, response) {
					
		try {
			var messages = resp.messages;
			// var debug = resp.changed.syllab_debug_mode;
			
			// If backup dir is not writable, change the text, and grey out the 'Backup Now' button
			var backup_dir_writable = resp.backup_dir.writable;
			var backup_dir_message = resp.backup_dir.message;
			var backup_button_title = resp.backup_dir.button_title;
		} catch (e) {
			console.log(e);
			console.log(response);
			alert(syllablion.jsonnotunderstood);
			$.unblockUI();
			return {};
		}
		
		if (resp.hasOwnProperty('changed')) {
			console.log("SyllabPlus: savesettings: some values were changed after being filtered");
			console.log(resp.changed);
			for (prop in resp.changed) {
				if ('object' === typeof resp.changed[prop]) {
					for (innerprop in resp.changed[prop]) {
						if (!$("[name='"+innerprop+"']").is(':checkbox')) {
							$("[name='"+prop+"["+innerprop+"]']").val(resp.changed[prop][innerprop]);
						}
					}
				} else {
					if (!$("[name='"+prop+"']").is(':checkbox')) {
						$("[name='"+prop+"']").val(resp.changed[prop]);
					}
				}
			}
		}
		
		$('#syllab_writable_mess').html(backup_dir_message);
		
		if (false == backup_dir_writable) {
			$('#syllab-backupnow-button').attr('disabled', 'disabled');
			$('#syllab-backupnow-button').attr('title', backup_button_title);
			$('.backupdirrow').css('display', 'table-row');
		} else {
			$('#syllab-backupnow-button').prop('disabled', false);
			$('#syllab-backupnow-button').removeAttr('title');
			// $('.backupdirrow').hide();
		}

		if (resp.hasOwnProperty('syllab_include_more_path')) {
			$('#backupnow_includefiles_moreoptions').html(resp.syllab_include_more_path);
		}

		if (resp.hasOwnProperty('backup_now_message')) { $('#backupnow_remote_container').html(resp.backup_now_message); }
		
		// Move from 2 to 1
		$('.syllabmessage').remove();
		
		$('#syllab_backup_started').before(resp.messages);
		
		console.log(resp);
		// $('#syllab-next-backup-inner').html(resp.scheduled);
		$('#syllab-next-files-backup-inner').html(resp.files_scheduled);
		$('#syllab-next-database-backup-inner').html(resp.database_scheduled);
		
		return resp;
		
	}

	/**
	 * This function has the workings for checking if any cloud storage needs authentication
	 * If so, these are amended to the HTML and the popup is shown to the users.
	 */
	function check_cloud_authentication(){
		var show_auth_modal = false;
		jQuery('#syllab-authenticate-modal-innards').html('');
		
		jQuery("div[class*=syllab_authenticate_] a.syllab_authlink").each(function () {
			jQuery("#syllab-authenticate-modal-innards").append('<p><a href="'+jQuery(this).attr('href')+'">'+jQuery(this).html()+'</a></p>');
			show_auth_modal = true;
		  });
			

		if (show_auth_modal) {
			var syllab_authenticate_modal_buttons = {};
			syllab_authenticate_modal_buttons[syllablion.cancel] = function() {
 jQuery(this).dialog("close"); };

			jQuery('#syllab-authenticate-modal').dialog({autoOpen: true,
				modal: true, resizable: false, draggable: false, resizeOnWindowResize: true, scrollWithViewport: true, resizeAccordingToViewport: true, useContentSize: false,
				open: function(event, ui) {
					$(this).dialog('option', 'width', 860);
					$(this).dialog('option', 'height', 260);
				},
				buttons: syllab_authenticate_modal_buttons}).dialog('open');
		}
	}

	$('.slp-replace-with-iframe--js').on('click', function(e) {
		e.preventDefault();
		var url = $(this).prop('href');
		var iframe = $('<iframe width="356" height="200" allowfullscreen webkitallowfullscreen mozallowfullscreen>').attr('src', url);
		iframe.insertAfter($(this));
		$(this).remove();
	});

});

// For When character set and collate both are unsupported at restoration time and if user change anyone substitution dropdown from both, Other substitution select box value should be change respectively.
jQuery(function($) {
	jQuery('#syllab-restore-modal').on('change', '#syllab_restorer_charset', function(e) {
		if ($('#syllab_restorer_charset').length && $('#syllab_restorer_collate').length && $('#collate_change_on_charset_selection_data').length) {
			var syllab_restorer_charset = $('#syllab_restorer_charset').val();
			// For only show collate which are related to charset
			$('#syllab_restorer_collate option').show();
			$('#syllab_restorer_collate option[data-charset!='+syllab_restorer_charset+']').hide();
			syllab_send_command('collate_change_on_charset_selection', {
				collate_change_on_charset_selection_data: $('#collate_change_on_charset_selection_data').val(),
				syllab_restorer_charset: syllab_restorer_charset,
				syllab_restorer_collate: $('#syllab_restorer_collate').val(),
			}, function(response) {
				if (response.hasOwnProperty('is_action_required') && 1 == response.is_action_required && response.hasOwnProperty('similar_type_collate')) {
					$('#syllab_restorer_collate').val(response.similar_type_collate);
				}
			});
		}
	});

	jQuery('#syllab-restore-modal').on('click', '#syllabplus_restore_tables_showmoreoptions', function(e) {
		e.preventDefault();
		jQuery('.syllabplus_restore_tables_options_container').toggle();
	});


	/**
	 * Sends request to generate a key to be used between SyllabPlus
	 * and SyllabCentral communication
	 *
	 * @param {string}   keysize    - The size of the encryption key to use
	 * @param {string}   firewalled - Indicates whether the target website is protected by some security protocol
	 * @param {function} callback   - The function to execute on successful key creation
	 * @param {object}   modal      - jQuery object representing the current modal element
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_create_syllab_key(keysize, firewalled, callback, modal) {
		if ('function' !== typeof callback) return;

		// Check for an already created key to avoid generating
		// the key more than once for the current session
		var form = $(modal).find('#syllabcentral_cloud_form');
		var key = form.find('.form_hidden_fields input[name="key"]');
		if (key.length) {
			if ('' !== key.val()) {
				callback.apply(this, [key.val()]);
				return;
			}
		}

		var data = {
			where_send: '__syllabpluscom',
			key_description: '',
			key_size: keysize,
			mothership_firewalled: firewalled
		};

		syllabcentral_cloud_show_spinner(modal);
		syllab_send_command('syllabcentral_create_key', data, function(response) {
			syllabcentral_cloud_hide_spinner(modal);

			try {
				data = ud_parse_json(response);
				if (data.hasOwnProperty('error')) {
					console.log(data);
					return;
				}

				if (data.hasOwnProperty('bundle')) {
					callback.apply(this, [data.bundle]);
				} else {
					if (data.hasOwnProperty('r')) {
						$(modal).find('.syllabcentral_cloud_notices').html(syllablion.trouble_connecting).addClass('syllabcentral_cloud_info');
						alert(data.r);
					} else {
						console.log(data);
					}
				}
			} catch (err) {
				console.log(err);
			}
		}, { json_parse: false });
	}

	/**
	 * Shows the spinner to indicate that a process is currently on-going
	 *
	 * @param {object} modal - jQuery object representing the current modal element
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_show_spinner(modal) {
		$(modal).find('.syllabplus_spinner.spinner').addClass('visible');
	}

	/**
	 * Hides the spinner to indicate that a process has completed its job
	 *
	 * @param {object} modal - jQuery object representing the current modal element
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_hide_spinner(modal) {
		$(modal).find('.syllabplus_spinner.spinner').removeClass('visible');
	}

	/**
	 * Sends request to the server to register a new user
	 *
	 * @param {array}  data  - The form data that will be submitted to the server
	 * @param {object} modal - jQuery object representing the current modal element
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_process_registration(data, modal) {
		syllabcentral_cloud_show_spinner(modal);
		syllab_send_command('process_syllabcentral_registration', data, function(response) {
			syllabcentral_cloud_hide_spinner(modal);

			try {
				data = ud_parse_json(response);
				if (data.hasOwnProperty('error')) {
					var message = data.message;
					var existing_email_errors = ['existing_user_email', 'email_exists'];

					if (-1 !== $.inArray(data.code, existing_email_errors)) message = data.message+' '+syllablion.perhaps_login;

					$(modal).find('.syllabcentral_cloud_notices').html(message).addClass('syllabcentral_cloud_error');
					$(modal).find('.syllabcentral_cloud_notices a').attr('target', '_blank');
					console.log(data);
					return;
				}

				if ('registered' === data.status) {
					$(modal).find('.syllabcentral_cloud_form_container').hide();
					$(modal).find('.syllabcentral-subheading').hide();
					$(modal).find('.syllabcentral_cloud_notices').removeClass('syllabcentral_cloud_error');
					
					syllabcentral_cloud_process_response(modal, data, syllablion.registration_successful);
				}
			} catch (err) {
				console.log(err);
			}
		}, { json_parse: false });
	}

	/**
	 * Sends request to the server to login an existing user
	 *
	 * @param {array}  form_data - The form data that will be submitted to the server
	 * @param {object} modal - jQuery object representing the current modal element
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_process_login(form_data, modal) {
		syllabcentral_cloud_show_spinner(modal);
		syllab_send_command('process_syllabcentral_login', form_data, function(response) {
			syllabcentral_cloud_hide_spinner(modal);

			try {
				data = ud_parse_json(response);
				if (data.hasOwnProperty('error')) {
					if ('incorrect_password' === data.code) {
						$(modal).find('.syllabcentral_cloud_form_container .tfa_fields').hide();
						$(modal).find('.syllabcentral_cloud_form_container .non_tfa_fields').show();
						$(modal).find('input#two_factor_code').val('');
						$(modal).find('input#password').val('').trigger('focus');
					}

					if ('email_not_registered' === data.code) {
						// Account does not exists then we will execute a registration process instead
						syllabcentral_cloud_process_registration(form_data, modal);
					} else {
					$(modal).find('.syllabcentral_cloud_notices').html(data.message).addClass('syllabcentral_cloud_error');
					$(modal).find('.syllabcentral_cloud_notices a').attr('target', '_blank');
					console.log(data);
					return;
				}
				}

				if (data.hasOwnProperty('tfa_enabled') && true == data.tfa_enabled) {
					$(modal).find('.syllabcentral_cloud_notices').html('').removeClass('syllabcentral_cloud_error');
					$(modal).find('.syllabcentral_cloud_form_container .non_tfa_fields').hide();
					$(modal).find('.syllabcentral_cloud_form_container .tfa_fields').show();

					$(modal).find('input#two_factor_code').trigger('focus');
				}

				if ('authenticated' === data.status) {
					$(modal).find('.syllabcentral_cloud_form_container').hide();
					$(modal).find('.syllabcentral_cloud_notices').removeClass('syllabcentral_cloud_error');

					syllabcentral_cloud_process_response(modal, data, syllablion.login_successful);
				}
			} catch (err) {
				console.log(err);
			}
		}, { json_parse: false });
	}

	/**
	 * Updates the redirect form with the needed details to redirect
	 * to SyllabCentral Cloud
	 *
	 * @param {object} modal - jQuery object representing the current modal element
	 * @param {array}  data    - The response data that was received from the SyllabCentral Cloud
	 * @param {string} message - A success string/message to show to the user before redirecting
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_process_response(modal, data, message) {
		var form = $(modal).find('form#syllabcentral_cloud_redirect_form');
		form.attr('action', data.redirect_url);
		form.attr('target', '_blank');

		if ('undefined' !== typeof data.redirect_token) {
			form.append('<input type="hidden" name="redirect_token" value="'+data.redirect_token+'">');
		}

		// Success, so we're updating the keys in advanced tools->syllabcentral area
		if (data.hasOwnProperty('keys_table') && data.keys_table) {
			$('#syllabcentral_keys_content').html(data.keys_table);
		}

		// Remove the option in the Extensions tab
		$('.syllabplus-addons-connect-to-udc').remove();

		$redirect_lnk = '<a href="'+syllablion.current_clean_url+'" class="syllabcentral_cloud_redirect_link">'+syllablion.syllabcentral_cloud+'</a>';
		$close_lnk = '<a href="'+syllablion.current_clean_url+'" class="syllabcentral_cloud_close_link">'+syllablion.close_wizard+'</a>';
		$(modal).find('.syllabcentral_cloud_notices').html(message.replace('%s', $redirect_lnk)+' '+$close_lnk+'<br/><br/>'+syllablion.control_udc_connections);

		$(modal).find('.syllabcentral_cloud_notices .syllabcentral_cloud_redirect_link').off('click').on('click', function(e) {
			e.preventDefault();

			form.trigger('submit');
			$(modal).find('.syllabcentral_cloud_notices .syllabcentral_cloud_close_link').trigger('click');
		});

		$(modal).find('.syllabcentral_cloud_notices .syllabcentral_cloud_close_link').off('click').on('click', function(e) {
			e.preventDefault();

			$(modal).dialog('close');
			$('#syllabcentral_cloud_connect_container').hide();
		});
	}

	/**
	 * Checks and validates submitted data before sending to the server
	 *
	 * @param {object} modal - jQuery object representing the current modal element
	 *
	 * @returns {boolean}
	 */
	function syllabcentral_cloud_pre_validate_input(modal) {
		var form = $(modal).find('#syllabcentral_cloud_form');
		var email = form.find('input#email').val();
		var password = form.find('input#password').val();
		var email_format = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+$/;

		$(modal).find('.syllabcentral_cloud_notices').html('').removeClass('syllabcentral_cloud_error syllabcentral_cloud_info');

		// Check whether the data consent was checked, if not, then we display
		// some error requiring the user to tick it before proceeding.
		var is_checked = form.find('.syllabcentral-data-consent > input[name="i_consent"]').is(':checked');
		if (!is_checked) {
			$(modal).find('.syllabcentral_cloud_notices').html(syllablion.data_consent_required).addClass('syllabcentral_cloud_error');
			return false;
		}

		if (0 === email.length || 0 === password.length) {
			$(modal).find('.syllabcentral_cloud_notices').html(syllablion.username_password_required).addClass('syllabcentral_cloud_error');
			return false;
		}

		if (null === email.match(email_format)) {
			$(modal).find('.syllabcentral_cloud_notices').html(syllablion.valid_email_required).addClass('syllabcentral_cloud_error');
			return false;
		}

		return true;
	}

	/**
	 * Prepares the data and executes the appropriate request based
	 * on the currently requested process (login or register)
	 *
	 * @param {object}  modal       - jQuery object representing the current modal element
	 * @param {boolean} is_register - Indicates whether the current request is for registration
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_prepare_data_and_send(modal, is_register) {
		var keysize = $(modal).find('#syllab_central_keysize').val();
		var firewalled = $(modal).find('#syllab_central_firewalled').is(':checked') ? 1 : 0;

		syllabcentral_cloud_create_syllab_key(keysize, firewalled, function(key) {
			var form = $(modal).find('#syllabcentral_cloud_form');
			var field = form.find('.form_hidden_fields input[name="key"]');
			if (0 === field.length) {
				form.find('.form_hidden_fields').append('<input type="hidden" name="key" value="'+key+'">');
			}
			
			var form_data = form.find('input').serialize();
			var data = {
				form_data: form_data
			}
	
			// Checks whether a login process or registration is to be execute
			// for the current call
			if ('undefined' !== typeof is_register && is_register) {
				syllabcentral_cloud_process_registration(data, modal);
			} else {
				syllabcentral_cloud_process_login(data, modal);
			}
		}, modal);
	}

	/**
	 * Opens the SyllabCentral Cloud login modal
	 *
	 * @returns {void}
	 */
	function syllabcentral_cloud_login_modal() {
		var form_template = $('#syllabcentral_cloud_login_form');
		if (form_template.length) {

			syllab_html_modal(form_template.html(), syllablion.syllabcentral_cloud, 520, 400);

			var consent_container = modal.find('.syllabcentral-data-consent');
			var name = consent_container.find('input').attr('name');

			if ('undefined' !== typeof name && name) {
				consent_container.find('input').attr('id', name);
				consent_container.find('label').attr('for', name);
			}
		}
	}

	// Handles the click event of the "Connect this site to an SyllabCentral Cloud" button
	$('#syllab-wrap #btn_cloud_connect').on('click', function() {
		syllabcentral_cloud_login_modal();
	});

	// Handles the click event to connect to the Self-Hosted SyllabCentral
	$('#syllab-wrap a#self_hosted_connect').on('click', function(e) {
		e.preventDefault();

		$('h2.nav-tab-wrapper > a#syllab-navtab-expert').trigger('click');
		$('div.advanced_settings_menu > #syllab_central').trigger('click');
	});

	// Handles the login button - triggered by a click event
	$('#syllab-iframe-modal').on('click', '#syllabcentral_cloud_login', function(e) {
		e.preventDefault();
		var modal = $(this).closest('#syllab-iframe-modal');

		if (syllabcentral_cloud_pre_validate_input(modal)) {
			syllabcentral_cloud_prepare_data_and_send(modal);
		}
	});

	var heartbeat_last_parameters = {};
	
	$(document).on('heartbeat-send', function(event, heartbeat_data) {
		heartbeat_last_parameters = syllab_poll_get_parameters();
		heartbeat_data.syllabplus = heartbeat_last_parameters;
	});

	$(document).on('heartbeat-tick', function(event, heartbeat_data) {
		if (null === heartbeat_data || !heartbeat_data.hasOwnProperty('syllabplus') || null == heartbeat_data.syllabplus) return;
		var resp = heartbeat_data.syllabplus;
		var response_raw = JSON.stringify(resp);
		// We do somewhat assume that there can't be overlapping heartbeat calls - they should be far enough apart to make that very unlikely (and even if it happened, it is unlikely to cause any trouble)
		syllab_process_status_check(resp, response_raw, heartbeat_last_parameters);
		if (!heartbeat_data.syllabplus.hasOwnProperty('time_now')) return;
		// Set the 'Time Now' status in the UI to the current time
		jQuery('body.settings_page_syllabplus #syllab-navtab-backups-content .syllab_time_now_wrapper .syllab_time_now').empty().html(heartbeat_data.syllabplus.time_now);
	});
});

/**
 * Process a status check result
 *
 * @param {Object} resp				   - the response after being parsed
 * @param {String} response_raw		   - the raw response
 * @param {Object} original_parameters - the original parameters used to make the check
 *
 * @returns {void}
 */
function syllab_process_status_check(resp, response_raw, original_parameters) {
	
	if (resp.hasOwnProperty('fatal_error')) {
		console.error(resp.fatal_error_message);
		if (true === syllabplus_activejobs_list_fatal_error_alert) {
			syllabplus_activejobs_list_fatal_error_alert = false;
			alert(this.alert_done + ' ' +resp.fatal_error_message);
		}
		return;
	}
	
	try {
		if (resp.hasOwnProperty('l')) {
			if (resp.l) {
				jQuery('#syllab_lastlogmessagerow').show();
				jQuery('#syllab_lastlogcontainer').html(resp.l);
			} else {
				jQuery('#syllab_lastlogmessagerow').hide();
				jQuery('#syllab_lastlogcontainer').html('('+syllablion.nothing_yet_logged+')');
			}
		}

		// hosting restrictions
		if (syllablion.hasOwnProperty('hosting_restriction') && syllablion.hosting_restriction instanceof Array) {
			syllablion.hosting_restriction.length = 0;
			if (resp.hasOwnProperty('hosting_restriction')) {
				if (resp.hosting_restriction && resp.hosting_restriction.includes('only_one_backup_per_month')) {
					syllablion.hosting_restriction.push('only_one_backup_per_month');
				}
				if (resp.hosting_restriction && resp.hosting_restriction.includes('only_one_incremental_per_day')) {
					syllablion.hosting_restriction.push('only_one_incremental_per_day');
				}
			}
		}

		if (!jQuery('#syllab-wrap #syllab-navtab-settings-content').is(':hidden')) {
			// auto-updates synchronised setting
			if (resp.hasOwnProperty('automatic_updates')) {
				jQuery('input[name="syllab_auto_updates"]').prop('checked', resp.automatic_updates);
			}
		}
		
		var lastactivity = -1;

		// Requested start of backup text
		var requeststart_el = jQuery('.syllab_requeststart');
		if (resp.j && requeststart_el.length && requeststart_el.data('remove')) {
			requeststart_el.remove();
		}

		// Parse response to add classes before inserting it, to avoid unwanted artifacts
		var $list_prepare = jQuery(resp.j);
		$list_prepare.find('.syllab_jobtimings').each(function(ind, element) {
			var $el = jQuery(element);
			if ($el.data('jobid')) {
				var jobid = $el.data('jobid');
				var job_row = $el.closest('.syllab_row');
				if (syllab_aborted_jobs[jobid]) {
					job_row.hide();
				}
			}
		});

		jQuery('#syllab_activejobsrow').html($list_prepare);

		var $clone_jobs = $list_prepare.find('.job-id[data-isclone="1"]');
		
		if ($clone_jobs.length > 0) {
			if (jQuery('.syllabclone_action_box .syllabclone_network_info').length == 0 && jQuery('#syllab_activejobsrow .job-id .syllab_clone_url').length > 0) {
				var clone_url = jQuery('#syllab_activejobsrow .job-id .syllab_clone_url').data('clone_url');
				
				syllab_send_command('get_clone_network_info', { clone_url: clone_url }, function(response) {
					if (response.hasOwnProperty('html')) {
						jQuery('.syllabclone_action_box').html(response.html);
					}
				});
			}

			jQuery('#syllab_clone_activejobsrow').empty();
			$clone_jobs.each(function(ind, element) {
				var $el = jQuery(element);
				$el.closest('.syllab_row')
					// .clone() // Clone allows to have the job on both tabs
					.appendTo(jQuery('#syllab_clone_activejobsrow'));
			});
		}

		jQuery('#syllab_activejobs .syllab_jobtimings').each(function(ind, element) {
			var $el = jQuery(element);
			// lastactivity, nextresumption, nextresumptionafter
			if ($el.data('lastactivity') && $el.data('jobid')) {
				var jobid = $el.data('jobid');
				var new_lastactivity = $el.data('lastactivity');
				if (lastactivity == -1 || new_lastactivity < lastactivity) { lastactivity = new_lastactivity; }
				var nextresumptionafter = $el.data('nextresumptionafter');
				var nextresumption = $el.data('nextresumption');

				// Milliseconds
				timenow = (new Date).getTime();
				if (new_lastactivity > 50 && nextresumption >0 && nextresumptionafter < -30 && timenow > syllab_last_forced_when+100000 && (syllab_last_forced_jobid != jobid || nextresumption != syllab_last_forced_resumption)) {
					syllab_last_forced_resumption = nextresumption;
					syllab_last_forced_jobid = jobid;
					syllab_last_forced_when = timenow;
					console.log('SyllabPlus: force resumption: job_id='+jobid+', resumption='+nextresumption);
					syllab_send_command('forcescheduledresumption', {
						resumption: nextresumption,
						job_id: jobid
					}, function(response) {
						console.log(response);
					}, { json_parse: false, alert_on_error: false });
				}
			}
		});
		
		timenow = (new Date).getTime();
		syllab_activejobs_nextupdate = timenow + 180000;
		// More rapid updates needed if a) we are on the main console, or b) a downloader is open (which can only happen on the restore console)
		if ((syllab_page_is_visible == 1 && 'backups' == syllab_console_focussed_tab)) {
			if (lastactivity > -1) {
				if (lastactivity < 5) {
					syllab_activejobs_nextupdate = timenow + 1750;
				} else {
					syllab_activejobs_nextupdate = timenow + 5000;
				}
			} else if (lastlog_lastdata == response_raw) {
				syllab_activejobs_nextupdate = timenow + 7500;
			} else {
				syllab_activejobs_nextupdate = timenow + 1750;
			}
		}

		if ($clone_jobs.length > 0) syllab_activejobs_nextupdate = timenow + 6000;

		lastlog_lastdata = response_raw;
		
		if (resp.j != null && resp.j != '') {
			jQuery('#syllab_activejobsrow').show();
			if ($clone_jobs.length > 0) jQuery('#syllab_clone_activejobsrow').show();

			if (original_parameters.hasOwnProperty('thisjobonly') && !syllab_inpage_hasbegun && jQuery('#syllab-jobid-'+original_parameters.thisjobonly).length) {
				syllab_inpage_hasbegun = 1;
				console.log('SyllabPlus: the start of the requested backup job has been detected');
			} else if (!syllab_inpage_hasbegun && syllab_activejobslist_backupnownonce_only && jQuery('.syllab_jobtimings.isautobackup').length) {
				autobackup_nonce = jQuery('.syllab_jobtimings.isautobackup').first().data('jobid');
				if (autobackup_nonce) {
					syllab_inpage_hasbegun = 1;
					syllab_backupnow_nonce = autobackup_nonce;
					original_parameters.thisjobonly = autobackup_nonce;
					console.log('SyllabPlus: the start of the requested backup job has been detected; id: '+autobackup_nonce);
				}
			}

			if (syllab_inpage_hasbegun == 1 && jQuery('#syllab-jobid-'+original_parameters.thisjobonly+'.syllab_finished').length) {
				// Don't reset to 0 - this will cause the 'began' event to be detected again
				syllab_inpage_hasbegun = 2;

				console.log('SyllabPlus: the end of the requested backup job has been detected');
				if (syllab_activejobs_update_timer) clearInterval(syllab_activejobs_update_timer);
				if (typeof syllab_inpage_success_callback !== 'undefined' && syllab_inpage_success_callback != '') {
					// Move on to next page
					syllab_inpage_success_callback.call(false);
				} else {
					jQuery('#syllab-backupnow-inpage-modal').dialog('close');
				}
			}
			if ('' == lastlog_jobs) {
				setTimeout(function() {
					jQuery('#syllab_backup_started').slideUp();}, 3500);
			}

			// detect manual backup
			if (original_parameters.hasOwnProperty('thisjobonly') && syllab_backupnow_nonce && original_parameters.thisjobonly === syllab_backupnow_nonce) {
				jQuery('.syllab_requeststart').remove();

				var thisjob = jQuery('#syllab-jobid-'+syllab_backupnow_nonce);
				// detect manual backup end
				if (thisjob.is('.syllab_finished')) {
					// reset current job vars
					syllab_activejobslist_backupnownonce_only = 0;
					// Aborted jobs
					if (syllab_aborted_jobs[syllab_backupnow_nonce]) {
						// remove deleted job from deleted jobs list
						syllab_aborted_jobs = syllab_aborted_jobs.filter(function(val, index) {
							return val != syllab_backupnow_nonce;
						});
					} else {

						if (syllab_active_job_is_clone(syllab_backupnow_nonce)) {
							// A clone job is complete
							syllab_show_success_modal(syllablion.clone_backup_complete);
							syllab_clone_jobs = syllab_clone_jobs.filter(function(val) {
								return val != syllab_backupnow_nonce;
							});
						} else {
							// A normal manual backup is complete
							syllab_show_success_modal(syllablion.backup_complete);
						}
					}
					syllab_backupnow_nonce = '';
					// Force fetch active jobs
					syllab_activejobs_update(true);

				}
			}
		} else {
			if (!jQuery('#syllab_activejobsrow').is(':hidden')) {
				// Backup has now apparently finished - hide the row. If using this for detecting a finished job, be aware that it may never have shown in the first place - so you'll need more than this.
				if (typeof lastbackup_laststatus != 'undefined') { syllab_showlastbackup(); }
				syllab_updatehistory(0, 0);
				jQuery('#syllab_activejobsrow').hide();
			}
		}
		lastlog_jobs = resp.j;
		
		// Download status
		if (resp.ds != null && resp.ds != '') {
			syllab_downloader_status_update(resp.ds, response_raw);
		}

		if (resp.u != null && resp.u != '' && jQuery("#syllab-poplog").dialog("isOpen")) {
			var log_append_array = resp.u;
			if (log_append_array.nonce == syllab_poplog_log_nonce) {
				syllab_poplog_log_pointer = log_append_array.pointer;
				if (log_append_array.log != null && log_append_array.log != '') {
					var oldscroll = jQuery('#syllab-poplog').scrollTop();
					jQuery('#syllab-poplog-content').append(log_append_array.log);
					if (syllab_poplog_lastscroll == oldscroll || syllab_poplog_lastscroll == -1) {
						jQuery('#syllab-poplog').scrollTop(jQuery('#syllab-poplog-content').prop("scrollHeight"));
						syllab_poplog_lastscroll = jQuery('#syllab-poplog').scrollTop();
					}
				}
			}
		}
	} catch (err) {
		console.log(syllablion.unexpectedresponse+' '+response_raw);
		console.log(err);
	}
}
