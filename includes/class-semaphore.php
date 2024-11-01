<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/
/**
 * Semaphore Lock Management
 * Adapted from WP Social under the GPL - thanks to Alex King (https://github.com/crowdfavorite/wp-social)
 */
class SyllabPlus_Semaphore {

	/**
	 * Initializes the semaphore object.
	 *
	 * @static
	 * @return SyllabPlus_Semaphore
	 */
	public static function factory() {
		return new self;
	}

	/**
	 * Lock Broke
	 *
	 * @var boolean
	 */
	protected $lock_broke = false;

	public $lock_name = 'lock';

	/**
	 * Attempts to start the lock. If the rename works, the lock is started.
	 *
	 * @return bool
	 */
	public function lock() {
		global $wpdb, $syllabplus;

		// Attempt to set the lock
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'syllabplus_locked_".$this->lock_name."'
			 WHERE option_name = 'syllabplus_unlocked_".$this->lock_name."'
		");

		if ('0' == $affected && !$this->stuck_check()) {
			$syllabplus->log('Semaphore lock ('.$this->lock_name.', '.$wpdb->options.') failed (line '.__LINE__.')');
			return false;
		}

		// Check to see if all processes are complete
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) + 1
			 WHERE option_name = 'syllabplus_semaphore_".$this->lock_name."'
			   AND option_value = '0'
		");
		if ('1' != $affected) {
			if (!$this->stuck_check()) {
				$syllabplus->log('Semaphore lock ('.$this->lock_name.', '.$wpdb->options.') failed (line '.__LINE__.')');
				return false;
			}

			// Reset the semaphore to 1
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = '1'
				 WHERE option_name = 'syllabplus_semaphore_".$this->lock_name."'
			");

			$syllabplus->log('Semaphore ('.$this->lock_name.', '.$wpdb->options.') reset to 1');
		}

		// Set the lock time
		$wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'syllabplus_last_lock_time_".$this->lock_name."'
		", current_time('mysql', 1)));
		$syllabplus->log('Set semaphore last lock ('.$this->lock_name.') time to '.current_time('mysql', 1));

		$syllabplus->log('Semaphore lock ('.$this->lock_name.') complete');
		return true;
	}

	public static function ensure_semaphore_exists($semaphore) {
		// Make sure the options for semaphores exist
		global $wpdb, $syllabplus;
		$results = $wpdb->get_results("
			SELECT option_id
				FROM $wpdb->options
				WHERE option_name IN ('syllabplus_locked_$semaphore', 'syllabplus_unlocked_$semaphore', 'syllabplus_last_lock_time_$semaphore', 'syllabplus_semaphore_$semaphore')
		");

		if (!is_array($results) || count($results) < 3) {
		
			if (is_array($results) && count($results) > 0) {
				$syllabplus->log("Semaphore ($semaphore, ".$wpdb->options.") in an impossible/broken state - fixing (".count($results).")");
			} else {
				$syllabplus->log("Semaphore ($semaphore, ".$wpdb->options.") being initialised");
			}
			
			$wpdb->query("
				DELETE FROM $wpdb->options
				WHERE option_name IN ('syllabplus_locked_$semaphore', 'syllabplus_unlocked_$semaphore', 'syllabplus_last_lock_time_$semaphore', 'syllabplus_semaphore_$semaphore')
			");
			
			$wpdb->query($wpdb->prepare("
				INSERT INTO $wpdb->options (option_name, option_value, autoload)
				VALUES
				('syllabplus_unlocked_$semaphore', '1', 'no'),
				('syllabplus_last_lock_time_$semaphore', '%s', 'no'),
				('syllabplus_semaphore_$semaphore', '0', 'no')
			", current_time('mysql', 1)));
		}
	}
	
	/**
	 * Increment the semaphore.
	 *
	 * @param  array $filters
	 * @return Syllab_Semaphore
	 */
	public function increment(array $filters = array()) {
		global $wpdb, $syllabplus;

		if (count($filters)) {
			// Loop through all of the filters and increment the semaphore
			foreach ($filters as $priority) {
				for ($i = 0, $j = count($priority); $i < $j; ++$i) {
					$this->increment();
				}
			}
		} else {
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = CAST(option_value AS UNSIGNED) + 1
				 WHERE option_name = 'syllabplus_semaphore_".$this->lock_name."'
			");
			$syllabplus->log('Incremented the semaphore ('.$this->lock_name.') by 1');
		}

		return $this;
	}

	/**
	 * Decrements the semaphore.
	 *
	 * @return void
	 */
	public function decrement() {
		global $wpdb, $syllabplus;

		$wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) - 1
			 WHERE option_name = 'syllabplus_semaphore_".$this->lock_name."'
			   AND CAST(option_value AS UNSIGNED) > 0
		");
		$syllabplus->log('Decremented the semaphore ('.$this->lock_name.') by 1');
	}

	/**
	 * Unlocks the process.
	 *
	 * @return bool
	 */
	public function unlock() {
		global $wpdb, $syllabplus;

		// Decrement for the master process.
		$this->decrement();

		$result = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'syllabplus_unlocked_".$this->lock_name."'
			 WHERE option_name = 'syllabplus_locked_".$this->lock_name."'
		");

		if ('1' == $result) {
			$syllabplus->log('Semaphore ('.$this->lock_name.') unlocked');
			return true;
		}

		$syllabplus->log('Semaphore ('.$this->lock_name.', '.$wpdb->options.') still locked ('.$result.')');
		return false;
	}

	/**
	 * Attempts to jiggle the stuck lock loose.
	 *
	 * @return bool
	 */
	private function stuck_check() {
		global $wpdb, $syllabplus;

		// Check to see if we already broke the lock.
		if ($this->lock_broke) {
			return true;
		}

		$current_time = current_time('mysql', 1);
		$three_minutes_before = gmdate('Y-m-d H:i:s', time()-(defined('SYLLABPLUS_SEMAPHORE_LOCK_WAIT') ? SYLLABPLUS_SEMAPHORE_LOCK_WAIT : 180));

		$affected = $wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'syllabplus_last_lock_time_".$this->lock_name."'
			   AND option_value <= %s
		", $current_time, $three_minutes_before));

		if ('1' == $affected) {
			$syllabplus->log('Semaphore ('.$this->lock_name.', '.$wpdb->options.') was stuck, set lock time to '.$current_time);
			$this->lock_broke = true;
			return true;
		}

		return false;
	}
} // End SyllabPlus_Semaphore
