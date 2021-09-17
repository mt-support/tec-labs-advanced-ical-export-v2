<?php
/**
 * Plugin Class.
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\Advanced_ICal_Export_V2
 */

namespace Tribe\Extensions\Advanced_ICal_Export_V2;

use Tribe__Date_Utils as Date;
/**
 * Class Plugin
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\Advanced_ICal_Export_V2
 */
class Plugin extends \tad_DI52_ServiceProvider {
	/**
	 * Stores the version for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Stores the base slug for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const SLUG = 'advanced-ical-export-v2';

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TRIBE_EXTENSION_ADVANCED_ICAL_EXPORT_V2_FILE;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin Directory.
	 */
	public $plugin_dir;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin path.
	 */
	public $plugin_path;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin URL.
	 */
	public $plugin_url;

	/**
	 * @since 1.0.0
	 *
	 * @var Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private $settings;

	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Set up the plugin provider properties.
		$this->plugin_path = trailingslashit( dirname( static::FILE ) );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = plugins_url( $this->plugin_dir, $this->plugin_path );

		// Register this provider as the main one and use a bunch of aliases.
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.advanced_ical_export_v2', $this );
		$this->container->singleton( 'extension.advanced_ical_export_v2.plugin', $this );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Do the settings.
		// TODO: Remove if not using settings
		$this->get_settings();

		// Start binds.

		add_filter( 'tribe_ical_feed_posts_per_page', [ $this, 'set_limit' ], 10 );
		add_filter( 'tribe_events_views_v2_view_repository_args', [ $this, 'custom_ical_export' ], 10, 3 );

		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * Checks whether the plugin dependency manifest is satisfied or not.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the plugin dependency manifest is satisfied or not.
	 */
	protected function check_plugin_dependencies() {
		$this->register_plugin_dependencies();

		return tribe_check_plugin( static::class );
	}

	/**
	 * Registers the plugin and dependency manifest among those managed by Tribe Common.
	 *
	 * @since 1.0.0
	 */
	protected function register_plugin_dependencies() {
		$plugin_register = new Plugin_Register();
		$plugin_register->register_plugin();

		$this->container->singleton( Plugin_Register::class, $plugin_register );
		$this->container->singleton( 'extension.advanced_ical_export_v2', $plugin_register );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @return string
     *
	 * @see \Tribe\Extensions\Advanced_ICal_Export_V2\Settings::set_options_prefix()
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tec-labs-advanced-ical-export-v2' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings( $this->get_options_prefix() );
		}

		return $this->settings;
	}

	/**
	 * Get all of this extension's options.
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_all_options() {
		$settings = $this->get_settings();

		return $settings->get_all_options();
	}

	/**
	 * Get a specific extension option.
	 *
	 * @param $option
	 * @param string $default
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_option( $option, $default = '' ) {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}

	/**
	 * @param $repository_args
	 * @param $context
	 * @param $view
	 */
	public function custom_ical_export( $repository_args, $context, $view ) {

		// Sanitization
		$filters = [
			'ical'          => FILTER_SANITIZE_NUMBER_INT,
			'custom'        => FILTER_SANITIZE_STRING,
			'start_date'    => FILTER_SANITIZE_STRING,
			'end_date'      => FILTER_SANITIZE_STRING,
			'year'          => FILTER_SANITIZE_NUMBER_INT,
		];
		$vars = filter_input_array( INPUT_GET, $filters );

		// Bail if not custom iCal export
		if ( ! tribe_context()->get( 'ical' ) && $vars['custom'] != 1 ) {
			return $repository_args;
		}

		// Resetting the arguments
		$repository_args = [];
//		unset( $repository_args['ends_after'] );
		$repository_args['order'] = 'ASC';
		$repository_args['posts_per_page'] = -1;
		$repository_args['paged'] = 1;

//		$date = Date::Build_date_object( '2021-01-01' );

		// Check if there is a start_date set
		if ( isset( $vars['start_date'] ) && Date::is_valid_date( $vars['start_date'] ) ) {
			$start_date = Date::Build_date_object( $vars['start_date'] );
		}
		// If not, fall back to this year's beginning
		else {
			$start_date = Date::Build_date_object( date( 'Y' ) . '-01-01' );
		}

		$start = $start_date->format( 'Y-m-d' );

		// Check if there is an end_date set
		if ( isset( $vars['end_date'] ) && Date::is_valid_date( $vars['end_date'] ) ) {
			$end_date = Date::Build_date_object( $vars['end_date'] );
		}
		// If there is no end date but there was a start year defined, then till the end of that year
		elseif ( isset( $vars['start_date'] ) && ! empty( $vars['start_date'] ) ) {
			$end_date = Date::Build_date_object( $start_date->format( 'Y' ) . '-12-31' );
		}
		// If no end date defined, fall back to this year's end
		else {
			$end_date = Date::Build_date_object( 'end of the year' );
		}

		$end = $end_date->format( 'Y-m-d' );

		$repository_args['date_overlaps'] = [
			$start,
			$end,
		];

		return $repository_args;

		/*
		 * Default repo args
		posts_per_page = {int} 13
		paged = {int} 1
		search = ""
		hidden_from_upcoming = false
		view_override_offset = true
		ends_after = "now"
		order = "ASC"
		context_hash = "0000000030a7c93a000000006b89b679"
		 * */
	}

	function set_limit() {
		$x = tribe_context()->get( 'ical' );
		$y = $_GET['custom'];
		$z = 99;

		return $z;
	}
}
