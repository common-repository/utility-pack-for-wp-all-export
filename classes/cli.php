<?php

/**
 * Utility Pack for WP All Export Cli Class.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Utility_Pack_WPAE_Cli
 */
class Utility_Pack_WPAE_Cli {

	/**
	 * ## OPTIONS
	 *
	 * <export_id>
	 * : ID of the export.
	 *
	 * [--force-run]
	 * : Run export from the beginning even it was already running.
	 *
	 * [--continue-run]
	 * : Run export from the last exported record if it's already running.
	 *
	 * [--ignore-upload-perms]
	 * : Ignore that the user running WP-CLI cannot write to the uploads directory. Only use this is if your export doesn't save files there.
	 *
	 * ## EXAMPLES
	 *
	 *     wp utility-all-export run 1
	 *     wp utility-all-export run 1 --continue-run
	 *
	 * @when after_wp_load
	 * @param $args
	 * @param $assoc_args
	 */
	function run( $args, $assoc_args ) {

		// Ensure that WP All Export is active before proceeding.
		if( !class_exists('PMXE_Export_Record')){
			WP_CLI::error('WP All Export is not active. Please activate it before trying to run an export.');
		}

		// Ensure that uploads is writable and warn if not.
		if( !is_writable(wp_get_upload_dir()['basedir'] . '/' . WP_ALL_EXPORT_UPLOADS_DIRECTORY) && !array_key_exists('ignore-upload-perms', $assoc_args)){
			WP_CLI::error('The uploads directory is not writable by the user running WP-CLI. This could be a permissions issue or you may need to use sudo to run as the web user.'."\n".'For example: \'sudo -u www-data wp utility-all-export run 1\''."\nUse '--ignore-upload-perms' to bypass this error if you aren't saving export files to the uploads folder.");
		}

		list( $export_ids ) = $args;

		$export_ids = explode(',', $export_ids );

		foreach( $export_ids as $export_id) {
			try {

				$logger = function($m) {
					echo "<p>$m</p>\\n";
				};

				$export = new PMXE_Export_Record();
				$export->getById($export_id);

				// Don't run for real time exports.
				if(isset($export->options['enable_real_time_exports']) && $export->options['enable_real_time_exports'] ) {
					WP_CLI::warning(__('Export ID '.$export_id.': This export is configured to run as records are created and cannot be run via this method.'), PMXE_PLugin::LANGUAGE_DOMAIN);
					continue;
				}

				// Load scheduling export class.
				$scheduledExport = new \Wpae\Scheduling\Export();

				if ($export->isEmpty()) {
					WP_CLI::error(__('Export not found.', PMXE_Plugin::LANGUAGE_DOMAIN));
				}

				// Commands logic is separated out between force and continue for future purposes.
				if(($export->triggered || $export->executing) && !array_key_exists('force-run', $assoc_args) && !array_key_exists('continue-run', $assoc_args)) {
					WP_CLI::error('Export already started. Use --continue-run to resume export or --force-run to restart it.');
				}
				elseif(!$export->triggered && !$export->processing){
					$scheduledExport->trigger($export);
				}elseif(($export->triggered || $export->executing) && array_key_exists('force-run', $assoc_args)){
					// Cancel export and mark it as triggered to start from the beginning.
					$export->set(array(
						'triggered' => 1,
						'processing' => 0,
						'executing' => 0,
						'canceled' => 1,
						'canceled_on' => date('Y-m-d H:i:s')
					))->update();
				}

				// Use the quickest option to get total records to export count.
				if( isset($export->options['exportquery']) && isset($export->options['exportquery']->request)) {
					//Get found rows.
					$query = preg_replace( '@(?<=SELECT).*?(?=FROM.*WHERE 1=1)@s', ' count(*) ', $export->options['exportquery']->request );

					global $wpdb;

					$record_count = $wpdb->get_var( $query );
				}

				// Don't create progress bar if record count fails.
				if( isset($record_count) && is_numeric($record_count) && $record_count > 0 ) {
					// Create progress bar.
					global ${'utility_pack_cli_progress_bar_' . $export_id};

					${'utility_pack_cli_progress_bar_' . $export_id} = \WP_CLI\Utils\make_progress_bar( 'Export ' . $export_id . ' Running', $record_count );

					if( ($export->triggered || $export->executing) && array_key_exists('continue-run', $assoc_args) ){
						// Adjust progress bar when continuing export run.
						${'utility_pack_cli_progress_bar_' . $export_id}->tick($export->exported);
					}
				}else{
					// Use a spinner if we couldn't build the progress bar
					global ${'utility_pack_cli_progress_bar_' . $export_id};

					${'utility_pack_cli_progress_bar_' . $export_id} = new \cli\notify\Spinner('Running Export '. $export_id);
				}

					// Tick progress bar after each exported record.
					add_action( 'pmxe_exported_post', function () {
						$export_id = utility_pack_for_wp_all_export_get_export_id();

						global ${'utility_pack_cli_progress_bar_' . $export_id};

						${'utility_pack_cli_progress_bar_' . $export_id}->tick();

					}, 10, 2 );

				// Ensure the export ID has been set as required by WP All Export.
				if( !isset($_GET['export_id'] )){
					$_GET['export_id'] = $export_id;
				}

				// Modify export options to set records per iteration to a high value to maximize export speed.
				$update_options = $export->options;

				if(is_array($update_options)) {
					$old_records_per_iteration = $update_options['records_per_iteration'];
					$update_options['records_per_iteration'] = apply_filters('coding_chicken_wpae_cli_records_per_iteration', 100000);
					$export->set( ['options'=>$update_options] );
				}

				// Run the export.
				$start = time();
				ob_start();
				while(true) {
					$export->set( array( 'canceled' => 0 ) )->execute( $logger, true );

					// Export complete.
					if( ! (int) $export->triggered and ! (int) $export->processing ){
						$scheduledExport->process( $export );
						break;
					}
				}
				$log_data = ob_get_clean();
				$end = time();

				if( is_numeric($record_count) && $record_count > 0 ) {
					// Export complete.
					${'utility_pack_cli_progress_bar_' . $export_id}->finish();
				}

				// Return records per iteration to previous value or default.
				if(is_array($update_options)) {
					$update_options['records_per_iteration'] = (isset($old_records_per_iteration) && $old_records_per_iteration > 0) ? $old_records_per_iteration : 50;
					$export->set( ['options'=>$update_options] )->update();
				}

				$items = [
					[
						'Exported' => $export->exported,
					]
				];
				WP_CLI\Utils\format_items( 'table', $items, [ 'Exported' ] );
				WP_CLI::success( sprintf(__('Export '.$export_id.' completed. [ time: %s ]', PMXE_Plugin::LANGUAGE_DOMAIN), human_time_diff($start, $end)));
				
			} catch (Exception $e) {
				WP_CLI::error($e->getTraceAsString());
			}
		}
	}

	/**
	 * ## EXAMPLES
	 *
	 *     wp utility-all-export list
	 *
	 * @when after_wp_load
	 *
	 * @subcommand list
	 * @param $args
	 * @param $assoc_args
	 */
	function _list( $args, $assoc_args ) {
		try {
			$items = [];
			$exports = new PMXE_Export_List();
			foreach ($exports->setColumns($exports->getTable() . '.*')->getBy(array('id !=' => ''))->convertRecords() as $export){
				$export->getById($export->id);
				if ( ! $export->isEmpty() ){
					$items[] = [
						'ID' => $export->id,
						'Name' => empty($export->friendly_name) ? $export->name : $export->friendly_name,
						'Exported' => $export->exported,
						'Last Activity' => $export->last_activity
					];
				}
			}

			WP_CLI\Utils\format_items( 'table', $items, array( 'ID', 'Name', 'Exported', 'Last Activity' ) );

		} catch (Exception $e) {
			WP_CLI::error($e->getMessage());
		}
	}
}



