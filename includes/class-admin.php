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
            .wclpm-badge-resolved { background:#d1e7dd; color:#0a3622; }
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
        $table = WCLPM_Database::table();

        // Handle mark-resolved action
        if (
            isset( $_GET['resolve'], $_GET['booking_id'] ) &&
            check_admin_referer( 'wclpm_resolve_cr_' . intval( $_GET['booking_id'] ) )
        ) {
            $wpdb->update(
                $table,
                [ 'change_requested' => 2 ], // 2 = resolved
                [ 'id' => intval( $_GET['booking_id'] ) ]
            );
            echo '<div class="notice notice-success is-dismissible"><p>Request marked as resolved.</p></div>';
        }

        $requests = $wpdb->get_results(
            "SELECT b.*, p.post_title AS location_name
             FROM {$table} b
             LEFT JOIN {$wpdb->posts} p ON p.ID = b.location_id
             WHERE b.change_requested IN (1, 2)
             ORDER BY b.change_requested ASC, b.pickup_date ASC"
        );
        ?>
        <div class="wrap wclpm-wrap">
            <h1>🔄 Pickup Change Requests</h1>

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
                        <th>Note</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $requests as $row ) :
                    $order        = wc_get_order( $row->order_id );
                    $order_link   = $order ? get_edit_post_link( $row->order_id ) : '#';
                    $customer     = $order ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : '—';
                    $date_display = $row->pickup_date ? ( new DateTime( $row->pickup_date ) )->format( 'M j, Y' ) : '—';
                    $time_display = $row->pickup_time ? ( new DateTime( '1970-01-01 ' . $row->pickup_time ) )->format( 'g:i A' ) : '—';
                    $is_resolved  = intval( $row->change_requested ) === 2;
                    $resolve_url  = wp_nonce_url(
                        add_query_arg([
                            'page'       => 'wclpm-change-requests',
                            'resolve'    => 1,
                            'booking_id' => $row->id,
                        ], admin_url( 'admin.php' ) ),
                        'wclpm_resolve_cr_' . $row->id
                    );
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
                    <td style="max-width:220px;font-size:12px;">
                        <?php echo esc_html( $row->change_request_note ?: '—' ); ?>
                    </td>
                    <td>
                        <?php if ( $is_resolved ) : ?>
                            <span class="wclpm-badge wclpm-badge-resolved">Resolved</span>
                        <?php else : ?>
                            <span class="wclpm-badge wclpm-badge-pending">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $is_resolved ) : ?>
                            <a href="<?php echo esc_url( $resolve_url ); ?>"
                               class="button button-small">Mark Resolved</a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
