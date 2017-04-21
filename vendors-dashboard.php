<?php
get_header();
global $WVBD;
?>
<div id="content" class="woocommerce container">
		<div class="row product content-area">

			<main id="main" class="site-main col-xl-12" role="main">
				<?php $WVBD->show_dashboard(); ?>
			</main>
        </div>
</div>
<?php get_footer(); ?>
