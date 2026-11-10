#!/bin/bash
# =====================================================================
# cPanel deployment (called from .cpanel.yml)
#   bin/deploy.sh <repository path> <destination path>
#
# Keeps everything the SERVER owns, which a plain copy would destroy:
#   - config/config.php, storage/ (logs, sessions, installed.lock)
#   - .well-known/ (AutoSSL / Let's Encrypt validation)
#   - php.ini, .user.ini, error_log (cPanel MultiPHP INI Editor / PHP)
#   - cPanel-generated blocks inside .htaccess (MultiPHP PHP-version handler)
# =====================================================================
set -euo pipefail

REPO="${1%/}"
DEST="${2%/}"
[ -d "$REPO/app" ] || { echo "Repository not found: $REPO" >&2; exit 1; }
mkdir -p "$DEST"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

HTFILES=".htaccess public/.htaccess"

# 1. Save cPanel-generated blocks (e.g. "# php -- BEGIN cPanel-generated handler") from the live .htaccess files
for f in $HTFILES; do
    key="${f//\//_}"
    if [ -f "$DEST/$f" ]; then
        awk '/^# (php -- )?BEGIN cPanel-generated/{on=1} on{print} /^# (php -- )?END cPanel-generated/{on=0}' "$DEST/$f" > "$TMP/$key" || true
    fi
done

# 2. Copy the code. Without -t, changed files get a fresh modification time, so LiteSpeed and
#    OPcache notice them immediately; --checksum copies only files whose content changed.
/usr/bin/rsync -rlp --checksum --delete \
    --exclude='.git/' --exclude='.gitignore' --exclude='.cpanel.yml' \
    --exclude='node_modules/' --exclude='resources/' --exclude='scripts/' --exclude='docs/' \
    --exclude='package.json' --exclude='package-lock.json' --exclude='hallsons.htm' \
    --exclude='/config/config.php' --exclude='/storage/' \
    --exclude='.well-known/' --exclude='php.ini' --exclude='.user.ini' --exclude='error_log' \
    --exclude='cgi-bin/' \
    "$REPO/" "$DEST/"

# 3. Put the cPanel-generated blocks back at the top of each .htaccess (where cPanel writes them)
for f in $HTFILES; do
    key="${f//\//_}"
    if [ -s "$TMP/$key" ] && [ -f "$DEST/$f" ]; then
        first="$(head -n 1 "$TMP/$key")"
        if ! grep -qxF "$first" "$DEST/$f"; then
            { cat "$TMP/$key"; echo; cat "$DEST/$f"; } > "$TMP/new" && cat "$TMP/new" > "$DEST/$f"
            echo "Kept cPanel block in $f"
        fi
    fi
done

# 4. Writable folders and their protection
mkdir -p "$DEST/storage/logs" "$DEST/storage/mail" "$DEST/storage/cache"
chmod 755 "$DEST/storage" "$DEST/storage/logs" "$DEST/storage/mail" "$DEST/storage/cache"
cp -f "$REPO/storage/.htaccess" "$DEST/storage/.htaccess"

# 5. Record what is deployed (shown on System Health)
{
    echo "commit=$(git -C "$REPO" rev-parse --short HEAD 2>/dev/null || echo unknown)"
    echo "branch=$(git -C "$REPO" rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
    echo "time=$(date -u '+%Y-%m-%d %H:%M:%S') UTC"
} > "$DEST/storage/deployed.txt"

echo "Deployed $(cat "$DEST/storage/deployed.txt" | tr '\n' ' ')"
