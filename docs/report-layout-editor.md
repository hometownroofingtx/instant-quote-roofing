# Report layout editor (1.4.0)

Open Roof Reports > Report Layout. Built-in sections are Report header, Roof summary & estimate, Aerial view, Roof diagrams & measurement details, Next steps, and Disclaimer / footer.

- Drag a section by its handle, or use Move up / Move down (also usable on mobile and by keyboard).
- Add text sections anywhere in the report. Edit their optional heading and body. Basic HTML supports paragraphs, bold, italics, lists, links, subheadings and blockquotes.
- Built-in sections cannot be removed; this keeps computed report information and disclaimers. Custom text may be removed.
- Restore default order keeps custom text, positioning it before the built-in sections.
- Save report layout publishes the shared layout for all reports, including existing reports. The site's global header/footer and the quote wizard are unaffected.
- Preview unsaved changes renders the same report/PDF template in a private admin iframe. Leave Report ID empty for explicitly labeled SAMPLE DATA with no aerial requests. Enter an existing report ID to preview real data as an administrator. Preview does not save options or report records.
- Downloading a report still uses browser Print / Save as PDF, and uses this same saved section order and text.

## Storage, permissions and security

The layout uses one new WordPress option, htr_rr_report_layout. There is no database table/schema migration. Generated measurements/prices and customer records are never modified by this editor. Administrator manage_options capability and htr_rr_report_layout nonce checks protect saving and previewing. Preview responses disable caching/indexing. Sections use a fixed server-side allowlist before template inclusion. Unknown types, duplicate built-ins, malformed payloads and oversized content are rejected. Core sections omitted from a request are restored automatically.

Up to 36 sections total; text bodies up to 20,000 bytes and headings up to 200 bytes. Custom content is sanitized through WordPress KSES with a limited HTML allowlist at save/preview/read/render; scripts, embeds, images, styles and event handlers are excluded. No PHP or arbitrary file paths are accepted. Public report token access controls remain unchanged. Preview sample figures are admin-only, clearly labeled and never stored as a customer's quote.

## Files

New:
- includes/class-report-layout.php
- assets/js/report-layout.js
- assets/css/report-layout.css
- templates/report-sections/header.php
- templates/report-sections/summary.php
- templates/report-sections/aerial.php
- templates/report-sections/diagrams.php
- templates/report-sections/next.php
- templates/report-sections/footer.php

Updated runtime:
- htr-instant-roof-report.php (loads editor and version 1.4.0)
- templates/report.php (shared section composition)

Install the complete plugin package together; do not upload only the shell template without its new section files and class. No new credentials. Back up first and clear caches after upload. Settings apply globally, not as an immutable snapshot per report.

Rollback: restore the complete prior plugin package. The new option can remain stored; the previous version ignores it. Pricing and report tables do not require rollback.

## Local validation and remaining QA

20 isolated PHP layout checks cover ordering, retained built-ins, malformed/oversized input, allowed tags, capability/nonce rejection boundaries, price/metrics rendering, diagram uniqueness and measurement fallbacks. WordPress KSES is a test double in these isolated tests; actual WP sanitization is not claimed proven by that fixture. Existing calculator/pricing/source suites are also run.

Chrome editor fixture checks cover add/edit, button ordering, preview POST payload, preserving custom text on reset, removal and 375/1366px widths. jQuery sortable uses a test double there. Verify actual mouse drag, capability/nonce enforcement, save/reload, preview with a real report, and Print / Save as PDF in authenticated WordPress before rollout. The renderer has been split into partials preserving its original generated content; manual-dimension reports no longer display a misleading Google-imagery attribution.
## Header/footer styling (1.5.0)

Use Header style / Footer style on the Report Layout toolbar. The accessible popup supports original styling, solid or gradient backgrounds, start/end and text colors, padding and alignment; header settings also include logo height. Apply style updates the unsaved preview. Save report layout publishes globally. Original style remains the default for existing reports. Styles are stored with the header/footer sections; colors, size ranges and enums are validated before emitting report CSS. No arbitrary CSS is accepted. The admin page header has a refreshed document-design layout.

The same styles render in preview, online reports and browser PDF output. Enable Background graphics in the browser print dialog for colored PDF backgrounds. As of 1.6.2 the configured header/footer repeat in print output. Live authenticated save/preview and actual browser PDF colors still need staging verification.

## Logo and footer content (1.5.1)

Header Style and Footer Style now offer a WordPress Media Library image selector, logo height and a clear/default control. The footer also has an optional heading and multiline details. These fields are stored with the existing per-section style data, sanitized, and rendered by the shared report template. Header logo falls back to the bundled logo; footer logo is optional. Deleted image attachments fall back without discarding the section order. Footer details are plain text and preserve line breaks; the original disclaimer remains. Choosing media temporarily closes the native style dialog so the WordPress Media Library is accessible, then reopens the style dialog. Browser fixtures mock the Media Library; actual selection/upload/save/print still require live WordPress verification.

## Editable preview wording (1.6.0)

Select Preview unsaved changes, then click outlined report wording to edit it directly. Headings, metric labels, explanatory copy, instructions, disclaimer/footer content and custom text are editable. Customer details, generated prices, measurements, imagery and diagram annotations stay read-only. Content hidden by the current report's state can be edited by previewing an applicable report. Empty footer details/headings are added through Footer Style; empty custom headings through their editor field.

Edits synchronize into the unsaved layout; Save report layout publishes them globally. Existing wording remains the default until an override is saved. Custom text edited directly in the preview becomes plain text; approved formatting can still be entered in the section's text field. Public reports/PDFs are read-only and contain no editing controls. Browser preview messages must match the iframe window, origin and current preview revision. Section references are retained across reorder operations; removed sections are ignored. Fast edits are synchronized before save/refresh or opening style settings. Server validation accepts only known wording keys with bounded plain text, so prices cannot be overridden through copy settings. Preview paste inserts text only.

Local checks cover editable vs public rendering, invalid/generated copy keys, iframe editing, spoof rejection and fast synchronization. Actual authenticated WordPress save/reload/PDF output remains a staging check.

## Branded contact footer (1.6.1)

In Footer Style select Footer design > Contact bar with red accent. Set Phone, Website, Service area and Accent color. The bar uses Start color for navy/background and Text color for icon/text color. Empty phone falls back to the configured company phone; blank website/service area are omitted. Existing footers retain Standard footer until changed. The disclaimer, optional logo, heading and other footer details remain above the contact bar.

The contact bar uses inline vector phone/globe/location icons, separators and an angled accent matching the supplied reference. Online links use tel and validated HTTP(S) URLs. It stacks on narrow screens but retains the horizontal bar in print; background-graphics printing requirements remain. Preview and Save report layout apply the same design globally.

## Repeated print header/footer (1.6.2)

Download report and browser Print now use a print-only table with repeating header/footer groups. These contain the current saved header/footer, including logos, edited wording, disclaimer and contact bar. Both groups reserve real layout space on every printed page. The existing report body is temporarily moved into the print table, preserving diagrams and current preview edits, then restored after printing. The original header/footer remain in their configured positions on screen and are suppressed in the printed body to avoid duplicates.

Mobile screen styling does not affect the printed contact bar: print retains the horizontal desktop arrangement and configured header alignment. No PDF service or schema changes were introduced. New runtime asset assets/js/report-print.js must be uploaded with templates/report.php and the versioned plugin loader. Existing print=1 and Download controls use the same browser print lifecycle.

Chrome tests generated actual two-page PDFs at 375px and 1366px viewport widths, extracted their text and confirmed the header and contact footer occur on both pages, with exactly two pages and correct cleanup. Test fixture PDFs are tests/.report-print-375.pdf and tests/.report-print-1366.pdf, not real customer reports. Native iOS/Safari/Android print dialogs still require device verification. Paper selection and very long header/footer content affect pagination; enable Background graphics for colored PDF bands. Browser-provided date/URL/page-number decorations remain controlled by browser settings.
## Full report print retention and outer margins (1.6.5)

Print preparation now moves each body section into its own table row instead of nesting the entire report inside one long row. The original live image/SVG/table nodes are retained and restored after print. Page margins and branding-band gaps are zero; report body cells retain internal reading gutters. The header/footer therefore reach the printable page edges while body text is padded. Physical printers can still impose hardware margins.

Download and print=1 now wait for image decoding and loaded fonts, and ensure measured-roof diagrams are drawn before invoking print. Manual/no-outline reports retain their honest fallback. Extended PDF tests exercise a full measured report with an aerial image, actual SVG diagrams, and roof-face table at mobile/desktop widths; the previous simplified two-page test did not cover that full body. Native mobile print engines still require device verification.

## Compact print and anchored final footer (1.6.6)

Print-only type sizes, section padding, metric cards, aerial image height, SVG diagram height and table cells are tighter. The tfoot reserves the footer's natural space but is not painted. A separate print-only fixed footer paints that same configured band at bottom:0 on every printed page, including a short final page. Its typography matches the reserved footer to avoid overlap. Cleanup removes both print structures and restores original sections.

Tests cover actual one-page and two-page PDFs at mobile/desktop widths, repeated footer text, bottom-aligned print geometry, preserved aerial/SVG/table content and screen restoration. Native device/printer verification remains necessary. Browser-added date/title/URL decorations are controlled by the browser Headers and footers setting; use None margins and disable those decorations if the browser overrides the document's zero-margin print styles.

## Single disclaimer (1.6.7)

The disclaimer is excluded from repeated footer bands and printed once at the end of the body. Print cleanup restores it to its original footer position for the online report/editor. The two-page PDF regression now asserts exactly one disclaimer while the header and branded contact footer repeat on both pages.

## Automatic roof-face grouping (1.6.8)

The Roof faces measured heading, table and imagery/source note now share a print keep-together group. If the group does not fit in the remaining page space, the browser moves it to the next page rather than leaving the heading and first rows behind. Oversized groups may still split when they exceed a full page. No unconditional page-two break is added. A controlled PDF regression verifies that a nearly full first page pushes the heading and all six rows to page two, alongside existing content/repeated-branding/single-disclaimer checks.

## Header breathing room and larger visuals (1.6.9)

Print adds 4 mm after each repeated header. The aerial view renders at the full available body width in a 16:9 area rather than a height-limited thumbnail. Both side-by-side roof SVGs use their full column width without the compact 48 mm height cap. Existing automatic table grouping, repeated branding, anchored footer and single disclaimer behavior are retained.
