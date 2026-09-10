<?php
/**
 * Meta box partial
 *
 * @package OptiPub
 */

defined('ABSPATH') or die('Direct access not allowed');

// Format meta data into string
$meta_string = '';

foreach ($meta_data as $key => $value) {
    $meta_string .= $key . ': ' . implode(',', $value) . "\n";
}

// Get content type for edit link
$content = 'resources';

switch ($post->post_type) {
    case 'videos':
        $content = 'videos';
        break;
    case 'faqs':
        $content = 'faqs';
        break;
    case 'page':
        $content = 'pages';
        break;
    case 'editors':
        $content = 'authors';
        break;
}

// Get optipub_page_id
$optipub_page_id = $meta_data['optipub_page_id'][0] ?? '';

// Get publication terms
$publication_terms = wp_get_object_terms($post->ID, OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY);
$publication_name = !empty($publication_terms) ? $publication_terms[0]->name : '';
$publication_id = !empty($publication_terms) ? get_term_meta($publication_terms[0]->term_id, 'publication_id', true) : '';

// Create edit link
$edit_link = rtrim(get_option(OptiPub\Base\Constants::OPTIPUB_BASE_API_URL), '/') .
             '/content/' . $content . '/' . $optipub_page_id . '/edit';

// Get featured image
$featured_image = '';

if (!empty($meta_data['resource_thumbnail_path'][0])) {
    $featured_image = $meta_data['resource_thumbnail_path'][0];
} elseif (!empty($meta_data['editor_website_image_path'][0])) {
    $featured_image = $meta_data['editor_website_image_path'][0];
} elseif (!empty($meta_data['video_thumbnail_path'][0])) {
    $featured_image = $meta_data['video_thumbnail_path'][0];
}
?>

<div>
    <?php if ($publication_name) { ?>
        <label for="publication-info" style="margin-bottom: 5px;"><strong><?php esc_html_e('Publication', 'optipub'); ?></strong></label><br>
        <span style="margin-bottom: 10px; display: block;">
            <?php echo esc_html($publication_name); ?> (ID: <?php echo esc_html($publication_id); ?>)
        </span>
    <?php } ?>

    <label for="extra-info" style="margin-bottom: 5px;"><strong><?php esc_html_e('OptiPub ID', 'optipub'); ?></strong></label><br>
    <a href="<?php echo esc_url($edit_link); ?>" style="margin-bottom: 5px; text-decoration: none;" target="_blank">
        <?php echo esc_html($optipub_page_id); ?>
    </a><br>
    
    <?php if ($featured_image) { ?>
        <label for="extra-info" style="margin-bottom: 5px;"><strong><?php esc_html_e('Featured Image', 'optipub'); ?></strong></label><br>
        <a href="<?php echo esc_url($featured_image); ?>" target="_blank">
            <img src="<?php echo esc_url($featured_image); ?>" style="max-width:100%;">
        </a><br>
    <?php } ?>
    
    <label for="extra-info" style="margin-bottom: 5px;"><strong><?php esc_html_e('wp_postmeta data', 'optipub'); ?></strong></label><br>
    <textarea name="extra-info" rows="6" style="width:100%;" readonly><?php echo esc_textarea($meta_string); ?></textarea>
</div> 