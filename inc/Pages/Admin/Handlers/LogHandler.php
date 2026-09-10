<?php

namespace OptiPub\Pages\Admin\Handlers;

use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;

class LogHandler extends BaseController
{
    public function __construct()
    {
        parent::__construct();  // Call parent constructor since we extend BaseController
    }

    public function trimSummaryLog(string $log): string
    {
        $log = trim($log);
        $maxLength = Constants::getSummaryLogMaxChars();

        if (strlen($log) <= $maxLength) {
            return $log;
        }

        return substr($log, 0, -round(strlen($log) / 2, 0, PHP_ROUND_HALF_EVEN));
    }

    public function clearLogs(): void
    {
        update_option(Constants::OPTIPUB_SUMMARY_LOG, '');
    }

    public function logOperation(string $operation, string $status): void
    {
        $this->logProgress($operation);

        if ($status === 'success') {
            $this->logDoneStatus();
        } else {
            $this->logFailedStatus();
        }
    }

    public function getCopyFileLog(): string
    {
        return get_option(Constants::OPTIPUB_COPY_FILE_LOG, '');
    }

    public function clearCopyFileLog(): void
    {
        update_option(Constants::OPTIPUB_COPY_FILE_LOG, '');
    }

    public function logMessage($message): void
    {
        $this->logStaticError($message);
    }

    public function logLastSuccessfulSyncRunTimestamp($timestamp): void
    {
        update_option(Constants::OPTIPUB_LAST_RUN, $timestamp);
        update_option(Constants::OPTIPUB_SYNC_HAS_RUN, 1);
    }

    public function appendToSummaryLog(string $oldLog, string $newLog): void
    {
        if (empty($newLog)) {
            update_option(Constants::OPTIPUB_SUMMARY_LOG, $oldLog);
        } else {
            update_option(Constants::OPTIPUB_SUMMARY_LOG, $newLog . '<br>' . $oldLog);
        }
    }
}
