<?php
defined( 'ABSPATH' ) || exit;

// ─── Ajax Slots ───────────────────────────────────────────────────────────────

class WCLPM_Ajax_Slots {

    public function __construct() {
        add_action( 'wp_ajax_get_pickup_dates',        [ $this, 'get_dates_handler' ] );
        add_action( 'wp_ajax_nopriv_get_pickup_dates', [ $this, 'get_dates_handler' ] );
        add_action( 'wp_ajax_get_pickup_slots',        [ $this, 'get_slots_handler' ] );
        add_action( 'wp_ajax_nopriv_get_pickup_slots', [ $this, 'get_slots_handler' ] );
    }

    /**
     * Return available pickup dates for a location.
     * Uses admin setting for booking_window_days as the default lookahead.
     */
    public function get_dates_handler() {
        check_ajax_referer( 'pickup_nonce', 'nonce' );

        $location_id = intval( $_POST['location_id'] );
        $date_start  = sanitize_text_field( $_POST['date_start'] ?? '' );
        $date_end    = sanitize_text_field( $_POST['date_end'] ?? '' );

        if ( ! $location_id ) {
            wp_send_json_error( 'Missing location' );
        }

        $schedule      = wclpm_get_field( 'pickup_schedule', $location_id );
        $default_hours = wclpm_get_field( 'default_weekly_hours', $location_id );
        $closed_dates  = wclpm_get_field( 'closed_dates', $location_id );
        $lead_time     = intval( wclpm_get_field( 'lead_time_hours', $location_id ) ?: 24 );
        $timezone      = new DateTimeZone( wp_timezone_string() );
        $now           = new DateTime( 'now', $timezone );
        $today         = new DateTime( 'today', $timezone );

        // Build lookups
        $closed_lookup     = $this->build_closed_lookup( $closed_dates );
        $specific_schedule = $this->build_specific_schedule( $schedule );
        $default_lookup    = $this->build_default_lookup( $default_hours );

        $get_time_ranges = function( $date_ymd, $day_name ) use ( $specific_schedule, $default_lookup ) {
            return $specific_schedule[ $date_ymd ] ?? $default_lookup[ $day_name ] ?? [];
        };

        $get_closing_time = function( $date_ymd, $day_name ) use ( $get_time_ranges ) {
            $ranges  = $get_time_ranges( $date_ymd, $day_name );
            $closing = null;
            foreach ( $ranges as $range ) {
                if ( ! empty( $range['end_time'] ) && ( $closing === null || $range['end_time'] > $closing ) ) {
                    $closing = $range['end_time'];
                }
            }
            return $closing;
        };

        $earliest_pickup = $this->calculate_earliest_pickup( $now, $today, $get_closing_time, $closed_lookup, $get_time_ranges, $lead_time, $timezone );

        // Date window
        $start_dt = null;
        $end_dt   = null;

        if ( ! empty( $date_start ) ) {
            $start_dt = DateTime::createFromFormat( 'Ymd', $date_start );
        }
        if ( ! empty( $date_end ) ) {
            $end_dt = DateTime::createFromFormat( 'Ymd', $date_end );
        }

        if ( ! $start_dt || $start_dt < $today ) {
            $start_dt = clone $today;
        }
        if ( ! $end_dt ) {
            $booking_window = intval( WCLPM_Settings::get( 'booking_window_days', 90 ) );
            $end_dt         = clone $today;
            $end_dt->modify( '+' . $booking_window . ' days' );
        }

        $available_dates = [];
        $current         = clone $start_dt;

        while ( $current <= $end_dt ) {
            $date_ymd  = $current->format( 'Ymd' );
            $day_name  = strtolower( $current->format( 'l' ) );

            if ( isset( $closed_lookup[ $date_ymd ] ) ) {
                $current->modify( '+1 day' );
                continue;
            }

            $time_ranges = $get_time_ranges( $date_ymd, $day_name );
            if ( empty( $time_ranges ) ) {
                $current->modify( '+1 day' );
                continue;
            }

            $has_slots  = false;
            $slot_index = 0;
            foreach ( $time_ranges as $range ) {
                if ( empty( $range['start_time'] ) || empty( $range['end_time'] ) ) {
                    continue;
                }
                $slot_current = DateTime::createFromFormat( 'Y-m-d H:i', $current->format( 'Y-m-d' ) . ' ' . $range['start_time'], $timezone );
                $slot_end     = DateTime::createFromFormat( 'Y-m-d H:i', $current->format( 'Y-m-d' ) . ' ' . $range['end_time'], $timezone );
                if ( ! $slot_current || ! $slot_end ) {
                    continue;
                }
                while ( $slot_current < $slot_end ) {
                    $slot_index++;
                    if ( $date_ymd === $earliest_pickup->format( 'Ymd' ) && $slot_index <= 4 ) {
                        $slot_current->modify( '+15 minutes' );
                        continue;
                    }
                    if ( $slot_current >= $earliest_pickup ) {
                        $has_slots = true;
                        break 2;
                    }
                    $slot_current->modify( '+15 minutes' );
                }
            }

            if ( $has_slots ) {
                $date_obj          = clone $current;
                $available_dates[] = [
                    'value'   => $date_ymd,
                    'label'   => $date_obj->format( 'l, F j, Y' ),
                    'day'     => $date_obj->format( 'j' ),
                    'weekday' => intval( $date_obj->format( 'w' ) ),
                ];
            }

            $current->modify( '+1 day' );
        }

        wp_send_json_success( [ 'dates' => $available_dates ] );
    }

    /**
     * Return available time slots for a location + date.
     * Respects per-location capacity override, with admin default as fallback.
     */
    public function get_slots_handler() {
        check_ajax_referer( 'pickup_nonce', 'nonce' );

        $location_id = intval( $_POST['location_id'] );
        $date        = sanitize_text_field( $_POST['date'] );

        if ( ! $location_id || ! $date ) {
            wp_send_json_error( 'Missing parameters' );
        }

        $per_loc_capacity = wclpm_get_field( 'location_capacity', $location_id );
        $capacity         = $per_loc_capacity
            ? intval( $per_loc_capacity )
            : intval( WCLPM_Settings::get( 'default_slot_capacity', 5 ) );

        $schedule      = wclpm_get_field( 'pickup_schedule', $location_id );
        $default_hours = wclpm_get_field( 'default_weekly_hours', $location_id );
        $closed_dates  = wclpm_get_field( 'closed_dates', $location_id );
        $lead_time     = intval( wclpm_get_field( 'lead_time_hours', $location_id ) ?: 24 );
        $timezone      = new DateTimeZone( wp_timezone_string() );
        $now           = new DateTime( 'now', $timezone );
        $today         = new DateTime( 'today', $timezone );

        $closed_lookup     = $this->build_closed_lookup( $closed_dates );
        $specific_schedule = $this->build_specific_schedule( $schedule );
        $default_lookup    = $this->build_default_lookup( $default_hours );

        if ( isset( $closed_lookup[ $date ] ) ) {
            wp_send_json_error( 'Location is closed on this date' );
        }

        $time_ranges = $specific_schedule[ $date ] ?? [];
        if ( empty( $time_ranges ) ) {
            $date_dt = DateTime::createFromFormat( 'Ymd', $date );
            if ( $date_dt ) {
                $day_name    = strtolower( $date_dt->format( 'l' ) );
                $time_ranges = $default_lookup[ $day_name ] ?? [];
            }
        }

        if ( empty( $time_ranges ) ) {
            wp_send_json_error( 'No time ranges for this date' );
        }

        $get_time_ranges = function( $d, $dn ) use ( $specific_schedule, $default_lookup ) {
            return $specific_schedule[ $d ] ?? $default_lookup[ $dn ] ?? [];
        };
        $get_closing_time = function( $d, $dn ) use ( $get_time_ranges ) {
            $ranges  = $get_time_ranges( $d, $dn );
            $closing = null;
            foreach ( $ranges as $r ) {
                if ( ! empty( $r['end_time'] ) && ( $closing === null || $r['end_time'] > $closing ) ) {
                    $closing = $r['end_time'];
                }
            }
            return $closing;
        };

        $earliest_pickup  = $this->calculate_earliest_pickup( $now, $today, $get_closing_time, $closed_lookup, $get_time_ranges, $lead_time, $timezone );
        $earliest_day_ymd = $earliest_pickup->format( 'Ymd' );
        $is_earliest_day  = $date === $earliest_day_ymd;

        global $wpdb;
        $table      = WCLPM_Database::table();
        $slots      = [];
        $slot_index = 0;

        foreach ( $time_ranges as $range ) {
            if ( empty( $range['start_time'] ) || empty( $range['end_time'] ) ) {
                continue;
            }

            $current = DateTime::createFromFormat( 'Y-m-d H:i', ( new DateTime( $date, $timezone ) )->format( 'Y-m-d' ) . ' ' . $range['start_time'], $timezone );
            $end     = DateTime::createFromFormat( 'Y-m-d H:i', ( new DateTime( $date, $timezone ) )->format( 'Y-m-d' ) . ' ' . $range['end_time'], $timezone );

            if ( ! $current || ! $end ) {
                continue;
            }

            while ( $current < $end ) {
                $slot_time  = $current->format( 'H:i' );
                $slot_label = $current->format( 'g:i A' );
                $slot_index++;

                if ( $is_earliest_day && $slot_index <= 4 ) {
                    $current->modify( '+15 minutes' );
                    continue;
                }

                if ( $current < $earliest_pickup ) {
                    $current->modify( '+15 minutes' );
                    continue;
                }

                $booked    = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE location_id=%d AND pickup_date=%s AND pickup_time=%s",
                    $location_id, $date, $slot_time
                ) );
                $available = intval( $capacity ) - intval( $booked );

                if ( $available > 0 ) {
                    $slots[] = [
                        'value' => $slot_time,
                        'label' => $slot_label . ' (' . $available . ' spots left)',
                    ];
                }

                $current->modify( '+15 minutes' );
            }
        }

        if ( empty( $slots ) ) {
            wp_send_json_error( 'No available slots for this date' );
        }

        wp_send_json_success( [ 'slots' => $slots ] );
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function build_closed_lookup( $closed_dates ) {
        $lookup = [];
        if ( ! empty( $closed_dates ) ) {
            foreach ( $closed_dates as $c ) {
                if ( ! empty( $c['closed_date'] ) ) {
                    $lookup[ $c['closed_date'] ] = $c['closed_reason'] ?? '';
                }
            }
        }
        return $lookup;
    }

    private function build_specific_schedule( $schedule ) {
        $lookup = [];
        if ( ! empty( $schedule ) ) {
            foreach ( $schedule as $row ) {
                if ( empty( $row['schedule_dates'] ) || empty( $row['schedule_time_ranges'] ) ) {
                    continue;
                }
                foreach ( $row['schedule_dates'] as $date_row ) {
                    if ( ! empty( $date_row['schedule_date'] ) ) {
                        $lookup[ $date_row['schedule_date'] ] = $row['schedule_time_ranges'];
                    }
                }
            }
        }
        return $lookup;
    }

    private function build_default_lookup( $default_hours ) {
        $lookup = [];
        if ( ! empty( $default_hours ) ) {
            foreach ( $default_hours as $row ) {
                if ( ! empty( $row['day_of_week'] ) && ! empty( $row['default_time_ranges'] ) ) {
                    $lookup[ strtolower( $row['day_of_week'] ) ] = $row['default_time_ranges'];
                }
            }
        }
        return $lookup;
    }

    private function calculate_earliest_pickup( $now, $today, $get_closing_time, $closed_lookup, $get_time_ranges, $lead_time, $timezone ) {
        $today_ymd      = $today->format( 'Ymd' );
        $today_day_name = strtolower( $today->format( 'l' ) );
        $today_closing  = $get_closing_time( $today_ymd, $today_day_name );
        $now_time_str   = $now->format( 'H:i' );

        if ( $today_closing && $now_time_str < $today_closing ) {
            $lead_start = clone $now;
        } else {
            $search     = clone $today;
            $lead_start = null;

            for ( $i = 1; $i <= 14; $i++ ) {
                $search->modify( '+1 day' );
                $search_ymd      = $search->format( 'Ymd' );
                $search_day_name = strtolower( $search->format( 'l' ) );

                if ( isset( $closed_lookup[ $search_ymd ] ) ) {
                    continue;
                }

                $ranges  = $get_time_ranges( $search_ymd, $search_day_name );
                $opening = null;
                foreach ( $ranges as $range ) {
                    if ( ! empty( $range['start_time'] ) && ( $opening === null || $range['start_time'] < $opening ) ) {
                        $opening = $range['start_time'];
                    }
                }

                if ( $opening ) {
                    $lead_start = DateTime::createFromFormat( 'Y-m-d H:i', $search->format( 'Y-m-d' ) . ' ' . $opening, $timezone );
                    break;
                }
            }

            if ( ! $lead_start ) {
                $lead_start = clone $now;
            }
        }

        $earliest = clone $lead_start;
        $earliest->modify( '+' . $lead_time . ' hours' );
        return $earliest;
    }
}

// ─── Order Meta ───────────────────────────────────────────────────────────────

class WCLPM_Order_Meta {

    public function __construct() {
        add_action( 'woocommerce_checkout_process',              [ $this, 'validate' ] );
        add_action( 'woocommerce_checkout_order_processed',      [ $this, 'save' ], 10, 3 );
        add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'display_in_admin' ], 10, 1 );
        add_action( 'woocommerce_email_order_details',           [ $this, 'display_in_email' ], 5, 4 );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_on_order_page' ], 10, 1 );
        add_filter( 'woocommerce_email_from_address',            [ $this, 'set_from_email' ] );
        add_filter( 'woocommerce_email_from_name',               [ $this, 'set_from_name' ] );
        add_filter( 'wp_mail_from',                              [ $this, 'set_wp_mail_from' ] );
        add_filter( 'wp_mail_from_name',                         [ $this, 'set_wp_mail_from_name' ] );
        add_filter( 'wp_mail',                                   [ $this, 'add_reply_to_header' ] );
    }

    private function is_local_pickup() {
        $chosen = WC()->session->get( 'chosen_shipping_methods' );
        foreach ( (array) $chosen as $method ) {
            if ( strpos( $method, 'local_pickup' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    public function validate() {
        $items = WCLPM_Fields::get_pickup_cart_items();
        if ( empty( $items ) || ! $this->is_local_pickup() ) {
            return;
        }

        if ( empty( $_POST['pickup_location_order'] ) ) {
            wc_add_notice( 'Please select a pickup location.', 'error' );
        }
        if ( empty( $_POST['pickup_date_order'] ) ) {
            wc_add_notice( 'Please select a pickup date.', 'error' );
        }
        if ( empty( $_POST['pickup_time_order'] ) ) {
            wc_add_notice( 'Please select a pickup time.', 'error' );
        }
    }

    public function save( $order_id, $posted_data, $order ) {
        $items = WCLPM_Fields::get_pickup_cart_items();
        if ( empty( $items ) || ! $this->is_local_pickup() ) {
            return;
        }

        $location_id = intval( $_POST['pickup_location_order'] ?? 0 );
        $date        = sanitize_text_field( $_POST['pickup_date_order'] ?? '' );
        $time        = sanitize_text_field( $_POST['pickup_time_order'] ?? '' );

        if ( ! $location_id || ! $date || ! $time ) {
            return;
        }

        $location_name    = get_the_title( $location_id );
        $location_address = wclpm_get_field( 'location_address', $location_id );
        $date_obj         = DateTime::createFromFormat( 'Ymd', $date );
        $date_display     = $date_obj ? $date_obj->format( 'l, F j, Y' ) : $date;
        $time_obj         = DateTime::createFromFormat( 'H:i', $time );
        $time_display     = $time_obj ? $time_obj->format( 'g:i A' ) : $time;
        $customer_email   = $order->get_billing_email();

        global $wpdb;
        $table = WCLPM_Database::table();

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE order_id = %d", $order_id
        ) );

        if ( ! $existing ) {
            $wpdb->insert( $table, [
                'order_id'       => $order_id,
                'product_id'     => 0,
                'location_id'    => $location_id,
                'pickup_date'    => $date_obj ? $date_obj->format( 'Y-m-d' ) : $date,
                'pickup_time'    => $time,
                'customer_email' => $customer_email,
            ] );
        }

        $order->update_meta_data( '_pickup_selections', [
            'location_id'      => $location_id,
            'location_name'    => $location_name,
            'location_address' => $location_address,
            'date'             => $date,
            'date_display'     => $date_display,
            'time'             => $time,
            'time_display'     => $time_display,
            'products'         => array_column( $items, 'name' ),
        ] );
        $order->save();
    }

    public static function normalize( $pickup ) {
        return wp_parse_args( $pickup, [
            'products'         => [],
            'location_name'    => '',
            'location_address' => '',
            'date_display'     => $pickup['date'] ?? '',
            'time_display'     => $pickup['time'] ?? '',
        ] );
    }

    public function display_in_admin( $order ) {
        $pickup = $order->get_meta( '_pickup_selections' );
        if ( empty( $pickup ) ) {
            return;
        }
        $pickup = self::normalize( $pickup );

        echo '<div style="margin-top:20px;padding:15px;background:#f8f8f8;border:1px solid #e5e5e5;">';
        echo '<h4 style="margin-bottom:10px;">Pickup Details</h4>';
        echo '<strong>Location:</strong> ' . esc_html( $pickup['location_name'] )    . '<br>';
        echo '<strong>Address:</strong> '  . esc_html( $pickup['location_address'] ) . '<br>';
        echo '<strong>Date:</strong> '     . esc_html( $pickup['date_display'] )     . '<br>';
        echo '<strong>Time:</strong> '     . esc_html( $pickup['time_display'] )     . '<br>';
        echo '</div>';
    }

    public function display_in_email( $order, $sent_to_admin, $plain_text, $email ) {
        $pickup = $order->get_meta( '_pickup_selections' );
        if ( empty( $pickup ) ) {
            return;
        }
        $pickup = self::normalize( $pickup );

        if ( $plain_text ) {
            echo "\n\nPICKUP DETAILS\n==============\n";
            if ( ! empty( $pickup['products'] ) ) {
                echo 'Products: ' . implode( ', ', $pickup['products'] ) . "\n";
            }
            echo 'Location: ' . $pickup['location_name']    . "\n";
            echo 'Address: '  . $pickup['location_address'] . "\n";
            echo 'Date: '     . $pickup['date_display']     . "\n";
            echo 'Time: '     . $pickup['time_display']     . "\n";
        } else {
            echo '<div style="margin-bottom:40px;">';
            echo '<h2 style="color:#1a1a2e;">Pickup Details</h2>';
            echo '<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;">';
            if ( ! empty( $pickup['products'] ) ) {
                echo '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">Products</th><td style="border:1px solid #e5e5e5;padding:8px;">' . esc_html( implode( ', ', $pickup['products'] ) ) . '</td></tr>';
            }
            echo '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">Location</th><td style="border:1px solid #e5e5e5;padding:8px;">' . esc_html( $pickup['location_name'] ) . '<br><small>' . esc_html( $pickup['location_address'] ) . '</small></td></tr>';
            echo '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">Date</th><td style="border:1px solid #e5e5e5;padding:8px;">' . esc_html( $pickup['date_display'] ) . '</td></tr>';
            echo '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">Time</th><td style="border:1px solid #e5e5e5;padding:8px;">' . esc_html( $pickup['time_display'] ) . '</td></tr>';
            echo '</table></div>';
        }
    }

    public function display_on_order_page( $order ) {
        $pickup = $order->get_meta( '_pickup_selections' );
        if ( empty( $pickup ) ) {
            return;
        }
        $pickup = self::normalize( $pickup );

        echo '<section class="woocommerce-pickup-details">';
        echo '<h2 style="margin-top:30px;">Pickup Details</h2>';
        echo '<table class="woocommerce-table" cellspacing="0">';
        if ( ! empty( $pickup['products'] ) ) {
            echo '<tr><th>Products</th><td>' . esc_html( implode( ', ', $pickup['products'] ) ) . '</td></tr>';
        }
        echo '<tr><th>Location</th><td>' . esc_html( $pickup['location_name'] ) . '<br><small>' . esc_html( $pickup['location_address'] ) . '</small></td></tr>';
        echo '<tr><th>Date</th><td>'     . esc_html( $pickup['date_display'] ) . '</td></tr>';
        echo '<tr><th>Time</th><td>'     . esc_html( $pickup['time_display'] ) . '</td></tr>';
        echo '</table></section>';
    }

    public function set_from_email( $email ) {
        return WCLPM_Settings::get( 'from_email', $email );
    }

    public function set_from_name( $name ) {
        return WCLPM_Settings::get( 'from_name', $name );
    }

    public function set_wp_mail_from( $email ) {
        return WCLPM_Settings::get( 'from_email', $email );
    }

    public function set_wp_mail_from_name( $name ) {
        return WCLPM_Settings::get( 'from_name', $name );
    }

    public function add_reply_to_header( $args ) {
        $from_name  = WCLPM_Settings::get( 'from_name', get_bloginfo( 'name' ) );
        $from_email = WCLPM_Settings::get( 'from_email', '' );
        $reply_to   = $from_name . ' <' . $from_email . '>';

        if ( is_array( $args['headers'] ) ) {
            $args['headers'][] = 'Reply-To: ' . $reply_to;
        } else {
            $args['headers'] .= "\r\nReply-To: " . $reply_to;
        }

        return $args;
    }
}
