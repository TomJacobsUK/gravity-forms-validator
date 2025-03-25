<?php
/**
 * Plugin Name: Gravity Forms Validator
 * Plugin URI: https://tomjacobs.co.uk
 * Description: A plugin to extend and improve Gravity Forms validation.
 * Version: 1.0.1
 * Author: TomJacobsUK
 * Author URI: https://github.com/TomJacobsUK
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the main class for Gravity Forms validation
require_once plugin_dir_path( __FILE__ ) . 'includes/class-gf-validator.php';

// Initialize the plugin
function gf_validator_init() {
    if ( class_exists( 'GF_Validator' ) ) {
        $gf_validator = new GF_Validator();
    }
}
add_action( 'init', 'gf_validator_init' );

// Initialize the plugin for each site in a multisite network
function gf_validator_init_multisite() {
    if ( is_multisite() ) {
        add_action( 'network_admin_menu', 'gf_validator_init' );
    } else {
        gf_validator_init();
    }
}
add_action( 'init', 'gf_validator_init_multisite' );
?>