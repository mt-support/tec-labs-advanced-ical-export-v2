<?php
/**
 * Plugin Name:       The Events Calendar Extension: Advanced iCal Export v2
 * Plugin URI:        
 * GitHub Plugin URI: https://github.com/mt-support/tec-labs-advanced-ical-export-v2
 * Description:       
 * Version:           1.0.0
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       __TRIBE_DOMAIN__
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

/**
 * Define the base file that loaded the plugin for determining plugin path and other variables.
 *
 * @since 1.0.0
 *
 * @var string Base file that loaded the plugin.
 */
define( 'TRIBE_EXTENSION_ADVANCED_ICAL_EXPORT_V2_FILE', __FILE__ );

/**
 * Register and load the service provider for loading the extension.
 *
 * @since 1.0.0
 */
function tribe_extension_advanced_ical_export_v2() {
	// When we don't have autoloader from common we bail.
	if ( ! class_exists( 'Tribe__Autoloader' ) ) {
		return;
	}

	// Register the namespace so we can the plugin on the service provider registration.
	Tribe__Autoloader::instance()->register_prefix(
		'\\Tribe\\Extensions\\Advanced_ICal_Export_V2\\',
		__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Tec',
		'advanced-ical-export-v2'
	);

	// Deactivates the plugin in case of the main class didn't autoload.
	if ( ! class_exists( '\Tribe\Extensions\Advanced_ICal_Export_V2\Plugin' ) ) {
		tribe_transient_notice(
			'advanced-ical-export-v2',
			'<p>' . esc_html__( 'Couldn\'t properly load "The Events Calendar Extension: Advanced iCal Export v2" the extension was deactivated.', 'tec-labs-advanced-ical-export-v2' ) . '</p>',
			[],
			// 1 second after that make sure the transient is removed.
			1
		);

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		deactivate_plugins( __FILE__, true );
		return;
	}

	tribe_register_provider( '\Tribe\Extensions\Advanced_ICal_Export_V2\Plugin' );
}

// Loads after common is already properly loaded.
add_action( 'tribe_common_loaded', 'tribe_extension_advanced_ical_export_v2' );
