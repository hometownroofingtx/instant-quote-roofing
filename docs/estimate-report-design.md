# Two-page estimate report design (1.7.1)

## Enable and preview

Open Roof Reports > Report Layout > Report template & estimate design. Choose Two-page estimate design. Use Preview unsaved changes with an existing report ID to inspect real data, or leave it empty for clearly labeled sample values. Save report layout to publish. Classic remains the default so existing saved reports are not silently restyled.

The quote form, customer submission actions, database report schema, measurement integrations, pricing calculations and token access rules are unchanged. This is a report presentation option. Switching back to Classic restores the existing draggable section layout and inline copy editor; their stored settings remain intact.

## Layout

- Cover: logo/tagline header, property address, prepared date and source, real Google aerial image when available, four computed metrics, existing price range and booking/contact CTA.
- Details: editable reassurance/warranty panel, optional approved rating text, selected material and alternative options, roof-face table beside next steps, actual imagery/source information and the existing disclaimer.
- Nine face rows fit the first details page; additional rows receive continuation pages in groups of twenty. No measurements are truncated.
- Available roof diagrams are an optional appendix. Existing custom text sections are retained in an additional-information appendix rather than discarded.
- No sample price, roof size, face count, warranty claim, BBB rating or material specification from the reference is used as customer data.

The original logo is reused; Header Style can supply a Media Library replacement. Footer Style supplies a phone override, with the report/company phone as fallback. The new design controls include its footer website and optional inspection booking URL. If no booking URL is configured, the CTA uses the configured phone; no booking integration is invented.

## Editing and styling

The design settings panel controls header tagline, headings, estimate labels/qualification, CTA copy/link, warranty/reassurance wording, rating text, material explanations, process steps, estimate notes and website. Header, accent/CTA and price-panel colors are editable. Only validated six-digit colors and HTTP(S) website/booking links are accepted. Credentials and computed values are never edited here.

Both templates support inline wording edits in the private preview. Click outlined wording, edit it, then choose Save report layout to publish. Changes also update the matching settings field. Computed prices, measurements and customer data remain read-only. Classic drag order and styling options do not rearrange this intentionally composed layout. Warranty/rating defaults make no lifetime-transferable or BBB claims; enter approved wording before publishing those claims. Manufacturer specifications and alternative pricing remain subject to a separate written proposal.

The report retains the existing disclaimer once. Manual measurements are explicitly labeled; unavailable measurements/prices and aerial imagery use honest fallback messages. Test imagery placeholders exist only in tests; production requests the existing Google map imagery.

## Printing

The standard fixture prints as two US Letter pages from both mobile and desktop viewport widths. Larger face tables, optional diagrams/custom content, longer copy or different browser paper/scale settings may require extra pages. Header/footer branding is rendered on each deliberately composed page, with footer page labels. Very long optional text can flow further; verify actual edited copy in print rather than assuming any arbitrary length always fits two pages.

Use Background graphics in browser PDF settings. No server-side PDF generator was added. The Download button waits for images and fonts; print=1 still opens the existing browser workflow. The original report remains available through the template selector.

## Deployment

Deploy the full plugin together. New runtime files:

- includes/class-estimate-design.php
- templates/report-estimate.php
- assets/css/report-estimate.css
- assets/js/report-estimate.js
- assets/img/workmanship-mascot.png (supplied artwork beside the workmanship protection text, included in print)
- assets/img/bbb-rating.png (supplied BBB artwork at the right of the workmanship panel, with A+ BBB Rating underneath)

Updated loader, Report Layout controller, report shell and editor CSS must accompany those files. Settings use the new WordPress option htr_rr_estimate_design; there is no custom-table schema migration. Both layout and new-template options use the existing administrator-only, nonce-checked save/preview actions. Save failures validate before writing either option. Old clients without estimate_design fields preserve existing settings.

Rollback: restore the previous complete plugin package. Its Classic template ignores the new option. Do not remove or migrate report data.

## Validation

The extended isolated layout suite covers existing defaults, template allowlisting, URLs/colors, live-value rendering, unapproved-claim absence, missing measurement handling, table continuation and appendix retention. Chrome fixtures cover responsive layout, exactly two standard PDF pages, actual report values and long-table retention at 375/1366px widths. Existing Classic/editor/print/submission tests are rerun.

Fixtures simulate WordPress/provider responses and are not live verification. Test screenshots/PDFs are ignored under tests/.estimate-design-*. Live WordPress save/preview, actual customer imagery, edited branding, real booking links and native mobile printing still require staging checks. New code remains on the current feature branch; no automatic push or production deployment is part of this update.
## Customer details (1.7.2)

Reports and PDFs include saved full name, property address, email and phone. Missing contact fields are omitted; customer values remain read-only. Contact details are added only by report_view() for token-authorized reports and administrator previews. The quote-status REST payload is unchanged. Report links remain bearer links: anyone possessing a valid link can view these details. URL expiration is deferred to the next round.
