<?php
defined( 'ABSPATH' ) || exit;

class GNYC_Pickup_Database {

    /**
     * Create the pickup_bookings table.
     * Safe to call repeatedly — uses CREATE TABLE IF NOT EXISTS via dbDelta.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'pickup_bookings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id            BIGINT(20) UNSIGNED NOT NULL,
            product_id          BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            location_id         BIGINT(20) UNSIGNED NOT NULL,
            pickup_date         DATE NOT NULL,
            pickup_time         VARCHAR(10) NOT NULL,
            customer_email      VARCHAR(255) NOT NULL,
            reminder_day_before TINYINT(1) DEFAULT 0,
            reminder_morning    TINYINT(1) DEFAULT 0,
            change_requested    TINYINT(1) DEFAULT 0,
            change_request_note TEXT DEFAULT NULL,
            created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id    (order_id),
            KEY product_id  (product_id),
            KEY location_id (location_id),
            KEY pickup_date (pickup_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get the full table name.
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'pickup_bookings';
    }
}
