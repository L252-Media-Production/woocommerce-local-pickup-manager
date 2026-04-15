<?php
/**
 * Plugin Name:  WooCommerce Local Pickup Manager
 * Plugin URI:   https://github.com/l252mp/woocommerce-local-pickup-manager
 * Description:  Advanced local pickup scheduling for WooCommerce — slot availability, reminder emails, and order workflow.
 * Version:      1.0.0
 * Author:       L252 Media Production
 * License:      GPL-2.0+
 * Text Domain:  wc-local-pickup
 *
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'WCLPM_VERSION',     '1.0.0' );
define( 'WCLPM_PLUGIN_FILE', __FILE__ );
define( 'WCLPM_PATH',        plugin_dir_path( __FILE__ ) );
define( 'WCLPM_URL',         plugin_dir_url( __FILE__ ) );

// ─── Activation / Deactivation ───────────────────────────────────────────────

register_activation_hook( __FILE__, 'wclpm_activate' );
function wclpm_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( WCLPM_PLUGIN_FILE ) );
        wp_die(
            '<strong>WooCommerce Local Pickup Manager</strong> requires WooCommerce to be installed and active. Please install WooCommerce first.',
            'Plugin Activation Error',
            [ 'back_link' => true ]
        );
    }

    require_once WCLPM_PATH . 'includes/class-database.php';
    require_once WCLPM_PATH . 'includes/class-settings.php';
    require_once WCLPM_PATH . 'includes/class-order-confirmation.php'; // contains Reminders

    WCLPM_Database::create_table();
    WCLPM_Settings::seed_defaults();
    WCLPM_Reminders::schedule_crons();
}

register_deactivation_hook( __FILE__, 'wclpm_deactivate' );
function wclpm_deactivate() {
    require_once WCLPM_PATH . 'includes/class-settings.php';
    require_once WCLPM_PATH . 'includes/class-order-confirmation.php';
    WCLPM_Reminders::unschedule_crons();
}

// ─── Boot ─────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', 'wclpm_boot' );
function wclpm_boot() {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>WooCommerce Local Pickup Manager</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    // File map — each file may define one or more classes
    $includes = [
        'includes/class-database.php',          // WCLPM_Database
        'includes/class-settings.php',          // WCLPM_Settings
        'includes/class-post-types.php',        // WCLPM_Post_Types
        'includes/class-order-status.php',      // WCLPM_Order_Status
        'includes/class-checkout-fields.php',   // WCLPM_Shipping, WCLPM_Cart, WCLPM_Checkout_Fields
        'includes/class-pickup-fields.php',     // WCLPM_Fields
        'includes/class-ajax-slots.php',        // WCLPM_Ajax_Slots, WCLPM_Order_Meta
        'includes/class-order-confirmation.php',// WCLPM_Order_Confirmation, WCLPM_Reminders,
                                                // WCLPM_Availability, WCLPM_Change_Requests
        'includes/class-acf-fields.php',        // WCLPM_ACF_Fields
        'includes/class-admin.php',             // WCLPM_Admin
    ];

    foreach ( $includes as $file ) {
        $path = WCLPM_PATH . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    // Instantiate modules
    new WCLPM_Post_Types();
    new WCLPM_Order_Status();
    new WCLPM_Shipping();
    new WCLPM_Cart();
    new WCLPM_Checkout_Fields();
    new WCLPM_Fields();
    new WCLPM_Ajax_Slots();
    new WCLPM_Order_Meta();
    new WCLPM_Order_Confirmation();
    new WCLPM_Reminders();
    new WCLPM_Availability();
    new WCLPM_Change_Requests();

    new WCLPM_ACF_Fields();

    if ( is_admin() ) {
        new WCLPM_Admin();
    }
}
