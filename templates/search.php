<?php
/** Standalone address search. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="htr-rr htr-rr-standalone" data-htr-search-redirect="<?php echo esc_url( $search_redirect ); ?>">
	<div class="htr-rr-address-bar">
		<svg class="htr-rr-address-icon" width="34" height="34" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14 16 3l13 11M6 13v15h7v-9h6v9h7V13M11 10l5-4 5 4"/></svg>
		<div class="htr-rr-address-field">
			<span class="htr-rr-address-label">Instant Roof Request</span>
			<div class="htr-rr-search" data-address-search aria-label="Search your property address"></div>
		</div>
		<button type="button" class="htr-rr-address-submit" data-address-submit>Get My Roof Report <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
	</div>
	<p class="htr-rr-error" data-address-error role="alert" hidden></p>
</div>
