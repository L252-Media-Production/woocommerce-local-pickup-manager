<?php
defined( 'ABSPATH' ) || exit;

class WCLPM_Fields {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_footer',          [ $this, 'render_in_footer' ] );
    }

    public function enqueue_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        // JS file lives in the plugin's assets folder
        wp_enqueue_script(
            'wclpm-pickup-checkout',
            WCLPM_URL . 'assets/js/pickup-checkout.js',
            [ 'jquery' ],
            WCLPM_VERSION,
            true
        );

        wp_localize_script( 'wclpm-pickup-checkout', 'pickupData', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'pickup_nonce' ),
            'cartItems' => $this->get_pickup_cart_items(),
        ] );
    }

    /**
     * Get cart items that have pickup locations configured.
     */
    public static function get_pickup_cart_items() {
        $items = [];

        if ( ! WC()->cart ) {
            return $items;
        }

        foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
            $product_id = $cart_item['product_id'];
            $locations  = get_field( 'available_pickup_locations', $product_id );

            if ( empty( $locations ) ) {
                continue;
            }

            $location_options = [];
            foreach ( $locations as $location ) {
                $location_options[] = [
                    'id'      => $location->ID,
                    'name'    => $location->post_title,
                    'address' => get_field( 'location_address', $location->ID ),
                ];
            }

            $variation_label = '';
            if ( ! empty( $cart_item['variation'] ) ) {
                $parts = [];
                foreach ( $cart_item['variation'] as $key => $value ) {
                    $parts[] = ucfirst( str_replace( 'attribute_pa_', '', $key ) ) . ': ' . ucfirst( $value );
                }
                $variation_label = ' (' . implode( ', ', $parts ) . ')';
            }

            $items[] = [
                'cart_key'     => $cart_key,
                'product_id'   => $product_id,
                'variation_id' => $cart_item['variation_id'] ?? 0,
                'name'         => get_the_title( $product_id ) . $variation_label,
                'locations'    => $location_options,
            ];
        }

        return $items;
    }

    /**
     * Intersection of locations available across ALL pickup cart items.
     */
    private function get_shared_pickup_locations() {
        $items = self::get_pickup_cart_items();

        if ( empty( $items ) ) {
            return [];
        }

        $shared_ids = array_column( $items[0]['locations'], 'id' );
        foreach ( $items as $item ) {
            $shared_ids = array_intersect( $shared_ids, array_column( $item['locations'], 'id' ) );
        }

        if ( empty( $shared_ids ) ) {
            return [];
        }

        $locations = [];
        foreach ( $items[0]['locations'] as $location ) {
            if ( in_array( $location['id'], $shared_ids ) ) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * Combined pickup date range across all cart items.
     */
    private function get_combined_pickup_date_range() {
        $items      = self::get_pickup_cart_items();
        $start_date = null;
        $end_date   = null;

        foreach ( $items as $item ) {
            $product_id    = $item['product_id'];
            $product_start = get_field( 'pickup_start_date', $product_id );
            $product_end   = get_field( 'pickup_end_date', $product_id );

            if ( $product_start ) {
                $start_dt = DateTime::createFromFormat( 'Ymd', $product_start );
                if ( ! $start_date || $start_dt > $start_date ) {
                    $start_date = $start_dt;
                }
            }

            if ( $product_end ) {
                $end_dt = DateTime::createFromFormat( 'Ymd', $product_end );
                if ( ! $end_date || $end_dt < $end_date ) {
                    $end_date = $end_dt;
                }
            }
        }

        return [
            'start' => $start_date ? $start_date->format( 'Ymd' ) : null,
            'end'   => $end_date   ? $end_date->format( 'Ymd' )   : null,
        ];
    }

    public function render_in_footer() {
        if ( ! is_checkout() ) {
            return;
        }

        $items     = self::get_pickup_cart_items();
        $locations = $this->get_shared_pickup_locations();

        if ( empty( $items ) || empty( $locations ) ) {
            return;
        }

        $date_range = $this->get_combined_pickup_date_range();
        ?>
        <div id="pickup-selection-wrapper" style="display:none;">
            <div id="pickup-selection-inner" style="margin:20px 0;padding:20px;border:1px solid #e5e5e5;border-radius:8px;box-sizing:border-box;width:100%;overflow:hidden;">
                <h3 style="margin-bottom:10px;">Pickup Details</h3>

                <div class="pickup-item"
                     data-date-range-start="<?php echo esc_attr( $date_range['start'] ?? '' ); ?>"
                     data-date-range-end="<?php echo esc_attr( $date_range['end'] ?? '' ); ?>">

                    <!-- Location -->
                    <p class="form-row form-row-wide" style="box-sizing:border-box;">
                        <label>Pickup Location <span class="required">*</span></label>
                        <select class="pickup-location-select input-text"
                                name="pickup_location_order"
                                style="width:100%;padding:8px;box-sizing:border-box;max-width:100%;">
                            <option value="">— Select a location —</option>
                            <?php foreach ( $locations as $location ) : ?>
                            <option value="<?php echo esc_attr( $location['id'] ); ?>"
                                    data-address="<?php echo esc_attr( $location['address'] ); ?>">
                                <?php echo esc_html( $location['name'] . ' — ' . $location['address'] ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <!-- Calendar -->
                    <div id="pickup-calendar-wrapper" style="display:none;margin-top:15px;box-sizing:border-box;width:100%;">
                        <label style="font-weight:bold;display:block;margin-bottom:10px;">
                            Pickup Date <span class="required">*</span>
                        </label>
                        <input type="hidden" name="pickup_date_order" id="pickup_date_order">
                        <div id="pickup-calendar" style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;overflow:hidden;width:100%;max-width:100%;box-sizing:border-box;user-select:none;">
                            <div id="pickup-cal-header" style="background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:12px 16px;">
                                <button type="button" id="pickup-cal-prev" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0 8px;">&#8249;</button>
                                <span id="pickup-cal-month-label" style="font-weight:bold;font-size:15px;"></span>
                                <button type="button" id="pickup-cal-next" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0 8px;">&#8250;</button>
                            </div>
                            <div id="pickup-cal-days-header" style="display:none;grid-template-columns:repeat(7,1fr);text-align:center;background:#f8f8f8;border-bottom:1px solid #e5e5e5;font-size:12px;font-weight:bold;color:#555;padding:8px 0;"></div>
                            <div id="pickup-cal-loading" style="padding:20px;text-align:center;color:#999;">Loading dates…</div>
                            <div id="pickup-cal-grid" style="display:none;grid-template-columns:repeat(7,1fr);"></div>
                        </div>
                    </div>

                    <!-- Time slots -->
                    <div id="pickup-timeslots-wrapper" style="display:none;margin-top:15px;">
                        <label style="font-weight:bold;display:block;margin-bottom:10px;">
                            Pickup Time <span class="required">*</span>
                        </label>
                        <input type="hidden" name="pickup_time_order" id="pickup_time_order">
                        <div id="pickup-timeslots-loading" style="display:none;color:#999;font-size:13px;">Loading time slots…</div>
                        <div id="pickup-timeslots-grid"></div>
                    </div>

                </div>

                <!-- Alternate Pickup Person -->
                <div id="alternate-pickup-section" style="border-top:1px solid #e5e5e5;margin-top:20px;padding-top:20px;">
                    <h4 style="margin:0 0 12px;font-size:14px;font-weight:600;">Alternate Pickup Person</h4>
                    <p class="form-row form-row-wide" style="margin-bottom:10px;">
                        <label for="has_alternate_pickup" style="display:block;margin-bottom:5px;">
                            Would you like to designate an alternate pickup person?
                        </label>
                        <select name="has_alternate_pickup" id="has_alternate_pickup" class="select input-text" style="width:100%;padding:8px;">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </p>
                    <div id="alternate-pickup-fields" style="display:none;">
                        <p class="form-row form-row-wide" style="margin-bottom:10px;">
                            <label for="alternate_pickup_name" style="display:block;margin-bottom:5px;">
                                Full Name <span class="required">*</span>
                            </label>
                            <input type="text" name="alternate_pickup_name" id="alternate_pickup_name"
                                   class="input-text" style="width:100%;padding:8px;box-sizing:border-box;"
                                   placeholder="Full name">
                        </p>
                        <p class="form-row form-row-first" style="margin-bottom:10px;">
                            <label for="alternate_pickup_phone" style="display:block;margin-bottom:5px;">
                                Phone Number <span class="required">*</span>
                            </label>
                            <input type="tel" name="alternate_pickup_phone" id="alternate_pickup_phone"
                                   class="input-text" style="width:100%;padding:8px;box-sizing:border-box;"
                                   placeholder="Phone number">
                        </p>
                        <p class="form-row form-row-last" style="margin-bottom:0;">
                            <label for="alternate_pickup_email" style="display:block;margin-bottom:5px;">
                                Email Address <span class="required">*</span>
                            </label>
                            <input type="email" name="alternate_pickup_email" id="alternate_pickup_email"
                                   class="input-text" style="width:100%;padding:8px;box-sizing:border-box;"
                                   placeholder="Email address">
                        </p>
                    </div>
                </div>

            </div>
        </div>
        <script>
        jQuery(function($) {
            $('#has_alternate_pickup').on('change', function() {
                if ( $(this).val() === 'yes' ) {
                    $('#alternate-pickup-fields').slideDown(200);
                } else {
                    $('#alternate-pickup-fields').slideUp(200);
                }
            });
        });
        </script>
        <?php
        // The interactive JS is in assets/js/pickup-checkout.js (enqueued above).
    }
}
