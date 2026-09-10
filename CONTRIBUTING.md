# Contributing to OptiPub

Thanks for your interest in improving the OptiPub WordPress plugin. This
document explains how to set up a development environment, the coding
standards we enforce, and how to submit changes.

## Development setup

This repository holds the plugin **source**. Runtime dependencies are managed
by Composer and are **not** committed to Git.

1. Fork and clone the repository:

   ```bash
   git clone https://github.com/<your-user>/optipub-wordpress-plugin.git optipub
   cd optipub
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Place the plugin in a WordPress install (symlink recommended):

   ```bash
   ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/optipub
   ```

4. Activate **OptiPub** in `wp-admin` and configure your API token.

## Coding standards

PHP code follows **PSR-12** plus the project rules defined in
[`.php-cs-fixer.dist.php`](.php-cs-fixer.dist.php), enforced with
[PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer).

Before opening a pull request, run:

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php -v --dry-run --using-cache=no
```

Fix reported issues with:

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

The same dry-run check runs automatically on every pull request via GitHub
Actions; PRs must pass it before merge.

## Pull request workflow

1. Create a topic branch from `main`:

   ```bash
   git checkout -b fix/short-description
   ```

2. Make focused commits with clear messages.
3. Ensure PHP-CS-Fixer passes locally.
4. Do **not** commit `vendor/`, build artifacts, IDE files, or `.DS_Store`
   (they are covered by `.gitignore`).
5. Push your branch and open a pull request against `main`, describing the
   change and how you verified it.

## Reporting issues

Please open a GitHub issue with:

- WordPress and PHP versions
- Plugin version (see the header in `optipub.php` or `Stable tag` in `readme.txt`)
- Clear reproduction steps and expected vs. actual behavior

## Security

Do **not** report security vulnerabilities in public issues. Contact the
maintainers privately at [support@optipub.com](mailto:support@optipub.com).

## License

By contributing, you agree that your contributions are licensed under the
GPL-2.0-or-later license that covers the project (see [LICENSE](LICENSE)).
