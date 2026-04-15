<?php
defined( 'ABSPATH' ) || exit;

class GNYC_Pickup_Settings {

    const OPTION_KEY = 'gnyc_pickup_settings';

    /**
     * Seed default values on first activation.
     */
    public static function seed_defaults() {
        if ( get_option( self::OPTION_KEY ) ) {
            return;
        }

        update_option( self::OPTION_KEY, self::defaults() );
    }

    /**
     * Default settings values.
     */
    public static function defaults() {
        return [
            // Email
            'from_email'              => 'store@gnycyouth.org',
            'from_name'               => get_bloginfo( 'name' ),
            'reminder_day_before_subject' => 'Pickup Reminder: Your order pickup is TOMORROW — {date}',
            'reminder_morning_subject'    => 'Pickup Reminder: Your order is ready TODAY at {time}',
            'ready_for_pickup_subject'    => 'Your order #{order_number} is ready for pickup!',

            // Reminder timing
            'reminder_send_time'      => '08:00', // HH:MM — time crons fire

            // Slot defaults
            'default_slot_capacity'   => 5,       // Orders per slot
            'booking_window_days'     => 90,      // How far ahead customers can book

            // Change requests
            'allow_change_requests'   => true,
            'change_cutoff_hours'     => 24,      // Hours before pickup; 0 = always allow

            // Data retention
            // (reserved for future use — not surfaced in UI per user selection)
        ];
    }

    /**
     * Get all settings, merged with defaults.
     */
    public static function get_all() {
        $saved = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $saved, self::defaults() );
    }

    /**
     * Get a single setting by key.
     *
     * @param string $key
     * @param mixed  $fallback  Returned when key is missing from both saved & defaults.
     */
    public static function get( $key, $fallback = null ) {
        $all = self::get_all();
        return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
    }

    /**
     * Save the full settings array.
     */
    public static function save( $data ) {
        $clean = self::sanitize( $data );
        update_option( self::OPTION_KEY, $clean );
    }

    /**
     * Sanitize incoming settings form data.
     */
    public static function sanitize( $data ) {
        $defaults = self::defaults();

        return [
            'from_email'                  => sanitize_email( $data['from_email'] ?? $defaults['from_email'] ),
            'from_name'                   => sanitize_text_field( $data['from_name'] ?? $defaults['from_name'] ),
            'reminder_day_before_subject' => sanitize_text_field( $data['reminder_day_before_subject'] ?? $defaults['reminder_day_before_subject'] ),
            'reminder_morning_subject'    => sanitize_text_field( $data['reminder_morning_subject'] ?? $defaults['reminder_morning_subject'] ),
            'ready_for_pickup_subject'    => sanitize_text_field( $data['ready_for_pickup_subject'] ?? $defaults['ready_for_pickup_subject'] ),
            'reminder_send_time'          => sanitize_text_field( $data['reminder_send_time'] ?? $defaults['reminder_send_time'] ),
            'default_slot_capacity'       => max( 1, intval( $data['default_slot_capacity'] ?? $defaults['default_slot_capacity'] ) ),
            'booking_window_days'         => max( 1, intval( $data['booking_window_days'] ?? $defaults['booking_window_days'] ) ),
            'allow_change_requests'       => ! empty( $data['allow_change_requests'] ),
            'change_cutoff_hours'         => max( 0, intval( $data['change_cutoff_hours'] ?? $defaults['change_cutoff_hours'] ) ),
        ];
    }
}
