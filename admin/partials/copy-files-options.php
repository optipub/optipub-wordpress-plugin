<?php
defined('ABSPATH') or die('Direct access not allowed');
?>

<p style="color: #8b0000;">
    <strong>Remember to uncomment and configure the optional "Helper Functions" after copying the files to your theme directory.</strong>
</p>

<p>Copy these OptiPub Plugin Template files to your Theme:</p>

<ul style="list-style: none;">
    <li>
        <input type="checkbox" id="page-tempates" name="page-templates" value="page-templates">
        <span>
            <?php esc_html_e('Page Templates (inc/optipub/theme-templates) - single page and archive page templates.', 'optipub'); ?>
            <a href="#" style="float: right;" data-toggle-target="#details-page">[<?php esc_html_e('Details', 'optipub'); ?>]</a>
        </span>
        <div id="details-page" class="details-dropdown">
            <p><?php esc_html_e('Page templates contains all the pages you would need for creating a theme. But they probably won\'t work well with other themes since they use Bootstrap CSS classes and some custom CSS classes.', 'optipub'); ?></p>
            <p><?php esc_html_e('These template files are not plug and play.', 'optipub'); ?></p>
            <p><?php esc_html_e('But the PHP in them may be useful as a guide when creating or modifying a theme.', 'optipub'); ?></p>
        </div>
    </li>

    <li>
        <input type="checkbox" id="template-parts" name="template-parts" value="template-parts">
        <span>
            <?php esc_html_e('Template Parts (inc/optipub/template-parts) - use include or get_template_part() with these files in your theme.', 'optipub'); ?>
            <a href="#" style="float: right;" data-toggle-target="#details-parts">[<?php esc_html_e('Details', 'optipub'); ?>]</a>
        </span>
        <div id="details-parts" class="details-dropdown">
            <p><?php esc_html_e('Template Parts include files with reusable code that you may use many times throughout your theme.', 'optipub'); ?></p>
            <p><?php esc_html_e('Checkout the files in page templates to see how they are used in the OptiPub Theme.', 'optipub'); ?></p>
        </div>
    </li>

    <li>
        <input type="checkbox" id="widget-tempates" name="widget-templates" value="widget-templates">
        <span>
            <?php esc_html_e('Widget Templates (inc/optipub/widget-templates) - use these files to create custom content widgets below.', 'optipub'); ?>
            <a href="#" style="float: right;" data-toggle-target="#details-widget">[<?php esc_html_e('Details', 'optipub'); ?>]</a>
        </span>
        <div id="details-widget" class="details-dropdown">
            <?php include plugin_dir_path(__FILE__) . 'widget-templates-details.php'; ?>
        </div>
    </li>

    <?php include plugin_dir_path(__FILE__) . 'helper-functions-details.php'; ?>

    <?php if ($saved_theme_changed) { ?>
        <li style="color: red;">
            <input type="checkbox" id="copy-previous-theme" name="copy-previous-theme" value="copy-previous-theme">
            <span>Copy Files from [Previous Theme Directory] to [Theme Directory]</span>
        </li>
    <?php } ?>
</ul> 