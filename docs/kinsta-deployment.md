# Kinsta deployment

This repository contains `wp-content`, not the WordPress core directory. The deployment workflow therefore builds the project-owned theme and custom MU plugin, then applies only those files inside the live site's `wp-content` directory.

## What the workflow does

`.github/workflows/build-and-deploy-kinsta.yml` runs on pull requests for build validation and on pushes to `main` for production deployment:

1. Installs Node.js 24 and PHP 8.3.
2. Runs `npm ci` with lifecycle scripts enabled so 10up Toolkit's native dependencies (including `sharp`) are installed correctly.
3. Builds all npm workspaces with 10up Toolkit.
4. Runs production Composer installs for `themes/marg` and `mu-plugins/10up-plugin`.
5. Creates a checksum-protected release artifact containing compiled assets and Composer `vendor/` directories.
6. Uploads the artifact to Kinsta over SSH.
7. Replaces the project-owned `themes/marg` and `mu-plugins/10up-plugin` directories with a verified release.
8. Activates `marg` automatically only when the old `marg.bak` theme is active, then removes the old backup directory and local-link path.

The workflow does not touch WordPress core, the database, uploads, installed plugins, other themes, or Kinsta-managed MU plugins.

## GitHub environment configuration

Create a protected GitHub environment named `production`. Add these repository or environment secrets:

| Secret                   | Value                                                                              |
| ------------------------ | ---------------------------------------------------------------------------------- |
| `KINSTA_SSH_HOST`        | Kinsta host/IP from the site's Primary SFTP/SSH details                            |
| `KINSTA_SSH_PORT`        | Kinsta's environment-specific SSH port                                             |
| `KINSTA_SSH_USER`        | Kinsta's primary SFTP/SSH username                                                 |
| `KINSTA_SSH_PRIVATE_KEY` | A dedicated, unencrypted deploy private key                                        |
| `KINSTA_SSH_KNOWN_HOSTS` | The verified `known_hosts` line(s) for the Kinsta host                             |
| `KINSTA_WP_PATH`         | Absolute WordPress document-root path from MyKinsta, such as `/www/example/public` |

Add the matching public key to the Kinsta SSH account. Do not commit any of these values to the repository.

Kinsta's SSH details are environment-specific, so use the values from the target environment's MyKinsta **Info** page. If an SSH IP allowlist is enabled, GitHub-hosted runners will not be able to connect unless the allowlist is adjusted.

## First deployment checklist

Before enabling the first production run:

1. Create a fresh Kinsta backup.
2. Confirm `KINSTA_WP_PATH` points to the live site's `public` directory.
3. Confirm the SSH host key independently before saving `KINSTA_SSH_KNOWN_HOSTS`.
4. Add the secrets to the `production` environment.
5. Enable required reviewers for the `production` environment if deployments need an approval gate.
6. Run the workflow from `main` and verify:
   - the active stylesheet is `marg` when the old `marg.bak` theme was active;
   - the public site references `/wp-content/themes/marg/dist/`;
   - the theme and custom MU plugin load without PHP errors.

## Rollback

Use **Actions → Build and deploy to Kinsta → Run workflow** and enter a known-good commit SHA from a release that has already passed this workflow in the `ref` field. The workflow rebuilds that exact commit and deploys it through the same protected environment.

Kinsta backups remain the rollback mechanism for database or media changes. GitHub workflow rollback only restores repository-owned code and built assets.

Reference: [Kinsta GitHub Actions integration](https://kinsta.com/docs/wordpress-hosting/site-management/wordpress-github-cicd/) and [Kinsta SSH](https://kinsta.com/docs/wordpress-hosting/connect-to-ssh/).
