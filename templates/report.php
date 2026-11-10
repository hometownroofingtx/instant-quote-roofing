<?php
/** Customer report page (print-ready). $v = HTR_RR_Report_Page::public_view(). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$report_layout = $report_layout ?? HTR_RR_Report_Layout::get();
$estimate_design = $estimate_design ?? HTR_RR_Estimate_Design::get();
if ('estimate' === $estimate_design['template']) { include __DIR__ . '/report-estimate.php'; return; }
$print = isset( $_GET['print'] ) && '1' === $_GET['print']; // phpcs:ignore WordPress.Security.NonceVerification
$logo  = HTR_RR_URL . 'assets/img/logo.png';
$m     = array( 'HTR_RR_Report_Page', 'money' );
?><!doctype html>
<html lang="en"<?php echo !empty($layout_preview) ? ' data-report-preview-key="'.esc_attr($layout_preview_key ?? '').'"' : ''; ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Roof Report - <?php echo esc_html( $v['address'] ); ?></title>
<style>
.report-section[data-report-style]{border-radius:10px;margin-block:16px;-webkit-print-color-adjust:exact;print-color-adjust:exact}.report-section[data-report-style] .hd{border:0;padding:0;color:inherit}.report-section[data-report-style] :is(h1,p,.note){color:inherit}.report-section[data-report-style] .hd img{height:var(--report-logo-size);max-width:100%;object-fit:contain}.report-section[data-report-style="center"] .hd{flex-direction:column;text-align:center}.report-section[data-report-style="right"] .hd{flex-direction:row-reverse;text-align:right}
[contenteditable]{white-space:pre-wrap;outline:1px dashed #0875d180;outline-offset:3px;cursor:text}[contenteditable]:focus{outline:2px solid #0875d1;background:#0875d10a}@media print{[contenteditable]{outline:none!important}}
.report-contact-bar{display:flex;align-items:center;position:relative;gap:0;margin-top:20px;padding:18px 42px 18px 18px;background:var(--footer-navy,#103b5c);color:var(--footer-text,#fff);isolation:isolate;-webkit-print-color-adjust:exact;print-color-adjust:exact;break-inside:avoid}
.report-contact-bar:after{content:'';position:absolute;inset:0 0 0 auto;width:26px;background:var(--footer-accent,#d6202b);clip-path:polygon(100% 0,100% 100%,0 100%);z-index:-1}
.report-contact-bar .report-contact-item{display:flex;align-items:center;gap:10px;flex:1 1 0;min-width:0;color:inherit;text-decoration:none;padding:0 14px;font-size:12px;line-height:1.5;text-align:left}
.report-contact-item+.report-contact-item{border-left:1px solid #ffffff66}.report-contact-item span{overflow-wrap:anywhere;white-space:pre-line}.report-contact-item svg{width:20px;height:20px;flex-shrink:0;fill:currentColor}.report-contact-item .report-contact-globe{fill:none;stroke:currentColor;stroke-width:1.5}.report-contact-item:focus-visible{outline:2px solid currentColor;outline-offset:4px}
@media screen and (max-width:640px){.report-contact-bar{flex-direction:column;align-items:stretch;gap:12px}.report-contact-bar .report-contact-item{padding:0;min-height:36px}.report-contact-item+.report-contact-item{border-left:0;border-top:1px solid #ffffff44;padding-top:12px}}
.report-contact-bar .report-contact-brand{flex-direction:column;align-items:flex-start;gap:6px}.report-contact-brand img{display:block;max-width:100%;object-fit:contain;object-position:left center}.report-contact-brand a{color:inherit;font-size:11px;text-decoration:none;overflow-wrap:anywhere}.report-contact-bar .report-contact-phone{justify-content:center;text-align:center}.report-contact-bar .report-contact-area{justify-content:flex-start}
body{margin:0;background:#eef1f5;color:#1d2433;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.pg{max-width:860px;margin:24px auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.hd{display:flex;justify-content:space-between;align-items:center;gap:16px;border-bottom:3px solid #0b2d4f;padding-bottom:14px}
.hd img{height:56px}.hd h1{margin:0;font-size:24px;color:#0b2d4f}.hd p{margin:2px 0 0;color:#5b6475}
.report-custom-text{overflow-wrap:anywhere}.report-custom-text p{line-height:1.7}
h2{color:#0b2d4f;font-size:17px;margin:22px 0 8px}
.g{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.t{border:1px solid #dfe4ec;border-radius:10px;padding:10px;background:#f4f7fb}
.t span{display:block;color:#5b6475;font-size:12px}.t b{font-size:20px;color:#0b2d4f;font-variant-numeric:tabular-nums}
.price{border:1px solid #f3c4c7;background:#fff5f5;border-radius:10px;padding:14px;margin-top:10px}.price b{font-size:28px;color:#d6202b}
table{width:100%;border-collapse:collapse;font-size:14px}td,th{border-bottom:1px solid #dfe4ec;padding:6px 8px;text-align:left}th{background:#0b2d4f;color:#fff}
.img{width:100%;border-radius:10px;margin-top:8px}
.dg{display:grid;grid-template-columns:1fr 1fr;gap:14px}.dg svg{width:100%;height:auto;background:#f7f9fc;border-radius:8px}.dg text{font:12px Arial,sans-serif;fill:#1d2433}.dg .dim{fill:#5b6475;font-size:11px}
@media (max-width:640px){.dg{grid-template-columns:1fr}}.note{color:#5b6475;font-size:13px}.warn{background:#fff8e6;border:1px solid #f1d48a;border-radius:10px;padding:10px 12px}
.bar{max-width:860px;margin:16px auto 0;padding:0 16px;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;justify-content:space-between}.bar p{margin:0;flex:1 1 260px;font-size:13px;color:#5b6475}.bar button{background:#d6202b;color:#fff;border:0;border-radius:8px;padding:10px 16px;font:inherit;font-weight:700;cursor:pointer}.bar button:focus-visible{outline:3px solid #2271b1;outline-offset:3px}
@page{margin:0}
@media (max-width:640px){.g{grid-template-columns:repeat(2,1fr)}.pg{padding:18px;margin:0;border-radius:0}}
@media print{body{background:#fff;font-size:11pt}.pg{box-shadow:none;margin:0;max-width:none;border-radius:0;padding:0}.bar{display:none}.hd,.g,.price,.warn,.dg,tr{break-inside:avoid}.hd img{max-width:160px;height:auto}h2{break-after:avoid}p{orphans:3;widows:3}.g{grid-template-columns:repeat(4,minmax(0,1fr))}.t b{font-size:16px}.dg{grid-template-columns:1fr 1fr}thead{display:table-header-group}th{background:#f4f7fb;color:#0b2d4f}.img{display:block;max-height:85mm;object-fit:contain;break-inside:avoid}}
.report-print-table{display:none}
.report-print-band .report-section{margin:0!important;box-sizing:border-box}
.report-print-band .report-contact-bar{flex-direction:row;align-items:center;gap:0}
.report-print-band .report-contact-bar .report-contact-item{min-height:0;padding:0 14px;border-top:0}
.report-print-band .report-contact-item+.report-contact-item{border-left:1px solid #ffffff66}
@media print{
 .report-repeat-print .pg{display:none}
 .report-print-table{display:table;width:100%;border:0;border-collapse:collapse;table-layout:fixed;background:#fff}
 .report-print-table>thead{display:table-header-group}.report-print-table>tfoot{display:table-footer-group}
 .report-print-table>thead>tr,.report-print-table>tfoot>tr{break-inside:avoid}
 .report-print-table>tbody>tr{break-inside:auto}
 .report-print-table>thead>tr>td,.report-print-table>tbody>tr>td,.report-print-table>tfoot>tr>td{border:0;padding:0;background:#fff;color:inherit;font-size:11pt;vertical-align:top}
 .report-print-header{padding:0 0 4mm}.report-print-footer{padding:0}
 .report-print-table>tbody>tr>td{padding:6mm 15mm;overflow:visible}
 .report-print-table>tbody>tr>td>.report-section{break-inside:auto;overflow:visible}
 .report-print-band{-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .report-print-band .hd{flex-direction:row;text-align:left}
 .report-print-band .report-section[data-report-style="center"] .hd{flex-direction:column;text-align:center}
 .report-print-band .report-section[data-report-style="right"] .hd{flex-direction:row-reverse;text-align:right}
 .report-print-band .report-contact-bar{margin-top:0}
}
/* Full-width, square report branding; body content retains its reading gutter. */
.pg{--report-gutter:32px;border-radius:0}
.report-section--header,.report-section--footer{border-radius:0!important}
.pg>.report-section--header{margin-inline:calc(-1 * var(--report-gutter))!important}
.pg>.report-section--header:first-child{margin-top:calc(-1 * var(--report-gutter))!important}
.pg>.report-section--footer .report-contact-bar{margin-inline:calc(-1 * var(--report-gutter));border-radius:0}
.pg>.report-section--footer:last-child:has(.report-contact-bar){margin-bottom:calc(-1 * var(--report-gutter))!important}
.pg>.report-section--footer[data-report-style]{margin-inline:calc(-1 * var(--report-gutter))!important}
.pg>.report-section--footer[data-report-style] .report-contact-bar{margin-inline:calc(-1 * var(--report-section-padding,24px));margin-bottom:calc(-1 * var(--report-section-padding,24px))}
@media screen and (max-width:640px){.pg{--report-gutter:18px}}
@media print{.pg{--report-gutter:0px}.report-print-band .report-section{border-radius:0!important}.report-print-band .report-contact-bar{border-radius:0}}
/* Compact body; the repeating table footer reserves room for the bottom-anchored band. */
.report-fixed-footer{display:none}
@media print{
 .report-face-details{break-inside:avoid;page-break-inside:avoid}.report-face-details h2{break-after:avoid}
 body{font-size:9.5pt;line-height:1.4}
 .report-print-table>thead>tr>td,.report-print-table>tbody>tr>td,.report-print-table>tfoot>tr>td{font-size:9.5pt}
 .report-print-table>tbody>tr>td{padding:2.5mm 10mm}
 .report-print-table h2,.report-fixed-footer h2{font-size:12pt;margin:10px 0 5px}
 .report-print-table p{margin:5px 0}
 .report-print-header .hd h1{font-size:20px}.report-print-header .hd img{max-height:48px}
 .report-print-header .report-section[data-report-style]{padding:16px 20px!important}
 .report-print-footer .report-contact-brand img{max-height:40px}
 .report-print-footer>.report-section:not([data-report-style])>.note{margin-inline:10mm}
 .report-print-table .g{gap:6px}.report-print-table .t{padding:7px}.report-print-table .t b{font-size:14px}
 .report-print-table .price{padding:9px;margin-top:8px}.report-print-table .price b{font-size:23px}
 .report-print-table .dg{gap:12px}.report-print-table .dg svg{width:100%;height:auto;max-height:none}
 .report-print-table .img{display:block;width:100%;height:auto;max-height:none;aspect-ratio:16/9;object-fit:contain;margin-top:8px}
 .report-print-table td,.report-print-table th{padding:4px 7px;font-size:9pt}
 .report-print-table>tfoot{visibility:hidden}
 .report-fixed-footer{display:block;position:fixed;bottom:0;left:0;right:0;z-index:20;background:#fff;font-size:9.5pt}
 .report-fixed-footer p,.report-print-table>tfoot p{margin:5px 0}
 .report-fixed-footer .report-contact-bar,.report-print-table>tfoot .report-contact-bar{padding-top:12px;padding-bottom:12px}
 .report-fixed-footer .report-section,.report-print-table>tfoot .report-section{margin:0!important;border-radius:0!important}
}

.report-customer-details{display:flex;flex-wrap:wrap;gap:3px 16px;margin:8px 0 12px;font-size:11px;line-height:1.35;overflow-wrap:anywhere;break-inside:avoid}.report-customer-details strong,.report-customer-details>span:nth-child(2){flex-basis:100%}
</style></head><body>
<div class="bar"><p id="htr-download-help">Choose <strong>Save as PDF</strong> in the print dialog to download your report.</p><button id="htr-download-report" type="button" aria-describedby="htr-download-help">Download report</button></div>
<?php if (!empty($layout_preview)) : ?><p style="text-align:center;font-weight:700"><?php echo esc_html($layout_preview); ?></p><?php endif; ?>
<div class="pg">
<?php foreach ($report_layout as $report_index => $report_section) : ?>
<section data-report-index="<?php echo (int)$report_index; ?>" class="report-section report-section--<?php echo esc_attr($report_section['type']); ?>"<?php echo HTR_RR_Report_Layout::style_attributes($report_section); ?>>
<?php if ('text' === $report_section['type']) : ?>
<?php if ($report_section['title']) : ?><h2<?php echo !empty($layout_preview) ? ' contenteditable="plaintext-only" role="textbox" aria-label="Edit custom heading" data-report-field="title"' : ''; ?>><?php echo esc_html($report_section['title']); ?></h2><?php endif; ?>
<div class="report-custom-text"<?php echo !empty($layout_preview) ? ' contenteditable="plaintext-only" role="textbox" aria-label="Edit custom text" data-report-field="content"' : ''; ?>><?php echo HTR_RR_Report_Layout::text_html($report_section['content']); ?></div>
<?php else : ?>
<?php include __DIR__ . '/report-sections/' . $report_section['type'] . '.php'; ?>
<?php endif; ?>
</section>
<?php endforeach; ?>
</div>
<script src="<?php echo esc_url(HTR_RR_URL . 'assets/js/report-print.js?ver=' . HTR_RR_VERSION); ?>"></script>
<script>
(function () {
    'use strict';
    async function downloadReport() {
        var button=document.getElementById('htr-download-report');
        if(button.disabled)return;button.disabled=true;button.textContent='Preparing print...';
        try {
            var data=document.getElementById('htr-rr-diagram-data');
            if(data&&window.HTRRoofDiagram){
                var roof=JSON.parse(data.textContent),iso=document.getElementById('htr-rr-iso'),plan=document.getElementById('htr-rr-planbox');
                if(iso&&!iso.querySelector('svg'))iso.innerHTML=window.HTRRoofDiagram.iso(roof);
                if(plan&&!plan.querySelector('svg'))plan.innerHTML=window.HTRRoofDiagram.plan(roof);
            }
            await Promise.all(Array.from(document.images).map(function(image){return image.decode ? image.decode().catch(function(){}) : Promise.resolve();}));
            if(document.fonts)await document.fonts.ready;
            window.print();
        } finally {button.disabled=false;button.textContent='Download report';}
    }
    document.getElementById('htr-download-report').addEventListener('click', downloadReport);
    <?php if ( $print ) : ?>
    // Load includes the logo, aerial image and diagram script; allow layout to finish before printing.
    window.addEventListener('load', function () { downloadReport(); });
    <?php endif; ?>
})();
</script>
<?php if (!empty($layout_preview)) : ?>
<script>
(function(){
 const previewKey=<?php echo wp_json_encode($layout_preview_key ?? ''); ?>;
 document.querySelectorAll('[data-report-copy],[data-report-field]').forEach(function(field){
  let timer;
  function notify(){const section=field.closest('[data-report-index]');window.parent.postMessage({type:'htr-report-copy',previewKey:previewKey,index:Number(section.dataset.reportIndex),key:field.dataset.reportCopy||field.dataset.reportField,custom:field.hasAttribute('data-report-field'),text:field.innerText},window.location.origin);}
  field.addEventListener('input',function(){field.dataset.copyEdited='true';clearTimeout(timer);timer=setTimeout(notify,250);});
  field.addEventListener('blur',function(){clearTimeout(timer);notify();});
  field.addEventListener('paste',function(event){field.dataset.copyEdited='true';event.preventDefault();const text=event.clipboardData.getData('text/plain');const selection=window.getSelection();if(!selection.rangeCount)return;const range=selection.getRangeAt(0);if(!field.contains(range.commonAncestorContainer))return;range.deleteContents();const node=document.createTextNode(text);range.insertNode(node);range.setStartAfter(node);range.collapse(true);selection.removeAllRanges();selection.addRange(range);notify();});
 });
})();
</script>
<?php endif; ?>

</body></html>
