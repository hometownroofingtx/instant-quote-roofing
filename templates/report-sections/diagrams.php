<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ($v['measured']) : ?>
		<?php if ( (int) $v['areaSqft'] < 400 ) : ?>
			<p class="warn"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'small_prefix',!empty($layout_preview)); ?> <?php echo esc_html( $v['areaSqft'] ); ?> <?php echo HTR_RR_Report_Layout::copy_text($report_section,'small_note',!empty($layout_preview)); ?></p>
		<?php endif; ?>
		<?php if ( empty( $v['customerEntered'] ) ) : ?>
		<div class="dg">
			<div><h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'iso',!empty($layout_preview)); ?></h2><div id="htr-rr-iso"></div></div>
			<div><h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'plan',!empty($layout_preview)); ?></h2><div id="htr-rr-planbox"></div></div>
		</div>
		<p class="note"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'note',!empty($layout_preview)); ?></p>
		<script type="application/json" id="htr-rr-diagram-data"><?php echo wp_json_encode( array( 'lat' => $v['lat'], 'lng' => $v['lng'], 'pitch' => $v['pitch'], 'faces' => $v['faces'], 'facesDetail' => $v['facesDetail'], 'buildingBox' => $v['buildingBox'] ) ); ?></script>
		<script src="<?php echo esc_url( HTR_RR_URL . 'assets/js/roof-diagram.js?ver=' . HTR_RR_VERSION ); ?>"></script>
		<div class="report-face-details">
		<h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'faces',!empty($layout_preview)); ?></h2>
		<table><thead><tr><th scope="col"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'number',!empty($layout_preview)); ?></th><th scope="col"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'area',!empty($layout_preview)); ?></th><th scope="col"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'pitch',!empty($layout_preview)); ?></th></tr></thead><tbody>
		<?php
		$i = 0;
		foreach ( $v['facesDetail'] as $f ) :
			$i++;
			?>
			<tr><td><?php echo (int) $i; ?></td><td><?php echo esc_html( number_format( $f['area'] ) ); ?></td><td><?php echo esc_html( $f['pitch'] . '/12' ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php endif; ?>
		<?php if (empty($v['customerEntered'])) : ?><p class="note"><?php echo HTR_RR_Report_Layout::copy_text($report_section,'source',!empty($layout_preview)); ?><?php echo $v['imageryDate'] ? ' ' . HTR_RR_Report_Layout::copy_text($report_section,'taken',!empty($layout_preview)) . ' ' . esc_html( substr( $v['imageryDate'], 0, 7 ) ) : ''; ?> (<?php echo HTR_RR_Report_Layout::copy_text($report_section,'quality',!empty($layout_preview)); ?>: <?php echo esc_html( strtolower( $v['imageryQuality'] ) ); ?>). <?php echo HTR_RR_Report_Layout::copy_text($report_section,'inspection',!empty($layout_preview)); ?></p></div><?php endif; ?>
<?php endif; ?>