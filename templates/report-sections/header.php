<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
	<div class="hd">
		<div><h1><?php echo HTR_RR_Report_Layout::copy_text($report_section,'title',!empty($layout_preview)); ?></h1><p><?php echo esc_html( $v['address'] ); ?></p><p><?php echo HTR_RR_Report_Layout::copy_text($report_section,'prepared',!empty($layout_preview)); ?> <?php echo esc_html( wp_date( 'M j, Y', strtotime( $v['createdAt'] . ' UTC' ) ) ); ?> <?php echo HTR_RR_Report_Layout::copy_text($report_section,'for',!empty($layout_preview)); ?> <?php echo esc_html( $v['firstName'] ); ?></p></div>
		<?php $header_style=HTR_RR_Report_Layout::sanitize_style($report_section['style'] ?? array()); $header_logo=$header_style['logo_id'] ? wp_get_attachment_image_url($header_style['logo_id'], 'medium') : ''; ?>
        <img style="height:<?php echo (int)$header_style['logo_size']; ?>px" src="<?php echo esc_url( $header_logo ?: $logo ); ?>" alt="Hometown Roofing &amp; Restoration">
	</div>
