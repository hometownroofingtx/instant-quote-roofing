<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ($v['measured']) : ?>
		<?php
		$key = HTR_RR_Settings::get( 'google_browser_key' );
		if ( $key && empty($layout_sample) ) :
			$src = add_query_arg(
				array( 'center' => $v['lat'] . ',' . $v['lng'], 'zoom' => 20, 'size' => '640x360', 'scale' => 2, 'maptype' => 'satellite', 'markers' => 'color:red|' . $v['lat'] . ',' . $v['lng'], 'key' => $key ),
				'https://maps.googleapis.com/maps/api/staticmap'
			);
			?>
			<h2><?php echo HTR_RR_Report_Layout::copy_text($report_section,'title',!empty($layout_preview)); ?></h2>
			<img class="img" referrerpolicy="origin" src="<?php echo esc_url( $src ); ?>" alt="Satellite view of the property">
		<?php endif; ?>

<?php endif; ?>