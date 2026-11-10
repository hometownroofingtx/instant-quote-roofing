# Hometown Instant Roof Report

A WordPress plugin for collecting roof-report requests, measuring roofs through Google Solar, calculating estimated price ranges, and sending leads to GoHighLevel through n8n.

**Version:** 1.2.0

## Current release: 1.2.0 (5 November 2026)

- Six-step Instant Roof Quote with compact styling, aligned progress indicators, light address search, image roof choices, and responsive sidebar.
- Calculator now asks for roof type and address, previews aerial measurements, then shows the contact form and estimate. Customers do not need to know their roof size.
- Confirm/Continue waits for the measurement preview to finish, including after the map pin moves. Unavailable imagery produces an inspection message rather than an invented price.
- Backend design controls for calculator and quote content/images, with compact grouped quote fields. Material images remain under Pricing; GHL form fields are edited in GHL.
- GHL success/contact messages are paired before saving. Temporary drafts create no Reports rows until contact finalization. Contact saves retry safely without requiring n8n.
- US country prefill, alternate phone payload names, and international phone numbers with country codes. Missing phone data is reported explicitly.
- Retains floating drawers, loaders, pricing transfers, downloads, source filters and per-user pagination.

Schema remains version 4. Existing report data and saved integration settings are preserved. Use this repository release for future deployments; the older 1.1.0 ZIP does not contain these updates. Default contact mode remains Plugin form. Browser-reported GHL success is not independent server verification; the optional authenticated callback remains supported.

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

The main Settings screen uses tabs for Maps & measurements, Lead delivery, Customer information, SMS consent, Usage limits, Floating side tab, and Test Google Solar. Switching tabs preserves unsaved field values; **Save settings** saves the existing settings form across all tabs. The Solar test remains a separate action. Arrow keys, Home and End navigate the tabs, and the last selected tab is remembered for the browser session. Without JavaScript, all sections remain visible.

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

### Floating quote tab

Open **Roof Reports > Floating Side Tab** (also linked from Settings) for a dedicated tabbed editor with an interactive live preview. It is off by default.

- **General:** enable the tab; choose left/right, top/center/bottom, an edge offset, and desktop/tablet/mobile visibility. Layouts include the original vertical quote tab, Icon stack, Icon + label, and Expandable. Expandable opens on hover/keyboard focus or click and closes with Escape or a click outside.
- **Items:** add up to 10 links with labels, one of eight built-in icons, and optional new-tab behavior. Reorder with the up/down arrows. Web, telephone and email links are supported. The original quote layout keeps its destination page, label and dismissible message. Other layouts fall back to that destination if no quick links are configured.
- **Style / Quick Settings:** change icon/text, hover and background colors, border radius, shadow and z-index. Preview desktop/tablet/mobile without saving. Preview links never navigate away.
- **Display Rules:** show across the site or only selected published pages, exclude selected pages, and optionally hide on linked destination pages. Exclusions take priority. Customer reports, feeds and admin pages are always excluded. An empty include-only selection shows nowhere. Mobile is up to 600px, tablet 601-1024px, desktop above 1024px.
- **Import / Export:** JSON downloads contain only floating-tab settings. Imports validate the format before saving and replace only these settings, preserving API keys, GHL setup and pricing. Export a backup first. Page selections store WordPress page IDs; review them after transferring to another site.

The message dismissal lasts for a browser-tab session. The frontend loads only the small tab assets where display rules permit it; it does not load Google Maps or submit leads. Existing quote-tab options are reused. Unsaved preview changes do not affect visitors. Use **Save Changes** to publish the tab configuration.

**Drawer option:** Under General > Quote link action, choose **Slide-out drawer**, then select **Instant Roof Report** or **Roof Calculator**. The quote tab opens that existing form in a panel from the selected side. In other layouts, links matching the configured destination page open the drawer; unrelated links retain navigation. A drawer-only quote tab needs no destination page. The embedded form loads on first open, keeps its inputs when closed/reopened, and uses the existing Plugin form/GHL form configuration. Close with the button, backdrop, or Escape. Mobile uses the full viewport width; reduced-motion preferences skip the slide animation. A new-tab link is available as a fallback. The admin preview shows a drawer placeholder without loading Google or GHL. The standalone embed URL is available only while the tab and drawer mode are enabled, is marked noindex, and does not render the theme header/footer.

Validation: `php tests/floating-admin-test.php --preview` renders an isolated fixture using the actual admin template and tests sanitization/visibility. With Playwright and an installed browser, `node tests/floating-browser-test.js` checks layouts, live preview, item ordering, keyboard controls and mobile overflow. Set `HTR_BROWSER_CHANNEL=msedge` to use installed Edge. These fixtures do not verify a deployed WordPress theme or live customer navigation.

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

Use **Download overall report (CSV)** above the Reports table to export all reports across every status, including records on every page. This administrator-only, nonce-protected spreadsheet contains customer/contact details, property information, measurements, estimate ranges, and lead delivery status. It excludes report access tokens, integration secrets, and raw measurement payloads. Rows are read in batches, and spreadsheet formula prefixes in customer-entered values are escaped. The current table status filter does not restrict the overall export.

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

Edit its sidebar photo and display text under **Roof Reports > Settings > Calculator Design**. Choose images from the WordPress Media Library, then click **Save settings**. **Use default** clears the selected image without deleting it. A blank sidebar image selection uses the roof illustration. The calculator has no outer logo/banner or rounded background. Step headings/descriptions, sidebar heading, four guide items, reassurance copy and footer disclaimer are editable. The same settings apply inside the floating drawer. These controls do not change estimate calculation, measurement inputs or the selected contact mode.

```text
[htr_roof_calculator]
```

Uses a four-step public design: Roof Type, Address, Contact Information (GHL Form when configured), and Your Estimate. Image choices use Pricing material images, excluding Other. Roof dimensions come from server-side Google Solar measurements, not customer entry. The address preview includes area, footprint, faces, pitch and imagery date. Pricing uses the nearest service area and saved estimator settings. The layout includes an editable sidebar and stacks on smaller screens.

In the default Plugin form mode, the contact section uses Google address suggestions and the existing first/last name, email, US phone, interests and optional SMS preferences. Selecting an address makes its nearest service area authoritative for submitted pricing. Submit requests use the existing report validation, honeypot, visitor limits, report storage and n8n → GHL delivery/retry flow. In GHL form mode, the selected GHL form is embedded instead; A submission message from the verified GHL iframe or a server-confirmed n8n link advances the page to the prepared estimate. After the iframe-specific success marker, the browser saves the matching contact details directly to WordPress; n8n is not required for this save. Google and n8n credentials still come from Settings.

Legacy manual calculator API calls remain supported for integrations. The public calculator requests aerial measurement mode and retains Calculator as its request source. Submitted prices are ignored; the server recalculates using saved pricing. Missing imagery shows an on-site quote message.

Validation: `php tests/calculator-test.php` runs isolated pricing, invalid-input, lead-pipeline, consent and original Solar-path checks without external calls. Verify Google suggestions, responsive appearance and real n8n/GHL delivery separately on the target site.

## Admin pagination

Reports and Logs have independent pagination, with 20 items per page by default. Open WordPress **Screen Options** on either list to save your preferred count (1-200) for your own account. Reports retain source and status filters across pages; changing a filter starts on page 1. The overall CSV still exports all reports. Overview recent reports and an individual report's activity history keep their existing behavior.

### GHL contact save without n8n

Prepared GHL reports now include a short-lived, report-specific contact-write capability. The public report response does not expose it. The browser pairs GHL's contact payload with its iframe-specific successful-submission marker, checking the iframe, origin, location and fingerprint, then posts only name, email and phone to `htr_rr_save_contact` (or REST `save_contact`). The existing property, roof and source fields stay on the report. Custom GHL fields and SMS consent are not inferred from the sticky-contact message.

WordPress validates the capability and contact fields, and atomically fills only a report still waiting for its form. Duplicate callbacks cannot overwrite an already-linked contact. The status is **Form submitted (browser)**, distinguishing this path from independently confirmed delivery. It is excluded from automatic outbound lead retries, so saving the contact does not require n8n or send the lead to GHL again. The existing authenticated n8n callback remains available.

The result page shows contact-save progress, retries transient failures, and provides a retry button if the save fails. If GHL does not provide matching contact data, the page explicitly reports that WordPress has not received it. Keep the page open until the save completes. Previously submitted reports without contact data are not automatically backfilled.
Roof-type changes in the Instant Roof GHL flow now reprice the same owned waiting report at the same map location. Rapid selections are serialized, obsolete iframe listeners are removed, and the submitted form is locked against accidental new drafts. Contact-save confirmation remains visible. Repricing cannot alter a submitted report. Existing draft reports are preserved; this does not delete prior test records.

### Submission-only Reports entries

The frontend GHL flow uses `draft_prepare` / `calculator_draft_prepare` to prepare a temporary quote in an expiring WordPress transient (48 hours), not the Reports table. Selecting or repricing a roof type therefore adds no row to Reports, its totals or CSV exports. Successful contact saving promotes the draft into one report with the latest property/roof selection and submitted contact fields. The unique report token prevents retries from creating duplicates. Abandoned temporary drafts expire; existing historical report rows are not removed.

The form's hidden `htr_report_id` carries a `draft-<token>` reference until submission. The authenticated `link_contact` endpoint accepts this reference too, if an optional workflow is still in use; pass the value unchanged rather than coercing it to an integer. Legacy `prepare` endpoints remain available for older integrations, while the current frontend uses the draft endpoints. Public report links become available after finalization, and the page reports any failure to save the submitted contact.
### Instant Roof Quote design
Roof Reports > Settings > Instant Roof Quote Design controls the three customer-facing progress labels, property/contact headings, retained section headings and descriptions, navigation and report action labels, analysis checklist, property illustration, sidebar photo and sidebar copy. Roof material photos remain in Pricing; GHL fields and its internal form appearance remain managed in GHL. The sidebar stacks beneath the quote on smaller screens. Design settings use the existing option, validate image attachments, and preserve absent values during partial updates.

## Standalone form embed (same origin)

The form-only page is `/?htr_rr_embed=report`. It renders the existing Instant Roof Quote without the theme header/footer or quote sidebar, independently of floating-tab enablement and drawer settings. LeadConnector chatbot scripts are dequeued on embed requests; the GHL contact form and regular website chat are unaffected.

Open `/?htr_rr_embed_demo=1` for a working iframe example, form-only link and copyable same-site HTML. The demo validates message origin and iframe source before applying automatic height updates. A copied fixed-height iframe can be used on another page of the same origin:

```html
<iframe src="https://YOUR-DOMAIN.com/?htr_rr_embed=report"
  title="Instant Roof Quote" width="100%" height="850"
  style="border:0" allow="geolocation"></iframe>
```

These pages are noindex and send `X-Frame-Options: SAMEORIGIN` plus `frame-ancestors 'self'`. External domains remain blocked; third-party allowlisting and source tracking are not implemented. Deploying the code does not change Google/GHL settings or enable external embedding. Validate the complete submission on the deployed site separately.

## Three-step quote interface (1.3.0)

The quote now groups property search, satellite confirmation and materials into one customer step. See [implementation, GHL verification requirements, tests and deployment notes](docs/three-step-quote-redesign.md). Version 1.3.2 restores the existing correlated GHL iframe contact-save path and persists before displaying Step 3 on the same page. Opening the full report or print dialog is a separate customer action. The authenticated link_contact callback remains supported. See the security distinction and staging checks in the implementation notes.

## Editable report layout (1.4.0)

Roof Reports > Report Layout supports section ordering, additional text and private unsaved previews. See [report layout editor guide](docs/report-layout-editor.md) for deployment files, global behavior and validation limits.

## Current release: 1.6.9

### Customer journey and submission behavior

The Instant Roof Quote has exactly three visible steps:

1. **Property & Roof Details** — existing address autocomplete, satellite/map confirmation, roof preview and configured material choices appear progressively on one screen.
2. **Contact Information** — the existing configured contact/GHL form, service interests and separate SMS consent controls.
3. **Your Roof Report** — saved report results, measurements, estimate, PDF/download, email and full-details actions.

Successful contact saving advances to Step 3 without an automatic external-page redirect. Submission feedback shows saving/retry activity and a longer-than-usual message after 15 seconds. A delay never counts as success. The separate roof calculator retains its own interface and shares the contact-handling helpers.

In GHL mode the existing iframe-specific source/origin/correlation checks and signed `save_contact` capability are retained. Contact saving is retried on transient failure and must persist before the normal browser-reported completion path reveals the report. The authenticated `link_contact` webhook remains supported; preserve `draft-<token>` references as strings in any workflow mapping. Browser-reported completion is not independent server verification of a GHL submission. Do not describe this release as a webhook-only gate. Waiting drafts withhold final prices/report links, and native report endpoints cannot bypass configured GHL mode.

### Report Layout editor

Open **Roof Reports → Report Layout** (`wp-admin/admin.php?page=htr-rr-layout`).

- Drag report sections into order, or use the accessible up/down icons.
- Add optional text sections; built-in generated sections are retained.
- Use the 30% editor / 70% preview workspace; it stacks on smaller screens.
- Preview unsaved changes with clearly labeled sample data, or enter a saved report ID for an administrator-only preview of actual data.
- Click outlined wording in the preview to edit headings, labels, instructions, disclaimers and custom text. Generated prices, measurements and customer data remain read-only.
- Use **Header Style** and **Footer Style** popups to set solid/gradient backgrounds, text colors, spacing, alignment and Media Library logos.
- Footer controls support headings/details and a branded contact bar: logo left, phone center, service area right, with an optional website beneath the logo.
- **Apply style** updates the unsaved preview; **Save report layout** publishes changes globally to online reports and PDF output, including existing reports.

Layout data is stored in the `htr_rr_report_layout` WordPress option. There are no custom-table schema changes. Saving and previewing require `manage_options` and a nonce. Section names, wording keys, sizes, colors, logo attachments and contact URLs are validated; custom text uses a limited HTML allowlist. No arbitrary PHP/CSS is accepted. Inline preview updates verify the iframe source, origin and preview revision.

### PDF and print behavior

PDF download uses the browser's **Print / Save as PDF**, not a server-side PDF service.

- Header and branded contact footer repeat on printed pages; the footer is anchored at the bottom with reserved layout space.
- The disclaimer prints once at the end of the report body.
- Body sections are paginated separately to retain aerial imagery, SVG diagrams and roof-face data. Download waits for images/fonts and draws available diagrams before printing.
- Normal roof-face tables stay with their heading and source note, moving together to the next page if needed. A table taller than a full page may still split.
- Print has square branding, zero document-defined outer margins, padded body content, additional breathing room after the header, a full-width aerial image and larger side-by-side diagrams.

Enable **Background graphics** for colored bands. If your browser overrides page margins, select **Margins: None**. Turn off browser **Headers and footers** to remove its date/title/URL decorations. Physical printers may impose hardware margins. Native iOS/Safari/Android printing still needs device verification.

### Deployment and rollback

Deploy this plugin as a complete unit. New runtime files include `includes/class-report-layout.php`, `assets/js/report-layout.js`, `assets/css/report-layout.css`, `assets/js/report-print.js` and all `templates/report-sections/*.php`. Do not upload the report shell without these files or mix versions.

Back up the plugin/options, upload the complete version, purge page/asset caches and test the actual GHL submission, backend saving, Step 3, report preview, email and PDF. Existing Google/GHL/n8n credentials and pricing settings remain in place; no new environment variables are required.

For rollback, restore the previous complete plugin package. The layout option can remain stored; older versions ignore it. No pricing/report-table rollback is needed. Consider the contact-path differences documented in the implementation notes when choosing a rollback version.

### Local validation

Run isolated PHP suites with PHP 7.4+ (tested locally with PHP 8.2):

```text
php tests/calculator-test.php
php tests/report-layout-test.php
php tests/calculator-design-test.php
```

Generate fixtures with the PHP suites before the relevant Playwright checks:

```text
node tests/instant-ghl-browser-test.js
node tests/calculator-design-browser-test.js
node tests/three-step-native-browser-test.js
node tests/report-layout-browser-test.js
node tests/report-print-browser-test.js
```

Set `HTR_BROWSER_CHANNEL=chrome` to use installed Chrome. Node and Playwright must be available. Generated HTML, screenshots and PDFs are ignored by Git.

The calculator/lead suite has 131 isolated checks (including pricing checks); the layout suite has 40. Browser checks cover submission retries, same-page completion, editor controls, inline wording, message rejection and PDF pagination. PDF tests include real Chrome-generated documents at mobile/desktop viewport widths, complete measured-roof fixtures, repeated branding, a single disclaimer and six-row table grouping. Provider responses, WordPress/KSES and some admin dependencies are doubled in fixtures; these checks do not prove live integrations or native-device printing. Existing PHP suites also cover settings, pagination, CSV output and request sources.

See [three-step implementation notes](docs/three-step-quote-redesign.md) and [report layout editor guide](docs/report-layout-editor.md) for details and remaining QA. Public token TTL, production debug settings and wider infrastructure hardening remain separate tasks; this release is not a full security audit.
## Optional two-page estimate design (1.7.0)

Roof Reports > Report Layout > Report template & estimate design now offers the supplied estimate-style presentation. The cover uses actual property imagery, metrics and saved pricing; the details page contains editable supporting information, materials, measurements and next steps. Classic remains available and is the default. See [estimate design setup and validation notes](docs/estimate-report-design.md).
