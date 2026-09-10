<?php

namespace OptiPub\Base;

class RedirectsLoader extends BaseController
{
    public const PER_PAGE = 1000;

    public $optiPubApi;

    private $table_name;

    public function __construct()
    {
        parent::__construct();
        $this->optiPubApi = parent::createOptiPubApiInstance();
    }

    public function loadRedirects($publication_id): void
    {
        if (empty($publication_id)) {
            $this->logStaticError('Failed to load redirects - No publication ID found');

            return;
        }

        // Get publication domain id
        $publication_domain_id = optipub_get_publication_data($publication_id, 'publication_domain_id');
        $publication_name = optipub_get_publication_data($publication_id, 'publication_name');

        if (!$publication_domain_id) {
            $this->logStaticError('Loading Redirects - No domain ID for publication - ' . $publication_name . ' - Update Publication in OptiPub');

            return;
        }

        global $wpdb;

        $totalCountResponse = $this->optiPubApi->getRedirects($publication_id, 1, 1);

        if (!$totalCountResponse) {
            $this->logError('Failed to get redirects data from API');

            return;
        }

        $totalCount = $totalCountResponse->total;
        $maxPages = ceil($totalCount / self::PER_PAGE);

        if (!empty($totalCount)) {
            $this->logProgress('Loading Redirects');

            for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
                $redirectsJsonResponse = $this->optiPubApi->getRedirects($publication_id, self::PER_PAGE, $pageNum);

                if (!$redirectsJsonResponse || empty($redirectsJsonResponse->data)) {
                    continue;
                }

                foreach ($redirectsJsonResponse->data as $redirect) {
                    $redirectsEntry = [
                        'id'         => $redirect->id,
                        'domain_id'  => $redirect->domain->id,
                        'from_url'   => true === $redirect->regex ? $redirect->from : trim($redirect->from, '/'),
                        'to_url'     => ltrim($redirect->to, '/'),
                        'code'       => absint($redirect->code),
                        'created_at' => gmdate('Y-m-d H:i:s', strtotime($redirect->created_at)),
                        'updated_at' => gmdate('Y-m-d H:i:s', strtotime($redirect->updated_at)),
                        'regex'      => (int) $redirect->regex,
                    ];

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Need to use direct database query.
                    $result = $wpdb->replace(
                        $wpdb->prefix . 'optipub_redirects',
                        $redirectsEntry,
                        [
                            '%d', // id
                            '%d', // domain_id
                            '%s', // from_url
                            '%s', // to_url
                            '%d', // code
                            '%s', // created_at
                            '%s', // updated_at
                            '%d',  // regex boolean
                        ]
                    );

                    // Clear cache after database operation
                    wp_cache_delete('optipub_redirects_' . $redirect->id, 'optipub');
                    wp_cache_delete('optipub_redirects_domain_' . $redirectsEntry['domain_id'], 'optipub');

                    if (false === $result) {
                        $this->logError(sprintf(
                            'Failed to insert/update redirect ID: %d - Error: %s',
                            $redirect->id,
                            $wpdb->last_error
                        ));
                    }
                }
            }

            if (!empty($redirectsJsonResponse->data)) {
                $this->logDoneStatus();
            }
        }
    }

    public function deleteRedirects($publication_id, $remove_all = false): void
    {
        global $wpdb;

        if (empty($publication_id)) {
            $this->logStaticError('Failed to delete redirects - No publication ID found');

            return;
        }

        // Get publication domain id
        $publication_domain_id = optipub_get_publication_data($publication_id, 'publication_domain_id');
        $publication_name = optipub_get_publication_data($publication_id, 'publication_name');

        if (!$publication_domain_id) {
            $this->logError(
                'Failed to get publication domain id when removing redirects for publication: ' . $publication_name
            );

            return;
        }

        $totalCountResponse = $this->optiPubApi->getRedirects($publication_id, 1, 1, true);

        if (!$totalCountResponse || !isset($totalCountResponse->total)) {
            $this->logError('Failed to get redirects data from API for deletion on publication: ' . $publication_name);

            return;
        }

        $totalCount = $totalCountResponse->total;
        $maxPages = ceil($totalCount / self::PER_PAGE);

        $redirectsIds = [];

        for ($pageNum = 1; $pageNum <= $maxPages; ++$pageNum) {
            $redirectsJsonResponse = $this->optiPubApi->getRedirects($publication_id, self::PER_PAGE, $pageNum, true);

            if ($redirectsJsonResponse && !empty($redirectsJsonResponse->data)) {
                $redirectsIds = array_merge($redirectsIds, array_column($redirectsJsonResponse->data, 'id'));
            }
        }

        if (!empty($redirectsIds) || $remove_all) {
            if ($remove_all) {
                $redirectsIds = [];
            }

            if (!empty($redirectsIds)) {
                $placeholders = implode(',', array_fill(0, count($redirectsIds), '%d'));
                $values = array_merge($redirectsIds, [$publication_domain_id]);

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Need to use direct database query.
                $redirectsRemove = $wpdb->query(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: Is it prepared already.
                    $wpdb->prepare("DELETE FROM {$wpdb->prefix}optipub_redirects WHERE id NOT IN ({$placeholders}) AND domain_id = %d", $values)
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Need to use direct database query.
                $redirectsRemove = $wpdb->query(
                    $wpdb->prepare("DELETE FROM {$wpdb->prefix}optipub_redirects WHERE domain_id = %d", $publication_domain_id)
                );
            }

            wp_cache_delete('optipub_redirects_domain_' . $publication_domain_id, 'optipub');

            if ($redirectsRemove === false && $wpdb->last_error) {
                $this->logError('Failed to delete redirects: ' . $wpdb->last_error);
            } elseif ($redirectsRemove > 0) {
                $this->logProgress("Deleted {$redirectsRemove} redirects");
                $this->logDoneStatus();
            }
        }
    }
}
