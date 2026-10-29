<?php
/** Customer report page (print-ready). $v = HTR_RR_Report_Page::public_view(). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$print = ! empty( $_GET['print'] ); // phpcs:ignore WordPress.Security.NonceVerification
$logo  = HTR_RR_URL . 'assets/img/logo.png';
$m     = array( 'HTR_RR_Report_Page', 'money' );
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Roof Report - <?php echo esc_html( $v['address'] ); ?></title>
<style>
body{margin:0;background:#eef1f5;color:#1d2433;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.pg{max-width:860px;margin:24px auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.hd{display:flex;justify-content:space-between;align-items:center;gap:16px;border-bottom:3px solid #0b2d4f;padding-bottom:14px}
.hd img{height:56px}.hd h1{margin:0;font-size:24px;color:#0b2d4f}.hd p{margin:2px 0 0;color:#5b6475}
h2{color:#0b2d4f;font-size:17px;margin:22px 0 8px}
.g{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.t{border:1px solid #dfe4ec;border-radius:10px;padding:10px;background:#f4f7fb}
.t span{display:block;color:#5b6475;font-size:12px}.t b{font-size:20px;color:#0b2d4f;font-variant-numeric:tabular-nums}
.price{border:1px solid #f3c4c7;background:#fff5f5;border-radius:10px;padding:14px;margin-top:10px}.price b{font-size:28px;color:#d6202b}
table{width:100%;border-collapse:collapse;font-size:14px}td,th{border-bottom:1px solid #dfe4ec;padding:6px 8px;text-align:left}th{background:#0b2d4f;color:#fff}
.img{width:100%;border-radius:10px;margin-top:8px}
.dg{display:grid;grid-template-columns:1fr 1fr;gap:14px}.dg svg{width:100%;height:auto;background:#f7f9fc;border-radius:8px}.dg text{font:12px Arial,sans-serif;fill:#1d2433}.dg .dim{fill:#5b6475;font-size:11px}
@media (max-width:640px){.dg{grid-template-columns:1fr}}.note{color:#5b6475;font-size:13px}.warn{background:#fff8e6;border:1px solid #f1d48a;border-radius:10px;padding:10px 12px}
.bar{max-width:860px;margin:16px auto 0;display:flex;gap:10px;justify-content:flex-end}.bar button{background:#d6202b;color:#fff;border:0;border-radius:8px;padding:10px 16px;font:inherit;font-weight:700;cursor:pointer}
@media print{body{background:#fff}.pg{box-shadow:none;margin:0;max-width:none;border-radius:0;padding:0}.bar{display:none}}
@media (max-width:640px){.g{grid-template-columns:repeat(2,1fr)}.pg{padding:18px;margin:0;border-radius:0}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Download PDF / Print</button></div>
<div class="pg">
	<div class="hd">
		<div><h1>Roof Measurement Report</h1><p><?php echo esc_html( $v['address'] ); ?></p><p>Prepared <?php echo esc_html( wp_date( 'M j, Y', strtotime( $v['createdAt'] . ' UTC' ) ) ); ?> for <?php echo esc_html( $v['firstName'] ); ?></p></div>
		<img src="<?php echo esc_url( $logo ); ?>" alt="Hometown Roofing &amp; Restoration">
	</div>

	<?php if ( $v['measured'] ) : ?>
		<h2>Roof summary</h2>
		<div class="g">
			<div class="t"><span>Total roof area</span><b><?php echo esc_html( number_format( $v['areaSqft'] ) ); ?> sq ft</b></div>
			<div class="t"><span>Roofing squares</span><b><?php echo esc_html( $v['squares'] ); ?></b></div>
			<div class="t"><span>Main pitch</span><b><?php echo esc_html( $v['pitch'] ); ?></b></div>
			<div class="t"><span>Roof faces</span><b><?php echo esc_html( $v['faces'] ); ?></b></div>
		</div>
		<div class="price">
			<span>Estimated price range &middot; <?php echo esc_html( $v['roofType'] ); ?></span><br>
			<?php if ( $v['priceLow'] ) : ?>
				<b><?php echo esc_html( call_user_func( $m, $v['priceLow'] ) . ' – ' . call_user_func( $m, $v['priceHigh'] ) ); ?></b><br>
				<span class="note">$<?php echo esc_html( number_format( $v['rateLow'], 2 ) ); ?> – $<?php echo esc_html( number_format( $v['rateHigh'], 2 ) ); ?> per sq ft &middot; <?php echo esc_html( $v['complexity'] ); ?> roof</span>
			<?php else : ?>
				<b>On-site quote</b><br><span class="note">This roof type is priced after a free inspection.</span>
			<?php endif; ?>
		</div>
		<?php if ( $v['needsReview'] ) : ?><p class="warn">Our team will double-check these measurements and confirm your estimate.</p><?php endif; ?>

		<?php
		$key = HTR_RR_Settings::get( 'google_browser_key' );
		if ( $key ) :
			$src = add_query_arg(
				array( 'center' => $v['lat'] . ',' . $v['lng'], 'zoom' => 20, 'size' => '640x360', 'scale' => 2, 'maptype' => 'satellite', 'markers' => 'color:red|' . $v['lat'] . ',' . $v['lng'], 'key' => $key ),
				'https://maps.googleapis.com/maps/api/staticmap'
			);
			?>
			<h2>Aerial view</h2>
			<img class="img" src="<?php echo esc_url( $src ); ?>" alt="Satellite view of the property">
		<?php endif; ?>

		<?php if ( (int) $v['areaSqft'] < 400 ) : ?>
			<p class="warn">This measured only <?php echo esc_html( $v['areaSqft'] ); ?> sq ft, which looks like a small structure (shed, carport or garage) rather than the house. Our team will confirm the right building.</p>
		<?php endif; ?>
		<div class="dg">
			<div><h2>3D roof diagram</h2><div id="htr-rr-iso"></div></div>
			<div><h2>Roof plan view</h2><div id="htr-rr-planbox"></div></div>
		</div>
		<p class="note">Drawn to the measured size and pitch. Exact ridge, hip, valley and eave lines are confirmed during your free inspection.</p>
		<script type="application/json" id="htr-rr-diagram-data"><?php echo wp_json_encode( array( 'lat' => $v['lat'], 'lng' => $v['lng'], 'pitch' => $v['pitch'], 'faces' => $v['faces'], 'facesDetail' => $v['facesDetail'], 'buildingBox' => $v['buildingBox'] ) ); ?></script>
		<script src="<?php echo esc_url( HTR_RR_URL . 'assets/js/roof-diagram.js?ver=' . HTR_RR_VERSION ); ?>"></script>
		<h2>Roof faces measured</h2>
		<table><tr><th>#</th><th>Area (sq ft)</th><th>Pitch</th></tr>
		<?php
		$i = 0;
		foreach ( $v['facesDetail'] as $f ) :
			$i++;
			?>
			<tr><td><?php echo (int) $i; ?></td><td><?php echo esc_html( number_format( $f['area'] ) ); ?></td><td><?php echo esc_html( $f['pitch'] . '/12' ); ?></td></tr>
		<?php endforeach; ?>
		</table>
		<p class="note">Measured from Google aerial imagery<?php echo $v['imageryDate'] ? ' taken ' . esc_html( substr( $v['imageryDate'], 0, 7 ) ) : ''; ?> (detail level: <?php echo esc_html( strtolower( $v['imageryQuality'] ) ); ?>). Ridge, hip, valley and eave lengths are measured during your free inspection.</p>
	<?php else : ?>
		<p class="warn">We could not measure this roof from aerial imagery. Our team has your details and will measure it in person, free of charge.</p>
	<?php endif; ?>

	<h2>Next steps</h2>
	<p>Schedule your free inspection so we can confirm these measurements and give you a final quote<?php echo $v['phone'] ? ': call <b>' . esc_html( $v['phone'] ) . '</b>' : ''; ?>.</p>
	<p class="note"><?php echo esc_html( $v['disclaimer'] ); ?></p>
</div>
<?php if ( $print ) : ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print();},600);});</script><?php endif; ?>
</body></html>
