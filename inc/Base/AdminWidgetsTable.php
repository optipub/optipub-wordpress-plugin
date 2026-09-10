<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

class AdminWidgetsTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Widget', 'optipub'),
            'plural'   => __('Widgets', 'optipub'),
            'ajax'     => true,
        ]);

        $this->prepare_items();
        $this->display();
    }

    public function get_columns(): array
    {
        return [
            'cb'             => '<input type="checkbox" />', // to enable checkboxes
            'actions'        => '<span class="optipub-actions-header" style="width: auto;">Actions</span>',
            'id'             => 'ID',
            'description'    => '',
            'name'           => __('Title', 'optipub'),
            'custom_content' => 'Custom Content',
            'filename'       => 'File Name',
        ];
    }

    public function prepare_items(): void
    {
        $this->process_bulk_action();

        $columns = $this->get_columns();
        $hidden = []; // columns to hide, if any
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Fetch widgets data
        $this->items = $this->get_sorted_widgets_data();
    }

    public function get_sorted_widgets_data(): array
    {
        $widgets_data = get_option('optipub_cc_widgets', []);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: Sorting parameters are handled by WP_List_Table
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'id';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: Sorting parameters are handled by WP_List_Table
        $order = !empty($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'asc';

        usort($widgets_data, function ($a, $b) use ($orderby, $order) {
            return ('asc' === $order)
                ? strcmp($a[$orderby], $b[$orderby])
                : strcmp($b[$orderby], $a[$orderby]);
        });

        return $widgets_data;
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'description':
                if (!empty($item['description'])) {
                    return sprintf(
                        '<span class="dashicons dashicons-info" data-tooltip="%s"></span>',
                        esc_attr($item['description'])
                    );
                }

                return '';
            case 'id':
                return $item[$column_name];
            case 'actions':
                return $this->get_row_actions($item);
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : 'Not set';
        }
    }

    public function process_bulk_action(): void
    {
        if (!isset($_POST['_wpnonce']) || empty($_POST['_wpnonce'])) {
            return;
        }

        $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
        $action = 'bulk-' . $this->_args['plural'];

        if (!wp_verify_nonce($nonce, $action)) {
            wp_die('Nope! Security check failed!');
        }

        if ('delete' !== $this->current_action()) {
            return;
        }

        $items_to_delete = isset($_POST['op-widget']) ? array_map('sanitize_text_field', wp_unslash($_POST['op-widget'])) : [];

        if (!is_array($items_to_delete)) {
            return;
        }

        $this->delete_widgets($items_to_delete);
    }

    protected function get_sortable_columns(): array
    {
        return [
            'id'             => ['id', false],
            'name'           => ['name', false],
            'custom_content' => ['custom_content', false],
            'filename'       => ['filename', false],
        ];
    }

    protected function column_cb($item): string
    {
        return sprintf(
            '<input id="cb-select-%s" type="checkbox" name="op-widget[]" value="%s" />',
            esc_attr($item['id']),
            esc_attr($item['id'])
        );
    }

    protected function get_bulk_actions(): array
    {
        return [
            'delete' => 'Delete',
        ];
    }

    private function get_row_actions(array $item): string
    {
        return $this->get_view_button($item) . $this->get_edit_button($item);
    }

    private function get_view_button(array $item): string
    {
        return sprintf(
            '<button type="button" data-itemid="%s" class="button button-secondary widget-view">View</button>',
            esc_attr($item['id'])
        );
    }

    private function get_edit_button(array $item): string
    {
        return sprintf(
            '<button type="button" data-itemid="%s" class="button button-primary widget-edit">Edit</button>',
            esc_attr($item['id'])
        );
    }

    /**
     * Deleting Custom Content Widget Sidebars
     *
     * 1a. Remove sidebar from sidebars_widgets
     * 1b. Unregister sidebar
     * 2a. Remove widget from optipub_cc_widgets option
     * 2b. Delete Widget File - (Disabled)
     * 3.  Delete widget option
     * 4.  Return response
     */
    private function delete_widgets(array $items_to_delete): void
    {
        $optipub_cc_widgets = get_option(Constants::OPTIPUB_WIDGET_OPTION, []);
        $sidebars_widgets = get_option('sidebars_widgets');

        foreach ($items_to_delete as $item_id) {
            /* widget_optipub_cc_${item_id} */
            $widget_base_item_id = Constants::OPTIPUB_WIDGET_PREFIX . $item_id;

            // Remove sidebar and unregister it
            unset($sidebars_widgets[$item_id]);
            add_action('widgets_init', function () use ($item_id) {
                unregister_sidebar($item_id);
            });

            // Remove widget from options
            foreach ($optipub_cc_widgets as $key => $value) {
                if ($value['id'] == $item_id) {
                    unset($optipub_cc_widgets[$key]);
                    break;
                }
            }

            // Delete widget option
            delete_option($widget_base_item_id);
        }

        update_option('optipub_cc_widgets', $optipub_cc_widgets);
        update_option('sidebars_widgets', $sidebars_widgets);
    }
}
