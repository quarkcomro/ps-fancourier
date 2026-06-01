# rc_fancourier — Usage Manual

## Overview

**rc_fancourier** is a commercial PrestaShop carrier module for Fan Courier (Romania). It integrates with the Fan Courier REST API to:

- Calculate shipping costs at checkout (via API tariff query or local price tables)
- Generate AWBs (Air Waybills / shipping labels) from the back-office order page or in bulk from the orders list
- Display a city selector at checkout (with Select2 autocomplete)
- Support FANbox locker delivery (customers choose a locker location at checkout)
- Track shipment status and automatically advance PrestaShop order states based on Fan Courier delivery events
- Show a Return AWB button for reverse logistics
- Present an AWB Status Dashboard on the module configuration page

## Requirements

- **PrestaShop**: 1.5 – 9.x
- **PHP**: 5.6+ (PHP 7.4+ recommended for PS 8+)
- **Fan Courier account**: Active commercial account with API access
  - API username and password
  - At least one client ID (depot/branch ID)
- **cURL** PHP extension (for API calls)

## Installation

1. Upload the `rc_fancourier` folder to your PrestaShop `modules/` directory.
2. Go to **Back Office → Modules → Module Manager**.
3. Search for "Fan Courier" and click **Install**.
4. After installation you will be redirected to the module configuration page.
5. Enter your Fan Courier API credentials and click **Save**.

The module creates the following database tables on install:

| Table | Purpose |
|-------|---------|
| `ps_rc_fancourier_cities` | Romanian locality list for city selector |
| `ps_rc_fancourier_offices` | Fan Courier offices, FANboxes, and PayPoint locations |
| `ps_rc_fancourier_awbs` | AWBs generated per order |
| `ps_rc_fancourier_locker_cart` | FANbox locker chosen during checkout |
| `ps_rc_fancourier_locker_order` | FANbox locker associated with a placed order |

## Configuration

Open **Back Office → Modules → Module Manager → Fan Courier → Configure**.

### API Credentials

| Field | Description |
|-------|-------------|
| Username | Your Fan Courier API username |
| Password | Your Fan Courier API password |

These credentials are used for every API call (tariff calculation, AWB generation, tracking). Without valid credentials the module falls back to local pricing for cost calculation, and AWB generation will not work.

### Shipping Cost Calculation Method (`RC_FANCOURIER_METHOD`)

Two modes are available:

- **API** — queries the Fan Courier tariff API at checkout to get the real shipping price for the customer's address, weight, and parcel count.
- **Local** — uses price groups/tables defined in the module configuration (useful when API access is slow or unavailable).

For most shops, API mode is recommended to show accurate prices.

### Local Pricing Setup (when `method = local`)

In the **Services** tab of the configuration page you can define per-carrier, per-zone price tables. Each entry maps a county (judet) to a base rate and a per-kg rate. Weight thresholds and price groups per customer group are supported.

### Package Defaults

| Setting | Description |
|---------|-------------|
| `RC_FANCOURIER_DEFAULT_WEIGHT` | Override total weight (kg) for all carts — useful when products have no defined weight |
| `RC_FANCOURIER_DEFAULT_WIDTH` | Default package width (cm) |
| `RC_FANCOURIER_DEFAULT_HEIGHT` | Default package height (cm) |
| `RC_FANCOURIER_DEFAULT_LENGTH` | Default package length (cm) |
| `RC_FANCOURIER_SHOW_DIM` | Show/hide dimension fields on the AWB generation form in the order panel |
| `RC_FANCOURIER_OBSERVATIONS` | Default text pre-filled in the Observations field |
| `RC_FANCOURIER_RESTITUTION` | Default value for the Restitution field |
| `RC_FANCOURIER_COD_WHO_PAYS` | Default "Who pays COD tax" (`expeditor`/`destinatar`) |
| `RC_FANCOURIER_DLV_WHO_PAYS` | Default "Who pays delivery tax" (`expeditor`/`destinatar`) |
| `RC_FANCOURIER_INCLUDE_DECLARED` | Include declared value (order total) in the AWB |

### Free Shipping Setup

Free shipping thresholds can be configured globally, per customer group, and per carrier service:

| Setting | Description |
|---------|-------------|
| `RC_FANCOURIER_FREE_FROM` | Cart total (RON) above which shipping is free (global) |
| `RC_FANCOURIER_FREE_GROUPS` | JSON-encoded per-group free-shipping thresholds |
| `RC_FANCOURIER_NO_FREESHIPPING` | Comma-separated customer group IDs excluded from free shipping |
| `RC_FANCOURIER_API_FS_LIMIT` | Safety limit (RON): do not apply free shipping when API tariff exceeds this value |
| `RC_FANCOURIER_ADDITIONAL_BHVR` | How to handle PS carrier additional cost:<br>`do_not_add` — ignore it<br>`add_no_free_shipping` — add it and cancel free shipping<br>`add_no_free_shipping_whole_amount` — always add it, always cancel free shipping |
| `RC_FANCOURIER_API_MULTIPLICATOR` | Multiply the API tariff by this factor (e.g. `1.2` for a 20% markup) |

### City Selector (`RC_FANCOURIER_CITY_SELECTOR`)

When enabled, the checkout address form and the back-office address form show an autocomplete dropdown (powered by Select2) that lets customers choose their city from the Fan Courier locality database. This ensures the city name is spelled exactly as Fan Courier expects.

Enable Select2 (`RC_FANCOURIER_SELECT2`) to use the autocomplete widget instead of a plain dropdown.

### Multi-Location / Multi-Depot Setup

Fan Courier allows multiple client IDs (one per depot/branch). Go to the **Locations** tab in the module configuration:

1. Enter your credentials and click **Retrieve Locations from API** to fetch your account's branches automatically.
2. Or add locations manually.
3. Each carrier you create in PrestaShop can be associated with a specific depot by selecting it in the Location field.

When generating an AWB for an order, the location (client ID) from the AWB form is passed to the API. The module picks the location associated with the order's carrier by default.

## Order Management

### Generating AWBs

1. Open **Back Office → Orders → Orders**.
2. Click an order that uses a Fan Courier carrier.
3. Scroll down to the **Fan Courier AWB** panel.
4. Review/adjust the pre-filled fields:
   - **Service** — Fan Courier service name (Standard, RedCode, FANbox, etc.)
   - **Location** — your depot/branch (client ID)
   - **Who pays COD tax / delivery tax** — `expeditor` (sender) or `destinatar` (recipient)
   - **Envelopes / Boxes** — parcel count
   - **Weight** — automatically calculated from product weights; override if needed
   - **Contact person, Declared value, COD value, Observations, Content**
   - **Dimensions** — length × width × height (cm); displayed only when `RC_FANCOURIER_SHOW_DIM` is on
   - **Options** — additional services (open on delivery, oPOD, Saturday delivery, ePOD)
   - **Shipping method** — delivery to address, or to a Fan Courier office/FANbox
5. Click **Generate AWB**.
6. On success the AWB number appears in the AWBs list above the form with Delete / Show AWB / Show Status buttons.

### Volumetric Weight Auto-Calculation (v2.5.0)

When dimensions are provided (and `RC_FANCOURIER_SHOW_DIM` is enabled), the module automatically calculates volumetric weight:

```
volumetric_weight = (length × width × height) / 5000
billed_weight = max(actual_weight, volumetric_weight)
```

This applies to both single AWB generation (order panel) and bulk AWB generation. No configuration is required — the calculation is automatic whenever all three dimension fields are non-zero.

### Return AWB Generation (v2.5.0)

The **Generate Return AWB** button next to the Generate AWB button pre-fills the form with the current delivery address values highlighted in yellow, and adds a warning banner at the top of the form.

Workflow:

1. Open the order and scroll to the Fan Courier AWB panel.
2. Click **Generate Return AWB**.
3. A confirmation dialog shows the current delivery address.
4. Click OK. Address fields are highlighted in yellow with an orange border.
5. A warning banner at the top of the form reminds you this is a return shipment.
6. Review the pre-filled address — modify any field as needed.
7. Click **Generate AWB** to submit the return AWB to Fan Courier.

No data is sent to the API until the operator clicks Generate AWB after reviewing the form.

### AWB Status Dashboard (v2.5.0)

At the bottom of the module configuration page there is an **AWB Status Dashboard** panel showing the 50 most recently generated AWBs with:

- AWB number
- Order ID and reference (clickable link to the order)
- Customer name (from the delivery address)
- Current PrestaShop order status
- Date added

This panel is read-only and is refreshed each time the configuration page loads.

### Printing Labels

After generating an AWB, click **Show AWB** next to the AWB number to open/download the PDF label from Fan Courier.

### Tracking Status

Click **Show Status** next to an AWB to query the current Fan Courier delivery status for that parcel. The status is fetched live from the API and displayed in a popup.

## Checkout

### City Selector

When `RC_FANCOURIER_CITY_SELECTOR` is enabled, the checkout address form includes an autocomplete field for selecting the city. The Select2 widget (`RC_FANCOURIER_SELECT2`) enhances this with a searchable dropdown.

The city data comes from the `ps_rc_fancourier_cities` table, which is populated from the Fan Courier locality database. You can refresh this list from the **Locations** tab in the module configuration.

### FANbox Locker Selection

For carriers configured with the FANbox service, a locker selector map/list is shown at the carrier selection step of checkout. The customer selects their preferred FANbox locker. The selection is stored in `ps_rc_fancourier_locker_cart` and copied to `ps_rc_fancourier_locker_order` when the order is placed.

When generating an AWB for such an order, the module automatically detects the FANbox locker and pre-fills the office/locker fields.

## Cron / Automated Tracking

### Setting Up the Cron Job

The module provides a cron endpoint that queries Fan Courier for the status of all recent AWBs and automatically advances PrestaShop order states based on delivery events.

The cron URL is displayed in the **Delivery States** tab of the module configuration page. It looks like:

```
https://your-shop.com/module/rc_fancourier/checkstates?rc_fancourier_token=<TOKEN>
```

Add this URL to your server's cron scheduler. A frequency of every 30–60 minutes is typical:

```cron
*/30 * * * * curl -s "https://your-shop.com/module/rc_fancourier/checkstates?rc_fancourier_token=<TOKEN>" > /dev/null
```

The token is derived from the shop's encryption key and does not change unless the key changes.

### What the Cron Does

1. Queries Fan Courier's `reports/awb/tracking` API for all AWBs added within the last `RC_FANCOURIER_OS_DAYS` days.
2. Matches each Fan Courier delivery event code (e.g. `S2` = Delivered, `S3` = Notified, `C0` = Picked up) against the mapping table defined in **Delivery States** configuration (`RC_FANCOURIER_OS_MAP_<event_code>`).
3. For orders where all parcels share the same delivery event, advances the PrestaShop order state to the mapped state.
4. Optionally checks COD transfer reports and maps those to an order state as well (`RC_FANCOURIER_OS_MAP_TRANSFER`).
5. Orders in states listed in `RC_FANCOURIER_OS_IGNORE` (comma-separated order state IDs) are skipped.

### Email Notification on AWB Generation

When `RC_FANCOURIER_UPDATETRACKING` is enabled, generating an AWB:
- Sets the tracking number on the PrestaShop order and order carrier.
- Optionally sends a "Package in transit" email (`RC_FANCOURIER_UPDTRACKING_EMAIL`) with the tracking link and product thumbnails.

## Troubleshooting

### AWB Generation Fails with API Error

- Verify your API credentials in the **Primary Settings** tab.
- Make sure the selected client ID (Location) is valid for your account.
- Check the module's log directory (`modules/rc_fancourier/log/`) for the raw API response.
- Ensure your server can reach `api.fancourier.ro` on port 443 (HTTPS).

### Shipping Cost Not Showing at Checkout

- Confirm the carrier is active in **Shipping → Carriers**.
- Check that `RC_FANCOURIER_ACTIVE` is enabled.
- In API mode: verify that API credentials are correct and `api.fancourier.ro` is reachable.
- In local mode: verify that price groups are defined for the customer's county.
- If no address has been entered yet, the module may return `false` (carrier hidden) — this is expected.

### City Selector Not Working

- Confirm `RC_FANCOURIER_CITY_SELECTOR` is enabled.
- Check that the `ps_rc_fancourier_cities` table is populated (run an update from the Locations tab).
- Check the browser console for JavaScript errors on the checkout page.

### FANbox Locker Not Saved

- Verify the `ps_rc_fancourier_locker_cart` and `ps_rc_fancourier_locker_order` tables exist (they are created on install; check for upgrade issues).
- Confirm the carrier service name contains "FANBOX" or "FAN BOX" (case-insensitive).

### Cron Not Updating Order States

- Check that the cron URL is correct and includes the token parameter.
- Verify `RC_FANCOURIER_OS_DAYS` is greater than 0.
- Ensure the delivery event codes returned by the API are mapped to order states in the **Delivery States** tab.
- Orders in states listed in `RC_FANCOURIER_OS_IGNORE` will not be updated — check if the order is in one of those states.

### Bulk AWB Generation Skips Some Orders

Bulk AWB generation skips orders that already have an AWB in `ps_rc_fancourier_awbs`. To regenerate, first delete the existing AWB from the order panel.

## Changelog

See [CHANGES.md](CHANGES.md) for the full version history.

Current version: **2.5.0**
