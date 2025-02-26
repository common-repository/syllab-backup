<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

if (!defined('SYLLABPLUS_DIR')) die('No access.');

/**
 * A class for scheduling-related code.
 * N.B. This class began life Nov 2018; it is not guaranteed to contain all scheduling-related code. The variables used have also generally remained in the SyllabPlus class.
 */
class SyllabPlus_Job_Scheduler {

	/**
	 * This function is purely for timing - we just want to know the maximum run-time; not whether we have achieved anything during it. It will also run a check on whether the resumption interval is being approached.
	 */
	public static function record_still_alive() {
	
		global $syllabplus;
	
		// Update the record of maximum detected runtime on each run
		$time_passed = $syllabplus->jobdata_get('run_times');
		if (!is_array($time_passed)) $time_passed = array();

		$time_this_run = microtime(true)-$syllabplus->opened_log_time;
		$time_passed[$syllabplus->current_resumption] = $time_this_run;
		$syllabplus->jobdata_set('run_times', $time_passed);

		$resume_interval = $syllabplus->jobdata_get('resume_interval');
		if ($time_this_run + 30 > $resume_interval) {
			$new_interval = ceil($time_this_run + 30);
			set_site_transient('syllab_initial_resume_interval', (int) $new_interval, 8*86400);
			$syllabplus->log("The time we have been running (".round($time_this_run, 1).") is approaching the resumption interval ($resume_interval) - increasing resumption interval to $new_interval");
			$syllabplus->jobdata_set('resume_interval', $new_interval);
		}

	}
	
	/**
	 * This method helps with efficient scheduling
	 */
	public static function reschedule_if_needed() {
	
		global $syllabplus;
	
		// If nothing is scheduled, then no re-scheduling is needed, so return
		if (empty($syllabplus->newresumption_scheduled)) return;
		
		$time_away = $syllabplus->newresumption_scheduled - time();
		
		// 45 is chosen because it is 15 seconds more than what is used to detect recent activity on files (file mod times). (If we use exactly the same, then it's more possible to slightly miss each other)
		if ($time_away > 1 && $time_away <= 45) {
			$syllabplus->log('The scheduled resumption is within 45 seconds - will reschedule');
			// Increase interval generally by 45 seconds, on the assumption that our prior estimates were innaccurate (i.e. not just 45 seconds *this* time)
			self::increase_resume_and_reschedule(45);
		}
	}
	
	/**
	 * Indicate that something useful happened. Calling this at appropriate times is an important part of scheduling decisions.
	 */
	public static function something_useful_happened() {

		global $syllabplus;
	
		self::record_still_alive();

		if (!$syllabplus->something_useful_happened) {
		
			// Update the record of when something useful happened
			$useful_checkins = $syllabplus->jobdata_get('useful_checkins');
			if (!is_array($useful_checkins)) $useful_checkins = array();
			if (!in_array($syllabplus->current_resumption, $useful_checkins)) {
				$useful_checkins[] = $syllabplus->current_resumption;
				$syllabplus->jobdata_set('useful_checkins', $useful_checkins);
			}
			
		}

		$syllabplus->something_useful_happened = true;

		$clone_job = $syllabplus->jobdata_get('clone_job');

		if (!empty($clone_job)) {
			static $last_call = false;

			// Check we haven't yet made a call or that 15 minutes has passed before we make another call
			if (!$last_call || time() - $last_call > 900) {
				$last_call = time();
				$clone_id = $syllabplus->jobdata_get('clone_id');
				$secret_token = $syllabplus->jobdata_get('secret_token');
				$log_data = $syllabplus->get_last_log_chunk($syllabplus->file_nonce);
				$log_contents = isset($log_data['log_contents']) ? $log_data['log_contents'] : '';
				$first_byte = isset($log_data['first_byte']) ? $log_data['first_byte'] : 0;
				$response = $syllabplus->get_syllabplus_clone()->backup_checkin(array('clone_id' => $clone_id, 'secret_token' => $secret_token, 'first_byte' => $first_byte, 'log_contents' => $log_contents));
				if (!isset($response['status']) || 'success' != $response['status']) {
					$syllabplus->log("SyllabClone backup check-in failed.");
				} else {
					$syllabplus->log("SyllabClone backup check-in made successfully.");
				}
			}
		}

		$syllab_dir = $syllabplus->backups_dir_location();
		if (file_exists($syllab_dir.'/deleteflag-'.$syllabplus->nonce.'.txt')) {
			$syllabplus->log("User request for abort: backup job will be immediately halted");
			@unlink($syllab_dir.'/deleteflag-'.$syllabplus->nonce.'.txt');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$syllabplus->backup_finish(true, true, true);
			die;
		}
		
		if ($syllabplus->current_resumption >= 9 && false == $syllabplus->newresumption_scheduled) {
			$syllabplus->log("This is resumption ".$syllabplus->current_resumption.", but meaningful activity is still taking place; so a new one will be scheduled");
			// We just use max here to make sure we get a number at all
			$resume_interval = max($syllabplus->jobdata_get('resume_interval'), 75);
			// Don't consult the minimum here
			// if (!is_numeric($resume_interval) || $resume_interval<300) { $resume_interval = 300; }
			$schedule_for = time()+$resume_interval;
			$syllabplus->newresumption_scheduled = $schedule_for;
			wp_schedule_single_event($schedule_for, 'syllab_backup_resume', array($syllabplus->current_resumption + 1, $syllabplus->nonce));
		} else {
			self::reschedule_if_needed();
		}
	}
	
	/**
	 * Reschedule the next resumption for the specified amount of time in the future
	 *
	 * @uses wp_schedule_single_event()
	 *
	 * @param Integer $how_far_ahead - a number of seconds
	 */
	public static function reschedule($how_far_ahead) {
	
		global $syllabplus;
		
		// Reschedule - remove presently scheduled event
		$next_resumption = $syllabplus->current_resumption + 1;
		wp_clear_scheduled_hook('syllab_backup_resume', array($next_resumption, $syllabplus->nonce));
		// Add new event
		// This next line may be too cautious; but until 14-Aug-2014, it was 300.
		// Update 20-Mar-2015 - lowered from 180 to 120
		// Update 03-Aug-2018 - lowered from 120 to 100
		// Update 09-Oct-2020 - lowered from 100 to 60
		if ($how_far_ahead < 60) $how_far_ahead = 60;
		$schedule_for = time() + $how_far_ahead;
		$syllabplus->log("Rescheduling resumption $next_resumption: moving to $how_far_ahead seconds from now ($schedule_for)");
		wp_schedule_single_event($schedule_for, 'syllab_backup_resume', array($next_resumption, $syllabplus->nonce));
		$syllabplus->newresumption_scheduled = $schedule_for;
	}
	
	/**
	 * Terminate a backup run because other activity on the backup has been detected
	 *
	 * @uses die()
	 *
	 * @param String  $file				   - Indicate the file whose recent modification is indicative of activity
	 * @param Integer $time_now			   - The epoch time at which the detection took place
	 * @param Integer $time_mod			   - The epoch time at which the file was modified
	 * @param Boolean $increase_resumption - Whether or not to increase the resumption interval
	 */
	public static function terminate_due_to_activity($file, $time_now, $time_mod, $increase_resumption = true) {
	
		global $syllabplus;
		
		// We check-in, to avoid 'no check in last time!' detectors firing.
		self::record_still_alive();
		
		// Log
		$file_size = file_exists($file) ? round(filesize($file)/1024, 1). 'KB' : 'n/a';
		$syllabplus->log("Terminate: ".basename($file)." exists with activity within the last 30 seconds (time_mod=$time_mod, time_now=$time_now, diff=".(floor($time_now-$time_mod)).", size=$file_size). This likely means that another SyllabPlus run is at work; so we will exit.");
		
		$increase_by = $increase_resumption ? 120 : 0;
		self::increase_resume_and_reschedule($increase_by, true);
		
		// Die, unless there was a deliberate over-ride (for development purposes)
		if (!defined('SYLLABPLUS_ALLOW_RECENT_ACTIVITY') || !SYLLABPLUS_ALLOW_RECENT_ACTIVITY) die;
	}
	
	/**
	 * Increase the resumption interval and reschedule the next resumption
	 *
	 * @uses self::reschedule()
	 *
	 * @param Integer $howmuch		  - how much to add to the existing resumption interval
	 * @param Boolean $due_to_overlap - setting this changes the strategy for calculating the next resumption; it indicates that the reason for an increase is because of recent activity detection
	 */
	private static function increase_resume_and_reschedule($howmuch = 120, $due_to_overlap = false) {

		global $syllabplus;
	
		$resume_interval = max((int) $syllabplus->jobdata_get('resume_interval'), (0 === $howmuch) ? 120 : 300);

		if (empty($syllabplus->newresumption_scheduled) && $due_to_overlap) {
			$syllabplus->log('A new resumption will be scheduled to prevent the job ending');
		}

		$new_resume = $resume_interval + $howmuch;
		// It may be that we're increasing for the second (or more) time during a run, and that we already know that the new value will be insufficient, and can be increased
		if ($syllabplus->opened_log_time > 100 && microtime(true)-$syllabplus->opened_log_time > $new_resume) {
			$new_resume = ceil(microtime(true)-$syllabplus->opened_log_time)+45;
			$howmuch = $new_resume-$resume_interval;
		}

		// This used to be always $new_resume, until 14-Aug-2014. However, people who have very long-running processes can end up with very long times between resumptions as a result.
		// Actually, let's not try this yet. I think it is safe, but think there is a more conservative solution available.
		// $how_far_ahead = min($new_resume, 600);
		// Nov 2018 - scheduling the next resumption unnecessarily-far-in-the-future after an overlap is still occurring, so, we're adjusting this to have a maximum value in that particular case
		$how_far_ahead = $due_to_overlap ? min($new_resume, 900) : $new_resume;
		
		// If it is very long-running, then that would normally be known soon.
		// If the interval is already 12 minutes or more, then try the next resumption 10 minutes from now (i.e. sooner than it would have been). Thus, we are guaranteed to get at least 24 minutes of processing in the first 34.
		if ($syllabplus->current_resumption <= 1 && $new_resume > 720) $how_far_ahead = 600;

		if (!empty($syllabplus->newresumption_scheduled) || $due_to_overlap) self::reschedule($how_far_ahead);
		
		$syllabplus->jobdata_set('resume_interval', $new_resume);

		$syllabplus->log("To decrease the likelihood of overlaps, increasing resumption interval to: $resume_interval + $howmuch = $new_resume");
	}
}
