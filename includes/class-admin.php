<?php
defined( 'ABSPATH' ) || exit;

class WCLPM_Admin {

    public function __construct() {
        add_action( 'admin_menu',         [ $this, 'register_menu' ] );
        add_action( 'admin_init',         [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    // ─── Menu ──────────────────────────────────────────────────────────────

    public function register_menu() {
        // Top-level entry under WooCommerce
        add_submenu_page(
            'woocommerce',
            'Pickup Manager',
            'Pickup Manager',
            'manage_woocommerce',
            'wclpm-manager',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'woocommerce',
            'Pickup Change Requests',
            'Change Requests',
            'manage_woocommerce',
            'wclpm-change-requests',
            [ $this, 'render_change_requests_page' ]
        );
    }

    // ─── Save ──────────────────────────────────────────────────────────────

    public function handle_save() {
        if (
            ! isset( $_POST['wclpm_save_settings'] ) ||
            ! current_user_can( 'manage_woocommerce' ) ||
            ! check_admin_referer( 'wclpm_settings_save' )
        ) {
            return;
        }

        WCLPM_Settings::save( $_POST );

        // Reschedule crons if send time changed
        WCLPM_Reminders::unschedule_crons();
        WCLPM_Reminders::schedule_crons();

        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Pickup Manager settings saved.</p></div>';
        });
    }

    // ─── Styles ────────────────────────────────────────────────────────────

    public function enqueue_styles( $hook ) {
        if ( strpos( $hook, 'wclpm' ) === false ) {
            return;
        }
        // Minimal inline styles — no external dependency
        wp_add_inline_style( 'wp-admin', '
            .wclpm-wrap h1 { margin-bottom: 20px; }
            .wclpm-settings-table { width: 100%; max-width: 760px; border-collapse: collapse; }
            .wclpm-settings-table th { width: 260px; text-align: left; padding: 14px 10px; vertical-align: top; font-weight: 600; }
            .wclpm-settings-table td { padding: 10px; vertical-align: top; }
            .wclpm-settings-table tr { border-bottom: 1px solid #f0f0f0; }
            .wclpm-settings-table input[type=text],
            .wclpm-settings-table input[type=email],
            .wclpm-settings-table input[type=time],
            .wclpm-settings-table input[type=number],
            .wclpm-settings-table select { width: 100%; max-width: 360px; }
            .wclpm-section-heading { background: #1a1a2e; color: #fff; padding: 8px 12px; border-radius: 4px; margin: 28px 0 4px; font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; max-width: 760px; }
            .wclpm-settings-desc { color: #666; font-size: 12px; margin-top: 4px; }
            .wclpm-cr-table { width: 100%; border-collapse: collapse; }
            .wclpm-cr-table th, .wclpm-cr-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; text-align: left; }
            .wclpm-cr-table th { background: #f8f8f8; font-weight: 700; }
            .wclpm-badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600; }
            .wclpm-badge-pending  { background:#fff3cd; color:#856404; }
            .wclpm-badge-approved { background:#d1e7dd; color:#0a3622; }
            .wclpm-badge-denied   { background:#f8d7da; color:#842029; }
            .wclpm-process-row td { border-top: none !important; }
            .wclpm-approve-fields { display:none; gap:16px; flex-wrap:wrap; }
        ' );
    }

    // ─── Settings Page ──────────────────────────────────────────────────────

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        $s = WCLPM_Settings::get_all();
        ?>
        <div class="wrap wclpm-wrap">
            <h1>⚙️ Pickup Manager Settings</h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'wclpm_settings_save' ); ?>

                <!-- ── Email ───────────────────────────────────────── -->
                <div class="wclpm-section-heading">📧 Email Sender</div>
                <table class="wclpm-settings-table">
                    <tr>
                        <th><label for="from_name">Sender Name</label></th>
                        <td>
                            <input type="text" id="from_name" name="from_name"
                                   value="<?php echo esc_attr( $s['from_name'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="from_email">Sender Email Address</label></th>
                        <td>
                            <input type="email" id="from_email" name="from_email"
                                   value="<?php echo esc_attr( $s['from_email'] ); ?>">
                            <p class="wclpm-settings-desc">Must match your WP SMTP authenticated address.</p>
                        </td>
                    </tr>
                </table>

                <!-- ── Subject Lines ──────────────────────────────── -->
                <div class="wclpm-section-heading">✉️ Email Subject Lines</div>
                <p style="max-width:760px;color:#555;font-size:13px;margin:8px 0 12px;">
                    Available tokens: <code>{date}</code> <code>{time}</code> <code>{order_number}</code> <code>{customer_name}</code>
                </p>
                <table class="wclpm-settings-table">
                    <tr>
                        <th><label for="reminder_day_before_subject">Day-Before Reminder Subject</label></th>
                        <td>
                            <input type="text" id="reminder_day_before_subject"
                                   name="reminder_day_before_subject"
                                   value="<?php echo esc_attr( $s['reminder_day_before_subject'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="reminder_morning_subject">Morning-Of Reminder Subject</label></th>
                        <td>
                            <input type="text" id="reminder_morning_subject"
                                   name="reminder_morning_subject"
                                   value="<?php echo esc_attr( $s['reminder_morning_subject'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ready_for_pickup_subject">Ready for Pickup Subject</label></th>
                        <td>
                            <input type="text" id="ready_for_pickup_subject"
                                   name="ready_for_pickup_subject"
                                   value="<?php echo esc_attr( $s['ready_for_pickup_subject'] ); ?>">
                        </td>
                    </tr>
                </table>

                <!-- ── Reminder Timing ────────────────────────────── -->
                <div class="wclpm-section-heading">⏰ Reminder Timing</div>
                <table class="wclpm-settings-table">
                    <tr>
                        <th><label for="reminder_send_time">Daily Send Time</label></th>
                        <td>
                            <input type="time" id="reminder_send_time" name="reminder_send_time"
                                   value="<?php echo esc_attr( $s['reminder_send_time'] ); ?>">
                            <p class="wclpm-settings-desc">
                                Both reminders (day-before and morning-of) fire at this time daily.<br>
                                Uses your WordPress timezone (<?php echo esc_html( wp_timezone_string() ); ?>).
                                Changing this will reschedule the cron jobs automatically.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- ── Slot Settings ──────────────────────────────── -->
                <div class="wclpm-section-heading">📅 Slot Availability</div>
                <table class="wclpm-settings-table">
                    <tr>
                        <th><label for="default_slot_capacity">Default Slot Capacity</label></th>
                        <td>
                            <input type="number" id="default_slot_capacity" name="default_slot_capacity"
                                   value="<?php echo esc_attr( $s['default_slot_capacity'] ); ?>"
                                   min="1" max="999">
                            <p class="wclpm-settings-desc">Max orders per 15-minute slot. Per-location overrides can be set in ACF.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="booking_window_days">Booking Window (days)</label></th>
                        <td>
                            <input type="number" id="booking_window_days" name="booking_window_days"
                                   value="<?php echo esc_attr( $s['booking_window_days'] ); ?>"
                                   min="1" max="365">
                            <p class="wclpm-settings-desc">How far ahead customers can see and book available slots.</p>
                        </td>
                    </tr>
                </table>

                <!-- ── Change Requests ────────────────────────────── -->
                <div class="wclpm-section-heading">🔄 Pickup Change Requests</div>
                <table class="wclpm-settings-table">
                    <tr>
                        <th>Allow Change Requests</th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_change_requests" value="1"
                                       <?php checked( $s['allow_change_requests'] ); ?>>
                                Let customers submit a pickup change request from their order page
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="change_cutoff_hours">Cutoff (hours before pickup)</label></th>
                        <td>
                            <input type="number" id="change_cutoff_hours" name="change_cutoff_hours"
                                   value="<?php echo esc_attr( $s['change_cutoff_hours'] ); ?>"
                                   min="0" max="720">
                            <p class="wclpm-settings-desc">
                                Set to <strong>0</strong> to allow requests any time.<br>
                                e.g. <strong>24</strong> = requests blocked within 24 hours of pickup time.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- ── Email Branding ─────────────────────────────── -->
                <div class="wclpm-section-heading">🖼️ Email Branding</div>
                <table class="wclpm-settings-table">
                    <tr>
                        <th><label for="logo_url">Logo URL</label></th>
                        <td>
                            <input type="url" id="logo_url" name="logo_url"
                                   value="<?php echo esc_attr( $s['logo_url'] ); ?>"
                                   placeholder="https://…">
                            <p class="wclpm-settings-desc">
                                Shown at the top of reminder emails. Leave blank to use your site logo
                                (Appearance → Customize → Site Identity), or omit entirely.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="store_address">Store Address</label></th>
                        <td>
                            <input type="text" id="store_address" name="store_address"
                                   value="<?php echo esc_attr( $s['store_address'] ); ?>"
                                   placeholder="123 Main St, City, State 00000">
                            <p class="wclpm-settings-desc">Shown in the email footer. Leave blank to omit.</p>
                        </td>
                    </tr>
                </table>

                <!-- ── CRM Integration ───────────────────────────── -->
                <div class="wclpm-section-heading">🔗 CRM Integration (optional)</div>
                <p style="max-width:760px;color:#555;font-size:13px;margin:8px 0 12px;">
                    Populates an affiliation dropdown at checkout from any CRM API.
                    Leave <strong>API URLs</strong> blank to disable the feature entirely.
                    Each endpoint must return JSON in the format <code>{"list":[{"id":"…","name":"…"}]}</code>.
                    Results from all URLs are merged, deduplicated, and sorted alphabetically.
                    Pagination is handled automatically per URL.
                </p>
                <table class="wclpm-settings-table">
                    <tr>
                        <th><label for="crm_api_url">API URLs</label></th>
                        <td>
                            <textarea id="crm_api_url" name="crm_api_url" rows="4"
                                      style="width:100%;font-family:monospace;font-size:12px;"
                                      placeholder="https://your-crm.example.com/api/v1/accounts?type=Church&#10;https://your-crm.example.com/api/v1/accounts?type=Company"><?php echo esc_textarea( $s['crm_api_url'] ); ?></textarea>
                            <p class="wclpm-settings-desc">
                                One URL per line. Use multiple URLs to work around per-request result limits — each is fetched separately and results are merged.
                                Do not include pagination parameters; those are appended automatically.
                                Results are cached for 24 hours.
                                <a href="<?php echo esc_url( add_query_arg( 'clear_crm_cache', '1' ) ); ?>">Clear cache now</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crm_api_key">API Key</label></th>
                        <td>
                            <input type="text" id="crm_api_key" name="crm_api_key"
                                   value="<?php echo esc_attr( WCLPM_Settings::mask_api_key( $s['crm_api_key'] ) ); ?>"
                                   autocomplete="off" style="font-family:monospace;">
                            <p class="wclpm-settings-desc">
                                Sent as the <code>X-Api-Key</code> header. Leave blank if your endpoint needs no authentication.
                                <?php if ( ! empty( $s['crm_api_key'] ) ) : ?>
                                To replace the key, clear this field and enter the new value.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crm_group_label">Field Label</label></th>
                        <td>
                            <input type="text" id="crm_group_label" name="crm_group_label"
                                   value="<?php echo esc_attr( $s['crm_group_label'] ); ?>">
                            <p class="wclpm-settings-desc">Label shown above the dropdown at checkout (e.g. "Church Affiliation", "Organization").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crm_max_per_page">Items Per Request</label></th>
                        <td>
                            <input type="number" id="crm_max_per_page" name="crm_max_per_page"
                                   value="<?php echo esc_attr( $s['crm_max_per_page'] ); ?>"
                                   min="1" style="width:80px;">
                            <p class="wclpm-settings-desc">Maximum items the API returns per call. The plugin pages through all results automatically until every item is fetched.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crm_offset_param">Offset Parameter</label></th>
                        <td>
                            <input type="text" id="crm_offset_param" name="crm_offset_param"
                                   value="<?php echo esc_attr( $s['crm_offset_param'] ); ?>"
                                   style="width:160px;" placeholder="offset">
                            <p class="wclpm-settings-desc">Query parameter name for the pagination offset (e.g. <code>offset</code>, <code>skip</code>, <code>start</code>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crm_limit_param">Limit Parameter</label></th>
                        <td>
                            <input type="text" id="crm_limit_param" name="crm_limit_param"
                                   value="<?php echo esc_attr( $s['crm_limit_param'] ); ?>"
                                   style="width:160px;" placeholder="maxSize">
                            <p class="wclpm-settings-desc">Query parameter name for the page size (e.g. <code>maxSize</code>, <code>limit</code>, <code>perPage</code>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crm_list_key">Response List Key</label></th>
                        <td>
                            <input type="text" id="crm_list_key" name="crm_list_key"
                                   value="<?php echo esc_attr( $s['crm_list_key'] ); ?>"
                                   style="width:160px;" placeholder="list">
                            <p class="wclpm-settings-desc">JSON key in the API response that contains the results array (e.g. <code>list</code>, <code>data</code>, <code>results</code>, <code>items</code>).</p>
                        </td>
                    </tr>
                </table>

                <p style="margin-top:24px;">
                    <input type="submit" name="wclpm_save_settings"
                           class="button button-primary button-large"
                           value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }

    // ─── Change Requests Page ───────────────────────────────────────────────

    public function render_change_requests_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        global $wpdb;
        $table  = WCLPM_Database::table();
        $notice = '';

        // Handle process (approve / deny) action
        if ( isset( $_POST['wclpm_process_cr_submit'] ) ) {
            $booking_id = intval( $_POST['wclpm_booking_id'] ?? 0 );
            if ( $booking_id && check_admin_referer( 'wclpm_process_cr_' . $booking_id ) ) {
                $action  = sanitize_key( $_POST['wclpm_cr_action'] ?? '' );
                $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ) );
                $order   = $booking ? wc_get_order( $booking->order_id ) : null;

                if ( $booking && $order && in_array( $action, [ 'approve', 'deny' ], true ) ) {
                    if ( $action === 'approve' ) {
                        $new_loc_id  = intval( $_POST['new_location_id'] ?? $booking->location_id );
                        $new_date    = sanitize_text_field( $_POST['new_date'] ?? $booking->pickup_date );
                        $new_time    = sanitize_text_field( $_POST['new_time'] ?? $booking->pickup_time );
                        $date_obj    = DateTime::createFromFormat( 'Y-m-d', $new_date );
                        $time_obj    = DateTime::createFromFormat( 'H:i', $new_time );

                        $wpdb->update( $table, [
                            'location_id'      => $new_loc_id,
                            'pickup_date'      => $new_date,
                            'pickup_time'      => $new_time,
                            'change_requested' => 2,
                        ], [ 'id' => $booking_id ] );

                        $old_pickup = (array) ( $order->get_meta( '_pickup_selections' ) ?: [] );
                        $order->update_meta_data( '_pickup_selections', array_merge( $old_pickup, [
                            'location_id'      => $new_loc_id,
                            'location_name'    => get_the_title( $new_loc_id ),
                            'location_address' => wclpm_get_field( 'location_address', $new_loc_id ),
                            'date'             => $date_obj ? $date_obj->format( 'Ymd' ) : str_replace( '-', '', $new_date ),
                            'date_display'     => $date_obj ? $date_obj->format( 'l, F j, Y' ) : $new_date,
                            'time'             => $new_time,
                            'time_display'     => $time_obj ? $time_obj->format( 'g:i A' ) : $new_time,
                        ] ) );
                        $order->add_order_note( sprintf(
                            'Pickup change approved. Updated to %s at %s.',
                            $date_obj ? $date_obj->format( 'M j, Y' ) : $new_date,
                            $time_obj ? $time_obj->format( 'g:i A' ) : $new_time
                        ) );
                        $order->save();

                        $old_date_obj = DateTime::createFromFormat( 'Y-m-d', $booking->pickup_date );
                        $old_time_obj = DateTime::createFromFormat( 'H:i', $booking->pickup_time );
                        $this->send_change_request_email( $booking, $order, 'approve', [
                            'location_name' => get_the_title( $booking->location_id ) ?: ( $old_pickup['location_name'] ?? '' ),
                            'date_display'  => $old_date_obj ? $old_date_obj->format( 'l, F j, Y' ) : $booking->pickup_date,
                            'time_display'  => $old_time_obj ? $old_time_obj->format( 'g:i A' ) : $booking->pickup_time,
                        ], [
                            'location_name' => get_the_title( $new_loc_id ),
                            'date_display'  => $date_obj ? $date_obj->format( 'l, F j, Y' ) : $new_date,
                            'time_display'  => $time_obj ? $time_obj->format( 'g:i A' ) : $new_time,
                        ] );
                        $notice = '<div class="notice notice-success is-dismissible"><p>Change request approved and customer notified.</p></div>';

                    } else {
                        $wpdb->update( $table, [ 'change_requested' => 3 ], [ 'id' => $booking_id ] );
                        $order->add_order_note( 'Pickup change request denied.' );
                        $order->save();

                        $old_pickup   = (array) ( $order->get_meta( '_pickup_selections' ) ?: [] );
                        $old_date_obj = DateTime::createFromFormat( 'Y-m-d', $booking->pickup_date );
                        $old_time_obj = DateTime::createFromFormat( 'H:i', $booking->pickup_time );
                        $this->send_change_request_email( $booking, $order, 'deny', [
                            'location_name' => get_the_title( $booking->location_id ) ?: ( $old_pickup['location_name'] ?? '' ),
                            'date_display'  => $old_date_obj ? $old_date_obj->format( 'l, F j, Y' ) : $booking->pickup_date,
                            'time_display'  => $old_time_obj ? $old_time_obj->format( 'g:i A' ) : $booking->pickup_time,
                        ], null );
                        $notice = '<div class="notice notice-success is-dismissible"><p>Change request denied and customer notified.</p></div>';
                    }
                }
            }
        }

        $requests = $wpdb->get_results(
            "SELECT b.*, p.post_title AS location_name
             FROM {$table} b
             LEFT JOIN {$wpdb->posts} p ON p.ID = b.location_id
             WHERE b.change_requested IN (1, 2, 3)
             ORDER BY b.change_requested ASC, b.pickup_date ASC"
        );

        $locations = get_posts( [
            'post_type'      => 'pickup_location',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <div class="wrap wclpm-wrap">
            <h1>🔄 Pickup Change Requests</h1>

            <?php echo wp_kses_post( $notice ); ?>

            <?php if ( empty( $requests ) ) : ?>
                <p>No change requests on file.</p>
            <?php else : ?>
            <table class="wclpm-cr-table widefat">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Location</th>
                        <th>Pickup Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $requests as $row ) :
                    $order        = wc_get_order( $row->order_id );
                    $order_link   = $order ? $order->get_edit_order_url() : '#';
                    $customer     = $order ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : '—';
                    $date_display = $row->pickup_date ? ( new DateTime( $row->pickup_date ) )->format( 'M j, Y' ) : '—';
                    $time_display = $row->pickup_time ? ( new DateTime( '1970-01-01 ' . $row->pickup_time ) )->format( 'g:i A' ) : '—';
                    $status       = intval( $row->change_requested );
                    $is_pending   = ( $status === 1 );
                ?>
                <tr>
                    <td>
                        <?php if ( $order ) : ?>
                            <a href="<?php echo esc_url( $order_link ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
                        <?php else : ?>
                            #<?php echo esc_html( $row->order_id ); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $customer ); ?><br><small><?php echo esc_html( $row->customer_email ); ?></small></td>
                    <td><?php echo esc_html( $row->location_name ?: '—' ); ?></td>
                    <td><?php echo esc_html( $date_display ); ?></td>
                    <td><?php echo esc_html( $time_display ); ?></td>
                    <td>
                        <?php if ( $status === 1 ) : ?>
                            <span class="wclpm-badge wclpm-badge-pending">Pending</span>
                        <?php elseif ( $status === 2 ) : ?>
                            <span class="wclpm-badge wclpm-badge-approved">Approved</span>
                        <?php else : ?>
                            <span class="wclpm-badge wclpm-badge-denied">Denied</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $is_pending ) : ?>
                            <button type="button" class="button button-small wclpm-toggle-form"
                                    data-target="wclpm-form-<?php echo esc_attr( $row->id ); ?>">
                                Process
                            </button>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( $is_pending ) : ?>
                <tr id="wclpm-form-<?php echo esc_attr( $row->id ); ?>" class="wclpm-process-row" style="display:none;background:#f9f9f9;">
                    <td colspan="7" style="padding:20px 16px;">
                        <?php if ( ! empty( $row->change_request_note ) ) : ?>
                        <p style="margin:0 0 16px;padding:10px 14px;background:#fff;border-left:3px solid #1a1a2e;font-size:13px;">
                            <strong>Customer's request:</strong> <?php echo esc_html( $row->change_request_note ); ?>
                        </p>
                        <?php endif; ?>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'wclpm_process_cr_' . $row->id ); ?>
                            <input type="hidden" name="wclpm_process_cr_submit" value="1">
                            <input type="hidden" name="wclpm_booking_id" value="<?php echo esc_attr( $row->id ); ?>">
                            <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                                <div>
                                    <label style="font-weight:600;display:block;margin-bottom:8px;">Decision</label>
                                    <label style="margin-right:16px;cursor:pointer;">
                                        <input type="radio" name="wclpm_cr_action" value="approve" class="wclpm-cr-radio"> Approve
                                    </label>
                                    <label style="cursor:pointer;">
                                        <input type="radio" name="wclpm_cr_action" value="deny" class="wclpm-cr-radio"> Deny
                                    </label>
                                </div>
                                <div class="wclpm-approve-fields">
                                    <div>
                                        <label style="font-weight:600;display:block;margin-bottom:4px;">New Location</label>
                                        <select name="new_location_id">
                                            <?php foreach ( $locations as $loc ) : ?>
                                            <option value="<?php echo esc_attr( $loc->ID ); ?>"
                                                <?php selected( intval( $loc->ID ), intval( $row->location_id ) ); ?>>
                                                <?php echo esc_html( $loc->post_title ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-weight:600;display:block;margin-bottom:4px;">New Date</label>
                                        <input type="date" name="new_date" value="<?php echo esc_attr( $row->pickup_date ); ?>">
                                    </div>
                                    <div>
                                        <label style="font-weight:600;display:block;margin-bottom:4px;">New Time</label>
                                        <input type="time" name="new_time" value="<?php echo esc_attr( $row->pickup_time ); ?>">
                                    </div>
                                </div>
                                <div style="align-self:flex-end;padding-top:20px;">
                                    <button type="submit" class="button button-primary">Confirm</button>
                                    <button type="button" class="button wclpm-cancel-form"
                                            data-target="wclpm-form-<?php echo esc_attr( $row->id ); ?>">Cancel</button>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            jQuery(function($) {
                $('.wclpm-toggle-form').on('click', function() {
                    $('#' + $(this).data('target')).toggle();
                });
                $('.wclpm-cancel-form').on('click', function() {
                    $('#' + $(this).data('target')).hide();
                });
                $(document).on('change', '.wclpm-cr-radio', function() {
                    var $fields = $(this).closest('form').find('.wclpm-approve-fields');
                    $fields.css('display', $(this).val() === 'approve' ? 'flex' : 'none');
                });
            });
            </script>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── Change Request Email ───────────────────────────────────────────────

    private function send_change_request_email( $booking, $order, $action, $old_details, $new_details ) {
        $settings  = WCLPM_Settings::get_all();
        $email_to  = $booking->customer_email ?: $order->get_billing_email();
        $first     = $order->get_billing_first_name();
        $order_num = $order->get_order_number();
        $headers   = [
            'Content-Type: text/html; charset=UTF-8',
            'From: '     . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'Reply-To: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        ];

        // Logo
        $logo_url = $settings['logo_url'] ?? '';
        if ( empty( $logo_url ) ) {
            $logo_id = get_theme_mod( 'custom_logo' );
            if ( $logo_id ) {
                $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
            }
        }
        $logo_html = $logo_url
            ? '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="max-height:50px;margin-bottom:16px;">'
            : '';

        // Footer
        $footer = '<p style="margin:0;font-size:12px;color:#888;">' . esc_html( get_bloginfo( 'name' ) );
        if ( ! empty( $settings['store_address'] ) ) {
            $footer .= '<br>' . esc_html( $settings['store_address'] );
        }
        $footer .= '</p>';

        if ( $action === 'approve' ) {
            $subject = 'Your Pickup Change Has Been Approved — Order #' . $order_num;
            $heading = 'Pickup Change Approved';
            $intro   = 'Great news, <strong>' . esc_html( $first ) . '</strong>! Your pickup change request for Order #' . esc_html( $order_num ) . ' has been approved.';
            $details = '
                <tr><td colspan="2" style="padding:8px 16px;background:#f0f0f0;font-weight:700;font-size:12px;color:#555;text-transform:uppercase;letter-spacing:.04em;">Previous Pickup</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;color:#555;font-size:14px;width:120px;">Location</td><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#333;font-size:14px;">' . esc_html( $old_details['location_name'] ) . '</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;color:#555;font-size:14px;">Date</td><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#333;font-size:14px;">' . esc_html( $old_details['date_display'] ) . '</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:2px solid #ddd;font-weight:600;color:#555;font-size:14px;">Time</td><td style="padding:10px 16px;border-bottom:2px solid #ddd;color:#333;font-size:14px;">' . esc_html( $old_details['time_display'] ) . '</td></tr>
                <tr><td colspan="2" style="padding:8px 16px;background:#1a1a2e;color:#fff;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">New Pickup</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;color:#555;font-size:14px;">Location</td><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#333;font-size:14px;">' . esc_html( $new_details['location_name'] ) . '</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;color:#555;font-size:14px;">Date</td><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#333;font-size:14px;">' . esc_html( $new_details['date_display'] ) . '</td></tr>
                <tr><td style="padding:10px 16px;font-weight:600;color:#555;font-size:14px;">Time</td><td style="padding:10px 16px;color:#333;font-size:14px;">' . esc_html( $new_details['time_display'] ) . '</td></tr>';
            $closing = 'Please bring your order confirmation when picking up your items. We look forward to seeing you!';
        } else {
            $subject = 'Update on Your Pickup Change Request — Order #' . $order_num;
            $heading = 'Pickup Change Request Update';
            $intro   = 'Hi <strong>' . esc_html( $first ) . '</strong>, thank you for your request regarding Order #' . esc_html( $order_num ) . '. Unfortunately, we were unable to accommodate the change at this time.';
            $details = '
                <tr><td colspan="2" style="padding:8px 16px;background:#f0f0f0;font-weight:700;font-size:12px;color:#555;text-transform:uppercase;letter-spacing:.04em;">Your Current Pickup</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;color:#555;font-size:14px;width:120px;">Location</td><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#333;font-size:14px;">' . esc_html( $old_details['location_name'] ) . '</td></tr>
                <tr><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;color:#555;font-size:14px;">Date</td><td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#333;font-size:14px;">' . esc_html( $old_details['date_display'] ) . '</td></tr>
                <tr><td style="padding:10px 16px;font-weight:600;color:#555;font-size:14px;">Time</td><td style="padding:10px 16px;color:#333;font-size:14px;">' . esc_html( $old_details['time_display'] ) . '</td></tr>';
            $closing = 'Your original pickup details remain unchanged. If you have any questions, please don\'t hesitate to contact us.';
        }

        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>' . esc_html( $heading ) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f0f0;padding:32px 0;">
<tr><td align="center" style="padding:0 12px;">
<table width="640" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;width:100%;">
<tr><td style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:28px 36px;text-align:center;">'
    . $logo_html
    . '<h1 style="color:#fff;margin:0;font-size:22px;font-weight:700;">' . esc_html( $heading ) . '</h1>
</td></tr>
<tr><td style="background:#fff;padding:28px 36px;">
<p style="font-size:15px;color:#333;">' . $intro . '</p>
<table cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:20px 0 24px;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;">'
    . $details
    . '</table>
<p style="font-size:14px;color:#555;">' . esc_html( $closing ) . '</p>
</td></tr>
<tr><td style="background:#f8f8f8;border:1px solid #e5e5e5;border-top:none;border-radius:0 0 8px 8px;padding:24px 36px;text-align:center;">
<p style="margin:0 0 8px;font-size:13px;color:#555;">Questions? <a href="mailto:' . esc_attr( $settings['from_email'] ) . '" style="color:#1a1a2e;font-weight:bold;">' . esc_html( $settings['from_email'] ) . '</a></p>'
    . $footer
    . '</td></tr>
</table></td></tr></table>
</body></html>';

        wp_mail( $email_to, $subject, $html, $headers );
    }
}
