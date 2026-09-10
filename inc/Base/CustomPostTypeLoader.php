<?php

namespace OptiPub\Base;

use Exception;
use OptiPub\Api\WpDatabaseQuery;
use WP_Query;
use WP_Rewrite;

class CustomPostTypeLoader extends BaseController
{
    public const PER_PAGE = 500;

    public $optiPubApi;

    private $wpDbQuery;

    private $pageFactory;

    private $primary_publication_id = null;

    public function __construct()
    {
        parent::__construct();
        $this->optiPubApi = parent::createOptiPubApiInstance();
        $this->wpDbQuery = new WpDatabaseQuery();
        $this->pageFactory = new PageFactory();
    }

    public function loadEditors(int $publication_id)
    {
        $this->ensureRewriteExists();

        $authorsResponse = $this->optiPubApi->getAuthors($publication_id, '', '', '', false, [], '');

        if (count($authorsResponse->data) > 0) {
            $this->logProgress('Loading authors');

            // Get publication term before the loop
            $publication = optipub_get_publication_data($publication_id);

            if (!$publication) {
                $this->logError("\nFailed loading authors: Publication not found\n");

                return;
            }

            $post_parent_id = $this->retrievePostParent($publication_id);

            foreach ($authorsResponse->data as $author) {
                $postDateGMT = gmdate('Y-m-d H:i:s', strtotime($author->created_at));
                $postDate = get_date_from_gmt($postDateGMT);
                $postDateModifiedGMT = gmdate('Y-m-d H:i:s', strtotime($author->updated_at));
                $postDateModified = get_date_from_gmt($postDateModifiedGMT);

                // OptiPub Authors do not have Tags currently
                $categoryIDs = [];

                $editorPost = [
                    'post_title'            => $author->display_name,
                    'post_name'             => sanitize_title($author->display_name),
                    'post_parent'           => $post_parent_id,
                    'post_status'           => $author->show_on_website ? 'publish' : 'private',
                    'post_content_filtered' => $author->bio ?? '',
                    'post_content'          => $author->bio ? $this->updateMacro($author->bio) : '',
                    'post_excerpt'          => $author->blurb ?? '',
                    'post_author'           => $author->id,
                    'post_category'         => $categoryIDs,
                    'menu_order'            => $author->rank ?? 0,
                    'post_date'             => $postDate,
                    'post_date_gmt'         => $postDateGMT,
                    'post_type'             => 'editors',
                ];

                $existingEditorID = $this->wpDbQuery->get_post_id_by_meta_key_and_value(
                    'optipub_page_id',
                    $author->id,
                    'editors',
                    $post_parent_id
                );

                if ($existingEditorID) {
                    $editorPost['ID'] = $existingEditorID;
                    $postId = wp_update_post($editorPost, true);
                } else {
                    $postId = wp_insert_post($editorPost, true);
                }

                if ($postId && !is_wp_error($postId)) {
                    $this->updateEditorMetadata($publication_id, $postId, $author);
                    $this->wpDbQuery->updatePostModifiedDate($postId, $postDateModified, $postDateModifiedGMT);

                    // Ensure publication assignment even for updates
                    update_post_meta($postId, 'publication_id', $publication_id);
                    wp_set_object_terms($postId, $publication->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
                }

                if (is_wp_error($postId)) {
                    $this->logError("\nFailed loading author post:\n" . $postId->get_error_message() . "\n");
                }
            }

            $this->logDoneStatus();
        }
    }

    public function loadVideos(int $publication_id)
    {
        $this->ensureRewriteExists();

        $jsonResponse = $this->optiPubApi->getVideos($publication_id, '', '', '', false, []);

        if (count($jsonResponse->data) > 0) {
            $this->logProgress('Loading videos');

            // Get publication term before the loop
            $publication = optipub_get_publication_data($publication_id);

            if (!$publication) {
                $this->logError("\nFailed loading videos: Publication not found\n");

                return;
            }

            $post_parent_id = $this->retrievePostParent($publication_id);

            foreach ($jsonResponse->data as $video) {
                $title = $video->title;
                $id = $video->id;
                $description = ! empty($video->name) ? $video->name : '';
                $postDateGMT = gmdate('Y-m-d H:i:s', strtotime($video->created_at));
                $postDate = get_date_from_gmt($postDateGMT);
                $postDateModifiedGMT = gmdate('Y-m-d H:i:s', strtotime($video->updated_at));
                $postDateModified = get_date_from_gmt($postDateModifiedGMT);
                $rank = null == $video->rank ? 0 : $video->rank;

                $categoryIDs = $this->populateCategories($video);

                $content = 'single-videos.php template not found!';

                $videoPost = [
                    'post_title'            => $video->title,
                    'post_name'             => sanitize_title($video->title),
                    'post_parent'           => $post_parent_id,
                    'post_excerpt'          => $video->name ?? '',
                    'post_status'           => 'publish',
                    'post_content_filtered' => $content,
                    'post_content'          => $this->updateMacro($content),
                    'post_date'             => $postDate,
                    'post_date_gmt'         => $postDateGMT,
                    'post_category'         => !empty($categoryIDs) ? $categoryIDs : [-1],
                    'menu_order'            => $video->rank ?? 0,
                    'post_type'             => 'videos',
                ];

                $existingVideoID = $this->wpDbQuery->get_post_id_by_meta_key_and_value('optipub_page_id', $video->id, 'videos', $post_parent_id);

                if ($existingVideoID) {
                    $videoPost['ID'] = $existingVideoID;
                    $postId = wp_update_post($videoPost, true);
                } else {
                    $postId = wp_insert_post($videoPost, true);
                }

                if ($postId && !is_wp_error($postId)) {
                    $tagIDs = $this->populateTags($video);

                    // Validate tagIDs to prevent wp_set_post_terms from auto-creating tags
                    $validated_tagIDs = $this->validateTagIDs($tagIDs);

                    wp_set_post_terms($postId, $validated_tagIDs, 'post_tag', false);

                    $this->updateVideoMetadata($publication_id, $postId, $video);
                    $this->wpDbQuery->updatePostModifiedDate($postId, $postDateModified, $postDateModifiedGMT);

                    // Ensure publication assignment even for updates
                    update_post_meta($postId, 'publication_id', $publication_id);
                    wp_set_object_terms($postId, $publication->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
                }

                if (is_wp_error($postId)) {
                    $this->logError("Failed loading video post: \n" . $postId->get_error_message() . "\n");
                }
            }

            $this->logDoneStatus();
        }
    }

    public function loadFaqs(int $publication_id)
    {
        $this->ensureRewriteExists();

        $jsonResponse = $this->optiPubApi->getFaqs($publication_id, '', '', '', false, []);

        if (count($jsonResponse->data) > 0) {
            $this->logProgress('Loading faqs');

            // Get publication term before the loop
            $publication = optipub_get_publication_data($publication_id);

            if (!$publication) {
                $this->logError("\nFailed loading faqs: Publication not found\n");

                return;
            }

            $post_parent_id = $this->retrievePostParent($publication_id);

            foreach ($jsonResponse->data as $faq) {
                $question = wp_strip_all_tags($faq->question);
                $answer = wp_strip_all_tags($faq->answer);
                $postDateGMT = gmdate('Y-m-d H:i:s', strtotime($faq->created_at));
                $postDate = get_date_from_gmt($postDateGMT);
                $postDateModifiedGMT = gmdate('Y-m-d H:i:s', strtotime($faq->updated_at));
                $postDateModified = get_date_from_gmt($postDateModifiedGMT);

                $categoryIDs = $this->populateCategories($faq);

                $faqPost = [
                    'post_title'            => $question,
                    'post_name'             => sanitize_title($question),
                    'post_parent'           => $post_parent_id,
                    'post_excerpt'          => $question,
                    'post_status'           => 'publish',
                    'post_content_filtered' => $answer,
                    'post_content'          => $answer ? $this->updateMacro($answer) : '',
                    'post_date'             => $postDate,
                    'post_date_gmt'         => $postDateGMT,
                    'post_category'         => !empty($categoryIDs) ? $categoryIDs : [-1],
                    'menu_order'            => $faq->rank ?? 0,
                    'post_type'             => 'faqs',
                ];

                $existingFaqID = $this->wpDbQuery->get_post_id_by_meta_key_and_value(
                    'optipub_page_id',
                    $faq->id,
                    'faqs',
                    $post_parent_id
                );

                if ($existingFaqID) {
                    $faqPost['ID'] = $existingFaqID;
                    $postId = wp_update_post($faqPost, true);
                } else {
                    $postId = wp_insert_post($faqPost, true);
                }

                if ($postId && !is_wp_error($postId)) {
                    $tagIDs = $this->populateTags($faq);

                    // Validate tagIDs to prevent wp_set_post_terms from auto-creating tags
                    $validated_tagIDs = $this->validateTagIDs($tagIDs);

                    wp_set_post_terms($postId, $validated_tagIDs, 'post_tag', false);

                    $this->updateFaqMetadata($publication_id, $postId, $faq);
                    $this->wpDbQuery->updatePostModifiedDate($postId, $postDateModified, $postDateModifiedGMT);

                    // Ensure publication assignment even for updates
                    update_post_meta($postId, 'publication_id', $publication_id);
                    wp_set_object_terms($postId, $publication->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
                }

                if (is_wp_error($postId)) {
                    $this->logError("Failed loading faq post: \n" . $postId->get_error_message() . "\n");
                }
            }

            $this->logDoneStatus();
        }
    }

    public function loadResources(int $publication_id, string $post_types = '', string $op_id = '')
    {
        $this->ensureRewriteExists();

        $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA);
        $specificPostTypeId = null;

        if ($post_types && isset($optiPubMetadata[$post_types])) {
            $specificPostTypeId = $optiPubMetadata[$post_types]['id'];
        }

        // Get all categories from API (both parent and child)
        $allCategories = $this->optiPubApi->getResourceCategories([
            'id',
            'name',
            'parent_category',
        ]);

        if (!$allCategories || !isset($allCategories->data)) {
            $this->logError("Failed to get categories from API for publication {$publication_id}");

            return;
        }

        $categoriesToProcess = [];

        // If specific post_types is requested, filter categories
        if ($specificPostTypeId) {
            $categoriesToProcess[] = [
                'id'        => $specificPostTypeId,
                'post_type' => $post_types,
                'name'      => $optiPubMetadata[$post_types]['cptPluralName'],
            ];
        } else {
            // Process all categories
            foreach ($allCategories->data as $category) {
                $categoryId = $category->id;

                // Check if this is a parent category (exists in metadata)
                $isParentCategory = false;
                $postTypeInfo = null;

                if ($optiPubMetadata) {
                    foreach ($optiPubMetadata as $key => $val) {
                        if ($val['id'] == $categoryId) {
                            $isParentCategory = true;
                            $postTypeInfo = [
                                'id'        => $categoryId,
                                'post_type' => $key,
                                'name'      => $val['cptPluralName'],
                            ];
                            break;
                        }
                    }
                }

                if ($isParentCategory) {
                    $categoriesToProcess[] = $postTypeInfo;
                } else {
                    // This is a child category, find its parent post type
                    $parentPostType = $this->getParentPostTypeFromChildCategory($categoryId);

                    if ($parentPostType && $parentPostType !== 'unknown') {
                        $categoriesToProcess[] = [
                            'id'        => $categoryId,
                            'post_type' => $parentPostType,
                            'name'      => $category->name . ' (' . $parentPostType . ')',
                        ];
                    }
                }
            }
        }

        // Process each category
        foreach ($categoriesToProcess as $categoryInfo) {
            $categoryId = $categoryInfo['id'];
            $post_type = $categoryInfo['post_type'];
            $categoryName = $categoryInfo['name'];

            if ($categoryId > 0) {
                // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Reason: Set time limit only for this specific long-running resource processing loop
                set_time_limit(0);

                if ($op_id) {
                    $maxPages = 1;
                } else {
                    $totalCountResponse = $this->optiPubApi->getAllResources($publication_id, $categoryId, 1, '', false, ['id'], null);

                    // Check if API response is valid
                    if (!$totalCountResponse || !isset($totalCountResponse->total)) {
                        $this->logError("API call failed to Load Resources - category {$categoryId} - Pub ID - {$publication_id}");
                        continue; // Skip this category and move to the next one
                    }

                    $totalCount = $totalCountResponse->total;
                    $maxPages = ceil($totalCount / self::PER_PAGE);
                }

                for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
                    $message = "Loading page {$pageNum} out of {$maxPages} for {$categoryName}";
                    $this->logProgress($message);

                    if ($op_id) {
                        $jsonResponse = $this->optiPubApi->getAllResources($publication_id, $categoryId, 1, 1, true, [], $op_id);
                    } else {
                        $jsonResponse = $this->optiPubApi->getAllResources($publication_id, $categoryId, self::PER_PAGE, $pageNum);
                    }

                    $post_parent_id = $this->retrievePostParent($publication_id);

                    foreach ($jsonResponse->data as $resource) {
                        $post_author = $resource->author->id;

                        if (empty(WpDatabaseQuery::getEditorForPost($resource->author->id))) {
                            $post_author = 0;
                        }

                        /** Set Created Date from OptiPub display_date */
                        if ($resource->display_date) {
                            $postDateGMT = gmdate('Y-m-d H:i:s', strtotime($resource->display_date));
                            $postDate = $postDateGMT; // Change this once OptiPub is setting the TZ for display_date correctly
                        } else {
                            $postDateGMT = gmdate('Y-m-d H:i:s', strtotime($resource->created_at));
                            $postDate = get_date_from_gmt($postDateGMT);
                        }

                        $postDateModifiedGMT = gmdate('Y-m-d H:i:s', strtotime($resource->updated_at));
                        $postDateModified = get_date_from_gmt($postDateModifiedGMT);

                        $categoryIDs = $this->populateCategories($resource);

                        // Always assign the resource's category as WordPress category
                        $resourceCategoryIDs = $this->assignResourceCategory($resource);

                        if (!empty($resourceCategoryIDs)) {
                            $categoryIDs = array_merge($categoryIDs, $resourceCategoryIDs);
                        }

                        $customPostType = [
                            'post_author'           => $post_author,
                            'post_parent'           => $post_parent_id,
                            'post_title'            => $resource->title,
                            'post_name'             => !empty($resource->url_slug) ? $resource->url_slug : sanitize_title($resource->title),
                            'post_excerpt'          => $resource->description ?? $resource->meta_description ?? '',
                            'post_status'           => $resource->featured ? 'featured' : 'publish',
                            'post_content_filtered' => $resource->content,
                            'post_content'          => $this->updateMacro($resource->content),
                            'post_category'         => !empty($categoryIDs) ? $categoryIDs : [-1],
                            'post_date'             => $postDate,
                            'post_date_gmt'         => $postDateGMT,
                            'menu_order'            => $resource->rank ?? 0,
                            'post_type'             => $post_type,
                        ];

                        $existingResourceID = $this->wpDbQuery->get_post_id_by_meta_key_and_value(
                            'optipub_page_id',
                            $resource->id,
                            $post_type,
                            $post_parent_id
                        );

                        if ($existingResourceID) {
                            $customPostType['ID'] = $existingResourceID;
                            $postId = wp_update_post($customPostType, true);
                        } else {
                            $postId = wp_insert_post($customPostType, true);
                        }

                        if ($postId && !is_wp_error($postId)) {
                            $tagIDs = $this->populateTags($resource);
                            $validated_tagIDs = $this->validateTagIDs($tagIDs);
                            wp_set_post_terms($postId, $validated_tagIDs, 'post_tag', false);

                            $this->updateResourceMetadata($publication_id, $postId, $resource);
                            $this->wpDbQuery->updatePostModifiedDate($postId, $postDateModified, $postDateModifiedGMT);

                            $publication = optipub_get_publication_data($publication_id);

                            if ($publication) {
                                wp_set_object_terms($postId, $publication->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
                            }
                        }

                        if (is_wp_error($postId)) {
                            $this->logError("\nFailed loading resource: \n" . $postId->get_error_message() . ' - OP ID: ' . $resource->id . ' - ' . $resource->title . "\n");
                        }
                    }

                    $this->logDoneStatus();
                }
            }
        }
    }

    public function loadMacros()
    {
        $totalCountResponse = $this->optiPubApi->getAllCustomContent(1, 1);
        $totalCount = $totalCountResponse->total;
        $maxPages = ceil($totalCount / self::PER_PAGE);

        for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
            $jsonResponse = $this->optiPubApi->getAllCustomContent(self::PER_PAGE, $pageNum);

            if (count($jsonResponse->data) > 0) {
                $message = "Loading page {$pageNum} out of {$maxPages} for Macros";
                $this->logProgress($message);

                foreach ($jsonResponse->data as $object) {
                    $macro = '{' . $object->macro . '}';
                    // Prefix option name to avoid conflicts with WordPress and other plugins
                    $option_name = 'optipub_macro_' . $macro;
                    update_option($option_name, $object->content, false);

                    if (get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
                        $this->updateNewMacro($option_name);
                    }
                }
            }
            $this->logDoneStatus();
        }
    }

    public function updateNewMacro($macro)
    {
        global $wpdb;

        // Create array of post IDs containing the custom content macro
        $sql = "SELECT id FROM {$wpdb->posts} WHERE post_content_filtered LIKE %s";
        $args = ['%' . $wpdb->esc_like($macro) . '%'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $ids[] = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        $arrLength = count($ids[0]);

        // Loop through array of IDs
        for ($i = 0; $i < $arrLength; ++$i) {
            // Get post that contains macro
            $id = $ids[0][$i]['id'];
            $post = get_post($id);
            $content = $post->post_content_filtered;

            // Match all macros in post_content_filtered
            $content = $this->updateMacro($content);
            // Replace post_content with converted macros
            $post->post_content = $content;
            $postUpdate = wp_update_post($post);

            if (is_wp_error($postUpdate)) {
                $this->logError("\nFailed updating macros: \n" . $postUpdate->get_error_message());
            } else {
                $message = 'Updating macros on post id ' . $id;
                $this->logProgress($message);
            }
            $this->logDoneStatus();
        }
    }

    public function deleteMacros()
    {
        global $wpdb;

        /**
         * THIS FUNCTION IS NOT WORKING RIGHT NOW
         * WHEN OPTIPUB CUSTOM CONTENT IS ADDED / UPDATED / REMOVED
         * THIS SYNC WILL BE OFF BY 1.
         *
         * RETURNING WITHOUT RUNNING FOR NOW
         */
        return;
        // Query to get synced macros
        $sql = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s";
        $args = [$wpdb->esc_like('{$custom_') . '%'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $syncedMacros = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

        $totalCountResponse = $this->optiPubApi->getAllCustomContent(1, 1, true, ['id']);

        if (!$totalCountResponse || !isset($totalCountResponse->total)) {
            $this->logError("API call failed to Delete Macros - Pub ID - {$publication_id}");

            return;
        }

        $totalCount = $totalCountResponse->total;
        $maxPages = ceil($totalCount / self::PER_PAGE);

        $optipubMacros = [];

        for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
            $macrosJsonResponse = $this->optiPubApi->getAllCustomContent(self::PER_PAGE, $pageNum, true);

            foreach ($macrosJsonResponse->data as $opMacro) {
                $optipubMacros[$opMacro->id] = $opMacro->macro;
            }
        }

        if (!empty($syncedMacros)) {
            $arrLength = count($syncedMacros);

            // Loop through array of synced macros
            // Delete if not found in optipub macros
            for ($i = 0; $i < $arrLength; ++$i) {
                $theMacro = $syncedMacros[$i]['option_name'];
                $myMacro = substr($theMacro, 1, -1);

                if (false === array_search($myMacro, $optipubMacros)) {
                    /**
                     * Commenting out delete action because OptiPub macros arent loading correctly
                     * when custom content is created or deleted in OptiPub
                     *
                     * delete_option($theMacro);
                     * $this->updateNewMacro($theMacro);
                     */

                    $this->logProgress("Dry Run - Deleted {$theMacro}");
                    $this->logDoneStatus();
                }
            }
        }
    }

    public function deleteDatas($post_types = 'page', int $publication_id, bool $remove_all = false): void
    {
        $ids = [];
        $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA);
        $postPerPage = self::PER_PAGE;

        $AlldataResponse = null;
        $resourceIdsFetched = false;

        try {
            if (isset($optiPubMetadata[$post_types]['id']) && (int) $optiPubMetadata[$post_types]['id'] >= 0) {
                $ids = $this->fetchAllResourceIdsForPostType($publication_id, $post_types);
                $this->logProgress('Fetched ' . count($ids) . " IDs for {$post_types} - Pub ID - {$publication_id}");
                $resourceIdsFetched = true;
            } elseif ('editors' == $post_types) {
                $AlldataResponse = $this->optiPubApi->getAuthors($publication_id, 1, '', '', true, ['id', 'publication_id']);
            } elseif ('videos' == $post_types) {
                $AlldataResponse = $this->optiPubApi->getVideos($publication_id, 1, '', '', true, ['id', 'publication_id']);
            } elseif ('faqs' == $post_types) {
                $AlldataResponse = $this->optiPubApi->getFaqs($publication_id, 1, '', '', true, ['id', 'publication_id']);
            } elseif ('page' == $post_types) {
                $AlldataResponse = $this->optiPubApi->getPages($publication_id, 1, '', '', true, ['id', 'publication_id']);
            }
        } catch (Exception $e) {
            $this->logError(
                "API call failed for Delete - {$post_types} - Pub ID - {$publication_id}: " . $e->getMessage()
            );

            return;
        }

        if ($resourceIdsFetched) {
            $this->runCleanupExistingDatas($post_types, $publication_id, $ids, $remove_all);

            return;
        }

        if ($AlldataResponse === null) {
            return;
        }

        if (!empty($AlldataResponse)) {
            if (!isset($AlldataResponse->total)) {
                $this->logError(
                    "API call failed for Delete - {$post_types} - Pub ID - {$publication_id}: response missing 'total'"
                );

                return;
            }

            $totalCount = (int) $AlldataResponse->total;
            $maxPages = ceil($totalCount / $postPerPage);

            // Get all IDs from API
            for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
                $AlldataArray = $this->getDataArrayByPostType($publication_id, $post_types, $optiPubMetadata, $postPerPage, $pageNum);

                if (!empty($AlldataArray->data)) {
                    foreach ($AlldataArray->data as $response) {
                        $ids[] = $response->id;
                    }
                }
            }

            $this->logProgress("Fetched {$totalCount} IDs for {$post_types} - Pub ID - {$publication_id}");

            $this->runCleanupExistingDatas($post_types, $publication_id, $ids, $remove_all);
        }
    }

    /**
     * Query existing OptiPub posts in WP and delete those whose optipub_page_id is not in $ids.
     */
    private function runCleanupExistingDatas(string $post_types, int $publication_id, array $ids, bool $remove_all): void
    {
        $args = [
            'post_type'      => $post_types,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'optipub_page_id',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => 'publication_id',
                    'value'   => $publication_id,
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_optipub_subdirectory',
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                    [
                        'key'     => '_optipub_subdirectory',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                [
                    'key'     => '_optipub_plugin_page',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $query = new WP_Query($args);
        $existing_datas = [];

        foreach ($query->posts as $post_id) {
            $existing_datas[] = [
                'wp_id' => $post_id,
                'op_id' => get_post_meta($post_id, 'optipub_page_id', true),
            ];
        }

        if (!$remove_all) {
            if (!empty($existing_datas) && empty($ids)) {
                $this->logProgress("No IDs found for {$post_types} - Pub ID - {$publication_id}");
                $this->logDoneStatus();

                return;
            }
        }

        if (!empty($existing_datas) || $remove_all) {
            foreach ($existing_datas as $existing_page) {
                if ('page' == $post_types) {
                    $child_pages = get_pages([
                        'child_of' => $existing_page['wp_id'],
                    ]);

                    if (count($child_pages) > 0) {
                        continue;
                    }
                }

                if ($remove_all) {
                    $ids = [];
                }

                if (!in_array($existing_page['op_id'], $ids)) {
                    $page_id = $existing_page['wp_id'];
                    $title = get_the_title($page_id);

                    wp_delete_post($page_id, true);
                    $this->logProgress("Deleted {$title} {$post_types}");
                    $this->logDoneStatus();
                }
            }
        }
    }

    /**
     * Get primary publication ID
     */
    private function getPrimaryPublicationId(): int
    {
        if ($this->primary_publication_id !== null) {
            return $this->primary_publication_id;
        }
        $this->primary_publication_id = optipub_get_primary_publication_id();

        return $this->primary_publication_id;
    }

    private function ensureRewriteExists()
    {
        global $wp_rewrite;

        if (null === $wp_rewrite) {
            $wp_rewrite = new WP_Rewrite();
        }
    }

    private function retrievePostParent(int $publication_id): int
    {
        // Log the publication ID being processed
        $this->logProgress("Retrieving post parent for publication ID: {$publication_id}");

        if ($publication_id == get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID)) {
            $this->logProgress('Publication is primary, returning 0 as parent');

            return 0;
        }

        // Get the publication slug page ID
        $publication_slug_page_id = $this->pageFactory->getPublicationSlugPageId($publication_id);

        if ($publication_slug_page_id) {
            $this->logProgress("Using publication slug page ID as parent: {$publication_slug_page_id}");

            return $publication_slug_page_id;
        }

        $this->logProgress('No publication slug page found, returning 0 as parent');

        return 0;
    }

    /**
     * Assign WordPress category ID based on resource's category
     */
    private function assignResourceCategory($resource): array
    {
        $categoryIDs = [];

        if (!isset($resource->category) || !isset($resource->category->id)) {
            return $categoryIDs;
        }

        $resourceCategoryId = $resource->category->id;

        // Find WordPress category that matches this OptiPub category ID
        $terms = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'optipub_category_id',
                    'value'   => $resourceCategoryId,
                    'compare' => '=',
                ],
            ],
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            $categoryIDs[] = $terms[0]->term_id;
        }

        return $categoryIDs;
    }

    /**
     * Get parent post type slug from child category ID
     */
    private function getParentPostTypeFromChildCategory($categoryId): ?string
    {
        // Query WordPress categories that have optipub_category_id meta matching the given categoryId
        $terms = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'optipub_category_id',
                    'value'   => $categoryId,
                    'compare' => '=',
                ],
            ],
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            $term = $terms[0];
            $parent_post_type = get_term_meta($term->term_id, 'optipub_parent_post_type', true);

            if (!empty($parent_post_type)) {
                return $parent_post_type;
            }
        }

        return null;
    }

    private function getCustomPostTypeSlugByOPId($post_type_id)
    {
        $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA);

        if ($optiPubMetadata) {
            foreach ($optiPubMetadata as $key => $val) {
                if ($val['id'] == $post_type_id) {
                    return $key;
                }
            }
        }

        // If not found in metadata, check if it's a child category
        $parentPostType = $this->getParentPostTypeFromChildCategory($post_type_id);

        if ($parentPostType) {
            return $parentPostType;
        }

        return 'unknown';
    }

    private function populateCategories($resource): array
    {
        $categoryIDs = [];

        // Only proceed if tags should be synced as categories
        if (get_option(Constants::OPTIPUB_TAGS_AS_CATEGORIES) !== '1') {
            return $categoryIDs;
        }

        if (isset($resource->tags) && !empty($resource->tags)) {
            foreach ($resource->tags as $tag) {
                // Use original tag name to avoid mutation issues
                $tag_name = $tag->name;

                $existingCategory = term_exists($tag_name, 'category');
                $categoryID = is_array($existingCategory) ? (int) $existingCategory['term_id'] : null;
                $category_created_by = $categoryID ? get_term_meta($categoryID, 'created_by', true) : '';

                /** Deduplicate if existing categories not created by optipub */
                if ('optipub-plugin' !== $category_created_by) {
                    if (!is_null($categoryID)) {
                        $x = 1;
                        $duplicate = true;

                        while ($duplicate) {
                            $category_dup = $tag_name . ' ' . $x;
                            $existingDuplicateCategory = term_exists($category_dup, 'category');
                            $duplicateCategoryID = is_array($existingDuplicateCategory) ? (int) $existingDuplicateCategory['term_id'] : null;
                            $category_dup_created_by = $duplicateCategoryID ? get_term_meta($duplicateCategoryID, 'created_by', true) : '';

                            if ($duplicateCategoryID && 'optipub-plugin' !== $category_dup_created_by) {
                                ++$x;
                            } else {
                                $tag_name = $category_dup;
                                $duplicate = false;
                            }
                        }
                    }

                    $categoryData = wp_insert_term(
                        $tag_name,
                        'category',
                        [
                            'slug' => sanitize_title($tag_name),
                        ]
                    );

                    if (is_wp_error($categoryData)) {
                        $this->logError("\nFailed updating category: " . $tag_name . "\n" . $categoryData->get_error_message());
                        $categoryID = $categoryData;
                    } else {
                        $categoryID = (int) $categoryData['term_id'];
                        update_term_meta($categoryID, 'created_by', 'optipub-plugin');
                    }
                }
                $categoryIDs[] = $categoryID;
            }
        }

        return $categoryIDs;
    }

    private function populateTags($resource): array
    {
        $tagIDs = [];

        // Only proceed if tags should be synced as tags
        if (get_option(Constants::OPTIPUB_TAGS_AS_TAGS) !== '1') {
            return $tagIDs;
        }

        if (isset($resource->tags) && !empty($resource->tags)) {
            foreach ($resource->tags as $tag) {
                $tag_name = $tag->name;

                $tagID = term_exists($tag_name, 'post_tag');
                $tag_created_by = get_term_meta($tagID['term_id'] ?? 0, 'created_by', true);

                // Only reuse tag if it was created by optipub
                if ($tagID && 'optipub-plugin' === $tag_created_by) {
                    $tagIDs[] = (int) $tagID['term_id'];
                } else {
                    $need_to_create_tag = true;

                    // If tag exists but wasn't created by optipub, create a new one with incremented name
                    if ($tagID) {
                        $x = 1;
                        $duplicate = true;

                        while ($duplicate) {
                            $tag_dup = $tag_name . ' ' . $x;
                            $existing_tag = term_exists($tag_dup, 'post_tag');

                            if ($existing_tag) {
                                $tag_dup_created_by = get_term_meta($existing_tag['term_id'], 'created_by', true);

                                if ('optipub-plugin' !== $tag_dup_created_by) {
                                    // Tag exists but not created by us, try next number
                                    ++$x;
                                } else {
                                    // Tag exists and was created by us, reuse it
                                    $tagIDs[] = (int) $existing_tag['term_id'];
                                    $need_to_create_tag = false;
                                    $duplicate = false;
                                }
                            } else {
                                // Tag doesn't exist, we can create it
                                $tag_name = $tag_dup;
                                $duplicate = false;
                            }
                        }
                    }

                    // Create new tag only if we need to
                    if ($need_to_create_tag) {
                        $tag_data = wp_insert_term(
                            $tag_name,
                            'post_tag',
                            [
                                'slug' => sanitize_title($tag_name),
                            ]
                        );

                        if (is_wp_error($tag_data)) {
                            $this->logError("\nFailed updating tag: " . $tag_name . "\n" . $tag_data->get_error_message());
                        } else {
                            update_term_meta($tag_data['term_id'], 'created_by', 'optipub-plugin');
                            $tagIDs[] = (int) $tag_data['term_id'];
                        }
                    }
                }
            }
        }

        return $tagIDs;
    }

    /**
     * Validate tag IDs to prevent wp_set_post_terms from auto-creating unwanted tags
     *
     * @param array $tagIDs Array of tag IDs to validate
     * @return array Array of valid tag IDs
     */
    private function validateTagIDs($tagIDs): array
    {
        $validated = [];

        foreach ($tagIDs as $tagID) {
            // Ensure it's an integer
            $tagID = (int) $tagID;

            // Skip if not a valid positive integer
            if ($tagID <= 0) {
                continue;
            }

            // Verify that the term actually exists
            $term = get_term($tagID, 'post_tag');

            if (is_wp_error($term) || !$term) {
                continue;
            }

            $validated[] = $tagID;
        }

        return $validated;
    }

    private function checkRanks($publication_id, $post_types = ''): void
    {
        $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA);
        $postPerPage = self::PER_PAGE;

        if ('reports' == $post_types) {
            $categoryId = $optiPubMetadata[$post_types]['id'];
            $AlldataResponse = $this->optiPubApi->getAllResources($publication_id, $categoryId, 1, '', true, ['id']);
        } elseif ('editors' == $post_types) {
            $AlldataResponse = $this->optiPubApi->getAuthors($publication_id, 1, '', '', true, ['id']);
        } elseif ('videos' == $post_types) {
            $AlldataResponse = $this->optiPubApi->getVideos($publication_id, 1, '', '', true, ['id']);
        } elseif ('faqs' == $post_types) {
            $AlldataResponse = $this->optiPubApi->getFaqs($publication_id, 1, '', '', true, ['id']);
        }

        if (!empty($AlldataResponse)) {
            $totalCount = $AlldataResponse->total;
            $maxPages = ceil($totalCount / $postPerPage);

            for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
                if ('reports' == $post_types) {
                    $categoryId = $optiPubMetadata[$post_types]['id'];
                    $AlldataArray = $this->optiPubApi->getAllResources($publication_id, $categoryId, $postPerPage, $pageNum, true, ['id', 'rank']);
                } elseif ('editors' == $post_types) {
                    $AlldataArray = $this->optiPubApi->getAuthors($publication_id, $postPerPage, '', '', true, ['id', 'rank'], '', $pageNum);
                } elseif ('videos' == $post_types) {
                    $AlldataArray = $this->optiPubApi->getVideos($publication_id, $postPerPage, '', '', true, ['id', 'rank'], '', $pageNum);
                } elseif ('faqs' == $post_types) {
                    $AlldataArray = $this->optiPubApi->getFaqs($publication_id, $postPerPage, '', '', true, ['id', 'rank'], '', $pageNum);
                }

                if (!empty($AlldataArray->data)) {
                    global $wpdb;

                    $sql = "SELECT p.ID, p.menu_order, pm.meta_value AS op_id 
                         FROM {$wpdb->posts} p 
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                         WHERE pm.meta_key = 'optipub_page_id' 
                         AND p.post_type = %s";
                    $args = [$post_types];

                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
                    $results = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

                    foreach ($AlldataArray->data as $response) {
                        $id = $response->id;
                        $rank = $response->rank ? $response->rank : 0;

                        foreach ($results as $row) {
                            if ($row->op_id == $id && $row->menu_order != $rank) {
                                if ('reports' == $post_types) {
                                    $categoryId = $optiPubMetadata[$post_types]['id'];
                                    $this->loadResources($publication_id, $post_types, $row->op_id);
                                } elseif ('editors' == $post_types) {
                                    $this->loadEditors($publication_id);
                                } elseif ('videos' == $post_types) {
                                    $this->loadVideos($publication_id);
                                } elseif ('faqs' == $post_types) {
                                    $this->loadFaqs($publication_id);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function updateVideoMetadata($publication_id, $postId, $video)
    {
        Helper::updateSeoMetadata($video, $postId);

        update_post_meta($postId, 'publication_id', $publication_id);

        if ((isset($video->player) && $video->player) && (isset($video->player_data) && $video->player_data)) {
            update_post_meta($postId, 'video_player', [$video->player, $video->player_data]);
        }

        if (isset($video->bottom_blurb) && $video->bottom_blurb) {
            update_post_meta($postId, 'video_bottom_blurb', $video->bottom_blurb);
        }

        if (isset($video->top_blurb) && $video->top_blurb) {
            update_post_meta($postId, 'video_top_blurb', $video->top_blurb);
        }

        if (isset($video->thumbnail_image->path) && $video->thumbnail_image->path) {
            update_post_meta($postId, 'video_thumbnail_path', $video->thumbnail_image->path);
            update_post_meta($postId, 'resource_thumbnail_path', $video->thumbnail_image->path);
            $this->createAttachmentFromUrl($video->thumbnail_image->path, $postId, $video->title);
        } else {
            delete_post_meta($postId, 'video_thumbnail_path');
            delete_post_meta($postId, 'resource_thumbnail_path');
        }

        if (isset($video->rank)) {
            update_post_meta($postId, 'resource_rank', $video->rank);
        }
    }

    private function updateFaqMetadata($publication_id, $postId, $faq)
    {
        Helper::updateSeoMetadata($faq, $postId);

        update_post_meta($postId, 'publication_id', $publication_id);

        if (isset($faq->rank)) {
            update_post_meta($postId, 'resource_rank', $faq->rank);
        }
    }

    private function updateResourceMetadata($publication_id, $postId, $resource)
    {
        Helper::updateSeoMetadata($resource, $postId);

        update_post_meta($postId, 'publication_id', $publication_id);
        update_post_meta($postId, 'resource_featured', $resource->featured);

        /**
         * Stored as '1'/'0' rather than the raw boolean: WordPress casts false
         * to an empty string, which a theme cannot tell apart from a post that
         * was synced before this flag existed.
         */
        if (isset($resource->show_date)) {
            update_post_meta($postId, 'resource_show_date', $resource->show_date ? '1' : '0');
        }

        update_post_meta($postId, 'resource_editor', $resource->author->display_name);
        update_post_meta($postId, 'resource_editor_id', $resource->author->id);
        update_post_meta($postId, 'editor_show_on_website', $resource->author->show_on_website);

        if (isset($resource->subtitle) && !empty($resource->subtitle)) {
            update_post_meta($postId, 'resource_subtitle', $resource->subtitle);
        }

        if (isset($resource->thumbnail->path) && !empty($resource->thumbnail->path)) {
            update_post_meta($postId, 'resource_thumbnail_path', $resource->thumbnail->path);
            $this->createAttachmentFromUrl($resource->thumbnail->path, $postId, $resource->title);
        } else {
            delete_post_meta($postId, 'resource_thumbnail_path');
        }

        /** Add Display Date to Post Meta - not needed because this is used for post_date */
        if (isset($resource->display_date) && !empty($resource->display_date)) {
            $displayDateGMT = gmdate('Y-m-d H:i:s', strtotime($resource->display_date));
            /**
             * Change this once OptiPub is setting the TZ for display_date correctly
             * $displayDate = get_date_from_gmt($displayDateGMT);
             */
            $displayDate = $displayDateGMT;
            update_post_meta($postId, 'resource_display_date_gmt', $displayDateGMT);
            update_post_meta($postId, 'resource_display_date', $displayDate);
        }

        /** Add Created Date to Post Meta */
        if (isset($resource->created_at) && !empty($resource->created_at)) {
            $createdDateGMT = gmdate('Y-m-d H:i:s', strtotime($resource->created_at));
            $createdDate = get_date_from_gmt($createdDateGMT);
            update_post_meta($postId, 'resource_created_date_gmt', $createdDateGMT);
            update_post_meta($postId, 'resource_created_date', $createdDate);
        }

        if (isset($resource->pdf->path) && !empty($resource->pdf->path)) {
            update_post_meta($postId, 'resource_pdf_path', $resource->pdf->path);
        } else {
            delete_post_meta($postId, 'resource_pdf_path');
        }

        if (isset($resource->rank)) {
            update_post_meta($postId, 'resource_rank', $resource->rank);
        }
    }

    private function updateEditorMetadata($publication_id, $postId, $author)
    {
        Helper::updateSeoMetadata($author, $postId);

        update_post_meta($postId, 'publication_id', $publication_id);

        if (isset($author->website_image) && $author->website_image->path) {
            update_post_meta($postId, 'editor_website_image_id', $author->website_image->id);
            update_post_meta($postId, 'editor_website_image_path', $author->website_image->path);
            update_post_meta($postId, 'resource_thumbnail_path', $author->website_image->path);
        } else {
            delete_post_meta($postId, 'editor_website_image_id');
            delete_post_meta($postId, 'editor_website_image_path');
            delete_post_meta($postId, 'resource_thumbnail_path');
        }

        if (isset($author->rank)) {
            update_post_meta($postId, 'resource_rank', $author->rank);
        }

        update_post_meta($postId, 'editor_title', $author->title);
        update_post_meta($postId, 'editor_show_on_website', $author->show_on_website);

        // Groups
        if (isset($author->group) && !empty($author->group)) {
            update_post_meta($postId, 'editor_group_id', $author->group->id);
            update_post_meta($postId, 'editor_group_name', $author->group->name);
        }

        // Socials
        if (isset($author->user->socials) && !empty($author->user->socials)) {
            update_post_meta($postId, 'editor_linkedin_url', $author->user->socials->linkedin);
            update_post_meta($postId, 'editor_twitter_url', $author->user->socials->twitter);
            update_post_meta($postId, 'editor_facebook_url', $author->user->socials->facebook);
            update_post_meta($postId, 'editor_instagram_url', $author->user->socials->instagram);
            update_post_meta($postId, 'editor_youtube_url', $author->user->socials->youtube);
            update_post_meta($postId, 'editor_reddit_url', $author->user->socials->reddit);
            update_post_meta($postId, 'editor_tiktok_url', $author->user->socials->tiktok);
            update_post_meta($postId, 'editor_rumble_url', $author->user->socials->rumble);
            update_post_meta($postId, 'editor_truth_social_url', $author->user->socials->truth_social);
            update_post_meta($postId, 'editor_threads_url', $author->user->socials->threads);
        } else {
            delete_post_meta($postId, 'editor_linkedin_url');
            delete_post_meta($postId, 'editor_twitter_url');
            delete_post_meta($postId, 'editor_facebook_url');
            delete_post_meta($postId, 'editor_instagram_url');
            delete_post_meta($postId, 'editor_youtube_url');
            delete_post_meta($postId, 'editor_reddit_url');
            delete_post_meta($postId, 'editor_tiktok_url');
            delete_post_meta($postId, 'editor_rumble_url');
            delete_post_meta($postId, 'editor_truth_social_url');
            delete_post_meta($postId, 'editor_threads_url');
        }
    }

    /**
     * Get all resource category IDs for a post type (parent + child categories).
     * Used so cleanup fetches IDs from the same categories that loadResources uses.
     */
    private function getResourceCategoryIdsForPostType(string $post_types): array
    {
        $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA, []);

        if (!isset($optiPubMetadata[$post_types]['id']) || (int) $optiPubMetadata[$post_types]['id'] < 0) {
            return [];
        }

        $parentId = (int) $optiPubMetadata[$post_types]['id'];
        $categoryIds = [$parentId];

        $allCategories = $this->optiPubApi->getResourceCategories(['id', 'parent_category']);

        if (empty($allCategories->data)) {
            return $categoryIds;
        }

        foreach ($allCategories->data as $category) {
            if (isset($category->parent_category->id) && (int) $category->parent_category->id === $parentId) {
                $categoryIds[] = (int) $category->id;
            }
        }

        return $categoryIds;
    }

    /**
     * Fetch all resource IDs from API for a post type (all categories: parent + children).
     */
    private function fetchAllResourceIdsForPostType(int $publication_id, string $post_types): array
    {
        $ids = [];
        $categoryIds = $this->getResourceCategoryIdsForPostType($post_types);
        $postPerPage = self::PER_PAGE;

        foreach ($categoryIds as $categoryId) {
            $totalResponse = $this->optiPubApi->getAllResources($publication_id, $categoryId, 1, '', true, ['id']);

            if (empty($totalResponse) || !isset($totalResponse->total)) {
                continue;
            }
            $totalCount = (int) $totalResponse->total;
            $maxPages = (int) ceil($totalCount / $postPerPage);

            for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
                $pageResponse = $this->optiPubApi->getAllResources($publication_id, $categoryId, $postPerPage, $pageNum, true, ['id']);

                if (!empty($pageResponse->data)) {
                    foreach ($pageResponse->data as $response) {
                        $ids[] = $response->id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Helper method to get data array based on post type
     */
    private function getDataArrayByPostType(int $publication_id, string $post_types, array $optiPubMetadata, int $postPerPage, int $pageNum)
    {
        if (isset($optiPubMetadata[$post_types]['id']) && (int) $optiPubMetadata[$post_types]['id'] >= 0) {
            $categoryId = $optiPubMetadata[$post_types]['id'];

            return $this->optiPubApi->getAllResources($publication_id, $categoryId, $postPerPage, $pageNum, true, ['id']);
        }

        if ('editors' == $post_types) {
            return $this->optiPubApi->getAuthors($publication_id, $postPerPage, '', '', true, ['id'], '', $pageNum);
        }

        if ('videos' == $post_types) {
            return $this->optiPubApi->getVideos($publication_id, $postPerPage, '', '', true, ['id'], '', $pageNum);
        }

        if ('faqs' == $post_types) {
            return $this->optiPubApi->getFaqs($publication_id, $postPerPage, '', '', true, ['id'], '', $pageNum);
        }

        if ('page' == $post_types) {
            return $this->optiPubApi->getPages($publication_id, $postPerPage, '', '', true, ['id'], $pageNum);
        }
    }

    private function createAttachmentFromUrl(string $url, int $post_id, string $title = ''): ?int
    {
        if (empty($url)) {
            return null;
        }

        return null;
        // Check if attachment already exists for this post
        $existing_attachment = get_posts([
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'meta_key'       => 'optipub_original_url',
            'meta_value'     => $url,
            'posts_per_page' => 1,
        ]);

        if (!empty($existing_attachment)) {
            return $existing_attachment[0]->ID;
        }

        // Get the file
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            $this->logError("\nFailed to download image from URL: {$url}\n" . $response->get_error_message());

            return null;
        }

        $file_content = wp_remote_retrieve_body($response);

        if (empty($file_content)) {
            return null;
        }

        // Get file info
        $file_name = basename($url);
        $upload = wp_upload_bits($file_name, null, $file_content);

        if ($upload['error']) {
            $this->logError("\nFailed to upload image - " . $file_name . ' - on post ID: ' . $post_id . ' - ' . $upload['error']);

            return null;
        }

        // Create attachment post
        $wp_filetype = wp_check_filetype($file_name, null);

        $attachment_title = $title ?: preg_replace('/\.[^.]+$/', '', $file_name);

        if (empty(sanitize_title($attachment_title))) {
            $this->logError("\nFailed to upload image - " . $file_name . ' - on post ID: ' . $post_id . ' - attachment title is empty');

            return null;
        }

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => $attachment_title,
            'post_name'      => sanitize_title($attachment_title),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            $this->logError("\nFailed to create attachment: " . $attachment_id->get_error_message());

            return null;
        }

        // Store original URL to prevent duplicates
        update_post_meta($attachment_id, 'optipub_original_url', $url);
        update_post_meta($attachment_id, 'created_by', 'optipub-plugin');

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        // Set the same publication taxonomy as the parent post
        $post_terms = wp_get_object_terms($post_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

        if (!empty($post_terms) && !is_wp_error($post_terms)) {
            wp_set_object_terms($attachment_id, $post_terms[0]->term_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);
        }

        return $attachment_id;
    }
}
