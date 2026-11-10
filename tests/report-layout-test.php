<?php
/** Isolated layout validation/render tests. No live customer data or provider requests. */
define('ABSPATH', __DIR__); define('HTR_RR_DIR', dirname(__DIR__).'/'); define('HTR_RR_URL', '../'); define('HTR_RR_VERSION', 'test');
class WP_Error { public function __construct($code,$message){} }
function is_wp_error($value){return $value instanceof WP_Error;}
function get_option($key,$default=false){return $key==='htr_rr_estimate_design' ? ($GLOBALS['estimate_option'] ?? $default) : ($GLOBALS['layout_option'] ?? $default);}
function sanitize_text_field($value){return trim(strip_tags($value));}
function wp_kses($value,$allowed){return strip_tags($value, '<'.implode('><',array_keys($allowed)).'>');} // Test double; production uses WordPress KSES.
function wpautop($value){return '<p>'.$value.'</p>';}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function esc_attr($value){return esc_html($value);}
function esc_url($value){return esc_html($value);}
function wp_date($format,$timestamp){return gmdate($format,$timestamp);}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
class HTR_RR_Settings {public static function get($key){return $key==='google_browser_key' ? ($GLOBALS['browser_fixture'] ?? '') : '';}}
class HTR_RR_Report_Page {public static function money($value){return '$'.number_format($value);}}
require HTR_RR_DIR.'includes/class-estimate-design.php';
require HTR_RR_DIR.'includes/class-report-layout.php';
$checks=0;
function check($ok,$message){global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$defaults=HTR_RR_Report_Layout::defaults();check(count($defaults)===6,'Six original sections');
$layout=HTR_RR_Report_Layout::sanitize(array(array('type'=>'footer'),array('type'=>'text','title'=>'<b>Our process</b>','content'=>'<strong>Custom instructions</strong>')));
check($layout[0]['type']==='footer' && $layout[1]['type']==='text','Custom order retained');
check($layout[1]['title']==='Our process','Heading sanitized');
check(count($layout)===7,'All missing built-ins restored');
check(is_wp_error(HTR_RR_Report_Layout::sanitize(array(array('type'=>'../../wp-config')))),'Unknown section/path rejected');
check(is_wp_error(HTR_RR_Report_Layout::sanitize(array(array('type'=>'header'),array('type'=>'header')))),'Duplicate built-in rejected');
check(is_wp_error(HTR_RR_Report_Layout::sanitize(array_fill(0,37,array('type'=>'text')))),'Section count bounded');
check(is_wp_error(HTR_RR_Report_Layout::sanitize(array(array('type'=>'text','content'=>str_repeat('a',20001))))),'Text size bounded');
check(is_wp_error(HTR_RR_Report_Layout::sanitize(array(array('type'=>'text','content'=>array())))),'Malformed content rejected');
check(!isset(HTR_RR_Report_Layout::allowed_html()['script']) && !isset(HTR_RR_Report_Layout::allowed_html()['iframe']) && !isset(HTR_RR_Report_Layout::allowed_html()['img']),'Unsafe/embed/tracking tags disallowed');
$GLOBALS['layout_option']=array(array('type'=>'bad'));check(HTR_RR_Report_Layout::get()===$defaults,'Bad saved layout falls back');
$v=array('address'=>'Sample property', 'firstName'=>'Preview', 'customerDetails'=>array('name'=>'Test Customer','email'=>'customer@example.test','phone'=>'555-0100'), 'createdAt'=>'2026-11-09 00:00:00','measured'=>true,'customerEntered'=>false,'areaSqft'=>2500,'squares'=>25,'pitch'=>'6/12','faces'=>4,'priceLow'=>15000,'priceHigh'=>20000,'roofType'=>'Sample','rateLow'=>6,'rateHigh'=>8,'complexity'=>'Sample','needsReview'=>true,'lat'=>29,'lng'=>-98,'facesDetail'=>array(array('area'=>2500,'pitch'=>6)),'buildingBox'=>null,'imageryDate'=>'2026-01','imageryQuality'=>'HIGH','phone'=>'','disclaimer'=>'Test disclaimer');
$report_layout=$layout;ob_start();include HTR_RR_DIR.'templates/report.php';$html=ob_get_clean();
check(strpos($html,'Test disclaimer')<strpos($html,'Our process'),'Renderer respects custom order');
check(strpos($html,'$15,000')!==false && strpos($html,'2,500 sq ft')!==false,'Actual generated price/metrics retained');
check(substr_count($html,'id="htr-rr-diagram-data"')===1,'Diagram script rendered once');
$v['customerEntered']=true;ob_start();include HTR_RR_DIR.'templates/report.php';$manual=ob_get_clean();check(strpos($manual,'id="htr-rr-diagram-data"')===false,'Manual measurements omit aerial diagram');
$v['measured']=false;ob_start();include HTR_RR_DIR.'templates/report.php';$missing=ob_get_clean();check(strpos($missing,'$15,000')===false && strpos($missing,'We could not measure')!==false,'Missing measurements retain honest fallback');
file_put_contents(__DIR__.'/.report-layout-preview.html',$html);
echo "Passed $checks isolated report layout checks. KSES is a test double, not a production security proof.\n";
function current_user_can($cap){return $GLOBALS['layout_cap'] ?? true;}
function admin_url($path){return 'https://example.test/wp-admin/'.$path;}
function wp_nonce_field($action){echo '<input type="hidden" name="_wpnonce" value="fixture">';}
ob_start();HTR_RR_Report_Layout::page();$admin=ob_get_clean();
file_put_contents(__DIR__.'/.report-layout-admin-preview.html','<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.file_get_contents(HTR_RR_DIR.'assets/css/report-layout.css').'</style></head><body>'.$admin.'<script>window.jQuery=function(){return {sortable:function(options){window.sortableOptions=options;}}};HTMLFormElement.prototype.submit=function(){window.previewSubmission={action:this.elements.action.value,target:this.target,layout:JSON.parse(this.elements.layout_json.value)}};</script><script>'.file_get_contents(HTR_RR_DIR.'assets/js/report-layout.js').'</script></body></html>');
function wp_die($message,$title='',$args=array()){throw new RuntimeException($message);}
function check_admin_referer($action){if(empty($GLOBALS['layout_nonce']))throw new RuntimeException('Invalid nonce');}
foreach (array('save','preview') as $method) {
 $GLOBALS['layout_cap']=false;$denied=false;try{HTR_RR_Report_Layout::$method();}catch(RuntimeException $e){$denied=$e->getMessage()==='Not allowed.';}check($denied,'Capability checked before '.$method);
 $GLOBALS['layout_cap']=true;$denied=false;try{HTR_RR_Report_Layout::$method();}catch(RuntimeException $e){$denied=$e->getMessage()==='Invalid nonce';}check($denied,'Nonce checked before '.$method);
}
echo "Report layout checks including permission boundaries: $checks\n";
function add_action($hook,$callback,$priority=10){$GLOBALS['layout_hooks'][$hook]=$priority;}
HTR_RR_Report_Layout::init();
check($GLOBALS['layout_hooks']['admin_menu']>10,'Layout submenu registers after parent/Overview, preserving main menu destination');
echo "Menu registration regression check passed.\n";
check(is_wp_error(HTR_RR_Report_Layout::sanitize_style(array('from'=>'red;position:fixed'))),'CSS injection rejected');
check(is_wp_error(HTR_RR_Report_Layout::sanitize_style(array('align'=>'unsafe'))),'Unknown alignment rejected');
$styled=HTR_RR_Report_Layout::sanitize(array(array('type'=>'header','style'=>array('background'=>'gradient','from'=>'#103b5c','to'=>'#0875d1','color'=>'#ffffff','padding'=>32,'align'=>'center'))));
check($styled[0]['style']['padding']===32,'Style survives layout sanitization');
check(strpos(HTR_RR_Report_Layout::style_attributes($styled[0]),'linear-gradient(135deg,#103b5c,#0875d1)')!==false,'Gradient CSS generated from validated colors');
check(HTR_RR_Report_Layout::style_attributes(array('type'=>'footer','style'=>array('background'=>'none')))==='','Existing styling is default');
$report_layout=$styled;$v['measured']=true;ob_start();include HTR_RR_DIR.'templates/report.php';$styled_html=ob_get_clean();
check(strpos($styled_html,'data-report-style="center"')!==false,'Report renderer applies chosen styling');
echo "Style regression checks passed. Total checks: $checks\n";
function sanitize_textarea_field($value){return trim(strip_tags($value));}
function wp_attachment_is_image($id){return $id===42;}
function wp_get_attachment_image_url($id,$size){return $id===42?'https://example.test/logo.png':false;}
check(is_wp_error(HTR_RR_Report_Layout::sanitize_style(array('logo_id'=>99))),'Non-image logo rejected');
$style=HTR_RR_Report_Layout::sanitize_style(array('logo_id'=>42,'footer_title'=>'Contact us','footer_details'=>"Office address\nPhone: 210-555-0100"));
check($style['logo_id']===42 && strpos($style['footer_details'],'Phone:')!==false,'Image and footer details retained');
$report_layout=HTR_RR_Report_Layout::sanitize(array(array('type'=>'header','style'=>$style),array('type'=>'footer','style'=>$style)));
ob_start();include HTR_RR_DIR.'templates/report.php';$custom_html=ob_get_clean();
check(substr_count($custom_html,'https://example.test/logo.png')===2,'Logo renders in header and footer');
check(strpos($custom_html,'Office address<br')!==false,'Footer preserves line breaks');
echo "Logo/footer checks passed. Total checks: $checks\n";
$copy_layout=HTR_RR_Report_Layout::sanitize(array(array('type'=>'header','copy'=>array('title'=>'Custom report heading'))));
check($copy_layout[0]['copy']['title']==='Custom report heading','Edited wording preserved');
check(is_wp_error(HTR_RR_Report_Layout::sanitize(array(array('type'=>'header','copy'=>array('priceLow'=>'1'))))),'Generated values cannot be overridden as wording');
check(strpos(HTR_RR_Report_Layout::copy_text($copy_layout[0],'title',true),'contenteditable=')!==false,'Private preview exposes editable wording');
check(strpos(HTR_RR_Report_Layout::copy_text($copy_layout[0],'title',false),'contenteditable=')===false,'Public report wording stays read-only');
$report_layout=HTR_RR_Report_Layout::defaults();$layout_preview='PRIVATE EDITABLE PREVIEW';$layout_preview_key='1';
ob_start();include HTR_RR_DIR.'templates/report.php';$inline_html=ob_get_clean();
file_put_contents(__DIR__.'/.report-inline-preview.html',$inline_html);
echo "Inline copy checks passed. Total checks: $checks\n";
check(is_wp_error(HTR_RR_Report_Layout::sanitize_style(array('contact_website'=>'javascript:alert(1)'))),'Unsafe footer URL rejected');
$contact_style=HTR_RR_Report_Layout::sanitize_style(array('footer_design'=>'contact','contact_phone'=>'(830) 283-4314','contact_website'=>'hometownroofingtx.com','contact_area'=>'Serving San Antonio and nearby areas'));
check(!is_wp_error($contact_style),'Contact bar settings accepted');
$report_layout=HTR_RR_Report_Layout::sanitize(array(array('type'=>'footer','style'=>$contact_style)));unset($layout_preview);ob_start();include HTR_RR_DIR.'templates/report.php';$contact_html=ob_get_clean();
check(strpos($contact_html,'href="tel:8302834314"')!==false,'Footer phone link rendered');
check(strpos($contact_html,'href="https://hometownroofingtx.com"')!==false,'Footer website link rendered');
check(strpos($contact_html,'report-contact-bar')!==false && strpos($contact_html,'Serving San Antonio')!==false,'Contact bar and service area rendered');
file_put_contents(__DIR__.'/.report-contact-preview.html',$contact_html);
echo "Contact footer checks passed. Total checks: $checks\n";
$GLOBALS['browser_fixture']='fixture';$v['customerEntered']=false;$v['measured']=true;$v['buildingBox']=array('sw'=>array('latitude'=>29.0,'longitude'=>-98.0),'ne'=>array('latitude'=>29.0002,'longitude'=>-97.9998));
$report_layout=HTR_RR_Report_Layout::sanitize(array(array('type'=>'header'),array('type'=>'summary'),array('type'=>'aerial'),array('type'=>'diagrams'),array('type'=>'next'),array('type'=>'footer','style'=>$contact_style)));
ob_start();include HTR_RR_DIR.'templates/report.php';$full_html=ob_get_clean();file_put_contents(__DIR__.'/.report-full-preview.html',$full_html);
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}

check(HTR_RR_Estimate_Design::get()['template']==='classic','Existing reports remain Classic by default');
check(is_wp_error(HTR_RR_Estimate_Design::sanitize(array('template'=>'../../bad'))),'Unknown template rejected');
check(is_wp_error(HTR_RR_Estimate_Design::sanitize(array('inspection_url'=>'javascript:alert(1)'))),'Unsafe inspection link rejected');
$v['faces']=9;$v['facesDetail']=array_map(function($area){return array('area'=>$area,'pitch'=>6);},array(300,300,300,300,300,300,300,200,200));
$estimate_design=HTR_RR_Estimate_Design::sanitize(array('template'=>'estimate'));
$v['phone']='(830) 283-4314';unset($layout_preview);$report_layout=HTR_RR_Report_Layout::defaults();
ob_start();include HTR_RR_DIR.'templates/report.php';$estimate_html=ob_get_clean();
check(substr_count($estimate_html,'class="estimate-page')===2,'Standard measured report has cover/details pages');
check(strpos($estimate_html,'$15,000 - $20,000')!==false,'New design uses existing report pricing');
check(strpos($estimate_html,'2,500')!==false,'New design uses existing measurements');
check(strpos($estimate_html,'LIFETIME TRANSFERABLE')===false,'Unapproved warranty claims not invented');
check(strpos($estimate_html,'assets/img/bbb-rating.png')!==false&&strpos($estimate_html,'<figcaption>A+ BBB Rating</figcaption>')!==false,'Requested BBB artwork and caption included');
file_put_contents(__DIR__.'/.estimate-design-preview.html',$estimate_html);
$v['facesDetail']=array_fill(0,35,array('area'=>100,'pitch'=>6));
ob_start();include HTR_RR_DIR.'templates/report.php';$long_estimate=ob_get_clean();
check(substr_count($long_estimate,'class="estimate-page')===4,'Long measurement table gets continuation pages');
check(strpos($long_estimate,'>35</td>')!==false,'Continuation retains final face');
file_put_contents(__DIR__.'/.estimate-design-long-preview.html',$long_estimate);
$v['measured']=false;$v['priceLow']=null;$v['priceHigh']=null;$v['facesDetail']=array();$v['customerEntered']=true;
ob_start();include HTR_RR_DIR.'templates/report.php';$unavailable_estimate=ob_get_clean();
check(strpos($unavailable_estimate,'On-site quote required')!==false,'No measurement does not invent pricing');
check(strpos($unavailable_estimate,'Based on customer-entered dimensions')!==false,'Manual source clearly labeled');
echo "Estimate design checks passed. Total checks: $checks\n";
check(is_wp_error(HTR_RR_Estimate_Design::sanitize(array('header_color'=>'red;position:fixed'))),'Estimate brand CSS injection rejected');
$estimate_design=HTR_RR_Estimate_Design::sanitize(array('template'=>'estimate','diagrams'=>'1'));
$v['measured']=true;$v['customerEntered']=false;$v['facesDetail']=array(array('area'=>2500,'pitch'=>6));
$report_layout=HTR_RR_Report_Layout::sanitize(array(array('type'=>'text','title'=>'Extra details','content'=>'Retained custom report text.')));
ob_start();include HTR_RR_DIR.'templates/report.php';$appendix_html=ob_get_clean();
check(strpos($appendix_html,'id="htr-rr-diagram-data"')!==false,'Available measured diagrams retained as optional appendix');
check(strpos($appendix_html,'Retained custom report text.')!==false,'Existing custom text retained in appendix');
echo "Estimate appendix/color checks passed. Total checks: $checks\n";$layout_preview='PRIVATE EDITABLE PREVIEW';$layout_preview_key='1';
ob_start();include HTR_RR_DIR.'templates/report.php';$editable_estimate=ob_get_clean();
check(strpos($editable_estimate,'data-estimate-copy="title"')!==false,'Estimate preview wording editable');
check(strpos($estimate_html,'contenteditable=')===false,'Customer estimate remains read only');
check(strpos(HTR_RR_Estimate_Design::text(array('title'=>'<script>bad</script>'),'title',true),'&lt;script&gt;')!==false,'Editable wording escaped');
file_put_contents(__DIR__.'/.estimate-design-inline-preview.html',$editable_estimate);
echo "Estimate inline checks passed. Total checks: $checks\n";

check(strpos($estimate_html,'customer@example.test')!==false,'Estimate includes saved customer email');
check(strpos($html,'Test Customer')!==false && strpos($html,'555-0100')!==false,'Classic includes customer name and phone');
$v['customerDetails']=array('name'=>'<script>bad</script>','email'=>'<img src=x>','phone'=>'');
ob_start();include HTR_RR_DIR.'templates/report-sections/customer-details.php';$customer_html=ob_get_clean();
check(strpos($customer_html,'&lt;script&gt;')!==false && strpos($customer_html,'<img')===false,'Customer fields escaped');
check(strpos($customer_html,'Phone:')===false,'Missing contact field omitted');
echo "Customer detail checks passed. Total checks: $checks\n";
