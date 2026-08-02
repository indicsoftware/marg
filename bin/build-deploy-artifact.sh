#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(git rev-parse --show-toplevel)"
cd "$repository_root"

release_sha="${1:-$(git rev-parse HEAD)}"
output_dir="${2:-$repository_root/release}"

if ! git rev-parse --verify "${release_sha}^{commit}" >/dev/null 2>&1; then
	printf 'Unknown release commit: %s\n' "$release_sha" >&2
	exit 1
fi

if [[ "$(git rev-parse HEAD)" != "$release_sha" ]]; then
	printf 'The checked-out commit (%s) does not match the release commit (%s).\n' "$(git rev-parse HEAD)" "$release_sha" >&2
	exit 1
fi

for required_command in composer find git npm php tar; do
	if ! command -v "$required_command" >/dev/null 2>&1; then
		printf 'Required command is not available: %s\n' "$required_command" >&2
		exit 1
	fi
done

rm -rf -- "$output_dir"
mkdir -p -- "$output_dir/staging"

printf '%s\n' 'Building JavaScript and CSS workspaces...'
npm run build

printf '%s\n' 'Installing production Composer dependencies...'
composer install \
	--working-dir=themes/marg \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-progress \
	--optimize-autoloader
composer install \
	--working-dir=mu-plugins/10up-plugin \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-progress \
	--optimize-autoloader

printf '%s\n' 'Checking PHP syntax...'
while IFS= read -r -d '' php_file; do
	php -l "$php_file" >/dev/null
done < <(find themes/marg mu-plugins/10up-plugin -type f -name '*.php' -print0)

for required_file in \
	themes/marg/dist/css/frontend.css \
	themes/marg/dist/js/frontend.js \
	themes/marg/vendor/autoload.php \
	mu-plugins/10up-plugin/dist/js/admin.js \
	mu-plugins/10up-plugin/vendor/autoload.php; do
	if [[ ! -f "$required_file" ]]; then
		printf 'Build output is missing: %s\n' "$required_file" >&2
		exit 1
	fi
done

git archive --format=tar "$release_sha" | tar -xf - -C "$output_dir/staging"

# Kinsta owns this MU plugin. Keep its source in the repository for reference,
# but never overwrite the platform-managed copy during an application deploy.
rm -rf -- \
	"$output_dir/staging/mu-plugins/kinsta-mu-plugins" \
	"$output_dir/staging/mu-plugins/kinsta-mu-plugins.php"

mkdir -p -- \
	"$output_dir/staging/themes/marg" \
	"$output_dir/staging/mu-plugins/10up-plugin"
cp -a themes/marg/dist "$output_dir/staging/themes/marg/"
cp -a themes/marg/vendor "$output_dir/staging/themes/marg/"
cp -a mu-plugins/10up-plugin/dist "$output_dir/staging/mu-plugins/10up-plugin/"
cp -a mu-plugins/10up-plugin/vendor "$output_dir/staging/mu-plugins/10up-plugin/"

archive_path="$output_dir/wp-content-${release_sha}.tar.gz"
checksum_path="${archive_path}.sha256"
tar -czf "$archive_path" -C "$output_dir/staging" .

(
	cd "$(dirname "$archive_path")"
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$(basename "$archive_path")"
	else
		shasum -a 256 "$(basename "$archive_path")"
	fi
) > "$checksum_path"

printf 'release_sha=%s\n' "$release_sha"
printf 'archive=%s\n' "$archive_path"
printf 'checksum=%s\n' "$checksum_path"
