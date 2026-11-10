<?php
/** Optional estimate presentation; all quote values use the existing public report view. */
if (!defined('ABSPATH')) { exit; }
$design=$estimate_design;
$edit_design=!empty($layout_preview);
$brand=array();foreach($report_layout as $part){$brand[$part['type']]=$part;}
$head_style=HTR_RR_Report_Layout::sanitize_style($brand['header']['style']??array());
$foot_style=HTR_RR_Report_Layout::sanitize_style($brand['footer']['style']??array());
$brand_logo=$head_style['logo_id']?wp_get_attachment_image_url($head_style['logo_id'],'large'):'';
$brand_logo=$brand_logo?:HTR_RR_URL.'assets/img/logo.png';
$phone=$foot_style['contact_phone']?:($v['phone']??'');
$tel=$phone?'tel:'.preg_replace('/[^0-9+]/','',$phone):'';
$booking=$design['inspection_url']?:$tel;
$website=$design['website'];$website_url=$website?(preg_match('#^https?://#i',$website)?$website:'https://'.$website):'';
$face_rows=is_array($v['facesDetail']??null)?$v['facesDetail']:array();
$face_groups=array(array_slice($face_rows,0,9));foreach(array_chunk(array_slice($face_rows,9),20) as $group)$face_groups[]=$group;
$custom_sections=array_values(array_filter($report_layout,function($section){return $section['type']==='text';}));
$show_diagrams=$design['diagrams']==='1'&&!empty($v['measured'])&&empty($v['customerEntered']);
$total_pages=1+count($face_groups)+($show_diagrams?1:0)+($custom_sections?1:0);
$header_phone=trim($design['header_phone']??'')?:$phone;
$header_tel=$header_phone?'tel:'.preg_replace('/[^0-9+]/','',$header_phone):'';
$phone=HTR_RR_Estimate_Design::display_phone($phone);
$header_phone=HTR_RR_Estimate_Design::display_phone($header_phone);
$header=function()use($brand_logo,$design,$edit_design,$header_phone,$header_tel){ ?>
<header class="estimate-header"><img src="<?php echo esc_url($brand_logo); ?>" alt="Hometown Roofing &amp; Restoration"><div><strong><?php echo HTR_RR_Estimate_Design::text($design,'tagline',$edit_design); ?></strong><p><?php echo HTR_RR_Estimate_Design::text($design,'tagline_note',$edit_design); ?></p><?php if($header_phone||$edit_design): ?><a class="estimate-header-phone" href="<?php echo esc_url($header_tel); ?>"><?php echo HTR_RR_Estimate_Design::text(array('header_phone'=>$header_phone),'header_phone',$edit_design); ?></a><?php endif; ?></div></header>
<?php };
$footer=function($page)use($website,$website_url,$phone,$tel,$design,$edit_design){ ?>
<footer class="estimate-footer"><div><?php if($website): ?><a href="<?php echo esc_url($website_url); ?>"><?php echo HTR_RR_Estimate_Design::text($design,'website',$edit_design); ?></a><?php endif; ?><?php if($phone): ?> <span aria-hidden="true">|</span> <a href="<?php echo esc_url($tel); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?></div><span><?php echo HTR_RR_Estimate_Design::text($design,'footer_label',$edit_design); ?> / <?php echo esc_html(str_pad((string)$page,2,'0',STR_PAD_LEFT)); ?></span></footer>
<?php };
$table=function($rows,$offset=0)use($v,$design,$edit_design){ ?>
<table class="estimate-face-table"><thead><tr><th><?php echo HTR_RR_Estimate_Design::text($design,'table_face',$edit_design); ?></th><th><?php echo HTR_RR_Estimate_Design::text($design,'table_area',$edit_design); ?></th><th><?php echo HTR_RR_Estimate_Design::text($design,'table_pitch',$edit_design); ?></th></tr></thead><tbody><?php foreach($rows as $index=>$face): ?><tr><td><?php echo (int)$offset+$index+1; ?></td><td><?php echo esc_html(number_format((float)$face['area'])); ?></td><td><?php echo esc_html($face['pitch'].'/12'); ?></td></tr><?php endforeach; ?></tbody></table>
<?php if(!$rows): ?><p class="estimate-muted"><?php echo HTR_RR_Estimate_Design::text($design,'unavailable_faces',$edit_design); ?></p><?php endif; ?>
<?php };
$source=!empty($v['customerEntered'])?'Based on customer-entered dimensions; not aerial measurements.':(!empty($v['measured'])?'Based on aerial measurements':'Measurements unavailable; inspection required');
$aerial='';$browser_key=HTR_RR_Settings::get('google_browser_key');
if($browser_key&&empty($layout_sample)&&!empty($v['lat'])&&!empty($v['lng']))$aerial=add_query_arg(array('center'=>$v['lat'].','.$v['lng'],'zoom'=>20,'size'=>'640x360','scale'=>2,'maptype'=>'satellite','markers'=>'color:red|'.$v['lat'].','.$v['lng'],'key'=>$browser_key),'https://maps.googleapis.com/maps/api/staticmap');
?><!doctype html><html lang="en"<?php echo $edit_design ? ' data-report-preview-key="'.esc_attr($layout_preview_key ?? '').'"' : ''; ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Roof Estimate - <?php echo esc_html($v['address']); ?></title><link rel="stylesheet" href="<?php echo esc_url(HTR_RR_URL.'assets/css/report-estimate.css?ver='.HTR_RR_VERSION.'.'.filemtime(HTR_RR_DIR.'assets/css/report-estimate.css')); ?>"></head><body style="--estimate-header:<?php echo esc_attr($design['header_color']); ?>;--estimate-cyan:<?php echo esc_attr($design['accent_color']); ?>;--estimate-price:<?php echo esc_attr($design['price_color']); ?>">
<div class="estimate-toolbar"><p>Choose Save as PDF in the print dialog. Enable Background graphics for brand colors.</p><button type="button" id="estimate-download">Download report</button></div>
<?php if(!empty($layout_preview)): ?><p class="estimate-preview-label"><?php echo esc_html($layout_preview); ?></p><?php endif; ?>
<article class="estimate-page estimate-cover"><?php $header(); ?><main class="estimate-content">
<div class="estimate-kicker"><span><?php echo HTR_RR_Estimate_Design::text($design,'eyebrow',$edit_design); ?></span><?php if(!empty($layout_sample)): ?><span>SAMPLE DATA / DESIGN PREVIEW</span><?php endif; ?></div>
<h1><?php echo HTR_RR_Estimate_Design::text($design,'title',$edit_design); ?></h1><h2 class="estimate-address"><?php echo esc_html($v['address']); ?></h2>
<p class="estimate-meta"><?php echo HTR_RR_Estimate_Design::text($design,'prepared_label',$edit_design); ?> <?php echo esc_html(wp_date('F j, Y',strtotime($v['createdAt'].' UTC'))); ?> <span aria-hidden="true">|</span> <?php echo esc_html($source); ?></p>
<?php include __DIR__.'/report-sections/customer-details.php'; ?>
<?php if($aerial): ?><img class="estimate-aerial" src="<?php echo esc_url($aerial); ?>" referrerpolicy="origin" alt="Satellite view of the property"><?php else: ?><div class="estimate-aerial estimate-image-unavailable"><p><?php echo !empty($layout_sample)?'Sample preview — actual property imagery appears on saved customer reports.':HTR_RR_Estimate_Design::text($design,'image_unavailable',$edit_design); ?></p></div><?php endif; ?>
<div class="estimate-metrics"><?php foreach(array('metric_area'=>!empty($v['measured'])?number_format($v['areaSqft']):'Unavailable','metric_squares'=>!empty($v['measured'])?$v['squares']:'Unavailable','metric_pitch'=>!empty($v['measured'])?$v['pitch']:'Unavailable','metric_faces'=>!empty($v['measured'])?$v['faces']:'Unavailable') as $label=>$value): ?><div><strong><?php echo esc_html($value); ?></strong><span><?php echo HTR_RR_Estimate_Design::text($design,$label,$edit_design); ?></span></div><?php endforeach; ?></div>
<section class="estimate-range"><h2><?php echo HTR_RR_Estimate_Design::text($design,'range_label',$edit_design); ?></h2><p class="estimate-price"><?php echo !empty($v['priceLow'])&& !empty($v['measured'])?esc_html(HTR_RR_Report_Page::money($v['priceLow']).' - '.HTR_RR_Report_Page::money($v['priceHigh'])):HTR_RR_Estimate_Design::text($design,'onsite_label',$edit_design); ?></p><p><?php echo esc_html($v['roofType']).' | '.HTR_RR_Estimate_Design::text($design,'estimate_note',$edit_design); ?></p></section>
<div class="estimate-cta"><?php if($booking): ?><a href="<?php echo esc_url($booking); ?>"><?php echo HTR_RR_Estimate_Design::text($design,'cta',$edit_design); ?></a><?php else: ?><strong><?php echo HTR_RR_Estimate_Design::text($design,'cta',$edit_design); ?></strong><?php endif; ?><div><strong><?php echo HTR_RR_Estimate_Design::text($design,'cta_note',$edit_design); ?></strong><?php if($phone): ?><p><?php echo HTR_RR_Estimate_Design::text($design,'call_label',$edit_design); ?> <a href="<?php echo esc_url($tel); ?>"><?php echo esc_html($phone); ?></a></p><?php endif; ?></div></div>
</main><?php $footer(1); ?></article>
<?php $page_number=1;foreach($face_groups as $group_index=>$rows):$page_number++; ?>
<article class="estimate-page estimate-details"><?php $header(); ?><div class="estimate-content">
<div class="estimate-kicker"><?php echo HTR_RR_Estimate_Design::text($design,'details_eyebrow',$edit_design); ?></div><h1><?php echo esc_html($group_index?'Roof measurement details — continued':$design['details_title']); ?></h1>
<?php if(!$group_index): ?>
<section class="estimate-reassurance"><div class="estimate-warranty"><img class="estimate-warranty-mascot" src="<?php echo esc_url(HTR_RR_URL.'assets/img/workmanship-mascot.png'); ?>" alt="" width="86" height="100"><div><h2><?php echo HTR_RR_Estimate_Design::text($design,'warranty_title',$edit_design); ?></h2><p><?php echo HTR_RR_Estimate_Design::text($design,'warranty_text',$edit_design); ?></p></div></div><figure class="estimate-bbb"><img src="<?php echo esc_url(HTR_RR_URL.'assets/img/bbb-rating.png'); ?>" alt="BBB Accredited Business" width="129" height="100"><figcaption>A+ BBB Rating</figcaption><?php if($design['rating']): ?><strong class="estimate-rating"><?php echo HTR_RR_Estimate_Design::text($design,'rating',$edit_design); ?></strong><?php endif; ?></figure></section>
<h2><?php echo HTR_RR_Estimate_Design::text($design,'materials_title',$edit_design); ?></h2><div class="estimate-materials"><section><span>SELECTED ROOF MATERIAL</span><h3><?php echo esc_html($v['roofType']); ?></h3><p><?php echo HTR_RR_Estimate_Design::text($design,'selected_note',$edit_design); ?></p></section><section><span>OTHER OPTIONS</span><h3><?php echo HTR_RR_Estimate_Design::text($design,'alternative_title',$edit_design); ?></h3><p><?php echo HTR_RR_Estimate_Design::text($design,'alternative_text',$edit_design); ?></p></section></div>
<?php endif; ?>
<div class="estimate-detail-grid"><section><h2><?php echo HTR_RR_Estimate_Design::text($design,'table_title',$edit_design); ?></h2><?php $table($rows,$group_index?9+($group_index-1)*20:0); ?><?php if($group_index===count($face_groups)-1&&!empty($v['measured'])): ?><p class="estimate-total"><?php echo HTR_RR_Estimate_Design::text($design,'total_label',$edit_design); ?> &nbsp; <?php echo esc_html(number_format($v['areaSqft'])); ?> sq ft</p><?php endif; ?></section>
<section><h2><?php echo HTR_RR_Estimate_Design::text($design,'process_title',$edit_design); ?></h2><?php foreach(range(1,3) as $step): ?><div class="estimate-process"><strong><?php echo esc_html(str_pad((string)$step,2,'0',STR_PAD_LEFT)); ?></strong><div><h3><?php echo HTR_RR_Estimate_Design::text($design,'process_'.$step,$edit_design); ?></h3><p><?php echo HTR_RR_Estimate_Design::text($design,'process_'.$step.'_text',$edit_design); ?></p></div></div><?php endforeach; ?></section></div>
<?php if($group_index===count($face_groups)-1): ?><section class="estimate-notes"><p><?php echo esc_html($source); ?><?php if(!empty($v['imageryDate'])&&empty($v['customerEntered'])): ?>. Imagery date: <?php echo esc_html(substr($v['imageryDate'],0,7)); ?><?php endif; ?>.</p><p><?php echo HTR_RR_Estimate_Design::text($design,'notes',$edit_design); ?></p><p><?php echo esc_html($v['disclaimer']); ?></p></section><p class="estimate-closing"><?php echo HTR_RR_Estimate_Design::text($design,'closing',$edit_design).' '.esc_html($phone); ?><?php if($website): ?> | <?php echo esc_html($website); ?><?php endif; ?></p><?php endif; ?>
</div><?php $footer($page_number); ?></article>
<?php endforeach; ?>
<?php if($show_diagrams):$page_number++; ?><article class="estimate-page"><?php $header(); ?><div class="estimate-content"><h1>Roof diagrams</h1><?php $report_section=$brand['diagrams']??array('type'=>'diagrams');$diagram_preview=$layout_preview??null;unset($layout_preview);ob_start();include __DIR__.'/report-sections/diagrams.php';$diagram_html=ob_get_clean();$layout_preview=$diagram_preview;$details_start=strpos($diagram_html,'<div class="report-face-details">');echo false===$details_start?$diagram_html:substr($diagram_html,0,$details_start); ?></div><?php $footer($page_number); ?></article><?php endif; ?>
<?php if($custom_sections):$page_number++; ?><article class="estimate-page"><?php $header(); ?><div class="estimate-content"><h1>Additional report information</h1><?php foreach($custom_sections as $section): ?><section><?php if($section['title']): ?><h2><?php echo esc_html($section['title']); ?></h2><?php endif; ?><?php echo HTR_RR_Report_Layout::text_html($section['content']); ?></section><?php endforeach; ?></div><?php $footer($page_number); ?></article><?php endif; ?>
<script src="<?php echo esc_url(HTR_RR_URL.'assets/js/report-estimate.js?ver='.HTR_RR_VERSION); ?>"></script>
<?php if(isset($_GET['print'])&&$_GET['print']==='1'): ?><script>window.addEventListener('load',function(){document.getElementById('estimate-download').click();});</script><?php endif; ?>
</body></html>
