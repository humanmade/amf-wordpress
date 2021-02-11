<?php
/**
 * AMF WordPress
 *
 * @package           HumanMade\AMFWordPress
 * @author            Human Made
 * @copyright         2021 Human Made
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: AMF WordPress
 * Plugin URI:        https://github.com/humanmade/amf-wordpress
 * Description:       Use another WordPress site as source for your media library.
 * Version:           0.1.0
 * Requires at least: 2.8
 * Requires PHP:      7.2
 * Author:            Human Made
 * Author URI:        https://humanmade.com/
 * Text Domain:       amf-wordpress
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace AMFWordPress;

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	include_once __DIR__ . '/vendor/autoload.php';
}

include_once __DIR__ . '/wp-content/plugins/asset-manager-framework/plugin.php';

bootstrap();
