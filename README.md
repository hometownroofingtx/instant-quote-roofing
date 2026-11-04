# Hometown Instant Roof Report

A WordPress plugin for collecting roof-report requests, measuring roofs through Google Solar, calculating estimated price ranges, and sending leads to GoHighLevel through n8n.

**Version:** 1.0.8

**Requires:** WordPress 6.0+ and PHP 7.4+

## Installation

1. Download this repository and place its contents in `wp-content/plugins/htr-instant-roof-report/`.
2. Activate **Hometown Instant Roof Report** in WordPress.
3. Open **Roof Reports → Settings** and configure your integrations and customer information.
4. Review **Roof Reports → Pricing** before accepting customer requests. Bundled rates are starting values, not verified business pricing.
5. Add the shortcode below to the page that should display the customer form.

```text
[htr_roof_report]
```

Activation creates the report and log tables and schedules lead retries. No Node build or Composer installation is needed for the checked-in plugin assets.

## Initial setup

### Maps and roof measurements

Configure the two Google keys under **Settings → Maps & roof measurements**:

| Setting | Purpose |
| --- | --- |
| Google browser key | Maps, address search, geocoding, and static maps used by the customer interface. This key is exposed to the browser by design. |
| Google Solar server key | Server-side roof measurement requests. |
| Preferred imagery quality | Starts with Medium or High imagery, then tries the configured fallback sequence. |

Restrict the browser key to your website and the server key to the intended API. Confirm API access, quotas, and billing in your own Google Cloud project; the plugin does not establish that configuration for you.

Use **Test Google Solar** with property coordinates to test the saved server key. Save key changes before testing. The test may use the measurement cache; inspect Logs to see whether a live request occurred.

### Lead delivery

Configure the n8n webhook URL, shared signing secret, delivery alert email, and GHL form ID under **Settings → Lead delivery**.

The plugin posts JSON to n8n with these headers:

| Header | Value |
| --- | --- |
| `Content-Type` | `application/json` |
| `X-HTR-Key` | The saved shared secret. |
| `X-HTR-Signature` | Hex HMAC-SHA256 of the exact JSON body using the shared secret. |
| `X-HTR-Report` | The report ID. |

Configure the workflow to authenticate the request and create or update the GHL contact. Use an HTTPS webhook URL. The payload includes customer details, address, roof type, measurements, price range, interests, SMS consent selections, report link, and service-area information. The complete payload contract is defined in `HTR_RR_Leads::payload()` in `includes/class-leads.php`.

**A Sent status means the webhook returned HTTP 2xx. It does not independently verify that the n8n workflow completed delivery to GHL.** Confirm the downstream workflow and contact separately.

### Customer information and limits

- Set the customer-facing company phone number and estimate disclaimer.
- Review both SMS consent statements against your GHL form and campaign wording.
- Set the number of reports allowed per visitor per hour; the request handler enforces this using an IP-based hash.
- Blank server-key and shared-secret fields retain their previously saved values.

## Pricing export and import

Open **Roof Reports > Pricing > Export / import pricing**.

1. Save any pending pricing edits, then click **Export saved pricing** to download a JSON backup.
2. To restore or transfer pricing, select an exported JSON file (maximum 1 MB), check **Replace current pricing with this file**, and click **Import and replace pricing**.

Import replaces roof types (including custom keys), service areas, service radius, waste, pitch bands, and complexity factors. Export a backup first. The entire document is validated before saving; an invalid or unsupported file leaves settings unchanged. Only administrators with `manage_options` can export or import, and both actions require a valid WordPress nonce.

If an import fails, the error identifies the section to fix. Radius and roof-face limits must be whole numbers, pitch limits must be unique, and all built-in roof types must remain in the file. JSON numbers must be numbers rather than quoted strings. UTF-8 files with a byte-order mark are accepted.

API keys, webhook credentials, customer text, and existing reports are not included or changed. Media files and attachment IDs are not transferred: matching roof types retain their destination-site images, and new roof types use the default image fallback until you choose an image locally. Imported pricing applies to future estimates. The format is `htr-rr-pricing`, version `1`.

## Customer experience

### Request source tags and calculator map

New reports save a server-selected `request_source`: `calculator` or `instant_roof`. Reports and Overview show **Calculator** or **Instant Roof** in a dedicated **Source** column; report details and the overall CSV also identify the source. Schema version 4 adds the source column automatically. Existing reports remain intact, and older calculator reports are identified from their saved customer-entered measurement source.

On the Reports screen, choose **All sources**, **Calculator**, or **Instant Roof** beside the status tabs and click **Filter**. Source and status filters combine, and switching status tabs keeps the selected source. Older reports use the same source fallback as their labels. The overall CSV still exports all sources and statuses.

The n8n webhook keeps its existing `event: roof_report` and adds `request_source` plus `tags`: `["Calculator"]` or `["Instant Roof Request"]`. Map this array into your GHL contact-tag action in n8n, appending to the contact's existing tags. WordPress sending this field does not confirm that the workflow has applied it in GHL. Retries and manual resends derive the same source/tag from the saved report; already-sent contacts are not automatically retagged.

After selecting a full street address, the public calculator displays a full-width satellite map, Satellite/Map switches, and a draggable pin. Moving the pin updates coordinates used for the saved report and nearest-area pricing. The calculator does not request aerial roof measurements or display a roof-details sidebar. Pricing uses the dimensions the customer enters, and the estimate stays hidden until contact submission succeeds. The map remains responsive on smaller screens.

The customer enters an address, confirms the property on a satellite map, chooses a roof type, provides contact details and consent selections, and receives a report with measurements or an explanation that review is needed.

Report links use a random token:

```text
https://your-site.example/?htr_roof_report=<token>
```

The standalone report supports printing or saving as PDF through the browser. It sends no-cache headers and a `noindex, nofollow` robots header. Anyone with the report link can open it; share it with the intended recipient.

Customers can select **Download report (PDF)** on the results screen to open the report in a new tab and launch the print dialog. Choose **Save as PDF** to save a copy. The standalone report also has a **Download report** button for opening the dialog again. The print layout hides the toolbar, repeats roof-face table headers on subsequent pages, and keeps measurement cards and diagrams together where space permits. The browser controls the PDF filename and save destination; this is a browser PDF workflow rather than a server-generated attachment.

The calculator also displays **Download report (PDF)** after the customer successfully submits their details and the estimate is saved.

Administrators can select **Download report** beside **View** in the Reports table or from the report detail page's action bar. The Overview's recent reports use the same table action. Each opens the matching customer report's print dialog; choose **Save as PDF** to download it.

Use **Download overall report (CSV)** above the Reports table to export all reports across every status, including records beyond the newest 200 displayed. This administrator-only, nonce-protected spreadsheet contains customer/contact details, property information, measurements, estimate ranges, and lead delivery status. It excludes report access tokens, integration secrets, and raw measurement payloads. Rows are read in batches, and spreadsheet formula prefixes in customer-entered values are escaped. The current table status filter does not restrict the overall export.

## Admin pages

All Roof Reports admin pages require the `manage_options` capability.

| Page | Features |
| --- | --- |
| Overview | Report totals, review counts, estimated value, saved integration configuration, recent reports, and recorded errors. Configuration badges are not live connection tests. |
| Reports | Status filters and the newest 200 matching reports. |
| Report details | Customer and property information, measurements, saved estimate breakdown, delivery attempts, review notes, technical details, and report activity. Includes customer-report and resend actions. |
| Pricing | Roof types, material images, service towns, rates, regional adjustments, waste allowance, pitch bands, and complexity factors. |
| Calculator | Preview estimates using the saved pricing settings without creating a customer report. |
| Settings | Integration credentials, customer messaging, SMS consent, usage limits, and the Solar test form. |
| Logs | The latest 300 entries, including measurement and webhook activity, HTTP responses, duration, and links to related reports. |

### Roof types and images

Click **Add roof type**, enter the name and rates, and select whether to show an automatic price. Existing material keys remain stable when labels change. New types receive a generated unique key.

Use the image picker to select or upload a Media Library image. After **Save pricing**, the customer form displays the image on its roof-type card. Types without images retain a CSS swatch. Removing the image restores that fallback. Newly added, unsaved rows can be removed using the trash icon.

### Service areas

Click **Add service area** and enter the town, latitude, longitude, and regional multiplier. Coordinates are validated on save. A multiplier of `1.00` preserves base rates; `1.10` applies a 10% increase.

The current report workflow measures addresses outside the service radius and flags outside-area leads. The radius should not be treated as an absolute submission block.

### Estimate calculation

```text
Roof area × (1 + waste percentage / 100)
          × material rate
          × pitch multiplier
          × complexity multiplier
          × regional multiplier
```

Low and high totals use their respective material rates and are rounded to the nearest $25. Roof types without automatic pricing return an on-site quote message. Estimates are not final quotations. Existing reports retain their saved estimate data when settings change.

## Background jobs and caching

- Lead retry hook: `htr_rr_retry_leads`.
- Schedule: every 15 minutes through WP-Cron.
- Each run processes up to 20 pending leads.
- Delivery stops after 12 total attempts and marks the lead failed. If configured, an alert email is attempted.
- WP-Cron depends on site traffic, so elapsed retry time is not guaranteed. Use a server cron runner if your deployment requires predictable execution.
- Manual resend resets the delivery attempt count and sends the lead again.
- Successful measurements and no-coverage responses are cached for six hours per coordinate location. Authentication and quota errors are not cached.

## Storage and request handling

The plugin stores settings in the `htr_rr_settings` option. Report and log tables use the WordPress prefix followed by `htr_rr_reports` and `htr_rr_log`.

The frontend primarily uses WordPress AJAX actions `htr_rr_preview`, `htr_rr_report`, and `htr_rr_email`. REST alternatives are also registered:

```text
POST /wp-json/htr-rr/v1/report
POST /wp-json/htr-rr/v1/email
```

Public request handlers validate inputs and apply their existing anti-abuse checks. Server credentials are excluded from public configuration. Protect WordPress database access and backups because reports contain customer data and settings include integration credentials.

Deactivation clears the retry job. Uninstall also clears the job but intentionally retains settings, reports, and logs. Uninstalling is not a customer-data deletion operation.

## Local validation

From the plugin directory:

```sh
php tests/pricing-test.php
php -l includes/class-admin.php
php -l includes/class-settings.php
```

The isolated pricing suite currently contains 24 checks covering custom roof types, stable keys, rates, regional areas, coordinate validation, estimator compatibility, and material-image settings. It uses local doubles and makes no live API calls or database changes.

Local checks do not establish live Google coverage, browser rendering, n8n/GHL delivery, email delivery, cron timing, or cache behavior. Verify those in the deployed environment before relying on production submissions.

## Project layout

```text
htr-instant-roof-report.php  Plugin bootstrap
includes/                    Admin, settings, storage, APIs, measurements, estimates and leads
templates/                   Customer form and standalone report
assets/css/                  Admin and frontend styles
assets/js/                   Customer flow, roof diagram and pricing editor
assets/img/                  Branding assets
tests/                       Isolated pricing checks
docs/                        Change explanations
```

See [Settings page redesign](docs/settings-page-redesign.md) for the current branch's UI changes and validation notes.
# Standalone address search

Place this shortcode on a landing page, replacing the example URL with your actual request page:

```text
[htr_roof_report_search redirect="/instant-roof-report/"]
```

The destination page must contain `[htr_roof_report]`. After the visitor selects a full street address from Google suggestions and clicks Get My Roof Report, the search redirects to the request page. The report page retrieves the selected Google Place and opens property confirmation with the map and roof preview. No report or lead is submitted until the visitor completes the existing report form. Without a selected address, the regular report form starts as usual.

This uses the existing Google browser key and Places API configuration. The redirect must be a local site URL. Search widgets can appear more than once on a page. JavaScript is required. Verify Google autocomplete and the destination map on the live WordPress site after deployment.

## Public roof calculator

```text
[htr_roof_calculator]
```

Uses the backend Calculator design: roof details on the left and an address/contact form in the right preview panel. No prices appear until the visitor submits valid contact information and a selected address. The saved lead response reveals the price range, low/high estimates and breakdown in the right panel. There is no anonymous calculator preview endpoint. Inputs are locked after success to keep the displayed estimate consistent with the saved request; refresh to start another request. Calculations use the existing server-side estimator and saved Pricing settings. Enter whole-number dimensions: area 100–100,000 sq ft, pitch 0–24 and faces 1–100. Roof types without automatic pricing show an on-site quote message.

The combined contact section uses Google address suggestions and the existing first/last name, email, US phone, interests and optional SMS preferences. Selecting an address makes its nearest service area authoritative for both preview and submitted pricing. Submit requests use the existing report validation, honeypot, visitor limits, report storage and n8n → GHL delivery/retry flow. The Google browser key and n8n credentials still come from Settings; this does not embed a GHL iframe.

Calculator dimensions are customer-entered, not Google Solar measurements. Reports are always marked for review, identify their manual source, and do not render measured roof diagrams. Submitted prices are ignored; the server recalculates using current saved settings. The original report and standalone search shortcodes remain available.

Validation: `php tests/calculator-test.php` runs isolated pricing, invalid-input, lead-pipeline, consent and original Solar-path checks without external calls. Verify Google suggestions, responsive appearance and real n8n/GHL delivery separately on the target site.
