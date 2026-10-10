#!/usr/bin/env bash
#
# Installs a self-hosted LibreTranslate server (open source, no API costs) in
# Docker for the PDF Translator and connects it to the app.
#
#   cd /www/wwwroot/ilove-pdf.site && bash scripts/install-translation.sh
#
# The server only listens on 127.0.0.1, so it is not reachable from outside.
# The first start downloads the language models (about 3–4 GB) – this can
# take 10–30 minutes. Running the script again keeps the downloaded models.
#
# Options (environment variables):
#   LANGS=en,ur,hi,ar     languages to load (default: all 16 languages of the app;
#                         use fewer on servers with less than 4 GB RAM)
#   LT_PORT=5000          local port
#
set -euo pipefail

say() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m%s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31mError: %s\033[0m\n' "$*" >&2; exit 1; }

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
WEB_USER="${WEB_USER:-www}"
# Argos model codes ("zh" = Simplified Chinese, exposed by the API as zh-Hans).
LANGS="${LANGS:-en,ur,hi,ar,zh,es,fr,de,it,pt,ru,tr,bn,fa,id,ms}"
LT_PORT="${LT_PORT:-5000}"
CONTAINER="libretranslate"
IMAGE="libretranslate/libretranslate:latest"
WAIT_MINUTES="${WAIT_MINUTES:-45}"

# Same PHP detection as deploy.sh: the website's PHP, else the newest aaPanel PHP.
site_php() {
    local conf version
    for conf in "${VHOST_DIR:-/www/server/panel/vhost/nginx}"/*.conf; do
        [ -f "$conf" ] && grep -qF "$APP_DIR" "$conf" || continue
        version="$(grep -oE 'enable-php-[0-9]+\.conf' "$conf" | head -1 | grep -oE '[0-9]+' || true)"
        if [ -n "$version" ] && [ -x "/www/server/php/$version/bin/php" ]; then
            echo "/www/server/php/$version/bin/php"
            return
        fi
    done
}

detect_php() {
    local candidate
    for candidate in $(ls -1d /www/server/php/[0-9]*/bin/php 2>/dev/null | sort -t/ -k5 -nr); do
        if "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' >/dev/null 2>&1; then
            echo "$candidate"
            return
        fi
    done
    command -v php 2>/dev/null || true
}

set_env() {
    if grep -qE "^$1=" .env; then
        sed -i "s#^$1=.*#$1=$2#" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
    echo "   $1=$2"
}

cd "$APP_DIR"

[ "$(id -u)" = "0" ] || fail "Please run this script as root."
[ -f .env ] || fail ".env is missing – deploy the app first (bash scripts/deploy.sh)."
command -v curl >/dev/null || fail "curl is missing: apt-get install -y curl"

PHP_BIN="${PHP_BIN:-$(site_php)}"
PHP_BIN="${PHP_BIN:-$(detect_php)}"
[ -n "$PHP_BIN" ] && [ -x "$PHP_BIN" ] || fail "PHP not found. Run with PHP_BIN=/www/server/php/XX/bin/php"

# ---------------------------------------------------------------------------
# 1. Resources
# ---------------------------------------------------------------------------
LANG_COUNT="$(echo "$LANGS" | tr ',' '\n' | grep -c .)"
RAM_MB="$(awk '/MemTotal/ {print int($2 / 1024)}' /proc/meminfo)"
SWAP_MB="$(awk '/SwapTotal/ {print int($2 / 1024)}' /proc/meminfo)"
DISK_GB="$(df -Pk /var/lib 2>/dev/null | awk 'NR==2 {print int($4 / 1048576)}')"

say "Server: ${RAM_MB} MB RAM, ${SWAP_MB} MB swap, ${DISK_GB} GB free disk – loading ${LANG_COUNT} languages"
if [ "$DISK_GB" -lt 8 ]; then
    fail "At least 8 GB of free disk space is needed for Docker and the language models."
fi
if [ "$RAM_MB" -lt 3500 ] && [ "$LANG_COUNT" -gt 6 ]; then
    warn "Less than 4 GB RAM: translating between many languages may run out of memory."
    warn "Tip: load fewer languages, e.g.  LANGS=en,ur,hi,ar bash scripts/install-translation.sh"
fi

# ---------------------------------------------------------------------------
# 2. Docker
# ---------------------------------------------------------------------------
if ! command -v docker >/dev/null; then
    if command -v apt-get >/dev/null; then
        say "Installing Docker"
        DEBIAN_FRONTEND=noninteractive apt-get update -q
        DEBIAN_FRONTEND=noninteractive apt-get install -y -q docker.io
    else
        fail "Docker is not installed. Install it in aaPanel → App Store → Docker, then run this script again."
    fi
fi
systemctl enable --now docker >/dev/null 2>&1 || service docker start >/dev/null 2>&1 || true
docker info >/dev/null 2>&1 || fail "Docker is installed but not running (systemctl status docker)."

# ---------------------------------------------------------------------------
# 3. LibreTranslate container
# ---------------------------------------------------------------------------
THREADS="$(nproc 2>/dev/null || echo 2)"
[ "$THREADS" -gt 4 ] && THREADS=4

say "Starting LibreTranslate ($IMAGE)"
docker pull -q "$IMAGE" >/dev/null
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d \
    --name "$CONTAINER" \
    --restart unless-stopped \
    -p "127.0.0.1:${LT_PORT}:5000" \
    -v libretranslate_models:/home/libretranslate/.local \
    -e LT_LOAD_ONLY="$LANGS" \
    -e LT_UPDATE_MODELS=true \
    -e LT_DISABLE_WEB_UI=true \
    -e LT_THREADS="$THREADS" \
    "$IMAGE" >/dev/null

say "Waiting for the language models (first start: 10–30 minutes)…"
URL="http://127.0.0.1:${LT_PORT}"
DEADLINE=$(( $(date +%s) + WAIT_MINUTES * 60 ))
READY=0
while [ "$(date +%s)" -lt "$DEADLINE" ]; do
    if [ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null)" != "true" ]; then
        docker logs --tail 30 "$CONTAINER" 2>&1 || true
        fail "The LibreTranslate container stopped. See the log above."
    fi

    if LANGUAGES="$(curl -fsS --max-time 5 "$URL/languages" 2>/dev/null)" && echo "$LANGUAGES" | grep -q '"code"'; then
        READY=1
        break
    fi

    printf '   %s  %s\n' "$(date +%H:%M:%S)" "$(docker logs --tail 1 "$CONTAINER" 2>&1 | cut -c1-100)"
    sleep 30
done

if [ "$READY" != "1" ]; then
    warn "LibreTranslate is still downloading models. The script stops waiting, the download continues."
    warn "Watch it with:  docker logs -f $CONTAINER   – then run this script again to finish the setup."
    exit 1
fi

AVAILABLE="$(echo "$LANGUAGES" | grep -oE '"code" *: *"[^"]+"' | sed -E 's/.*"([^"]+)"$/\1/' | tr '\n' ' ')"
echo "   Languages: $AVAILABLE"

# ---------------------------------------------------------------------------
# 4. Connect the app
# ---------------------------------------------------------------------------
say "Connecting the PDF Translator"
set_env TRANSLATION_DRIVER libretranslate
set_env LIBRETRANSLATE_URL "$URL"
chown "$WEB_USER:$WEB_USER" .env
sudo -u "$WEB_USER" "$PHP_BIN" artisan optimize:clear >/dev/null
sudo -u "$WEB_USER" "$PHP_BIN" artisan optimize >/dev/null

say "Test translation (English → Urdu)"
curl -fsS --max-time 60 -X POST "$URL/translate" \
    -H 'Content-Type: application/json' \
    -d '{"q":"Hello, how are you?","source":"en","target":"ur","format":"text"}' || warn "Test translation failed – check: docker logs $CONTAINER"
echo

say "Done – the admin dashboard now shows “translation: libretranslate”."
echo "Useful commands:"
echo "   docker logs -f $CONTAINER        # log"
echo "   docker restart $CONTAINER        # restart"
echo "   LANGS=en,ur,hi bash scripts/install-translation.sh   # change languages"
