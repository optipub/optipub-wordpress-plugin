<?php

/**
 * Sync monitor
 *
 * GET /wp-json/optipub/v1/last-run tells an uptime monitor whether the OptiPub sync is running:
 * 200 when the last successful sync started less than Constants::CRON_LOCK_TIMEOUT_IN_SECONDS
 * ago, 503 otherwise.
 */

namespace OptiPub\Api;

if (!defined('ABSPATH')) {
    exit;
}

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use OptiPub\Base\Constants;
use WP_REST_Response;
use WP_REST_Server;

class SyncMonitor
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerEndpoints']);
    }

    public function registerEndpoints(): void
    {
        register_rest_route('optipub/v1', '/last-run', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleLastRun'],
            'permission_callback' => '__return_true', // Public, for uptime monitors
        ]);
    }

    /**
     * GET /wp-json/optipub/v1/last-run
     */
    public function handleLastRun(): WP_REST_Response
    {
        $threshold_seconds = Constants::CRON_LOCK_TIMEOUT_IN_SECONDS;
        $last_run_raw = get_option(Constants::OPTIPUB_LAST_RUN);

        // The sync stores the time it started in UTC (gmdate)
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);

        $data = [
            'last_run'          => $last_run_raw,
            'threshold_seconds' => $threshold_seconds,
            'now'               => $now->format('Y-m-d H:i:s'),
            'is_stale'          => true,
            'seconds_since'     => null,
        ];

        if (empty($last_run_raw)) {
            return $this->response('error', 'No Data Found', $data, 503);
        }

        try {
            $last_run = new DateTimeImmutable($last_run_raw, $utc);
        } catch (Exception $e) {
            return $this->response('error', 'Invalid Data Format', $data + ['parse_error' => $e->getMessage()], 503);
        }

        $diff = $now->getTimestamp() - $last_run->getTimestamp();

        $data['seconds_since'] = max(0, $diff);
        $data['is_stale'] = $diff > $threshold_seconds;

        if ($data['is_stale']) {
            return $this->response('stale', 'Server Error', $data, 503);
        }

        return $this->response('ok', 'Success', $data, 200);
    }

    /**
     * Response that page and edge caches do not keep, so the monitor always gets the current state
     */
    private function response(string $status, string $message, array $meta, int $code): WP_REST_Response
    {
        $response = new WP_REST_Response([
            'status'  => $status,
            'message' => $message,
            'meta'    => $meta,
        ], $code);

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
