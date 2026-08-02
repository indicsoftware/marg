#!/usr/bin/env bash

set -Eeuo pipefail

wordpress_path="${1:?WordPress document-root path is required}"
release_root="${2:?Extracted release path is required}"
release_sha="${3:?Release commit SHA is required}"
content_path="$wordpress_path/wp-content"

if [[ ! -d "$content_path" ]]; then
	printf 'WordPress content directory does not exist: %s\n' "$content_path" >&2
	exit 1
fi

for required_path in \
	"$release_root/themes/marg" \
	"$release_root/themes/index.php" \
	"$release_root/mu-plugins/10up-plugin" \
	"$release_root/mu-plugins/10up-plugin-loader.php"; do
	if [[ ! -e "$required_path" ]]; then
		printf 'Release is missing: %s\n' "$required_path" >&2
		exit 1
	fi
done

if [[ -e "$release_root/mu-plugins/kinsta-mu-plugins" || -e "$release_root/mu-plugins/kinsta-mu-plugins.php" ]]; then
	printf '%s\n' 'Refusing to deploy a release that contains Kinsta-managed MU plugins.' >&2
	exit 1
fi

if ! command -v wp >/dev/null 2>&1; then
	printf '%s\n' 'Kinsta WP-CLI is required for the deployment verification.' >&2
	exit 1
fi

backup_root="$(mktemp -d "/tmp/marg-deploy-backup-${release_sha}.XXXXXX")"
had_theme=0
had_plugin=0

restore_previous_release() {
	status=$?
	if [[ $status -ne 0 ]]; then
		printf 'Deployment failed; restoring the previous project directories.\n' >&2
		rm -rf -- "$content_path/themes/marg" "$content_path/mu-plugins/10up-plugin"
		if [[ $had_theme -eq 1 ]]; then
			cp -a "$backup_root/themes/marg" "$content_path/themes/"
		fi
		if [[ $had_plugin -eq 1 ]]; then
			cp -a "$backup_root/mu-plugins/10up-plugin" "$content_path/mu-plugins/"
		fi
	fi
	rm -rf -- "$backup_root"
	exit $status
}
trap restore_previous_release EXIT

mkdir -p -- "$backup_root/themes" "$backup_root/mu-plugins"
if [[ -d "$content_path/themes/marg" ]]; then
	cp -a "$content_path/themes/marg" "$backup_root/themes/"
	had_theme=1
fi
if [[ -d "$content_path/mu-plugins/10up-plugin" ]]; then
	cp -a "$content_path/mu-plugins/10up-plugin" "$backup_root/mu-plugins/"
	had_plugin=1
fi

rm -rf -- "$content_path/themes/marg" "$content_path/mu-plugins/10up-plugin"
cp -a "$release_root/themes/marg" "$content_path/themes/"
cp -a "$release_root/mu-plugins/10up-plugin" "$content_path/mu-plugins/"
install -m 0644 "$release_root/themes/index.php" "$content_path/themes/index.php"

for mu_plugin_file in "$release_root/mu-plugins/"*.php; do
	[[ -e "$mu_plugin_file" ]] || continue
	install -m 0644 "$mu_plugin_file" "$content_path/mu-plugins/$(basename "$mu_plugin_file")"
done

for required_file in \
	"$content_path/themes/marg/dist/css/frontend.css" \
	"$content_path/themes/marg/dist/js/frontend.js" \
	"$content_path/themes/marg/vendor/autoload.php" \
	"$content_path/mu-plugins/10up-plugin/dist/js/admin.js" \
	"$content_path/mu-plugins/10up-plugin/vendor/autoload.php"; do
	if [[ ! -f "$required_file" ]]; then
		printf 'Deployed file is missing: %s\n' "$required_file" >&2
		exit 1
	fi
done

current_theme="$(wp --path="$wordpress_path" option get stylesheet --quiet)"
if [[ "$current_theme" == 'marg.bak' ]]; then
	wp --path="$wordpress_path" theme activate marg --quiet
fi

rm -rf -- "$content_path/themes/marg.bak" "$content_path/themes/marg-local-link"

active_theme="$(wp --path="$wordpress_path" option get stylesheet --quiet)"
printf 'Deployed %s; active stylesheet: %s\n' "$release_sha" "$active_theme"

trap - EXIT
rm -rf -- "$backup_root"
