<?php

namespace OptiPub\Base;

/**
 * Helper class for various utility functions
 */
class Helper extends BaseController
{
    private $optiPubApi;

    public function __construct()
    {
        parent::__construct();
        $this->optiPubApi = parent::createOptiPubApiInstance();
    }

    /**
     * Write custom post types metadata to WordPress options
     *
     * @param string|int $publication_id The publication ID
     * @return void
     */
    public function writeCustomPostTypesMetadataInOptions($publication_id): void
    {
        if (!$publication_id) {
            $this->logError("Unable to write metadata since publication is empty.\n");

            return;
        }

        $publication = optipub_get_publication_data($publication_id);

        if (isset($publication->meta['publication_page_settings'][0])) {
            return;
        }

        $this->logProgress('Saving metadata');

        // Get base categories (these define CPTs)
        $categories = $this->optiPubApi->getResourceCategories($this->getCategoryColumns());
        $importedCPTs = $this->buildCustomPostTypes($categories, $publication->name);
        $importedCPTs = array_merge(
            $importedCPTs,
            $this->getAdditionalPostTypes($publication->name)
        );

        // Store base CPT structure in global option
        update_option(Constants::OPTIPUB_METADATA, $importedCPTs);

        if ($publication) {
            // Store the metadata for this specific publication
            $this->savePublicationPageSettings($publication->term_id, $importedCPTs);
        }

        $this->logDoneStatus();
    }

    /**
     * Reconcile the WordPress category terms mirroring OptiPub child categories
     *
     * Resource syncing resolves a child category through its `optipub_category_id`
     * term meta and silently skips every category it cannot resolve, so a term that
     * was deleted or recreated by hand takes all of its resources down with it.
     * Reconciling on each sync is what keeps that from being unrecoverable, since
     * writeCustomPostTypesMetadataInOptions() only ever runs at publication setup.
     *
     * @return void
     */
    public function syncPostTypeCategories(): void
    {
        $categories = $this->optiPubApi->getResourceCategories($this->getCategoryColumns());

        if (empty($categories->data)) {
            $this->logError('Skipped category sync - no categories returned from the API');

            return;
        }

        [$parentCategories, $childCategories] = $this->splitCategoriesByDepth($categories);

        foreach ($childCategories as $category) {
            $parentPostType = $this->findParentPostType($category->parent_category->id, $parentCategories);

            if ($parentPostType) {
                $this->createPostTypeCategory($category, $parentPostType);
            } else {
                $this->logError('Could not find parent post type for category ' . $category->name);
            }
        }
    }

    /**
     * Gets path to WordPress configuration
     *
     * @return string|false Path to wp-config.php or false if not found
     */
    public static function locate_wp_config()
    {
        static $path;

        if (null === $path) {
            $path = false;

            // Get the WordPress root directory using untrailingslashit to normalize
            $wp_root = untrailingslashit(ABSPATH);

            // Get parent directory of WordPress installation
            $parent_dir = dirname($wp_root);

            $possible_paths = [
                getenv('WP_CONFIG_PATH'),
                $wp_root . '/wp-config.php',
                $parent_dir . '/wp-config.php',
            ];

            foreach ($possible_paths as $possible_path) {
                if ($possible_path && file_exists($possible_path)) {
                    // If wp-config.php is in parent directory, verify it's not a false positive
                    // by checking that wp-settings.php doesn't exist in the same directory
                    if ($possible_path === $parent_dir . '/wp-config.php') {
                        if (!file_exists($parent_dir . '/wp-settings.php')) {
                            $path = $possible_path;
                            break;
                        }
                    } else {
                        $path = $possible_path;
                        break;
                    }
                }
            }

            if ($path) {
                $path = realpath($path);
            }
        }

        return $path;
    }

    /**
     * Update SEO metadata for a post
     */
    public static function updateSeoMetadata($resource, $postId, bool $isLastPage = true, $key = null): void
    {
        update_post_meta($postId, 'created_by', 'optipub-plugin');

        if (!$isLastPage) {
            $optipub_page_id = $resource->id . '-' . $key;
            update_post_meta($postId, 'optipub_page_id', $optipub_page_id);

            return;
        }

        update_post_meta($postId, 'optipub_page_id', $resource->id);

        /* $resource field => wp_postmeta meta_key */
        $metaFields = [
            'meta_title'       => 'resource_meta_title',
            'meta_description' => 'resource_meta_description',
            'meta_keywords'    => 'resource_meta_keywords',
            'canonical_url'    => 'canonical_url',
        ];

        /**
         * Update wp_postmeta meta_key with resource field value
         */
        foreach ($metaFields as $source => $target) {
            if (isset($resource->$source)) {
                update_post_meta($postId, $target, $resource->$source);
            } elseif ('meta_description' === $source && isset($resource->description)) {
                update_post_meta($postId, $target, $resource->description);
            }
        }
    }

    /**
     * Save publication-specific page settings to term meta
     *
     * @param int $term_id The publication term ID
     * @param array $cpt_metadata Base metadata structure
     * @return void
     */
    private function savePublicationPageSettings(int $term_id, array $cpt_metadata): void
    {
        // Get existing publication settings
        $existing_settings = get_term_meta($term_id, 'publication_page_settings', true);
        $existing_settings = $existing_settings ? json_decode($existing_settings, true) : [];

        // For each page type, create/update publication-specific settings
        foreach ($cpt_metadata as $key => $settings) {
            if (!isset($existing_settings[$key])) {
                // Use default settings but can be customized per publication
                $existing_settings[$key] = $settings;
            }
            // Otherwise keep existing customized settings
        }

        // Store updated settings
        update_term_meta($term_id, 'publication_page_settings', wp_json_encode($existing_settings));
    }

    /**
     * Build custom post types from categories
     */
    private function buildCustomPostTypes($categories, $publication_name): array
    {
        $importedCPTs = [];

        [$parentCategories, $childCategories] = $this->splitCategoriesByDepth($categories);

        // First, create post types for parent categories
        foreach ($parentCategories as $category) {
            $this->logProgress('Start building custom post type for ' . $category->name);
            $categoryName = $this->formatCategoryName($category->name);
            $categorySlug = str_replace(' ', '-', strtolower($categoryName)) . 's';
            $categoryPublic = $category->public;

            $importedCPTs[$categorySlug] = $this->buildPostTypeConfig(
                $category->id,
                $categoryName,
                $categoryName . 's',
                $categoryPublic,
                $publication_name
            );
            $this->logProgress('Done building custom post type for ' . $category->name);
        }

        // Then, handle child categories by creating post type categories
        foreach ($childCategories as $category) {
            $this->logProgress('Creating post type category for ' . $category->name);

            $parentPostType = $this->findParentPostType($category->parent_category->id, $parentCategories);

            if ($parentPostType) {
                $this->createPostTypeCategory($category, $parentPostType);
                $this->logProgress('Done creating post type category for ' . $category->name);
            } else {
                $this->logError('Could not find parent post type for category ' . $category->name);
            }
        }

        return $importedCPTs;
    }

    /**
     * Columns requested when pulling the OptiPub category tree
     */
    private function getCategoryColumns(): array
    {
        return [
            'id',
            'name',
            'description',
            'created_at',
            'updated_at',
            'uses_ranks',
            'public',
            'id_with_name',
            'parent_category',
            'thumbnail_image_id',
            'thumbnail',
        ];
    }

    /**
     * Split the category tree into top level categories, which define custom post
     * types, and their children, which become WordPress category terms
     */
    private function splitCategoriesByDepth($categories): array
    {
        $parentCategories = [];
        $childCategories = [];

        foreach ($categories->data as $category) {
            if (isset($category->parent_category) && !empty($category->parent_category)) {
                $childCategories[] = $category;
            } else {
                $parentCategories[] = $category;
            }
        }

        return [$parentCategories, $childCategories];
    }

    /**
     * Find parent post type slug for a given parent category ID
     */
    private function findParentPostType($parentCategoryId, array $parentCategories): ?string
    {
        foreach ($parentCategories as $parentCategory) {
            if ($parentCategory->id == $parentCategoryId) {
                $categoryName = $this->formatCategoryName($parentCategory->name);

                return str_replace(' ', '-', strtolower($categoryName)) . 's';
            }
        }

        return null;
    }

    /**
     * Create post type category for child categories
     */
    private function createPostTypeCategory($categoryObject, string $parentPostType): void
    {
        $categoryName = $this->formatCategoryName($categoryObject->name);

        $existingCategory = get_term_by('name', $categoryName, 'category');

        if (!$existingCategory) {
            $insertedCategory = wp_insert_term(
                $categoryName,
                'category',
                [
                    'description' => $categoryObject->description,
                    'slug'        => sanitize_title($categoryName),
                ]
            );

            if (is_wp_error($insertedCategory)) {
                $this->logError("\nFailed creating post type category: " . $categoryName . "\n" . $insertedCategory->get_error_message());
            } else {
                $categoryId = (int) $insertedCategory['term_id'];
                update_term_meta($categoryId, 'created_by', 'optipub-plugin');
                update_term_meta($categoryId, 'optipub_category_id', $categoryObject->id);
                update_term_meta($categoryId, 'optipub_category_description', $categoryObject->description);

                if (isset($categoryObject->thumbnail->path)) {
                    update_term_meta($categoryId, 'optipub_category_thumbnail', $categoryObject->thumbnail->path);
                }
                update_term_meta($categoryId, 'optipub_parent_post_type', $parentPostType);

                $this->logProgress('Successfully created post type category: ' . $categoryName . ' (ID: ' . $categoryId . ')');
            }
        } else {
            $updatedCategory = wp_update_term(
                (int) $existingCategory->term_id,
                'category',
                [
                    'name'        => $categoryName,
                    'description' => $categoryObject->description,
                    'slug'        => sanitize_title($categoryName),
                ]
            );

            if (is_wp_error($updatedCategory)) {
                $this->logError("\nFailed updating post type category: " . $categoryName . "\n" . $updatedCategory->get_error_message());
            } else {
                update_term_meta($existingCategory->term_id, 'created_by', 'optipub-plugin');
                update_term_meta($existingCategory->term_id, 'optipub_category_id', $categoryObject->id);
                update_term_meta($existingCategory->term_id, 'optipub_category_description', $categoryObject->description);

                if (isset($categoryObject->thumbnail->path)) {
                    update_term_meta($existingCategory->term_id, 'optipub_category_thumbnail', $categoryObject->thumbnail->path);
                } else {
                    delete_term_meta($existingCategory->term_id, 'optipub_category_thumbnail');
                }
                update_term_meta($existingCategory->term_id, 'optipub_parent_post_type', $parentPostType);

                $this->logProgress('Updated existing post type category: ' . $categoryName . ' (ID: ' . $existingCategory->term_id . ')');
            }
        }
    }

    /**
     * Format category name
     */
    private function formatCategoryName(string $name): string
    {
        return 'Bonus Report' === $name ? 'Report' : $name;
    }

    /**
     * Build post type configuration
     */
    private function buildPostTypeConfig(
        int $id,
        string $singular,
        string $plural,
        bool $isPublic,
        string $publication_name
    ): array {
        $config = [
            'id'                   => $id,
            'cptSingularName'      => $singular,
            'cptPluralName'        => $plural,
            'createCpt'            => true,
            'pageTitle'            => $plural,
            'pageDescription'      => $publication_name . ' ' . $plural,
            'isStaticPage'         => false,
            'cptPublic'            => $isPublic,
            'cptShowInMenu'        => 'optipub-resources',
            'cptHasArchive'        => $isPublic,
            'cptRewrite'           => $isPublic,
            'cptPubliclyQueryable' => $isPublic,
            'cptExcludeFromSearch' => false === $isPublic,
            'cptUsePostTypeInUrl'  => false,
            'pageSlug'             => str_replace(' ', '-', strtolower($plural)),
        ];

        // Add template based on post type
        switch ($singular) {
            case 'Article':
                $config['templateName'] = Constants::ARCHIVE_TEMPLATE_NAME;
                break;
            case 'Report':
                $config['templateName'] = Constants::REPORTS_TEMPLATE_NAME;
                break;
            case 'Resource':
                $config['templateName'] = Constants::RESOURCES_TEMPLATE_NAME;
                break;
            default:
                $config['templateName'] = Constants::ARCHIVE_TEMPLATE_NAME;
                break;
        }

        return $config;
    }

    /**
     * Get additional post types (videos, editors, etc)
     */
    private function getAdditionalPostTypes(string $publication_name): array
    {
        return [
            'videos' => [
                'id'                   => -1,
                'cptSingularName'      => 'Video',
                'cptPluralName'        => 'Videos',
                'createCpt'            => true,
                'pageTitle'            => 'Videos',
                'isStaticPage'         => true,
                'cptPublic'            => true,
                'cptShowInMenu'        => true,
                'cptHasArchive'        => true,
                'cptRewrite'           => true,
                'cptPubliclyQueryable' => true,
                'cptExcludeFromSearch' => false,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => Constants::VIDEOS_TEMPLATE_NAME,
                'pageDescription'      => "Videos presented by the editors of {$publication_name}",
                'pageSlug'             => 'videos',
            ],
            'faqs' => [
                'id'                   => -1,
                'cptSingularName'      => 'FAQ',
                'cptPluralName'        => 'FAQs',
                'createCpt'            => true,
                'pageTitle'            => 'FAQs',
                'isStaticPage'         => true,
                'cptPublic'            => true,
                'cptShowInMenu'        => true,
                'cptHasArchive'        => true,
                'cptRewrite'           => true,
                'cptPubliclyQueryable' => true,
                'cptExcludeFromSearch' => true,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => Constants::FAQS_TEMPLATE_NAME,
                'pageDescription'      => "Frequently Asked Questions presented by the editors of {$publication_name}",
                'pageSlug'             => 'faqs',
            ],
            'editors' => [
                'id'                   => -1,
                'cptSingularName'      => 'Author',
                'cptPluralName'        => 'Authors',
                'createCpt'            => true,
                'pageTitle'            => 'Authors',
                'isStaticPage'         => true,
                'cptPublic'            => true,
                'cptShowInMenu'        => true,
                'cptHasArchive'        => true,
                'cptRewrite'           => true,
                'cptPubliclyQueryable' => true,
                'cptExcludeFromSearch' => false,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => Constants::EDITOR_TEMPLATE_NAME,
                'pageDescription'      => "Meet the authors of {$publication_name}",
                'pageSlug'             => 'editors',
            ],
            'search' => [
                'id'                   => -1,
                'cptSingularName'      => 'Search',
                'cptPluralName'        => 'Search',
                'createCpt'            => false,
                'pageTitle'            => 'Search',
                'isStaticPage'         => false,
                'cptPublic'            => false,
                'cptShowInMenu'        => false,
                'cptHasArchive'        => false,
                'cptRewrite'           => false,
                'cptPubliclyQueryable' => false,
                'cptExcludeFromSearch' => true,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => Constants::SEARCH_TEMPLATE_NAME,
                'pageDescription'      => "Search {$publication_name}",
                'pageSlug'             => 'search',
            ],
            'archives' => [
                'id'                   => -1,
                'cptSingularName'      => 'Archive',
                'cptPluralName'        => 'Archives',
                'createCpt'            => false,
                'pageTitle'            => 'Archives',
                'isStaticPage'         => false,
                'cptPublic'            => false,
                'cptShowInMenu'        => false,
                'cptHasArchive'        => false,
                'cptRewrite'           => false,
                'cptPubliclyQueryable' => false,
                'cptExcludeFromSearch' => true,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => Constants::ARCHIVE_TEMPLATE_NAME,
                'pageDescription'      => "{$publication_name} Archives",
                'pageSlug'             => 'archives',
            ],
            'subscribers' => [
                'id'                   => -1,
                'cptSingularName'      => 'Subscriber',
                'cptPluralName'        => 'Subscribers',
                'createCpt'            => false,
                'pageTitle'            => 'Subscribers',
                'isStaticPage'         => false,
                'cptPublic'            => false,
                'cptShowInMenu'        => false,
                'cptHasArchive'        => false,
                'cptRewrite'           => false,
                'cptPubliclyQueryable' => false,
                'cptExcludeFromSearch' => true,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => !empty(get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE))
                    ? get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE)
                    : 'default',
                'pageDescription' => 'Subscribers',
                'pageSlug'        => 'subscribers',
            ],
            'login' => [
                'id'                   => -1,
                'cptSingularName'      => 'Login',
                'cptPluralName'        => 'Login',
                'createCpt'            => false,
                'pageTitle'            => 'Login',
                'isStaticPage'         => false,
                'cptPublic'            => false,
                'cptShowInMenu'        => false,
                'cptHasArchive'        => false,
                'cptRewrite'           => false,
                'cptPubliclyQueryable' => false,
                'cptExcludeFromSearch' => true,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => !empty(get_option(Constants::OPTIPUB_LOGIN_TEMPLATE))
                    ? get_option(Constants::OPTIPUB_LOGIN_TEMPLATE)
                    : 'default',
                'pageDescription' => 'Login',
                'pageSlug'        => 'login',
            ],
            'home' => [
                'id'                   => -1,
                'cptSingularName'      => 'Home',
                'cptPluralName'        => 'Home',
                'createCpt'            => false,
                'pageTitle'            => 'Home',
                'isStaticPage'         => false,
                'cptPublic'            => false,
                'cptShowInMenu'        => false,
                'cptHasArchive'        => false,
                'cptRewrite'           => false,
                'cptPubliclyQueryable' => false,
                'cptExcludeFromSearch' => true,
                'cptUsePostTypeInUrl'  => false,
                'templateName'         => !empty(get_option(Constants::OPTIPUB_HOME_TEMPLATE))
                    ? get_option(Constants::OPTIPUB_HOME_TEMPLATE)
                    : 'default',
                'pageDescription' => 'Home',
                'pageSlug'        => 'home',
            ],
        ];
    }
}
