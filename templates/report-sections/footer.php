<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
	<p class="note report-disclaimer"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'disclaimer',!empty($layout_preview),$v['disclaimer']); ?></p>

<?php $footer_style=HTR_RR_Report_Layout::sanitize_style($report_section['style'] ?? array()); ?>
<?php if ($footer_style['logo_id'] && 'contact' !== $footer_style['footer_design']) : ?>
    <?php $footer_logo=wp_get_attachment_image_url($footer_style['logo_id'], 'medium'); ?>
    <?php if ($footer_logo) : ?><img class="report-footer-logo" src="<?php echo esc_url($footer_logo); ?>" style="height:<?php echo (int)$footer_style['logo_size']; ?>px;max-width:100%;object-fit:contain" alt="Company logo"><?php endif; ?>
<?php endif; ?>
<?php if ($footer_style['footer_title']) : ?><h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'footer_title',!empty($layout_preview),$footer_style['footer_title']); ?></h2><?php endif; ?>
<?php if ($footer_style['footer_details']) : ?><p class="report-footer-details"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'footer_details',!empty($layout_preview),$footer_style['footer_details']); ?></p><?php endif; ?>
<?php if ('contact' === $footer_style['footer_design']) : ?>
    <?php
    $contact_phone=$footer_style['contact_phone'] ?: $v['phone'];
    $contact_site=$footer_style['contact_website'];
    $contact_url=$contact_site ? (preg_match('#^https?://#i',$contact_site) ? $contact_site : 'https://'.$contact_site) : '';
    $contact_label=preg_replace('#^https?://#i','',$contact_site);
    ?>
    <div class="report-contact-bar" style="--footer-navy:<?php echo esc_attr($footer_style['from']); ?>;--footer-accent:<?php echo esc_attr($footer_style['accent']); ?>;--footer-text:<?php echo esc_attr($footer_style['color']); ?>">
        <div class="report-contact-item report-contact-brand">
            <?php $bar_logo=$footer_style['logo_id'] ? wp_get_attachment_image_url($footer_style['logo_id'],'medium') : ''; ?>
            <img src="<?php echo esc_url($bar_logo ?: $logo); ?>" alt="Company logo" style="height:<?php echo (int)$footer_style['logo_size']; ?>px">
            <?php if ($contact_site) : ?><a href="<?php echo esc_url($contact_url); ?>"><?php echo esc_html(rtrim($contact_label,'/')); ?></a><?php endif; ?>
        </div>
        <?php if ($contact_phone) : ?>
            <a class="report-contact-item report-contact-phone" href="<?php echo esc_url('tel:'.preg_replace('/[^0-9+]/','',$contact_phone)); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.2c1.1.4 2.3.6 3.6.6a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.6 21 3 13.4 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.3.2 2.5.6 3.6a1 1 0 0 1-.2 1z"/></svg>
                <span><?php echo esc_html(HTR_RR_Estimate_Design::display_phone($contact_phone)); ?></span>
            </a>
        <?php endif; ?>
        <?php if ($footer_style['contact_area']) : ?>
            <div class="report-contact-item report-contact-area">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a8 8 0 0 0-8 8c0 6 8 12 8 12s8-6 8-12a8 8 0 0 0-8-8m0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6" fill-rule="evenodd"/></svg>
                <span><?php echo nl2br(esc_html($footer_style['contact_area'])); ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
