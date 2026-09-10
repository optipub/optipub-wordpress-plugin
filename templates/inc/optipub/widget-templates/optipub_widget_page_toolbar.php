<?php

if (! defined('ABSPATH')) {
    exit;
}

$facebook_profile = 'optipub';
$facebook_social_icon = OPTIPUB_PLUGIN_URL . 'public/img/facebook-social-icon-widget-64x64-901.png';

$twitter_profile = 'optipub';
$twitter_social_icon = OPTIPUB_PLUGIN_URL . 'public/img/twitter-social-icon-widget-64x64-902.png';

$contact_page = '/contact';
?>

<div id="page-toolbar" data-spy="affix">
	<div class="toolbar-top">
		<a class="social-media-icon" href="https://www.facebook.com/<?php echo esc_attr($facebook_profile); ?>" target="_blank" title="Like us on Facebook">
			<img src="<?php echo esc_url($facebook_social_icon); ?>" alt="Like us on Facebook" width="34" height="34">
		</a>
		<a class="social-media-icon" href="https://www.twitter.com/<?php echo esc_attr($twitter_profile); ?>" target="_blank" title="Follow us on Twitter">
			<img src="<?php echo esc_url($twitter_social_icon); ?>" alt="Follow us on Twitter" width="34" height="34">
		</a>
	</div>
	<div class="toolbar-bottom">
		<a href="javascript:window.print();" data-toggle="tooltip" data-placement="right" data-original-title="Print"><i class="fas fa-print"></i></a>
		<a href="<?php echo esc_url($contact_page); ?>" data-toggle="tooltip" data-placement="right" data-original-title="Contact Us"><i class="fas fa-phone-alt"></i></a>
	</div>
</div>