# Internal quotes

To offer the same tool on a normal WordPress page, add a Shortcode block containing `[htr_roof_quote_preview]`. Only logged-in users with `manage_options` (administrators) see the tool. Visitors and other roles see an access message. Use this shortcode by itself on the page, rather than combining it with the public quote form. Exclude the preview page from any full-page/CDN cache; the shortcode also marks its response as non-cacheable.

Administrators can open **Roof Reports → Create Quote** to use the same address search, satellite map, roof images, and report design as the frontend. Select the full street address, confirm the correct roof on the draggable map, and choose a roof type.

**Generate internal quote** skips the contact step, uses the existing Google Solar measurement and pricing flow, and displays the result without creating a Reports entry. Unavailable imagery shows a manual-measurement message without an invented price.

The quote is cached temporarily for one hour to support PDF downloads and the full preview. These links require the same logged-in administrator who generated the quote. Expired previews must be regenerated. No saved report, contact details, GHL submission, lead delivery, or customer email is created. Public form settings and existing saved reports are unchanged.

Address search and the map use the configured Google browser key. Generation requires an authenticated administrator session and a valid nonce. No public endpoint accepts the internal quote flag.
