<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
	<h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'title',!empty($layout_preview)); ?></h2>
	<p><?php echo HTR_RR_Report_Layout::copy_text($report_section,'details',!empty($layout_preview)); ?><?php echo $v['phone'] ? ': ' . HTR_RR_Report_Layout::copy_text($report_section,'call',!empty($layout_preview)) . ' <b>' . esc_html( $v['phone'] ) . '</b>' : ''; ?>.</p>
