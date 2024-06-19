<?php
/*
Plugin Name: Drupal to WordPress Importer
Description: A plugin to import data from Drupal to WordPress.
Version: 1.0
Author: Dhaval Parikh
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include necessary files
include_once plugin_dir_path( __FILE__ ) . 'includes/class-drupal-importer.php';
include_once plugin_dir_path( __FILE__ ) . 'admin/admin-menu.php';

// Activation hook
function drupal_importer_activate() {
    // Activation code here
}
register_activation_hook( __FILE__, 'drupal_importer_activate' );

// Deactivation hook
function drupal_importer_deactivate() {
    // Deactivation code here
}
register_deactivation_hook( __FILE__, 'drupal_importer_deactivate' );
