<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles registration and management of custom taxonomies
 */
class CustomTaxonomyController extends BaseController
{
    /**
     * Store all custom taxonomies
     * @var array
     */
    private $taxonomies = [];

    public function register(): void
    {
        $this->initTaxonomies();

        if (empty($this->taxonomies)) {
            return;
        }

        add_action('init', [$this, 'registerTaxonomies']);
        $this->addCustomFieldsHooks();

        // Add custom columns management
        add_filter('manage_edit-' . Constants::OPTIPUB_PUBLICATION_TAXONOMY . '_columns', [$this, 'customizeColumns']);
        add_filter('manage_' . Constants::OPTIPUB_PUBLICATION_TAXONOMY . '_custom_column', [$this, 'customColumnContent'], 10, 3);

        // Add hooks for flushing rewrite rules
        add_action('created_' . Constants::OPTIPUB_PUBLICATION_TAXONOMY, [$this, 'parent::flushRules']);
        add_action('edited_' . Constants::OPTIPUB_PUBLICATION_TAXONOMY, [$this, 'parent::flushRules']);
        add_action('delete_' . Constants::OPTIPUB_PUBLICATION_TAXONOMY, [$this, 'parent::flushRules']);

        // Add capability check filter
        add_filter('map_meta_cap', [$this, 'check_publication_capability'], 10, 4);
    }

    /**
     * Register all defined taxonomies
     */
    public function registerTaxonomies(): void
    {
        foreach ($this->taxonomies as $taxonomy => $config) {
            register_taxonomy($taxonomy, $config['post_types'], $config['args']);
        }
    }

    /**
     * Add custom fields to the add taxonomy form
     */
    public function addCustomFields($taxonomy): void
    {
        $meta_fields = $this->taxonomies[$taxonomy]['meta_fields'] ?? [];

        foreach ($meta_fields as $key => $field) {
            ?>
            <div class="form-field">
                <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
                <?php $this->renderField($key, $field); ?>
                <p><?php echo esc_html($field['description']); ?></p>
            </div>
        <?php
        }
        ?>
        <!-- Add nonce field for security -->
        <?php wp_nonce_field('add-tag'); ?>
        <?php
    }

    /**
     * Add custom fields to the edit taxonomy form
     */
    public function editCustomFields($term, $taxonomy): void
    {
        $meta_fields = $this->taxonomies[$taxonomy]['meta_fields'] ?? [];

        ?>
        <!-- Publication Page Settings -->
        </tbody>
        </table>
        <table id="publication-page-settings-table" class="form-table" role="presentation">
            <tbody>
                <tr class="form-field">
                    <td style="padding-left: 0px; padding-right: 0px;">
                        <?php $this->add_publication_page_settings_fields($term); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <!-- Add nonce field for security -->
        <?php wp_nonce_field('update-tag_' . $term->term_id); ?>
        <div id="publication-settings" class="accordion-container">
            <button type="button" id="publication-settings-accordion" class="accordion">
                <?php esc_html_e('Publication Settings', 'optipub'); ?>
                <span class="accordion-arrow dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <div id="publication-settings-panel" class="panel">
                <table id="publication-settings-table" class="form-table" role="presentation">
                    <tbody>
                        <?php

                        foreach ($meta_fields as $key => $field) {
                            $value = get_term_meta($term->term_id, $key, true);
                            ?>
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
                                    <?php if ($key == 'publication_logo_asset_url' || $key == 'publication_fav_icon_asset_url') { ?>
                                        <br>
                                        <a href="<?php echo esc_url($value); ?>" target="_blank">
                                            <img src="<?php echo esc_url($value); ?>" alt="Logo" class="logo-asset-url">
                                        </a>
                                    <?php } ?>
                                </th>
                                <td>
                                    <?php $this->renderField($key, $field, $value); ?>
                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                </td>
                            </tr>
                        <?php
                        }
        ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php
    }

    /**
     * Save custom fields
     */
    public function saveCustomFields($term_id): void
    {
        // Verify nonce for security - handle both add and edit scenarios
        $nonce_action = 'update-tag_' . $term_id;

        $post_action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

        if ($post_action === 'add-tag') {
            $nonce_action = 'add-tag';
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $nonce_action)) {
            return;
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';

        if (!$taxonomy || !isset($this->taxonomies[$taxonomy])) {
            return;
        }

        $meta_fields = $this->taxonomies[$taxonomy]['meta_fields'];

        foreach ($meta_fields as $key => $field) {
            if ($field['type'] === 'checkbox') {
                if (isset($_POST[$key])) {
                    // For checkboxes, save the actual value (could be '0' or '1')
                    update_term_meta(
                        $term_id,
                        $key,
                        sanitize_text_field(wp_unslash($_POST[$key])) === '1' ? '1' : '0'
                    );
                } else {
                    // If not in POST data at all, it's unchecked
                    update_term_meta($term_id, $key, '0');
                }
            } else {
                if (isset($_POST[$key])) {
                    update_term_meta(
                        $term_id,
                        $key,
                        sanitize_text_field(wp_unslash($_POST[$key]))
                    );
                }
            }
        }

        // Special handling for primary publication
        $publication_primary = isset($_POST['publication_primary']) ? sanitize_text_field(wp_unslash($_POST['publication_primary'])) : '';

        if (
            $taxonomy === Constants::OPTIPUB_PUBLICATION_TAXONOMY
            && $publication_primary
        ) {
            $this->ensureSinglePrimaryPublication($term_id);
        }

        // Save publication page settings
        if ($taxonomy === Constants::OPTIPUB_PUBLICATION_TAXONOMY) {
            $this->save_publication_page_settings($term_id);

            // Rebuild dynamic pages
            $this->rebuild_dynamic_pages($term_id);
        }
    }

    /**
     * Customize the columns shown in the publications table
     */
    public function customizeColumns($columns): array
    {
        if (!isset($columns['cb'])) {
            $columns['cb'] = '';
        }

        return [
            'cb'                         => $columns['cb'],
            'name'                       => __('Name', 'optipub'),
            'slug'                       => __('Slug', 'optipub'),
            'publication_id'             => __('OP ID', 'optipub'),
            'publication_code'           => __('Code', 'optipub'),
            'publication_domain_id'      => __('Domain ID', 'optipub'),
            'publication_paid'           => __('Paid', 'optipub'),
            'publication_gated'          => __('Gated', 'optipub'),
            'publication_primary'        => __('Primary', 'optipub'),
            'publication_last_sync_time' => __('Last Sync', 'optipub'),
            'publication_sync_status'    => __('Sync Status', 'optipub'),
        ];
    }

    /**
     * Provide content for custom columns
     */
    public function customColumnContent($content, $column_name, $term_id): string
    {
        switch ($column_name) {
            case 'publication_id':
            case 'publication_code':
            case 'publication_domain_id':
            case 'publication_sync_status':
                $value = get_term_meta($term_id, $column_name, true);

                return $value ?: '-';
            case 'slug':
                $term = get_term($term_id);

                return $term ? $term->slug : '-';
            case 'publication_paid':
            case 'publication_primary':
            case 'publication_gated':
                $value = get_term_meta($term_id, $column_name, true);

                return $value ? '✓' : '-';
            case 'publication_last_sync_time':
                $value = get_term_meta($term_id, $column_name, true);

                return $value ? gmdate('Y-m-d H:i:s', $value) : '-';
            default:
                return $content;
        }
    }

    /** REMOVE THIS FUNCTION */
    // Save the rewrite settings on term update
    public function save_publication_rewrite_settings($term_id)
    {
        if (isset(get_term($term_id)->slug)) {
            $term = get_term($term_id);
            $slug = $term->slug;
            $rewrite = get_term_meta($term_id, 'rewrite', true);

            $rewrite['slug'] = $slug;
            //update_term_meta($term_id, 'rewrite', $rewrite);
        }
    }

    // Add this to your term edit form
    public function add_publication_page_settings_fields($term)
    {
        // Get term meta
        $page_settings = get_term_meta($term->term_id, 'publication_page_settings', true);
        $page_settings = $page_settings ? json_decode($page_settings, true) : [];

        // Get global settings as default
        $global_settings = get_option(Constants::OPTIPUB_METADATA);

        // Get available templates - store as filename => template name
        $templates = [];
        $wp_templates = get_page_templates();

        if (!empty($wp_templates)) {
            // Store both ways for easy lookup
            $filename_to_name = [];

            foreach ($wp_templates as $template_name => $template_filename) {
                $templates[$template_name] = $template_name;
                $filename_to_name[$template_filename] = $template_name;
            }
        }
        ?>
        <div id="publication-page-settings" class="accordion-container">
            <button type="button" id="publication-page-settings-accordion" class="accordion">
                <?php esc_html_e('Publication Page Settings', 'optipub'); ?>
                <span class="accordion-arrow dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <div id="publication-page-settings-panel" class="panel">
                <table class="form-table">
                    <?php foreach ($global_settings as $key => $default_settings) {
                        // Merge with default settings to ensure we have all required fields
                        $settings = isset($page_settings[$key]) ? $page_settings[$key] : $default_settings;
                        ?>
                        <tr class="top-row">
                            <th><label><?php echo esc_html($default_settings['cptPluralName']); ?> Page</label></th>
                            <td class="">
                                <label>Enable:&nbsp;&nbsp;&nbsp;&nbsp;
                                    <input type="hidden" name="pub_pages[<?php echo esc_attr($key); ?>][createCpt]" value="0">
                                    <input class="enable-checkbox" type="checkbox" name="pub_pages[<?php echo esc_attr($key); ?>][createCpt]" value="1"
                                        <?php checked(isset($settings['createCpt']) ? $settings['createCpt'] : true); ?>>
                                </label>
                            </td>
                            <td class="publication-page-settings-row">
                                <label>Slug:
                                    <input class="page-slug-input" type="text" name="pub_pages[<?php echo esc_attr($key); ?>][pageSlug]"
                                        value="<?php echo esc_attr($settings['pageSlug']); ?>">
                                </label>

                                <label>Title:
                                    <input class="page-title-input" type="text" name="pub_pages[<?php echo esc_attr($key); ?>][pageTitle]"
                                        value="<?php echo esc_attr($settings['pageTitle']); ?>">
                                </label>

                                <label>Template:
                                    <select class="template-select" name="pub_pages[<?php echo esc_attr($key); ?>][templateName]">
                                        <?php
                                            // Add default template from global settings if available
                                            if (isset($default_settings['templateName']) && $default_settings['templateName']) {
                                                // Use filename for default template
                                                $default_template_value = $default_settings['templateName'];

                                                echo '<option value="' . esc_attr($default_template_value) . '" ' .
                                                    selected($settings['templateName'] === $default_template_value, true, false) .
                                                    '>' . esc_html($default_template_value) . ' (Default)</option>';
                                            }

                        // Add all available templates with their friendly names
                        foreach ($templates as $template_value => $template_label) {
                            // Skip if this is the same as the default to avoid duplication
                            if (isset($default_settings['templateName']) && $template_value === $default_settings['templateName']) {
                                continue;
                            }
                            $selected = ($template_value === $settings['templateName']);
                            ?>
                                            <option value="<?php echo esc_attr($template_value); ?>" <?php selected($selected); ?>>
                                                <?php echo esc_html($template_label); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td class=""></td>
                            <td class="publication-page-settings-row">
                                <label>Description:
                                    <input class="page-description-input" type="text" name="pub_pages[<?php echo esc_attr($key); ?>][pageDescription]"
                                        value="<?php echo esc_attr($settings['pageDescription']); ?>">
                                </label>
                            </td>
                        </tr>
                        <tr class="bottom-row">
                            <th></th>
                            <td class=""></td>
                            <td class="publication-page-settings-row">
                                <label>Use Post Type in URL:
                                    <input type="hidden" name="pub_pages[<?php echo esc_attr($key); ?>][cptUsePostTypeInUrl]" value="0">
                                    <input class="enable-checkbox" type="checkbox" name="pub_pages[<?php echo esc_attr($key); ?>][cptUsePostTypeInUrl]" value="1"
                                        <?php checked(isset($settings['cptUsePostTypeInUrl']) ? $settings['cptUsePostTypeInUrl'] : false); ?>>
                                </label>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
<?php
    }

    // Save the settings on term update
    public function save_publication_page_settings($term_id)
    {
        // Verify nonce for security - handle both add and edit scenarios
        $nonce_action = 'update-tag_' . $term_id;

        $post_action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

        if ($post_action === 'add-tag') {
            $nonce_action = 'add-tag';
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $nonce_action)) {
            return;
        }

        $pub_pages = isset($_POST['pub_pages']) ? map_deep(wp_unslash($_POST['pub_pages']), 'sanitize_text_field') : [];

        if ($pub_pages) {
            $global_settings = get_option(Constants::OPTIPUB_METADATA);
            $page_settings = [];

            foreach ($pub_pages as $key => $settings) {
                if (isset($global_settings[$key])) {
                    $page_settings[$key] = array_merge($global_settings[$key], $settings);
                }
            }

            update_term_meta($term_id, 'publication_page_settings', wp_json_encode($page_settings));

            // Reset last sync time to trigger a full sync
            update_term_meta($term_id, 'publication_last_sync_time', 0);

            // Flush rewrite rules
            $this->flushRules();
        }
    }

    /**
     * Check publication capability dynamically
     */
    public function check_publication_capability($caps, $cap, $user_id, $args): array
    {
        if ($cap === 'read' && !empty($args[0])) {
            $term_id = $args[0];
            $pub_id = get_term_meta($term_id, 'publication_id', true);

            if ($pub_id) {
                return ['read_optipub_publication_' . $pub_id];
            }
        }

        return $caps;
    }

    /**
     * Initialize taxonomies configuration
     */
    private function initTaxonomies(): void
    {
        // Get OptiPub post types plus 'post' and 'page'
        $publication_post_types = \OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes(
            ['post'], // no exceptions
            false,    // don't require posts
            true,     // include built-in
            true      // only OptiPub
        );

        $this->taxonomies = [
            Constants::OPTIPUB_PUBLICATION_TAXONOMY => [
                'post_types' => $publication_post_types,
                'args'       => [
                    'labels' => [
                        'name'          => _x('Publications', 'taxonomy general name', 'optipub'),
                        'singular_name' => _x('Publication', 'taxonomy singular name', 'optipub'),
                        'search_items'  => __('Search Publications', 'optipub'),
                        'all_items'     => __('All Publications', 'optipub'),
                        'edit_item'     => __('Edit Publication', 'optipub'),
                        'update_item'   => __('Update Publication', 'optipub'),
                        'menu_name'     => __('Publications', 'optipub'),
                    ],
                    'hierarchical'       => false,
                    'public'             => true,
                    'publicly_queryable' => false,
                    'rewrite'            => false,
                    'show_ui'            => true,
                    'show_in_menu'       => true,
                    'show_in_nav_menus'  => false,
                    'show_in_rest'       => true,
                    'query_var'          => 'optipub_publication',
                    'show_tagcloud'      => false,
                    'show_in_quick_edit' => true,
                    'show_admin_column'  => true,
                    'capabilities'       => [
                        'manage_terms' => 'manage_options',
                        'edit_terms'   => 'manage_options',
                        'delete_terms' => 'do_not_allow',
                        'assign_terms' => 'manage_options',
                    ],
                    'update_count_callback' => '_update_post_term_count',
                ],
                'meta_fields' => [
                    'publication_gated' => [
                        'type'        => 'checkbox',
                        'label'       => 'Members Only',
                        'description' => 'Does content require a subscription?',
                    ],
                    'publication_primary' => [
                        'type'        => 'checkbox',
                        'label'       => 'Primary Publication',
                        'description' => 'Is this the primary publication?',
                        'readonly'    => true,
                    ],
                    'publication_paid' => [
                        'type'        => 'checkbox',
                        'label'       => 'Paid Publication',
                        'description' => 'Is this a paid publication?',
                        'readonly'    => true,
                    ],
                    'publication_term_id' => [
                        'type'        => 'number',
                        'label'       => 'Publication Term ID',
                        'description' => 'OptiPub Publication Term ID',
                        'readonly'    => true,
                    ],
                    'publication_id' => [
                        'type'        => 'number',
                        'label'       => 'Publication ID',
                        'description' => 'OptiPub Publication ID',
                        'readonly'    => true,
                    ],
                    'publication_name' => [
                        'type'        => 'text',
                        'label'       => 'Pulication Name',
                        'description' => 'Publication Name',
                        'readonly'    => true,
                    ],
                    'publication_code' => [
                        'type'        => 'text',
                        'label'       => 'Code',
                        'description' => 'Publication Code',
                        'readonly'    => true,
                    ],
                    'publication_domain_id' => [
                        'type'        => 'number',
                        'label'       => 'Domain ID',
                        'description' => 'OptiPub Domain ID',
                        'readonly'    => true,
                    ],
                    'publication_logo_asset_id' => [
                        'type'        => 'number',
                        'label'       => 'Logo Asset ID',
                        'description' => 'OptiPub Logo Asset ID',
                        'readonly'    => true,
                    ],
                    'publication_logo_asset_url' => [
                        'type'        => 'text',
                        'label'       => 'Logo Asset URL',
                        'description' => 'OptiPub Logo Asset URL',
                        'readonly'    => true,
                    ],
                    'publication_fav_icon_asset_id' => [
                        'type'        => 'number',
                        'label'       => 'Favicon Asset ID',
                        'description' => 'OptiPub Favicon Asset ID',
                        'readonly'    => true,
                    ],
                    'publication_fav_icon_asset_url' => [
                        'type'        => 'text',
                        'label'       => 'Favicon Asset URL',
                        'description' => 'OptiPub Favicon Asset URL',
                        'readonly'    => true,
                    ],
                    'publication_domain_website_tags' => [
                        'type'        => 'textarea',
                        'label'       => 'Domain Website Tags',
                        'description' => 'OptiPub Domain Website Tags',
                        'readonly'    => true,
                    ],
                    'publication_info' => [
                        'type'        => 'textarea',
                        'label'       => 'Publication Info',
                        'description' => 'Publication Info JSON',
                        'readonly'    => true,
                    ],
                    'publication_mappings' => [
                        'type'        => 'textarea',
                        'label'       => 'Publication Mappings',
                        'description' => 'Publication Mappings JSON',
                        'readonly'    => true,
                    ],
                    'publication_content' => [
                        'type'        => 'textarea',
                        'label'       => 'Publication Content',
                        'description' => 'Publication Content JSON',
                        'readonly'    => true,
                    ],
                    'publication_page_settings' => [
                        'type'        => 'textarea',
                        'label'       => 'Publication Page Settings',
                        'description' => 'Publication Page Settings JSON',
                        'readonly'    => true,
                    ],
                    'publication_page_slug_meta' => [
                        'type'        => 'text',
                        'label'       => 'Publication Page Slug Meta',
                        'description' => 'Publication Page Slug Meta',
                        'readonly'    => true,
                    ],
                ],
            ],
            // Add more taxonomies here as needed
            // 'another_taxonomy' => [
            //     'post_types' => ['post'],
            //     'args' => [...],
            //     'meta_fields' => [...]
            // ],
        ];
    }

    /**
     * Add hooks for custom fields
     */
    private function addCustomFieldsHooks(): void
    {
        foreach ($this->taxonomies as $taxonomy => $config) {
            if (!empty($config['meta_fields'])) {
                add_action("{$taxonomy}_add_form_fields", [$this, 'addCustomFields']);
                add_action("{$taxonomy}_edit_form_fields", [$this, 'editCustomFields'], 10, 2);
                add_action("created_{$taxonomy}", [$this, 'saveCustomFields']);
                add_action("edited_{$taxonomy}", [$this, 'saveCustomFields']);
            }
        }
    }

    /**
     * Render individual field based on type
     */
    private function renderField(string $key, array $field, $value = ''): void
    {
        switch ($field['type']) {
            case 'checkbox':
                if (isset($field['readonly']) && $field['readonly']) {
                    // For readonly fields, don't add the general hidden field
                    // Just render a disabled checkbox for display and a hidden field with the actual value
                    printf(
                        '<input type="checkbox" id="%s" %s disabled />
                         <input type="hidden" name="%s" value="%s" />',
                        esc_attr($key),
                        checked($value, '1', false),
                        esc_attr($key),
                        esc_attr($value) // This preserves the original value
                    );
                } else {
                    // For editable checkboxes, add the 0 hidden field and the checkbox
                    printf(
                        '<input type="hidden" name="%s" value="0" />
                         <input type="checkbox" name="%s" id="%s" value="1" %s />',
                        esc_attr($key),
                        esc_attr($key),
                        esc_attr($key),
                        checked($value, '1', false)
                    );
                }
                break;
            case 'number':
                printf(
                    '<input type="number" name="%s" id="%s" value="%s" %s />',
                    esc_attr($key),
                    esc_attr($key),
                    esc_attr($value),
                    isset($field['readonly']) && $field['readonly'] ? 'readonly' : ''
                );
                break;
            case 'textarea':
                $rows = 3;

                // If it's JSON, decode and re-encode with pretty print
                if ($value && in_array($key, ['publication_info', 'publication_mappings', 'publication_content', 'publication_page_settings'])) {
                    $decoded = json_decode($value, true);
                    $value = wp_json_encode($decoded, JSON_PRETTY_PRINT);
                    $rows = 15;
                }

                printf(
                    '<textarea name="%s" id="%s" rows="%d" style="width: 95%%; font-family: monospace;" %s>%s</textarea>',
                    esc_attr($key),
                    esc_attr($key),
                    esc_attr($rows),
                    isset($field['readonly']) && $field['readonly'] ? 'readonly' : '',
                    esc_textarea($value)
                );
                break;
            default:
                printf(
                    '<input type="text" name="%s" id="%s" value="%s" %s />',
                    esc_attr($key),
                    esc_attr($key),
                    esc_attr($value),
                    isset($field['readonly']) && $field['readonly'] ? 'readonly' : ''
                );
                break;
        }
    }

    /**
     * Ensure only one publication is marked as primary
     */
    private function ensureSinglePrimaryPublication($current_term_id): void
    {
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        foreach ($terms as $term) {
            if ($term->term_id != $current_term_id) {
                update_term_meta($term->term_id, 'publication_primary', '0');
            }
        }
    }

    /**
     * Rebuild dynamic pages after settings update
     */
    private function rebuild_dynamic_pages($term_id): void
    {
        // Create PageFactory instance
        $page_factory = new PageFactory();

        if (get_term_meta($term_id, 'publication_primary', true) == '0') {
            //$slug_name = get_term($term_id)->slug;
            $pub_id = get_term_meta($term_id, 'publication_id', true);
            //$slug_meta = get_term_meta($term_id, 'publication_slug_meta', true);

            //$page_factory->createDynamicPage($slug_meta, $slug_name, '', $pub_id, $term_id);
            $page_factory->buildPublicationSlugPage($pub_id);
        }

        // Rebuild all dynamic pages
        $page_factory->buildDynamicPages();
    }
}
