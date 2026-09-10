<?php

namespace OptiPub\Pages\Admin\Handlers;

use Exception;
use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;
use OptiPub\Pages\Admin\OptiPubWidgets;

class WidgetHandler extends BaseController
{
    /**
     * Handle widget operations
     */
    public function handleView(string $itemId): array
    {
        if (!is_active_sidebar($itemId)) {
            throw new Exception(sprintf('Sidebar %s not found', esc_html($itemId)));
        }

        ob_start();
        dynamic_sidebar($itemId);
        $sidebarContent = ob_get_clean();

        return [
            'display' => $sidebarContent,
            'source'  => '<pre>' . htmlspecialchars($sidebarContent, ENT_QUOTES, 'UTF-8') . '</pre>',
        ];
    }

    public function handleEdit(?string $itemId = null): string
    {
        ob_start();
        $this->displayWidgetForm($itemId);

        return ob_get_clean();
    }

    public function handleExport(): string
    {
        $widgets = get_option('optipub_cc_widgets');

        if (empty($widgets)) {
            throw new Exception('No widgets found to export');
        }

        $widgets_json = wp_json_encode($widgets, JSON_PRETTY_PRINT);

        if (false === $widgets_json) {
            throw new Exception('Failed to encode widgets data');
        }

        ob_start();
        echo '<h3>Export Widgets</h3>';
        echo '<textarea id="widget-export-field" name="widget-export-field" rows="30" style="width: 100%;">'
            . esc_textarea($widgets_json)
            . '</textarea>';

        return ob_get_clean();
    }

    public function handleImport(string $import_content): string
    {
        $widgets_array = json_decode($import_content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception('Invalid JSON: ' . esc_html(json_last_error_msg()));
        }

        if (!is_array($widgets_array)) {
            throw new Exception('Invalid widget data format');
        }

        ob_start();
        echo '<h3>Import Widgets</h3>';
        echo '<p>json</p>';
        echo '<pre>' . esc_html($import_content) . '</pre>';
        echo '<p>array</p>';

        if (is_array($widgets_array)) {
            echo 'is array';
        } else {
            echo 'not array';
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Reason: Debugging information
        echo '<pre>' . esc_html(print_r($widgets_array, true)) . '</pre>';
        echo '<p>loop</p>';

        foreach ($widgets_array as $key => $widget) {
            echo '<p>' . esc_html($key) . '</p>';

            if (is_array($widget)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Reason: Debugging information
                echo '<pre>' . esc_html(print_r($widget, true)) . '</pre>';
            } else {
                echo '<p>' . esc_html($widget) . '</p>';
            }
        }

        return ob_get_clean();
    }

    public function handleSave(array $widget_data): string
    {
        $validated_data = $this->validateWidgetData($widget_data);

        return $this->saveWidget($validated_data);
    }

    /**
     * Validate and process widget data
     *
     * @param array $data Widget form data
     * @return array Validated widget data
     */
    public function validateWidgetData(array $data): array
    {
        if (empty($data['title']) || empty($data['sidebar']) || empty($data['filename'])) {
            throw new Exception('Required fields are missing');
        }

        return [
            'name'           => sanitize_text_field($data['title']),
            'id'             => sanitize_key($data['sidebar']),
            'description'    => sanitize_text_field($data['description'] ?? ''),
            'class'          => sanitize_html_class($data['class'] ?? $data['sidebar']),
            'before_widget'  => wp_kses_post($data['before_widget'] ?? ''),
            'after_widget'   => wp_kses_post($data['after_widget'] ?? ''),
            'before_title'   => wp_kses_post($data['before_title'] ?? ''),
            'after_title'    => wp_kses_post($data['after_title'] ?? ''),
            'filename'       => sanitize_file_name($data['filename']),
            'custom_content' => sanitize_text_field($data['custom_content']),
            'widgets_path'   => Constants::OPTIPUB_THEME_WIDGET_PATH,
        ];
    }

    public function handleImportView(): string
    {
        ob_start();
        echo '<h3>Import Widgets</h3>';
        echo '<form id="import-widgets-submit" method="post">';
        echo '<textarea id="widget-import-field" name="widget-import-field" rows="30" style="width: 100%;" autocomplete="off"></textarea>';
        echo '<input id="submit-import-widgets" type="submit" value="Import" class="button button-primary button-optipub" style="float: right; text-align: center;">';
        echo '</form>';

        return ob_get_clean();
    }

    private function saveWidget(array $widget_data): string
    {
        $theme_directory = rtrim(get_template_directory(), '/');
        $widgets_directory = $theme_directory . '/' . Constants::OPTIPUB_THEME_WIDGET_PATH;

        if (!is_dir($widgets_directory) && !wp_mkdir_p($widgets_directory)) {
            $this->logStaticError('Failed to create widget directory');

            throw new Exception('Failed to create widget directory');
        }
        $this->logStaticError('Widgets Directory Created Successfully: ' . $widgets_directory);

        $this->updateWidgetOption($widget_data);
        $this->createWidgetFile($widgets_directory . '/' . $widget_data['filename'], $widget_data['custom_content']);
        $this->addWidgetToSidebar($widget_data);

        $this->logStaticError($widget_data['id'] . ' Sidebar Widget Created Successfully!');

        return $widget_data['id'];
    }

    private function updateWidgetOption(array $widget_data): void
    {
        $current_widgets = get_option(Constants::OPTIPUB_WIDGET_OPTION, []);
        $widget_updated = false;

        foreach ($current_widgets as $key => $widget) {
            if ($widget['id'] === $widget_data['id']) {
                $current_widgets[$key] = $widget_data;
                $widget_updated = true;
                break;
            }
        }

        if (!$widget_updated) {
            $current_widgets[] = $widget_data;
        }

        update_option(Constants::OPTIPUB_WIDGET_OPTION, $current_widgets, 'no');
    }

    private function createWidgetFile(string $file_path, string $custom_content): void
    {
        if (file_exists($file_path)) {
            $this->updateExistingWidgetFile($file_path, $custom_content);
        } else {
            $this->createNewWidgetFile($file_path, $custom_content);
        }
    }

    private function updateExistingWidgetFile(string $file_path, string $custom_content): void
    {
        $file_content = file_get_contents($file_path);

        if (false === $file_content) {
            throw new Exception('Failed to read existing widget file');
        }

        $pattern = "/get_option\('(?:optipub_macro_)?\{[$]custom_[^}]+\}'\)/";
        // Prefix option name to avoid conflicts
        $option_name = 'optipub_macro_' . $custom_content;
        $replacement = "get_option('" . $option_name . "')";
        $modified_contents = preg_replace($pattern, $replacement, $file_content);

        if (false === file_put_contents($file_path, $modified_contents)) {
            throw new Exception('Failed to update widget file');
        }
    }

    private function createNewWidgetFile(string $file_path, string $custom_content): void
    {
        // Prefix option name to avoid conflicts
        $option_name = 'optipub_macro_' . $custom_content;
        $file_content = "<?php echo get_option('" . $option_name . "'); ?>";

        if (false === file_put_contents($file_path, $file_content)) {
            throw new Exception('Failed to create widget file');
        }
    }

    private function addWidgetToSidebar(array $widget_data): void
    {
        $widget_id_base = $widget_data['id'];
        $sidebar = $widget_data['id'];
        $widget_name = Constants::OPTIPUB_REGISTER_WIDGET_PREFIX . $widget_id_base;
        $option_widget_name = Constants::OPTIPUB_WIDGET_PREFIX . $widget_id_base;

        $sidebars = get_option('sidebars_widgets', []);
        $sidebars[$sidebar] = [];

        $widget_opts = [
            '_multiwidget' => 1,
            1              => $widget_data,
        ];

        update_option($option_widget_name, $widget_opts);
        register_widget(new OptiPubWidgets($widget_id_base, $widget_data['name']));

        $sidebars[$sidebar][1] = "{$widget_name}-1";
        update_option('sidebars_widgets', $sidebars);
    }

    /**
     * Display widget form for editing
     */
    private function displayWidgetForm(?string $itemId = null): void
    {
        $theme_directory = rtrim(get_template_directory(), '/');
        $widget_directory = $theme_directory . '/' . Constants::OPTIPUB_THEME_WIDGET_PATH;

        $current_widgets = get_option(Constants::OPTIPUB_WIDGET_OPTION, []);
        $edit = null;

        // Find existing widget
        if ($itemId) {
            foreach ($current_widgets as $widget) {
                if ($widget['id'] === $itemId) {
                    $edit = $widget;
                    break;
                }
            }
        }

        ?>

        <p style="margin-top: 0px; margin-bottom: 24px;">Widget File Names will be created in:
            <br>
            <code><?php echo esc_html(rtrim($widget_directory, '/')); ?></code>
        </p>
        <form id="save-widgets" method="post">
            <p style="margin-bottom: 0.5em;">* Required</p>
            <div class="settings">
                <label for="widget_title">Title*</label>
                <span class="dashicons dashicons-info" data-tooltip="The name or title of the sidebar displayed in the Widgets interface."></span>
                <input id="widget_title" class="widget_validate widget_required" name="optipub_cc_widgets[title]" value="<?php echo isset($edit['name']) ? esc_attr($edit['name']) : ''; ?>" placeholder="My Widget Title" type="text">

                <label for="widget_sidebar">Sidebar ID*</label>
                <span class="dashicons dashicons-info" data-tooltip="The unique identifier by which the sidebar will be called. This value will replace '%1$s' in element modifiers below."></span>
                <input id="widget_sidebar" class="widget_validate widget_required" name="optipub_cc_widgets[sidebar]" value="<?php echo isset($edit['id']) ? esc_attr($edit['id']) : ''; ?>" placeholder="sidebar-1" type="text">

                <label for="widget_filename">File Name*</label>
                <span class="dashicons dashicons-info" data-tooltip="Only the macro name will be replaced when editing an existing File Name."></span>
                <input id="widget_filename" class="widget_validate widget_required" name="optipub_cc_widgets[filename]" value="<?php echo isset($edit['filename']) ? esc_attr($edit['filename']) : ''; ?>" placeholder="optipub_widget_title.php" type="text">

                <label for="widget_description">Description</label>
                <span class="dashicons dashicons-info" data-tooltip="Description of the sidebar displayed in the Widgets interface."></span>
                <input id="widget_description" name="optipub_cc_widgets[description]" value="<?php echo isset($edit['description']) ? esc_attr($edit['description']) : ''; ?>" placeholder="Description of widget purpose" type="text">

                <label for="widget_custom_content">Custom Content</label>
                <span class="dashicons dashicons-info" data-tooltip="Macro name created in OptiPub &#8594; Content &#8594; Custom Content."></span>
                <input id="widget_custom_content" class="widget_validate widget_required" name="optipub_cc_widgets[custom_content]" value="<?php echo isset($edit['custom_content']) ? esc_attr($edit['custom_content']) : ''; ?>" placeholder="{$custom_wp_custom_content}" type="text">

                <label for="widget_classname">Class Name</label>
                <span class="dashicons dashicons-info" data-tooltip="This value will replace '%2$s' in element modifiers below."></span>
                <input id="widget_classname" class="widget_validate" name="optipub_cc_widgets[class]" value="<?php echo isset($edit['class']) ? esc_attr($edit['class']) : ''; ?>" placeholder="sidebar-1" type="text">

                <label for="widget_before_widget">Before Widget</label>
                <span class="dashicons dashicons-info" data-tooltip="HTML content to prepend to widget's HTML output."></span>
                <textarea id="widget_before_widget" class="element_validate" name="optipub_cc_widgets[before_widget]" placeholder="&lt;div id=&quot;%1$s&quot; class=&quot;widget %2$s&quot;&gt;" rows="1"><?php echo isset($edit['before_widget']) ? esc_textarea($edit['before_widget']) : ''; ?></textarea>

                <label for="widget_after_widget">After Widget</label>
                <span class="dashicons dashicons-info" data-tooltip="HTML content to append to widget's HTML output."></span>
                <textarea id="widget_after_widget" class="element_validate" name="optipub_cc_widgets[after_widget]" placeholder="&lt;/div&gt;" rows="1"><?php echo isset($edit['after_widget']) ? esc_textarea($edit['after_widget']) : ''; ?></textarea>

                <label for="widget_before_title">Before Title</label>
                <span class="dashicons dashicons-info" data-tooltip="HTML content to prepend to the sidebar title when displayed."></span>
                <textarea id="widget_before_title" class="element_validate" name="optipub_cc_widgets[before_title]" placeholder="&lt;h2 class=&quot;op-title&quot;&gt;" rows="1"><?php echo isset($edit['before_title']) ? esc_textarea($edit['before_title']) : ''; ?></textarea>

                <label for="widget_after_title">After Title</label>
                <span class="dashicons dashicons-info" data-tooltip="HTML content to append to the sidebar title when displayed."></span>
                <textarea id="widget_after_title" class="element_validate" name="optipub_cc_widgets[after_title]" placeholder="&lt;/h2&gt;" rows="1"><?php echo isset($edit['after_title']) ? esc_textarea($edit['after_title']) : ''; ?></textarea>

                <input type="submit" name="submit" id="submit-widgets" class="button button-primary button-optipub" value="Save Widget">
            </div>
        </form>
        <?php
    }

    // ... rest of widget methods ...
}
