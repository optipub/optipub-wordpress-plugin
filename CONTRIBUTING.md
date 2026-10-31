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

## Releasing

Releases to WordPress.org are automated with GitHub Actions. WordPress.org
only accepts plugins through its SVN repository, so SVN is used for releases
only, never for development.

### Releasing a new version

1. Open a pull request that bumps the version in the `optipub.php` header
   (`Version:`) and in `readme.txt` (`Stable tag:`); the two must match.
   Add a changelog entry to `readme.txt`.
2. Merge it into `main`.
3. [Publish a GitHub release](https://github.com/optipub/optipub-wordpress-plugin/releases/new)
   from `main` with the tag `v<version>`, for example `v1.0.26`.

Publishing the release runs the
[Deploy to WordPress.org](.github/workflows/deploy.yml) workflow:

- It runs the Lint checks.
- It checks that the tag matches the plugin version and points to a commit on `main`.
- It installs the production dependencies (`composer install --no-dev`).
- It deploys `trunk`, `tags/<version>` and the `.wordpress-org` assets in one
  SVN commit. Files listed in `.distignore` are left out.
- It attaches `optipub.zip` to the release.

Pre-releases are not deployed. The workflow can also be run by hand from the
Actions tab; it defaults to a dry run, which prepares the SVN commit without
committing it.

### Updating the readme or assets between releases

Plugin page assets (icon, banners, screenshots) live in `.wordpress-org/`,
using the [WordPress.org file names](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/).

Changes to `readme.txt` or `.wordpress-org/` that are merged into `main` are
pushed to WordPress.org right away by the
[Update WordPress.org readme and assets](.github/workflows/wordpress-org-assets.yml)
workflow, for example a new "Tested up to" value. This workflow never pushes
code. It does nothing while the version on `main` has not been released yet,
so readme changes for an upcoming version go out with that release.

### Secrets

Both workflows use the `SVN_USERNAME` and `SVN_PASSWORD` secrets of the
`wordpress-org` environment (repository secrets also work). They are the
WordPress.org account that owns the plugin and its SVN password, which is set
under Account & Security on WordPress.org.

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
