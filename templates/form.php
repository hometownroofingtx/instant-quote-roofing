<?php
/** Homeowner form markup. Behaviour lives in assets/js/roof-report.js. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cfg = HTR_RR_Settings::public_config();
$quote = HTR_RR_Settings::get( 'quote_design' );
$quote_steps = array();
foreach ( range( 1, 6 ) as $n ) { $quote_steps[] = $quote[ 'step_' . $n . '_label' ]; }
?>
<div class="htr-quote-layout"><div class="htr-rr htr-rr-wizard" id="htr-rr" data-step="1" data-quote-copy="<?php echo esc_attr( wp_json_encode( $quote ) ); ?>">
 <ol class="htr-quote-steps" aria-label="Roof quote progress"><?php foreach ( $quote_steps as $i => $label ) : ?><li data-quote-step="<?php echo (int) $i + 1; ?>"><span><?php echo (int) $i + 1; ?></span><b><?php echo esc_html( $label ); ?></b></li><?php endforeach; ?></ol>
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
			<h3><span class="htr-rr-num">1</span> <?php echo esc_html( $quote['step_1_heading'] ); ?></h3>
			<p class="htr-rr-sub"><?php echo esc_html( $quote['step_1_description'] ); ?></p>
			<div class="htr-rr-search" id="htr-rr-search"><!-- Google address search is placed here --></div>
			<button type="button" class="htr-rr-link" id="htr-rr-geo"><?php echo esc_html( $quote['location'] ); ?></button>
			<p class="htr-rr-error" id="htr-rr-err1" role="alert" hidden></p>
            <div class="htr-quote-illustration" aria-hidden="true"><?php if ( $quote['property'] ) : ?><?php echo wp_get_attachment_image( $quote['property'], 'large', false, array( 'alt' => '' ) ); ?><?php else : ?><svg viewBox="0 0 400 170"><path d="m20 140 180-90 180 90-180 30Z" fill="#e6f1f9"/><path d="M105 95 200 25l95 70v60H105Z" fill="#dce9ed"/><path d="m90 96 110-82L310 96" fill="none" stroke="#1265b0" stroke-width="12"/><path d="M179 155v-50h43v50" fill="#187ac4"/><path d="M130 105h28v25h-28zm112 0h28v25h-28z" fill="#fff"/><path d="M200 0c-16 0-26 11-26 25 0 20 26 44 26 44s26-24 26-44c0-14-10-25-26-25Z" fill="#0083ec"/><circle cx="200" cy="24" r="9" fill="#fff"/></svg><?php endif; ?></div>
            <div class="htr-quote-nav"><button type="button" class="htr-rr-btn" data-quote-find><?php echo esc_html( $quote['find'] ); ?></button></div>
		</section>

		<!-- 2. Confirm your property -->
		<section class="htr-rr-card" data-card="2" aria-disabled="true">
			<h3><span class="htr-rr-num">2</span> <?php echo esc_html( $quote['step_2_heading'] ); ?></h3>
			<p class="htr-rr-sub"><?php echo esc_html( $quote['step_2_description'] ); ?></p>
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
						<div><p class="htr-rr-addr" id="htr-rr-addr"></p><button type="button" class="htr-rr-link" id="htr-rr-edit"><?php echo esc_html( $quote['edit'] ); ?></button></div>
					</div>
					<h4><?php echo esc_html( $quote['preview_heading'] ); ?></h4>
					<dl class="htr-rr-facts" id="htr-rr-facts"><div><dt>Roof area</dt><dd>&mdash;</dd></div></dl>
					<p class="htr-rr-note" id="htr-rr-propnote"><?php echo esc_html( $quote['preview_note'] ); ?></p>
				</aside>
			</div>
            <div class="htr-quote-nav"><button type="button" class="htr-rr-btn htr-rr-btn--ghost" data-quote-back="1"><?php echo esc_html( $quote['back'] ); ?></button><button type="button" class="htr-rr-btn" id="htr-rr-confirm" disabled><?php echo esc_html( $quote['confirm'] ); ?></button></div>
		</section>

		<!-- 3. Roof type -->
		<section class="htr-rr-card" data-card="3" aria-disabled="true">
			<h3><span class="htr-rr-num">3</span> <?php echo esc_html( $quote['step_3_heading'] ); ?></h3>
			<p class="htr-rr-sub"><?php echo esc_html( $quote['step_3_description'] ); ?></p>
			<div class="htr-rr-materials" id="htr-rr-materials" role="radiogroup" aria-label="Roof type">
				<?php foreach ( $cfg['materials'] as $m ) : ?>
					<?php if ( 'other' === $m['key'] ) { continue; } ?>
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
            <div class="htr-quote-nav"><button type="button" class="htr-rr-btn htr-rr-btn--ghost" data-quote-back="2"><?php echo esc_html( $quote['back'] ); ?></button><button type="button" class="htr-rr-btn" data-quote-contact disabled><?php echo esc_html( $quote['continue'] ); ?></button></div>
		</section>

		<!-- 4. Contact -->
		<section class="htr-rr-card" data-card="4" aria-disabled="true">
			<h3><span class="htr-rr-num">4</span> <?php echo esc_html( $quote['step_4_heading'] ); ?></h3>
			<p class="htr-rr-sub"><?php echo esc_html( $quote['step_4_description'] ); ?></p>
			<?php if ( 'ghl_form' === $cfg['contactMode'] ) : ?>
				<div class="htr-rr-ghl" id="htr-rr-ghl" aria-live="polite"></div>
				<p class="htr-rr-error" id="htr-rr-err4" role="alert" hidden></p>
			<?php else : ?>
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
				<button type="submit" class="htr-rr-btn" id="htr-rr-submit"><?php echo esc_html( $quote['submit'] ); ?></button>
			</form>
			<?php endif; ?>
            <div class="htr-quote-nav"><button type="button" class="htr-rr-btn htr-rr-btn--ghost" data-quote-back="3"><?php echo esc_html( $quote['back'] ); ?></button></div>
		</section>

		<!-- 5. Analysing -->
		<section class="htr-rr-card" data-card="5" aria-disabled="true">
			<h3><span class="htr-rr-num">5</span> <?php echo esc_html( $quote['step_5_heading'] ); ?></h3>
            <p class="htr-rr-sub"><?php echo esc_html( $quote['step_5_description'] ); ?></p>
			<ol class="htr-rr-progress" id="htr-rr-progress" aria-live="polite">
				<?php foreach ( range( 1, 5 ) as $n ) : ?><li data-p="<?php echo (int) $n; ?>"><?php echo esc_html( $quote[ 'analysis_' . $n ] ); ?></li><?php endforeach; ?>
			</ol>
            <div class="htr-quote-working" role="status"><span></span>Preparing your report…</div>
		</section>

		<!-- 6. Result -->
		<section class="htr-rr-card htr-rr-card--wide" data-card="6" aria-disabled="true">
			<h3><span class="htr-rr-num">6</span> <?php echo esc_html( $quote['step_6_heading'] ); ?></h3>
			<p class="htr-rr-sub"><?php echo esc_html( $quote['step_6_description'] ); ?></p><div id="htr-rr-result"></div>
		</section>
	</div>
</div>

<aside class="htr-quote-sidebar" aria-label="About your roof quote">
 <?php if ( $quote['sidebar'] ) : ?><div class="htr-quote-sidebar-photo"><?php echo wp_get_attachment_image( $quote['sidebar'], 'large', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?></div><?php endif; ?>
 <div class="htr-quote-sidebar-content"><h2><?php echo esc_html( $quote['sidebar_title'] ); ?></h2><p><?php echo esc_html( $quote['sidebar_text'] ); ?></p>
 <ol><?php foreach ( $quote_steps as $i => $label ) : ?><li><span><?php echo (int) $i + 1; ?></span><div><strong><?php echo esc_html( $label ); ?></strong><p><?php echo esc_html( $quote['step_' . ( $i + 1 ) . '_description'] ); ?></p></div></li><?php endforeach; ?></ol>
 <div class="htr-quote-reassurance"><strong><?php echo esc_html( $quote['reassurance_title'] ); ?></strong><p><?php echo esc_html( $quote['reassurance_text'] ); ?></p></div></div>
</aside></div>
