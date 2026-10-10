#!/usr/bin/env bash
#
# Install / update PDF Toolkit on a Linux server (aaPanel, plain Nginx, ...).
#
#   cd /www/wwwroot/ilove-pdf.site && bash scripts/deploy.sh
#
# Safe to run again for every update: pulls the latest code, installs
# dependencies, builds the frontend, migrates the database, rebuilds the
# caches, restarts queue workers and fixes file permissions.
#
# Override the defaults with environment variables, e.g.
#   PHP_BIN=/www/server/php/82/bin/php bash scripts/deploy.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-/www/server/php/83/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
WEB_USER="${WEB_USER:-www}"
BRANCH="${BRANCH:-main}"
SKIP_PULL="${SKIP_PULL:-0}"

say() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31mError: %s\033[0m\n' "$*" >&2; exit 1; }

cd "$APP_DIR"

[ -x "$PHP_BIN" ] || fail "PHP not found at $PHP_BIN (set PHP_BIN=/www/server/php/XX/bin/php)."
[ -n "$COMPOSER_BIN" ] || fail "Composer not found. Install it first (see docs/DEPLOY-AAPANEL.md)."
command -v npm >/dev/null || fail "Node.js / npm not found. Install Node.js 22 (see docs/DEPLOY-AAPANEL.md)."
[ -f .env ] || fail ".env is missing. Run: cp .env.example .env  and edit it first."

"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' || fail "PHP 8.2 or newer is required."

# Put the site into maintenance mode while updating (only once installed).
WAS_DOWN=0
if [ -f vendor/autoload.php ] && [ -f bootstrap/cache/packages.php ]; then
    say "Maintenance mode on"
    "$PHP_BIN" artisan down --retry=30 || true
    WAS_DOWN=1
fi

# Always restore file ownership (commands above run as root) and leave maintenance mode.
finish() {
    say "Fixing permissions"
    mkdir -p public/uploads storage/app/pdf-temp
    chown -R "$WEB_USER:$WEB_USER" "$APP_DIR"
    chmod -R ug+rwX storage bootstrap/cache database public/uploads

    if [ "$WAS_DOWN" = "1" ]; then
        "$PHP_BIN" artisan up || true
    fi
}
trap finish EXIT

if [ "$SKIP_PULL" != "1" ] && [ -d .git ]; then
    # The folder belongs to the web user; allow root to run git in it.
    git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$APP_DIR" \
        || git config --global --add safe.directory "$APP_DIR"

    say "Pulling the latest code ($BRANCH)"
    git pull --ff-only origin "$BRANCH"
fi

say "Installing PHP dependencies"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --no-progress

if ! grep -qE '^APP_KEY=.+' .env; then
    say "Generating APP_KEY"
    "$PHP_BIN" artisan key:generate --force
fi

if grep -qE '^DB_CONNECTION=sqlite' .env && [ ! -f database/database.sqlite ]; then
    say "Creating the SQLite database"
    touch database/database.sqlite
fi

say "Building the frontend (Vite)"
npm ci --no-audit --no-fund
NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=2048}" npm run build

say "Migrating the database"
"$PHP_BIN" artisan migrate --force

say "Caching config, routes, views and events"
"$PHP_BIN" artisan optimize:clear >/dev/null
"$PHP_BIN" artisan optimize

say "Restarting queue workers"
"$PHP_BIN" artisan queue:restart || true

say "Checking server software"
"$PHP_BIN" artisan pdf:doctor || true

say "Done – $(grep -E '^APP_URL=' .env | cut -d= -f2-)"
