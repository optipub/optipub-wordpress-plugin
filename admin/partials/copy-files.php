<?php
defined('ABSPATH') or die('Direct access not allowed');

use OptiPub\Base\Constants;

?>

<div id="copy-files" class="accordion-container">
    <button id="copy-accordion" class="accordion">
        <?php esc_html_e('Copy Files to Theme', 'optipub'); ?>
    </button>
    <div id="copy-content" class="panel">
        <form id="copy-submit" method="post">
            <div id="templates-files-anchor"></div>
            <p>
                <?php
                printf(
                    '%s <a href="%s" target="_blank">%s</a>',
                    esc_html__('For more information on how to configure these files - Read the', 'optipub'),
                    esc_url($setup_tutorial),
                    esc_html__('Plugin Setup Tutorial', 'optipub')
                );
?>
            </p>
            
            <?php include plugin_dir_path(__FILE__) . 'copy-files-options.php'; ?>

            <p style="margin-bottom:0px;"><?php esc_html_e('Copied Files to Theme Directory Log', 'optipub'); ?></p>
            <pre id="copy-progress">
                <?php
if (get_option(Constants::OPTIPUB_COPY_FILE_LOG)) {
    echo "\n" . esc_html(trim(get_option(Constants::OPTIPUB_COPY_FILE_LOG)));
}
?>
            </pre>

            <div id="copy-form">
                <?php if ($saved_theme_changed) { ?>
                    <label for="previous-theme-directory" style="color: red;">
                        <?php esc_html_e('Previous Theme Directory', 'optipub'); ?>
                    </label>
                    <input id="previous-theme-directory" name="previous-theme-directory"
                           value="<?php echo esc_attr(get_option(Constants::OPTIPUB_ACTIVE_THEME_DIR)); ?>" 
                           type="text" readonly>
                <?php } ?>

                <label for="theme-directory"><?php esc_html_e('Theme Directory', 'optipub'); ?></label>
                <input id="theme-directory" name="theme-directory" 
                       value="<?php echo esc_attr($current_theme_directory); ?>" 
                       type="text" readonly>

                <label for="plugin-directory"><?php esc_html_e('Templates Directory', 'optipub'); ?></label>
                <input id="plugin-directory" name="plugin-directory" 
                       value="<?php echo esc_attr($templates_directory); ?>" 
                       type="text" readonly>

                <input id="copy-button" type="submit" value="<?php esc_attr_e('Copy Files', 'optipub'); ?>"
                       class="button button-primary button-optipub">
                <button id="copy-clear" class="button button-primary button-optipub">
                    <?php esc_html_e('Clear Log', 'optipub'); ?>
                </button>
            </div>
        </form>
    </div>
</div> 