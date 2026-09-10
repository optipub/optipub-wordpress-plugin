<?php

namespace OptiPub\Pages\Admin\Handlers;

use Exception;
use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;
use WP_Filesystem_Base;

class FileHandler extends BaseController
{
    /**
     * WP_Filesystem instance.
     *
     * @var WP_Filesystem_Base
     */
    protected $filesystem;

    public function __construct()
    {
        parent::__construct();
        $this->initFilesystem();
    }

    public function handleCopyFiles(array $data): void
    {
        $validated_data = $this->validateCopyFilesData($data);
        $this->processCopyFiles($validated_data);
    }

    public function handleThemeChange(string $theme_directory): void
    {
        $current_theme = wp_get_theme();

        update_option(Constants::OPTIPUB_ACTIVE_THEME_DIR, $theme_directory);
        update_option(Constants::OPTIPUB_ACTIVE_THEME_NAME, $current_theme->get('Name'));
        update_option(Constants::OPTIPUB_ACTIVE_THEME_VERSION, $current_theme->get('Version'));

        $this->logCopyFile("Saved New Theme Directory to Plugin.\n");
    }

    /**
     * Initialize WP_Filesystem
     */
    private function initFilesystem(): void
    {
        global $wp_filesystem;

        // Initialize WP_Filesystem if not already done
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->filesystem = $wp_filesystem;
    }

    /**
     * Process file copying operations
     */
    private function processCopyFiles(array $data): void
    {
        $copy_operations = [
            'page-templates'   => Constants::OPTIPUB_THEME_TEMPLATE_PATH,
            'template-parts'   => Constants::OPTIPUB_TEMPLATE_PARTS_PATH,
            'widget-templates' => Constants::OPTIPUB_THEME_WIDGET_PATH,
            'helper-functions' => Constants::OPTIPUB_THEME_HELPER_FUNCTIONS,
        ];

        foreach ($copy_operations as $key => $path) {
            if ($data[$key] === 'true') {
                $this->processSingleCopyOperation($key, $path, $data);
            }
        }

        if ($data['copy-previous-theme'] === 'true') {
            $this->copyPreviousTheme($data);
        }
    }

    private function processSingleCopyOperation(string $key, string $path, array $data): void
    {
        $this->logCopyFile("Copying {$key}");
        $src = realpath($data['src'] . '/' . $path . '/');

        if (!$src) {
            $this->logCopyFile("ERROR: Plugin {$key} Directory Does Not Exist.");

            return;
        }

        $dst = $data['dst'] . '/' . $path;
        $this->copyFiles($src, $dst);
    }

    private function copyFiles(string $src, string $dst): void
    {
        $dir = opendir($src);

        if (false === $dir) {
            throw new Exception('Failed to open source directory: ' . esc_html($src));
        }

        $this->ensureDestinationDirectory($dst);

        while ($file = readdir($dir)) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;

            if (is_dir($src_file)) {
                $this->copyFiles($src_file, $dst_file);
            } else {
                if (!copy($src_file, $dst_file)) {
                    throw new Exception('Failed to copy file: ' . esc_html($dst_file));
                }
                $this->logCopyFile(substr($dst_file, strlen($dst)));
            }
        }

        closedir($dir);
    }

    private function copyPreviousTheme(array $data): void
    {
        $this->logCopyFile('Copying Previous Theme');
        $src = realpath($data['og_src'] . '/' . Constants::OPTIPUB_THEME_BASE_PATH . '/');

        if (!$src) {
            $this->logCopyFile('ERROR: Previous Theme Directory Does Not Exist.');

            return;
        }

        $dst = $data['dst'] . '/' . Constants::OPTIPUB_THEME_BASE_PATH;
        $this->copyFiles($src, $dst);

        $this->updateThemeState($data['dst']);
    }

    private function updateThemeState(string $theme_directory): void
    {
        $current_theme = wp_get_theme();
        update_option(Constants::OPTIPUB_ACTIVE_THEME_NAME, $current_theme->get('Name'));
        update_option(Constants::OPTIPUB_ACTIVE_THEME_VERSION, $current_theme->get('Version'));
        update_option(Constants::OPTIPUB_ACTIVE_THEME_DIR, $theme_directory);

        $this->logCopyFile("Saved New Theme Directory to Plugin.\n");
    }

    private function ensureDestinationDirectory(string $dst): void
    {
        if (is_dir($dst)) {
            if (!$this->filesystem->is_writable($dst)) {
                throw new Exception('Directory exists but is not writable: ' . esc_html($dst));
            }

            return;
        }

        if (!wp_mkdir_p($dst)) {
            throw new Exception('Failed to create directory: ' . esc_html($dst));
        }

        if (!$this->filesystem->is_writable($dst)) {
            throw new Exception('Created directory but it is not writable: ' . esc_html($dst));
        }
    }

    private function validateCopyFilesData(array $data): array
    {
        if (!isset($data['src'], $data['dst'])) {
            throw new Exception('Missing required source or destination paths');
        }

        $postArray = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['nonce', 'action'])) {
                continue;
            }
            $postArray[$key] = rtrim($value, '/');
        }

        // Exit if destination path does not exist
        $dst = $postArray['dst'];

        if (!realpath($dst)) {
            throw new Exception('Current Theme Directory Does Not Exist - Exiting.');
        }

        return $postArray;
    }

    // ... rest of file handling methods ...
}
