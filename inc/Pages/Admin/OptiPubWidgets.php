<?php

namespace OptiPub\Pages\Admin;

use OptiPub\Base\Constants;

class OptiPubWidgets extends \WP_Widget
{
    private $get_widget;

    private $widgets_directory;

    /**
     * Constructor
     *
     * @param string $id_base Base ID for the widget
     * @param string $name Widget name in admin
     */
    public function __construct($id_base = '', $name = '')
    {
        $this->get_widget = $this->get_optipub_widget($id_base);
        $this->widgets_directory = rtrim(get_stylesheet_directory(), '/') . '/' . Constants::OPTIPUB_THEME_WIDGET_PATH;

        $id_base = Constants::OPTIPUB_REGISTER_WIDGET_PREFIX . $id_base;

        $widget_options = [
            'classname'                   => $this->get_widget['id'],
            'description'                 => $this->get_widget['description'],
            'customize_selective_refresh' => true,
            'widgets_path'                => Constants::OPTIPUB_THEME_WIDGET_PATH,
            'filename'                    => $this->get_widget['filename'],
        ];

        parent::__construct($id_base, $name, $widget_options);
    }

    /**
     * Front-end display of widget
     *
     * @param array $args Display arguments
     * @param array $instance Settings for the current widget instance
     */
    public function widget($args, $instance): void
    {
        $filename = $this->widgets_directory . '/' . $instance['filename'];

        if (!empty($args['before_widget'])) {
            echo wp_kses_post(html_entity_decode($args['before_widget']));
        }

        if (!empty($instance['name']) && !empty($args['before_title']) && !empty($args['after_title'])) {
            echo wp_kses_post(html_entity_decode($args['before_title'])) .
                 esc_html(apply_filters('widget_title', $instance['name'], $instance, $this->id_base)) .
                 wp_kses_post(html_entity_decode($args['after_title']));
        }

        if (file_exists($filename)) {
            include $filename;
        }

        if (!empty($args['after_widget'])) {
            echo wp_kses_post(html_entity_decode($args['after_widget']));
        }
    }

    /**
     * Back-end widget form
     *
     * @param array $instance Current settings
     */
    public function form($instance): void
    {
        global $wp_registered_sidebars;

        $i = count($wp_registered_sidebars) + 1;

        $title = !empty($this->get_widget['name']) ? $this->get_widget['name'] : 'Default OptiPub Widget Title';
        $sidebar = !empty($this->get_widget['id']) ? $this->get_widget['id'] : 'default-sidebar-' . $i;
        $filenameTitle = str_replace(' ', '_', strtolower($title));
        $filename = !empty($this->get_widget['filename']) ? $this->get_widget['filename'] : 'optipub_default_widget_' . $filenameTitle . '.php';

        $widget_fields = $this->get_widget_fields($title, $sidebar, $filename);

        $this->render_widget_form($widget_fields);
    }

    /**
     * Updating widget replacing old instances with new
     *
     * @param array $new_instance New settings for this instance
     * @param array $old_instance Old settings for this instance
     * @return array Updated safe settings to save
     */
    public function update($new_instance, $old_instance): array
    {
        return $new_instance;
    }

    /**
     * Get widget form fields with values
     */
    private function get_widget_fields(string $title, string $sidebar, string $filename): array
    {
        return [
            'title' => [
                'label'    => 'Title*',
                'value'    => $title,
                'readonly' => true,
            ],
            'sidebar' => [
                'label'    => 'Sidebar ID*',
                'value'    => $sidebar,
                'readonly' => true,
            ],
            'filename' => [
                'label'    => 'File Name*',
                'value'    => $filename,
                'readonly' => true,
            ],
            'description' => [
                'label'    => 'Description',
                'value'    => $this->get_widget['description'] ?? '',
                'readonly' => true,
            ],
            'custom_content' => [
                'label'    => 'Custom Content',
                'value'    => $this->get_widget['custom_content'] ?? '',
                'readonly' => true,
            ],
            'class' => [
                'label'    => 'Class Name',
                'value'    => $this->get_widget['class'] ?? '',
                'readonly' => true,
            ],
            'before_widget' => [
                'label'    => 'Before Widget',
                'value'    => $this->get_widget['before_widget'] ?? '',
                'readonly' => true,
                'type'     => 'textarea',
            ],
            'after_widget' => [
                'label'    => 'After Widget',
                'value'    => $this->get_widget['after_widget'] ?? '',
                'readonly' => true,
                'type'     => 'textarea',
            ],
            'before_title' => [
                'label'    => 'Before Title',
                'value'    => $this->get_widget['before_title'] ?? '',
                'readonly' => true,
                'type'     => 'textarea',
            ],
            'after_title' => [
                'label'    => 'After Title',
                'value'    => $this->get_widget['after_title'] ?? '',
                'readonly' => true,
                'type'     => 'textarea',
            ],
        ];
    }

    /**
     * Render widget form fields
     */
    private function render_widget_form(array $fields): void
    {
        $widget_file = $this->widgets_directory . '/' . ($fields['filename']['value'] ?? '');

        if (file_exists($widget_file)) {
            include $widget_file;
        }

        foreach ($fields as $id => $field) {
            $this->render_form_field($id, $field);
        }
    }

    /**
     * Render individual form field
     */
    private function render_form_field(string $id, array $field): void
    {
        $field_id = $this->get_field_id($id);
        $field_name = $this->get_field_name($id);
        $type = $field['type'] ?? 'text';

        printf(
            '<p><label for="%s">%s</label>',
            esc_attr($field_id),
            esc_html($field['label'])
        );

        if ($type === 'textarea') {
            printf(
                '<textarea class="widefat" id="%s" name="%s" %s>%s</textarea>',
                esc_attr($field_id),
                esc_attr($field_name),
                $field['readonly'] ? 'readonly' : '',
                esc_textarea($field['value'])
            );
        } else {
            printf(
                '<input class="widefat" id="%s" name="%s" type="text" value="%s" %s>',
                esc_attr($field_id),
                esc_attr($field_name),
                esc_attr($field['value']),
                $field['readonly'] ? 'readonly' : ''
            );
        }
        echo '</p>';
    }

    /**
     * Get widget configuration from options
     *
     * @param string $id Widget ID to retrieve
     * @return array|null Widget configuration or null if not found
     */
    private function get_optipub_widget(string $id): ?array
    {
        $optipub_widgets = get_option(Constants::OPTIPUB_WIDGET_OPTION);

        if (!is_array($optipub_widgets)) {
            return null;
        }

        foreach ($optipub_widgets as $widget) {
            if ($widget['id'] === $id) {
                return $widget;
            }
        }

        return null;
    }
}
