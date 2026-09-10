<?php

/**
 * Settings form partial
 *
 * @package OptiPub
 */

defined('ABSPATH') or die('Direct access not allowed');

use OptiPub\Base\Constants;

?>
<form id="save-changes" method="post">
    <div class="settings">
        <label for="editToken"><?php esc_html_e('* Required to start sync', 'optipub'); ?></label>
        <span></span>
        <span></span>

        <!-- OptiPub Sync Settings -->
        <span class="options-title"></span><span></span><span></span>
        <span class="options-title">Sync Settings</span><span></span>
        <button type="button" id="editToken" class="op-button-warning"><?php esc_html_e('Edit', 'optipub'); ?></button>

        <label for="url"><?php esc_html_e('OptiPub Domain*', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Ex. https://<domain>.app.optipub.com', 'optipub'); ?>">
        </span>
        <input id="url" name="url" value="<?php echo esc_attr(get_option(Constants::OPTIPUB_BASE_API_URL)); ?>" type="text" <?php echo esc_attr($readonly); ?>>

        <label for="token"><?php esc_html_e('API Token*', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('KEEP THIS TOKEN SECURE AND DO NOT SHARE IT WITH ANYONE - Create an API Token for a user - OptiPub > User > Preferences > API Keys > [Create New Token]|Read the Setup Tutorial to configure permissions for an API User', 'optipub'); ?>">
        </span>
        <input id="token"
            name="token"
            autocomplete="off"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_API_TOKEN)); ?>"
            type="password"
            <?php echo esc_attr($readonly); ?>>
        <label for="optipub_opengraph"><?php esc_html_e('Open Graph Image Link', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Only required if your Theme is using Page Templates or Helper Functions copied from this Plugin, which call get_option(Constants::OPTIPUB_OPENGRAPH_IMAGE_LINK).', 'optipub'); ?>">
        </span>
        <input id="optipub_opengraph"
            name="optipub_opengraph"
            autocomplete="off"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_OPENGRAPH_IMAGE_LINK)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <!-- OptiPub Publication Settings -->
        <span class="options-title"></span><span></span><span></span>
        <span class="options-title">Login Settings</span><span></span><span></span>
        <label for="wp_auth_token"><?php esc_html_e('WP Auth Token', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Create a WP Auth Token to authenticate subscriber logins through OptiPub - KEEP THIS TOKEN SECURE AND DO NOT SHARE IT WITH ANYONE - OptiPub > Administration > System Settings > Integrations > [Activate] Wordpress', 'optipub'); ?>">
        </span>
        <input id="wp_auth_token"
            name="wp_auth_token"
            autocomplete="off"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_WP_AUTH_TOKEN)); ?>"
            type="password"
            <?php echo esc_attr($readonly); ?>>

        <!-- OptiPub Publication Settings -->
        <span class="options-title"></span><span></span><span></span>
        <span class="options-title">Publication Settings</span><span></span><span></span>

        <label for="optipub_publication_id"><?php esc_html_e('Primary Publication*', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Verify your Domain and API Token to view Publications', 'optipub'); ?>">
        </span>
        <select id="optipub_publication_id" name="optipub_publication_id" <?php echo esc_attr($readonly); ?>>
            <option selected disabled value="select-publication">
                <?php esc_html_e('-- Select a publication --', 'optipub'); ?>
            </option>
            <?php if ($publications && !empty($publications->data)) {
                $selected_publication = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

                foreach ($publications->data as $publication) { ?>
                    <option value="<?php echo esc_attr($publication->id); ?>"
                        <?php selected($selected_publication, $publication->id); ?>>
                        <?php echo esc_html($publication->name); ?>
                    </option>
            <?php }
                } ?>
        </select>

        <label for="optipub_additional_publications"><?php esc_html_e('Additional Publications', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Select additional publications to sync', 'optipub'); ?>">
        </span>
        <div class="publication-manager">
            <div class="selected-publications">
                <?php
                    $additional_publications = get_option(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS, []);

if ($publications && !empty($publications->data)) {
    foreach ($publications->data as $publication) {
        if (in_array($publication->id, $additional_publications)) {
            ?>
                            <div class="selected-publication" data-id="<?php echo esc_attr($publication->id); ?>">
                                <span class="publication-name"><?php echo esc_html($publication->name); ?></span>
                                <button type="button" class="remove-publication">×</button>
                                <input type="hidden" name="optipub_additional_publications[]"
                                    value="<?php echo esc_attr($publication->id); ?>">
                            </div>
                <?php
        }
    }
}
?>
            </div>
            <select id="publication_selector"
                <?php echo esc_attr($readonly); ?>>
                <option value=""><?php esc_html_e('-- Select a publication to add --', 'optipub'); ?></option>
                <?php if ($publications && !empty($publications->data)) {
                    foreach ($publications->data as $publication) {
                        // Skip if this is the primary publication
                        if ($publication->id == $selected_publication) {
                            continue;
                        }

                        // Skip if already selected
                        if (in_array($publication->id, $additional_publications)) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo esc_attr($publication->id); ?>"
                            data-name="<?php echo esc_attr($publication->name); ?>">
                            <?php echo esc_html($publication->name); ?>
                        </option>
                <?php }
                    } ?>
            </select>
        </div>

        <label for="additional_publications_prefix"><?php esc_html_e('Additional Publications URL Prefix', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('(optional) Prefix in front of publication slug for additional publications (e.g., premium)', 'optipub'); ?>">
        </span>
        <input id="additional_publications_prefix"
            name="<?php echo esc_attr(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX); ?>"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX)); ?>"
            type="text"
            placeholder="Leave empty to use publication slug only"
            <?php echo esc_attr($readonly); ?>>

        <!-- Page Template Options -->
        <span class="options-title"></span><span></span><span></span>
        <span class="options-title">Template Settings</span><span></span><span></span>

        <label for="optipub_home_template"><?php esc_html_e('Home Page Template', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('(optional) Set Home Page Theme Template ex. optipub_home_page.php', 'optipub'); ?>">
        </span>
        <input id="optipub_home_template"
            name="optipub_home_template"
            autocomplete="off"
            placeholder="optipub_home_page.php"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_HOME_TEMPLATE)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <label for="optipub_page_cagetory_id_home"><?php esc_html_e('Home Page Category ID', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Configure \'Home Page\' Page Category ID - OptiPub > Administration > System Settings > Categories > Page Categories', 'optipub'); ?>">
        </span>
        <input id="optipub_page_cagetory_id_home"
            name="optipub_page_cagetory_id_home"
            autocomplete="off"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_HOME)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <label for="optipub_subscribers_template"><?php esc_html_e('Subscribers Page Template', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('(optional) Set Subscribers Page Theme Template ex. optipub_subscribers.php', 'optipub'); ?>">
        </span>
        <input id="optipub_subscribers_template"
            name="optipub_subscribers_template"
            autocomplete="off"
            placeholder="optipub_subscribers.php"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <label for="optipub_page_cagetory_id_subscribers"><?php esc_html_e('Subscribers Page Category ID', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Configure \'Subscribers\' Page Category ID - pages are accessible to any logged in user - OptiPub > Administration > System Settings > Categories > Page Categories', 'optipub'); ?>">
        </span>
        <input id="optipub_page_cagetory_id_subscribers"
            name="optipub_page_cagetory_id_subscribers"
            autocomplete="off"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_SUBSCRIBERS)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <label for="optipub_login_template"><?php esc_html_e('Login Page Template', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('(optional) Set Login Page Theme Template ex. optipub_login.php', 'optipub'); ?>">
        </span>
        <input id="optipub_login_template"
            name="optipub_login_template"
            autocomplete="off"
            placeholder="optipub_login.php"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_LOGIN_TEMPLATE)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <label for="optipub_page_cagetory_id_login"><?php esc_html_e('Login Page Category ID', 'optipub'); ?></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Configure \'Login\' Page Category ID - Unauthorized requests will redirect to this page - OptiPub > Administration > System Settings > Categories > Page Categories', 'optipub'); ?>">
        </span>
        <input id="optipub_page_cagetory_id_login"
            name="optipub_page_cagetory_id_login"
            autocomplete="off"
            value="<?php echo esc_attr(get_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_LOGIN)); ?>"
            type="text"
            <?php echo esc_attr($readonly); ?>>

        <!-- OptiPub Tag Settings -->
        <span class="options-title"></span><span></span><span></span>
        <span class="options-title">Tag Settings</span><span></span><span></span>

        <label for="optipub_tags_as_tags"></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Sync OptiPub tags as WordPress tags', 'optipub'); ?>">
        </span>
        <div class="checkbox-wrapper">
            <input type="checkbox"
                id="optipub_tags_as_tags"
                name="<?php echo esc_attr(Constants::OPTIPUB_TAGS_AS_TAGS); ?>"
                value="1"
                <?php checked(get_option(Constants::OPTIPUB_TAGS_AS_TAGS), '1'); ?>
                <?php echo esc_attr($readonly); ?>>
            <label class="checkbox-label" for="optipub_tags_as_tags">
                <?php esc_html_e('Sync OptiPub tags as WordPress tags', 'optipub'); ?>
            </label>
        </div>

        <label for="optipub_tags_as_categories"></label>
        <span class="dashicons dashicons-info"
            data-tooltip="<?php esc_attr_e('Sync OptiPub tags as WordPress categories', 'optipub'); ?>">
        </span>
        <div class="checkbox-wrapper">
            <input type="checkbox"
                id="optipub_tags_as_categories"
                name="<?php echo esc_attr(Constants::OPTIPUB_TAGS_AS_CATEGORIES); ?>"
                value="1"
                <?php checked(get_option(Constants::OPTIPUB_TAGS_AS_CATEGORIES), '1'); ?>
                <?php echo esc_attr($readonly); ?>>
            <label class="checkbox-label" for="optipub_tags_as_categories">
                <?php esc_html_e('Sync OptiPub tags as WordPress categories', 'optipub'); ?>
            </label>
        </div>

        <input id="submit-data" type="submit" name="submit_save"
            value="<?php esc_attr_e('Save Changes', 'optipub'); ?>"
            class="button button-primary button-optipub">
        <span></span>
        <?php if ($url && $token) { ?>
            <div class="clear-data-container">
                <input id="clear-data" type="submit" name="submit_clear"
                    value="<?php esc_attr_e('Clear Data', 'optipub'); ?>"
                    class="button button-primary button-optipub">
                <div class="checkbox-wrapper clear-data-checkboxes">
                    <div id="clear-publication-terms-container">
                        <input type="checkbox" id="clear_publication_terms" name="clear_publication_terms" value="0">
                        <label class="checkbox-label" for="clear_publication_terms">
                            <?php esc_html_e('Clear Publications', 'optipub'); ?>
                        </label>
                    </div>

                    <div id="pause-sync-container">
                        <input type="checkbox" id="pause_after_clear" name="pause_after_clear" value="0">
                        <label class="checkbox-label" for="pause_after_clear">
                            <?php esc_html_e('Pause Sync until Save', 'optipub'); ?>
                        </label>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</form>