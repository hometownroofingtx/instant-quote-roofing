<?php
/** Public calculator using saved pricing and the existing lead pipeline. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$settings = HTR_RR_Settings::all();
$ghl_mode = 'ghl_form' === HTR_RR_Settings::public_config()['contactMode'];
$design = $settings['calculator_design'];
// Update the old stock copy while retaining administrator-written descriptions.
foreach ( array( 'roof_heading' => array( 'Step 1: Roof Details', 'Step 1: Roof Type' ), 'guide_1_text' => array( 'Type, size, pitch, and roof complexity.', 'Choose your roof type. We estimate the dimensions from aerial imagery.' ), 'footer' => array( 'Preliminary estimate using dimensions you enter. Final pricing may vary after an on-site inspection.', 'Preliminary estimate using aerial roof measurements. Final pricing may vary after an on-site inspection.' ) ) as $key => $copy ) { if ( $design[ $key ] === $copy[0] ) { $design[ $key ] = $copy[1]; } }
$sidebar = $design['sidebar'] ? wp_get_attachment_image_url( $design['sidebar'], 'large' ) : '';
?>
<div class="htr-rr htr-rr-calculator" data-htr-calculator>
 <div class="htr-rr-calc-layout"><div class="htr-estimate-main"><ol class="htr-estimate-steps" aria-label="Estimate progress"><?php foreach ( array( 1 => 'Roof Type', 2 => 'Address', 3 => 'Contact Details', 4 => 'Your Estimate' ) as $step => $label ) : ?><li data-calc-progress="<?php echo (int) $step; ?>" <?php echo 1 === $step ? 'aria-current="step"' : ''; ?>><span><?php echo (int) $step; ?></span><?php echo esc_html( $label ); ?></li><?php endforeach; ?></ol>
  <section class="htr-rr-card">
   <div data-calc-stage="1" tabindex="-1"><h3><?php echo esc_html( $design['roof_heading'] ); ?></h3><p class="htr-rr-sub"><?php echo esc_html( $design['roof_description'] ); ?></p>
   <form data-calc-inputs>
    <fieldset class="htr-calc-roofs"><legend>Roof type <span class="htr-estimate-required">*</span></legend><div class="htr-rr-materials">
    <?php foreach ( $settings['materials'] as $key => $material ) : ?>
     <?php if ( 'other' === $key ) { continue; } ?>
     <label class="htr-calc-roof htr-rr-mat--<?php echo esc_attr( $key ); ?>"><input type="radio" name="material" value="<?php echo esc_attr( $key ); ?>" required><span class="htr-calc-roof-content">
     <?php $roof_image = ! empty( $material['image_id'] ) ? wp_get_attachment_image( $material['image_id'], 'medium', false, array( 'class' => 'htr-rr-material-image', 'alt' => '', 'loading' => 'lazy' ) ) : ''; ?>
     <?php if ( $roof_image ) : ?><span class="htr-rr-material-visual" aria-hidden="true"><?php echo $roof_image; // WordPress attachment markup. ?></span><?php else : ?><span class="htr-rr-swatch" aria-hidden="true"></span><?php endif; ?>
     <span class="htr-rr-material-label"><?php echo esc_html( $material['label'] ); ?></span></span></label>
    <?php endforeach; ?>
    </div></fieldset><p class="htr-rr-sub">Choose your roof type. We will estimate your roof size from aerial imagery after you select your address.</p>
   <div class="htr-rr-calc-actions"><button type="submit" class="htr-rr-btn" data-calc-calculate>Continue to Address <span aria-hidden="true">&rarr;</span></button> <button type="reset" class="htr-rr-link">Reset</button></div>
   </form>
   <p class="htr-rr-error" data-calc-error role="alert" hidden></p></div>
  </section>
  <section class="htr-rr-card">

 <div class="htr-rr-calc-contact" data-calc-contact><section data-calc-stage="2" tabindex="-1"><h3><?php echo esc_html( $design['address_heading'] ); ?></h3><p class="htr-rr-sub"><?php echo esc_html( $design['address_description'] ); ?></p>
  <div class="htr-rr-search" data-address-search></div>
  <p data-calc-address aria-live="polite"></p>
  <p class="htr-rr-error" data-address-error role="alert" hidden></p>
  <div data-calc-property hidden>
   <p class="htr-rr-sub">Zoom in and drag the red pin onto your roof if it is not already there.</p>
   <div class="htr-rr-confirm">
    <div class="htr-rr-mapwrap">
     <div class="htr-rr-map" data-calc-map aria-label="Satellite map of your property"></div>
     <div class="htr-rr-maptype" role="group" aria-label="Map view">
      <button type="button" data-type="satellite" class="is-on" aria-pressed="true">Satellite</button>
      <button type="button" data-type="roadmap" aria-pressed="false">Map</button>
     </div>
    </div>
    <aside class="htr-rr-property"><h4>Roof preview</h4><dl class="htr-rr-facts" id="htr-rr-facts"></dl><p class="htr-rr-note" id="htr-rr-propnote" role="status">Select your address to preview roof measurements.</p></aside>
   </div>
  </div>
  <div class="htr-estimate-navigation"><button type="button" class="htr-rr-link" data-calc-back="1">&larr; Roof type</button><button type="button" class="htr-rr-btn" data-calc-address-next>Continue to Contact <span aria-hidden="true">&rarr;</span></button></div></section><section data-calc-stage="3" tabindex="-1"><h3><?php echo esc_html( $design['contact_heading'] ); ?></h3><p class="htr-rr-sub"><?php echo esc_html( $design['contact_description'] ); ?></p><button type="button" class="htr-rr-link" data-calc-back="2">&larr; Edit address</button>
  <?php if ( $ghl_mode ) : ?>
   <button type="button" class="htr-rr-btn" data-calc-continue hidden>Retry loading contact form</button>
   <p data-calc-status role="status"></p>
   <div class="htr-rr-ghl" data-calc-ghl aria-live="polite" hidden></div>
  <?php else : ?>
  <form data-calc-lead>
   <div class="htr-rr-row"><label>First name *<input name="first_name" autocomplete="given-name" required></label><label>Last name *<input name="last_name" autocomplete="family-name" required></label></div>
   <div class="htr-rr-row"><label>Email *<input name="email" type="email" autocomplete="email" required></label><label>Phone *<input name="phone" type="tel" autocomplete="tel" inputmode="tel" required></label></div>
   <label class="htr-rr-hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
   <fieldset class="htr-rr-interests"><legend>What are you interested in?</legend>
    <?php foreach ( array( 'replacement' => 'Roof replacement', 'repair' => 'Roof repair', 'storm' => 'Storm damage inspection', 'inspection' => 'General inspection' ) as $key => $label ) : ?><label><input type="checkbox" name="interests" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $label ); ?></label><?php endforeach; ?>
   </fieldset>
   <fieldset class="htr-rr-consent"><legend>Text message preferences (optional)</legend><label><input type="checkbox" name="sms_service" value="1"> <?php echo esc_html( $settings['consent_service'] ); ?></label><label><input type="checkbox" name="sms_marketing" value="1"> <?php echo esc_html( $settings['consent_marketing'] ); ?></label></fieldset>
   <button type="submit" class="htr-rr-btn" data-calc-send>Calculate my estimate</button>
   <p data-calc-status role="status"></p>
  </form>
  <?php endif; ?></section>
 </div>
   <div data-calc-result data-calc-stage="4" tabindex="-1" aria-live="polite" hidden></div>
   <p class="htr-rr-calc-footer"><?php echo esc_html( $design['footer'] ); ?></p>
  </section>
 </div>
 <aside class="htr-estimate-guide" aria-label="How your estimate works"><div class="htr-estimate-guide-hero"><?php if ( $sidebar ) : ?><img class="htr-estimate-sidebar-photo" src="<?php echo esc_url( $sidebar ); ?>" alt="" loading="lazy"><?php else : ?><svg viewBox="0 0 400 180" aria-hidden="true"><path d="M100 130 245 20l145 110H100Z" fill="#315a76"/><path d="m120 130 125-95 125 95v50H120Z" fill="#e3e8e9"/><path d="m92 130 153-118 153 118" fill="none" stroke="#fff" stroke-width="8"/><path d="M220 180v-52h50v52" fill="#648aa1"/></svg><?php endif; ?><h3><?php echo nl2br( esc_html( $design['sidebar_title'] ) ); ?></h3></div><ol><?php foreach ( range( 1, 4 ) as $number ) : $index = $number - 1; $item = array( $design[ 'guide_' . $number . '_title' ], $design[ 'guide_' . $number . '_text' ] ); ?><li><span><?php echo (int) $index + 1; ?></span><div><strong><?php echo esc_html( $item[0] ); ?></strong><p><?php echo esc_html( $item[1] ); ?></p></div></li><?php endforeach; ?></ol><div class="htr-estimate-reassurance"><strong><?php echo esc_html( $design['reassurance_title'] ); ?></strong><p><?php echo esc_html( $design['reassurance_text'] ); ?></p></div></aside>
 </div>
</div>
