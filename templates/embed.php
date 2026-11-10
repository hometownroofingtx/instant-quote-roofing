<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!doctype html><html <?php language_attributes(); ?>><head>
<meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instant Roof Quote</title>
<?php wp_head(); ?>
<style>html,body{margin:0!important;padding:0!important;min-width:0!important;background:transparent!important}#wpadminbar,.htr-quote-sidebar,chat-widget,#htr-storm-bar,.site-header__announcement{display:none!important}.htr-embed-form{padding:8px}.htr-embed-form .htr-quote-layout{display:block;max-width:820px}.htr-embed-form #htr-rr{max-width:none}</style>
</head><body><main class="htr-embed-form"><?php echo $content; // Existing shortcode output. ?></main><?php wp_footer(); ?>
<script>(function(){var main=document.querySelector('.htr-embed-form'),last=0;function resize(){var height=Math.ceil(main.getBoundingClientRect().height);if(height!==last&&window.parent!==window){last=height;window.parent.postMessage({type:'htr-embed-height',height:height},window.location.origin);}}if(window.ResizeObserver)new ResizeObserver(resize).observe(main);window.addEventListener('load',resize);resize();})();</script>
</body></html>
