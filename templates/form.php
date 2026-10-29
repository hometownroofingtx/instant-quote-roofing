<?php
/** Homeowner form markup. Behaviour lives in assets/js/roof-report.js. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cfg = HTR_RR_Settings::public_config();
?>
<div class="htr-rr" id="htr-rr" data-step="1">
	<?php if ( empty( $cfg['browserKey'] ) ) : ?>
		<div class="htr-rr-alert htr-rr-alert--warn">
			<?php echo current_user_can( 'manage_options' ) ? 'Admin note: add the Google browser key under Roof Reports &gt; Settings to turn on the map and address search.' : 'The roof report tool is being set up. Please call us for a free inspection.'; ?>
		</div>
	<?php endif; ?>

	<header class="htr-rr-hero">
		<p class="htr-rr-eyebrow">Instant Roof Report</p>
		<h2>See your roof. Know your options.</h2>
		<p>Enter your property address to get your roof measurements from aerial imagery and an estimated price range, in about a minute.</p>
		<ul class="htr-rr-badges">
			<li>Aerial measurements</li><li>Roof report</li><li>Estimated price range</li><li>No obligation</li>
		</ul>
	</header>

	<div class="htr-rr-grid">
		<!-- 1. Find your property -->
		<section class="htr-rr-card" data-card="1">
			<h3><span class="htr-rr-num">1</span> Find your property</h3>
			<p class="htr-rr-sub">Start typing your full property address and pick it from the list.</p>
			<div class="htr-rr-search" id="htr-rr-search"><!-- Google address search is placed here --></div>
			<button type="button" class="htr-rr-link" id="htr-rr-geo">Use my current location</button>
			<p class="htr-rr-error" id="htr-rr-err1" role="alert" hidden></p>
		</section>

		<!-- 2. Confirm your property -->
		<section class="htr-rr-card" data-card="2" aria-disabled="true">
			<h3><span class="htr-rr-num">2</span> Confirm your property</h3>
			<p class="htr-rr-sub">Zoom in and drag the red pin onto your roof if it is not already there.</p>
			<div class="htr-rr-confirm">
				<div class="htr-rr-mapwrap">
					<div class="htr-rr-map" id="htr-rr-map" aria-label="Satellite map of your property"></div>
					<div class="htr-rr-maptype" role="group" aria-label="Map view">
						<button type="button" data-type="satellite" class="is-on">Satellite</button>
						<button type="button" data-type="roadmap">Map</button>
					</div>
				</div>
				<aside class="htr-rr-prop" id="htr-rr-prop" aria-live="polite">
					<div class="htr-rr-prop__head">
						<img id="htr-rr-thumb" alt="" width="72" height="56" hidden>
						<div><p class="htr-rr-addr" id="htr-rr-addr"></p><button type="button" class="htr-rr-link" id="htr-rr-edit">Edit address</button></div>
					</div>
					<h4>Roof preview</h4>
					<dl class="htr-rr-facts" id="htr-rr-facts"><div><dt>Roof area</dt><dd>&mdash;</dd></div></dl>
					<p class="htr-rr-note" id="htr-rr-propnote">From Google aerial imagery. Move the pin to update.</p>
				</aside>
			</div>
			<button type="button" class="htr-rr-btn" id="htr-rr-confirm" disabled>Confirm property &amp; continue</button>
		</section>

		<!-- 3. Roof type -->
		<section class="htr-rr-card" data-card="3" aria-disabled="true">
			<h3><span class="htr-rr-num">3</span> What type of roof do you have?</h3>
			<p class="htr-rr-sub">Pick the main roofing material on the house today.</p>
			<div class="htr-rr-materials" id="htr-rr-materials" role="radiogroup" aria-label="Roof type">
				<?php foreach ( $cfg['materials'] as $m ) : ?>
					<button type="button" role="radio" aria-checked="false" class="htr-rr-mat htr-rr-mat--<?php echo esc_attr( $m['key'] ); ?>" data-mat="<?php echo esc_attr( $m['key'] ); ?>">
						<?php $roof_image = ! empty( $m['image_id'] ) ? wp_get_attachment_image( $m['image_id'], 'medium', false, array( 'class' => 'htr-rr-material-image', 'alt' => '', 'loading' => 'lazy' ) ) : ''; ?>
						<?php if ( $roof_image ) : ?>
							<span class="htr-rr-material-visual" aria-hidden="true"><?php echo $roof_image; // WordPress-generated attachment markup. ?></span>
						<?php else : ?>
							<span class="htr-rr-swatch" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="htr-rr-material-label"><?php echo esc_html( $m['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- 4. Contact -->
		<section class="htr-rr-card" data-card="4" aria-disabled="true">
			<h3><span class="htr-rr-num">4</span> Your contact information</h3>
			<p class="htr-rr-sub">We will send your roof report to this email.</p>
			<form id="htr-rr-form" novalidate>
				<div class="htr-rr-row">
					<label>First name *<input name="first_name" autocomplete="given-name" required></label>
					<label>Last name *<input name="last_name" autocomplete="family-name" required></label>
				</div>
				<label>Email *<input name="email" type="email" autocomplete="email" required></label>
				<label>Phone *<input name="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="(210) 000-0000" required></label>
				<label class="htr-rr-hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
				<fieldset class="htr-rr-interests">
					<legend>What are you interested in?</legend>
					<label><input type="checkbox" name="interests" value="replacement"> Roof replacement</label>
					<label><input type="checkbox" name="interests" value="repair"> Roof repair</label>
					<label><input type="checkbox" name="interests" value="storm"> Storm damage inspection</label>
					<label><input type="checkbox" name="interests" value="inspection"> General inspection</label>
				</fieldset>
				<fieldset class="htr-rr-consent">
					<label><input type="checkbox" name="sms_service" value="1"> <?php echo esc_html( HTR_RR_Settings::get( 'consent_service' ) ); ?></label>
					<label><input type="checkbox" name="sms_marketing" value="1"> <?php echo esc_html( HTR_RR_Settings::get( 'consent_marketing' ) ); ?></label>
				</fieldset>
				<p class="htr-rr-error" id="htr-rr-err4" role="alert" hidden></p>
				<button type="submit" class="htr-rr-btn" id="htr-rr-submit">Generate my roof report</button>
			</form>
		</section>

		<!-- 5. Analysing -->
		<section class="htr-rr-card" data-card="5" aria-disabled="true">
			<h3><span class="htr-rr-num">5</span> Analyzing your roof</h3>
			<ol class="htr-rr-progress" id="htr-rr-progress" aria-live="polite">
				<li data-p="1">Property confirmed</li>
				<li data-p="2">Roof type selected</li>
				<li data-p="3">Reading aerial imagery</li>
				<li data-p="4">Measuring roof area and pitch</li>
				<li data-p="5">Preparing your report</li>
			</ol>
		</section>

		<!-- 6. Result -->
		<section class="htr-rr-card htr-rr-card--wide" data-card="6" aria-disabled="true">
			<h3><span class="htr-rr-num">6</span> Your roof report is ready</h3>
			<div id="htr-rr-result"></div>
		</section>
	</div>
</div>
