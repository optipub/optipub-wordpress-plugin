<?php
/**
 * Widgets table partial
 *
 * @package OptiPub
 */

defined('ABSPATH') or die('Direct access not allowed');

$widgets_url = admin_url('widgets.php');
?>

<div id="widgets-accordion" class="widgets-table-title accordion">
    <a href="<?php echo esc_url($widgets_url); ?>" target="_blank">
        <h3><?php esc_html_e('Custom Content Widget Sidebars', 'optipub'); ?></h3>
    </a>
    <button type="button" class="button button-secondary widget-edit">
        <?php esc_html_e('Create New Widget', 'optipub'); ?>
    </button>
</div>

<div id="widgets-content" class="panel">
    <p style="margin-top: 0px; margin-bottom: 16px;">
        <?php
        printf(
            '%s <a href="%s" target="_blank">%s</a>%s<a href="#" style="float: right;" data-toggle-target="#details-create-widgets">[%s]</a>',
            esc_html__('The OptiPub Plugin will register all Widget Sidebars created in the Plugin.', 'optipub'),
            'https://developer.wordpress.org/reference/functions/register_sidebar/',
            esc_html__('Read How WordPress Registers Sidebars', 'optipub'),
            '<span>',
            esc_html__('Details', 'optipub')
        );
?>
    </p>

    <div id="details-create-widgets" class="details-dropdown">
        <?php include plugin_dir_path(__FILE__) . 'widgets-details.php'; ?>
    </div>

    <?php
    // Create widgets table
    $list_table = $this->createAdminWidgetsTable();
?>

    <div id="import-export-widgets">
        <a href="#" id="export-widgets"><?php esc_html_e('Export', 'optipub'); ?></a>
    </div>
</div> 