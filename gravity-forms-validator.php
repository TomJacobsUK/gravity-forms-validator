<?php
/**
 * Plugin Name: Gravity Forms Validator
 * Plugin URI: https://tomjacobs.co.uk
 * Description: A plugin to extend and improve Gravity Forms validation.
 * Version: 1.0.3
 * Author: TomJacobsUK
 * Author URI: https://github.com/TomJacobsUK
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Display admin notice if dependencies are missing
    function gf_validator_missing_dependencies_notice() {
        ?>
        <div class="error notice">
            <p><?php _e('Gravity Forms Validator: Required dependencies are missing. Please run "composer install" in the plugin directory.', 'gravity-forms-validator'); ?></p>
        </div>
        <?php
    }
    add_action('admin_notices', 'gf_validator_missing_dependencies_notice');
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

// Check dependencies on plugin activation
register_activation_hook(__FILE__, 'gf_validator_check_dependencies');

function gf_validator_check_dependencies() {
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Gravity Forms Validator requires Composer dependencies. Please run "composer install" in the plugin directory before activating.');
    }
}

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