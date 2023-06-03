<?php
/*
Simple Product Images Exporter is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Simple Product Images Exporter is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/
/*
 * Plugin Name:       Simple Product Images Exporter
 * Plugin URI:        https://lpuk.it/simple-product-images-exporter/
 * Description:       Export all product images to a folder.
 * Version:           1.0.0
 * Requires at least: 5.4
 * Requires PHP:      7.2
 * Author:            Lpuk
 * Author URI:        https://lpuk.it/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://lpuk.it/simple-product-images-exporter/
 * Text Domain:       simple-product-images-exporter
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

define( 'SPIE_VERSION', '1.0.0' );
define( 'SPIE__MINIMUM_WP_VERSION', '5.4' );
define( 'SPIE__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( '\SPIE\Simple_Product_Images_Exporter', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( '\SPIE\Simple_Product_Images_Exporter', 'plugin_deactivation' ) );
register_uninstall_hook( __FILE__, array( '\SPIE\Simple_Product_Images_Exporter', 'plugin_uninstall' ) );

require_once( SPIE__PLUGIN_DIR . 'class.simple-product-images-exporter.php' );

add_action( 'init', array( '\SPIE\Simple_Product_Images_Exporter', 'init' ) );
