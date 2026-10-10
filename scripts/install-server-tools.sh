#!/usr/bin/env bash
#
# Installs the optional open-source programs used by the server engine and
# connects them to the app:
#   qpdf, Ghostscript, Poppler (pdftotext, pdftoppm, pdfimages, pdftohtml),
#   Tesseract OCR + 16 languages, LibreOffice (headless)
#
# It also enables the PHP functions they need (proc_open, putenv,
# pcntl_signal, pcntl_alarm) for the website's PHP version, writes the
# program paths into .env and refreshes the detection in the admin panel.
#
#   cd /www/wwwroot/ilove-pdf.site && bash scripts/install-server-tools.sh
#
# Options (environment variables):
#   SKIP_LIBREOFFICE=1   do not install LibreOffice (~300 MB)
#   PHP_BIN=/www/server/php/82/bin/php
#
set -euo pipefail

say() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m%s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31mError: %s\033[0m\n' "$*" >&2; exit 1; }

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
WEB_USER="${WEB_USER:-www}"
SKIP_LIBREOFFICE="${SKIP_LIBREOFFICE:-0}"
OCR_LANGS="eng urd hin ara chi-sim spa fra deu ita por rus tur ben fas ind msa"
NEEDED_FUNCTIONS="proc_open,putenv,pcntl_signal,pcntl_alarm"

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

cd "$APP_DIR"

[ "$(id -u)" = "0" ] || fail "Please run this script as root."
[ -f .env ] || fail ".env is missing – deploy the app first (bash scripts/deploy.sh)."

PHP_BIN="${PHP_BIN:-$(site_php)}"
PHP_BIN="${PHP_BIN:-$(detect_php)}"
[ -n "$PHP_BIN" ] && [ -x "$PHP_BIN" ] || fail "PHP not found. Run with PHP_BIN=/www/server/php/XX/bin/php"
PHP_DIR="$(dirname "$(dirname "$PHP_BIN")")"
say "Using PHP $("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null) ($PHP_BIN)"

# ---------------------------------------------------------------------------
# 1. Packages
# ---------------------------------------------------------------------------
if command -v apt-get >/dev/null; then
    export DEBIAN_FRONTEND=noninteractive

    say "Updating package lists"
    apt-get update -q

    available() {
        local pkg list=""
        for pkg in "$@"; do
            if apt-cache show "$pkg" >/dev/null 2>&1; then list="$list $pkg"; else warn "  package $pkg is not available – skipped" >&2; fi
        done
        echo "$list"
    }

    say "Installing qpdf, Ghostscript, Poppler and fonts"
    apt-get install -y -q qpdf ghostscript poppler-utils fonts-dejavu-core $(available fonts-noto-core)

    say "Installing Tesseract OCR and language data"
    LANG_PACKAGES=""
    for lang in $OCR_LANGS; do LANG_PACKAGES="$LANG_PACKAGES tesseract-ocr-$lang"; done
    apt-get install -y -q tesseract-ocr $(available $LANG_PACKAGES)

    if [ "$SKIP_LIBREOFFICE" != "1" ]; then
        say "Installing LibreOffice (headless) – this can take a few minutes"
        if apt-cache show libreoffice-writer-nogui >/dev/null 2>&1; then
            apt-get install -y -q --no-install-recommends libreoffice-writer-nogui libreoffice-calc-nogui libreoffice-impress-nogui
        else
            apt-get install -y -q --no-install-recommends libreoffice-writer libreoffice-calc libreoffice-impress
        fi
    fi
elif command -v dnf >/dev/null || command -v yum >/dev/null; then
    PM="$(command -v dnf || command -v yum)"
    say "Installing packages with $(basename "$PM")"
    "$PM" install -y epel-release || true
    "$PM" install -y qpdf ghostscript poppler-utils tesseract dejavu-sans-fonts
    for lang in $OCR_LANGS; do
        "$PM" install -y "tesseract-langpack-${lang//-/_}" >/dev/null 2>&1 || warn "  tesseract-langpack-$lang not available – skipped"
    done
    if [ "$SKIP_LIBREOFFICE" != "1" ]; then
        "$PM" install -y libreoffice-headless libreoffice-writer libreoffice-calc libreoffice-impress || warn "LibreOffice is not available for this OS – PowerPoint → PDF stays unavailable."
    fi
else
    fail "No apt-get, dnf or yum found. Install qpdf, ghostscript, poppler-utils, tesseract-ocr and libreoffice manually."
fi

# ---------------------------------------------------------------------------
# 2. PHP functions needed to run the programs (website + command line)
# ---------------------------------------------------------------------------
say "Enabling the PHP functions the programs need ($NEEDED_FUNCTIONS)"
read -r -d '' ENABLE_FUNCTIONS <<'PHP' || true
$file = $argv[1];
$remove = explode(',', $argv[2]);
$lines = file($file);
$changed = false;
foreach ($lines as $i => $line) {
    if (preg_match('/^\s*disable_functions\s*=\s*(.*?)\s*$/', $line, $m)) {
        $current = array_values(array_filter(array_map('trim', explode(',', trim($m[1], " \"'"))), 'strlen'));
        $keep = array_values(array_diff($current, $remove));
        if ($keep !== $current) {
            $lines[$i] = 'disable_functions = '.implode(',', $keep).PHP_EOL;
            $changed = true;
        }
    }
}
if ($changed) {
    copy($file, $file.'.bak-'.date('YmdHis'));
    file_put_contents($file, implode('', $lines));
    echo "updated";
} else {
    echo "ok";
}
PHP

PHP_CHANGED=0
for ini in "$PHP_DIR/etc/php.ini" "$PHP_DIR/etc/php-cli.ini"; do
    [ -f "$ini" ] || continue
    result="$("$PHP_BIN" -n -r "$ENABLE_FUNCTIONS" -- "$ini" "$NEEDED_FUNCTIONS")"
    echo "   $ini: $result"
    [ "$result" = "updated" ] && PHP_CHANGED=1
done

if [ "$PHP_CHANGED" = "1" ]; then
    VERSION="$(basename "$PHP_DIR")"
    if [ -x "/etc/init.d/php-fpm-$VERSION" ]; then
        "/etc/init.d/php-fpm-$VERSION" reload >/dev/null 2>&1 || "/etc/init.d/php-fpm-$VERSION" restart
        echo "   PHP-FPM $VERSION reloaded."
    else
        warn "   Restart PHP $VERSION in aaPanel (App Store → PHP → Service → Restart)."
    fi
fi

# ---------------------------------------------------------------------------
# 3. Program paths in .env
# ---------------------------------------------------------------------------
say "Writing program paths to .env"
set_path() {
    local key="$1" path="" name
    shift
    for name in "$@"; do
        path="$(command -v "$name" 2>/dev/null || true)"
        [ -n "$path" ] && break
    done

    if [ -z "$path" ]; then
        warn "   $key: not found"
        return
    fi

    if grep -qE "^${key}=.+" .env; then
        echo "   $key already set: $(grep -E "^${key}=" .env | cut -d= -f2-)"
    elif grep -qE "^${key}=" .env; then
        sed -i "s#^${key}=.*#${key}=${path}#" .env
        echo "   $key=$path"
    else
        echo "${key}=${path}" >> .env
        echo "   $key=$path"
    fi
}

set_path QPDF_PATH qpdf
set_path GHOSTSCRIPT_PATH gs
set_path PDFTOTEXT_PATH pdftotext
set_path PDFTOPPM_PATH pdftoppm
set_path PDFIMAGES_PATH pdfimages
set_path PDFTOHTML_PATH pdftohtml
set_path LIBREOFFICE_PATH soffice libreoffice
set_path TESSERACT_PATH tesseract

# ---------------------------------------------------------------------------
# 4. Refresh caches and the detection shown in Admin → System
# ---------------------------------------------------------------------------
say "Refreshing the configuration and the software detection"
chown "$WEB_USER:$WEB_USER" .env
sudo -u "$WEB_USER" "$PHP_BIN" artisan optimize:clear >/dev/null
sudo -u "$WEB_USER" "$PHP_BIN" artisan optimize >/dev/null
sudo -u "$WEB_USER" "$PHP_BIN" artisan pdf:doctor || true

for ini in "$APP_DIR/.user.ini" "$APP_DIR/public/.user.ini"; do
    if [ -f "$ini" ] && grep -q open_basedir "$ini"; then
        warn "Note: $ini restricts PHP with open_basedir. If tools still show as unavailable, turn off “Anti-XSS attack” in aaPanel → Website → Site directory."
    fi
done

say "Done – open Admin → System and click “Detect software again” if the page was already open."
