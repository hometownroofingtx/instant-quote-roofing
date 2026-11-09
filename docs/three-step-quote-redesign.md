# Three-step quote redesign and original submission actions (1.3.3)

Customers see Property & Roof Details, Contact Information, and Your Roof Report. Existing internal card IDs, configured material options, address/map controls, pricing, measurement providers, database schema, and report generation remain in place. The calculator retains its original interface.

## Submission regression corrected

Versions 1.3.0 and 1.3.1 removed the established browser contact-save action and required a separate authenticated webhook to persist and release every GHL quote. That changed the integration contract and could leave completed submissions only in temporary drafts, absent from Reports.

1.3.3 restores the original save_contact action. The current GHL iframe must supply both its submission marker and contact payload, with matching location/fingerprint correlation and the existing strict origin/source checks. Sticky restoration or generic submit messages alone cannot save the report. Payload and marker may arrive in either order.

The frontend calls save_contact with the existing report-specific signed, expiring capability. Contact normalization, database persistence, status checks and retry behavior are preserved. The report is marked ghl_submitted as before, and no outbound duplicate lead is sent. It retries two transient save failures and offers a manual retry after continued failure. Only after persistence can status release the report; the main quote advances to Step 3 on the same page. Full Details and PDF links remain explicit customer actions. The calculator keeps its inline results. Prepared prices and report URLs stay server-side while awaiting_form.

The existing authenticated link_contact webhook remains supported as an alternative completion path. Its already-linked guard is preserved so subsequent webhook replay does not send a second lead for a browser-saved report. Historical ghl_submitted reports remain accessible.

## Security distinction

The restored path records browser-reported GHL completion. Strict iframe checks and a signed write capability prevent unrelated iframe messages and report-token-only writes, but they do not independently prove provider-side submission to the server. This preserves the user's existing working integration; it does not meet the stricter webhook-only interpretation in the original redesign specification. Strong independent verification needs an authenticated provider/workflow callback. Do not describe browser-reported completion as webhook verified.

No prices or links are released for awaiting_form drafts, and failed saves cannot trigger redirect. Existing public random report tokens have no TTL. Endpoint nonce strategy, callback concurrency/deduplication, CDN caching and access-log handling still need broader infrastructure review. Report pages use same-origin referrer policy, with origin-only referrer for the Static Maps image.

## Runtime files

Upload the plugin package as a unit. Changed runtime files relative to the original 1.2.0 plugin:

- htr-instant-roof-report.php
- includes/class-settings.php
- includes/class-admin.php
- includes/class-rest.php
- includes/class-leads.php
- includes/class-report-page.php
- templates/form.php
- templates/report.php
- assets/js/roof-report.js
- assets/css/roof-report.css

For the 1.3.1 to 1.3.3 correction specifically, update assets/js/roof-report.js, includes/class-rest.php, includes/class-report-page.php and htr-instant-roof-report.php together. No schema migration or new credentials are needed. Purge caches after deployment. Existing contact_mode, ghl_form_id, ghl_roof_type_key, Google and n8n settings remain.

## Local tests and live QA

Run tests/calculator-test.php and the remaining isolated PHP suites. Generate browser fixtures with tests/calculator-design-test.php, then run tests/instant-ghl-browser-test.js, tests/calculator-design-browser-test.js and tests/three-step-native-browser-test.js with Node/Playwright and HTR_BROWSER_CHANNEL=chrome.

The quote browser test covers matching iframe submission, two failed contact saves followed by success, and Step 3 only after saving. All fixture provider responses are test-only. Live GHL event compatibility, real Reports persistence, contact/consent data, callback mapping, email/PDF, and cache behavior must be verified in staging. Do not treat the fixtures as production proof.

Rollback: back up the plugin/options and restore the complete previous known-working package if needed; no database rollback is required. Avoid 1.3.0/1.3.1 if relying on the original browser save path.

## Report-only content management

A report-only WordPress block-editor template for editable header, text sections and footer is feasible using native posts/meta, revisions and permissions. Keep computed pricing/metrics/diagrams controlled, allowlist blocks, sanitize output, and verify printed page breaks. Render it only through the report template, with no changes to the site's global header/footer. This content editor is a proposed follow-up, not implemented here.
## Submission feedback (1.3.4)

The hosted GHL form remains cross-origin. After its accepted submission message, a polite live status panel with reduced-motion-aware spinner shows saving/retrying progress. At 15 seconds it reports a longer-than-usual delay without asserting a network diagnosis. Terminal save failure stops the spinner and retains the existing Retry saving contact details control. Completion/preview cleanup clears its delay timer. Successful saving still advances to Step 3 on the same page. No completion is inferred from elapsed time. Feedback cannot start on an unobservable button click inside the external iframe.
