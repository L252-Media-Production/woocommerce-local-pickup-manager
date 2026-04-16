<?php
defined( 'ABSPATH' ) || exit;

class WCLPM_Settings {

    const OPTION_KEY = 'wclpm_settings';

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
            'from_email'              => get_option( 'admin_email', '' ),
            'from_name'               => get_bloginfo( 'name' ),
            'reminder_day_before_subject' => 'Pickup Reminder: Your order pickup is TOMORROW — {date}',
            'reminder_morning_subject'    => 'Pickup Reminder: Your order is ready TODAY at {time}',
            'ready_for_pickup_subject'    => 'Your order #{order_number} is ready for pickup!',

            // Email branding
            'logo_url'                => '',   // Falls back to WP site logo; leave blank to omit
            'store_address'           => self::get_wc_store_address(), // Falls back to WC store address; leave blank to omit

            // Reminder timing
            'reminder_send_time'      => '08:00', // HH:MM — time crons fire

            // Slot defaults
            'default_slot_capacity'   => 5,       // Orders per slot
            'booking_window_days'     => 90,      // How far ahead customers can book

            // Change requests
            'allow_change_requests'   => true,
            'change_cutoff_hours'     => 24,      // Hours before pickup; 0 = always allow

            // CRM integration (optional — group affiliation field at checkout)
            'crm_api_url'             => '',   // Base URL; leave blank to disable
            'crm_api_key'             => '',   // Sent as X-Api-Key header
            'crm_group_label'         => 'Organization Affiliation',
            'crm_max_per_page'        => 200,  // Items requested per API call
            'crm_offset_param'        => 'offset',  // Query param name for pagination offset
            'crm_limit_param'         => 'maxSize', // Query param name for page size
            'crm_list_key'            => 'list',    // JSON key containing the results array
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
            'logo_url'                    => esc_url_raw( $data['logo_url'] ?? '' ),
            'store_address'               => sanitize_text_field( $data['store_address'] ?? '' ),
            'reminder_send_time'          => sanitize_text_field( $data['reminder_send_time'] ?? $defaults['reminder_send_time'] ),
            'default_slot_capacity'       => max( 1, intval( $data['default_slot_capacity'] ?? $defaults['default_slot_capacity'] ) ),
            'booking_window_days'         => max( 1, intval( $data['booking_window_days'] ?? $defaults['booking_window_days'] ) ),
            'allow_change_requests'       => ! empty( $data['allow_change_requests'] ),
            'change_cutoff_hours'         => max( 0, intval( $data['change_cutoff_hours'] ?? $defaults['change_cutoff_hours'] ) ),
            'crm_api_url'                 => implode( "\n", array_filter( array_map(
                                                'esc_url_raw',
                                                array_map( 'trim', explode( "\n", $data['crm_api_url'] ?? '' ) )
                                            ) ) ),
            'crm_api_key'                 => sanitize_text_field( $data['crm_api_key'] ?? '' ),
            'crm_group_label'             => sanitize_text_field( $data['crm_group_label'] ?? $defaults['crm_group_label'] ),
            'crm_max_per_page'            => max( 1, intval( $data['crm_max_per_page'] ?? $defaults['crm_max_per_page'] ) ),
            'crm_offset_param'            => sanitize_key( $data['crm_offset_param'] ?? $defaults['crm_offset_param'] ),
            'crm_limit_param'             => sanitize_key( $data['crm_limit_param'] ?? $defaults['crm_limit_param'] ),
            'crm_list_key'                => sanitize_key( $data['crm_list_key'] ?? $defaults['crm_list_key'] ),
        ];
    }

    /**
     * Build a one-line store address from WooCommerce store settings.
     * Used as the default for the email footer address setting.
     */
    private static function get_wc_store_address() {
        $address  = get_option( 'woocommerce_store_address', '' );
        $address2 = get_option( 'woocommerce_store_address_2', '' );
        $city     = get_option( 'woocommerce_store_city', '' );
        $postcode = get_option( 'woocommerce_store_postcode', '' );

        // woocommerce_default_country is stored as "US:NY" or just "US"
        $country_state = get_option( 'woocommerce_default_country', '' );
        $state         = '';
        if ( strpos( $country_state, ':' ) !== false ) {
            $state = explode( ':', $country_state, 2 )[1];
        }

        $parts = array_filter( [ $address, $address2, $city, $state, $postcode ] );
        return implode( ', ', $parts );
    }
}
