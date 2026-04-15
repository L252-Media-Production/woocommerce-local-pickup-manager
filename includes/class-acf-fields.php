<?php
defined( 'ABSPATH' ) || exit;

/**
 * Programmatically registers ACF field groups for the plugin.
 *
 * Fields are always in sync with the codebase — no JSON import needed.
 * Requires ACF Pro (repeater + relationship fields).
 *
 * Expected JSON response shape from CRM API (for reference):
 *   { "list": [ { "id": "…", "name": "…" } ] }
 */
class WCLPM_ACF_Fields {

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_field_groups' ] );
    }

    public function register_field_groups() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $this->register_pickup_location_fields();
        $this->register_product_fields();
    }

    // ─── Pickup Location CPT ─────────────────────────────────────────────────

    private function register_pickup_location_fields() {
        acf_add_local_field_group( [
            'key'    => 'group_wclpm_pickup_location',
            'title'  => 'Pickup Location Settings',
            'fields' => [

                // ── Basic info ──────────────────────────────────────────────

                [
                    'key'          => 'field_wclpm_loc_address',
                    'label'        => 'Address',
                    'name'         => 'location_address',
                    'type'         => 'text',
                    'instructions' => 'Full address shown to customers at checkout and in emails.',
                    'required'     => 1,
                ],
                [
                    'key'          => 'field_wclpm_loc_capacity',
                    'label'        => 'Slot Capacity Override',
                    'name'         => 'location_capacity',
                    'type'         => 'number',
                    'instructions' => 'Max orders per 15-minute slot. Default: 5.',
                    'default_value' => 5,
                    'required'     => 0,
                    'min'          => 1,
                ],
                [
                    'key'           => 'field_wclpm_loc_lead_time',
                    'label'         => 'Lead Time (hours)',
                    'name'          => 'lead_time_hours',
                    'type'          => 'number',
                    'instructions'  => 'Minimum advance notice before a slot is bookable. Default: 24.',
                    'required'      => 0,
                    'default_value' => 24,
                    'min'           => 0,
                ],

                // ── Default weekly hours ────────────────────────────────────

                [
                    'key'          => 'field_wclpm_loc_default_hours',
                    'label'        => 'Default Weekly Hours',
                    'name'         => 'default_weekly_hours',
                    'type'         => 'repeater',
                    'instructions' => 'Recurring weekly schedule. Specific date overrides below take precedence.',
                    'required'     => 0,
                    'layout'       => 'block',
                    'button_label' => 'Add Day',
                    'sub_fields'   => [
                        [
                            'key'      => 'field_wclpm_loc_day_of_week',
                            'label'    => 'Day of Week',
                            'name'     => 'day_of_week',
                            'type'     => 'select',
                            'required' => 1,
                            'wrapper'  => [ 'width' => '50' ],
                            'choices'  => [
                                'monday'    => 'Monday',
                                'tuesday'   => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday'  => 'Thursday',
                                'friday'    => 'Friday',
                                'saturday'  => 'Saturday',
                                'sunday'    => 'Sunday',
                            ],
                        ],
                        [
                            'key'          => 'field_wclpm_loc_default_ranges',
                            'label'        => 'Time Ranges',
                            'name'         => 'default_time_ranges',
                            'type'         => 'repeater',
                            'instructions' => 'One or more open windows for this day.',
                            'layout'       => 'table',
                            'button_label' => 'Add Time Range',
                            'wrapper'      => [ 'width' => '50' ],
                            'sub_fields'   => [
                                [
                                    'key'            => 'field_wclpm_loc_default_start',
                                    'label'          => 'Start Time',
                                    'name'           => 'start_time',
                                    'type'           => 'time_picker',
                                    'display_format' => 'g:i A',
                                    'return_format'  => 'H:i',
                                    'required'       => 1,
                                ],
                                [
                                    'key'            => 'field_wclpm_loc_default_end',
                                    'label'          => 'End Time',
                                    'name'           => 'end_time',
                                    'type'           => 'time_picker',
                                    'display_format' => 'g:i A',
                                    'return_format'  => 'H:i',
                                    'required'       => 1,
                                ],
                            ],
                        ],
                    ],
                ],

                // ── Specific date schedule ──────────────────────────────────

                [
                    'key'          => 'field_wclpm_loc_schedule',
                    'label'        => 'Specific Date Schedule',
                    'name'         => 'pickup_schedule',
                    'type'         => 'repeater',
                    'instructions' => 'Override hours for specific dates (e.g. holidays, special events).',
                    'required'     => 0,
                    'layout'       => 'block',
                    'button_label' => 'Add Date Override',
                    'sub_fields'   => [
                        [
                            'key'          => 'field_wclpm_loc_schedule_dates',
                            'label'        => 'Dates',
                            'name'         => 'schedule_dates',
                            'type'         => 'repeater',
                            'layout'       => 'table',
                            'button_label' => 'Add Date',
                            'wrapper'      => [ 'width' => '50' ],
                            'sub_fields'   => [
                                [
                                    'key'            => 'field_wclpm_loc_schedule_date',
                                    'label'          => 'Date',
                                    'name'           => 'schedule_date',
                                    'type'           => 'date_picker',
                                    'display_format' => 'F j, Y',
                                    'return_format'  => 'Ymd',
                                    'required'       => 1,
                                ],
                            ],
                        ],
                        [
                            'key'          => 'field_wclpm_loc_schedule_ranges',
                            'label'        => 'Time Ranges',
                            'name'         => 'schedule_time_ranges',
                            'type'         => 'repeater',
                            'layout'       => 'table',
                            'button_label' => 'Add Time Range',
                            'wrapper'      => [ 'width' => '50' ],
                            'sub_fields'   => [
                                [
                                    'key'            => 'field_wclpm_loc_schedule_start',
                                    'label'          => 'Start Time',
                                    'name'           => 'start_time',
                                    'type'           => 'time_picker',
                                    'display_format' => 'g:i A',
                                    'return_format'  => 'H:i',
                                    'required'       => 1,
                                ],
                                [
                                    'key'            => 'field_wclpm_loc_schedule_end',
                                    'label'          => 'End Time',
                                    'name'           => 'end_time',
                                    'type'           => 'time_picker',
                                    'display_format' => 'g:i A',
                                    'return_format'  => 'H:i',
                                    'required'       => 1,
                                ],
                            ],
                        ],
                    ],
                ],

                // ── Closed dates ────────────────────────────────────────────

                [
                    'key'          => 'field_wclpm_loc_closed_dates',
                    'label'        => 'Closed Dates',
                    'name'         => 'closed_dates',
                    'type'         => 'repeater',
                    'instructions' => 'Dates on which this location is closed. No slots will be shown.',
                    'required'     => 0,
                    'layout'       => 'table',
                    'button_label' => 'Add Closed Date',
                    'sub_fields'   => [
                        [
                            'key'            => 'field_wclpm_loc_closed_date',
                            'label'          => 'Date',
                            'name'           => 'closed_date',
                            'type'           => 'date_picker',
                            'display_format' => 'F j, Y',
                            'return_format'  => 'Ymd',
                            'required'       => 1,
                        ],
                        [
                            'key'      => 'field_wclpm_loc_closed_reason',
                            'label'    => 'Reason (optional)',
                            'name'     => 'closed_reason',
                            'type'     => 'text',
                            'required' => 0,
                        ],
                    ],
                ],

            ],
            'location' => [
                [
                    [ 'param' => 'post_type', 'operator' => '==', 'value' => 'pickup_location' ],
                ],
            ],
            'active' => true,
        ] );
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    private function register_product_fields() {
        acf_add_local_field_group( [
            'key'    => 'group_wclpm_product',
            'title'  => 'Pickup Settings',
            'fields' => [

                // ── Pickup enforcement ──────────────────────────────────────

                [
                    'key'           => 'field_wclpm_prod_pickup_only',
                    'label'         => 'Pickup Only',
                    'name'          => 'pickup_only',
                    'type'          => 'true_false',
                    'instructions'  => 'Force local pickup as the only shipping method for this product.',
                    'message'       => 'This product is available for local pickup only',
                    'default_value' => 0,
                    'ui'            => 1,
                ],
                [
                    'key'               => 'field_wclpm_prod_locations',
                    'label'             => 'Available Pickup Locations',
                    'name'              => 'available_pickup_locations',
                    'type'              => 'relationship',
                    'instructions'      => 'Which pickup locations carry this product.',
                    'required'          => 0,
                    'post_type'         => [ 'pickup_location' ],
                    'filters'           => [ 'search' ],
                    'return_format'     => 'object',
                    'conditional_logic' => [
                        [
                            [ 'field' => 'field_wclpm_prod_pickup_only', 'operator' => '==', 'value' => '1' ],
                        ],
                    ],
                ],

                // ── Pickup booking window ───────────────────────────────────

                [
                    'key'               => 'field_wclpm_prod_start_date',
                    'label'             => 'Pickup Start Date',
                    'name'              => 'pickup_start_date',
                    'type'              => 'date_picker',
                    'instructions'      => 'Earliest date customers can book a pickup. Leave blank for no restriction.',
                    'required'          => 0,
                    'display_format'    => 'F j, Y',
                    'return_format'     => 'Ymd',
                    'wrapper'           => [ 'width' => '50' ],
                    'conditional_logic' => [
                        [
                            [ 'field' => 'field_wclpm_prod_pickup_only', 'operator' => '==', 'value' => '1' ],
                        ],
                    ],
                ],
                [
                    'key'               => 'field_wclpm_prod_end_date',
                    'label'             => 'Pickup End Date',
                    'name'              => 'pickup_end_date',
                    'type'              => 'date_picker',
                    'instructions'      => 'Latest date customers can book a pickup. Leave blank for no restriction.',
                    'required'          => 0,
                    'display_format'    => 'F j, Y',
                    'return_format'     => 'Ymd',
                    'wrapper'           => [ 'width' => '50' ],
                    'conditional_logic' => [
                        [
                            [ 'field' => 'field_wclpm_prod_pickup_only', 'operator' => '==', 'value' => '1' ],
                        ],
                    ],
                ],

                // ── Seasonal availability ───────────────────────────────────

                [
                    'key'            => 'field_wclpm_prod_avail_start',
                    'label'          => 'Availability Start',
                    'name'           => 'availability_start_date',
                    'type'           => 'date_time_picker',
                    'instructions'   => 'When this product becomes purchasable. Leave blank for always available.',
                    'required'       => 0,
                    'display_format' => 'F j, Y g:i A',
                    'return_format'  => 'Y-m-d H:i:s',
                    'wrapper'        => [ 'width' => '50' ],
                ],
                [
                    'key'            => 'field_wclpm_prod_avail_end',
                    'label'          => 'Availability End',
                    'name'           => 'availability_end_date',
                    'type'           => 'date_time_picker',
                    'instructions'   => 'When this product stops being purchasable. Leave blank for no end date.',
                    'required'       => 0,
                    'display_format' => 'F j, Y g:i A',
                    'return_format'  => 'Y-m-d H:i:s',
                    'wrapper'        => [ 'width' => '50' ],
                ],
                [
                    'key'               => 'field_wclpm_prod_expires',
                    'label'             => 'Expires After End Date',
                    'name'              => 'expires_after_end_date',
                    'type'              => 'true_false',
                    'instructions'      => 'If on, product permanently expires after the end date. If off, availability repeats annually by month/day.',
                    'message'           => 'Product permanently expires (does not recur annually)',
                    'default_value'     => 0,
                    'ui'                => 1,
                    'conditional_logic' => [
                        [
                            [ 'field' => 'field_wclpm_prod_avail_end', 'operator' => '!=empty' ],
                        ],
                    ],
                ],

            ],
            'location' => [
                [
                    [ 'param' => 'post_type', 'operator' => '==', 'value' => 'product' ],
                ],
            ],
            'active' => true,
        ] );
    }
}
