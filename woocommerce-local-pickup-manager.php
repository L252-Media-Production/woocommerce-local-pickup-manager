<?php
/**
 * Plugin Name:  GNYC Pickup Manager
 * Plugin URI:   https://gnycyouth.org
 * Description:  Manages local pickup scheduling, slot availability, reminder emails, and order workflow for GNYC Youth Store.
 * Version:      1.0.0
 * Author:       GNYC Youth
 * License:      GPL-2.0+
 * Text Domain:  gnyc-pickup
 *
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'GNYC_PICKUP_VERSION',    '1.0.0' );
define( 'GNYC_PICKUP_PLUGIN_FILE', __FILE__ );
define( 'GNYC_PICKUP_PATH',       plugin_dir_path( __FILE__ ) );
define( 'GNYC_PICKUP_URL',        plugin_dir_url( __FILE__ ) );

// ─── Activation / Deactivation ───────────────────────────────────────────────

register_activation_hook( __FILE__, 'gnyc_pickup_activate' );
function gnyc_pickup_activate() {
    require_once GNYC_PICKUP_PATH . 'includes/class-database.php';
    require_once GNYC_PICKUP_PATH . 'includes/class-settings.php';
    require_once GNYC_PICKUP_PATH . 'includes/class-order-confirmation.php'; // contains Reminders

    GNYC_Pickup_Database::create_table();
    GNYC_Pickup_Settings::seed_defaults();
    GNYC_Pickup_Reminders::schedule_crons();
}

register_deactivation_hook( __FILE__, 'gnyc_pickup_deactivate' );
function gnyc_pickup_deactivate() {
    require_once GNYC_PICKUP_PATH . 'includes/class-settings.php';
    require_once GNYC_PICKUP_PATH . 'includes/class-order-confirmation.php';
    GNYC_Pickup_Reminders::unschedule_crons();
}

// ─── Boot ─────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', 'gnyc_pickup_boot' );
function gnyc_pickup_boot() {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>GNYC Pickup Manager</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    // File map — each file may define one or more classes
    $includes = [
        'includes/class-database.php',          // GNYC_Pickup_Database
        'includes/class-settings.php',          // GNYC_Pickup_Settings
        'includes/class-post-types.php',        // GNYC_Pickup_Post_Types
        'includes/class-order-status.php',      // GNYC_Pickup_Order_Status
        'includes/class-checkout-fields.php',   // GNYC_Pickup_Shipping, GNYC_Pickup_Cart, GNYC_Pickup_Checkout_Fields
        'includes/class-pickup-fields.php',     // GNYC_Pickup_Fields
        'includes/class-ajax-slots.php',        // GNYC_Pickup_Ajax_Slots, GNYC_Pickup_Order_Meta
        'includes/class-order-confirmation.php',// GNYC_Pickup_Order_Confirmation, GNYC_Pickup_Reminders,
                                                // GNYC_Pickup_Availability, GNYC_Pickup_Change_Requests
        'includes/class-admin.php',             // GNYC_Pickup_Admin
    ];

    foreach ( $includes as $file ) {
        $path = GNYC_PICKUP_PATH . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    // Instantiate modules
    new GNYC_Pickup_Post_Types();
    new GNYC_Pickup_Order_Status();
    new GNYC_Pickup_Shipping();
    new GNYC_Pickup_Cart();
    new GNYC_Pickup_Checkout_Fields();
    new GNYC_Pickup_Fields();
    new GNYC_Pickup_Ajax_Slots();
    new GNYC_Pickup_Order_Meta();
    new GNYC_Pickup_Order_Confirmation();
    new GNYC_Pickup_Reminders();
    new GNYC_Pickup_Availability();
    new GNYC_Pickup_Change_Requests();

    if ( is_admin() ) {
        new GNYC_Pickup_Admin();
    }
}
