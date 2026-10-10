#!/usr/bin/env bash
#
# Repairs the extension lines of an aaPanel PHP installation:
#   php.ini      – used by the website (PHP-FPM)
#   php-cli.ini  – used on the command line (composer, artisan, cron)
#
# - disables duplicate lines ("PHP Warning: Module "zip" is already loaded")
# - enables fileinfo / zip when the extension file exists but is not loaded
#
# Every changed file is backed up next to it (*.bak-YYYYmmddHHMMSS).
#
#   bash scripts/fix-php-extensions.sh                       # newest aaPanel PHP
#   bash scripts/fix-php-extensions.sh /www/server/php/84
#
set -euo pipefail

REQUIRED="${REQUIRED:-fileinfo zip}"

PHP_DIR="${1:-}"
if [ -z "$PHP_DIR" ]; then
    PHP_DIR="$(ls -1d /www/server/php/[0-9]* 2>/dev/null | sort -t/ -k5 -nr | head -1 || true)"
fi
PHP_DIR="${PHP_DIR%/}"
PHP="$PHP_DIR/bin/php"
[ -x "$PHP" ] || { echo "PHP not found in '$PHP_DIR'. Usage: bash scripts/fix-php-extensions.sh /www/server/php/84" >&2; exit 1; }

if [ -z "${EXT_DIR:-}" ]; then
    EXT_DIR="$(ls -1d "$PHP_DIR"/lib/php/extensions/no-debug-* 2>/dev/null | head -1 || true)"
    [ -n "$EXT_DIR" ] || EXT_DIR="$("$PHP" -n -r 'echo ini_get("extension_dir");' 2>/dev/null)"
fi

# Modules compiled into PHP itself (they must not have an extension= line).
BUILTIN="$("$PHP" -n -m 2>/dev/null | tr 'A-Z' 'a-z')"

# Line numbers of active (not commented) extension= lines for a module.
ext_lines() {
    grep -nE "^[[:space:]]*extension[[:space:]]*=[[:space:]]*[\"']?([^\"']*[/\\\\])?(php_)?$2(\.so|\.dll)?[\"']?[[:space:]]*(;.*)?$" "$1" | cut -d: -f1 || true
}

ext_file() {
    local file
    for file in "$EXT_DIR/$1.so" "$EXT_DIR/php_$1.dll"; do
        if [ -f "$file" ]; then echo "$file"; return; fi
    done
}

loaded_with() {
    "$PHP" -c "$1" -m 2>/dev/null | tr 'A-Z' 'a-z' | grep -qx "$2"
}

duplicates() {
    "$PHP" -c "$1" -d display_startup_errors=1 -v 2>&1 \
        | sed -n 's/.*Module "\([^"]*\)" is already loaded.*/\1/p' | tr 'A-Z' 'a-z' | sort -u || true
}

BACKUP=""
backup_once() {
    if [ -z "$BACKUP" ]; then
        BACKUP="$1.bak-$(date +%Y%m%d%H%M%S)"
        cp -a "$1" "$BACKUP"
    fi
}

CHANGED=0

for INI in "$PHP_DIR/etc/php.ini" "$PHP_DIR/etc/php-cli.ini"; do
    [ -f "$INI" ] || continue
    echo "== $INI"
    BACKUP=""

    for mod in $(duplicates "$INI"); do
        lines=($(ext_lines "$INI" "$mod"))
        keep=1
        if echo "$BUILTIN" | grep -qx "$mod"; then keep=0; fi

        if [ "${#lines[@]}" -le "$keep" ]; then
            echo "   '$mod' is loaded twice, but no removable extension= line was found – check this file by hand."
            continue
        fi

        n=0
        for line in "${lines[@]}"; do
            n=$((n + 1))
            if [ "$n" -gt "$keep" ]; then
                backup_once "$INI"
                sed -i "${line}s/^/;/" "$INI"
                echo "   disabled duplicate '$mod' (line $line)"
            fi
        done
    done

    for mod in $REQUIRED; do
        if loaded_with "$INI" "$mod"; then
            echo "   '$mod' ok"
            continue
        fi

        file="$(ext_file "$mod")"
        if [ -n "$file" ]; then
            backup_once "$INI"
            printf '\nextension = %s\n' "$file" >> "$INI"
            echo "   enabled '$mod' ($file)"
        else
            echo "   '$mod' is NOT installed – aaPanel → App Store → PHP → Setting → Install extensions → $mod"
        fi
    done

    if [ -n "$BACKUP" ]; then
        CHANGED=1
        echo "   backup: $BACKUP"
    fi
done

echo
echo "Command line PHP now:"
"$PHP" -m 2>&1 | grep -iE "warning|^($(echo "$REQUIRED" | tr ' ' '|'))\$" || true

if [ "$CHANGED" = "1" ]; then
    VERSION="$(basename "$PHP_DIR")"
    if [ -x "/etc/init.d/php-fpm-$VERSION" ]; then
        "/etc/init.d/php-fpm-$VERSION" reload >/dev/null 2>&1 || "/etc/init.d/php-fpm-$VERSION" restart
        echo "PHP-FPM $VERSION reloaded."
    else
        echo "Now restart PHP $VERSION in aaPanel (App Store → PHP → Service → Restart)."
    fi
fi
