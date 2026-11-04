<?php
/** Public calculator using saved pricing and the existing lead pipeline. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$settings = HTR_RR_Settings::all();
$ghl_mode = 'ghl_form' === HTR_RR_Settings::public_config()['contactMode'];
?>
<div class="htr-rr htr-rr-calculator" data-htr-calculator>
 <div class="htr-rr-calc-layout">
  <section class="htr-rr-card">
   <h3>Roof details</h3><p class="htr-rr-sub">Enter your roof details to preview an estimate.</p>
   <form data-calc-inputs>
    <label>Roof type<select name="material" required><?php foreach ( $settings['materials'] as $key => $material ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $material['label'] ); ?></option><?php endforeach; ?></select></label>
    <label>Roof area (sq ft)<input type="number" name="area_sqft" min="100" max="100000" step="1" placeholder="e.g. 2850" required></label>
    <div class="htr-rr-row"><label>Pitch (/12)<input type="number" name="pitch_x12" min="0" max="24" step="1" value="6" required></label><label>Roof faces<input type="number" name="faces" min="1" max="100" step="1" value="8" required></label></div>
    <label>Service area<select name="town" required><?php foreach ( $settings['areas'] as $index => $area ) : ?><option value="<?php echo (int) $index; ?>"><?php echo esc_html( $area['town'] ); ?></option><?php endforeach; ?></select></label>
    <p class="htr-rr-sub">After selecting an address, pricing uses its nearest service area.</p>
   <div class="htr-rr-calc-actions"><button type="submit" class="htr-rr-btn" data-calc-calculate>Continue to my estimate</button> <button type="reset" class="htr-rr-link">Reset</button></div>
   </form>
   <p class="htr-rr-error" data-calc-error role="alert" hidden></p>
  </section>
  <section class="htr-rr-card">
   <h3>Estimate preview</h3><p class="htr-rr-sub">Enter your address and contact details to calculate and reveal your estimate.</p>
 <div class="htr-rr-calc-contact" data-calc-contact>
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
   </div>
  </div>
  <?php if ( $ghl_mode ) : ?>
   <button type="button" class="htr-rr-btn" data-calc-continue>Continue to my estimate</button>
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
  <?php endif; ?>
 </div>
   <div data-calc-result aria-live="polite" hidden></div>
   <p class="htr-rr-calc-footer">Preliminary estimate using dimensions you enter. Final pricing may vary after an on-site inspection.</p>
  </section>
 </div>
</div>
