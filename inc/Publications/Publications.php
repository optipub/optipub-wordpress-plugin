<?php

namespace OptiPubWP\Publications;

use OptiPub\Api\OptiPubApi;
use OptiPub\Base\Constants;
use OptiPub\Base\EncryptDecryptUtil;
use OptiPubWP\Log;
use WP_Error;

class Publications
{
    private static ?Log $log = null;

    private $api;

    private $plugin_path;

    private $subscription_service;

    private $users_service;

    private $login_control;

    private $subscribers_service;

    public function __construct()
    {
        $this->plugin_path = OPTIPUB_PLUGIN_DIR;
        $this->initializeServices();
    }

    public static function createOptiPubApiInstance(): OptiPubApi
    {
        $apiBaseUrl = get_option(Constants::OPTIPUB_BASE_API_URL);
        $apiToken = EncryptDecryptUtil::getDecryptedToken('api');
        $publicationId = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        return new OptiPubApi($apiBaseUrl, $apiToken, $publicationId);
    }

    /**
     * Register authentication hooks
     */
    public function register(): void
    {
        // TODO: Implement register() method.
    }

    public function optipub_get_publication_api($publication_id)
    {
        $publication = $this->api->getPublication($publication_id);

        if (!$publication || is_wp_error($publication)) {
            return new WP_Error('publication_not_found', 'Publication not found');
        }

        return $publication;
    }

    /**
     * Get publication term data by publication ID
     *
     * @param int    $publication_id The OptiPub publication ID
     * @param string $meta_key       Optional specific meta key to retrieve
     * @return mixed Term object with meta, specific meta value if key provided, or false if not found
     */
    public function optipub_get_publication_by_id($publication_id)
    {
        // get taxonomy
        $publication = get_term($publication_id, 'publication_id');

        if (!$publication || is_wp_error($publication)) {
            return new WP_Error('publication_not_found', 'Publication not found');
        }

        return $publication;
    }

    /**
     * Get publication term data by publication ID
     *
     * @param int    $publication_id The OptiPub publication ID
     * @param string $meta_key       Optional specific meta key to retrieve
     * @return mixed Term object with meta, specific meta value if key provided, or false if not found
     *
     * no meta key - return data is $term->name and $term->meta['meta_key'][0]
     * meta key - return data is $termmeta
     */
    public function optipub_get_publication_terms_by_pub_id($publication_id, string $meta_key = ''): mixed
    {
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_id',
                    'value'   => $publication_id,
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        $term = $terms[0];

        // If meta_key is specified, check if it's a standard term property
        if (!empty($meta_key)) {
            // Check if it's a standard term property
            if (property_exists($term, $meta_key)) {
                return $term->$meta_key;
            }

            // If not, get it from term meta
            return get_term_meta($term->term_id, $meta_key, true);
        }

        // Return full term object with all properties and meta
        $term->meta = get_term_meta($term->term_id);

        return $term;
    }

    public function optipub_get_publication_template_page_url(int $publication_id = 0, string $template_name = '')
    {
        if ($publication_id === 0) {
            $publication_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);
        }

        $publication = $this->optipub_get_publication_terms_by_pub_id($publication_id);

        if (!$publication || is_wp_error($publication)) {
            return new WP_Error('publication_not_found', 'Publication not found');
        }

        // If no template name provided, use subscribers template
        if (empty($template_name)) {
            $template_name = get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE);
        }

        if ($template_name) {
            // Query for pages using this template
            $pages = get_pages([
                'meta_key'    => '_wp_page_template',
                'meta_value'  => $template_name,
                'number'      => 1,
                'sort_order'  => 'ASC',
                'sort_column' => 'menu_order',
            ]);

            if (!empty($pages)) {
                return get_permalink($pages[0]->ID);
            }
        }

        return false;
    }

    /**
     * Get all publication terms
     *
     * @return array Array of terms with meta and publication_id as key
     */
    public function optipub_get_all_publication_terms()
    {
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }

        $all_terms = [];

        foreach ($terms as $term) {
            $term->meta = get_term_meta($term->term_id);
            $all_terms[$term->meta['publication_id'][0]] = $term;
        }

        return $all_terms;
    }

    private function initializeServices(): void
    {
        $this->api = self::createOptiPubApiInstance();
    }
}
