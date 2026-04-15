<?php
defined( 'ABSPATH' ) || exit;

// ─── Shipping ─────────────────────────────────────────────────────────────────

class WCLPM_Shipping {
    public function __construct() {
        add_filter( 'woocommerce_shipping_methods', [ $this, 'register_method' ] );
        add_filter( 'woocommerce_package_rates',    [ $this, 'suppress_blocks_pickup' ], 20, 2 );
    }

    public function register_method( $methods ) {
        $methods['wclpm_local_pickup'] = 'WCLPM_Local_Pickup_Method';
        return $methods;
    }

    /**
     * Remove WooCommerce's blocks-based local pickup rates (pickup_location:*)
     * whenever our zone-based method (wclpm_local_pickup:*) is also in the package.
     */
    public function suppress_blocks_pickup( $rates, $package ) {
        $has_wclpm = false;
        foreach ( $rates as $rate_id => $rate ) {
            if ( strpos( $rate_id, 'wclpm_local_pickup' ) !== false ) {
                $has_wclpm = true;
                break;
            }
        }

        if ( ! $has_wclpm ) {
            return $rates;
        }

        foreach ( $rates as $rate_id => $rate ) {
            if ( strpos( $rate_id, 'pickup_location' ) !== false ) {
                unset( $rates[ $rate_id ] );
            }
        }

        return $rates;
    }
}

class WCLPM_Local_Pickup_Method extends WC_Shipping_Method {

    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'wclpm_local_pickup';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = 'Local Pickup';
        $this->method_description = 'Allow customers to pick up orders at your location. Managed by Local Pickup Manager.';
        $this->supports           = [ 'shipping-zones', 'instance-settings' ];
        $this->init();
    }

    private function init() {
        $this->instance_form_fields = [
            'title' => [
                'title'       => 'Method title',
                'type'        => 'text',
                'default'     => 'Local pickup',
                'desc_tip'    => true,
                'description' => 'Label shown to the customer at checkout.',
            ],
        ];
        $this->title = $this->get_option( 'title', 'Local pickup' );
        add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function calculate_shipping( $package = [] ) {
        $this->add_rate( [
            'id'    => $this->get_rate_id(),
            'label' => $this->title,
            'cost'  => 0,
        ] );
    }
}

// ─── Cart ─────────────────────────────────────────────────────────────────────

class WCLPM_Cart {
    public function __construct() {
        add_filter( 'woocommerce_package_rates',        [ $this, 'enforce_pickup_only_shipping' ], 10, 2 );
        add_action( 'woocommerce_before_cart',          [ $this, 'pickup_only_cart_notice' ] );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_mixed_cart' ], 10, 2 );
    }

    public function enforce_pickup_only_shipping( $rates, $package ) {
        $has_pickup_only = false;
        foreach ( $package['contents'] as $cart_item ) {
            if ( get_field( 'pickup_only', $cart_item['product_id'] ) ) {
                $has_pickup_only = true;
                break;
            }
        }

        $pickup_rates  = [];
        $regular_rates = [];
        foreach ( $rates as $rate_id => $rate ) {
            if ( strpos( $rate_id, 'local_pickup' ) !== false ) {
                $pickup_rates[ $rate_id ] = $rate;
            } else {
                $regular_rates[ $rate_id ] = $rate;
            }
        }

        if ( $has_pickup_only ) {
            return ! empty( $pickup_rates ) ? $pickup_rates : $rates;
        }
        return ! empty( $regular_rates ) ? $regular_rates : $rates;
    }

    public function pickup_only_cart_notice() {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( get_field( 'pickup_only', $cart_item['product_id'] ) ) {
                wc_add_notice(
                    'Your cart contains a pickup-only item. Only local pickup is available for this order.',
                    'notice'
                );
                break;
            }
        }
    }

    public function prevent_mixed_cart( $passed, $product_id ) {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return $passed;
        }

        $adding_pickup_only   = get_field( 'pickup_only', $product_id );
        $cart_has_pickup_only = false;
        $cart_has_regular     = false;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( get_field( 'pickup_only', $cart_item['product_id'] ) ) {
                $cart_has_pickup_only = true;
            } else {
                $cart_has_regular = true;
            }
        }

        if ( $adding_pickup_only && $cart_has_regular ) {
            wc_add_notice(
                'This item is available for local pickup only and cannot be purchased together with regular shipping items. Please complete your current order first or <a href="' . wc_get_cart_url() . '">clear your cart</a>.',
                'error'
            );
            return false;
        }

        if ( ! $adding_pickup_only && $cart_has_pickup_only ) {
            wc_add_notice(
                'Your cart contains a pickup-only item. Regular shipping items cannot be added. Please complete your current order first or <a href="' . wc_get_cart_url() . '">clear your cart</a>.',
                'error'
            );
            return false;
        }

        return $passed;
    }
}

// ─── Checkout Fields ──────────────────────────────────────────────────────────

class WCLPM_Checkout_Fields {
    public function __construct() {
        add_action( 'woocommerce_before_checkout_billing_form', [ $this, 'render_fields' ] );
        add_action( 'woocommerce_checkout_order_processed',     [ $this, 'save_fields' ], 10, 3 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_in_admin' ], 10, 1 );
        add_action( 'woocommerce_email_order_details',          [ $this, 'display_in_email' ], 4, 4 );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_on_order_page' ], 5, 1 );
        add_action( 'wp_footer',                                [ $this, 'hide_shipping_for_local_pickup' ] );
        add_action( 'init', [ $this, 'clear_church_cache_on_request' ] );
    }

    public function get_crm_groups() {
        $api_url = WCLPM_Settings::get( 'crm_api_url', '' );
        if ( empty( $api_url ) ) {
            return [];
        }

        $cache_key = 'wclpm_crm_groups';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $headers = [ 'Accept' => 'application/json' ];
        $api_key = WCLPM_Settings::get( 'crm_api_key', '' );
        if ( ! empty( $api_key ) ) {
            $headers['X-Api-Key'] = $api_key;
        }

        $response = wp_remote_get( $api_url, [
            'timeout' => 15,
            'headers' => $headers,
        ]);

        if ( is_wp_error( $response ) ) {
            return [];
        }
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['list'] ) || ! is_array( $body['list'] ) ) {
            return [];
        }

        $groups = [];
        foreach ( $body['list'] as $item ) {
            if ( isset( $item['id'], $item['name'] ) ) {
                $groups[] = [ 'id' => $item['id'], 'name' => $item['name'] ];
            }
        }

        set_transient( $cache_key, $groups, DAY_IN_SECONDS );
        return $groups;
    }

    private function is_local_pickup() {
        if ( ! WC()->session ) {
            return false;
        }
        $chosen = WC()->session->get( 'chosen_shipping_methods' );
        if ( empty( $chosen ) ) {
            return false;
        }
        foreach ( $chosen as $method ) {
            if ( strpos( $method, 'local_pickup' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    public function render_fields() {
        $groups      = $this->get_crm_groups();
        $group_label = WCLPM_Settings::get( 'crm_group_label', 'Organization Affiliation' );

        if ( empty( $groups ) ) {
            return;
        }
        ?>
        <div id="custom-checkout-fields" style="margin-bottom:30px;">
            <div class="woocommerce-billing-fields">
                <h3><?php echo esc_html( $group_label ); ?></h3>
                <p class="form-row form-row-wide" id="church_affiliation_field">
                    <label for="church_affiliation"><?php echo esc_html( $group_label ); ?> <span class="required">*</span></label>
                    <select name="church_affiliation_id" id="church_affiliation" class="select input-text" style="width:100%;padding:8px;">
                        <option value="">— Select an option —</option>
                        <?php foreach ( $groups as $group ) : ?>
                        <option value="<?php echo esc_attr( $group['id'] ); ?>"
                                data-name="<?php echo esc_attr( $group['name'] ); ?>">
                            <?php echo esc_html( $group['name'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_fields( $order_id, $posted_data, $order ) {
        if ( ! empty( $_POST['church_affiliation_id'] ) ) {
            $church_id   = sanitize_text_field( $_POST['church_affiliation_id'] );
            $church_name = '';
            foreach ( $this->get_crm_groups() as $group ) {
                if ( $group['id'] === $church_id ) {
                    $church_name = $group['name'];
                    break;
                }
            }
            $order->update_meta_data( '_church_affiliation_id',   $church_id );
            $order->update_meta_data( '_church_affiliation_name', $church_name );
        }

        $has_alternate = sanitize_text_field( $_POST['has_alternate_pickup'] ?? 'no' );
        $order->update_meta_data( '_has_alternate_pickup', $has_alternate );

        if ( $has_alternate === 'yes' ) {
            $order->update_meta_data( '_alternate_pickup_name',  sanitize_text_field( $_POST['alternate_pickup_name']  ?? '' ) );
            $order->update_meta_data( '_alternate_pickup_phone', sanitize_text_field( $_POST['alternate_pickup_phone'] ?? '' ) );
            $order->update_meta_data( '_alternate_pickup_email', sanitize_email(      $_POST['alternate_pickup_email'] ?? '' ) );
        }

        $order->save();
    }

    public function display_in_admin( $order ) {
        $church_name   = $order->get_meta( '_church_affiliation_name' );
        $church_id     = $order->get_meta( '_church_affiliation_id' );
        $has_alternate = $order->get_meta( '_has_alternate_pickup' );

        if ( empty( $church_name ) && empty( $has_alternate ) ) {
            return;
        }

        echo '<div style="margin-top:20px;padding:15px;background:#f8f8f8;border:1px solid #e5e5e5;">';
        echo '<h4 style="margin-bottom:10px;">Additional Order Information</h4>';
        if ( ! empty( $church_name ) ) {
            echo '<p><strong>Church:</strong> ' . esc_html( $church_name ) . '</p>';
            echo '<p style="color:#999;font-size:11px;">EspoCRM ID: ' . esc_html( $church_id ) . '</p>';
        }
        if ( $has_alternate === 'yes' ) {
            echo '<hr style="margin:10px 0;"><p><strong>Alternate Pickup Person</strong></p>';
            echo '<p><strong>Name:</strong> '  . esc_html( $order->get_meta( '_alternate_pickup_name' ) )  . '</p>';
            echo '<p><strong>Phone:</strong> ' . esc_html( $order->get_meta( '_alternate_pickup_phone' ) ) . '</p>';
            echo '<p><strong>Email:</strong> ' . esc_html( $order->get_meta( '_alternate_pickup_email' ) ) . '</p>';
        }
        echo '</div>';
    }

    public function display_in_email( $order, $sent_to_admin, $plain_text, $email ) {
        $church_name   = $order->get_meta( '_church_affiliation_name' );
        $has_alternate = $order->get_meta( '_has_alternate_pickup' );

        if ( empty( $church_name ) && $has_alternate !== 'yes' ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n\nADDITIONAL ORDER INFORMATION\n============================\n";
            if ( ! empty( $church_name ) ) {
                echo 'Church: ' . $church_name . "\n";
            }
            if ( $has_alternate === 'yes' ) {
                echo 'Alternate Pickup: ' . $order->get_meta( '_alternate_pickup_name' ) . "\n";
                echo 'Phone: '            . $order->get_meta( '_alternate_pickup_phone' ) . "\n";
                echo 'Email: '            . $order->get_meta( '_alternate_pickup_email' ) . "\n";
            }
        } else {
            echo '<div style="margin-bottom:40px;"><h2 style="color:#1a1a2e;">Additional Order Information</h2>';
            echo '<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;">';
            if ( ! empty( $church_name ) ) {
                echo '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">Church</th><td style="border:1px solid #e5e5e5;padding:8px;">' . esc_html( $church_name ) . '</td></tr>';
            }
            if ( $has_alternate === 'yes' ) {
                echo '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">Alternate Pickup</th><td style="border:1px solid #e5e5e5;padding:8px;">';
                echo esc_html( $order->get_meta( '_alternate_pickup_name' ) )  . '<br>';
                echo esc_html( $order->get_meta( '_alternate_pickup_phone' ) ) . '<br>';
                echo esc_html( $order->get_meta( '_alternate_pickup_email' ) );
                echo '</td></tr>';
            }
            echo '</table></div>';
        }
    }

    public function display_on_order_page( $order ) {
        $church_name   = $order->get_meta( '_church_affiliation_name' );
        $has_alternate = $order->get_meta( '_has_alternate_pickup' );

        if ( empty( $church_name ) && $has_alternate !== 'yes' ) {
            return;
        }

        echo '<section class="woocommerce-order-additional-info">';
        echo '<h2 style="margin-top:30px;">Additional Order Information</h2>';
        echo '<table class="woocommerce-table" cellspacing="0">';
        if ( ! empty( $church_name ) ) {
            echo '<tr><th>Church</th><td>' . esc_html( $church_name ) . '</td></tr>';
        }
        if ( $has_alternate === 'yes' ) {
            echo '<tr><th>Alternate Pickup Person</th><td>';
            echo esc_html( $order->get_meta( '_alternate_pickup_name' ) )  . '<br>';
            echo esc_html( $order->get_meta( '_alternate_pickup_phone' ) ) . '<br>';
            echo esc_html( $order->get_meta( '_alternate_pickup_email' ) );
            echo '</td></tr>';
        }
        echo '</table></section>';
    }

    public function hide_shipping_for_local_pickup() {
        if ( ! is_checkout() ) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            var lastMethod = null;
            function toggleShipping() {
                var $radio  = $('input[name="shipping_method[0]"]:checked');
                var method  = $radio.length ? $radio.val() : $('input[name="shipping_method[0]"]').val();
                if ( method === lastMethod ) return;
                lastMethod = method;

                if ( method && method.indexOf('local_pickup') !== -1 ) {
                    $('#ship-to-different-address').hide();
                    $('.woocommerce-shipping-fields').hide();
                    var $cb = $('#ship-to-different-address-checkbox');
                    if ( $cb.is(':checked') ) $cb.prop('checked', false);
                } else {
                    $('#ship-to-different-address').show();
                    $('.woocommerce-shipping-fields').show();
                }
            }
            setTimeout( toggleShipping, 600 );
            $(document.body).on('updated_checkout', function() { setTimeout( toggleShipping, 400 ); });
            $(document.body).on('change', 'input[name="shipping_method[0]"]', function() { lastMethod = null; toggleShipping(); });
        });
        </script>
        <?php
    }

    public function clear_church_cache_on_request() {
        if ( isset( $_GET['clear_crm_cache'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( 'wclpm_crm_groups' );
            wp_die( 'CRM group cache cleared. It will be refreshed on the next checkout visit.' );
        }
    }
}
