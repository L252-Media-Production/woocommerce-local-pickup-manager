<?php
defined( 'ABSPATH' ) || exit;

class GNYC_Pickup_Order_Status {

    public function __construct() {
        add_action( 'init',                                         [ $this, 'register_status' ] );
        add_filter( 'wc_order_statuses',                            [ $this, 'add_to_order_statuses' ] );
        add_filter( 'woocommerce_register_shop_order_post_statuses',[ $this, 'add_to_hpos_statuses' ] );
        add_action( 'admin_head',                                   [ $this, 'status_style' ] );
        add_filter( 'woocommerce_order_is_paid_statuses',           [ $this, 'mark_as_paid' ] );
        add_action( 'woocommerce_order_status_ready-pickup',        [ $this, 'send_ready_email' ], 10, 2 );
    }

    public function register_status() {
        register_post_status( 'wc-ready-pickup', [
            'label'                     => 'Ready for Pickup',
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop(
                'Ready for Pickup <span class="count">(%s)</span>',
                'Ready for Pickup <span class="count">(%s)</span>'
            ),
        ] );
    }

    public function add_to_order_statuses( $statuses ) {
        $new = [];
        foreach ( $statuses as $key => $status ) {
            $new[ $key ] = $status;
            if ( 'wc-processing' === $key ) {
                $new['wc-ready-pickup'] = 'Ready for Pickup';
            }
        }
        return $new;
    }

    public function add_to_hpos_statuses( $statuses ) {
        $statuses['wc-ready-pickup'] = [
            'label'                     => 'Ready for Pickup',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Ready for Pickup <span class="count">(%s)</span>',
                'Ready for Pickup <span class="count">(%s)</span>'
            ),
        ];
        return $statuses;
    }

    public function status_style() {
        echo '<style>
            .order-status.status-ready-pickup,
            mark.order-status.status-ready-pickup {
                background: #c8d7e1 !important;
                color: #2e4453 !important;
            }
        </style>';
    }

    public function mark_as_paid( $statuses ) {
        $statuses[] = 'ready-pickup';
        return $statuses;
    }

    public function send_ready_email( $order_id, $order ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }

        $pickup = $order->get_meta( '_pickup_selections' );
        if ( empty( $pickup ) ) {
            return;
        }

        $settings         = GNYC_Pickup_Settings::get_all();
        $customer_email   = $order->get_billing_email();
        $customer_name    = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $location_name    = $pickup['location_name'] ?? '';
        $location_address = $pickup['location_address'] ?? '';
        $date_display     = $pickup['date_display'] ?? '';
        $time_display     = $pickup['time_display'] ?? '';
        $order_number     = $order->get_order_number();

        $subject = strtr( $settings['ready_for_pickup_subject'], [
            '{order_number}'  => $order_number,
            '{date}'          => $date_display,
            '{time}'          => $time_display,
            '{customer_name}' => $customer_name,
        ] );

        $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f7f7f7;font-family:Arial,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7;padding:40px 0;">
            <tr><td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #e5e5e5;">
                    <tr><td style="background:#1a1a2e;padding:30px;text-align:center;">
                        <h1 style="color:#fff;margin:0;font-size:24px;">Your Order is Ready!</h1>
                    </td></tr>
                    <tr><td style="padding:30px;">
                        <p>Hi <strong>' . esc_html( $customer_name ) . '</strong>,</p>
                        <p>Great news! Your order <strong>#' . esc_html( $order_number ) . '</strong> is ready for pickup.</p>
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f8f8;border:1px solid #e5e5e5;margin:20px 0;">
                            <tr><td style="padding:15px;">
                                <h3 style="margin:0 0 15px;color:#1a1a2e;">Pickup Details</h3>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr><td style="padding:5px 0;width:120px;"><strong>Location:</strong></td><td>' . esc_html( $location_name ) . '</td></tr>
                                    <tr><td style="padding:5px 0;"><strong>Address:</strong></td><td>' . esc_html( $location_address ) . '</td></tr>
                                    <tr><td style="padding:5px 0;"><strong>Date:</strong></td><td>' . esc_html( $date_display ) . '</td></tr>
                                    <tr><td style="padding:5px 0;"><strong>Time:</strong></td><td>' . esc_html( $time_display ) . '</td></tr>
                                </table>
                            </td></tr>
                        </table>
                        <p>Please bring your order confirmation when picking up your items.</p>
                    </td></tr>
                    <tr><td style="background:#f8f8f8;padding:20px;text-align:center;border-top:1px solid #e5e5e5;color:#666;font-size:12px;">
                        <p style="margin:0;">' . get_bloginfo( 'name' ) . '</p>
                        <p style="margin:5px 0 0;">' . get_bloginfo( 'url' ) . '</p>
                    </td></tr>
                </table>
            </td></tr>
        </table></body></html>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'Reply-To: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        ];

        wp_mail( $customer_email, $subject, $html, $headers );
    }
}
