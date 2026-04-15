# WooCommerce Local Pickup Manager — Project Context

This file provides context for Claude Code when working on this plugin.

---

## Project Overview

**WooCommerce Local Pickup Manager** is a WordPress plugin that adds advanced local pickup
scheduling on top of WooCommerce's built-in local pickup shipping method. It was originally
built as internal Code Snippets and has been consolidated into a distributable plugin.

**Current status:** Pre-release. Core functionality is complete. Remaining work is
infrastructure (LICENSE, README, uninstall.php) and optional quality-of-life improvements.

---

## Pre-Release Checklist

- [x] Main plugin file named `woocommerce-local-pickup-manager.php`
- [x] Plugin header clean (Plugin Name, Author, Plugin URI, Text Domain `wc-local-pickup`)
- [x] All hardcoded email addresses replaced with `from_email` setting
- [x] CRM URL configurable via admin settings (generic — not EspoCRM-specific)
- [x] Logo URL configurable (falls back to WP site logo, or omitted)
- [x] Store address configurable (omitted when blank)
- [x] All `wclpm_` prefixes, `WCLPM_*` class names, `WCLPM_*` constants in place
- [x] Hard WooCommerce activation check (deactivates + `wp_die` if WC missing)
- [x] ACF fields registered programmatically via `WCLPM_ACF_Fields`
- [x] Add `LICENSE` file (GPL-2.0)
- [x] Write `README.md`
- [ ] Add `uninstall.php` to drop DB table and options on plugin deletion

---

## Plugin Architecture

### File Structure
```
woocommerce-local-pickup-manager/
├── woocommerce-local-pickup-manager.php   # Main loader, constants, activation hooks
├── assets/
│   └── js/
│       └── pickup-checkout.js             # Calendar & time slot UI (jQuery)
├── includes/
│   ├── class-database.php                 # DB table creation + table name helper
│   ├── class-settings.php                 # Settings get/set/sanitize/defaults
│   ├── class-admin.php                    # WP Admin settings page + change requests page
│   ├── class-acf-fields.php               # Programmatic ACF field group registration
│   ├── class-post-types.php               # Pickup Location CPT
│   ├── class-order-status.php             # "Ready for Pickup" status + customer email
│   ├── class-checkout-fields.php          # WCLPM_Shipping, _Cart, _Checkout_Fields
│   ├── class-pickup-fields.php            # Checkout calendar/slot UI HTML + script enqueue
│   ├── class-ajax-slots.php               # AJAX handlers + WCLPM_Order_Meta
│   └── class-order-confirmation.php       # Thank you page, Reminders, Availability,
│                                          # Change Requests (all in one file)
├── LICENSE
├── README.md
└── CLAUDE.md                              # This file
```

### Classes (16 total)

| Class | File | Purpose |
|---|---|---|
| `WCLPM_Database` | class-database.php | Creates `wp_pickup_bookings` table, provides `::table()` helper |
| `WCLPM_Settings` | class-settings.php | Single option key, get/set/sanitize, seeds defaults on activation |
| `WCLPM_Admin` | class-admin.php | Settings page + Change Requests review page under WooCommerce menu |
| `WCLPM_ACF_Fields` | class-acf-fields.php | Registers ACF field groups for pickup locations and products via `acf/init` |
| `WCLPM_Post_Types` | class-post-types.php | Registers `pickup_location` CPT, shown under WooCommerce menu |
| `WCLPM_Order_Status` | class-order-status.php | Registers `wc-ready-pickup` status, sends customer email on status change |
| `WCLPM_Shipping` | class-checkout-fields.php | Re-enables legacy local pickup shipping method |
| `WCLPM_Cart` | class-checkout-fields.php | Enforces pickup-only shipping, prevents mixed carts |
| `WCLPM_Checkout_Fields` | class-checkout-fields.php | Optional CRM group affiliation dropdown + alternate pickup person fields |
| `WCLPM_Fields` | class-pickup-fields.php | Renders pickup UI (location/calendar/slots) at checkout, enqueues JS |
| `WCLPM_Ajax_Slots` | class-ajax-slots.php | `get_pickup_dates` and `get_pickup_slots` AJAX handlers |
| `WCLPM_Order_Meta` | class-ajax-slots.php | Validates/saves pickup selections, displays in admin/email/order page |
| `WCLPM_Order_Confirmation` | class-order-confirmation.php | Custom two-column thank you page layout |
| `WCLPM_Reminders` | class-order-confirmation.php | WP Cron day-before + morning-of reminder emails |
| `WCLPM_Availability` | class-order-confirmation.php | Seasonal product availability (ACF date fields) |
| `WCLPM_Change_Requests` | class-order-confirmation.php | Customer-facing change request form + submission handler |

---

## Database

**Table:** `wp_pickup_bookings`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `order_id` | BIGINT UNSIGNED | WooCommerce order ID |
| `product_id` | BIGINT UNSIGNED | Always 0 (pickup is per-order, not per-item) |
| `location_id` | BIGINT UNSIGNED | Post ID of `pickup_location` CPT |
| `pickup_date` | DATE | Format: `Y-m-d` |
| `pickup_time` | VARCHAR(10) | Format: `H:i` |
| `customer_email` | VARCHAR(255) | Billing email |
| `reminder_day_before` | TINYINT(1) | 0 = not sent, 1 = sent |
| `reminder_morning` | TINYINT(1) | 0 = not sent, 1 = sent |
| `change_requested` | TINYINT(1) | 0 = none, 1 = pending, 2 = resolved |
| `change_request_note` | TEXT | Customer's change request message |
| `created_at` | DATETIME | Auto-set on insert |

---

## Settings

**Option key:** `wclpm_settings`

| Key | Default | Description |
|---|---|---|
| `from_email` | WP admin email | Sender email for all plugin emails |
| `from_name` | Site name | Sender name |
| `reminder_day_before_subject` | `Pickup Reminder: ... TOMORROW` | Tokens: `{date}` `{time}` `{order_number}` `{customer_name}` |
| `reminder_morning_subject` | `Pickup Reminder: ... TODAY` | Same tokens |
| `ready_for_pickup_subject` | `Your order #{order_number} is ready!` | Same tokens |
| `logo_url` | _(blank)_ | Email header logo — falls back to WP site logo, or omitted |
| `store_address` | _(blank)_ | Email footer address line — omitted when blank |
| `reminder_send_time` | `08:00` | Daily cron fire time (HH:MM), uses WP timezone |
| `default_slot_capacity` | `5` | Max orders per 15-min slot (ACF per-location field overrides this) |
| `booking_window_days` | `90` | How far ahead customers can book |
| `allow_change_requests` | `true` | Show change request form on order page |
| `change_cutoff_hours` | `24` | Block requests within N hours of pickup (0 = always allow) |
| `crm_api_url` | _(blank)_ | Full CRM API URL (incl. query params). Blank = feature disabled |
| `crm_api_key` | _(blank)_ | Sent as `X-Api-Key` header. Blank = unauthenticated request |
| `crm_group_label` | `Organization Affiliation` | Label for the affiliation dropdown at checkout |

---

## External Dependencies

| Dependency | Required | Notes |
|---|---|---|
| WooCommerce 7.0+ | Yes | Hard requirement — plugin deactivates without it |
| ACF Pro | Yes | Repeater + relationship fields used throughout |
| WP SMTP | Recommended | Needed for reliable email delivery |
| CRM API | Optional | Any JSON API returning `{"list":[{"id":"…","name":"…"}]}` — hidden when URL is blank |

---

## ACF Field Groups

Both groups are registered programmatically in `WCLPM_ACF_Fields` — no import needed.

**Pickup Location CPT (`pickup_location`):**
- `location_address` (text) — shown at checkout and in emails
- `location_capacity` (number) — per-location slot capacity override
- `lead_time_hours` (number) — minimum advance booking notice
- `default_weekly_hours` (repeater → `day_of_week`, `default_time_ranges` → `start_time`/`end_time`)
- `pickup_schedule` (repeater → `schedule_dates` → `schedule_date`, `schedule_time_ranges` → `start_time`/`end_time`)
- `closed_dates` (repeater → `closed_date`, `closed_reason`)

**Date/time return formats:** date pickers use `Ymd`; time pickers use `H:i`; datetime pickers use `Y-m-d H:i:s`.

**Products (`product`):**
- `pickup_only` (true/false) — forces local pickup shipping
- `available_pickup_locations` (relationship → `pickup_location`) — shown when `pickup_only` is on
- `pickup_start_date` / `pickup_end_date` (date, `Ymd`) — shown when `pickup_only` is on
- `availability_start_date` / `availability_end_date` (datetime, `Y-m-d H:i:s`)
- `expires_after_end_date` (true/false) — shown when `availability_end_date` is set; one-time vs recurring

---

## Order Meta Keys

| Key | Value |
|---|---|
| `_pickup_selections` | Serialized array: `location_id`, `location_name`, `location_address`, `date`, `date_display`, `time`, `time_display`, `products` |
| `_church_affiliation_id` | CRM group/account ID |
| `_church_affiliation_name` | CRM group display name |
| `_has_alternate_pickup` | `yes` or `no` |
| `_alternate_pickup_name` | Text |
| `_alternate_pickup_phone` | Text |
| `_alternate_pickup_email` | Email |

---

## WP Cron Jobs

| Hook | Schedule | Purpose |
|---|---|---|
| `send_pickup_day_before_reminders` | Daily at `reminder_send_time` | Sends reminder for tomorrow's pickups |
| `send_pickup_morning_reminders` | Daily at `reminder_send_time` | Sends reminder for today's pickups |

Crons are scheduled on activation and rescheduled automatically when `reminder_send_time` is saved.
Both use a transient lock to prevent duplicate sends on concurrent runs.

---

## Constants

| Constant | Value |
|---|---|
| `WCLPM_VERSION` | `1.0.0` |
| `WCLPM_PLUGIN_FILE` | Absolute path to main plugin file |
| `WCLPM_PATH` | Plugin directory path (trailing slash) |
| `WCLPM_URL` | Plugin directory URL (trailing slash) |

---

## Known Issues / Future Improvements

- `class-order-confirmation.php` hosts 4 classes — consider splitting into separate files
- No unit tests
- No `uninstall.php` to clean up DB table and options on plugin deletion
- Move `Alternate Pickup Person` to `Pickup Details` to avoid confusion