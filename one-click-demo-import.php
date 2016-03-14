<?php

/*
Plugin Name: One Click Demo Import
Plugin URI: http://www.proteusthemes.com
Description: WordPress import made easy. Theme authors: Enable simple demo import for your theme demo data.
Version: 0.2-alpha
Author: ProteusThemes
Author URI: http://www.proteusthemes.com
License: GPL3
License URI: http://www.gnu.org/licenses/gpl.html
Text Domain: pt-ocdi
*/

// Block direct access to the main plugin file.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Path/URL to root of this plugin, with trailing slash.
define( 'PT_OCDI_PATH', plugin_dir_path( __FILE__ ) );
define( 'PT_OCDI_URL', plugin_dir_url( __FILE__ ) );

// Current version of the plugin.
define( 'PT_OCDI_VERSION', '0.2-alpha' );

// Include files.
require PT_OCDI_PATH . 'inc/class-ocdi-helpers.php';
require PT_OCDI_PATH . 'inc/class-ocdi-importer.php';
require PT_OCDI_PATH . 'inc/class-ocdi-widget-importer.php';
require PT_OCDI_PATH . 'inc/class-ocdi-logger.php';

/**
 * One Click Demo Import class, so we don't have to worry about namespaces.
 */
class PT_One_Click_Demo_Import {

	/**
	 * Private variables used throughout the plugin.
	 */
	private $importer, $plugin_page, $import_files, $logger, $log_file_path;

	/**
	 * Class construct function, to initiate the plugin.
	 */
	function __construct() {

		// Actions.
		add_action( 'admin_menu', array( $this, 'create_plugin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_ocdi_import_demo_data', array( $this, 'import_demo_data_ajax_callback' ) );
		add_action( 'after_setup_theme', array( $this, 'setup_plugin_with_filter_data' ) );
	}


	/**
	 * Creates the plugin page and a submenu item in WP Appearance menu.
	 */
	function create_plugin_page() {
		$this->plugin_page = add_theme_page( 'One Click Demo Import', 'Import Demo Data', 'import', 'pt-one-click-demo-import', array( $this, 'display_plugin_page' ) );
	}


	/**
	 * Plugin page display.
	 */
	function display_plugin_page() {
	?>

	<div class="ocdi  wrap">
		<h2 class="ocdi__title"><span class="dashicons  dashicons-download"></span><?php esc_html_e( 'One Click Demo Import', 'pt-ocdi' ); ?></h2>

		<?php

		// Display warrning if PHP safe mode is enabled, since we wont be able to change the max_execution_time.
		if ( ini_get( 'safe_mode' ) ) {
			printf(
				__( '%sWarning: your server is using %sPHP safe mode%s. This means that you might experience server timeout errors.%s', 'pt-ocdi' ),
				'<div class="notice  notice-error"><p>',
				'<strong>',
				'</strong>',
				'</p></div>'
			);
		}
		?>

		<div class="ocdi__intro-text">
			<p><?php esc_html_e( 'Importing demo data (post, pages, images, theme settings, ...) is the easiest way to setup your theme. It will allow you to quickly edit everything instead of creating content from scratch. When you import the data, the following things might happen:', 'pt-ocdi'); ?></p>

			<ul>
				<li><?php esc_html_e( 'No existing posts, pages, categories, images, custom post types or any other data will be deleted or modified.', 'pt-ocdi' ); ?></li>
				<li><?php esc_html_e( 'Posts, pages, images, widgets and menus will get imported.', 'pt-ocdi' ); ?></li>
				<li><?php esc_html_e( 'Please click "Import Demo Data" button only once and wait, it can take a couple of minutes.', 'pt-ocdi' ); ?></li>
			</ul>
		</div>

		<div class="ocdi__intro-text">
			<p><?php esc_html_e( 'Before you begin, make sure all the required plugins are activated.', 'pt-ocdi' ); ?></p>
		</div>

		<?php if ( empty( $this->import_files ) ) : ?>
			<?php echo OCDI_Helpers::create_wp_info_notice( esc_html__( 'There are no predefined import files available in this theme. Please upload the import files manually!', 'pt-ocdi' ) ); ?>
			<p>
				<label for="data-file-upload"><?php esc_html_e( 'Choose a XML file for data import:', 'pt-ocdi' ); ?></label>
				<input id="ocdi__data-file-upload" type="file" name="data-file-upload">
				<br>
				<small><?php esc_html_e( 'optional', 'pt-ocdi' ); ?></small> <label for="widget-file-upload"><?php esc_html_e( 'Choose a WIE or JSON file for widget import:', 'pt-ocdi' ); ?></label>
				<input id="ocdi__widget-file-upload" type="file" name="widget-file-upload">
			</p>
		<?php elseif ( 1 < count( $this->import_files ) ) : ?>
		<p>
			<select id="ocdi__demo-import-files">
				<?php foreach ( $this->import_files as $index => $import_file ) : ?>
					<option value="<?php echo esc_attr( $index ); ?>">
						<?php echo esc_html( $import_file['import_file_name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php endif; ?>

		<p>
			<button class="ocdi__button  panel-save  button-primary  js-ocdi-import-data"><?php esc_html_e( 'Import Demo Data', 'pt-ocdi' ); ?></button>
		</p>

		<div class="ocdi__response  js-ocdi-ajax-response"></div>
	</div>

	<?php
	}


	/**
	 * Enqueue admin scripts (JS and CSS)
	 *
	 * @param string $hook holds info on which admin page you are currently looking at.
	 */
	function admin_enqueue_scripts( $hook ) {

		// Enqueue the scripts only on the plugin page.
		if ( $this->plugin_page === $hook ) {
			wp_enqueue_script( 'ocdi-main-js', PT_OCDI_URL . 'assets/js/main.js' , array( 'jquery', 'jquery-form' ), PT_OCDI_VERSION );

			wp_localize_script( 'ocdi-main-js', 'ocdi',
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'  => wp_create_nonce( 'ocdi-ajax-verification' ),
					'loader_text' => esc_html__( 'Importing now, please wait!', 'pt-ocdi' ),
				)
			);

			wp_enqueue_style( 'ocdi-main-css', PT_OCDI_URL . 'assets/css/main.css', array() , PT_OCDI_VERSION );
		}
	}


	/**
	 * AJAX download import file callback function.
	 */
	function import_demo_data_ajax_callback() {

		// Verify if the AJAX call is valid (checks nonce and current_user_can).
		OCDI_Helpers::verify_ajax_call();

		// Error messages displayed on front page.
		$frontend_error_messages = '';

		// Create a date and time string to use for demo and log file names.
		$demo_import_start_time = date( apply_filters( 'pt-ocdi/date_format_for_file_names', 'Y-m-d__H-i-s' ) );

		// Define log file path.
		$this->log_file_path = OCDI_Helpers::get_log_path( $demo_import_start_time );

		// Get selected file index or set it to the first file.
		$selected_index = empty( $_POST['selected'] ) ? 0 : absint( $_POST['selected'] );

		/**
		 * Prepare import files.
		 * Manually uploaded import files or predefined import files via filter: pt-ocdi/import_files
		 */
		if ( ! empty( $_FILES ) ) { // Using manual file uploads?

			// Get paths for the uploaded files.
			$selected_import_files = OCDI_Helpers::process_uploaded_files( $_FILES, $this->log_file_path );

			// Set the name of the import files, because we used the uploaded files
			$this->import_files[ $selected_index ]['import_file_name'] = esc_html__( 'Manually uploaded files', 'pt-ocdi' );
		}
		else { // Use predefined import files from wp filter: pt-ocdi/import_files

			if ( ! empty( $this->import_files[ $selected_index ] ) ) {

				// Download the import files (content and widgets files) and save it to variable for later use.
				$selected_import_files = OCDI_Helpers::download_import_files( $this->import_files[ $selected_index ], $demo_import_start_time );

				// Check Errors.
				if ( is_wp_error( $selected_import_files ) ) {

					// Write error to log file and send an AJAX response with the error
					OCDI_Helpers::log_error_and_send_ajax_response( $selected_import_files->get_error_message(), $this->log_file_path, esc_html__( 'Downloaded files', 'pt-ocdi' ) );
				}

				// Add this message to log file.
				$log_added = OCDI_Helpers::append_to_file(
					sprintf(
						__( 'The import files for: %s were successfully downloaded!', 'pt-ocdi' ),
						$this->import_files[ $selected_index ]['import_file_name']
					) . PHP_EOL .
					sprintf(
						__( 'MAX EXECUTION TIME = %s', 'pt-ocdi' ),
						ini_get( 'max_execution_time' )
					) . PHP_EOL .
					sprintf(
						__( 'Files info:%1$sSite URL = %2$s%1$sDemo file = %3$s%1$sWidget file = %4$s', 'pt-ocdi' ),
						PHP_EOL,
						get_site_url(),
						$selected_import_files['data'],
						empty( $selected_import_files['widgets'] ) ? esc_html__( 'not defined!', 'pt-ocdi') : $selected_import_files['widgets']
					),
					$this->log_file_path,
					esc_html__( 'Downloaded files' , 'pt-ocdi' )
				);
			}
			else {

				// Send JSON Error response to the AJAX call.
				wp_send_json( esc_html__( 'No import files specified!', 'pt-ocdi' ) );
			}
		}

		/**
		 * Import demo data.
		 * Returns any errors greater then the "error" logger level, that can be displayed on front page.
		 */
		$frontend_error_messages .= $this->import_data( $selected_import_files['data'] );

		/**
		 * Import widgets.
		 */
		if ( ! empty( $selected_import_files['widgets'] ) ) {

			// Import widgets
			$this->import_widgets( $selected_import_files['widgets'] );
		}

		/**
		 * After import setup.
		 */
		if ( false !== has_action( 'pt-ocdi/after_import' ) ) {

			// Run the after_import action to setup other settings.
			$this->after_import_setup();
		}

		// Display final messages (success or error messages).
		if ( empty( $frontend_error_messages ) ) {
			$response['message'] = $this->sucessfull_import_finished_message();
		}
		else {
			$response['message'] = $frontend_error_messages . '<br>';
			$response['message'] .= $this->errors_import_finished_message( $this->log_file_path );
		}

		wp_send_json( $response );
	}


	/**
	 * Import data from an WP XML file.
	 *
	 * @param string $import_file_path path to the import file.
	 */
	function import_data( $import_file_path ) {

		// This should be replaced with multiple AJAX calles (import in smaller chunks)
		// so that it would not come to the Internal Error, because of the PHP script timeout.
		// Also this function has no effect when PHP is running in safe mode
		// http://php.net/manual/en/function.set-time-limit.php.
		// Increase PHP max execution time.
		set_time_limit( apply_filters( 'pt-ocdi/set_time_limit_for_demo_data_import', 120 ) );

		// Import demo data.
		if ( ! empty( $import_file_path ) ) {

			ob_start();
				$this->importer->import( $import_file_path );
			$message = ob_get_clean();

			// Add this message to log file.
			$log_added = OCDI_Helpers::append_to_file(
				$message . PHP_EOL . 'MAX EXECUTION TIME = ' . ini_get( 'max_execution_time' ),
				$this->log_file_path,
				esc_html__( 'Importing demo data' , 'pt-ocdi' )
			);
		}

		// Return any error messages for the front page output.
		return $this->logger->error_output;
	}


	/**
	 * Import widgets from JSON file.
	 *
	 * @param string $widget_import_file_path path to the widget import file.
	 */
	function import_widgets( $widget_import_file_path ) {

		// Widget import results.
		$results = array();

		// Create an instance of the Widget Importer.
		$widget_importer = new OCDI_Widget_Importer();

		// Import widgets.
		if ( ! empty( $widget_import_file_path ) ) {

			// Import widgets and get result.
			$results = $widget_importer->import_widgets( $widget_import_file_path );
		}

		// Check for errors.
		if ( is_wp_error( $results ) ) {

			// Write error to log file and send an AJAX response with the error
			OCDI_Helpers::log_error_and_send_ajax_response( $widget_output->get_error_message(), $this->log_file_path, esc_html__( 'Importing widgets', 'pt-ocdi' ) );
		}

		ob_start();
			$widget_importer->format_results_for_log( $results );
		$message = ob_get_clean();

		// Add this message to log file.
		$log_added = OCDI_Helpers::append_to_file(
			$message,
			$this->log_file_path,
			esc_html__( 'Importing widgets' , 'pt-ocdi' )
		);
	}


	/**
	 * Setup other things after the whole import process is finished.
	 */
	function after_import_setup() {

		// Enable users to add custom code to the end of the import process.
		// Append any output to the AJAX response message.
		ob_start();
			do_action( 'pt-ocdi/after_import' );
		$message = ob_get_clean();

		// Add this message to log file.
		$log_added = OCDI_Helpers::append_to_file(
			$message,
			$this->log_file_path,
			esc_html__( 'After import setup' , 'pt-ocdi' )
		);
	}


	/**
	 * Get data from filters, after the theme has loaded and instantiate the importer.
	 */
	function setup_plugin_with_filter_data() {

		// Get info of import data files and filter it.
		$this->import_files = OCDI_Helpers::validate_import_file_info( apply_filters( 'pt-ocdi/import_files', array() ) );

		// Importer options array.
		$importer_options = apply_filters( 'pt-ocdi/importer_options', array(
			'fetch_attachments' => true,
		) );

		// Create importer instance with proper parameters.
		$this->importer = new OCDI_Importer( $importer_options );

		// Logger options for the importer.
		$logger_options = apply_filters( 'pt-ocdi/logger_options', array(
			'logger_min_level' => 'warning',
		) );

		// Configure logger instance and set it to the importer.
		$this->logger            = new OCDI_Logger();
		$this->logger->min_level = $logger_options['logger_min_level'];
		$this->importer->set_logger( $this->logger );
	}


	/**
	 * Return successful import finished message.
	 */
	private function sucessfull_import_finished_message() {
		return sprintf(
			__( '%1$s%3$sThat\'s it, all done!%4$s%2$sThe demo import has finished. Please check your page and make sure that everything has imported correctly. If it did, you can deactivate the %3$sOne Click Demo Import%4$s plugin, because it has done its job.%5$s', 'pt-ocdi' ),
			'<div class="notice  notice-success"><p>',
			'<br>',
			'<strong>',
			'</strong>',
			'</p></div>'
		);
	}


	/**
	 * Return import finished message with errors.
	 *
	 * @param string $log_file_path path to the log file.
	 */
	private function errors_import_finished_message( $log_file_path = '' ) {
		return sprintf(
			__( '%1$sThe demo import has finished, but there were some import errors.%2$sMore details about the errors can be found in this %3$s%5$slog file%6$s%4$s%7$s', 'pt-ocdi' ),
			'<div class="notice  notice-error"><p>',
			'<br>',
			'<strong>',
			'</strong>',
			'<a href="' . OCDI_Helpers::get_log_url( $log_file_path ) .'" target="_blank">',
			'</a>',
			'</p></div>'
		);
	}


	/**
	 * Return response of en error.
	 *
	 * @param object $wp_error object of class WP_Error.
	 */
	private function create_wp_error_notice_response( $wp_error ) {
		$response               = array();
		$response['error_code'] = $wp_error->get_error_code();
		$response['message']    = sprintf(
			'%s%s%s',
			'<div class="notice  notice-error"><p>',
			$wp_error->get_error_message(),
			'</p></div>'
		);
		return $response;
	}
}

new PT_One_Click_Demo_Import();
