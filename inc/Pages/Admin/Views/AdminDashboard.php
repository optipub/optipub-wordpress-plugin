<?php

namespace OptiPub\Pages\Admin\Views;

use OptiPub\Base\AdminWidgetsTable;
use OptiPub\Base\BaseController;
use OptiPub\Pages\Admin\Handlers\LogHandler;

class AdminDashboard extends BaseController
{
    private $widgets_table = null;

    private $log_handler;

    public function __construct()
    {
        parent::__construct();
        $this->log_handler = new LogHandler();
    }

    public function render(): void
    {
        // $summary_log = $this->getSummaryLog();
        require_once "{$this->plugin_path}/admin/OptiPubAdmin.php";
    }

    public function renderUnauthorized(): void
    {
        require_once "{$this->plugin_path}/admin/unauthorized.php";
    }

    public function createAdminWidgetsTable()
    {
        if ($this->widgets_table === null) {
            if (!class_exists('WP_List_Table')) {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
            }
            $this->widgets_table = new AdminWidgetsTable();
        }

        return $this->widgets_table;
    }
}
