#!/usr/bin/env bash
#
# Incident cleanup for the Laravel sites on ciba-dev (102.219.40.228).
#
# What it does, per site:
#   - moves webshells / dropped files into a quarantine folder (nothing is deleted)
#   - restores tracked files that were tampered with (public/index.php, .htaccess, ...)
#   - reinstalls vendor/ from composer.lock and clears every Laravel cache
#   - sets APP_DEBUG=false (and optionally rotates APP_KEY)
#   - installs the nginx hardening snippet (deploy/nginx/security.conf)
# Server-wide it reports persistence (cron, keys, systemd, prepend files, tmp
# executables) and can close database/cache ports to the internet.
#
# Usage (as root, from anywhere):
#   sudo bash cleanup-compromise.sh                      # dry run: report only
#   sudo bash cleanup-compromise.sh --apply              # clean the sites
#   sudo bash cleanup-compromise.sh --apply --rotate-app-key --lock-ports
#   sudo bash cleanup-compromise.sh --apply --sites "controldesk.ncloud.africa storm-pm.ncloud.africa"
#
# Before --apply: take a DB backup and, ideally, a VM snapshot.
# Run it once without --apply and read the output first.

set -uo pipefail

APPLY=0
ROTATE_KEY=0
LOCK_PORTS=0
HOME_DIR=/home/forge
OWNER=forge
SITES="controldesk.ncloud.africa blackwidow-pm.ncloud.africa crm-renforcer.ncloud.africa renforcer.ncloud.africa storm-pm.ncloud.africa"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NGINX_SNIPPET="$SCRIPT_DIR/../nginx/security.conf"

while [ $# -gt 0 ]; do
    case "$1" in
        --apply) APPLY=1 ;;
        --rotate-app-key) ROTATE_KEY=1 ;;
        --lock-ports) LOCK_PORTS=1 ;;
        --sites) SITES="$2"; shift ;;
        *) echo "Unknown option: $1"; exit 2 ;;
    esac
    shift
done

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)."; exit 1; }

STAMP="$(date +%Y%m%d-%H%M%S)"
QUARANTINE="/root/incident-$STAMP"
PHP_LIKE='.*\.\(php[0-9]*\|phtml\|phar\|pht\|phps\|inc\|suspected\)$'

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
warn() { printf '\033[33m!! %s\033[0m\n' "$*"; }
run()  { if [ "$APPLY" -eq 1 ]; then "$@"; else echo "   [dry-run] $*"; fi; }
as_owner() { sudo -u "$OWNER" -H bash -lc "$1"; }

# Move a file into the quarantine tree, preserving its path, and neuter it.
quarantine() {
    local f="$1" dest="$QUARANTINE/files$1"
    if [ "$APPLY" -eq 1 ]; then
        mkdir -p "$(dirname "$dest")"
        mv -f -- "$f" "$dest" && chmod 000 "$dest"
    else
        echo "   [dry-run] quarantine $f"
    fi
}

[ "$APPLY" -eq 1 ] && { mkdir -p "$QUARANTINE"; chmod 700 "$QUARANTINE"; exec > >(tee -a "$QUARANTINE/cleanup.log") 2>&1; }
echo "Mode: $([ "$APPLY" -eq 1 ] && echo APPLY || echo DRY-RUN)   Quarantine: $QUARANTINE"

# ---------------------------------------------------------------------------
say "Scanning every site in $HOME_DIR for the dropper kit"
for d in "$HOME_DIR"/*/; do
    d="${d%/}"
    [ -d "$d" ] || continue
    n_up=$(find "$d/storage/app/public" -type f -iregex "$PHP_LIKE" 2>/dev/null | wc -l)
    n_pub=$(find "$d/public" -path "$d/public/storage" -prune -o -type f -iregex "$PHP_LIKE" ! -path "$d/public/index.php" -print 2>/dev/null | wc -l)
    kit=$(grep -ls 'suspected' "$d/.htaccess" "$d/public/.htaccess" 2>/dev/null | wc -l)
    idx=$( [ -f "$d/public/index.php" ] && grep -qE 'goto [A-Za-z0-9_]{4,};|eval\(|base64_decode\(|gzinflate\(' "$d/public/index.php" && echo INFECTED || echo ok)
    if [ "$n_up" -gt 0 ] || [ "$n_pub" -gt 0 ] || [ "$kit" -gt 0 ] || [ "$idx" = INFECTED ]; then
        warn "$(basename "$d"): php-in-uploads=$n_up php-in-public=$n_pub htaccess-kit=$kit index.php=$idx"
    fi
done
echo "(Sites flagged above but not in --sites need the same treatment.)"

# ---------------------------------------------------------------------------
for site in $SITES; do
    dir="$HOME_DIR/$site"
    say "Site: $site"
    [ -d "$dir/.git" ] || { warn "$dir is not a git checkout, skipping"; continue; }
    cd "$dir" || continue

    if [ "$APPLY" -eq 1 ]; then
        mkdir -p "$QUARANTINE/evidence/$site"
        as_owner "cd '$dir' && git status --porcelain --ignored=no" > "$QUARANTINE/evidence/$site/git-status.txt"
        as_owner "cd '$dir' && git diff --binary" > "$QUARANTINE/evidence/$site/git-diff.patch"
    fi

    echo "-- PHP-like files in uploaded storage"
    find "$dir/storage/app/public" -type f \( -iregex "$PHP_LIKE" -o -name '.htaccess' \) -print0 2>/dev/null |
        while IFS= read -r -d '' f; do quarantine "$f"; done

    echo "-- PHP-like files in public/ (except index.php)"
    find "$dir/public" -path "$dir/public/storage" -prune -o -type f \( -iregex "$PHP_LIKE" -o -name '*.dat' -o -name '.htaccess' \) \
        ! -path "$dir/public/index.php" -print0 2>/dev/null |
        while IFS= read -r -d '' f; do
            # Keep .htaccess files that are tracked in git (Laravel ships public/.htaccess).
            if as_owner "cd '$dir' && git ls-files --error-unmatch '${f#$dir/}'" >/dev/null 2>&1; then continue; fi
            quarantine "$f"
        done

    echo "-- Untracked files dropped into the code tree (not gitignored; check the dry run for legit ones)"
    as_owner "cd '$dir' && git ls-files --others --exclude-standard -z" |
        while IFS= read -r -d '' rel; do
            case "$rel" in
                .env|.env.*) echo "   keeping secret file $rel (outside web root)"; continue ;;
                _quarantine_*) continue ;;
            esac
            quarantine "$dir/$rel"
        done
    run find "$dir/public" "$dir/storage/app/public" -mindepth 1 -type d -empty -delete

    echo "-- Restore tracked files that were modified"
    as_owner "cd '$dir' && git -c core.autocrlf=false diff --name-only" | sed 's/^/   /'
    run as_owner "cd '$dir' && git checkout -- ."

    echo "-- Leftover quarantine from January (owned by root, inside the site)"
    for q in "$dir"/_quarantine_*; do
        [ -e "$q" ] && { run mkdir -p "$QUARANTINE/files$dir"; run mv "$q" "$QUARANTINE/files$q"; }
    done

    echo "-- APP_DEBUG=false"
    if grep -q '^APP_DEBUG=true' .env; then
        run sed -i 's/^APP_DEBUG=true/APP_DEBUG=false/' .env
    fi

    if [ "$ROTATE_KEY" -eq 1 ]; then
        echo "-- Rotating APP_KEY (logs every user out)"
        run as_owner "cd '$dir' && php artisan key:generate --force"
    fi

    echo "-- Reinstall vendor/ from composer.lock and clear caches"
    if [ -f composer.lock ] && [ -d vendor ]; then
        if [ "$APPLY" -eq 1 ]; then
            mkdir -p "$QUARANTINE/files$dir"
            mv vendor "$QUARANTINE/files$dir/vendor"
            if ! as_owner "cd '$dir' && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader"; then
                warn "composer install failed for $site; putting the old vendor/ back so the site stays up"
                rm -rf vendor && mv "$QUARANTINE/files$dir/vendor" vendor
            fi
        else
            echo "   [dry-run] move vendor/ to quarantine, then composer install --no-dev"
        fi
    fi
    run as_owner "cd '$dir' && php artisan optimize:clear && rm -f storage/framework/views/*.php && php artisan config:cache"

    echo "-- nginx hardening"
    conf_dir="/etc/nginx/forge-conf/$site/server"
    if [ -d "$conf_dir" ] && [ -f "$NGINX_SNIPPET" ]; then
        sock=$(grep -o 'unix:/var/run/php/php[0-9.]*-fpm.sock' "/etc/nginx/sites-enabled/$site" | head -1)
        if [ "$APPLY" -eq 1 ]; then
            sed "s#unix:/var/run/php/php8.3-fpm.sock#${sock:-unix:/var/run/php/php8.3-fpm.sock}#" "$NGINX_SNIPPET" > "$conf_dir/security.conf"
        else
            echo "   [dry-run] install $conf_dir/security.conf (socket ${sock:-default})"
        fi
    else
        warn "No Forge conf dir or snippet for $site; add deploy/nginx/security.conf by hand."
    fi

    echo "-- Restart queue workers so they load the clean code"
    run as_owner "cd '$dir' && php artisan queue:restart; php artisan horizon:terminate 2>/dev/null || true"

    echo "-- Ownership and .env permissions"
    run chown -R "$OWNER:$OWNER" "$dir"
    run chmod 600 "$dir/.env"
done

say "Reloading nginx"
if [ "$APPLY" -eq 1 ]; then
    nginx -t && systemctl reload nginx || warn "nginx -t failed: fix /etc/nginx/forge-conf/*/server/security.conf before reloading"
else
    echo "   [dry-run] nginx -t && systemctl reload nginx"
fi
run systemctl restart 'php*-fpm'

# ---------------------------------------------------------------------------
say "Persistence checks (report only; review every line)"
echo "-- crontabs"
for u in $(cut -d: -f1 /etc/passwd); do c=$(crontab -l -u "$u" 2>/dev/null | grep -v '^#'); [ -n "$c" ] && { echo "[$u]"; echo "$c"; }; done
ls -la --time-style=long-iso /etc/cron.d /etc/cron.hourly /etc/cron.daily /var/spool/cron/crontabs 2>/dev/null
echo "-- authorized_keys (compare every key with your team)"
for f in /root/.ssh/authorized_keys /home/*/.ssh/authorized_keys; do [ -f "$f" ] && { echo "[$f]"; awk '{print "   " $1, $NF}' "$f"; }; done
echo "-- systemd units / supervisor programs changed in the last 365 days"
find /etc/systemd/system /lib/systemd/system /etc/supervisor -type f -mtime -365 -printf '   %TY-%Tm-%Td %p\n' 2>/dev/null | sort
echo "-- PHP auto_prepend / auto_append and .user.ini files"
grep -rnE '^\s*auto_(prepend|append)_file\s*=\s*\S' /etc/php 2>/dev/null
find "$HOME_DIR" -name '.user.ini' -not -path '*/node_modules/*' 2>/dev/null
echo "-- ld.so.preload (should not exist)"
[ -s /etc/ld.so.preload ] && cat /etc/ld.so.preload
echo "-- Executables in tmp dirs"
find /tmp /var/tmp /dev/shm -type f \( -perm -u+x -o -name '*.php' -o -name '*.so' \) 2>/dev/null
echo "-- Processes owned by $OWNER (look for anything unfamiliar)"
ps -u "$OWNER" -o pid,etime,cmd | grep -vE 'php-fpm|node|npm|PM2|artisan|sshd|bash|ps -u'
echo "-- Users with a login shell / sudo"
awk -F: '$7 !~ /(nologin|false)$/ {print "   " $1, $7}' /etc/passwd
getent group sudo

# ---------------------------------------------------------------------------
say "Services listening on public interfaces"
ss -tlnp | awk 'NR==1 || ($4 !~ /^(127\.|\[::1\]|\[::ffff:127)/)'

if [ "$LOCK_PORTS" -eq 1 ]; then
    say "Binding MySQL / Redis / Memcached to localhost and enabling the firewall"
    # Typesense (8108) and the node app (4000) also listen publicly; the default-deny
    # below closes them. Add "ufw allow <port>/tcp" first if they must stay reachable.
    run sed -i 's/^\s*bind-address\s*=.*/bind-address = 127.0.0.1/; s/^\s*mysqlx-bind-address\s*=.*/mysqlx-bind-address = 127.0.0.1/' /etc/mysql/mysql.conf.d/mysqld.cnf
    run sed -i 's/^bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf
    run sed -i 's/^-l .*/-l 127.0.0.1/' /etc/memcached.conf
    grep -rqE '^\s*bind-address\s*=\s*127\.0\.0\.1' /etc/mysql/ || warn "MySQL bind-address not set in mysqld.cnf; find it with: grep -rn bind-address /etc/mysql/"
    run systemctl restart mysql redis-server memcached
    run systemctl disable --now vsftpd
    # Allow SSH first so we never lock ourselves out.
    run ufw allow 22/tcp
    run ufw allow 80/tcp
    run ufw allow 443/tcp
    run ufw default deny incoming
    run ufw --force enable
    run ufw status verbose
else
    echo "(Re-run with --lock-ports to close 3306/33060/6379/11211/21 to the internet.)"
fi

say "Done"
cat <<EOF
Still to do by hand:
  1. Rotate every secret in each site's .env (DB, mail, Pusher, GitHub, API tokens)
     and change the passwords of all application users.
  2. Deploy the branch with the upload lock-down (app/Helpers/SafeFileUpload.php).
  3. Check the users / roles tables for accounts you don't recognise.
  4. Turn access logs back on in Forge so the next attempt leaves a trace.
  5. Give each site its own Forge site user (isolation) or move them to a fresh server.
Quarantined files and evidence: $QUARANTINE
EOF
