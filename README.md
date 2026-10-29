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

## Customer experience

The customer enters an address, confirms the property on a satellite map, chooses a roof type, provides contact details and consent selections, and receives a report with measurements or an explanation that review is needed.

Report links use a random token:

```text
https://your-site.example/?htr_roof_report=<token>
```

The standalone report supports printing or saving as PDF through the browser. It sends no-cache headers and a `noindex, nofollow` robots header. Anyone with the report link can open it; share it with the intended recipient.

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
