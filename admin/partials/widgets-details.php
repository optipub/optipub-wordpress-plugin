<?php
/**
 * Widgets details partial
 *
 * @package OptiPub
 */

defined('ABSPATH') or die('Direct access not allowed');
?>

<ul style="list-style: circle; margin-left: 24px;">
    <li>
        <p><?php esc_html_e('OptiPub does not permit Server-side language code, such as PHP, in any Content (Resources, Pages, Custom Content etc).', 'optipub'); ?></p>
        <p><?php esc_html_e('Add additional code in the created widget file after saving.', 'optipub'); ?></p>
    </li>
    <li>
        <p><?php esc_html_e('Editing a Widget with an existing "File Name" does not affect the additional code, it only replaces the custom content macro reference.', 'optipub'); ?></p>
    </li>
    <li>
        <p><?php esc_html_e('Deleting a widget does not delete the widget file associated with the widget from your Theme Directory.', 'optipub'); ?></p>
    </li>
    <li>
        <p><?php esc_html_e('Clearing sync data and Deactivating the Plugin will not delete the widgets created here. But will delete Custom Content macros referenced by the widgets.', 'optipub'); ?></p>
    </li>
    <li>
        <p><?php esc_html_e('Deleting the OptiPub will delete all OptiPub synced data including the Custom Content Widget Sidebars, except for the Widget File Names the Plugin created in your Theme Directory.', 'optipub'); ?></p>
    </li>
</ul> 