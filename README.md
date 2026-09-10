# OptiPub for WordPress

Sync content from the [OptiPub](https://www.optipub.com/) platform to WordPress with automated cron jobs and custom post types.

[![Code style: PHP-CS-Fixer](https://img.shields.io/badge/code%20style-PHP--CS--Fixer-brightgreen.svg)](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)

OptiPub synchronizes publications, resources, videos, FAQs and authors from the
OptiPub platform into WordPress. It creates the matching custom post types,
keeps them in sync every five minutes via WP-Cron, and integrates with your
theme through templates and widgets.

## Features

- Custom post types for Resources, Videos, FAQs and Authors
- Automated content synchronization via WP-Cron (default: every 5 minutes)
- Secure API authentication with encrypted token storage
- Multiple publication support
- Widget integration with custom content
- Tag and category management
- Static and dynamic page sync, redirect handling
- Theme template integration

## Requirements

- WordPress 5.0 or newer (tested up to 6.9)
- PHP 7.4 or newer
- [Composer](https://getcomposer.org/) (for development only)

## Installation

### From WordPress.org (end users)

Install **OptiPub** from the WordPress plugin directory, or upload the packaged
plugin, then activate it and configure your OptiPub domain and API token under
**OptiPub Admin**.

### From source (developers)

This repository contains the plugin **source only**. Runtime dependencies in
`vendor/` are not committed; install them with Composer:

```bash
git clone https://github.com/optipub/optipub-wordpress-plugin.git optipub
cd optipub
composer install
```

Then symlink or copy the directory into `wp-content/plugins/` of a local
WordPress install. The plugin loads its Composer autoloader from
`vendor/autoload.php`, so `composer install` is required before activation.

## Repository layout

| Path                    | Purpose                                             |
| ----------------------- | --------------------------------------------------- |
| `optipub.php`           | Plugin bootstrap and WordPress header               |
| `uninstall.php`         | Cleanup routine run on uninstall                    |
| `inc/`                  | PSR-4 autoloaded classes (`OptiPubWP\`, `OptiPub\`) |
| `admin/`                | Admin screens and partials                          |
| `public/`               | Front-end assets (JS, CSS, images)                  |
| `templates/`            | Theme templates and sync fixtures                   |
| `readme.txt`            | WordPress.org plugin readme (canonical changelog)   |
| `composer.json`         | Dependencies and PSR-4 autoload map                 |
| `.php-cs-fixer.dist.php` | Coding-standards configuration                     |

## Development

Coding standards are enforced with [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer).

```bash
# Check for violations (no changes written)
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php -v --dry-run --using-cache=no

# Auto-fix
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

CI runs the dry-run check on every pull request. See
[CONTRIBUTING.md](CONTRIBUTING.md) for the full contribution workflow.

## Releases and distribution

The published plugin (including a bundled `vendor/` directory) is distributed
through the [WordPress.org plugin SVN repository](https://plugins.svn.wordpress.org/optipub).
This GitHub repository is the source of truth for development and pull requests;
release packaging is handled separately.

The canonical, user-facing changelog lives in [`readme.txt`](readme.txt).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

This plugin bundles [Video.js](https://github.com/videojs/video.js) v4.6.3
(`public/js/video.js`), licensed under Apache 2.0.
