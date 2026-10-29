# Settings page redesign

The Settings page previously displayed every setting in one long table. This change groups related fields so administrators can find configuration and customer-facing text more easily.

## Changes

- Separate cards for Maps and roof measurements, lead delivery, customer information, and SMS consent.
- Two columns on desktop, stacking into one column on smaller screens.
- Consistent field widths, top-aligned labels, compact spacing, and readable descriptions.
- A separate report usage limit and a clear Save settings action.
- A separate Google Solar test panel explaining that testing uses the saved server key.
- Associated labels for the imagery selector, consent text, disclaimer, usage limit, and test coordinates.

The existing setting names, stored options, save endpoint, nonces, credential validation, and lead-delivery behavior are preserved. Blank secret fields retain their saved values. The redesign changes admin presentation only; it does not change the customer form or estimate calculations.

## Files

- `includes/class-admin.php`: Settings markup and a reusable settings-card heading helper.
- `assets/css/admin.css`: Scoped Settings layout and responsive styles.

## Validation

- PHP syntax check passed for `includes/class-admin.php`.
- All 24 existing isolated pricing checks passed.
- `git diff --check` passed.

Visual browser verification and live integration testing have not been performed for this change.
