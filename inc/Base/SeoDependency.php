<?php

namespace OptiPub\Base;

class SeoDependency
{
    private const YOAST_SLUG = 'wordpress-seo';
    private const YOAST_BASENAME = 'wordpress-seo/wp-seo.php';

    private const AIOSEO_SLUG = 'all-in-one-seo-pack';
    private const AIOSEO_BASENAME = 'all-in-one-seo-pack/all_in_one_seo_pack.php';

    public static function resolve_status(): array
    {
        self::ensure_plugin_functions_loaded();

        $all_plugins = \is_callable('get_plugins') ? (array) \call_user_func('get_plugins') : [];
        $file_mods_disabled = \defined('DISALLOW_FILE_MODS') ? (bool) \constant('DISALLOW_FILE_MODS') : false;
        $can_install = \is_callable('current_user_can') ? (bool) \call_user_func('current_user_can', 'install_plugins') && ! $file_mods_disabled : false;
        $can_activate = \is_callable('current_user_can') ? (bool) \call_user_func('current_user_can', 'activate_plugins') : false;

        $yoast = self::build_plugin_status(self::YOAST_SLUG, self::YOAST_BASENAME, $all_plugins, $can_install, $can_activate);
        $aioseo = self::build_plugin_status(self::AIOSEO_SLUG, self::AIOSEO_BASENAME, $all_plugins, $can_install, $can_activate);

        $satisfied = $yoast['active'] || $aioseo['active'];

        return [
            'dependency' => [
                'type'      => 'or',
                'satisfied' => $satisfied,
            ],
            'capabilities' => [
                'can_install'  => $can_install,
                'can_activate' => $can_activate,
            ],
            'plugins' => [
                'yoast'  => $yoast,
                'aioseo' => $aioseo,
            ],
        ];
    }

    private static function build_plugin_status(string $slug, string $basename, array $all_plugins, bool $can_install, bool $can_activate): array
    {
        $installed = self::is_plugin_installed($basename, $all_plugins);
        $active = self::is_plugin_active_anywhere($basename);

        $action = 'none';
        $urls = [
            'install'  => null,
            'activate' => null,
        ];

        if (! $active) {
            if (! $installed) {
                $action = $can_install ? 'install' : 'none';

                if ($can_install) {
                    $urls['install'] = self::build_install_url($slug);
                }
            } else {
                $action = $can_activate ? 'activate' : 'none';

                if ($can_activate) {
                    $urls['activate'] = self::build_activate_url($basename);
                }
            }
        }

        return [
            'slug'      => $slug,
            'basename'  => $basename,
            'installed' => $installed,
            'active'    => $active,
            'action'    => $action,
            'urls'      => $urls,
        ];
    }

    private static function is_plugin_active_anywhere(string $basename): bool
    {
        if (\is_callable('is_plugin_active') && (bool) \call_user_func('is_plugin_active', $basename)) {
            return true;
        }

        if (
            \is_callable('is_multisite')
            && \is_callable('is_plugin_active_for_network')
            && (bool) \call_user_func('is_multisite')
            && (bool) \call_user_func('is_plugin_active_for_network', $basename)
        ) {
            return true;
        }

        return false;
    }

    private static function is_plugin_installed(string $basename, array $all_plugins): bool
    {
        if (isset($all_plugins[$basename])) {
            return true;
        }

        if (! \defined('WP_PLUGIN_DIR')) {
            return false;
        }

        return \file_exists((string) \constant('WP_PLUGIN_DIR') . '/' . $basename);
    }

    private static function build_install_url(string $slug): string
    {
        return (string) \call_user_func(
            'wp_nonce_url',
            (string) \call_user_func('admin_url', 'update.php?action=install-plugin&plugin=' . \rawurlencode($slug)),
            'install-plugin_' . $slug
        );
    }

    private static function build_activate_url(string $basename): string
    {
        return (string) \call_user_func(
            'wp_nonce_url',
            (string) \call_user_func('admin_url', 'plugins.php?action=activate&plugin=' . \rawurlencode($basename)),
            'activate-plugin_' . $basename
        );
    }

    private static function ensure_plugin_functions_loaded(): void
    {
        if (! \function_exists('get_plugins') || ! \function_exists('is_plugin_active') || ! \function_exists('is_plugin_active_for_network')) {
            $abspath = \defined('ABSPATH') ? (string) \constant('ABSPATH') : '';

            if ($abspath !== '') {
                require_once $abspath . 'wp-admin/includes/plugin.php';
            }
        }
    }
}
