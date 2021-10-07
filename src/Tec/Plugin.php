<?php
/**
 * Plugin Class.
 *
 * @package Tribe\Extensions\Advanced_ICal_Export_V2
 * @since   1.0.0
 *
 */

namespace Tribe\Extensions\Advanced_ICal_Export_V2;

use Tribe__Date_Utils as Date;

/**
 * Class Plugin
 *
 * @package Tribe\Extensions\Advanced_ICal_Export_V2
 * @since   1.0.0
 *
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

		// Start binds.

		if ( 1 == tribe_context()->get( 'ical' ) ) {
			add_filter( 'tribe_ical_feed_posts_per_page', [ $this, 'set_limit' ], 10 );
			//add_filter( 'tribe_events_views_v2_view_repository_args', [ $this, 'custom_ical_export' ], 10, 3 );
			add_filter( 'tribe_events_views_v2_view_ical_repository_args', [ $this, 'custom_ical_export' ], 10, 2 );
		}

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
	 * Prepares the export feed based on the supplied parameters.
	 *
	 * @param $repository_args
	 * @param $context
	 * @param $view
	 */
	//public function custom_ical_export( $repository_args, $context, $view ) {
	public function custom_ical_export( $repository_args, $view ) {

		// Sanitization.
		$vars = $this->getVars();

		// Bail if not custom iCal export.
		if (
			! isset( $vars['custom'] )
			|| 1 != $vars['custom']
		) {
			return $repository_args;
		}

		// Resetting the arguments.
		$repository_args                   = [];
		$repository_args['order']          = 'ASC';
		$repository_args['posts_per_page'] = isset( $vars['limit'] ) ? $vars['limit'] : 30;
		$repository_args['paged']          = 1;

		// Check if there is a start_date set.
		if (
			isset( $vars['start_date'] )
			&& Date::is_valid_date( $vars['start_date'] )
		) {
			$start_date = Date::Build_date_object( $vars['start_date'] );
		} else {
			// If not, fall back to today.
			$start_date = Date::Build_date_object( date( 'Y-m-d' ) );
		}

		$start = $start_date->format( 'Y-m-d' );

		// Check if there is an end_date set.
		if (
			isset( $vars['end_date'] )
			&& Date::is_valid_date( $vars['end_date'] )
		) {
			$end_date = Date::Build_date_object( $vars['end_date'] );
		} elseif (
			isset( $vars['start_date'] )
			&& ! empty( $vars['start_date'] )
		) {
			// If there is no end date but there was a start year defined, then till the end of that year.
			$end_date = Date::Build_date_object( $start_date->format( 'Y' ) . '-12-31' );
		} else {
			// If no end date and no start date defined, fall back to this year's end.
			$end_date = Date::Build_date_object( date( 'Y' ) . '-12-31' );
		}

		$end = $end_date->format( 'Y-m-d' );

		// If year is defined then use only that.
		if (
			isset ( $vars['year'] )
			&& $vars['year'] >= 1900
			&& $vars['year'] <= 2100
		) {
			$start_date = Date::Build_date_object( $vars['year'] . '-01-01' );
			$start      = $start_date->format( 'Y-m-d' );

			$end_date = Date::Build_date_object( $vars['year'] . '-12-31' );
			$end      = $end_date->format( 'Y-m-d' );
		}

		// Die if the date range is invalid.
		if (
			$start_date > $end_date
			|| $start_date == $end_date
		) {
			die( 'Invalid date range.' );
		}

		$repository_args['date_overlaps'] = [
			$start,
			$end,
		];

		return $repository_args;
	}

	/**
	 * Sets the event limit in the feed based on the parameter.
	 *
	 * @param $count
	 *
	 * @return int
	 */
	function set_limit( $count ) {
		// Sanitization.
		$vars = $this->getVars();

		// Change limit only if it's a custom feed.
		if (
			! isset( $vars['custom'] )
			|| empty( $vars['custom'] )
			|| 1 != $vars['custom']
		) {
			return $count;
		}

		// If limit is -1 or not set, then "unlimited".
		if (
			! isset( $vars['limit'] )
			|| (
				! empty( $vars['limit'] )
				&& -1 == $vars['limit']
			)
		) {
			return 99999;
		}

		return isset( $vars['limit'] ) ? (int) $vars['limit'] : 30;
	}

	/**
	 * Sanitization of submitted parameters.
	 *
	 * @return array|false|null
	 */
	private function getVars() {
		$filters = [
			'ical'       => FILTER_SANITIZE_NUMBER_INT,
			'custom'     => FILTER_SANITIZE_STRING,
			'start_date' => FILTER_SANITIZE_STRING,
			'end_date'   => FILTER_SANITIZE_STRING,
			'limit'      => FILTER_SANITIZE_NUMBER_INT,
			'year'       => FILTER_SANITIZE_NUMBER_INT,
		];

		$vars = filter_input_array( INPUT_GET, $filters );

		return $vars;
	}

}
