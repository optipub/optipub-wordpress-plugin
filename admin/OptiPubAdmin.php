<?php
/**
 * OptiPub Admin Dashboard View
 *
 * @package OptiPub
 */

// If this file is called directly, abort
defined('ABSPATH') or die('Direct access not allowed');

use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;

$setup_tutorial = 'https://gitlab.com/optipub/optipub/-/blob/develop/readme.md';

// Update plugin path for current site
update_option(Constants::OPTIPUB_PLUGIN_PATH, rtrim(plugin_dir_path(__DIR__), '/'));

// Initialize API instance
$content_api = BaseController::createOptiPubApiInstance();
$publications = '';

// Get current site settings
$url = get_option(Constants::OPTIPUB_BASE_API_URL);
$token = get_option(Constants::OPTIPUB_API_TOKEN);
$verified = get_option(Constants::OPTIPUB_TOKEN_VERIFIED);

$readonly = '';

if (!empty($token)) {
    // Retrieve active Publications from the OptiPub Instance
    try {
        $publications = $content_api->getPublications();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Make input fields read only if Token has not been verified
if (!empty($url) && !empty($token)) {
    if (get_option(Constants::OPTIPUB_TOKEN_VERIFIED) == '1') {
        //$readonly = ' readonly';
        $error_message = null;
    } else {
        $error_message = 'Error validating API Token or OptiPub Domain.';
    }
}

// Theme directory handling
$current_theme_directory = rtrim(get_stylesheet_directory(), '/');
$templates_directory = get_option(Constants::OPTIPUB_PLUGIN_PATH) . '/' . Constants::OPTIPUB_TEMPLATES_PATH;
$active_theme_directory = get_option(Constants::OPTIPUB_ACTIVE_THEME_DIR);

$saved_theme_changed = false;

// Check if theme directory has changed
if ($current_theme_directory !== $active_theme_directory && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
    $saved_theme_changed = true;
}
?>

<div id="optipub" class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('OptiPub', 'optipub'); ?></h1>
    
    <p class="admin-description">
        <?php esc_html_e('The OptiPub syncs content from a connected OptiPub instance, and creates Custom Post Types for the synced content. The plugin can also create Dynamic Sidebar Widgets (optionally) using OptiPub Custom Content.', 'optipub'); ?>
    </p>
    
    <p class="admin-description">
        <?php
        printf(
            /* translators: %s: URL to setup tutorial */
            esc_html__('Read the %s.', 'optipub'),
            sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($setup_tutorial),
                esc_html__('Plugin Setup Tutorial', 'optipub')
            )
        );
?>
    </p>

    <!-- Theme Change Warning Modal -->
    <?php if ($saved_theme_changed) { ?>
    <div id="themeChanged" class="optipub-modal">
        <div class="optipub-modal-content">
            <span id="closeThemeChanged" class="optipub-close">&times;</span>
            <h2><?php esc_html_e('Warning', 'optipub'); ?></h2>
            <div class="op-warning-message">
                <h4>
                    <?php esc_html_e('Your Theme Directory Has Changed. Click the "Copy Files to Theme" dropdown and choose the new option "Copy Files from [Previous Theme Directory] to [Theme Directory]"', 'optipub'); ?>
                </h4>
                <ul style="list-style: none;">
                    <li>
                        <input type="checkbox" id="ignore-theme-change" name="ignore-theme-change" value="ignore-theme-change">
                        <span><?php esc_html_e("Don't show me this again.", 'optipub'); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Publication Error Display -->
    <div id="publication-error">
        <?php if (!empty($error_message)) { ?>
            <br>
            <p class="error-message">
                <?php
        printf(
            /* translators: %1$s: line break, %2$s: error message */
            esc_html__('There was an error syncing your OptiPub Domain.%1$s%2$s', 'optipub'),
            '<br>',
            esc_html($error_message)
        );
            ?>
            </p>
            <br>
        <?php } ?>
    </div>

    <!-- OptiPub Sync Options -->
    <div id="optipub-options" class="accordion-container">
        <button id="options-accordion" class="accordion">
            <?php esc_html_e('OptiPub Sync Options', 'optipub'); ?>
        </button>
        
        <div id="widgets-panel" class="panel">
            <p>
                <?php
            printf(
                '%s <a href="%s" target="_blank">%s</a>',
                esc_html__('The OptiPub Plugin will register all widget sidebars created in the plugin.', 'optipub'),
                'https://developer.wordpress.org/reference/functions/register_sidebar/',
                esc_html__('Read How WordPress Registers Sidebars', 'optipub')
            );
?>
            </p>

            <!-- Re-Sync Button -->
            <button id="resync-all" class="button button-primary button-optipub" 
                    style="margin-top: 0px !important; width: 75px !important; height: 30px !important; background: #999; border-color: #999;">
                <?php esc_html_e('Re-Sync', 'optipub'); ?>
            </button>

            <!-- Settings Form -->
            <div class="save-changes-wrap">               
                <?php include plugin_dir_path(__FILE__) . 'partials/settings-form.php'; ?>
            </div>
        </div>
    </div>

    <!-- Copy Files Section -->
    <?php include plugin_dir_path(__FILE__) . 'partials/copy-files.php'; ?>

    <!-- Custom Content Widgets -->
    <div id="widgets-table" class="accordion-container">
        <form id="optipub-widget-table" method="post">
            <?php include plugin_dir_path(__FILE__) . 'partials/widgets-table.php'; ?>
        </form>
    </div>

    <!-- Widget Modal -->
    <div id="optipubModal" class="optipub-modal">
        <div class="optipub-modal-content">
            <span class="optipub-close">&times;</span>
            <span id="widget-view-options">
                <a href="#" data-toggle-target=".widget-view-display" id="widget-option-display">
                    <?php esc_html_e('Source', 'optipub'); ?>
                </a>
            </span>
            <div class="optipub-close-sp"></div>
            <div id="optipubModalBody"></div>
        </div>
    </div>

    <!-- Controls above progress log section -->
    <div class="optipub-actions" style="margin-bottom: 10px;">
        <button id="optipub-clear-log" class="button button-secondary"><?php esc_html_e('Clear Log', 'optipub'); ?></button>
        <button id="optipub-force-sync" class="button button-primary"><?php esc_html_e('Force Sync', 'optipub'); ?></button>
    </div>

    <!-- Plugin Activity Log -->
    <div class="ajax-response">
        <pre class="pre-response">
        <?php
        if (!empty(get_option(Constants::OPTIPUB_SUMMARY_LOG))) {
            $summaryLog = BaseController::getSummaryLog();

            if ($summaryLog) {
                echo esc_html(trim($summaryLog));
            }
        }
?>
        </pre>
    </div>
</div>