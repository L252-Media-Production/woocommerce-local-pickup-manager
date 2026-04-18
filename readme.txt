=== WooCommerce Local Pickup Manager ===
Contributors: officialJCReyes, darwinini
Tags: woocommerce, local pickup, scheduling, shipping, time slots
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced local pickup scheduling for WooCommerce — slot availability, reminder emails, and order workflow.

== Description ==

WooCommerce Local Pickup Manager replaces the plain "local pickup" option at checkout with a full scheduling experience. Customers choose a specific pickup location, date, and time slot, with real-time availability based on per-location schedules and capacity limits.

**Features**

* Slot-based pickup scheduling — 15-minute slots with configurable capacity per location
* Live availability calendar at checkout with real-time slot counts
* Per-location weekly schedules, specific date overrides, and closed dates
* Automated day-before and morning-of reminder emails via WP Cron
* Custom "Ready for Pickup" order status with customer email notification
* Pickup change request system — customers submit requests from their order page, reviewed in WP Admin
* Seasonal product availability — restrict products to date ranges (recurring or one-time)
* Alternate pickup person — customers can designate someone else to collect their order
* Optional CRM group affiliation dropdown at checkout (hidden when not configured)
* Mixed cart prevention — pickup-only products cannot be combined with shippable products
* Admin settings panel under WooCommerce for email branding, slot capacity, booking window, and more

**ACF Pro — Currently Required (Removal In Progress)**

ACF Pro is currently required. Pickup location and product fields are managed through ACF's repeater and relationship UI, registered programmatically with no JSON import needed.

Work is in progress to replace the ACF dependency with native WordPress meta boxes. Once complete, ACF Pro will become optional — when active it will continue to provide its polished UI; when absent, the plugin will fall back to native meta boxes automatically.

**Elementor Pro — Currently Required (Removal In Progress)**

Elementor Pro is currently required for the seasonal availability feature, which hides the Elementor add-to-cart widget for out-of-season products and replaces it with an availability message.

Work is in progress to implement this via standard WooCommerce hooks so all themes are supported. Once complete, Elementor Pro will become optional.

== Installation ==

1. Upload the `woocommerce-local-pickup-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Shipping**, open your shipping zone, and add **Local Pickup (Manager)** as a method.
4. Create pickup locations under **WooCommerce → Pickup Locations**.
5. Configure plugin settings under **WooCommerce → Pickup Manager**.

== Frequently Asked Questions ==

= Does this plugin require ACF Pro? =

Yes, currently. ACF Pro is required in the current release for the field UI on pickup locations and products. Work is in progress to remove this dependency — a future release will fall back to native WordPress meta boxes automatically when ACF Pro is not active.

= Does this plugin require Elementor Pro? =

Yes, currently. Elementor Pro is required in the current release for the seasonal availability feature. Work is in progress to remove this dependency so the feature will work with all themes via standard WooCommerce hooks.

= How are pickup slots generated? =

Slots are generated in 15-minute increments from the time ranges configured on each pickup location. The number of available spots per slot is determined by the per-location capacity setting, falling back to the global default in plugin settings.

= Can I restrict which products are available for pickup? =

Yes. On any product you can enable "Pickup Only" to force local pickup as the only shipping method, and select which pickup locations carry that product.

= How do reminder emails work? =

Two WP Cron jobs run daily at the configured send time. One sends reminders for pickups scheduled for the following day; the other sends reminders for pickups happening the same day. Reminder subjects are configurable in plugin settings and support tokens: `{date}`, `{time}`, `{order_number}`, `{customer_name}`.

= Can I use a CRM for group affiliation at checkout? =

Yes. Enter any API URL that returns `{"list":[{"id":"...","name":"..."}]}` in the CRM settings. An affiliation dropdown will appear at checkout. Leave the URL blank to hide the field entirely.

= Will this conflict with WooCommerce's built-in local pickup? =

The plugin registers its own shipping method for use in shipping zones and automatically suppresses WooCommerce's blocks-based local pickup rates to prevent duplicates at checkout.

== Screenshots ==

1. Checkout — full Pickup Details panel showing location selector, availability calendar, time slot picker, and Alternate Pickup Person toggle
2. Checkout — calendar with available dates highlighted and time slot dropdown open showing remaining spots per slot
3. Checkout — Alternate Pickup Person section expanded with full name, phone number, and email address fields
4. Product admin — Pickup Settings meta box: Pickup Only toggle, available location assignment, and pickup booking window dates
5. Pickup Location admin — basic settings (address, capacity, lead time) and Default Weekly Hours repeater
6. Pickup Location admin — Specific Date Schedule overrides and Closed Dates with optional reason field
7. Pickup Manager Settings — Email Sender, Email Subject Lines with token reference, and Reminder Timing
8. Pickup Manager Settings — Slot Availability (default capacity and booking window)
9. Pickup Manager Settings — Change Requests cutoff, Email Branding (logo and store address), and CRM Integration

== Changelog ==

= 1.1.3 =
* Enhancement: Change requests in WooCommerce → Change Requests can now be approved or denied — approval supports inline editing of pickup location, date, and time
* Enhancement: Customers receive an email notification when their change request is approved (includes previous and updated pickup details) or denied
* Enhancement: Customer order page now reflects the outcome of their change request (approved, denied, or still pending)
* Fix: Change requests submitted by customers now reliably appear in the admin panel even when no booking row existed for the order
* Fix: Order links on the Change Requests page now work correctly with WooCommerce High-Performance Order Storage (HPOS)

= 1.1.2 =
* Enhancement: Organization affiliation dropdown at checkout now uses Select2 for searchable typeahead filtering
* Fix: Database schema migrations are now applied automatically on plugin update — new columns are added without requiring plugin reactivation

= 1.1.1 =
* Security: CRM API key is now masked in the admin settings page — only the last 4 characters are visible after saving

= 1.1.0 =
* CRM integration: support multiple API URLs (one per line) — results are merged, deduplicated, and sorted alphabetically
* CRM integration: automatic pagination loops through all pages until every record is retrieved
* CRM integration: configurable items-per-page, offset parameter name, limit parameter name, and response list key — works with any JSON API, not just EspoCRM

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.3 =
Change request workflow overhauled — admins can now approve or deny requests with inline pickup editing and automatic customer email notification.

= 1.1.2 =
Organization affiliation dropdown now supports searchable filtering. Database schema migrations are applied automatically on update.

= 1.1.1 =
Security patch: the CRM API key is now masked in admin settings.

= 1.1.0 =
CRM integration now supports multiple API URLs and automatic pagination to retrieve more than the per-request limit.

= 1.0.0 =
Initial release.
