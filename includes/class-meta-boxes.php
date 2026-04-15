<?php
defined( 'ABSPATH' ) || exit;

/**
 * Native WordPress meta boxes for pickup_location and product post types.
 * Only registered when ACF Pro is not active.
 *
 * Also defines wclpm_get_field() — always available as a compatibility shim:
 *   - When ACF Pro is active it delegates to get_field().
 *   - When ACF Pro is absent it reads from wp_postmeta (JSON-decoded where needed).
 */

if ( ! function_exists( 'wclpm_get_field' ) ) {
    function wclpm_get_field( $field, $post_id ) {
        if ( function_exists( 'get_field' ) ) {
            return get_field( $field, $post_id );
        }

        $value = get_post_meta( $post_id, $field, true );

        // JSON-encoded complex fields
        if ( in_array( $field, [ 'default_weekly_hours', 'pickup_schedule', 'closed_dates', 'available_pickup_locations' ], true )
             && is_string( $value ) && $value !== '' ) {
            $decoded = json_decode( $value, true );
            if ( $decoded !== null ) {
                // Return WP_Post objects for the relationship field, matching ACF behaviour
                if ( $field === 'available_pickup_locations' ) {
                    return array_values( array_filter( array_map( 'get_post', array_map( 'intval', $decoded ) ) ) );
                }
                return $decoded;
            }
        }

        return $value;
    }
}

// ─── Meta Boxes ───────────────────────────────────────────────────────────────

class WCLPM_Meta_Boxes {

    private static $days = [
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
        'sunday'    => 'Sunday',
    ];

    public function __construct() {
        // ACF Pro handles the UI when active — no native boxes needed
        if ( function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        add_action( 'add_meta_boxes',        [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post',             [ $this, 'save_location_meta' ], 10, 2 );
        add_action( 'save_post',             [ $this, 'save_product_meta' ],  10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'wclpm_location_settings',
            'Pickup Location Settings',
            [ $this, 'render_location_box' ],
            'pickup_location', 'normal', 'high'
        );
        add_meta_box(
            'wclpm_product_settings',
            'Pickup Settings',
            [ $this, 'render_product_box' ],
            'product', 'normal', 'default'
        );
    }

    public function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        global $post;
        if ( ! $post || ! in_array( $post->post_type, [ 'pickup_location', 'product' ], true ) ) {
            return;
        }
        wp_add_inline_style( 'wp-admin', $this->css() );
    }

    // ─── Location Meta Box ────────────────────────────────────────────────────

    public function render_location_box( $post ) {
        wp_nonce_field( 'wclpm_location_meta', 'wclpm_location_nonce' );

        $address      = get_post_meta( $post->ID, 'location_address',  true );
        $capacity     = get_post_meta( $post->ID, 'location_capacity', true );
        $lead         = get_post_meta( $post->ID, 'lead_time_hours',   true );
        $weekly_hours = $this->decode_meta( $post->ID, 'default_weekly_hours' );
        $schedule     = $this->decode_meta( $post->ID, 'pickup_schedule' );
        $closed_dates = $this->decode_meta( $post->ID, 'closed_dates' );
        ?>
        <div class="wclpm-mb">

            <!-- Basic Information -->
            <div class="wclpm-section">
                <h4>Basic Information</h4>
                <table class="wclpm-tbl">
                    <tr>
                        <th><label>Address <abbr title="required">*</abbr></label></th>
                        <td>
                            <input type="text" name="wclpm[location_address]"
                                   value="<?php echo esc_attr( $address ); ?>"
                                   class="large-text" required
                                   placeholder="Full address shown to customers at checkout and in emails">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Slot Capacity Override</label></th>
                        <td>
                            <input type="number" name="wclpm[location_capacity]"
                                   value="<?php echo esc_attr( $capacity ); ?>" min="1"
                                   style="width:80px;" placeholder="5">
                            <span class="description">Max orders per 15-min slot. Leave blank to use the global default.</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Lead Time (hours)</label></th>
                        <td>
                            <input type="number" name="wclpm[lead_time_hours]"
                                   value="<?php echo esc_attr( $lead ); ?>" min="0"
                                   style="width:80px;" placeholder="24">
                            <span class="description">Minimum advance notice before a slot is bookable.</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Default Weekly Hours -->
            <div class="wclpm-section">
                <h4>Default Weekly Hours</h4>
                <p class="description">Recurring weekly schedule. Specific date overrides below take precedence.</p>
                <div class="wclpm-repeater" data-type="weekly">
                    <?php foreach ( $weekly_hours as $i => $row ) : ?>
                        <?php $this->render_weekly_row( $i, $row ); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button wclpm-add-outer" data-type="weekly">+ Add Day</button>
            </div>

            <!-- Specific Date Schedule -->
            <div class="wclpm-section">
                <h4>Specific Date Schedule</h4>
                <p class="description">Override hours for specific dates (e.g. holidays, special events).</p>
                <div class="wclpm-repeater" data-type="schedule">
                    <?php foreach ( $schedule as $i => $row ) : ?>
                        <?php $this->render_schedule_row( $i, $row ); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button wclpm-add-outer" data-type="schedule">+ Add Date Override</button>
            </div>

            <!-- Closed Dates -->
            <div class="wclpm-section">
                <h4>Closed Dates</h4>
                <p class="description">Dates on which this location is closed. No slots will be shown.</p>
                <table class="wclpm-time-tbl" id="wclpm-closed-tbl">
                    <thead><tr><th>Date</th><th>Reason (optional)</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ( $closed_dates as $i => $c ) : ?>
                    <tr>
                        <td><input type="date" name="wclpm[closed_dates][<?php echo $i; ?>][closed_date]"
                                   value="<?php echo esc_attr( $this->ymd_to_input( $c['closed_date'] ?? '' ) ); ?>" required></td>
                        <td><input type="text" name="wclpm[closed_dates][<?php echo $i; ?>][closed_reason]"
                                   value="<?php echo esc_attr( $c['closed_reason'] ?? '' ); ?>"
                                   placeholder="e.g. Holiday"></td>
                        <td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="button" id="wclpm-add-closed">+ Add Closed Date</button>
            </div>

        </div>
        <?php $this->render_location_js(); ?>
        <?php
    }

    private function render_weekly_row( $i, $row ) {
        $day    = $row['day_of_week']        ?? '';
        $ranges = $row['default_time_ranges'] ?? [];
        ?>
        <div class="wclpm-outer-row">
            <div class="wclpm-outer-row-head">
                <span class="wclpm-row-lbl"><?php echo esc_html( self::$days[ $day ] ?? ucfirst( $day ) ?: 'New Day' ); ?></span>
                <button type="button" class="button-link wclpm-remove-outer" title="Remove">&#x2715;</button>
            </div>
            <div class="wclpm-outer-row-body">
                <div class="wclpm-half">
                    <label>Day of Week <abbr title="required">*</abbr></label>
                    <select name="wclpm[default_weekly_hours][<?php echo $i; ?>][day_of_week]" class="wclpm-day-sel">
                        <?php foreach ( self::$days as $v => $l ) : ?>
                        <option value="<?php echo $v; ?>" <?php selected( $day, $v ); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wclpm-half">
                    <label>Time Ranges</label>
                    <table class="wclpm-time-tbl">
                        <thead><tr><th>Start</th><th>End</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ( $ranges as $j => $r ) : ?>
                        <tr>
                            <td><input type="time" name="wclpm[default_weekly_hours][<?php echo $i; ?>][default_time_ranges][<?php echo $j; ?>][start_time]"
                                       value="<?php echo esc_attr( $r['start_time'] ?? '' ); ?>" required></td>
                            <td><input type="time" name="wclpm[default_weekly_hours][<?php echo $i; ?>][default_time_ranges][<?php echo $j; ?>][end_time]"
                                       value="<?php echo esc_attr( $r['end_time'] ?? '' ); ?>" required></td>
                            <td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button button-small wclpm-add-time-range">+ Add Time Range</button>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_schedule_row( $i, $row ) {
        $dates  = $row['schedule_dates']       ?? [];
        $ranges = $row['schedule_time_ranges'] ?? [];
        ?>
        <div class="wclpm-outer-row">
            <div class="wclpm-outer-row-head">
                <span class="wclpm-row-lbl">Override #<?php echo ( $i + 1 ); ?></span>
                <button type="button" class="button-link wclpm-remove-outer" title="Remove">&#x2715;</button>
            </div>
            <div class="wclpm-outer-row-body">
                <div class="wclpm-half">
                    <label>Dates</label>
                    <table class="wclpm-time-tbl">
                        <thead><tr><th>Date</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ( $dates as $j => $d ) : ?>
                        <tr>
                            <td><input type="date" name="wclpm[pickup_schedule][<?php echo $i; ?>][schedule_dates][<?php echo $j; ?>][schedule_date]"
                                       value="<?php echo esc_attr( $this->ymd_to_input( $d['schedule_date'] ?? '' ) ); ?>" required></td>
                            <td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button button-small wclpm-add-sched-date">+ Add Date</button>
                </div>
                <div class="wclpm-half">
                    <label>Time Ranges</label>
                    <table class="wclpm-time-tbl">
                        <thead><tr><th>Start</th><th>End</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ( $ranges as $j => $r ) : ?>
                        <tr>
                            <td><input type="time" name="wclpm[pickup_schedule][<?php echo $i; ?>][schedule_time_ranges][<?php echo $j; ?>][start_time]"
                                       value="<?php echo esc_attr( $r['start_time'] ?? '' ); ?>" required></td>
                            <td><input type="time" name="wclpm[pickup_schedule][<?php echo $i; ?>][schedule_time_ranges][<?php echo $j; ?>][end_time]"
                                       value="<?php echo esc_attr( $r['end_time'] ?? '' ); ?>" required></td>
                            <td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button button-small wclpm-add-sched-time">+ Add Time Range</button>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_location_js() {
        ?>
        <script>
        (function($) {
            var days = <?php echo wp_json_encode( self::$days ); ?>;

            // ── Remove sub-repeater row ─────────────────────────────────────
            $(document).on('click', '.wclpm-remove-tr', function() {
                var $tbody = $(this).closest('tbody');
                if ( $tbody.find('tr').length > 1 ) {
                    $(this).closest('tr').remove();
                } else {
                    $(this).closest('tr').find('input').val('');
                }
            });

            // ── Remove outer repeater row ───────────────────────────────────
            $(document).on('click', '.wclpm-remove-outer', function() {
                $(this).closest('.wclpm-outer-row').remove();
            });

            // ── Day-of-week label ───────────────────────────────────────────
            $(document).on('change', '.wclpm-day-sel', function() {
                $(this).closest('.wclpm-outer-row').find('.wclpm-row-lbl').text( days[this.value] || this.value );
            });

            // ── Add time range inside weekly row ────────────────────────────
            $(document).on('click', '.wclpm-add-time-range', function() {
                var $row     = $(this).closest('.wclpm-outer-row');
                var $tbody   = $row.find('.wclpm-time-tbl tbody');
                var outerIdx = $row.closest('.wclpm-repeater').find('.wclpm-outer-row').index($row);
                var innerIdx = $tbody.find('tr').length;
                $tbody.append(
                    '<tr>' +
                    '<td><input type="time" name="wclpm[default_weekly_hours][' + outerIdx + '][default_time_ranges][' + innerIdx + '][start_time]" required></td>' +
                    '<td><input type="time" name="wclpm[default_weekly_hours][' + outerIdx + '][default_time_ranges][' + innerIdx + '][end_time]" required></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr>'
                );
            });

            // ── Add date inside schedule row ────────────────────────────────
            $(document).on('click', '.wclpm-add-sched-date', function() {
                var $row     = $(this).closest('.wclpm-outer-row');
                var $tbody   = $(this).prev('.wclpm-time-tbl').find('tbody');
                var outerIdx = $row.closest('.wclpm-repeater').find('.wclpm-outer-row').index($row);
                var innerIdx = $tbody.find('tr').length;
                $tbody.append(
                    '<tr>' +
                    '<td><input type="date" name="wclpm[pickup_schedule][' + outerIdx + '][schedule_dates][' + innerIdx + '][schedule_date]" required></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr>'
                );
            });

            // ── Add time range inside schedule row ──────────────────────────
            $(document).on('click', '.wclpm-add-sched-time', function() {
                var $row     = $(this).closest('.wclpm-outer-row');
                var $tbody   = $(this).prev('.wclpm-time-tbl').find('tbody');
                var outerIdx = $row.closest('.wclpm-repeater').find('.wclpm-outer-row').index($row);
                var innerIdx = $tbody.find('tr').length;
                $tbody.append(
                    '<tr>' +
                    '<td><input type="time" name="wclpm[pickup_schedule][' + outerIdx + '][schedule_time_ranges][' + innerIdx + '][start_time]" required></td>' +
                    '<td><input type="time" name="wclpm[pickup_schedule][' + outerIdx + '][schedule_time_ranges][' + innerIdx + '][end_time]" required></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr>'
                );
            });

            // ── Add weekly hours row ────────────────────────────────────────
            $(document).on('click', '.wclpm-add-outer[data-type="weekly"]', function() {
                var idx = $('.wclpm-repeater[data-type="weekly"] .wclpm-outer-row').length;
                var opts = '';
                $.each(days, function(v, l) { opts += '<option value="' + v + '">' + l + '</option>'; });
                $('.wclpm-repeater[data-type="weekly"]').append(
                    '<div class="wclpm-outer-row">' +
                    '<div class="wclpm-outer-row-head"><span class="wclpm-row-lbl">Monday</span>' +
                    '<button type="button" class="button-link wclpm-remove-outer" title="Remove">&#x2715;</button></div>' +
                    '<div class="wclpm-outer-row-body">' +
                    '<div class="wclpm-half"><label>Day of Week <abbr title="required">*</abbr></label>' +
                    '<select name="wclpm[default_weekly_hours][' + idx + '][day_of_week]" class="wclpm-day-sel">' + opts + '</select></div>' +
                    '<div class="wclpm-half"><label>Time Ranges</label>' +
                    '<table class="wclpm-time-tbl"><thead><tr><th>Start</th><th>End</th><th></th></tr></thead><tbody><tr>' +
                    '<td><input type="time" name="wclpm[default_weekly_hours][' + idx + '][default_time_ranges][0][start_time]" required></td>' +
                    '<td><input type="time" name="wclpm[default_weekly_hours][' + idx + '][default_time_ranges][0][end_time]" required></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr></tbody></table>' +
                    '<button type="button" class="button button-small wclpm-add-time-range">+ Add Time Range</button></div>' +
                    '</div></div>'
                );
            });

            // ── Add schedule override row ───────────────────────────────────
            $(document).on('click', '.wclpm-add-outer[data-type="schedule"]', function() {
                var idx = $('.wclpm-repeater[data-type="schedule"] .wclpm-outer-row').length;
                $('.wclpm-repeater[data-type="schedule"]').append(
                    '<div class="wclpm-outer-row">' +
                    '<div class="wclpm-outer-row-head"><span class="wclpm-row-lbl">Override #' + (idx + 1) + '</span>' +
                    '<button type="button" class="button-link wclpm-remove-outer" title="Remove">&#x2715;</button></div>' +
                    '<div class="wclpm-outer-row-body">' +
                    '<div class="wclpm-half"><label>Dates</label>' +
                    '<table class="wclpm-time-tbl"><thead><tr><th>Date</th><th></th></tr></thead><tbody><tr>' +
                    '<td><input type="date" name="wclpm[pickup_schedule][' + idx + '][schedule_dates][0][schedule_date]" required></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr></tbody></table>' +
                    '<button type="button" class="button button-small wclpm-add-sched-date">+ Add Date</button></div>' +
                    '<div class="wclpm-half"><label>Time Ranges</label>' +
                    '<table class="wclpm-time-tbl"><thead><tr><th>Start</th><th>End</th><th></th></tr></thead><tbody><tr>' +
                    '<td><input type="time" name="wclpm[pickup_schedule][' + idx + '][schedule_time_ranges][0][start_time]" required></td>' +
                    '<td><input type="time" name="wclpm[pickup_schedule][' + idx + '][schedule_time_ranges][0][end_time]" required></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr></tbody></table>' +
                    '<button type="button" class="button button-small wclpm-add-sched-time">+ Add Time Range</button></div>' +
                    '</div></div>'
                );
            });

            // ── Add closed date row ─────────────────────────────────────────
            $('#wclpm-add-closed').on('click', function() {
                var idx = $('#wclpm-closed-tbl tbody tr').length;
                $('#wclpm-closed-tbl tbody').append(
                    '<tr>' +
                    '<td><input type="date" name="wclpm[closed_dates][' + idx + '][closed_date]" required></td>' +
                    '<td><input type="text" name="wclpm[closed_dates][' + idx + '][closed_reason]" placeholder="e.g. Holiday"></td>' +
                    '<td><button type="button" class="button-link wclpm-remove-tr" title="Remove">&#x2715;</button></td>' +
                    '</tr>'
                );
            });

        })(jQuery);
        </script>
        <?php
    }

    // ─── Product Meta Box ─────────────────────────────────────────────────────

    public function render_product_box( $post ) {
        wp_nonce_field( 'wclpm_product_meta', 'wclpm_product_nonce' );

        $pickup_only  = get_post_meta( $post->ID, 'pickup_only',                true );
        $locs_raw     = get_post_meta( $post->ID, 'available_pickup_locations', true );
        $selected_ids = $locs_raw ? array_map( 'intval', (array) json_decode( $locs_raw, true ) ) : [];
        $pickup_start = get_post_meta( $post->ID, 'pickup_start_date',          true );
        $pickup_end   = get_post_meta( $post->ID, 'pickup_end_date',            true );
        $avail_start  = get_post_meta( $post->ID, 'availability_start_date',    true );
        $avail_end    = get_post_meta( $post->ID, 'availability_end_date',      true );
        $expires      = get_post_meta( $post->ID, 'expires_after_end_date',     true );

        $all_locs = get_posts( [
            'post_type'      => 'pickup_location',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $pickup_start_in  = $this->ymd_to_input( $pickup_start );
        $pickup_end_in    = $this->ymd_to_input( $pickup_end );
        $avail_start_date = $avail_start ? substr( $avail_start, 0, 10 ) : '';
        $avail_start_time = $avail_start ? substr( $avail_start, 11, 5 ) : '';
        $avail_end_date   = $avail_end   ? substr( $avail_end, 0, 10 )   : '';
        $avail_end_time   = $avail_end   ? substr( $avail_end, 11, 5 )   : '';
        ?>
        <div class="wclpm-mb">

            <div class="wclpm-section">
                <label style="display:flex;gap:8px;align-items:center;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="wclpm[pickup_only]" id="wclpm_pickup_only"
                           value="1" <?php checked( $pickup_only, '1' ); ?>>
                    Pickup Only
                </label>
                <p class="description" style="margin-top:4px;">Force local pickup as the only shipping method for this product.</p>
            </div>

            <div id="wclpm-pickup-fields" <?php echo $pickup_only ? '' : 'style="display:none;"'; ?>>

                <div class="wclpm-section">
                    <h4>Available Pickup Locations</h4>
                    <?php if ( empty( $all_locs ) ) : ?>
                        <p><em>No published pickup locations found.
                            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pickup_location' ) ); ?>">Create one</a>.
                        </em></p>
                    <?php else : ?>
                        <div class="wclpm-checklist">
                            <?php foreach ( $all_locs as $loc ) : ?>
                            <label>
                                <input type="checkbox" name="wclpm[available_pickup_locations][]"
                                       value="<?php echo esc_attr( $loc->ID ); ?>"
                                       <?php checked( in_array( $loc->ID, $selected_ids, true ) ); ?>>
                                <?php echo esc_html( $loc->post_title ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="wclpm-section">
                    <h4>Pickup Booking Window</h4>
                    <table class="wclpm-tbl">
                        <tr>
                            <th>Pickup Start Date</th>
                            <td>
                                <input type="date" name="wclpm[pickup_start_date]"
                                       value="<?php echo esc_attr( $pickup_start_in ); ?>">
                                <span class="description">Earliest date customers can book. Leave blank for no restriction.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Pickup End Date</th>
                            <td>
                                <input type="date" name="wclpm[pickup_end_date]"
                                       value="<?php echo esc_attr( $pickup_end_in ); ?>">
                                <span class="description">Latest date customers can book. Leave blank for no restriction.</span>
                            </td>
                        </tr>
                    </table>
                </div>

            </div><!-- /#wclpm-pickup-fields -->

            <div class="wclpm-section">
                <h4>Seasonal Availability</h4>
                <table class="wclpm-tbl">
                    <tr>
                        <th>Availability Start</th>
                        <td>
                            <input type="date" name="wclpm[avail_start_date]" value="<?php echo esc_attr( $avail_start_date ); ?>">
                            <input type="time" name="wclpm[avail_start_time]" value="<?php echo esc_attr( $avail_start_time ); ?>">
                            <span class="description">When this product becomes purchasable. Leave blank for always available.</span>
                        </td>
                    </tr>
                    <tr>
                        <th>Availability End</th>
                        <td>
                            <input type="date" name="wclpm[avail_end_date]" id="wclpm_avail_end_date"
                                   value="<?php echo esc_attr( $avail_end_date ); ?>">
                            <input type="time" name="wclpm[avail_end_time]" value="<?php echo esc_attr( $avail_end_time ); ?>">
                            <span class="description">When this product stops being purchasable. Leave blank for no end date.</span>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="wclpm-expires-section" class="wclpm-section"
                 <?php echo $avail_end_date ? '' : 'style="display:none;"'; ?>>
                <label style="display:flex;gap:8px;align-items:center;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="wclpm[expires_after_end_date]"
                           value="1" <?php checked( $expires, '1' ); ?>>
                    Expires After End Date
                </label>
                <p class="description" style="margin-top:4px;">
                    If checked, product permanently expires after the end date.
                    If unchecked, availability repeats annually by month/day.
                </p>
            </div>

        </div>
        <script>
        (function($){
            $('#wclpm_pickup_only').on('change', function(){
                $('#wclpm-pickup-fields').toggle(this.checked);
            });
            $('#wclpm_avail_end_date').on('change input', function(){
                $('#wclpm-expires-section').toggle(!!this.value);
            });
        })(jQuery);
        </script>
        <?php
    }

    // ─── Save: Location ───────────────────────────────────────────────────────

    public function save_location_meta( $post_id, $post ) {
        if ( $post->post_type !== 'pickup_location' ) {
            return;
        }
        if ( ! isset( $_POST['wclpm_location_nonce'] ) ||
             ! wp_verify_nonce( $_POST['wclpm_location_nonce'], 'wclpm_location_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $d = $_POST['wclpm'] ?? [];

        update_post_meta( $post_id, 'location_address',  sanitize_text_field( $d['location_address']  ?? '' ) );
        update_post_meta( $post_id, 'location_capacity', sanitize_text_field( $d['location_capacity'] ?? '' ) );
        update_post_meta( $post_id, 'lead_time_hours',   sanitize_text_field( $d['lead_time_hours']   ?? '' ) );

        // Weekly hours
        $weekly = [];
        foreach ( (array) ( $d['default_weekly_hours'] ?? [] ) as $row ) {
            $ranges = [];
            foreach ( (array) ( $row['default_time_ranges'] ?? [] ) as $r ) {
                $s = sanitize_text_field( $r['start_time'] ?? '' );
                $e = sanitize_text_field( $r['end_time']   ?? '' );
                if ( $s && $e ) {
                    $ranges[] = [ 'start_time' => $s, 'end_time' => $e ];
                }
            }
            $day = sanitize_text_field( $row['day_of_week'] ?? '' );
            if ( $day && $ranges ) {
                $weekly[] = [ 'day_of_week' => $day, 'default_time_ranges' => $ranges ];
            }
        }
        update_post_meta( $post_id, 'default_weekly_hours', wp_json_encode( $weekly ) );

        // Specific date schedule
        $schedule = [];
        foreach ( (array) ( $d['pickup_schedule'] ?? [] ) as $row ) {
            $dates = [];
            foreach ( (array) ( $row['schedule_dates'] ?? [] ) as $dt ) {
                $v = sanitize_text_field( $dt['schedule_date'] ?? '' );
                if ( $v ) {
                    $obj     = DateTime::createFromFormat( 'Y-m-d', $v );
                    $dates[] = [ 'schedule_date' => $obj ? $obj->format( 'Ymd' ) : $v ];
                }
            }
            $ranges = [];
            foreach ( (array) ( $row['schedule_time_ranges'] ?? [] ) as $r ) {
                $s = sanitize_text_field( $r['start_time'] ?? '' );
                $e = sanitize_text_field( $r['end_time']   ?? '' );
                if ( $s && $e ) {
                    $ranges[] = [ 'start_time' => $s, 'end_time' => $e ];
                }
            }
            if ( $dates && $ranges ) {
                $schedule[] = [ 'schedule_dates' => $dates, 'schedule_time_ranges' => $ranges ];
            }
        }
        update_post_meta( $post_id, 'pickup_schedule', wp_json_encode( $schedule ) );

        // Closed dates
        $closed = [];
        foreach ( (array) ( $d['closed_dates'] ?? [] ) as $c ) {
            $v = sanitize_text_field( $c['closed_date'] ?? '' );
            if ( $v ) {
                $obj      = DateTime::createFromFormat( 'Y-m-d', $v );
                $closed[] = [
                    'closed_date'   => $obj ? $obj->format( 'Ymd' ) : $v,
                    'closed_reason' => sanitize_text_field( $c['closed_reason'] ?? '' ),
                ];
            }
        }
        update_post_meta( $post_id, 'closed_dates', wp_json_encode( $closed ) );
    }

    // ─── Save: Product ────────────────────────────────────────────────────────

    public function save_product_meta( $post_id, $post ) {
        if ( $post->post_type !== 'product' ) {
            return;
        }
        if ( ! isset( $_POST['wclpm_product_nonce'] ) ||
             ! wp_verify_nonce( $_POST['wclpm_product_nonce'], 'wclpm_product_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $d = $_POST['wclpm'] ?? [];

        update_post_meta( $post_id, 'pickup_only', ! empty( $d['pickup_only'] ) ? '1' : '0' );

        $ids = array_values( array_filter( array_map( 'intval', (array) ( $d['available_pickup_locations'] ?? [] ) ) ) );
        update_post_meta( $post_id, 'available_pickup_locations', wp_json_encode( $ids ) );

        update_post_meta( $post_id, 'pickup_start_date', $this->input_to_ymd( sanitize_text_field( $d['pickup_start_date'] ?? '' ) ) );
        update_post_meta( $post_id, 'pickup_end_date',   $this->input_to_ymd( sanitize_text_field( $d['pickup_end_date']   ?? '' ) ) );

        $avail_start = $this->combine_dt(
            sanitize_text_field( $d['avail_start_date'] ?? '' ),
            sanitize_text_field( $d['avail_start_time'] ?? '' )
        );
        $avail_end = $this->combine_dt(
            sanitize_text_field( $d['avail_end_date'] ?? '' ),
            sanitize_text_field( $d['avail_end_time'] ?? '' )
        );
        update_post_meta( $post_id, 'availability_start_date', $avail_start );
        update_post_meta( $post_id, 'availability_end_date',   $avail_end );

        update_post_meta( $post_id, 'expires_after_end_date', ! empty( $d['expires_after_end_date'] ) ? '1' : '0' );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function decode_meta( $post_id, $key ) {
        $raw     = get_post_meta( $post_id, $key, true );
        $decoded = $raw ? json_decode( $raw, true ) : null;
        return is_array( $decoded ) ? $decoded : [];
    }

    private function ymd_to_input( $ymd ) {
        if ( ! $ymd ) {
            return '';
        }
        $dt = DateTime::createFromFormat( 'Ymd', $ymd );
        return $dt ? $dt->format( 'Y-m-d' ) : '';
    }

    private function input_to_ymd( $input ) {
        if ( ! $input ) {
            return '';
        }
        $dt = DateTime::createFromFormat( 'Y-m-d', $input );
        return $dt ? $dt->format( 'Ymd' ) : '';
    }

    private function combine_dt( $date, $time ) {
        if ( ! $date ) {
            return '';
        }
        return $date . ' ' . ( $time ?: '00:00' ) . ':00';
    }

    // ─── CSS ─────────────────────────────────────────────────────────────────

    private function css() {
        return '
        .wclpm-mb { font-size:13px; }
        .wclpm-section { margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid #f0f0f0; }
        .wclpm-section:last-child { border-bottom:none; margin-bottom:0; }
        .wclpm-section h4 { margin:0 0 8px; font-size:13px; }
        .wclpm-section > .description { color:#666; font-size:12px; font-style:italic; margin:0 0 8px; }
        .wclpm-tbl { width:100%; border-collapse:collapse; margin-top:6px; }
        .wclpm-tbl th { width:180px; padding:8px 10px 8px 0; vertical-align:top; font-weight:600; }
        .wclpm-tbl td { padding:6px 0; vertical-align:top; }
        .wclpm-tbl .description { display:block; margin-top:4px; font-size:11px; color:#888; font-style:italic; }
        .wclpm-repeater { margin-bottom:8px; }
        .wclpm-outer-row { border:1px solid #ddd; border-radius:4px; margin-bottom:8px; overflow:hidden; }
        .wclpm-outer-row-head { display:flex; justify-content:space-between; align-items:center; background:#f6f7f7; padding:8px 12px; border-bottom:1px solid #ddd; }
        .wclpm-row-lbl { font-weight:600; }
        .wclpm-remove-outer, .wclpm-remove-tr { color:#a00 !important; font-size:16px !important; line-height:1; text-decoration:none; }
        .wclpm-outer-row-body { padding:12px; display:flex; gap:16px; flex-wrap:wrap; }
        .wclpm-half { flex:1; min-width:220px; }
        .wclpm-half > label { display:block; font-weight:600; margin-bottom:6px; font-size:12px; }
        .wclpm-half select { width:100%; max-width:240px; }
        .wclpm-time-tbl { width:100%; border-collapse:collapse; margin-bottom:6px; }
        .wclpm-time-tbl th { background:#f6f7f7; padding:4px 8px; font-size:11px; border:1px solid #ddd; text-align:left; }
        .wclpm-time-tbl td { padding:4px 6px; border:1px solid #f0f0f0; vertical-align:middle; }
        .wclpm-time-tbl input[type=time], .wclpm-time-tbl input[type=date] { width:100%; box-sizing:border-box; }
        .wclpm-checklist { display:flex; flex-direction:column; gap:6px; margin-top:4px; }
        .wclpm-checklist label { font-weight:normal; display:flex; gap:6px; align-items:center; cursor:pointer; }
        ';
    }
}
