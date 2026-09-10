<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

if (! \function_exists(__NAMESPACE__ . '\\esc_html')) {
    function esc_html(string $value): string
    {
        return \is_callable('esc_html') ? (string) \call_user_func('esc_html', $value) : $value;
    }
}

if (! \function_exists(__NAMESPACE__ . '\\esc_url')) {
    function esc_url(string $value): string
    {
        return \is_callable('esc_url') ? (string) \call_user_func('esc_url', $value) : $value;
    }
}

if (! \function_exists(__NAMESPACE__ . '\\esc_html__')) {
    function esc_html__(string $value, string $domain = 'default'): string
    {
        return \is_callable('esc_html__') ? (string) \call_user_func('esc_html__', $value, $domain) : $value;
    }
}

class SeoDependencyNotice
{
    public function register(): void
    {
        \call_user_func('add_action', 'admin_notices', [$this, 'render_admin_notice']);

        if (\is_callable('is_multisite') && (bool) \call_user_func('is_multisite')) {
            \call_user_func('add_action', 'network_admin_notices', [$this, 'render_network_notice']);
        }
    }

    public function render_admin_notice(): void
    {
        // On multisite, skip rendering on network admin to avoid duplication.
        // network_admin_notices hook will handle network admin rendering.
        if (\is_callable('is_multisite') && (bool) \call_user_func('is_multisite')) {
            if (\is_callable('is_network_admin') && (bool) \call_user_func('is_network_admin')) {
                return;
            }
        }

        $this->render_notice();
    }

    public function render_network_notice(): void
    {
        $this->render_notice();
    }

    private function render_notice(): void
    {
        if (
            (\function_exists('wp_doing_ajax') && (bool) \call_user_func('wp_doing_ajax'))
            || (\defined('DOING_AJAX') && (bool) \constant('DOING_AJAX'))
        ) {
            return;
        }

        $status = SeoDependency::resolve_status();
        $dependency = isset($status['dependency']) && \is_array($status['dependency']) ? $status['dependency'] : [];

        if (! empty($dependency['satisfied'])) {
            return;
        }

        $plugins = isset($status['plugins']) && \is_array($status['plugins']) ? $status['plugins'] : [];
        $capabilities = isset($status['capabilities']) && \is_array($status['capabilities']) ? $status['capabilities'] : [];
        $can_install = ! empty($capabilities['can_install']);
        $can_activate = ! empty($capabilities['can_activate']);

        echo '<div id="optipub-seo-dependency-notice" class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__('OptiPub requires an SEO plugin', 'optipub') . '</strong></p>';
        echo '<p>' . esc_html__('Please activate one of these supported plugins to continue.', 'optipub') . '</p>';
        echo '<div style="margin-top: 8px; padding-bottom: 8px;">';
        echo '<div style="padding-bottom: 10px;">';
        $this->render_plugin_row('Yoast SEO', $plugins['yoast'] ?? [], $can_install, $can_activate);
        echo '</div>';
        echo '<div style="padding-top: 2px;">';
        $this->render_plugin_row('All in One SEO', $plugins['aioseo'] ?? [], $can_install, $can_activate);
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_plugin_row(string $label, array $plugin, bool $can_install, bool $can_activate): void
    {
        $active = ! empty($plugin['active']);
        $installed = ! empty($plugin['installed']);
        $urls = isset($plugin['urls']) && \is_array($plugin['urls']) ? $plugin['urls'] : [];
        $activate_url = isset($urls['activate']) ? (string) $urls['activate'] : '';
        $install_url = isset($urls['install']) ? (string) $urls['install'] : '';

        echo '<span style="display: inline-flex; align-items: center; gap: 8px;"><strong>' . esc_html($label) . '</strong>:';

        if ($active) {
            echo '<span>' . esc_html__('Active', 'optipub') . '</span>';
            echo '</span>';

            return;
        }

        if ($installed && $can_activate && $activate_url !== '') {
            echo '<a class="button button-secondary" href="' . esc_url($activate_url) . '">' . esc_html__('Activate', 'optipub') . '</a>';
            echo '</span>';

            return;
        }

        if (! $installed && $can_install && $install_url !== '') {
            echo '<a class="button button-secondary" href="' . esc_url($install_url) . '">' . esc_html__('Install', 'optipub') . '</a>';
            echo '</span>';

            return;
        }

        $no_permission_text = esc_html__('You do not have permission to install or activate this plugin.', 'optipub');

        if (\is_callable('is_multisite') && (bool) \call_user_func('is_multisite')) {
            $no_permission_text .= ' ' . esc_html__('Ask a network administrator for help.', 'optipub');
        } else {
            $no_permission_text .= ' ' . esc_html__('Ask an administrator for help.', 'optipub');
        }
        echo '<span>' . esc_html($no_permission_text) . '</span>';
        echo '</span>';
    }
}
