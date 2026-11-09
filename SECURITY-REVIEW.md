# Security Review Summary

Date: 2026-11-09

Scope reviewed:
- `wp-content/themes/base-theme-starter`
- related plugins under `wp-content/plugins` and `wp-content/plugins-old`
- `wp-config.php` and deployment-related config

## Findings

### 1) HIGH — Token leakage risk via Referer on report pages
- **Files**:
  - `includes/class-report-page.php:17`
  - `templates/report.php:62-66`
  - `includes/class-report-page.php:90-91`
- **Issue**: Report access uses URL bearer tokens (`?htr_roof_report=<token>`) while report pages load third-party map resources. Without strict `Referrer-Policy`, tokenized URLs may leak via `Referer` headers.
- **Impact**: Exposed token can allow unauthorized report access.
- **Confidence**: 8/10

### 2) MEDIUM — Debug output enabled in runtime config
- **File**: `wp-config.php:30-31`
- **Issue**: `WP_DEBUG` and `WP_DEBUG_DISPLAY` are enabled unconditionally.
- **Impact**: Verbose errors can reveal sensitive internals (paths, stack traces, plugin details) to unauthenticated users.
- **Confidence**: 9/10

## Notes
- This summary records findings only, per request.
