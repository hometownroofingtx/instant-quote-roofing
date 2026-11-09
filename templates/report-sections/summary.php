<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
	<?php if ( $v['measured'] ) : ?>
		<?php if ( ! empty( $v['customerEntered'] ) ) : ?><p class="warn"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'manual',!empty($layout_preview)); ?></p><?php endif; ?>
		<h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'title',!empty($layout_preview)); ?></h2>
		<div class="g">
			<div class="t"><span><?php echo HTR_RR_Report_Layout::copy_text($report_section,'area',!empty($layout_preview)); ?></span><b><?php echo esc_html( number_format( $v['areaSqft'] ) ); ?> <?php echo HTR_RR_Report_Layout::copy_text($report_section,'unit',!empty($layout_preview)); ?></b></div>
			<div class="t"><span><?php echo HTR_RR_Report_Layout::copy_text($report_section,'squares',!empty($layout_preview)); ?></span><b><?php echo esc_html( $v['squares'] ); ?></b></div>
			<div class="t"><span><?php echo HTR_RR_Report_Layout::copy_text($report_section,'pitch',!empty($layout_preview)); ?></span><b><?php echo esc_html( $v['pitch'] ); ?></b></div>
			<div class="t"><span><?php echo HTR_RR_Report_Layout::copy_text($report_section,'faces',!empty($layout_preview)); ?></span><b><?php echo esc_html( $v['faces'] ); ?></b></div>
		</div>
		<div class="price">
			<span><?php echo HTR_RR_Report_Layout::copy_text($report_section,'estimate',!empty($layout_preview)); ?> &middot; <?php echo esc_html( $v['roofType'] ); ?></span><br>
			<?php if ( $v['priceLow'] ) : ?>
				<b><?php echo esc_html( call_user_func( $m, $v['priceLow'] ) . ' – ' . call_user_func( $m, $v['priceHigh'] ) ); ?></b><br>
				<span class="note">$<?php echo esc_html( number_format( $v['rateLow'], 2 ) ); ?> – $<?php echo esc_html( number_format( $v['rateHigh'], 2 ) ); ?> <?php echo HTR_RR_Report_Layout::copy_text($report_section,'per_unit',!empty($layout_preview)); ?> &middot; <?php echo esc_html( $v['complexity'] ); ?> <?php echo HTR_RR_Report_Layout::copy_text($report_section,'roof',!empty($layout_preview)); ?></span>
			<?php else : ?>
				<b><?php echo HTR_RR_Report_Layout::copy_text($report_section,'onsite',!empty($layout_preview)); ?></b><br><span class="note"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'onsite_note',!empty($layout_preview)); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $v['needsReview'] ) : ?><p class="warn"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'review',!empty($layout_preview)); ?></p><?php endif; ?>

<?php else : ?><p class="warn"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'unavailable',!empty($layout_preview)); ?></p><?php endif; ?>