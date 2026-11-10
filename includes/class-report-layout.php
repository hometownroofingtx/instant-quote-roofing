<?php
/** Report-only layout options. No customer record or price is edited here. */
if (!defined('ABSPATH')) { exit; }
class HTR_RR_Report_Layout {
    const OPTION = 'htr_rr_report_layout';
    public static function init() {
        // Register after HTR_RR_Admin creates the parent and its Overview submenu.
        add_action('admin_menu', array(__CLASS__, 'menu'), 20);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_post_htr_rr_layout_save', array(__CLASS__, 'save'));
        add_action('admin_post_htr_rr_layout_preview', array(__CLASS__, 'preview'));
    }
    public static function core() {
        return array('header'=>'Report header', 'summary'=>'Roof summary & estimate', 'aerial'=>'Aerial view', 'diagrams'=>'Roof diagrams & measurement details', 'next'=>'Next steps', 'footer'=>'Disclaimer / footer');
    }
    public static function defaults() {
        return array_map(function($type) { return array('type'=>$type); }, array_keys(self::core()));
    }
    public static function copy_defaults() {
        return array(
            'header'=>array('title'=>'Roof Measurement Report','prepared'=>'Prepared','for'=>'for'),
            'summary'=>array('unit'=>'sq ft','per_unit'=>'per sq ft','roof'=>'roof','title'=>'Roof summary','area'=>'Total roof area','squares'=>'Roofing squares','pitch'=>'Main pitch','faces'=>'Roof faces','estimate'=>'Estimated price range','onsite'=>'On-site quote','onsite_note'=>'This roof type is priced after a free inspection.','review'=>'Our team will double-check these measurements and confirm your estimate.','manual'=>'Calculator estimate using customer-entered roof dimensions. These measurements have not been verified.','unavailable'=>'We could not measure this roof from aerial imagery. Our team has your details and will measure it in person, free of charge.'),
            'aerial'=>array('title'=>'Aerial view'),
            'diagrams'=>array('small_prefix'=>'This measured only','small_note'=>'sq ft, which looks like a small structure (shed, carport or garage) rather than the house. Our team will confirm the right building.','iso'=>'3D roof diagram','plan'=>'Roof plan view','note'=>'Drawn to the measured size and pitch. Exact ridge, hip, valley and eave lines are confirmed during your free inspection.','faces'=>'Roof faces measured','number'=>'#','area'=>'Area (sq ft)','pitch'=>'Pitch','source'=>'Measured from Google aerial imagery','taken'=>'taken','quality'=>'detail level','inspection'=>'Ridge, hip, valley and eave lengths are measured during your free inspection.'),
            'next'=>array('title'=>'Next steps','details'=>'Schedule your free inspection so we can confirm these measurements and give you a final quote','call'=>'call'),
            'footer'=>array('disclaimer'=>'','footer_title'=>'','footer_details'=>'')
        );
    }
    public static function copy_text($section,$key,$editable=false,$fallback=null) {
        $defaults=self::copy_defaults();
        $value=$section['copy'][$key] ?? $fallback ?? ($defaults[$section['type']][$key] ?? '');
        if (!$editable) { return nl2br(esc_html($value)); }
        return '<span contenteditable="plaintext-only" role="textbox" aria-label="'.esc_attr('Edit '.$key).'" data-report-copy="'.esc_attr($key).'">'.esc_html($value).'</span>';
    }
    public static function style_defaults() {
        return array('background'=>'none', 'from'=>'#103b5c', 'to'=>'#0875d1', 'color'=>'#ffffff', 'padding'=>24, 'align'=>'left', 'logo_size'=>56, 'logo_id'=>0, 'footer_title'=>'', 'footer_details'=>'', 'footer_design'=>'plain', 'contact_phone'=>'', 'contact_website'=>'', 'contact_area'=>'', 'accent'=>'#d6202b');
    }
    public static function sanitize_style($input) {
        if (!is_array($input)) { return new WP_Error('style', 'Invalid report style.'); }
        $style=self::style_defaults();
        foreach (array('from','to','color','accent') as $key) {
            if (isset($input[$key])) {
                if (!is_string($input[$key]) || !preg_match('/^#[a-f0-9]{6}$/iD', $input[$key])) { return new WP_Error('style', 'Choose a valid six-digit color.'); }
                $style[$key]=$input[$key];
            }
        }
        foreach (array('background'=>array('none','solid','gradient'), 'align'=>array('left','center','right'), 'footer_design'=>array('plain','contact')) as $key=>$allowed) {
            if (isset($input[$key])) { if (!in_array($input[$key],$allowed,true)) { return new WP_Error('style','Invalid style option.'); } $style[$key]=$input[$key]; }
        }
        foreach (array('padding'=>array(0,64),'logo_size'=>array(24,120)) as $key=>$bounds) {
            if (isset($input[$key])) { if (!is_scalar($input[$key]) || !is_numeric($input[$key])) { return new WP_Error('style','Invalid style size.'); } $style[$key]=max($bounds[0],min($bounds[1],(int)$input[$key])); }
        }
        if (isset($input['logo_id'])) {
            if (!is_scalar($input['logo_id']) || !is_numeric($input['logo_id']) || (int)$input['logo_id']<0) { return new WP_Error('style','Choose a valid logo image.'); }
            $id=(int)$input['logo_id'];
            if ($id && !wp_attachment_is_image($id)) { return new WP_Error('style','Choose a logo image from the Media Library.'); }
            $style['logo_id']=$id;
        }
        foreach (array('footer_title'=>200,'footer_details'=>5000,'contact_phone'=>80,'contact_website'=>300,'contact_area'=>500) as $key=>$max) {
            if (isset($input[$key])) { if (!is_string($input[$key]) || strlen($input[$key])>$max) { return new WP_Error('style','Footer details are too long or invalid.'); } $style[$key]=sanitize_textarea_field($input[$key]); }
        }
        if ($style['contact_website']) {
            $url=preg_match('#^https?://#i',$style['contact_website']) ? $style['contact_website'] : 'https://'.$style['contact_website'];
            if (!filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),array('http','https'),true)) { return new WP_Error('style','Enter a valid website domain or HTTP(S) URL.'); }
        }
        return $style;
    }
    public static function style_attributes($section) {
        if (!in_array($section['type'],array('header','footer'),true) || !isset($section['style'])) { return ''; }
        $style=self::sanitize_style($section['style']);
        if (is_wp_error($style) || $style['background']==='none') { return ''; }
        $background=$style['background']==='gradient' ? 'linear-gradient(135deg,'.$style['from'].','.$style['to'].')' : $style['from'];
        return ' data-report-style="'.$style['align'].'" style="'.esc_attr('background:'.$background.';color:'.$style['color'].';padding:'.$style['padding'].'px;--report-logo-size:'.$style['logo_size'].'px;--report-section-padding:'.$style['padding'].'px;text-align:'.$style['align'].';').'"';
    }
    public static function allowed_html() {
        return array('p'=>array(), 'br'=>array(), 'strong'=>array(), 'em'=>array(), 'ul'=>array(), 'ol'=>array(), 'li'=>array(), 'a'=>array('href'=>true, 'title'=>true), 'h3'=>array(), 'blockquote'=>array());
    }
    public static function text_html($content) {
        return wp_kses(wpautop($content), self::allowed_html());
    }
    public static function sanitize($sections) {
        if (!is_array($sections) || count($sections)>36) { return new WP_Error('layout', 'Use no more than 36 report sections.'); }
        $clean=array(); $seen=array(); $core=self::core();
        foreach ($sections as $section) {
            if (!is_array($section) || !is_string($section['type'] ?? null)) { return new WP_Error('layout', 'Invalid report section.'); }
            $type=$section['type'];
            if ('text' === $type) {
                if (!is_string($section['title'] ?? '') || !is_string($section['content'] ?? '') || strlen($section['content'] ?? '')>20000 || strlen($section['title'] ?? '')>200) { return new WP_Error('layout', 'Keep text sections below 20,000 bytes and headings below 200 bytes.'); }
                $clean[]=array('type'=>'text', 'title'=>sanitize_text_field($section['title'] ?? ''), 'content'=>wp_kses($section['content'] ?? '', self::allowed_html()));
            } elseif (isset($core[$type]) && !isset($seen[$type])) {
                $entry=array('type'=>$type);
                if (isset($section['copy'])) { if (!is_array($section['copy'])) { return new WP_Error('copy','Invalid report wording.'); } foreach ($section['copy'] as $key=>$text) { if (!array_key_exists($key,self::copy_defaults()[$type]) || !is_string($text) || strlen($text)>5000) { return new WP_Error('copy','Invalid or oversized report wording.'); } $entry['copy'][$key]=sanitize_textarea_field($text); } }
                if (in_array($type,array('header','footer'),true) && isset($section['style'])) { $style=self::sanitize_style($section['style']); if (is_wp_error($style)) { return $style; } $entry['style']=$style; }
                $clean[]=$entry; $seen[$type]=true;
            } else { return new WP_Error('layout', 'Unknown or repeated built-in report section.'); }
        }
        // Generated data and disclaimers are always retained.
        foreach ($core as $type=>$label) { if (!isset($seen[$type])) { $clean[]=array('type'=>$type); } }
        if (count($clean)>36) { return new WP_Error('layout', 'Use no more than 36 sections including the built-in sections.'); }
        return $clean;
    }
    public static function get() {
        $saved=get_option(self::OPTION, self::defaults());
        if (is_array($saved)) { foreach ($saved as &$section) { if (is_array($section) && isset($section['style']) && is_array($section['style']) && !empty($section['style']['logo_id']) && is_numeric($section['style']['logo_id']) && !wp_attachment_is_image((int)$section['style']['logo_id'])) { $section['style']['logo_id']=0; } } unset($section); }
        $layout=self::sanitize($saved);
        return is_wp_error($layout) ? self::defaults() : $layout;
    }
    public static function menu() {
        add_submenu_page('htr-rr', 'Report Layout', 'Report Layout', 'manage_options', 'htr-rr-layout', array(__CLASS__, 'page'));
    }
    public static function assets() {
        if (($_GET['page'] ?? '') !== 'htr-rr-layout') { return; }
        wp_enqueue_media();
        wp_enqueue_script('htr-rr-report-layout', HTR_RR_URL.'assets/js/report-layout.js', array('jquery-ui-sortable'), HTR_RR_VERSION, true);
        wp_enqueue_style('htr-rr-report-layout', HTR_RR_URL.'assets/css/report-layout.css', array(), HTR_RR_VERSION);
    }
    private static function authorize() {
        if (!current_user_can('manage_options')) { wp_die('Not allowed.', '', array('response'=>403)); }
        check_admin_referer('htr_rr_report_layout');
    }
    private static function posted() {
        $raw=wp_unslash($_POST['layout_json'] ?? '');
        if (!is_string($raw) || strlen($raw)>500000) { wp_die('Invalid report layout.', '', array('response'=>400)); }
        $layout=self::sanitize(json_decode($raw, true));
        if (is_wp_error($layout)) { wp_die(esc_html($layout->get_error_message()), '', array('response'=>400)); }
        return $layout;
    }
    public static function save() {
        self::authorize(); $layout=self::posted();
        $estimate_design=HTR_RR_Estimate_Design::posted();
        update_option(self::OPTION, $layout, false);
        update_option(HTR_RR_Estimate_Design::OPTION, $estimate_design, false);
        wp_safe_redirect(admin_url('admin.php?page=htr-rr-layout&saved=1')); exit;
    }
    public static function preview() {
        self::authorize(); $report_layout=self::posted();
        $estimate_design=HTR_RR_Estimate_Design::posted();
        $id=isset($_POST['report_id']) && is_scalar($_POST['report_id']) ? absint($_POST['report_id']) : 0;
        if ($id) {
            $report=HTR_RR_DB::get_report($id);
            if (!$report) { wp_die('Report not found.', '', array('response'=>404)); }
            $v=HTR_RR_Report_Page::report_view($report); $layout_preview='PRIVATE ADMIN PREVIEW - Report #'.$id;
        } else {
            $v=array('address'=>'Sample property - layout preview only', 'firstName'=>'Sample customer', 'customerDetails'=>array('name'=>'Sample customer','email'=>'sample@example.com','phone'=>'(555) 010-0123'), 'createdAt'=>gmdate('Y-m-d H:i:s'), 'measured'=>true, 'customerEntered'=>true, 'areaSqft'=>2500, 'squares'=>25, 'pitch'=>'6/12', 'faces'=>4, 'priceLow'=>15000, 'priceHigh'=>20000, 'roofType'=>'Sample material', 'rateLow'=>6, 'rateHigh'=>8, 'complexity'=>'Sample', 'needsReview'=>true, 'lat'=>0, 'lng'=>0, 'facesDetail'=>array(), 'buildingBox'=>null, 'imageryDate'=>'', 'imageryQuality'=>'', 'phone'=>HTR_RR_Settings::get('company_phone'), 'disclaimer'=>HTR_RR_Settings::get('disclaimer'));
            $layout_sample=true;
            $layout_preview='SAMPLE DATA - Layout preview only. These are not customer measurements or a quote.';
        }
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
        $layout_preview_key=isset($_POST['preview_key']) && is_string($_POST['preview_key']) && preg_match('/^[0-9]{1,20}$/D',$_POST['preview_key']) ? $_POST['preview_key'] : '';
        unset($_GET['print']);
        include HTR_RR_DIR.'templates/report.php'; exit;
    }
    public static function page() {
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $layout=self::get(); $core=self::core();
        ?>
        <div class="wrap htr-layout"><header class="htr-layout-page-header"><div><p class="htr-layout-eyebrow">Roof Reports / Document design</p><h1>Report Layout</h1><p>Design the report your customers see and download.</p></div><span class="htr-layout-header-tag">Online report &amp; PDF</span></header>
        <p>Arrange your report sections and add text for the report header, body or footer. Changes apply to online reports and Save as PDF. Pricing, measurements and the website header/footer are unaffected.</p>
        <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Report layout saved.</p></div><?php endif; ?>
        <div class="htr-layout-workspace">
        <form id="htr-layout-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('htr_rr_report_layout'); ?>
            <input type="hidden" name="preview_key" value="0"><input type="hidden" name="action" value="htr_rr_layout_save"><input type="hidden" name="layout_json" id="htr-layout-json" value="<?php echo esc_attr(wp_json_encode($layout)); ?>">
            <?php HTR_RR_Estimate_Design::controls(); ?>
            <div class="htr-layout-toolbar"><button type="button" class="button" data-report-style-open="header">Header style</button><button type="button" class="button" data-report-style-open="footer">Footer style</button><button type="button" class="button" id="htr-layout-add">Add text section</button><button type="button" class="button" id="htr-layout-reset">Restore default order</button></div>
            <p class="description">Drag a section by its handle, or use Move up / Move down. Built-in sections stay in the report; custom text sections can be removed. Basic HTML supports paragraphs, bold, italics, lists and links.</p>
            <div id="htr-layout-sections"></div><p id="htr-layout-status" role="status" aria-live="polite"></p>
            <div class="htr-layout-actions"><button type="submit" class="button button-primary">Save report layout</button><label>Preview report ID <input type="number" name="report_id" min="1" placeholder="Optional"></label><button type="button" class="button" id="htr-layout-preview">Preview unsaved changes</button></div>
            <p class="description">Leave the report ID empty for clearly labeled sample data. Enter a saved report ID to preview actual data privately. Preview does not save changes.</p>
        </form>
        <section class="htr-layout-preview-panel" aria-label="Report preview"><h2>Report preview</h2><p class="description">Select Preview unsaved changes, then click any wording to edit it. Generated prices, measurements and customer details remain read-only. Save the layout to publish wording changes.</p>
        <iframe id="htr-layout-preview-frame" name="htr-layout-preview" title="Report layout preview" sandbox="allow-scripts allow-same-origin allow-modals" hidden></iframe>
        </section></div>
        <dialog id="htr-report-style-dialog" aria-labelledby="htr-report-style-title">
            <div class="htr-style-dialog-header"><h2 id="htr-report-style-title">Report style</h2><button type="button" class="button" data-style-close aria-label="Close style settings">&times;</button></div>
            <p>Applies to the preview, online report and PDF. Save the report layout to publish changes.</p>
            <div class="htr-style-fields">
                <label>Background<select aria-label="Background" data-style="background"><option value="none">Original style</option><option value="solid">Solid color</option><option value="gradient">Blue gradient / custom gradient</option></select></label>
                <label>Start color<input type="color" data-style="from" value="#103b5c"></label>
                <label>End color<input type="color" data-style="to" value="#0875d1"></label>
                <label>Text color<input type="color" data-style="color" value="#ffffff"></label>
                <label>Padding (px)<input type="number" data-style="padding" min="0" max="64" value="24"></label>
                <label>Alignment<select aria-label="Alignment" data-style="align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
                <div class="htr-style-logo-control"><label>Report logo<input type="hidden" data-style="logo_id" value="0"></label><img data-logo-preview alt="Selected report logo" hidden><div><button type="button" class="button" data-logo-choose>Choose logo</button> <button type="button" class="button" data-logo-clear>Use default / no footer logo</button></div></div>
                <label data-style-logo>Logo height (px)<input type="number" data-style="logo_size" min="24" max="120" value="56"></label>
                <div data-footer-fields><label>Footer design<select data-style="footer_design" aria-label="Footer design"><option value="plain">Standard footer</option><option value="contact">Contact bar with red accent</option></select></label><label>Phone<input type="text" data-style="contact_phone" maxlength="80" placeholder="Uses your company phone when empty"></label><label>Website<input type="text" data-style="contact_website" maxlength="300" placeholder="hometownroofingtx.com"></label><label>Service area<textarea data-style="contact_area" rows="2" maxlength="500" placeholder="Serving San Antonio and nearby areas"></textarea></label><label>Accent color<input type="color" data-style="accent" value="#d6202b"></label><label>Footer heading<input type="text" data-style="footer_title" maxlength="200"></label><label>Footer details<textarea data-style="footer_details" rows="5" maxlength="5000" placeholder="Company name, address, phone, email or other report details"></textarea></label></div>
            </div>
            <p class="description">For colored PDF backgrounds, enable Background graphics in your browser print settings. The report header and footer repeat on printed pages.</p>
            <div class="htr-style-dialog-actions"><button type="button" class="button" data-style-close>Cancel</button><button type="button" class="button button-primary" id="htr-style-apply">Apply style</button></div>
        </dialog>
        <script type="application/json" id="htr-layout-core"><?php echo wp_json_encode($core, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        </div>
        <?php
    }
}