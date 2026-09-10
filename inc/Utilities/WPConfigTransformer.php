<?php

namespace OptiPub\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use WP_Filesystem_Base;

/**
 * Transforms a wp-config.php file.
 */
class WPConfigTransformer
{
    /**
     * Append to end of file.
     */
    public const ANCHOR_EOF = 'EOF';

    /**
     * Path to the wp-config.php file.
     *
     * @var string
     */
    protected $wp_config_path;

    /**
     * Original source of the wp-config.php file.
     *
     * @var string
     */
    protected $wp_config_src;

    /**
     * Array of parsed configs.
     *
     * @var array
     */
    protected $wp_configs = [];

    /**
     * WP_Filesystem instance.
     *
     * @var WP_Filesystem_Base
     */
    protected $filesystem;

    /**
     * Instantiates the class with a valid wp-config.php.
     *
     * @param string $wp_config_path Path to a wp-config.php file.
     *
     * @throws Exception If the wp-config.php file is missing.
     * @throws Exception If the wp-config.php file is not writable.
     */
    public function __construct($wp_config_path)
    {
        global $wp_filesystem;

        // Initialize WP_Filesystem if not already done
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->filesystem = $wp_filesystem;
        $basename = basename($wp_config_path);

        if (! $this->filesystem->exists($wp_config_path)) {
            throw new Exception(esc_html($basename) . ' does not exist.');
        }

        if (! $this->filesystem->is_writable($wp_config_path)) {
            throw new Exception(esc_html($basename) . ' is not writable.');
        }

        $this->wp_config_path = $wp_config_path;
    }

    /**
     * Checks if a config exists in the wp-config.php file.
     *
     * @param string $type config type (constant or variable)
     * @param string $name config name
     *
     * @throws Exception If the wp-config.php file is empty.
     * @throws Exception if the requested config type is invalid
     *
     * @return bool
     */
    public function exists($type, $name)
    {
        $wp_config_src = $this->filesystem->get_contents($this->wp_config_path);

        if (! trim($wp_config_src)) {
            throw new Exception('Config file is empty.');
        }
        // Normalize the newline to prevent an issue coming from OSX.
        $this->wp_config_src = str_replace(["\n\r", "\r"], "\n", $wp_config_src);
        $this->wp_configs = $this->parse_wp_config($this->wp_config_src);

        if (! isset($this->wp_configs[$type])) {
            throw new Exception("Config type '" . esc_html($type) . "' does not exist.");
        }

        return isset($this->wp_configs[$type][$name]);
    }

    /**
     * Get the value of a config in the wp-config.php file.
     *
     * @param string $type config type (constant or variable)
     * @param string $name config name
     *
     * @throws Exception If the wp-config.php file is empty.
     * @throws Exception if the requested config type is invalid
     *
     * @return array
     */
    public function get_value($type, $name)
    {
        $wp_config_src = $this->filesystem->get_contents($this->wp_config_path);

        if (! trim($wp_config_src)) {
            throw new Exception('Config file is empty.');
        }

        $this->wp_config_src = $wp_config_src;
        $this->wp_configs = $this->parse_wp_config($this->wp_config_src);

        if (! isset($this->wp_configs[$type])) {
            throw new Exception("Config type '" . esc_html($type) . "' does not exist.");
        }

        return $this->wp_configs[$type][$name]['value'];
    }

    /**
     * Adds a config to the wp-config.php file.
     *
     * @param string $type    config type (constant or variable)
     * @param string $name    config name
     * @param string $value   config value
     * @param array  $options (optional) Array of special behavior options
     *
     * @throws Exception if the config value provided is not a string
     * @throws Exception if the config placement anchor could not be located
     *
     * @return bool
     */
    public function add($type, $name, $value, array $options = [])
    {
        if (! is_string($value)) {
            throw new Exception('Config value must be a string.');
        }

        if ($this->exists($type, $name)) {
            return false;
        }

        $defaults = [
            'raw'       => false, // Display value in raw format without quotes.
            'anchor'    => "/* That's all, stop editing!", // Config placement anchor string.
            'separator' => PHP_EOL, // Separator between config definition and anchor string.
            'placement' => 'before', // Config placement direction (insert before or after).
        ];

        [$raw, $anchor, $separator, $placement] = array_values(array_merge($defaults, $options));

        $raw = (bool) $raw;
        $anchor = (string) $anchor;
        $separator = (string) $separator;
        $placement = (string) $placement;

        if (self::ANCHOR_EOF === $anchor) {
            $contents = $this->wp_config_src . $this->normalize($type, $name, $this->format_value($value, $raw));
        } else {
            if (false === strpos($this->wp_config_src, $anchor)) {
                throw new Exception('Unable to locate placement anchor.');
            }

            $new_src = $this->normalize($type, $name, $this->format_value($value, $raw));
            $new_src = ('after' === $placement) ? $anchor . $separator . $new_src : $new_src . $separator . $anchor;
            $contents = str_replace($anchor, $new_src, $this->wp_config_src);
        }

        return $this->save($contents);
    }

    /**
     * Updates an existing config in the wp-config.php file.
     *
     * @param string $type    config type (constant or variable)
     * @param string $name    config name
     * @param string $value   config value
     * @param array  $options (optional) Array of special behavior options
     *
     * @throws Exception if the config value provided is not a string
     *
     * @return bool
     */
    public function update($type, $name, $value, array $options = [])
    {
        if (! is_string($value)) {
            throw new Exception('Config value must be a string.');
        }

        $defaults = [
            'add'       => true, // Add the config if missing.
            'raw'       => false, // Display value in raw format without quotes.
            'normalize' => false, // Normalize config output using WP Coding Standards.
        ];

        [$add, $raw, $normalize] = array_values(array_merge($defaults, $options));

        $add = (bool) $add;
        $raw = (bool) $raw;
        $normalize = (bool) $normalize;

        if (! $this->exists($type, $name)) {
            return ($add) ? $this->add($type, $name, $value, $options) : false;
        }

        $old_src = $this->wp_configs[$type][$name]['src'];
        $old_value = $this->wp_configs[$type][$name]['value'];
        $new_value = $this->format_value($value, $raw);

        if ($normalize) {
            $new_src = $this->normalize($type, $name, $new_value);
        } else {
            $new_parts = $this->wp_configs[$type][$name]['parts'];
            $new_parts[1] = str_replace($old_value, $new_value, $new_parts[1]); // Only edit the value part.
            $new_src = implode('', $new_parts);
        }

        $contents = preg_replace(
            sprintf('/(?<=^|;|<\?php\s|<\?\s)(\s*?)%s/m', preg_quote(trim($old_src), '/')),
            '$1' . str_replace('$', '\$', trim($new_src)),
            $this->wp_config_src
        );

        return $this->save($contents);
    }

    /**
     * Removes a config from the wp-config.php file.
     *
     * @param string $type config type (constant or variable)
     * @param string $name config name
     *
     * @return bool
     */
    public function remove($type, $name)
    {
        if (! $this->exists($type, $name)) {
            return false;
        }

        $pattern = sprintf('/(?<=^|;|<\?php\s|<\?\s)%s\s*(\S|$)/m', preg_quote($this->wp_configs[$type][$name]['src'], '/'));
        $contents = preg_replace($pattern, '$1', $this->wp_config_src);

        return $this->save($contents);
    }

    /**
     * Applies formatting to a config value.
     *
     * @param string $value config value
     * @param bool   $raw   display value in raw format without quotes
     *
     * @throws Exception when a raw value is requested for an empty string
     *
     * @return mixed
     */
    protected function format_value($value, $raw)
    {
        if ($raw && '' === trim($value)) {
            throw new Exception('Raw value for empty string not supported.');
        }

        return ($raw) ? $value : "'" . addslashes($value) . "'";
    }

    /**
     * Normalizes the source output for a name/value pair.
     *
     * @param string $type  config type (constant or variable)
     * @param string $name  config name
     * @param mixed  $value config value
     *
     * @throws Exception if the requested config type does not support normalization
     *
     * @return string
     */
    protected function normalize($type, $name, $value)
    {
        if ('constant' === $type) {
            $placeholder = "define( '%s', %s );";
        } elseif ('variable' === $type) {
            $placeholder = '$%s = %s;';
        } else {
            throw new Exception("Unable to normalize config type '" . esc_html($type) . "'.");
        }

        return sprintf($placeholder, $name, $value);
    }

    /**
     * Parses the source of a wp-config.php file.
     *
     * @param string $src config file source
     *
     * @return array
     */
    protected function parse_wp_config($src)
    {
        $configs = [];
        $configs['constant'] = [];
        $configs['variable'] = [];

        // Strip comments.
        foreach (token_get_all($src) as $token) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $src = str_replace($token[1], '', $src);
            }
        }

        preg_match_all('/(?<=^|;|<\?php\s|<\?\s)(\h*define\s*\(\s*[\'"](\w*?)[\'"]\s*)(,\s*(\'\'|""|\'.*?[^\\\\]\'|".*?[^\\\\]"|.*?)\s*)((?:,\s*(?:true|false)\s*)?\)\s*;)/ims', $src, $constants);
        preg_match_all('/(?<=^|;|<\?php\s|<\?\s)(\h*\$(\w+)\s*=)(\s*(\'\'|""|\'.*?[^\\\\]\'|".*?[^\\\\]"|.*?)\s*;)/ims', $src, $variables);

        if (! empty($constants[0]) && ! empty($constants[1]) && ! empty($constants[2]) && ! empty($constants[3]) && ! empty($constants[4]) && ! empty($constants[5])) {
            foreach ($constants[2] as $index => $name) {
                $configs['constant'][$name] = [
                    'src'   => $constants[0][$index],
                    'value' => $constants[4][$index],
                    'parts' => [
                        $constants[1][$index],
                        $constants[3][$index],
                        $constants[5][$index],
                    ],
                ];
            }
        }

        if (! empty($variables[0]) && ! empty($variables[1]) && ! empty($variables[2]) && ! empty($variables[3]) && ! empty($variables[4])) {
            // Remove duplicate(s), last definition wins.
            $variables[2] = array_reverse(array_unique(array_reverse($variables[2], true)), true);

            foreach ($variables[2] as $index => $name) {
                $configs['variable'][$name] = [
                    'src'   => $variables[0][$index],
                    'value' => $variables[4][$index],
                    'parts' => [
                        $variables[1][$index],
                        $variables[3][$index],
                    ],
                ];
            }
        }

        return $configs;
    }

    /**
     * Saves new contents to wp-config.php
     * Uses WP_Filesystem for proper file operations
     *
     * @param string $contents New config contents
     * @return bool Success
     * @throws Exception If contents empty or save fails
     */
    protected function save($contents): bool
    {
        if (!trim($contents)) {
            throw new Exception('Cannot save empty config file contents.');
        }

        if ($contents === $this->wp_config_src) {
            return false;
        }

        $result = $this->filesystem->put_contents($this->wp_config_path, $contents);

        if (false === $result) {
            throw new Exception('Failed to update wp-config.php file.');
        }

        return true;
    }
}
