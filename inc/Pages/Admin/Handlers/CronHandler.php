<?php

namespace OptiPub\Pages\Admin\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

use OptiPub\Api\WpDatabaseQuery;
use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;
use OptiPub\Base\CustomPostTypeLoader;
use OptiPub\Base\Helper;
use OptiPub\Base\PageFactory;
use OptiPub\Base\RedirectsLoader;
use OptiPubWP\Log;

class CronHandler extends BaseController
{
    private const MINUTES_IN_SECONDS = 'minutes_in_seconds';

    private $log_handler;

    private $settings_handler;

    private $is_running = false;

    public function __construct()
    {
        parent::__construct();
        $this->log_handler = new LogHandler();
        $this->settings_handler = new SettingsHandler();
    }

    public function clearCronJob(): void
    {
        wp_clear_scheduled_hook(Constants::getOptiPubCronJobName());
        $this->logStaticError('Syncing Stopped Until Save');
    }

    public function scheduleCronJob(): void
    {
        // Define WP_CRON_LOCK_TIMEOUT if not already defined
        if (!defined('WP_CRON_LOCK_TIMEOUT')) {
            define('WP_CRON_LOCK_TIMEOUT', Constants::CRON_LOCK_TIMEOUT_IN_SECONDS);
        }

        if (WP_CRON_LOCK_TIMEOUT < Constants::CRON_LOCK_TIMEOUT_IN_SECONDS) {
            $this->logMessage('Could not activate Sync - WP_CRON_LOCK_TIMEOUT is too small');

            return;
        }

        // Clear any existing scheduled events for this hook
        //wp_clear_scheduled_hook(Constants::getOptiPubCronJobName());

        // Schedule the new event
        if (!wp_next_scheduled(Constants::getOptiPubCronJobName())) {
            $this->logStaticError('Scheduling new Sync Job - ' . esc_html(Constants::getOptiPubCronJobName()));
            $scheduled = wp_schedule_event(
                time(),
                self::MINUTES_IN_SECONDS,
                Constants::getOptiPubCronJobName()
            );

            if ($scheduled === false) {
                $this->logStaticError('Failed to schedule cron job');
            } else {
                $this->logStaticError('Sync Job scheduled - ' . esc_html(Constants::getOptiPubCronJobName()));
            }
        }
    }

    public function syncContent(): void
    {
        // Register shutdown function with proper $this context
        register_shutdown_function(function () {
            $error = error_get_last();

            // Check for all types of fatal errors
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
                $this->logStaticError('Registered Shutdown Error: ' . $error['message']);
                update_option(Constants::OPTIPUB_SYNC_LOCK, 0);
            }
        });

        if (!$this->canSync()) {
            return;
        }

        try {
            $this->performSync();
        } catch (\Exception $e) {
            $this->logStaticError('Sync failed: ' . $e->getMessage());
        } finally {
            update_option(Constants::OPTIPUB_SYNC_LOCK, 0);
        }
    }

    public function cleanupContent(int $publication_id, bool $remove_all = false): void
    {
        $redirectsLoader = new RedirectsLoader();
        $cptLoader = new CustomPostTypeLoader();

        if (empty($publication_id)) {
            $this->logStaticError('Failed to cleanup content - No publication ID found');

            return;
        }

        $redirectsLoader->deleteRedirects($publication_id, $remove_all);

        // Cleanup page and fixed OptiPub CPTs (editors, videos, faqs)
        $cptLoader->deleteDatas('page', $publication_id, $remove_all);
        $cptLoader->deleteDatas('editors', $publication_id, $remove_all);
        $cptLoader->deleteDatas('videos', $publication_id, $remove_all);
        $cptLoader->deleteDatas('faqs', $publication_id, $remove_all);

        // Cleanup all resource-category CPTs from metadata (articles, reports, resources, partners, clients, etc.)
        $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA, []);

        if (is_array($optiPubMetadata)) {
            foreach ($optiPubMetadata as $post_type => $config) {
                if (empty($config['createCpt'])) {
                    continue;
                }

                if (in_array($post_type, ['editors', 'videos', 'faqs'], true)) {
                    continue;
                }
                $cptLoader->deleteDatas($post_type, $publication_id, $remove_all);
            }
        }
    }

    public function addCronIntervals($schedules): array
    {
        $schedules[self::MINUTES_IN_SECONDS] = [
            'interval' => Constants::CRON_INTERVAL_IN_SECONDS,
            'display'  => 'MINUTE_IN_SECONDS',
        ];

        return $schedules;
    }

    public function triggerSyncNow(): void
    {
        if ($this->is_running) {
            $this->logStaticError('Sync already in progress, skipping...');

            return;
        }

        $this->clearCronJob();

        $this->logStaticError('Manually triggering sync...');
        $this->is_running = true;

        try {
            do_action(Constants::getOptiPubCronJobName());
        } finally {
            $this->is_running = false;
        }

        $this->scheduleCronJob();
    }

    /**
     * Check if the sync lock is valid
     * If the lock is valid, return false
     * If the lock is not valid, set the lock and return true
     *
     * This is a pseudo lock to supplement WP-CRON
     */
    private function checkSyncTimeout(): bool
    {
        $lock_time = get_option(Constants::OPTIPUB_SYNC_LOCK);
        $timeout = defined('WP_CRON_LOCK_TIMEOUT') ? WP_CRON_LOCK_TIMEOUT : 600; // 10 minutes
        $now = time();

        // If no lock, set lock and return true
        if (!$lock_time || $lock_time === '0' || $lock_time === 0) {
            update_option(Constants::OPTIPUB_SYNC_LOCK, gmdate('Y-m-d H:i:s', $now + $timeout));

            return true;
        }

        $lock_timestamp = strtotime($lock_time);

        // If lock_time is greater than now, the lock has expired
        if ($lock_timestamp > $now) {
            $this->logStaticError('Sync lock expired, setting new lock, lock_timestamp: ' . gmdate('Y-m-d H:i:s', $lock_timestamp) . ' now: ' . gmdate('Y-m-d H:i:s', $now));
            update_option(Constants::OPTIPUB_SYNC_LOCK, gmdate('Y-m-d H:i:s', $now + $timeout));

            return true;
        }

        // Lock is still valid, cannot sync
        $this->logStaticError('Sync lock is still valid, cannot sync, lock_timestamp: ' . gmdate('Y-m-d H:i:s', $lock_timestamp) . ' now: ' . gmdate('Y-m-d H:i:s', $now));

        return true;
    }

    private function canSync(): bool
    {
        // Check Sync Requirements are Configured
        $baseUrl = get_option(Constants::OPTIPUB_BASE_API_URL);
        $apiToken = get_option(Constants::OPTIPUB_API_TOKEN);
        $pubId = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        $checks = [
            'Base URL'          => !empty($baseUrl),
            'API Token'         => !empty($apiToken),
            'Publication ID'    => !empty($pubId),
            'Cron Lock Timeout' => WP_CRON_LOCK_TIMEOUT >= Constants::CRON_LOCK_TIMEOUT_IN_SECONDS,
        ];

        foreach ($checks as $name => $result) {
            if (!$result) {
                if ($name !== 'Publication ID') {
                    $this->logStaticError("Sync failed check: {$name} Issue");
                }

                return false;
            }
        }

        if (!$this->checkSyncTimeout()) {
            return false;
        }

        return true;
    }

    private function performSync(): void
    {
        // Remove all content filters before sync
        kses_remove_filters();
        remove_all_filters('content_save_pre');
        remove_all_filters('content_filtered_save_pre');

        $sorted_publications = $this->settings_handler->getSyncedPublicationDataSorted();

        foreach ($sorted_publications as $publication) {
            $this->syncData($publication);
        }

        // Restore all content filters after sync
        kses_init_filters();
    }

    /**
     * Sync data from OptiPub to WordPress
     */
    private function syncData(array $publication): void
    {
        // Store original memory limit to restore later
        $original_memory_limit = ini_get('memory_limit');

        // Memory throttling function
        $checkMemory = function () {
            $memory_usage = memory_get_usage(true);
            $memory_limit = ini_get('memory_limit');
            $memory_limit_bytes = $this->convertToBytes($memory_limit);

            if ($memory_usage > ($memory_limit_bytes * 0.8)) { // 80% of memory limit
                $this->logError('Memory usage high: ' . $this->formatBytes($memory_usage) . ' of ' . $memory_limit);
                // Sleep for 5 seconds to allow garbage collection
                sleep(5);
                // Force garbage collection
                gc_collect_cycles();
            }
        };

        try {
            // Defensive: validate all required fields
            if (
                empty($publication['term']->term_id) || empty($publication['meta']['publication_id'][0]) || empty($publication['meta']['publication_name'][0])
            ) {
                throw new \Exception('Missing required publication data');
            }

            if (!isset($publication['meta']['publication_primary'][0]) || !isset($publication['meta']['publication_last_sync_time'][0]) || !isset($publication['meta']['publication_sync_status'][0])
            ) {
                throw new \Exception('Missing required publication meta data');
            }

            // Get publication ID and term ID from the passed publication data
            $publication_id = $publication['meta']['publication_id'][0];
            $term_id = $publication['term']->term_id;
            $publication_name = $publication['meta']['publication_name'][0];
            $publication_primary = $publication['meta']['publication_primary'][0];

            // Mark publication as syncing
            update_term_meta($term_id, 'publication_sync_status', '1');

            $sync_start_time = time();

            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Reason: Increase memory limit only for this specific sync operation
            ini_set('memory_limit', '512M');

            $helper = new Helper();
            $redirectsLoader = new RedirectsLoader();
            $cptLoader = new CustomPostTypeLoader();
            $pageFactory = new PageFactory();

            // Get old summary log and trim it
            $oldSummaryLog = get_option(Constants::OPTIPUB_SUMMARY_LOG);
            $oldSummaryLog = trim($oldSummaryLog);
            //update_option(Constants::OPTIPUB_SUMMARY_LOG, '');

            $currentDateTime = gmdate('Y-m-d H:i:s');

            // Check memory before each major operation
            $checkMemory();
            $redirectsLoader->loadRedirects($publication_id);

            $checkMemory();
            $cptLoader->loadMacros($publication_id);

            $checkMemory();
            $pageFactory->buildStaticPages($publication_id);

            $checkMemory();
            $cptLoader->loadEditors($publication_id);

            $checkMemory();
            $cptLoader->loadVideos($publication_id);

            $checkMemory();
            $cptLoader->loadFAQs($publication_id);

            // Resources resolve their category through term meta and are skipped
            // when it cannot be resolved, so the terms are reconciled first.
            $checkMemory();
            $helper->syncPostTypeCategories();

            $checkMemory();
            $cptLoader->loadResources($publication_id);

            // Remove content from WP that is not found in OptiPub anymore
            $checkMemory();
            $this->cleanupContent($publication_id, false);

            // Update last sync time on success
            update_term_meta($term_id, 'publication_last_sync_time', $sync_start_time);

            // Mark sync as completed
            update_option(Constants::OPTIPUB_SYNC_HAS_RUN, 1);
            $this->logLastSuccessfulSyncRunTimestamp($currentDateTime);

            $newSummaryLog = get_option(Constants::OPTIPUB_SUMMARY_LOG);
            $newSummaryLog = trim($newSummaryLog);

            $fullSummaryLog = $newSummaryLog . "\n" . $oldSummaryLog;
            $fullSummaryLog = $this->log_handler->trimSummaryLog($fullSummaryLog);
            update_option(Constants::OPTIPUB_SUMMARY_LOG, $fullSummaryLog);
        } catch (\Exception $e) {
            $this->logError("Sync failed for publication {$publication_name}: " . $e->getMessage());
        } finally {
            // Reset sync status
            update_term_meta($term_id, 'publication_sync_status', '0');

            // Restore original memory limit
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Reason: Restoring original memory limit after sync
            ini_set('memory_limit', $original_memory_limit);
        }
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($memory_limit): int
    {
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function resetContent(): void
    {
        delete_option(Constants::OPTIPUB_SITE_LOGO_SRC);
        delete_option(Constants::OPTIPUB_SITE_FAVICON_SRC);

        $wp_database_query = new WpDatabaseQuery();
        $wp_database_query->resetAllContent();
    }

    /*
    private function updateHomePage(): void
    {
        if (empty(get_option(Constants::OPTIPUB_HOME_TEMPLATE))) {
            $this->logProgress('No Home Page template set.');

            return;
        }

        $pages = get_pages();
        // Update page_on_front page
        foreach ($pages as $page) {
            $template = get_post_meta($page->ID, '_wp_page_template', true);
            if ($template == get_option(Constants::OPTIPUB_HOME_TEMPLATE)) {
                if (get_option('page_on_front') != $page->ID) {
                    update_option('page_on_front', $page->ID);
                    update_option('show_on_front', 'page');
                    $this->logProgress("Set $page->post_name page as the Home Page");
                    $this->logDoneStatus();
                }
            }
        }
    }
    */
}
