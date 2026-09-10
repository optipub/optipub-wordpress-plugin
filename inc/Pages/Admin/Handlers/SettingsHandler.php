<?php

namespace OptiPub\Pages\Admin\Handlers;

use OptiPub\Api\WpDatabaseQuery;
use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;
use OptiPub\Base\CustomPostTypeController;
use OptiPub\Base\Helper;
use OptiPub\Base\PageFactory;
use OptiPub\Base\RewriteUrlForCPT;
use OptiPub\Base\UserRoleManager;

class SettingsHandler extends BaseController
{
    private $wpDbQuery;

    private $userRoleManager;

    private $optiPubApi;

    public function __construct()
    {
        parent::__construct();
        $this->wpDbQuery = new WpDatabaseQuery();
        $this->userRoleManager = new UserRoleManager();
        $this->optiPubApi = parent::createOptiPubApiInstance();
        /** Reminder: Do not add CronHandler to constructor */
    }

    public function handleSaveChanges(array $data): void
    {
        // Clear existing summary log
        //update_option(Constants::OPTIPUB_SUMMARY_LOG, '');
        $this->processAdminFormData($data);

        // Build Dynamic Pages
        if (isset($data['optipub_publication_id'])) {
            $pageFactory = new PageFactory();
            $pageFactory->buildDynamicPages();
        }

        parent::flushRules();

        // Schedule Cron Job
        $cron_handler = new CronHandler();
        $cron_handler->scheduleCronJob();
    }

    public function handleResync(): void
    {
        if (!get_option(Constants::OPTIPUB_TOKEN_VERIFIED)) {
            throw new \Exception('API Token validation failed');

            return;
        }

        // Reset all publications' sync times
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        foreach ($terms as $term) {
            update_term_meta($term->term_id, 'publication_last_sync_time', strtotime('1970-01-01 00:00:00'));
        }

        update_option(Constants::OPTIPUB_SYNC_HAS_RUN, 0);

        // Create CronHandler only when needed
        $cron_handler = new CronHandler();
        $cron_handler->scheduleCronJob();
    }

    public function handleClearContent(array $data): void
    {
        // Check if pause_after_clear is set to '1'
        $pause_after_clear = isset($data['pause_after_clear']) && $data['pause_after_clear'] === '1';
        // Check if clear_publication_terms is set to '1'
        $clear_publication_terms = isset($data['clear_publication_terms']) && $data['clear_publication_terms'] === '1';

        $this->clearOptions();
        $this->wpDbQuery->resetAllContent($clear_publication_terms);
        $this->logProgress('Clearing Content - Clearing database tables');
        $this->logDoneStatus();

        $this->logProgress('Clearing Content - Clearing publication terms');
        $this->cleanupAllPublicationTermMeta();

        // Create CronHandler only when needed
        $cron_handler = new CronHandler();

        // Check if we should pause sync
        if ($pause_after_clear) {
            // Create CronHandler only when needed
            $cron_handler->clearCronJob();
        } else {
            // Process form data to restart sync
            $this->handleSaveChanges($data);

            // Schedule Cron Job
            $cron_handler->scheduleCronJob();
        }
    }

    /**
     * Get OptiPub Publication Term IDs with Term Meta
     *
     * @return array - Array Key is Term IDs / all publication data
     */
    public function getSyncedPublicationData(): array
    {
        $term_ids = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_id',
                    'compare' => 'EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);

        if (empty($term_ids)) {
            return [];
        }

        $terms_with_meta = [];

        foreach ($term_ids as $term_id) {
            $term = get_term($term_id);
            $term_meta = get_term_meta($term_id);
            $terms_with_meta[$term_id] = [
                'term' => $term,
                'meta' => $term_meta,
            ];
        }

        return $terms_with_meta;
    }

    /**
     * Get OptiPub Publication IDs with Term Meta
     *
     * @return array - Array Key is OptiPub Publication IDs / only term data
     */
    public function getSyncedTermMapByPublicationID(): array
    {
        $terms_with_meta = $this->getSyncedPublicationData();

        if (empty($terms_with_meta)) {
            return [];
        }

        $term_map = [];

        foreach ($terms_with_meta as $term_id => $term_data) {
            $pub_id = $term_data['meta']['publication_id'][0];
            $term_map[$pub_id] = $term_data['term'];
        }

        return $term_map;
    }

    /**
     * Sort Primary Publication First
     *
     * @return array - Array Key is Term IDs / all publication data
     */
    public function getSyncedPublicationDataSorted(): array
    {
        $terms = $this->getSyncedPublicationData();
        $primary_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        if (empty($terms) || !$primary_id) {
            $this->logStaticError('Primary Publication not set.');

            return $terms;
        }

        // Find the term that has the primary publication ID
        $primary_term_id = null;

        foreach ($terms as $term_id => $term_data) {
            if (isset($term_data['meta']['publication_id'][0])
                && $term_data['meta']['publication_id'][0] == $primary_id
            ) {
                $primary_term_id = $term_id;
                break;
            }
        }

        // If we found the primary term, move it to the front
        if ($primary_term_id) {
            $primary_term = [$primary_term_id => $terms[$primary_term_id]];
            unset($terms[$primary_term_id]);

            return $primary_term + $terms;
        }

        return $terms;
    }

    /**
     * Sort Primary Publication First
     *
     * @return array - Array Key is OptiPub Publication IDs / only term data
     */
    public function getSortedTermsByPublicationID(): array
    {
        $terms = $this->getSyncedTermMapByPublicationID();
        $primary_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        if (empty($terms) || !$primary_id) {
            $this->logStaticError('Primary Publication not set.');

            return $terms;
        }

        // Extract primary term if it exists
        $primary_term = [];

        if (isset($terms[$primary_id])) {
            $primary_term[$primary_id] = $terms[$primary_id];
            unset($terms[$primary_id]);
        }

        // Merge primary term back at the beginning
        return $primary_term + $terms;
    }

    public function loadPublicationImages(int $asset_id)
    {
        try {
            if (!empty($asset_id)) {
                // Create a fresh API instance instead of using the one from constructor
                $freshApi = parent::createOptiPubApiInstance();

                $assetResponse = $freshApi->getAssetsData($asset_id);

                // Check for API error
                if (isset($assetResponse->error) && $assetResponse->error === true) {
                    return '';
                }

                if (isset($assetResponse->data) && is_array($assetResponse->data) && count($assetResponse->data) > 0) {
                    $asset = $assetResponse->data[0];

                    if (isset($asset->path)) {
                        return $asset->path;
                    }
                }
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Clean up all existing publication taxonomy terms
     */
    private function cleanupAllPublicationTermMeta(): void
    {
        // Get all terms for the publication taxonomy
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'fields'     => 'ids',  // Just get the IDs
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                // Delete only sync-related meta data
                delete_term_meta($term_id, 'publication_last_sync_time');
                delete_term_meta($term_id, 'publication_sync_status');
                delete_term_meta($term_id, 'publication_page_slug_meta');
                $this->logProgress("Deleted sync meta for publication term ID: {$term_id}");
                $this->logDoneStatus();
            }
        }

        $this->logDoneStatus();
    }

    private function clearOptions(): void
    {
        $options_to_clear = [
            Constants::OPTIPUB_SUMMARY_LOG,
            Constants::OPTIPUB_LAST_RUN,
            Constants::OPTIPUB_SYNC_HAS_RUN,
            Constants::OPTIPUB_SITE_LOGO_SRC,
            Constants::OPTIPUB_SITE_FAVICON_SRC,
        ];

        foreach ($options_to_clear as $option) {
            delete_option($option);
        }
    }

    /**
     * Process Admin Form Data
     *
     * @param array $data The data from the admin form
     */
    private function processAdminFormData(array $data): void
    {
        // Save Current Active Theme Info
        $this->saveThemeState();

        // Configure Home Page
        update_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_HOME, $data['optipub_page_cagetory_id_home'] ?? '');
        $this->processPageTemplate($data['optipub_home_template'] ?? '', 'home');

        // Configure Subscribers Page
        update_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_SUBSCRIBERS, $data['optipub_page_cagetory_id_subscribers'] ?? '');
        $this->processPageTemplate($data['optipub_subscribers_template'] ?? '', 'subscribers');

        // Configure Login Page
        update_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_LOGIN, $data['optipub_page_cagetory_id_login'] ?? '');
        $this->processPageTemplate($data['optipub_login_template'] ?? '', 'login');

        // Configure OptiPub Tags Sync
        update_option(
            Constants::OPTIPUB_TAGS_AS_TAGS,
            isset($data[Constants::OPTIPUB_TAGS_AS_TAGS]) ? '1' : '0'
        );
        update_option(
            Constants::OPTIPUB_TAGS_AS_CATEGORIES,
            isset($data[Constants::OPTIPUB_TAGS_AS_CATEGORIES]) ? '1' : '0'
        );

        // Configure Open Graph
        $this->processOpenGraph(esc_url_raw($data['optipub_opengraph'] ?? ''));

        $primary_id = absint($data['optipub_publication_id'] ?? 0);

        // Only process publication if selected
        if (empty($primary_id)) {
            return;
        }

        // Process Primary Publication
        $old_primary_id = $this->processPrimaryPublication($primary_id);

        // Ensure additional publications is always an array
        $additional_publications = $data['optipub_additional_publications'] ?? [];

        if (!is_array($additional_publications)) {
            // Convert single value to array if needed
            $additional_publications = [$additional_publications];
        }
        $additional_ids = array_map('absint', array_filter($additional_publications));

        // If primary changed add the old to additional
        if ($old_primary_id) {
            $additional_ids[] = $old_primary_id;
        }

        // Remove primary publication from additional publications if present
        $additional_ids = array_diff($additional_ids, [$primary_id]);

        // Update additional publications
        update_option(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS, $additional_ids);

        // Create or update publication taxonomy terms
        $all_publication_ids = array_merge([$primary_id], $additional_ids);
        $this->syncPublicationTerms($primary_id, $all_publication_ids);

        // Ensure only one primary publication is set
        $this->ensureSinglePrimaryPublication($primary_id);

        // Process URL prefix for additional publications
        $prefix = trim(sanitize_title($data[Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX] ?? ''), '/');

        // Update permalink prefix if changed
        if ($prefix !== get_option(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX)) {
            update_option(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX, $prefix);

            // Force rewrite rules to be regenerated before flushing
            $rewrite = new RewriteUrlForCPT();
            $rewrite->modify_post_type_rewrite_rules();

            // Flush rewrite rules after all changes
            parent::flushRules();
        }
    }

    /**
     * Process template changes for special pages (home and subscribers)
     *
     * @param string $template The new template name
     * @param string $type The template type ('home' or 'subscribers')
     */
    private function processPageTemplate(string $template, string $type): void
    {
        $template_options = [
            'home' => [
                'option'      => Constants::OPTIPUB_HOME_TEMPLATE,
                'description' => 'Home Page',
            ],
            'subscribers' => [
                'option'      => Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE,
                'description' => 'Subscribers Page',
            ],
            'login' => [
                'option'      => Constants::OPTIPUB_LOGIN_TEMPLATE,
                'description' => 'Login Page',
            ],
        ];

        if (!isset($template_options[$type])) {
            return;
        }

        $option = $template_options[$type]['option'];
        $description = $template_options[$type]['description'];
        $current_template = get_option($option);

        if (empty($template)) {
            if ($current_template) {
                update_option($option, '');
                $this->logStaticError("{$current_template} - Removed as {$description} Template.");
            }

            return;
        }

        if ($template !== $current_template) {
            update_option($option, $template);
            $this->logStaticError("{$description} Template Changed to - {$template}");
        }
    }

    private function saveThemeState(): void
    {
        $current_theme_directory = rtrim(get_stylesheet_directory(), '/');

        // Only update if different from current saved directory
        if ($current_theme_directory !== get_option(Constants::OPTIPUB_ACTIVE_THEME_DIR)) {
            update_option(Constants::OPTIPUB_ACTIVE_THEME_NAME, wp_get_theme()->get('Name'));
            update_option(Constants::OPTIPUB_ACTIVE_THEME_VERSION, wp_get_theme()->get('Version'));
            update_option(Constants::OPTIPUB_ACTIVE_THEME_DIR, $current_theme_directory);
            $this->logStaticError('Active Theme Changed to - ' . wp_get_theme()->get('Name'));
        }
    }

    private function processOpenGraph(string $opengraphUrl): void
    {
        if (empty($opengraphUrl)) {
            delete_option(Constants::OPTIPUB_OPENGRAPH_IMAGE_LINK);

            return;
        }

        update_option(Constants::OPTIPUB_OPENGRAPH_IMAGE_LINK, $opengraphUrl);
    }

    private function processPrimaryPublication(int $new_publication_id): ?int
    {
        // Update publication ID if changed
        $current_publication_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        if ($new_publication_id !== $current_publication_id) {
            update_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID, $new_publication_id);

            return $current_publication_id ?: null;
        }

        return null;
    }

    private function updatePrimaryPublication(object $optipub_primary_publication): void
    {
        update_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID, $optipub_primary_publication->id);
        update_option(Constants::OPTIPUB_SELECTED_PUBLICATION_NAME, $optipub_primary_publication->name);
        update_option(Constants::OPTIPUB_SELECTED_PUBLICATION_DATA, $optipub_primary_publication);
    }

    private function getOptiPubPublications()
    {
        try {
            $api = parent::createOptiPubApiInstance();

            return $api->getPublications();
            // Return the data without casting to array
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * Create or update publication terms
     */
    private function syncPublicationTerms(int $primary_id, array $all_publication_ids): void
    {
        $api = parent::createOptiPubApiInstance();
        $publications = $api->getPublications();

        if (empty($publications)) {
            $this->logError('No publications found in API response');

            return;
        }

        // Get publication terms synced in wordpress
        $term_map = $this->getSyncedTermMapByPublicationID();

        if (empty($term_map)) {
            if ($primary_id) {
                update_option(Constants::OPTIPUB_LAST_RUN, '');
                update_option(Constants::OPTIPUB_SYNC_HAS_RUN, 0);
            } else {
                $this->logError('No existing publication terms found');
            }
        }

        foreach ($publications->data as $pub) {
            // Cast publication ID to string for consistent comparison
            $pub_id = (int) $pub->id;

            if (in_array($pub_id, $all_publication_ids)) {
                // Check if term exists with matching publication_id
                if (isset($term_map[$pub_id])) {
                    // Preserve existing terms built-in attributes
                    //$term = $term_map[$pub_id];
                    $term = ['term_id' => $term_map[$pub_id]->term_id];
                } else {
                    // Create new term with publication name
                    $term = wp_insert_term(
                        $pub->name,
                        Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                        [
                            'name' => $pub->name,
                            'slug' => trim(sanitize_title($pub->code), '/'),
                        ]
                    );
                }

                // Update primary publication options
                if ($pub_id === $primary_id) {
                    $this->updatePrimaryPublication($pub);
                }

                if (!is_wp_error($term)) {
                    $this->populatePublicationTerms($pub, $term, $primary_id);
                } else {
                    $this->logError('Failed to create term for publication ' . $pub->id);
                }

                // Write Custom Post Types optipub_metadata to WordPress options
                $helper = new Helper();
                $helper->writeCustomPostTypesMetadataInOptions($pub->id);

                if ($primary_id) {
                    // Create Custom Post Types
                    $cptController = new CustomPostTypeController();
                    $cptController->register();
                }

                // Run function to create user role for publication
                $this->userRoleManager->createPublicationUserRole($pub->name, $pub->id);
            }
        }

        // Clean up any terms that are no longer in use
        foreach ($term_map as $pub_id => $term) {
            if (!in_array($pub_id, $all_publication_ids)) {
                // Clean up content for this publication
                $cron_handler = new CronHandler();
                $cron_handler->cleanupContent((int) $pub_id, true);

                wp_delete_term($term->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
            }
        }
    }

    private function populatePublicationTerms(object $pub, array $term, int $primary_id): void
    {
        // Basic attributes
        update_term_meta($term['term_id'], 'publication_id', $pub->id);
        update_term_meta($term['term_id'], 'publication_primary', (int) ($pub->id === $primary_id));
        update_term_meta($term['term_id'], 'publication_paid', $pub->paid ? '1' : '0');
        update_term_meta($term['term_id'], 'publication_gated', $pub->paid ? '1' : '0');
        update_term_meta($term['term_id'], 'publication_name', trim($pub->name));

        // Additional scalar attributes
        update_term_meta($term['term_id'], 'publication_code', sanitize_text_field($pub->code));

        $domain_id = property_exists($pub->domain, 'id') ? absint($pub->domain->id) : '';
        update_term_meta($term['term_id'], 'publication_domain_id', $domain_id);

        // Get OptiPub Domain Website Tags
        $domain_website_tags = property_exists($pub->domain, 'website_tags') ? $pub->domain->website_tags : '';
        update_term_meta($term['term_id'], 'publication_domain_website_tags', $domain_website_tags ? $domain_website_tags : '');

        if (!property_exists($pub->domain, 'id')) {
            $this->logStaticError('No domain ID for publication - ' . $pub->name . ' - Update Publication in OptiPub' . $pub->id);
        }

        // Get Publication Logo Asset ID
        $logo_asset_id = property_exists($pub, 'logo_asset_id') ? $pub->logo_asset_id : null;
        update_term_meta($term['term_id'], 'publication_logo_asset_id', $logo_asset_id ? absint($logo_asset_id) : '');

        // Get Publication Logo Asset URL
        $logo_asset_url = '';

        if (!empty($logo_asset_id)) {
            $logo_asset_url = $this->loadPublicationImages($logo_asset_id) ?: '';
        }
        update_term_meta($term['term_id'], 'publication_logo_asset_url', $logo_asset_url);

        // Get Publication Favicon Asset ID
        $favicon_asset_id = property_exists($pub, 'fav_icon_asset_id') ? $pub->fav_icon_asset_id : null;
        update_term_meta($term['term_id'], 'publication_fav_icon_asset_id', $favicon_asset_id ? absint($favicon_asset_id) : '');

        // Get Publication Favicon Asset URL
        $favicon_asset_url = '';

        if (!empty($favicon_asset_id)) {
            $favicon_asset_url = $this->loadPublicationImages($favicon_asset_id) ?: '';
        }
        update_term_meta($term['term_id'], 'publication_fav_icon_asset_url', $favicon_asset_url);

        // Set initial sync time if it doesn't exist
        if (!get_term_meta($term['term_id'], 'publication_last_sync_time', true)) {
            update_term_meta($term['term_id'], 'publication_last_sync_time', strtotime('1970-01-01 00:00:00'));
        }

        // Initialize sync status
        if (!get_term_meta($term['term_id'], 'publication_sync_status', true)) {
            update_term_meta($term['term_id'], 'publication_sync_status', '0');
        }

        // Store publication term ID
        update_term_meta($term['term_id'], 'publication_term_id', $term['term_id']);

        // Store created by
        update_term_meta($term['term_id'], 'created_by', 'optipub-plugin');

        if ($pub->id === $primary_id) {
            // Update options for primary publication
            update_option(Constants::OPTIPUB_SITE_LOGO_SRC, $logo_asset_url);
            update_option(Constants::OPTIPUB_SITE_FAVICON_SRC, $favicon_asset_url);
            update_option(Constants::OPTIPUB_SELECTED_DOMAIN_WEBSITE_TAGS, $domain_website_tags);

            do_action('optipub_primary_publication_settings_saved');
        }

        /**
         * I Think we can significantly simplify page_factory->buildPublicationRootPage
         * with this logic. just need to also add 'additional_publication_prefix'
         * and use that the post_parent / make sure post_parent is dynamic
         *
         * Mostly did this ^
         */
        if (!isset($term->meta['publication_page_slug_meta'][0]) && $pub->id !== $primary_id) {
            $page_factory = new PageFactory();
            $slug_meta = 'publication_slug_' . $pub->id;
            $page_slug_id = $page_factory->buildPublicationSlugPage($pub->id);
            //$page_slug_id = $page_factory->createDynamicPage($slug_meta, get_term($term['term_id'])->slug, '', $pub->id, $term['term_id']);
            update_term_meta($term['term_id'], 'publication_page_slug_meta', $slug_meta);
        }

        // Store JSON encoded objects/arrays
        $json_fields = [
            'info'                => 'publication_info',
            'mappings'            => 'publication_mappings',
            'publication_content' => 'publication_content',
        ];

        foreach ($json_fields as $source => $meta_key) {
            if (isset($pub->$source)) {
                $clean_data = [];

                foreach ($pub->$source as $key => $value) {
                    $clean_data[$key] = $value;
                }

                $json = wp_json_encode($clean_data);

                if (json_last_error() !== JSON_ERROR_NONE) {
                }
                update_term_meta($term['term_id'], $meta_key, addslashes($json));
            } else {
                $this->logError("Failed to encode {$meta_key} for publication {$pub->id}");
            }
        }
    }

    private function ensureSinglePrimaryPublication(int $primary_id): void
    {
        // Get the term that has this publication_id
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_id',
                    'value'   => $primary_id,
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            $this->logError("Could not find term for primary publication ID: {$primary_id}");

            return;
        }

        $primary_term_id = $terms[0]->term_id;

        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        foreach ($terms as $term) {
            if ($term->term_id != $primary_term_id) {
                update_term_meta($term->term_id, 'publication_primary', '0');
            }
        }
    }
}
