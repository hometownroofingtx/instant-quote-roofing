<?php
/** Presentation settings for the optional two-page estimate design. */
if (!defined('ABSPATH')) { exit; }
class HTR_RR_Estimate_Design {
    const OPTION='htr_rr_estimate_design';
    public static function fields() {
        return array(
            'tagline'=>array('Header tagline','TEXAS IS OUR HOMETOWN.'),
            'tagline_note'=>array('Header supporting line','A legacy you can count on.'),
            'eyebrow'=>array('Cover label','PERSONALIZED ROOF REPORT'),
            'title'=>array('Cover heading','Your roof. Your estimate.'),
            'range_label'=>array('Estimate label','YOUR ESTIMATED REPLACEMENT RANGE'),
            'estimate_note'=>array('Estimate qualification','Preliminary estimate, subject to inspection'),
            'cta'=>array('Inspection button','SCHEDULE YOUR FREE INSPECTION'),
            'cta_note'=>array('Inspection supporting text','No pressure. No obligation.'),
            'details_eyebrow'=>array('Details label','BUILT FOR YOUR HOME. BACKED BY HOMETOWN.'),
            'details_title'=>array('Details heading','Confidence beyond the estimate.'),
            'warranty_title'=>array('Warranty / reassurance heading','Workmanship protection'),
            'warranty_text'=>array('Approved warranty / reassurance details','Ask your Hometown team about the written warranty coverage available for your project.'),
            'rating'=>array('Approved rating or accreditation (optional)',''),
            'materials_title'=>array('Materials heading','The material makes a difference.'),
            'selected_note'=>array('Selected material details','Product, color and specifications are confirmed in your written proposal.'),
            'alternative_title'=>array('Alternative material heading','Explore other roofing options'),
            'alternative_text'=>array('Alternative material details','Ask about alternative systems and a separate written price. Specifications are confirmed at inspection.'),
            'table_title'=>array('Measurement table heading','Your roof at a glance'),
            'process_title'=>array('Next steps heading','A clear path to your new roof'),
            'process_1'=>array('Process 1 heading','Inspect & verify'),
            'process_1_text'=>array('Process 1 details','Confirm dimensions, roof condition and the details aerial imagery cannot show.'),
            'process_2'=>array('Process 2 heading','Choose your system'),
            'process_2_text'=>array('Process 2 details','Review material options and a written scope with final pricing.'),
            'process_3'=>array('Process 3 heading','Plan your installation'),
            'process_3_text'=>array('Process 3 details','Your Hometown team coordinates the project and explains warranty coverage.'),
            'notes'=>array('Additional estimate notes','Measurements are approximate; one roofing square equals 100 square feet. This report does not assess storm damage. Final price and scope depend on inspection, roof access, tear-off layers, decking, flashing, ventilation, code requirements and selected products.'),
            'closing'=>array('Closing contact heading','Ready for a detailed quote?'),
            'website'=>array('Estimate footer website','HometownRoofingTX.com'),
            'inspection_url'=>array('Inspection booking URL (optional)',''),
            'metric_area'=>array('Roof area label','ROOF SQ FT'),
            'metric_squares'=>array('Roof squares label','ROOFING SQUARES'),
            'metric_pitch'=>array('Pitch label','MAIN PITCH'),
            'metric_faces'=>array('Face count label','ROOF FACES'),
            'table_face'=>array('Table face label','FACE'),
            'table_area'=>array('Table area label','AREA / SQ FT'),
            'table_pitch'=>array('Table pitch label','PITCH'),
            'total_label'=>array('Total label','TOTAL'),
            'footer_label'=>array('Footer page label','ROOF ESTIMATE'),
            'prepared_label'=>array('Prepared date label','Prepared'),
            'call_label'=>array('Call label','Call'),
            'onsite_label'=>array('No price fallback','On-site quote required'),
            'unavailable_faces'=>array('Missing face breakdown text','Roof face breakdown unavailable for this report.'),
            'image_unavailable'=>array('Unavailable imagery text','Property imagery is unavailable. We will verify your roof during inspection.')
        );
    }
    public static function text($design,$key,$editable=false) {
        $value=$design[$key]??'';
        if(!$editable)return nl2br(esc_html($value));
        return '<span contenteditable="plaintext-only" role="textbox" aria-label="'.esc_attr('Edit '.$key).'" data-estimate-copy="'.esc_attr($key).'">'.esc_html($value).'</span>';
    }
    public static function defaults() {
        $out=array('template'=>'classic','diagrams'=>'0','header_color'=>'#080d12','accent_color'=>'#51c3e3','price_color'=>'#102f40');
        foreach(self::fields() as $key=>$field){$out[$key]=$field[1];}return $out;
    }
    public static function sanitize($input) {
        if(!is_array($input)){return new WP_Error('design','Invalid estimate design.');}
        $out=self::defaults();
        if(isset($input['template'])&&!in_array($input['template'],array('classic','estimate'),true)){return new WP_Error('design','Choose a valid report template.');}
        $out['template']=$input['template']??'classic';
        $out['diagrams']=isset($input['diagrams'])&&'1'===$input['diagrams']?'1':'0';
        foreach(self::fields() as $key=>$field){
            if(!isset($input[$key]))continue;
            if(!is_string($input[$key])||strlen($input[$key])>1500){return new WP_Error('design','Keep each report text field below 1,500 bytes.');}
            $out[$key]=sanitize_textarea_field($input[$key]);
        }
        foreach(array('header_color','accent_color','price_color') as $key){if(isset($input[$key])){if(!is_string($input[$key])||!preg_match('/^#[a-f0-9]{6}$/iD',$input[$key]))return new WP_Error('design','Choose a valid six-digit brand color.');$out[$key]=$input[$key];}}
        foreach(array('inspection_url','website') as $key){if(!$out[$key])continue;$url=preg_match('#^https?://#i',$out[$key])?$out[$key]:'https://'.$out[$key];if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),array('http','https'),true)){return new WP_Error('design','Enter a valid HTTP(S) website or booking URL.');}if('inspection_url'===$key)$out[$key]=$url;}
        return $out;
    }
    public static function get(){ $value=self::sanitize(get_option(self::OPTION,self::defaults()));return is_wp_error($value)?self::defaults():$value; }
    public static function posted(){
        if(!isset($_POST['estimate_design']))return self::get();
        $value=self::sanitize(wp_unslash($_POST['estimate_design']));
        if(is_wp_error($value))wp_die(esc_html($value->get_error_message()),'',array('response'=>400));return $value;
    }
    public static function controls(){
        $design=self::get(); ?>
        <details class="htr-estimate-controls"><summary>Report template &amp; estimate design</summary>
        <p>Choose the new estimate design to match the supplied PDF. Classic preserves the section-based report. Header/footer logo and contact settings are reused. Click wording in either preview to edit it directly, or use the fields below. Drag order applies to Classic.</p>
        <label>Report template<select name="estimate_design[template]" aria-label="Report template"><option value="classic"<?php echo $design['template']==='classic'?' selected':''; ?>>Classic report</option><option value="estimate"<?php echo $design['template']==='estimate'?' selected':''; ?>>Two-page estimate design</option></select></label>
        <label>Diagram appendix<select name="estimate_design[diagrams]" aria-label="Diagram appendix"><option value="0"<?php echo $design['diagrams']==='0'?' selected':''; ?>>Not included</option><option value="1"<?php echo $design['diagrams']==='1'?' selected':''; ?>>Include available measured roof diagrams</option></select></label>
        <?php foreach(array('header_color'=>'Header background','accent_color'=>'Accent / CTA color','price_color'=>'Price panel background') as $key=>$label): ?><label><?php echo esc_html($label); ?><input type="color" name="estimate_design[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($design[$key]); ?>"></label><?php endforeach; ?>
        <p class="description">Enter approved warranty/rating claims only. Prices, material names and measurements always come from the saved report.</p>
        <?php foreach(self::fields() as $key=>$field): ?><label for="estimate-<?php echo esc_attr($key); ?>"><?php echo esc_html($field[0]); ?></label><textarea id="estimate-<?php echo esc_attr($key); ?>" name="estimate_design[<?php echo esc_attr($key); ?>]" rows="2" maxlength="1500"><?php echo esc_html($design[$key]); ?></textarea><?php endforeach; ?>
        </details>
        <?php
    }
}