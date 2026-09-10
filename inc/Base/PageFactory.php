<?php

namespace OptiPub\Base;

use OptiPub\Api\WpDatabaseQuery;
use OptiPub\Pages\Admin\Handlers\SettingsHandler;

class PageFactory extends BaseController
{
    public $optiPubApi;

    private $wpDbQuery;

    private $settings_handler;

    public function __construct()
    {
        parent::__construct();
        $this->optiPubApi = parent::createOptiPubApiInstance();
        $this->wpDbQuery = new WpDatabaseQuery();
        $this->settings_handler = new SettingsHandler();
    }

    /**
     * Get the publication slug page ID for static pages
     *
     * @param int $publication_id The publication ID
     * @return int The ID of the publication slug page or 0 if not found
     */
    public function getPublicationSlugPageId(int $publication_id, bool $is_global_prefix = false): int
    {
        $meta_value = $is_global_prefix ? 'additional_pub_prefix' : 'publication_slug_' . $publication_id;

        $existing_page = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'meta_key'       => '_optipub_plugin_page',
            'meta_value'     => $meta_value,
            'posts_per_page' => 1,
        ]);

        return !empty($existing_page) ? $existing_page[0]->ID : 0;
    }

    /**
     * Builds dynamic pages based on publication-specific settings
     */
    public function buildDynamicPages(): void
    {
        // Get the global metadata as fallback
        $global_metadata = get_option(Constants::OPTIPUB_METADATA);

        if (!$global_metadata) {
            $this->logError("\nFailed creating resource pages. optipub_metadata is missing from the wp_options table.\n");

            return;
        }

        $pub_terms = $this->settings_handler->getSortedTermsByPublicationID();

        foreach ($pub_terms as $publication_id => $pub_term) {
            // Get publication-specific page settings
            $pub_page_settings = get_term_meta($pub_term->term_id, 'publication_page_settings', true);
            $pub_page_settings = $pub_page_settings ? json_decode($pub_page_settings, true) : $global_metadata;

            // Create pages using publication-specific settings
            foreach ($pub_page_settings as $key => $val) {
                if (!$val['isStaticPage']) {
                    $this->createDynamicPageFromMetadata($key, $val, $publication_id, $pub_term->term_id);
                }
            }
        }
    }

    /**
     * Creates or updates a dynamic page based on settings
     */
    public function createDynamicPage(string $key, string $pageName, string $description, int $publication_id, int $pub_term_id, string $template = ''): int
    {
        // Find existing page
        $found_page = $this->findExistingDynamicPage($key, $publication_id);

        // Update publication root page (returns 0 for primary publication)
        //$root_page_id = $this->buildPublicationRootPage($publication_id);
        //$root_page_id = $this->buildPublicationSlugPage($publication_id);
        $root_page_id = $this->getPublicationSlugPageId($publication_id);

        // Get publication page settings to get the custom slug if available
        $pub_page_settings = get_term_meta($pub_term_id, 'publication_page_settings', true);
        $pub_page_settings = $pub_page_settings ? json_decode($pub_page_settings, true) : [];

        // Get custom slug from settings or fallback to sanitized title
        $post_slug = isset($pub_page_settings[$key]['pageSlug']) ?
            sanitize_title($pub_page_settings[$key]['pageSlug']) :
            sanitize_title($pageName);

        $page = [
            'post_type'    => 'page',
            'post_title'   => sanitize_text_field($pageName),
            'post_status'  => 'publish',
            'post_name'    => $post_slug, // Use custom slug from settings
            'post_excerpt' => sanitize_text_field($description),
            'post_parent'  => $root_page_id, // Set publication root as parent
            'post_author'  => 0, // Set no author
        ];

        if ($found_page) {
            $this->logProgress('Updating Plugin page - ' . esc_html($pageName));
            $page['ID'] = $found_page->ID;
            $page_id = wp_update_post($page);
        } else {
            $this->logProgress('Creating Plugin page - ' . esc_html($pageName));
            $page_id = wp_insert_post($page);

            if (!is_wp_error($page_id)) {
                update_post_meta($page_id, 'created_by', 'optipub-plugin');
                update_post_meta($page_id, '_optipub_plugin_page', $key);

                // Set the publication taxonomy
                wp_set_object_terms($page_id, $pub_term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

                // Add publication ID as post meta
                update_post_meta($page_id, 'publication_id', $publication_id);
            }
        }

        // Update the template
        if ($template) {
            update_post_meta($page_id, '_wp_page_template', $template);
        }

        $this->logDoneStatus();

        // Update the rewrite rules - not sure if this is needed its already done in the SettingsHandler
        $this->flushRules();

        return $page_id;
    }

    public function buildStaticPages($publication_id): void
    {
        $jsonResponse = $this->optiPubApi->getPages($publication_id);

        foreach ($jsonResponse->data as $optipub_page) {
            $this->processStaticPage($optipub_page, $publication_id);
        }
    }

    public function updatePageMetadata(int $postId, object $optipub_page, string $path, bool $isLastPage, int $key): void
    {
        Helper::updateSeoMetadata($optipub_page, $postId, $isLastPage, $key);

        // Find the term for this publication
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_id',
                    'value'   => $optipub_page->publication_id,
                    'compare' => '=',
                ],
            ],
        ]);

        update_post_meta($postId, 'publication_id', $optipub_page->publication_id);

        if (!empty($terms) && !is_wp_error($terms)) {
            wp_set_object_terms($postId, $terms[0]->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
        }

        $postDateModifiedGMT = gmdate('Y-m-d H:i:s', strtotime($optipub_page->updated_at));
        $postDateModified = get_date_from_gmt($postDateModifiedGMT);
        $this->wpDbQuery->updatePostModifiedDate($postId, $postDateModified, $postDateModifiedGMT);

        // Only update templates for new pages, not when updating existing ones
        if ($isLastPage && !get_post_meta($postId, '_optipub_plugin_page', true)) {
            //if ($isLastPage) {
            $this->updatePageTemplates($postId, $optipub_page);
        }
    }

    /**
     * Build the publication slug page structure
     *
     * This function:
     * 1. Creates/updates the additional publications prefix page if needed
     * 2. Creates/updates the publication slug page under the prefix
     *
     * @param int $publication_id The publication ID
     * @return int The ID of the publication slug page
     */
    public function buildPublicationSlugPage(int $publication_id): int
    {
        $publications = new \OptiPubWP\Publications\Publications();
        $publication_data = $publications->optipub_get_publication_terms_by_pub_id($publication_id);

        if (!$publication_data) {
            return 0;
        }

        $primary_publication_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        // If this is the primary publication, don't create a slug page
        if ($publication_id == $primary_publication_id) {
            return 0;
        }

        // Get the term ID for the primary publication
        $primary_publication_data = $publications->optipub_get_publication_terms_by_pub_id($primary_publication_id);
        $primary_term_id = $primary_publication_data ? $primary_publication_data->term_id : 0;

        // Get the additional publications prefix from settings
        $prefix_slug = get_option(Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX);

        if (empty($prefix_slug)) {
            $prefix_page_id = 0;
        } else {
            // Create or get the prefix page
            $prefix_page_id = $this->createOrGetPublicationSlugPage($primary_publication_id, 0, (object) [
                'slug'    => $prefix_slug,
                'term_id' => $primary_term_id,
            ], 'additional_pub_prefix');
        }

        // Create or get the publication slug page
        return $this->createOrGetPublicationSlugPage($publication_id, $prefix_page_id, $publication_data, 'publication_slug_' . $publication_id);
    }

    /**
     * Create a dynamic page from metadata
     */
    private function createDynamicPageFromMetadata(string $key, array $val, int $publication_id, int $pub_term_id): void
    {
        $title = $val['pageTitle'];
        $description = $val['pageDescription'] ?? '';

        if (!isset($val['templateName']) || empty($val['templateName'])) {
            return;
        }
        $template_name = $val['templateName'];

        // Find existing page regardless of createCpt setting
        $existing_page = $this->findExistingDynamicPage($key, $publication_id);

        // Check if page creation is disabled in settings
        if (isset($val['createCpt']) && !$val['createCpt']) {
            // If page exists and createCpt is false, delete the page
            if ($existing_page) {
                $this->logProgress('Deleting disabled page - ' . esc_html($title));
                wp_delete_post($existing_page->ID, true); // true = force delete, bypass trash
                $this->logDoneStatus();
            }

            return;
        }

        // Continue with page creation/update if createCpt is true or not set
        $pageId = $this->createDynamicPage($key, $title, $description, $publication_id, $pub_term_id, $template_name);

        // If no home page then set Articles as Homepage for primary publication
        $is_primary_publication = optipub_get_publication_data($publication_id, 'publication_primary');

        if ($is_primary_publication === '1' && $key === 'articles') {
            //$this->setDynamicAsHomePage($pageId, $key);
        }
    }

    /**
     * Find existing dynamic page by key and publication ID
     */
    private function findExistingDynamicPage(string $key, int $publication_id)
    {
        $existing_pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => parent::getPublicPostStatuses(),
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'publication_id',
                    'value'   => $publication_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_optipub_plugin_page',
                    'value'   => $key,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($existing_pages) ? $existing_pages[0] : null;
    }

    /**
     * Set Homepage to Articles Dynamic Page as default
     *
     * Static home pages will override
     */
    private function setDynamicAsHomePage(int $pageId, string $key): void
    {
        $current_front_page = get_option('page_on_front');

        if (!$current_front_page || '0' === $current_front_page) {
            update_option('page_on_front', $pageId);
            update_post_meta($pageId, '_optipub_home_page', '1');
            $this->logError('Set New Home Page to Articles page');
        }
    }

    private function processStaticPage($optipub_page, $publication_id): void
    {
        $path = $optipub_page->path;

        // Remove domain and protocol if present
        $path = preg_replace('#^https?://[^/]+#', '', $path);

        // Split path into parts, excluding empty elements
        $subPages = ('/' === $path) ? ['/'] : array_filter(explode('/', ltrim($path, '/')));

        // Get the publication slug page ID
        $root_page_id = $this->getPublicationSlugPageId($publication_id);

        // Start with publication slug page as parent
        $post_parent = $root_page_id;
        $lastKey = array_key_last($subPages);

        // Create a static page for each part of the path
        foreach ($subPages as $key_count => $post_name) {
            $isLastPage = ($key_count === $lastKey);

            if ($isLastPage) {
                // Set post_parent to the ID of the last created page
                $post_parent = $this->createStaticPage($publication_id, $subPages, $key_count, $optipub_page, $post_name, $post_parent, $isLastPage);
            } else {
                $post_parent = $this->createStaticSubdirectory($publication_id, $subPages, $key_count, $optipub_page, $post_name, $post_parent, $isLastPage);
            }
        }
    }

    /**
     * Create a static subdirectory page
     *
     * @param int $publication_id
     * @param array $subPages
     * @param int $key_count
     * @param object $optipub_page
     * @param string $post_name
     * @param int $post_parent
     * @param bool $isLastPage
     *
     * @return int $post_id
     */
    private function createStaticSubdirectory($publication_id, array $subPages, int $key_count, object $optipub_page, string $post_name, int $post_parent, bool $isLastPage): int
    {
        // Prepare page arguments
        $pagePost = $this->preparePagePost($post_name, $optipub_page, $post_parent, $key_count, $isLastPage);

        // path only used for logging to wp admin page
        $path = $this->buildPagePath($subPages, $key_count);

        // Declare var for existing page
        $existingPageID = null;

        // Does a post with this path exist?
        $existingPath = $this->findExistingPath($post_name, $post_parent);

        // Separate check if first path part matches pub slug page
        if ($key_count === 0 || $key_count === '0') {
            $root_is_plugin_page = get_post_meta($post_parent, '_optipub_plugin_page', true);

            if ($root_is_plugin_page) {
                $root_name = get_post($post_parent)->post_name;

                if ($root_name === $pagePost['post_name']) {
                    $existingPageID = $post_parent;
                    $existing_subdirectory_meta = get_post_meta($post_parent, '_optipub_subdirectory', true);
                }
            }
        }

        if (isset($existingPath->ID) && $existingPageID === null) {
            $existing_publication_id = get_post_meta($existingPath->ID, 'publication_id', true);
            $existing_subdirectory_meta = get_post_meta($existingPath->ID, '_optipub_subdirectory', true);

            if ($existing_publication_id !== $optipub_page->publication_id) {
                $this->logError("Warning: '{$path}' page - subdirectory used by multiple publications\n");
            }

            $existingPageID = $existingPath->ID;
        }

        // If the existing page is not a subdirectory then return the existing page ID
        if (!empty($existingPageID) && $existing_subdirectory_meta !== '1') {
            return $existingPageID;
        }

        // Create a static page
        return $this->insertOrUpdateStaticPage($publication_id, $pagePost, $path, $existingPageID, $optipub_page, $isLastPage, $key_count);
    }

    /**
     * Create a static page
     *
     * @param int $publication_id
     * @param array $subPages
     * @param int $key_count
     * @param object $optipub_page
     * @param string $post_name
     * @param int $post_parent
     * @param bool $isLastPage
     *
     * @return int $post_id
     */
    private function createStaticPage($publication_id, array $subPages, int $key_count, object $optipub_page, string $post_name, int $post_parent, bool $isLastPage): int
    {
        // Prepare page arguments
        $pagePost = $this->preparePagePost($post_name, $optipub_page, $post_parent, $key_count, $isLastPage);

        // path only used for logging to wp admin page
        $path = $this->buildPagePath($subPages, $key_count);

        // Declare var for existing page
        $existingPageID = null;

        // Separate check if first path part matches pub slug page
        if ($key_count === 0 || $key_count === '0') {
            $root_is_plugin_page = get_post_meta($post_parent, '_optipub_plugin_page', true);

            if ($root_is_plugin_page) {
                $root_name = get_post($post_parent)->post_name;

                if ($root_name === $pagePost['post_name']) {
                    $existingPageID = $post_parent;
                }
            }
        }

        // Does a post with this path exist?
        $existingPath = $this->findExistingPath($post_name, $post_parent);

        // Does this OptiPub page already exist?
        $existingPageID = $this->findExistingPageId($optipub_page->id) ?? $existingPageID;

        // Match on dynamic page postmeta because they don't have an optipub_page_id
        if ($existingPath && !$existingPageID) {
            $isPluginPage = get_post_meta($existingPath->ID, '_optipub_plugin_page', true);

            if ($isPluginPage) {
                // Get the publication ID and post parent of the existing page
                $plugin_page_publication_id = get_post_meta($existingPath->ID, 'publication_id', true);

                // If this is the same publication as the page being created, use the existing page's parent
                if ($plugin_page_publication_id !== $optipub_page->publication_id) {
                    $this->logError("Warning: '{$path}' - OptiPub page attempting to overwrite plugin page from a different publication.\n");
                } else {
                    $this->logError("Warning: '{$path}' - OptiPub page attempting to overwrite plugin page.\n");
                }

                // Never overwrite plugin-managed pages (search/archives/resources/etc).
                // Keep the existing plugin page as the path node and skip static page update.
                return (int) $existingPath->ID;
            }
        }

        // Insert or update the page and return the new page ID to be the next post_parent
        return $this->insertOrUpdateStaticPage($publication_id, $pagePost, $path, $existingPageID, $optipub_page, $isLastPage, $key_count);
    }

    private function preparePagePost(string $post_name, object $optipub_page, int $post_parent, int $key_count, bool $isLastPage): array
    {
        $postDateGMT = gmdate('Y-m-d H:i:s', strtotime($optipub_page->created_at));
        $postDate = get_date_from_gmt($postDateGMT);

        $post_title = ucwords($post_name);
        $post_title = str_replace('-', ' ', $post_title);

        return [
            'post_title'            => $post_title,
            'post_name'             => sanitize_title($post_name),
            'post_excerpt'          => $isLastPage ? sanitize_text_field($optipub_page->meta_description ?? '') : '',
            'post_status'           => 'publish',
            'post_content_filtered' => $isLastPage ? ($optipub_page->html ?? '') : '',
            'post_content'          => $isLastPage ? $this->updateMacro($optipub_page->html ?? '') : '',
            'post_date'             => $postDate,
            'post_date_gmt'         => $postDateGMT,
            'post_parent'           => $post_parent,
            'post_type'             => 'page',
            'post_author'           => 0, // Set no author
            'menu_order'            => $key_count,
        ];
    }

    /**
     * Build the page path from the subpages array up to the current key
     */
    private function buildPagePath(array $subPages, int $key_count): string
    {
        $path = '';

        for ($i = 0; $i <= $key_count; $i++) {
            $path .= '/' . $subPages[$i];
        }

        return ('/' === $path) ? $path : ltrim($path, '/');
    }

    private function findExistingPath(string $path, int $post_parent): ?object
    {
        // 'name' is post_name or slug
        $existing_pages = get_posts([
            'name'           => sanitize_title($path),
            'post_type'      => 'page',
            'post_status'    => parent::getPublicPostStatuses(),
            'posts_per_page' => 1,
            'post_parent'    => $post_parent,
        ]);

        return !empty($existing_pages) ? $existing_pages[0] : null;
    }

    private function findExistingPageId(int $optipub_id): ?int
    {
        $existing_pages = get_posts([
            'post_type'      => 'page',
            'meta_key'       => 'optipub_page_id',
            'meta_value'     => $optipub_id,
            'posts_per_page' => 1,
        ]);

        return !empty($existing_pages) ? $existing_pages[0]->ID : null;
    }

    private function isCurrentSubdirectory(?object $existingPath, int $post_parent, bool $isLastPage): bool
    {
        if (isset($existingPath->ID)) {
            $isSubDirectory = get_post_meta($existingPath->ID, '_optipub_subdirectory', true) == '1';
        } else {
            $isSubDirectory = false;
        }

        return $existingPath && $existingPath->post_parent === $post_parent && !$isLastPage && $isSubDirectory;
    }

    private function insertOrUpdateStaticPage(int $publication_id, array $pagePost, string $path, ?int $existingPageID, object $optipub_page, bool $isLastPage, int $key): int
    {
        $path_esc = esc_html($path);

        // Insert or update the page
        if ($existingPageID) {
            $this->logProgress('Updating page - ' . $path_esc);

            // Get the current page to check its parent
            $existing_page = get_post($existingPageID);

            $pagePost['ID'] = $existingPageID;
            $pagePost['post_parent'] = $existing_page->post_parent;
            $postId = wp_update_post($pagePost, true);

            $wasSubdirectory = get_post_meta($existingPageID, '_optipub_subdirectory', true);

            if ((1 === $wasSubdirectory || '1' === $wasSubdirectory) && $isLastPage) {
                update_post_meta($existingPageID, '_optipub_subdirectory', '0');
            } elseif (!$isLastPage) {
                update_post_meta($existingPageID, '_optipub_subdirectory', '1');
            }
        } else {
            $this->logProgress('Creating page - ' . $path_esc);
            $postId = wp_insert_post($pagePost, true);
        }

        if (is_wp_error($postId)) {
            $this->logFailedStatus();
            $this->logError("\nFailed creating '{$path_esc}' page: \n" . $postId->get_error_message() . "\n");

            return 0;
        }

        // Continue with existing metadata updates
        $this->updatePageMetadata($postId, $optipub_page, $path, $isLastPage, $key);
        $this->logDoneStatus();

        return $postId;
    }

    /**
     * Process home template setting for a page
     */
    private function processHomeTemplate(int $page_id): void
    {
        $optipub_template = get_option(Constants::OPTIPUB_HOME_TEMPLATE);
        $template = '';

        // First check if OptiPub template exists and use it
        if (!empty($optipub_template)) {
            if (locate_template($optipub_template)) {
                $template = $optipub_template;
            } else {
                $this->logStaticError("ERROR: Could not set template - '{$optipub_template}' - for page - 'esc_html({$path})' - because the template file does not exist in theme");
            }
        } else {
            // Exit if the page already has a template
            $current_template = get_post_meta($page_id, '_wp_page_template', true);

            if (!empty($current_template)) {
                return;
            }
        }

        // If no optipub home template was found, fall back to common home page templates
        if (empty($template)) {
            $template = locate_template(['front-page.php', 'home.php']);

            // If no template was found, fall back to Wordpress default template
            if (empty($template)) {
                // Use Wordpress default template
                $this->logStaticError('No home page template found, using default template');
                delete_post_meta($page_id, '_wp_page_template');

                return;
            }
        }

        // Verify page exists before setting template and front page
        if (get_post($page_id)) {
            $template_name = basename($template);

            // Set template for home page
            update_post_meta($page_id, '_wp_page_template', $template_name);
            $this->logStaticError("Set template '{$template_name}' for home page");

            // Set as front page in WordPress but don't force home to use a page
            update_option('page_on_front', $page_id);
            $post_name = get_post($page_id)->post_name;
            $this->logStaticError("Set '{$post_name}' as front page");
        } else {
            $this->logStaticError('Invalid page ID for home page');
        }
    }

    private function updatePageTemplates(int $postId, object $optipub_page): void
    {
        // Check for special page categories
        if (!empty($optipub_page->page_category_id)) {
            /**
             *  Handle Home Page
             */
            $home_page_id = get_option(Constants::OPTIPUB_PAGE_CATEGORY_ID_HOME);

            if ($optipub_page->page_category_id == $home_page_id) {
                // Set page template to home template
                $this->processHomeTemplate($postId);

                // Unset current OptiPub home page
                $this->unsetCurrentOptiPubHomePage();
                update_post_meta($postId, '_optipub_home_page', '1');
            } else {
                delete_post_meta($postId, '_optipub_home_page');
            }

            // Handle special pages
            $this->handleSpecialPage($optipub_page, $postId, 'subscribers');
            $this->handleSpecialPage($optipub_page, $postId, 'login');
        }
    }

    private function unsetCurrentOptiPubHomePage(): void
    {
        // Find posts that are marked as an OptiPub home page
        $existing_home_pages = get_posts([
            'post_type'      => 'page',
            'meta_key'       => '_optipub_home_page',
            'meta_value'     => '1',
            'posts_per_page' => -1,
        ]);

        foreach ($existing_home_pages as $page) {
            delete_post_meta($page->ID, '_optipub_home_page');
            $this->logStaticError("Changed home page off '{$page->post_title}'");
        }
    }

    /**
     * Handle special page types (subscribers and login)
     */
    private function handleSpecialPage(object $optipub_page, int $postId, string $type): void
    {
        // Map type to actual constants
        $constants_map = [
            'subscribers' => [
                'page_id'  => Constants::OPTIPUB_PAGE_CATEGORY_ID_SUBSCRIBERS,
                'template' => Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE,
            ],
            'login' => [
                'page_id'  => Constants::OPTIPUB_PAGE_CATEGORY_ID_LOGIN,
                'template' => Constants::OPTIPUB_LOGIN_TEMPLATE,
            ],
        ];

        if (!isset($constants_map[$type])) {
            return;
        }

        $meta_key = "_optipub_{$type}_page";
        $page_category_id = get_option($constants_map[$type]['page_id']);

        if ($optipub_page->page_category_id == $page_category_id) {
            // Check if template exists in theme
            $template = locate_template($constants_map[$type]['template']);

            if ($template) {
                $template_name = basename($template);
                update_post_meta($postId, '_wp_page_template', $template_name);
            }

            update_post_meta($postId, $meta_key, '1');
        } else {
            delete_post_meta($postId, $meta_key);
        }
    }

    /**
     * Create or get a page (prefix or publication slug)
     *
     * @param int $publication_id The publication ID (0 for prefix page)
     * @param int $parent_id The parent page ID
     * @param object $publication_data The publication term data (or prefix data)
     * @param string $meta_value The meta value to use for _optipub_plugin_page
     * @return int The ID of the page
     */
    private function createOrGetPublicationSlugPage(int $publication_id, int $parent_id, object $publication_data, string $meta_value): int
    {
        // Get the slug from term object or prefix
        $slug = $publication_data->slug;

        if (empty($slug)) {
            return 0;
        }

        // Check if the page exists
        $page_id = 0;

        // For prefix pages, we need to check differently
        if ($meta_value === 'additional_pub_prefix') {
            $page_id = $this->getPublicationSlugPageId($publication_id, true);
        } else {
            // For publication slug pages, use the getPublicationSlugPageId function
            $page_id = $this->getPublicationSlugPageId($publication_id);
        }

        // If the page exists, update it
        if ($page_id) {
            // Only update specific fields for existing page
            $update_result = wp_update_post([
                'ID'          => $page_id,
                'post_title'  => ucwords(str_replace('-', ' ', $slug)),
                'post_name'   => sanitize_title($slug),
                'post_parent' => $parent_id,
            ]);

            if (is_wp_error($update_result)) {
                return 0;
            }

            // Update the meta value for existing page
            update_post_meta($page_id, '_optipub_plugin_page', $meta_value);

            return $page_id;
        }

        // Create new page with all fields
        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => ucwords(str_replace('-', ' ', $slug)),
            'post_name'    => sanitize_title($slug),
            'post_status'  => 'publish',
            'post_parent'  => $parent_id,
            'post_author'  => 0,
            'post_content' => '<!-- wp:paragraph --><p>Directory page for ' . esc_html($slug) . '</p><!-- /wp:paragraph -->',
        ]);

        if (is_wp_error($page_id)) {
            return 0;
        }

        if ($page_id) {
            update_post_meta($page_id, 'created_by', 'optipub-plugin');
            update_post_meta($page_id, '_optipub_plugin_page', $meta_value);
            update_post_meta($page_id, '_optipub_subdirectory', '1');

            // Set publication-specific meta if publication_id is provided
            if ($publication_id !== 0) {
                update_post_meta($page_id, 'publication_id', $publication_id);

                // Set the publication taxonomy if term_id is available
                if (isset($publication_data->term_id) && $publication_data->term_id) {
                    wp_set_object_terms($page_id, $publication_data->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
                }
            }

            return $page_id;
        }

        return 0;
    }
}
