<?php

/**
 * Template Part: Load and play OptiPub Vidoes
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();

$videoPlayerMeta = get_post_meta($post_id, 'video_player', true);

if (
    is_array($videoPlayerMeta) && isset($videoPlayerMeta[0]) && isset($videoPlayerMeta[1])
) {
    $videoPlayer = $videoPlayerMeta[0];
    $videoPlayerData = $videoPlayerMeta[1];
}

if (isset($videoPlayer) && isset($videoPlayerData)) {
    if (
        'youtube' == $videoPlayer && isset($videoPlayerData->video_id)
    ) { ?>

		<iframe type="text/html" src="https://youtube.com/embed/<?php echo esc_attr($videoPlayerData->video_id); ?>?autoplay=1" frameborder="0"></iframe>

	<?php } elseif (
	    'wistia' == $videoPlayer && isset($videoPlayerData)
	) { ?>

		<iframe src="//fast.wistia.net/embed/iframe/<?php echo esc_attr($videoPlayerData->video_id); ?>" allowtransparency="true" frameborder="0" scrolling="no" class="wistia_embed" name="wistia_embed" allowfullscreen mozallowfullscreen webkitallowfullscreen oallowfullscreen msallowfullscreen width="600" height="366"></iframe>

<?php } elseif (
    ('video.js' == $videoPlayer || 'videojs' == $videoPlayer) && (isset($videoPlayerData->mp4) && isset($videoPlayerData->webm))
) {
    // Enqueue video.js assets properly
    wp_enqueue_style('videojs-css', get_template_directory_uri() . '/css/video-js.css', [], '1.0.0');
    wp_enqueue_script('videojs-js', get_template_directory_uri() . '/js/video.js', [], '1.0.0', true);
    wp_enqueue_script('videojs-init', get_template_directory_uri() . '/js/init.js', ['videojs-js'], '1.0.0', true);
    echo '<video id="vjsx" class="video-js vjs-default-skin" controls preload="auto" autoplaydata-setup="{}" height="338" width="600">';
    // echo '    ' . '<source src="/mp4/' . $videoPlayerData->mp4 . '"' . ' type="video/mp4">'; # replace src with path to mp4 file
    // echo '    ' . '<source src="/webm/' . $videoPlayerData->webm . '"' . ' type="video/webm">'; # replace src with path to webm file
    echo '</video>';
}
}
?>