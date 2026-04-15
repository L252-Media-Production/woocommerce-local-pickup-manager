<?php
defined( 'ABSPATH' ) || exit;

// ─── Order Confirmation ───────────────────────────────────────────────────────

class GNYC_Pickup_Order_Confirmation {

    public function __construct() {
        add_action( 'init',       [ $this, 'remove_default_hooks' ] );
        add_action( 'wp_head',    [ $this, 'hide_default_elements' ] );
        add_filter( 'woocommerce_thankyou_order_received_text', '__return_empty_string', 20 );
        add_action( 'woocommerce_thankyou', [ $this, 'render' ], 10, 1 );
    }

    public function remove_default_hooks() {
        remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
    }

    public function hide_default_elements() {
        if ( ! is_wc_endpoint_url( 'order-received' ) ) {
            return;
        }
        echo '<style>
            .woocommerce-order-overview,
            .woocommerce-thankyou-order-received { display:none !important; }
        </style>';
    }

    public function render( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $pickup        = $order->get_meta( '_pickup_selections' );
        $church_name   = $order->get_meta( '_church_affiliation_name' );
        $has_alt       = $order->get_meta( '_has_alternate_pickup' );
        $alt_name      = $order->get_meta( '_alternate_pickup_name' );
        $alt_phone     = $order->get_meta( '_alternate_pickup_phone' );
        $alt_email     = $order->get_meta( '_alternate_pickup_email' );
        $is_pickup     = ! empty( $pickup ) && is_array( $pickup );
        $date_created  = $order->get_date_created();
        ?>
        <style>
        .thankyou-layout{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:30px;}
        @media(max-width:768px){.thankyou-layout{grid-template-columns:1fr;}}
        .thankyou-card{background:#fff;border:1px solid #e5e5e5;border-radius:8px;overflow:hidden;}
        .thankyou-card-header{background:#1a1a2e;color:#fff;padding:14px 20px;font-weight:bold;}
        .thankyou-card-body{padding:20px;}
        .thankyou-row{display:flex;gap:12px;border-bottom:1px solid #f0f0f0;padding:10px 0;}
        .thankyou-row:last-child{border:none;}
        .thankyou-label{font-weight:bold;min-width:120px;}
        .thankyou-value{flex:1;}
        .thankyou-order-table{width:100%;border-collapse:collapse;}
        .thankyou-order-table td,.thankyou-order-table th{padding:10px 8px;}
        .thankyou-order-table th{text-align:left;border-bottom:2px solid #f0f0f0;}
        .thankyou-order-table td:last-child,.thankyou-order-table th:last-child{text-align:right;}
        .thankyou-full-width{grid-column:1/-1;}
        .thankyou-badge{background:#e8f0fe;padding:3px 10px;border-radius:20px;font-size:12px;}
        </style>

        <div class="thankyou-layout">

            <!-- Header -->
            <div class="thankyou-card thankyou-full-width">
                <div class="thankyou-card-header" style="padding:20px;">
                    <strong style="font-size:18px;">🎉 Thank you, <?php echo esc_html( $order->get_billing_first_name() ); ?>!</strong>
                    <div style="margin-top:10px;display:flex;gap:30px;flex-wrap:wrap;font-size:13px;">
                        <div><div>Order #</div><strong><?php echo esc_html( $order->get_order_number() ); ?></strong></div>
                        <div><div>Date</div><strong><?php echo $date_created ? esc_html( $date_created->date_i18n( 'F j, Y' ) ) : ''; ?></strong></div>
                        <div><div>Total</div><strong><?php echo wc_price( $order->get_total() ); ?></strong></div>
                        <div><div>Status</div><strong><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></strong></div>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="thankyou-card">
                <div class="thankyou-card-header">🛍️ Order Details</div>
                <div class="thankyou-card-body">
                    <table class="thankyou-order-table">
                        <thead><tr><th>Product</th><th>Qty</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ( $order->get_items() as $item ) :
                            $variations = [];
                            foreach ( $item->get_meta_data() as $meta ) {
                                if ( strpos( $meta->key, 'attribute_' ) !== false ) {
                                    $k = ucwords( str_replace( [ 'attribute_pa_', 'attribute_', '-' ], [ '', '', ' ' ], $meta->key ) );
                                    $v = is_scalar( $meta->value ) ? $meta->value : '';
                                    $variations[] = $k . ': ' . ucfirst( (string) $v );
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                <?php if ( $variations ) : ?>
                                    <div style="font-size:12px;color:#888;"><?php echo esc_html( implode( ' · ', $variations ) ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>× <?php echo esc_html( $item->get_quantity() ); ?></td>
                            <td><?php echo wc_price( $item->get_total() ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php if ( $order->get_subtotal() !== $order->get_total() ) : ?>
                            <tr><td colspan="2">Subtotal</td><td><?php echo wc_price( $order->get_subtotal() ); ?></td></tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="2">Shipping</td>
                                <td><?php foreach ( $order->get_shipping_methods() as $m ) {
                                    echo '<span class="thankyou-badge">' . esc_html( $m->get_name() ) . '</span> ';
                                } ?></td>
                            </tr>
                            <tr><td colspan="2"><strong>Total</strong></td><td><strong><?php echo wc_price( $order->get_total() ); ?></strong></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Right column -->
            <div style="display:flex;flex-direction:column;gap:24px;">

                <?php if ( $is_pickup ) : ?>
                <div class="thankyou-card">
                    <div class="thankyou-card-header">📍 Pickup Details</div>
                    <div class="thankyou-card-body">
                        <div class="thankyou-row">
                            <span class="thankyou-label">Location</span>
                            <span class="thankyou-value">
                                <strong><?php echo esc_html( $pickup['location_name'] ?? '' ); ?></strong><br>
                                <small><?php echo esc_html( $pickup['location_address'] ?? '' ); ?></small>
                            </span>
                        </div>
                        <div class="thankyou-row">
                            <span class="thankyou-label">Date</span>
                            <span class="thankyou-value"><?php echo esc_html( $pickup['date_display'] ?? '' ); ?></span>
                        </div>
                        <div class="thankyou-row">
                            <span class="thankyou-label">Time</span>
                            <span class="thankyou-value"><?php echo esc_html( $pickup['time_display'] ?? '' ); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $church_name || $has_alt === 'yes' ) : ?>
                <div class="thankyou-card">
                    <div class="thankyou-card-header">ℹ️ Additional Info</div>
                    <div class="thankyou-card-body">
                        <?php if ( $church_name ) : ?>
                        <div class="thankyou-row"><span class="thankyou-label">Church</span><span class="thankyou-value"><?php echo esc_html( $church_name ); ?></span></div>
                        <?php endif; ?>
                        <?php if ( $has_alt === 'yes' ) : ?>
                        <div class="thankyou-row">
                            <span class="thankyou-label">Alternate Pickup</span>
                            <span class="thankyou-value">
                                <?php echo esc_html( $alt_name ); ?><br>
                                <?php echo esc_html( $alt_phone ); ?><br>
                                <?php echo esc_html( $alt_email ); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="thankyou-card">
                    <div class="thankyou-card-header">🏠 Billing</div>
                    <div class="thankyou-card-body"><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></div>
                </div>

            </div>
        </div>
        <?php
    }
}

// ─── Reminders ────────────────────────────────────────────────────────────────

class GNYC_Pickup_Reminders {

    public function __construct() {
        add_action( 'send_pickup_day_before_reminders', [ $this, 'send_day_before' ] );
        add_action( 'send_pickup_morning_reminders',    [ $this, 'send_morning' ] );
    }

    public static function schedule_crons() {
        $send_time = GNYC_Pickup_Settings::get( 'reminder_send_time', '08:00' );
        $timezone  = new DateTimeZone( wp_timezone_string() );
        $now       = new DateTime( 'now', $timezone );
        $next_run  = new DateTime( 'today ' . $send_time . ':00', $timezone );

        if ( $now >= $next_run ) {
            $next_run->modify( '+1 day' );
        }

        $timestamp = $next_run->getTimestamp();

        if ( ! wp_next_scheduled( 'send_pickup_day_before_reminders' ) ) {
            wp_schedule_event( $timestamp, 'daily', 'send_pickup_day_before_reminders' );
        }
        if ( ! wp_next_scheduled( 'send_pickup_morning_reminders' ) ) {
            wp_schedule_event( $timestamp, 'daily', 'send_pickup_morning_reminders' );
        }
    }

    public static function unschedule_crons() {
        wp_clear_scheduled_hook( 'send_pickup_day_before_reminders' );
        wp_clear_scheduled_hook( 'send_pickup_morning_reminders' );
    }

    public function send_day_before() {
        $this->send_reminders( false );
    }

    public function send_morning() {
        $this->send_reminders( true );
    }

    private function send_reminders( $is_morning ) {
        global $wpdb;

        $lock_key = 'pickup_reminder_lock_' . ( $is_morning ? 'morning' : 'day_before' );
        if ( get_transient( $lock_key ) ) {
            return;
        }
        set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

        $table    = GNYC_Pickup_Database::table();
        $timezone = new DateTimeZone( wp_timezone_string() );

        if ( $is_morning ) {
            $target_date = ( new DateTime( 'now', $timezone ) )->format( 'Y-m-d' );
            $column      = 'reminder_morning';
        } else {
            $target_date = ( new DateTime( 'tomorrow', $timezone ) )->format( 'Y-m-d' );
            $column      = 'reminder_day_before';
        }

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE pickup_date = %s AND {$column} = 0",
            $target_date
        ) );

        foreach ( (array) $bookings as $booking ) {
            $rows = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET {$column} = 1 WHERE id = %d AND {$column} = 0",
                $booking->id
            ) );
            if ( ! $rows ) {
                continue;
            }

            $order_details = $this->get_order_details( $booking->order_id );
            if ( empty( $order_details ) ) {
                continue;
            }

            $email = $this->build_email( $booking, $order_details, $is_morning );

            $settings = GNYC_Pickup_Settings::get_all();
            $headers  = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
                'Reply-To: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            ];

            wp_mail( $booking->customer_email, $email['subject'], $email['html'], $headers );
        }

        delete_transient( $lock_key );
    }

    private function get_order_details( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return [];
        }

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product   = $item->get_product();
            $variation = [];
            if ( $product && $product->is_type( 'variation' ) ) {
                foreach ( $item->get_meta_data() as $meta ) {
                    if ( strpos( $meta->key, 'attribute_' ) !== false ) {
                        $variation[] = ucfirst( str_replace( 'attribute_pa_', '', $meta->key ) ) . ': ' . ucfirst( $meta->value );
                    }
                }
            }
            $items[] = [
                'name'      => $item->get_name(),
                'qty'       => $item->get_quantity(),
                'subtotal'  => wc_price( $item->get_subtotal() ),
                'variation' => implode( ', ', $variation ),
            ];
        }

        return [
            'order_number'  => $order->get_order_number(),
            'order_date'    => $order->get_date_created()->date_i18n( 'F j, Y' ),
            'order_total'   => wc_price( $order->get_total() ),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'first_name'    => $order->get_billing_first_name(),
            'items'         => $items,
        ];
    }

    private function build_email( $booking, $order_details, $is_morning ) {
        $settings         = GNYC_Pickup_Settings::get_all();
        $location_name    = get_the_title( $booking->location_id );
        $location_address = get_field( 'location_address', $booking->location_id );
        $date_obj         = DateTime::createFromFormat( 'Y-m-d', $booking->pickup_date );
        $date_display     = $date_obj ? $date_obj->format( 'l, F j, Y' ) : $booking->pickup_date;
        $time_obj         = DateTime::createFromFormat( 'H:i', $booking->pickup_time );
        $time_display     = $time_obj ? $time_obj->format( 'g:i A' ) : $booking->pickup_time;

        $subject_template = $is_morning
            ? $settings['reminder_morning_subject']
            : $settings['reminder_day_before_subject'];

        $subject = strtr( $subject_template, [
            '{date}'          => $date_display,
            '{time}'          => $time_display,
            '{order_number}'  => $order_details['order_number'],
            '{customer_name}' => $order_details['customer_name'],
        ] );

        $heading_text  = $is_morning ? 'Your Pickup is Today!' : 'Your Pickup is Tomorrow!';
        $greeting_line = $is_morning
            ? 'This is a friendly reminder that your order is ready for pickup <strong>today</strong>.'
            : 'This is a friendly reminder that your order is scheduled for pickup <strong>tomorrow</strong>.';

        $items_html = '';
        foreach ( $order_details['items'] as $item ) {
            $variation_html = ! empty( $item['variation'] )
                ? '<br><span style="font-size:12px;color:#888;">' . esc_html( $item['variation'] ) . '</span>'
                : '';
            $items_html .= '<tr>
                <td style="padding:10px 8px;border-bottom:1px solid #f0f0f0;vertical-align:top;">
                    <span style="font-weight:bold;color:#1a1a2e;">' . esc_html( $item['name'] ) . '</span>' . $variation_html . '
                </td>
                <td style="padding:10px 8px;border-bottom:1px solid #f0f0f0;text-align:center;vertical-align:top;">&times; ' . esc_html( $item['qty'] ) . '</td>
                <td style="padding:10px 8px;border-bottom:1px solid #f0f0f0;text-align:right;vertical-align:top;">' . $item['subtotal'] . '</td>
            </tr>';
        }

        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>' . esc_html( $heading_text ) . '</title>
    <style type="text/css">
        @media screen and (max-width:620px) {
            .email-outer{padding:0 !important;}
            .email-inner{width:100% !important;max-width:100% !important;}
            .email-header{padding:20px 16px !important;border-radius:0 !important;}
            .email-body{padding:20px 16px !important;}
            .email-footer{padding:16px !important;border-radius:0 !important;}
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-outer" style="background-color:#f0f0f0;padding:32px 0;">
    <tr><td align="center" style="padding:0 12px;">
        <table width="640" cellpadding="0" cellspacing="0" border="0" class="email-inner" style="max-width:640px;width:100%;">
            <tr><td class="email-header" style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:28px 36px;text-align:center;">
                <img src="https://cdn.gnycyouth.org/wp-content/uploads/2026/03/11033656/logo.png" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="max-height:50px;margin-bottom:16px;">
                <h1 style="color:#fff;margin:0;font-size:22px;font-weight:700;">' . esc_html( $heading_text ) . '</h1>
            </td></tr>
            <tr><td class="email-body" style="background:#fff;padding:28px 36px;">
                <p style="font-size:15px;color:#333;">Hi <strong>' . esc_html( $order_details['first_name'] ) . '</strong>,</p>
                <p style="font-size:14px;color:#555;">' . $greeting_line . '</p>

                <!-- Pickup Details -->
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;">
                    <tr><td style="background:#1a1a2e;color:#fff;padding:14px 20px;border-radius:6px 6px 0 0;font-weight:bold;font-size:15px;">📍 Pickup Details</td></tr>
                    <tr><td style="border:1px solid #e5e5e5;border-top:none;border-radius:0 0 6px 6px;padding:0;">
                        <table cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr><td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;font-weight:bold;color:#555;width:130px;font-size:14px;">Location</td><td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;color:#333;font-size:14px;"><strong>' . esc_html( $location_name ) . '</strong><br><span style="color:#888;font-size:12px;">' . esc_html( $location_address ) . '</span></td></tr>
                            <tr><td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;font-weight:bold;color:#555;font-size:14px;">Date</td><td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;color:#333;font-size:14px;">' . esc_html( $date_display ) . '</td></tr>
                            <tr><td style="padding:12px 16px;font-weight:bold;color:#555;font-size:14px;">Time</td><td style="padding:12px 16px;color:#333;font-size:14px;">' . esc_html( $time_display ) . '</td></tr>
                        </table>
                    </td></tr>
                </table>

                <!-- Order Summary -->
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;">
                    <tr><td style="background:#1a1a2e;color:#fff;padding:14px 20px;border-radius:6px 6px 0 0;font-weight:bold;font-size:15px;">🧾 Order Summary</td></tr>
                    <tr><td style="border:1px solid #e5e5e5;border-top:none;border-radius:0 0 6px 6px;padding:0;">
                        <table cellspacing="0" cellpadding="0" border="0" width="100%">
                            <thead><tr style="background:#f8f8f8;">
                                <th style="padding:10px 8px;text-align:left;font-size:12px;color:#555;font-weight:bold;border-bottom:2px solid #f0f0f0;">Product</th>
                                <th style="padding:10px 8px;text-align:center;font-size:12px;color:#555;font-weight:bold;border-bottom:2px solid #f0f0f0;">Qty</th>
                                <th style="padding:10px 8px;text-align:right;font-size:12px;color:#555;font-weight:bold;border-bottom:2px solid #f0f0f0;">Total</th>
                            </tr></thead>
                            <tbody>' . $items_html . '</tbody>
                            <tfoot><tr>
                                <td colspan="2" style="padding:10px 8px;font-weight:bold;font-size:15px;color:#1a1a2e;border-top:2px solid #1a1a2e;">Order Total</td>
                                <td style="padding:10px 8px;font-weight:bold;font-size:15px;color:#1a1a2e;border-top:2px solid #1a1a2e;text-align:right;">' . $order_details['order_total'] . '</td>
                            </tr></tfoot>
                        </table>
                    </td></tr>
                </table>

                <p style="font-size:14px;color:#555;">Please bring your order confirmation when picking up your items.</p>
            </td></tr>
            <tr><td class="email-footer" style="background:#f8f8f8;border:1px solid #e5e5e5;border-top:none;border-radius:0 0 8px 8px;padding:24px 36px;text-align:center;">
                <p style="margin:0 0 8px;font-size:13px;color:#555;">Questions? Contact us at <a href="mailto:' . esc_attr( $settings['from_email'] ) . '" style="color:#1a1a2e;font-weight:bold;">' . esc_html( $settings['from_email'] ) . '</a></p>
                <p style="margin:0;font-size:12px;color:#888;">' . esc_html( get_bloginfo( 'name' ) ) . '<br>7 Shelter Rock Rd, Manhasset, NY 11030</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body></html>';

        return [ 'subject' => $subject, 'html' => $html ];
    }
}

// ─── Product Availability ─────────────────────────────────────────────────────

class GNYC_Pickup_Availability {

    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ $this, 'block_add_to_cart_standard' ], 25 );
        add_action( 'wp_footer',                          [ $this, 'block_elementor_add_to_cart' ] );
        add_filter( 'woocommerce_is_purchasable',         [ $this, 'is_purchasable' ], 10, 2 );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 2 );
    }

    public static function in_season( $product_id ) {
        $start   = get_field( 'availability_start_date', $product_id );
        $end     = get_field( 'availability_end_date', $product_id );
        $expires = get_field( 'expires_after_end_date', $product_id );

        if ( ! $start ) {
            return true;
        }

        $tz       = new DateTimeZone( wp_timezone_string() );
        $now      = new DateTime( 'now', $tz );
        $start_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $start, $tz );

        if ( $expires ) {
            if ( ! $end ) {
                return $now >= $start_dt;
            }
            $end_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $end, $tz );
            return $now >= $start_dt && $now <= $end_dt;
        }

        $now_md   = intval( $now->format( 'md' ) );
        $start_md = intval( $start_dt->format( 'md' ) );

        if ( ! $end ) {
            if ( $now_md === $start_md ) {
                return $now >= $start_dt;
            }
            return $now_md >= $start_md;
        }

        $end_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $end, $tz );
        $end_md = intval( $end_dt->format( 'md' ) );

        if ( $start_md <= $end_md ) {
            if ( $now_md === $start_md ) {
                return $now >= $start_dt && $now_md <= $end_md;
            }
            if ( $now_md === $end_md ) {
                return $now_md >= $start_md && $now <= $end_dt;
            }
            return $now_md >= $start_md && $now_md <= $end_md;
        }

        return $now_md >= $start_md || $now_md <= $end_md;
    }

    private function get_seasonal_message( $product_id ) {
        $expires = get_field( 'expires_after_end_date', $product_id );
        $end     = get_field( 'availability_end_date', $product_id );
        $start   = get_field( 'availability_start_date', $product_id );

        if ( $expires ) {
            $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $end, new DateTimeZone( wp_timezone_string() ) );
            return 'This product is no longer available. Sales ended on <strong>' . esc_html( $d->format( 'F j, Y \a\t g:i A' ) ) . '</strong>.';
        }
        $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $start, new DateTimeZone( wp_timezone_string() ) );
        return 'This product is not currently available. It returns on <strong>' . esc_html( $d->format( 'F j \a\t g:i A' ) ) . '</strong>.';
    }

    public function block_add_to_cart_standard() {
        global $product;
        if ( ! self::in_season( $product->get_id() ) ) {
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            echo '<p class="seasonal-unavailable">' . $this->get_seasonal_message( $product->get_id() ) . '</p>';
        }
    }

    public function block_elementor_add_to_cart() {
        if ( ! is_product() ) {
            return;
        }
        global $product;
        if ( self::in_season( $product->get_id() ) ) {
            return;
        }
        $message = $this->get_seasonal_message( $product->get_id() );
        ?>
        <style>.elementor-widget-woocommerce-product-add-to-cart{display:none !important;}</style>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var atc = document.querySelector('.elementor-widget-woocommerce-product-add-to-cart');
            if ( atc ) {
                var p = document.createElement('p');
                p.className = 'seasonal-unavailable-elementor';
                p.innerHTML = <?php echo json_encode( $message ); ?>;
                atc.parentNode.insertBefore(p, atc);
                atc.style.display = 'none';
            }
        });
        </script>
        <?php
    }

    public function is_purchasable( $purchasable, $product ) {
        if ( $purchasable && ! self::in_season( $product->get_id() ) ) {
            return false;
        }
        return $purchasable;
    }

    public function validate_add_to_cart( $passed, $product_id ) {
        if ( ! self::in_season( $product_id ) ) {
            wc_add_notice( 'This product is not currently available.', 'error' );
            return false;
        }
        return $passed;
    }
}

// ─── Change Requests ─────────────────────────────────────────────────────────

class GNYC_Pickup_Change_Requests {

    public function __construct() {
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'render_form' ], 20, 1 );
        add_action( 'init', [ $this, 'handle_submission' ] );
    }

    public function render_form( $order ) {
        if ( ! GNYC_Pickup_Settings::get( 'allow_change_requests', true ) ) {
            return;
        }

        $pickup = $order->get_meta( '_pickup_selections' );
        if ( empty( $pickup ) ) {
            return;
        }

        // Check cutoff
        $cutoff_hours = intval( GNYC_Pickup_Settings::get( 'change_cutoff_hours', 24 ) );
        if ( $cutoff_hours > 0 ) {
            $date     = $pickup['date'] ?? '';
            $time     = $pickup['time'] ?? '';
            $timezone = new DateTimeZone( wp_timezone_string() );

            if ( $date && $time ) {
                $pickup_dt = DateTime::createFromFormat( 'Ymd H:i', $date . ' ' . $time, $timezone );
                if ( $pickup_dt ) {
                    $now        = new DateTime( 'now', $timezone );
                    $diff_hours = ( $pickup_dt->getTimestamp() - $now->getTimestamp() ) / 3600;
                    if ( $diff_hours <= $cutoff_hours ) {
                        echo '<p style="margin-top:30px;color:#888;font-size:13px;">Change requests are no longer accepted within ' . esc_html( $cutoff_hours ) . ' hours of your scheduled pickup.</p>';
                        return;
                    }
                }
            }
        }

        global $wpdb;
        $table    = GNYC_Pickup_Database::table();
        $booking  = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d LIMIT 1",
            $order->get_id()
        ) );

        if ( ! $booking || intval( $booking->change_requested ) === 1 ) {
            if ( $booking && intval( $booking->change_requested ) === 1 ) {
                echo '<p style="margin-top:30px;color:#888;font-size:13px;">✅ Your change request has been received. We\'ll be in touch to confirm.</p>';
            }
            return;
        }

        $nonce = wp_create_nonce( 'gnyc_change_request_' . $order->get_id() );
        ?>
        <div style="margin-top:30px;padding:20px;border:1px solid #e5e5e5;border-radius:8px;background:#f9f9f9;">
            <h3 style="margin-top:0;">Request a Pickup Change</h3>
            <p style="font-size:13px;color:#666;">Need a different date, time, or location? Submit a request and we'll reach out to assist you.</p>
            <form method="post">
                <?php wp_nonce_field( 'gnyc_change_request_' . $order->get_id(), 'gnyc_cr_nonce' ); ?>
                <input type="hidden" name="gnyc_change_request_order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
                <p>
                    <label for="gnyc_change_note" style="display:block;font-weight:bold;margin-bottom:6px;">Details of your request</label>
                    <textarea id="gnyc_change_note" name="gnyc_change_note" rows="4"
                              style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"
                              placeholder="e.g. Can I reschedule to Saturday May 10 between 2–4pm?"></textarea>
                </p>
                <button type="submit" name="gnyc_submit_change_request"
                        style="background:#1a1a2e;color:#fff;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;font-size:14px;">
                    Submit Request
                </button>
            </form>
        </div>
        <?php
    }

    public function handle_submission() {
        if ( ! isset( $_POST['gnyc_submit_change_request'] ) ) {
            return;
        }

        $order_id = intval( $_POST['gnyc_change_request_order_id'] ?? 0 );
        if ( ! $order_id || ! check_admin_referer( 'gnyc_change_request_' . $order_id, 'gnyc_cr_nonce' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Verify current user owns the order
        if ( $order->get_customer_id() !== get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $note = sanitize_textarea_field( $_POST['gnyc_change_note'] ?? '' );

        global $wpdb;
        $table = GNYC_Pickup_Database::table();

        $wpdb->update(
            $table,
            [
                'change_requested'    => 1,
                'change_request_note' => $note,
            ],
            [ 'order_id' => $order_id ]
        );

        // Add internal order note
        $order->add_order_note(
            'Customer submitted a pickup change request: ' . ( $note ?: '(no note provided)' )
        );

        wc_add_notice( 'Your change request has been submitted. We\'ll contact you to confirm the new details.', 'success' );
    }
}
