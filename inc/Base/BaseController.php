<?php

namespace OptiPub\Base;

use OptiPub\Api\OptiPubApi;

class BaseController
{
    public const MAX_LOG_LINES = 2000;

    public $plugin_path;

    public $plugin_url;

    public $plugin;

    public function __construct()
    {
        $this->plugin_path = OPTIPUB_PLUGIN_DIR;
        $this->plugin_url = OPTIPUB_PLUGIN_URL;
        $this->plugin = plugin_basename(OPTIPUB_PLUGIN_FILE);
    }

    public static function createOptiPubApiInstance(): OptiPubApi
    {
        $apiBaseUrl = get_option(Constants::OPTIPUB_BASE_API_URL);
        $apiToken = EncryptDecryptUtil::getDecryptedToken('api');
        $publicationId = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        return new OptiPubApi($apiBaseUrl, $apiToken, $publicationId);
    }

    public function createAdminWidgetsTable()
    {
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }
        $OPListTable = new AdminWidgetsTable();
        $OPListTable->process_bulk_action();
        $OPListTable->prepare_items();
    }

    public function logProgress($message)
    {
        // Ensure message is on a single line and trim it
        $message = trim(preg_replace('/[\s\r\n]+/', ' ', $message));

        $maxChars = 80;
        $length = strlen($message);

        while ($length <= $maxChars) {
            $message .= '.';
            ++$length;
        }

        $this->addLogEntry($message, true);
    }

    public function logMessage($message)
    {
        $this->addLogEntry($message, true, false);
    }

    public function logDoneStatus(): void
    {
        $this->addLogEntry('DONE', false, false);
    }

    public function logFailedStatus()
    {
        $this->addLogEntry('FAILED', false, false);
    }

    public function logLastSuccessfulSyncRunTimestamp($currentDateTime)
    {
        update_option(Constants::OPTIPUB_LAST_RUN, $currentDateTime);
        update_option(Constants::OPTIPUB_SYNC_HAS_RUN, 1);
    }

    public function logError($message)
    {
        $this->addLogEntry($message, true, true);
    }

    public function logErrorNoDate($message)
    {
        $this->addLogEntry($message, false, false);
    }

    public function getSummaryLog()
    {
        $last_run_timestamp = get_option(Constants::OPTIPUB_LAST_RUN);
        $summary_log = get_option(Constants::OPTIPUB_SUMMARY_LOG);

        // Trim all logs
        $summary_log = trim($summary_log);

        if (get_option(Constants::OPTIPUB_SYNC_LOCK) !== '0' && get_option(Constants::OPTIPUB_SYNC_LOCK) !== 0) {
            return "WP Cron Job is processing: \n\n" . $summary_log;
        }

        if (!empty($last_run_timestamp)) {
            // Only prepend "Last Sync" if it's not already there
            if (strpos($summary_log, 'Last Sync') === false) {
                return "[{$last_run_timestamp}] Last Sync\n\n" . $summary_log;
            }
        }

        return $summary_log;
    }

    public function clearSummaryLog()
    {
        update_option(Constants::OPTIPUB_SUMMARY_LOG, '');
    }

    public static function logCopyFile($message)
    {
        $existing = get_option(Constants::OPTIPUB_COPY_FILE_LOG);
        $new_message = trim($message . "\n" . $existing);
        update_option(Constants::OPTIPUB_COPY_FILE_LOG, $new_message);
    }

    public static function logStaticError($message)
    {
        // Since this is a static method, we need to create an instance to call addLogEntry
        $instance = new self();
        $instance->addLogEntry($message, true, false);
    }

    public function write_log($log)
    {
        if (is_array($log) || is_object($log)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Reason: Debugging information
            error_log(print_r($log, true));
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Reason: Debugging information
            error_log($log);
        }
    }

    public function isCronJobRunning(): bool
    {
        return (bool) get_transient('doing_cron');
    }

    /**
     * Flush rewrite rules with logging
     */
    public function flushRules(): void
    {
        flush_rewrite_rules();
        $this->logStaticError('URL Rewrite rules flushed');
    }

    public function updateMacro($content)
    {
        if (empty($content)) {
            return;
        }

        preg_match_all('#{\$custom_.*?}#', $content, $matches);

        if (!empty($matches) && isset($matches[0])) {
            foreach ($matches[0] as $match) {
                $prefixed_macro_key = 'optipub_macro_' . $match;

                if (1 == preg_match('#{\$custom_ad_\d+}#', $match)) {
                    $cc_macro_content = get_option($prefixed_macro_key, false);

                    if (false === $cc_macro_content) {
                        $cc_macro_content = get_option($match);
                    }
                } else {
                    $cc_macro_content = get_option($prefixed_macro_key, false);

                    if (false === $cc_macro_content) {
                        $cc_macro_content = get_option($match, $match);
                    }
                }

                // Log error if macro not found
                if ($cc_macro_content == $match) {
                    $message = "WARNING: {$match} not defined";
                    $this->logErrorNoDate($message);
                }

                // Replace macro with macro content
                $content = str_replace($match, $cc_macro_content, $content);
            }
        }

        return $content;
    }

    /**
     * Get array of public post statuses
     *
     * @return array Array of public post status names
     */
    public static function getPublicPostStatuses(): array
    {
        return array_values(get_post_stati([
            'public' => true,
        ], 'names'));
    }

    // public function createSubscribersTerm(): array
    // {
    //     $subscribers_term = term_exists('Subscribers', Constants::OPTIPUB_PUBLICATION_TAXONOMY);

    //     if (!$subscribers_term) {
    //         $subscribers_term = wp_insert_term(
    //             'Subscribers',
    //             Constants::OPTIPUB_PUBLICATION_TAXONOMY,
    //             [
    //                 'slug' => ''
    //             ]
    //         );

    //         if (!is_wp_error($subscribers_term)) {
    //             update_term_meta($subscribers_term['term_id'], 'created_by', 'optipub-plugin');

    //             $this->logStaticError('Created Subscribers term');
    //         } else {
    //             $this->logStaticError('Failed to create Subscribers term');
    //             return 0;
    //         }
    //     }

    //     return $subscribers_term;
    // }

    // public function getSyncedPublicationDataSorted(): array
    // {
    //     $terms = $this->settings_handler->getSyncedPublicationData();
    //     $primary_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

    //     if (empty($terms) || !$primary_id) {
    //         $this->logStaticError('Primary Publication not set.');
    //         return $terms;
    //     }

    //     // Find the term that has the primary publication ID
    //     $primary_term_id = null;
    //     foreach ($terms as $term_id => $term_data) {
    //         if (isset($term_data['meta']['publication_id'][0])
    //             && $term_data['meta']['publication_id'][0] == $primary_id
    //         ) {
    //             $primary_term_id = $term_id;
    //             break;
    //         }
    //     }

    //     // If we found the primary term, move it to the front
    //     if ($primary_term_id) {
    //         $primary_term = [$primary_term_id => $terms[$primary_term_id]];
    //         unset($terms[$primary_term_id]);
    //         return $primary_term + $terms;
    //     }

    //     return $terms;
    // }

    // public function getSortedTermsByPublicationID(): array
    // {
    //     $terms = $this->settings_handler->getSyncedTermMapByPublicationID();
    //     $primary_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

    //     if (empty($terms) || !$primary_id) {
    //         $this->logStaticError('Primary Publication not set.');
    //         return $terms;
    //     }

    //     // Extract primary term if it exists
    //     $primary_term = [];
    //     if (isset($terms[$primary_id])) {
    //         $primary_term[$primary_id] = $terms[$primary_id];
    //         unset($terms[$primary_id]);
    //     }

    //     // Merge primary term back at the beginning
    //     return array_merge($primary_term, $terms);
    // }

    // public function getSyncedPublicationData(): array
    // {
    //     $term_ids = get_terms([
    //         'taxonomy' => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
    //         'hide_empty' => false,
    //         'meta_query' => [
    //             [
    //                 'key' => 'publication_id',
    //                 'compare' => 'EXISTS',
    //             ],
    //         ],
    //         'fields' => 'ids'
    //     ]);

    //     $terms_with_meta = [];
    //     foreach ($term_ids as $term_id) {
    //         $term = get_term($term_id);
    //         $term_meta = get_term_meta($term_id);
    //         $terms_with_meta[$term_id] = [
    //             'term' => $term,
    //             'meta' => $term_meta
    //         ];
    //     }

    //     return $terms_with_meta;
    // }

    // /**
    //  * Get publication data with publication id as array key
    //  */
    // public function getSyncedTermMapByPublicationID(): array
    // {
    //     $terms_with_meta = $this->getSyncedPublicationData();

    //     if (empty($terms_with_meta)) {
    //         return [];
    //     }

    //     $term_map = [];
    //     foreach ($terms_with_meta as $term_id => $term_data) {
    //         $pub_id = $term_data['meta']['publication_id'][0];
    //         $term_map[$pub_id] = $term_data['term'];
    //     }

    //     return $term_map;
    // }

    // Add this static method to inspect the current API configuration
    public static function debugApiConfig(): array
    {
        $url = get_option(Constants::OPTIPUB_BASE_API_URL);
        $token = get_option(Constants::OPTIPUB_API_TOKEN);
        $decrypted_token = EncryptDecryptUtil::getDecryptedToken('api');

        return [
            'api_url'                => $url,
            'token_exists'           => !empty($token),
            'token_length'           => strlen($token),
            'decrypted_token_exists' => !empty($decrypted_token),
            'decrypted_token_length' => strlen($decrypted_token),
        ];
    }

    /**
     * Common method to add log entries
     *
     * @param string $message The message to log
     * @param bool $withTimestamp Whether to add timestamp to the message
     * @param bool $isError Whether this is an error message (adds orange color)
     */
    private function addLogEntry($message, $withTimestamp = true, $isError = false)
    {
        // Clean up the message - remove extra whitespace and newlines
        $message = trim(preg_replace('/[\s\r\n]+/', ' ', $message));

        // Get existing log and trim it
        $existing = get_option(Constants::OPTIPUB_SUMMARY_LOG);
        $existing = trim($existing);

        // Format the new log entry
        if ($withTimestamp) {
            $currentDateTime = current_time('Y-m-d H:i:s');

            if ($isError) {
                $newLog = "[{$currentDateTime}] {$message}\n";
            } else {
                $newLog = "[{$currentDateTime}] {$message}\n";
            }
        } else {
            $newLog = "{$message}\n";
        }

        // Combine logs and trim to last MAX_LOG_LINES
        $combined = $newLog . $existing;
        $lines = explode("\n", $combined);

        if (count($lines) > self::MAX_LOG_LINES) {
            $lines = array_slice($lines, 0, self::MAX_LOG_LINES);
        }

        // Ensure the log ends properly (no broken HTML tags)
        $finalLog = implode("\n", $lines);

        update_option(Constants::OPTIPUB_SUMMARY_LOG, $finalLog);
    }
}
