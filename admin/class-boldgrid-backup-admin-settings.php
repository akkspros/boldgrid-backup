<?php
/**
 * The admin-specific utilities methods for the plugin
 *
 * @link http://www.boldgrid.com
 * @since 1.0
 *
 * @package Boldgrid_Backup
 * @subpackage Boldgrid_Backup/admin
 * @copyright BoldGrid.com
 * @version $Id$
 * @author BoldGrid.com <wpb@boldgrid.com>
 */

/**
 * BoldGrid Backup admin settings class.
 *
 * @since 1.0
 */
class Boldgrid_Backup_Admin_Settings {
	/**
	 * The core class object.
	 *
	 * @since 1.0
	 * @access private
	 * @var Boldgrid_Backup_Admin_Core
	 */
	private $core;

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 *
	 * @param Boldgrid_Backup_Admin_Config $core Config class object.
	 */
	public function __construct( $core ) {
		// Save the Boldgrid_Backup_Admin_Core object as a class property.
		$this->core = $core;
	}

	/**
	 * Get settings using defaults.
	 *
	 * @since 1.0
	 *
	 * @return array An array of settings.
	 */
	public function get_settings() {
		// Get settings.
		$settings = get_option( 'boldgrid_backup_settings' );

		// Parse settings.
		if ( false === empty( $settings['schedule'] ) ) {
			// Update schedule format.
			// Days of the week.
			$settings['schedule']['dow_sunday'] = ( false ===
				empty( $settings['schedule']['dow_sunday'] ) ? 1 : 0 );
			$settings['schedule']['dow_monday'] = ( false ===
				empty( $settings['schedule']['dow_monday'] ) ? 1 : 0 );
			$settings['schedule']['dow_tuesday'] = ( false ===
				empty( $settings['schedule']['dow_tuesday'] ) ? 1 : 0 );
			$settings['schedule']['dow_wednesday'] = ( false ===
				empty( $settings['schedule']['dow_wednesday'] ) ? 1 : 0 );
			$settings['schedule']['dow_thursday'] = ( false ===
				empty( $settings['schedule']['dow_thursday'] ) ? 1 : 0 );
			$settings['schedule']['dow_friday'] = ( false ===
				empty( $settings['schedule']['dow_friday'] ) ? 1 : 0 );
			$settings['schedule']['dow_saturday'] = ( false ===
				empty( $settings['schedule']['dow_saturday'] ) ? 1 : 0 );

			// Time of day.
			$settings['schedule']['tod_h'] = ( false === empty( $settings['schedule']['tod_h'] ) ? $settings['schedule']['tod_h'] : mt_rand( 1, 5 ) );
			$settings['schedule']['tod_m'] = ( false === empty( $settings['schedule']['tod_m'] ) ? $settings['schedule']['tod_m'] : mt_rand( 1, 59 ) );
			$settings['schedule']['tod_a'] = ( false === empty( $settings['schedule']['tod_a'] ) ? $settings['schedule']['tod_a'] : 'AM' );

			// Other settings.
			$settings['notifications']['backup'] = ( false ===
				isset( $settings['notifications']['backup'] ) || false ===
				empty( $settings['notifications']['backup'] ) ? 1 : 0 );
			$settings['notifications']['restore'] = ( false ===
				isset( $settings['notifications']['restore'] ) || false ===
				empty( $settings['notifications']['restore'] ) ? 1 : 0 );
			$settings['auto_backup'] = ( false === isset( $settings['auto_backup'] ) ||
				false === empty( $settings['auto_backup'] ) ? 1 : 0 );
			$settings['auto_rollback'] = ( false === isset( $settings['auto_rollback'] ) ||
				false === empty( $settings['auto_rollback'] ) ? 1 : 0 );
		} else {
			// Define defaults.
			// Days of the week.
			$settings['schedule']['dow_sunday'] = 0;
			$settings['schedule']['dow_monday'] = 0;
			$settings['schedule']['dow_tuesday'] = 0;
			$settings['schedule']['dow_wednesday'] = 0;
			$settings['schedule']['dow_thursday'] = 0;
			$settings['schedule']['dow_friday'] = 0;
			$settings['schedule']['dow_saturday'] = 0;

			// Time of day.
			$settings['schedule']['tod_h'] = mt_rand( 1, 5 );
			$settings['schedule']['tod_m'] = mt_rand( 1, 59 );
			$settings['schedule']['tod_a'] = 'AM';

			// Other settings.
			$settings['notifications']['backup'] = 1;
			$settings['notifications']['restore'] = 1;
			$settings['auto_backup'] = 1;
			$settings['auto_rollback'] = 1;
		}

		// Return the settings array.
		return $settings;
	}

	/**
	 * Update or add an entry to the system user crontab or wp-cron.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @global WP_Filesystem $wp_filesystem The WordPress Filesystem API global object.
	 *
	 * @param string $entry A cron entry.
	 * @return bool Success.
	 */
	private function update_cron( $entry ) {
		// If no entry was passed, then abort.
		if ( true === empty( $entry ) ) {
			return false;
		}

		// Check if crontab is available.
		$is_crontab_available = $this->core->test->is_crontab_available();

		// Check if wp-cron is available.
		$is_wpcron_available = $this->core->test->wp_cron_enabled();

		// If crontab or wp-cron is not available, then abort.
		if ( true !== $is_crontab_available && true !== $is_wpcron_available ) {
			return false;
		}

		// Check if the backup directory is configured.
		if ( false === $this->core->config->get_backup_directory() ) {
			return false;
		}

		// Use either crontab or wp-cron.
		if ( true === $is_crontab_available ) {
			// Use crontab.
			// Read crontab.
			$command = 'crontab -l';

			$crontab = $this->core->execute_command( $command );

			// Check for failure.
			if ( false === $crontab ) {
				return false;
			}

			// Add entry to crontab to the end, if it does not already exist.
			if ( false === strpos( $crontab, $entry ) ) {
				$crontab .= "\n" . $entry . "\n";
			}

			// Strip extra line breaks.
			$crontab = str_replace( "\n\n", "\n", $crontab );

			// Trim the crontab.
			$crontab = trim( $crontab );

			// Add a line break at the end of the file.
			$crontab .= "\n";

			// Get the backup directory path.
			$backup_directory = $this->core->config->get_backup_directory();

			// Connect to the WordPress Filesystem API.
			global $wp_filesystem;

			// Check if the backup directory is writable.
			if ( true !== $wp_filesystem->is_writable( $backup_directory ) ) {
				return false;
			}

			// Save the temp crontab to file.
			$temp_crontab_path = $backup_directory . '/crontab.' . microtime( true ) . '.tmp';

			$wp_filesystem->put_contents( $temp_crontab_path, $crontab, 0600 );

			// Check if the defaults file was written.
			if ( false === $wp_filesystem->exists( $temp_crontab_path ) ) {
				return false;
			}

			// Write crontab.
			$command = 'crontab ' . $temp_crontab_path;

			$crontab = $this->core->execute_command( $command, null, $success );

			// Remove temp crontab file.
			$wp_filesystem->delete( $temp_crontab_path, false, 'f' );

			// Check for failure.
			if ( false === $crontab || true !== $success ) {
				return false;
			}
		} else {
			// Use wp-cron.
			// @todo Write wp-cron code here.
		}

		return true;
	}

	/**
	 * Delete boldgrid-backup cron entries from the system user crontab or wp-cron.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @global WP_Filesystem $wp_filesystem The WordPress Filesystem API global object.
	 *
	 * @return bool Success.
	 */
	private function delete_cron_entries() {
		// Check if crontab is available.
		$is_crontab_available = $this->core->test->is_crontab_available();

		// Check if wp-cron is available.
		$is_wpcron_available = $this->core->test->wp_cron_enabled();

		// If crontab or wp-cron is not available, then abort.
		if ( true !== $is_crontab_available && true !== $is_wpcron_available ) {
			return false;
		}

		// Check if the backup directory is configured.
		if ( false === $this->core->config->get_backup_directory() ) {
			return false;
		}

		// Set a search pattern to match for our cron jobs.
		$pattern = 'boldgrid-backup-cron.php';

		// Use either crontab or wp-cron.
		if ( true === $is_crontab_available ) {
			// Use crontab.
			// Read crontab.
			$command = 'crontab -l';

			$crontab = $this->core->execute_command( $command, null, $success );

			// If the command to retrieve crontab failed, then abort.
			if ( true !== $success ) {
				return false;
			}

			// If no entries exist, then return success.
			if ( false === strpos( $crontab, $pattern ) ) {
				return true;
			}

			// Remove lines matching the pattern.
			$crontab_exploded = explode( "\n", $crontab );

			$crontab = '';

			foreach ( $crontab_exploded as $line ) {
				if ( false === strpos( $line, $pattern ) ) {
					$line = trim( $line );
					$crontab .= $line . "\n";
				}
			}

			// Get the backup directory path.
			$backup_directory = $this->core->config->get_backup_directory();

			// Connect to the WordPress Filesystem API.
			global $wp_filesystem;

			// Check if the backup directory is writable.
			if ( true !== $wp_filesystem->is_writable( $backup_directory ) ) {
				return false;
			}

			// Save the temp crontab to file.
			$temp_crontab_path = $backup_directory . '/crontab.' . microtime( true ) . '.tmp';

			// Save a temporary file for crontab.
			$wp_filesystem->put_contents( $temp_crontab_path, $crontab, 0600 );

			// Check if the defaults file was written.
			if ( false === $wp_filesystem->exists( $temp_crontab_path ) ) {
				return false;
			}

			// Write crontab.
			$command = 'crontab ' . $temp_crontab_path;

			$crontab = $this->core->execute_command( $command, null, $success );

			// Remove temp crontab file.
			$wp_filesystem->delete( $temp_crontab_path, false, 'f' );
		} else {
			// Use wp-cron.
			// @todo Write wp-cron code here.
		}

		return true;
	}

	/**
	 * Update settings.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @return bool Update success.
	 */
	private function update_settings() {
		// Verify nonce.
		check_admin_referer( 'boldgrid-backup-settings', 'settings_auth' );

		// Check for settings update.
		if ( false === empty( $_POST['save_time'] ) ) {
			// Get settings.
			$settings = $this->get_settings();

			// Initialize $update_error.
			$update_error = false;

			// Initialize $days_scheduled.
			$days_scheduled = array();

			// Validate input for schedule.
			$indices = array(
				'dow_sunday',
				'dow_monday',
				'dow_tuesday',
				'dow_wednesday',
				'dow_thursday',
				'dow_friday',
				'dow_saturday',
				'tod_h',
				'tod_m',
				'tod_a',
			);

			foreach ( $indices as $index ) {
				// Determine input type.
				if ( 0 === strpos( $index, 'dow_' ) ) {
					$type = 'day';
				} elseif ( 'tod_h' === $index ) {
					$type = 'h';
				} elseif ( 'tod_m' === $index ) {
					$type = 'm';
				} elseif ( 'tod_a' === $index ) {
					$type = 'a';
				} else {
					// Unknown type.
					$type = '?';
				}

				if ( false === empty( $_POST[ $index ] ) ) {
					// Validate by type.
					switch ( $type ) {
						case 'day' :
							// Convert to integer.
							$_POST[ $index ] = (int) $_POST[ $index ];

							// If day was scheduled, then track it.
							if ( 1 === $_POST[ $index ] ) {
								$days_scheduled[] = date( 'w', strtotime( str_replace( 'dow_', '', $index ) ) );
							}

							break;
						case 'h' :
							if ( $_POST[ $index ] < 1 || $_POST[ $index ] > 12 ) {
								// Error in input.
								$update_error = true;
								break 2;
							}

							// Convert to integer.
							$_POST[ $index ] = (int) $_POST[ $index ];

							break;
						case 'm' :
							if ( $_POST[ $index ] < 0 || $_POST[ $index ] > 59 ) {
								// Error in input.
								$update_error = true;
								break 2;
							}

							// Convert to integer.
							$_POST[ $index ] = (int) $_POST[ $index ];

							// Pad left with 0.
							$_POST[ $index ] = str_pad( $_POST[ $index ], 2, '0', STR_PAD_LEFT );

							break;
						case 'a' :
							if ( 'AM' !== $_POST[ $index ] && 'PM' !== $_POST[ $index ] ) {
								// Error in input; unknown type.
								$update_error = true;
								break 2;
							}

							break;
						default :
							// Error in input; unknown type.
							$update_error = true;
							break 2;
					}

					// Update the setting value provided.
					$settings['schedule'][ $index ] = $_POST[ $index ];
				} elseif ( 'day' === $type ) {
					// Unassigned days.
					$settings['schedule'][ $index ] = 0;
				} else {
					// Error in input.
					$update_error = true;

					break;
				}
			}

			// Validate input for other settings.
			$settings['notifications']['backup'] = ( ( true === isset( $_POST['notify_backup'] ) &&
				'1' === $_POST['notify_backup'] ) ? 1 : 0 );

			$settings['notifications']['restore'] = ( ( true === isset( $_POST['notify_restore'] ) &&
				'1' === $_POST['notify_restore'] ) ? 1 : 0 );

			$settings['auto_backup'] = ( ( false === isset( $_POST['auto_backup'] ) ||
				'1' === $_POST['auto_backup'] ) ? 1 : 0 );

			$settings['auto_rollback'] = ( ( false === isset( $_POST['auto_rollback'] ) ||
				'1' === $_POST['auto_rollback'] ) ? 1 : 0 );

			// If no errors, then save the settings.
			if ( false === $update_error ) {
				// Record the update time.
				$settings['updated'] = time();

				// Attempt to update WP option.
				if ( true !== update_option( 'boldgrid_backup_settings', $settings ) ) {
					// Failure.
					$update_error = true;

					do_action( 'boldgrid_backup_notice',
						'Invalid settings submitted.  Please try again.',
						'notice notice-error is-dismissible'
					);
				}
			} else {
				// Interrupted by a previous error.
				do_action( 'boldgrid_backup_notice',
					'Invalid settings submitted.  Please try again.',
					'notice notice-error is-dismissible'
				);
			}
		}

		// Delete existing backup cron jobs.
		$cron_status = $this->delete_cron_entries();

		// If delete cron failed, then show a notice.
		if ( true !== $cron_status ) {
			$update_error = true;

			do_action( 'boldgrid_backup_notice',
				'An error occurred when modifying cron jobs.  Please try again.',
				'notice notice-error is-dismissible'
			);
		}

		// Update cron, if there are days selected.
		if ( false === empty( $days_scheduled ) ) {
			// Build cron job line in crontab format.
			$entry = date( 'i G',
				strtotime(
					$settings['schedule']['tod_h'] . ':' . $settings['schedule']['tod_m'] . ' ' .
					$settings['schedule']['tod_a']
				)
			) . ' * * ';

			$days_scheduled_list = '';

			foreach ( $days_scheduled as $day ) {
				$days_scheduled_list .= $day . ',';
			}

			$days_scheduled_list = rtrim( $days_scheduled_list, ',' );

			$entry .= $days_scheduled_list . ' php -qf "' . dirname( dirname( __FILE__ ) ) .
			'/boldgrid-backup-cron.php" mode=backup HTTP_HOST=' . $_SERVER['HTTP_HOST'];

			if ( false === $this->core->test->is_windows() ) {
				$entry .= ' > /dev/null 2>&1';
			}

			// Update cron.
			$cron_status = $this->update_cron( $entry );

			// If update cron failed, then show a notice.
			if ( true !== $cron_status ) {
				$update_error = true;

				do_action( 'boldgrid_backup_notice',
					'An error occurred when modifying cron jobs.  Please try again.',
					'notice notice-error is-dismissible'
				);
			}
		}

		// If there was no error, then show success notice.
		if ( false === $update_error ) {
			// Success.
			do_action( 'boldgrid_backup_notice',
				'Settings saved.',
				'updated settings-error notice is-dismissible'
			);
		}

		// Return success.
		return ! $update_error;
	}

	/**
	 * Menu callback to display the Backup schedule page.
	 *
	 * @since 1.0
	 *
	 * @return null
	 */
	public function page_backup_settings() {
		// Run the functionality tests.
		$is_functional = $this->core->test->get_is_functional();

		// If tests fail, then show an admin notice and abort.
		if ( false === $is_functional ) {
			do_action( 'boldgrid_backup_notice',
				'Functionality test has failed.  You can go to <a href="' .
				admin_url( 'admin.php?page=boldgrid-backup-test' ) .
				'">Functionality Test</a> to view a report.',
				'notice notice-error is-dismissible'
			);

			return;
		}

		// Display warning on resource usage and backups.
		do_action( 'boldgrid_backup_notice',
			'Warning: Making backups uses resources. When the system is backing up, it will slow down your site for visitors. Furthermore, when the database itself is being copied, your site must “pause” temporarily to preserve data integrity. For most sites, the pause is typically a few seconds and is not noticed by visitors. Large sites take longer though. Please keep the number of backups you have stored and how often you make those backups to a minimum.',
			'notice notice-warning is-dismissible'
		);

		// Get BoldGrid reseller settings.
		$boldgrid_reseller = get_option( 'boldgrid_reseller' );

		// If not part of a reseller, then show the unofficial host notice.
		if ( true === empty( $boldgrid_reseller ) ) {
			do_action( 'boldgrid_backup_notice',
				'Please note that your web hosting provider may have a policy against these types of backups. Please verify with your provider or choose a BoldGrid Official Host.',
				'notice notice-warning is-dismissible'
			);
		}

		// Check for settings update.
		if ( false === empty( $_POST['save_time'] ) ) {
			// Verify nonce.
			check_admin_referer( 'boldgrid-backup-settings', 'settings_auth' );

			$this->update_settings();
		}

		// Enqueue CSS for the settings page.
		wp_enqueue_style( 'boldgrid-backup-admin-settings',
			plugin_dir_url( __FILE__ ) . 'css/boldgrid-backup-admin-settings.css', array(),
			BOLDGRID_BACKUP_VERSION, 'all'
		);

		// Register the JS for the settings page.
		wp_register_script( 'boldgrid-backup-admin-settings',
			plugin_dir_url( __FILE__ ) . 'js/boldgrid-backup-admin-settings.js',
			array(
				'jquery',
			), BOLDGRID_BACKUP_VERSION, false
		);

		// Enqueue JS for the settings page.
		wp_enqueue_script( 'boldgrid-backup-admin-settings' );

		// Get settings.
		$settings = $this->get_settings();

		// Include the page template.
		include BOLDGRID_BACKUP_PATH . '/admin/partials/boldgrid-backup-admin-settings.php';

		return;
	}
}
