/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
(function ($) {

	$(function() {
		/*
			Plugins page
			splash screen on activation
		*/

		$('.syllabplus-welcome .close').on('click', function(e) {
			e.preventDefault();
			$(this).closest('.syllabplus-welcome').remove();
		});

		/*
			Syllabplus page tour
		*/

		// if Shepherd is undefined, exit.
		if (!window.Shepherd) return;

		var button_classes = 'button button-primary';
		var plugins_page_tour = window.syllab_plugins_page_tour = new Shepherd.Tour();
		var main_tour = window.syllab_main_tour = new Shepherd.Tour();

		// Set up the defaults for each step
		main_tour.options.defaults = plugins_page_tour.options.defaults = {
			classes: 'shepherd-theme-arrows-plain-buttons shepherd-main-tour',
			showCancelLink: true,
			scrollTo: false,
			tetherOptions: {
				constraints: [
					{
						to: 'scrollParent',
						attachment: 'together',
						pin: false
					}
				]
			}
		};
		
		/*
			Plugins page
		*/

		plugins_page_tour.addStep('intro', {
			title: syllabplus_tour_i18n.plugins_page.title,
			text: syllabplus_tour_i18n.plugins_page.text,
			attachTo: '.js-syllabplus-settings top',
			buttons: [
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.plugins_page.button.text,
					action: function() {
						window.location = syllabplus_tour_i18n.plugins_page.button.url;
					}
				}
			],
			tetherOptions: {
				constraints: [
					{
						to: 'scrollParent',
						attachment: 'together',
						pin: false
					}
				],
				offset: '20px 0'
			},
			when: {
				show: function() {
					$('body').addClass('highlight-slp');
					var popup = $(this.el);
					// var target = $(this.tether.target);
					$('body, html').animate({
						scrollTop: popup.offset().top - 50
					}, 500, function() {
						window.scrollTo(0, popup.offset().top - 50);
					});
				},
				hide: function() {
					$('body').removeClass('highlight-slp');
				}
			}
		});

		/*
			Main Tour steps
		*/

		// 1. Your first backup
		main_tour.addStep('backup_now', {
			title: syllabplus_tour_i18n.backup_now.title,
			text: syllabplus_tour_i18n.backup_now.text,
			attachTo: '#syllab-backupnow-button bottom',
			buttons: [
				{
					classes: 'slp-tour-end',
					text: syllabplus_tour_i18n.end_tour,
					action: main_tour.cancel
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: function() {
						$('#syllab-navtab-settings').trigger('click');
					}
				}
			]
		});

		// Manual backup options
		main_tour.addStep('backup_options', {
			title: syllabplus_tour_i18n.backup_options.title,
			text: syllabplus_tour_i18n.backup_options.text,
			classes: 'shepherd-theme-arrows-plain-buttons shepherd-main-tour super-index',
			attachTo: '#backupnow_includedb left',
			tetherOptions: {
				offset: '-15px 25px'
			},
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						$('#syllab-backupnow-modal').dialog('close');
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: main_tour.next
				}
			]
		});

		// Backup Now button
		main_tour.addStep('backup_now_btn', {
			title: syllabplus_tour_i18n.backup_now_btn.title,
			text: syllabplus_tour_i18n.backup_now_btn.text,
			classes: 'shepherd-theme-arrows-plain-buttons shepherd-main-tour super-index',
			attachTo: '.js-tour-backup-now-button top',
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.back();
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.backup_now_btn.btn_text,
					action: function() {
						$('#syllab-backupnow-modal').dialog('close');
						$('#syllab-navtab-settings').trigger('click');
					}
				}
			]
		});

		// Congratulations - Shows when a user clicks "backup now" in the modal
		main_tour.addStep('backup_now_btn_success', {
			title: syllabplus_tour_i18n.backup_now_btn_success.title,
			text: syllabplus_tour_i18n.backup_now_btn_success.text,
			attachTo: '#syllab_activejobs_table top',
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						$('#syllab-backupnow-button').trigger('click');
						main_tour.show('backup_now_btn');
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.backup_now_btn_success.btn_text,
					action: function() {
						$('#syllab-navtab-settings').trigger('click');
					}
				}
			],
			when: {
				show: function() {
					setTimeout(function() {
						$(window).trigger('scroll');
					})
					
				}
			}
		})

		// Settings - timing
		main_tour.addStep('settings_timing', {
			title: syllabplus_tour_i18n.settings_timing.title,
			text: syllabplus_tour_i18n.settings_timing.text,
			attachTo: '.retain-files right',
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						$('#syllab-navtab-backups').trigger('click');
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: main_tour.next
				}
			],
			tetherOptions: $.extend({}, main_tour.options.defaults.tetherOptions, {
				offset: '-33px -15px'
			}),
			when: {
				show: function() {
					scroll_to_popup();
				}
			}
		});

		// Settings - Remote storage + vault
		main_tour.addStep('settings_remote_storage', {
			title: syllabplus_tour_i18n.settings_remote_storage.title,
			text: syllabplus_tour_i18n.settings_remote_storage.text,
			attachTo: {
				element: 'label[for=syllab_servicecheckbox_syllabvault]',
				on: 'top'
			},
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.back();
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: function() {
						if ($('#syllab_servicecheckbox_syllabvault').is(':checked')) {
							main_tour.show('vault_selected')
						} else {
							main_tour.next();
						}
					}
				}
			],
			when: {
				show: function(p) {
					$('label[for=syllab_servicecheckbox_syllabvault]').addClass('emphasize');
					scroll_to_popup();
				},
				hide: function(p) {
					$('label[for=syllab_servicecheckbox_syllabvault]').removeClass('emphasize');
				}
			}
		});

		// Settings - more + syllabcentral
		main_tour.addStep('settings_more', {
			title: syllabplus_tour_i18n.settings_more.title,
			text: syllabplus_tour_i18n.settings_more.text,
			attachTo: '.js-tour-settings-more top',
			scrollTo: false,
			tetherOptions: {},
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.back();
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: main_tour.next
				}
			],
			when: {
				show: function() {
					scroll_to_popup();
				}
			}
		});

		// Save settings
		main_tour.addStep('settings_save', {
			title: syllabplus_tour_i18n.settings_save.title,
			text: syllabplus_tour_i18n.settings_save.text,
			attachTo: '#syllabplus-settings-save top',
			scrollTo: false,
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.back();
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: function() {
						if ($('#syllabcentral_cloud_connect_container').length) {
							main_tour.show('syllab_central');
						} else {
							$('#syllab-navtab-addons').trigger('click');
						}
					}
				}
			],
			when: {
				show: function() {
					scroll_to_popup();
				}
			}
		});

		// UDCentral
		main_tour.addStep('syllab_central', {
			title: syllabplus_tour_i18n.syllab_central.title,
			text: syllabplus_tour_i18n.syllab_central.text,
			attachTo: '#syllabcentral_cloud_connect_container  top',
			scrollTo: false,
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.back();
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: function() {
						$('#syllab-navtab-addons').trigger('click');
					}
				}
			],
			when: {
				show: function() {
					scroll_to_popup();
				}
			}
		});

		// Premium + addons
		main_tour.addStep('premium', {
			title: syllabplus_tour_i18n.premium.title,
			text: syllabplus_tour_i18n.premium.text,
			attachTo: syllabplus_tour_i18n.premium.attach_to,
			scrollTo: false,
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.going_somewhere = true;
						$('#syllab-navtab-settings').trigger('click');
						if ($('#syllabcentral_cloud_connect_container').length) {
							main_tour.show('syllab_central');
						} else {
							main_tour.show('settings_save');
						}
						scroll_to_popup();
						
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.premium.button,
					action: main_tour.cancel
				}
			],
			when: {
				show: function() {
					window.scroll(0, 0)
				}
			}
		});

		// EXTRA STEPS

		// Premium + addons
		main_tour.addStep('vault_selected', {
			title: syllabplus_tour_i18n.vault_selected.title,
			text: syllabplus_tour_i18n.vault_selected.text,
			attachTo: '#syllabvault_settings_cell top',
			scrollTo: false,
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.show('settings_remote_storage');
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: function() {
						main_tour.show('settings_more');
					}
				}
			],
			when: {
				show: function(p) {
					scroll_to_popup();
				}
			}
		});

		// Saved settings
		main_tour.addStep('settings_saved', {
			title: syllabplus_tour_i18n.settings_saved.title,
			text: syllabplus_tour_i18n.settings_saved.text,
			attachTo: '#syllabplus-settings-save top',
			scrollTo: false,
			buttons: [
				{
					classes: 'slp-tour-back',
					text: syllabplus_tour_i18n.back,
					action: function() {
						main_tour.show('settings_more');
					}
				},
				{
					classes: button_classes,
					text: syllabplus_tour_i18n.next,
					action: function() {
						if ($('#syllabcentral_cloud_connect_container').length) {
							main_tour.show('syllab_central');
						} else {
							$('#syllab-navtab-addons').trigger('click');
						}
					}
				}
			],
			when: {
				show: function() {
					scroll_to_popup();
				}
			}
		});

		main_tour.steps.forEach(function(step) {
			step.once('show', function() {
				// Adds a Close label near the (x)
				var close_btn = $(this.el).find('header .shepherd-cancel-link');
				close_btn.attr('data-btntext', syllabplus_tour_i18n.close);

				// opens the settings tab
				$(this.el).find('.js--go-to-settings').on('click', function(e) {
					e.preventDefault();
					$('#syllab-navtab-settings').trigger('click');
				});
			})
		});
		
		// on Cancel
		main_tour.on('cancel', cancel_tour);
		plugins_page_tour.on('cancel', cancel_tour);

		/**
		 * Cancel tour
		 */
		function cancel_tour() {
			// The tour is either finished or [x] was clicked
			main_tour.canceled = true;
			var data = {
				current_step: this.getCurrentStep().id
			};

			if ('function' === typeof syllab_send_command) {
				syllab_send_command(
					'set_tour_status',
					data,
					function(response) {
						console.log('Successfully deactivated tour');
					},
					{ alert_on_error: false }
				);
			} else {
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'syllab_ajax',
						subaction: 'set_tour_status',
						nonce: syllabplus_tour_i18n.nonce,
						current_step: this.getCurrentStep().id
					}
				});
			}
		};

		/**
		 * Scroll to Popup
		 *
		 * @param {Object} step
		 */
		var scroll_to_popup = function(step) {
			main_tour.going_somewhere = false;
			if (!step) {
				step = main_tour.getCurrentStep();
			}
			var popup = $(step.el);
			var target = $(step.tether.target);
			$('body, html').animate({
				scrollTop: popup.offset().top - 50
			}, 500, function() {
				window.scrollTo(0, popup.offset().top - 50);
			});
			
		}

		// If $('#syllab-backupnow-button'), start tour
		if ($('#syllab-backupnow-button').length) {
			/*
				Setup other events
			*/

			// Backup now
			$('#syllab-backupnow-button').on('click', function(e) {
				if (!main_tour.canceled) {
					main_tour.show('backup_options');
				}
			});

			// Click on status tab
			$('#syllab-navtab-backups').on('click', function(e) {
				if (!main_tour.canceled) {
					main_tour.show('backup_now');
				}
			});

			$(document).on('click', 'label[for=syllab_servicecheckbox_syllabvault]', function(e) {
				if (!main_tour.canceled && !$('#syllab_servicecheckbox_syllabvault').is(':checked')) {
					setTimeout(function() {
						main_tour.show('vault_selected');
					}, 200);
				}
			});

			// close backup backupnow modal
			$('#syllab-backupnow-modal').on("dialogclose", function(event, ui) {
				if (!main_tour.canceled) {
					main_tour.show('backup_now');
				}
			});
			
			// Backup now - manual backup is starting
			$('.js-tour-backup-now-button').on('click', function(e) {
				if (!main_tour.canceled) {
					main_tour.show('backup_now_btn_success');
				}
			})

			// settings tab
			$('#syllab-navtab-settings').on('click', function(e) {
				if (!main_tour.canceled && !main_tour.going_somewhere) {
					main_tour.show('settings_timing');
				}
			});

			// addons tab
			$('#syllab-navtab-addons').on('click', function(e) {
				if (!main_tour.canceled) {
					main_tour.show('premium');
				}
			});

			// Tabs without guide
			$('#syllab-navtab-migrate, #syllab-navtab-expert').on('click', function(e) {
				if (!main_tour.canceled) {
					main_tour.hide();
				}
			});
			// start tour
			main_tour.start();

			// go back to fisrt tab
			if (syllabplus_tour_i18n.show_tab_on_load) {
				$(syllabplus_tour_i18n.show_tab_on_load).trigger('click');
			} else {
				$('#syllab-navtab-backups').trigger('click');
			}

		}

		// start plugins page tour
		if ($('.js-syllabplus-settings').length) {
			plugins_page_tour.start();
		}
		
	});

})(jQuery);
