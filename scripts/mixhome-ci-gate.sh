#!/bin/sh
# Install as root:root, mode 0755, at /usr/local/sbin/mixhome-ci-gate.
set -eu
umask 077

[ "$(id -u)" -eq 0 ] || { echo 'Root required.' >&2; exit 1; }
state=/var/lib/mixhome-ci
command=${SSH_ORIGINAL_COMMAND:-}

case "$command" in
    backup-status)
        exec runuser -u admin -- rclone lsjson b2-mixhome:mixhome-backups/producao --files-only
        ;;
    deploy-homologacao)
        environment=homologacao
        target=/opt/hibrido-home-office-hml
        ;;
    deploy-producao)
        environment=producao
        target=/opt/hibrido-home-office-prod
        ;;
    *)
        echo 'Operation not permitted.' >&2
        exit 1
        ;;
esac

incoming=$(mktemp "$state/incoming.XXXXXXXX")
consumed=
trap 'rm -f "$incoming" ${consumed:+"$consumed"}' EXIT HUP INT TERM
cat > "$incoming"
test -s "$incoming" || { echo 'Empty package.' >&2; exit 1; }
tar -tzf "$incoming" >/dev/null
digest=$(sha256sum "$incoming" | cut -d ' ' -f 1)
approval="$state/approved/$environment-$digest"
pending="$target/.deploy-approval/pending/$environment-$digest"
web_approval="$target/.deploy-approval/approved/$environment-$digest"
printf '%s\n' "$(( $(date +%s) + 900 ))" > "$pending"
chmod 644 "$pending"
trap 'rm -f "$incoming" "$pending" ${consumed:+"$consumed"}' EXIT HUP INT TERM

echo "Package received for $environment. SHA-256: $digest"
echo "Waiting up to 15 minutes for server approval."

attempt=0
while [ "$attempt" -lt 60 ]; do
    if [ -f "$approval" ]; then
        consumed="$approval.used.$$"
        if mv "$approval" "$consumed" 2>/dev/null; then
            expires=$(cat "$consumed")
            now=$(date +%s)
            case "$expires" in
                *[!0-9]*|'') echo 'Invalid approval.' >&2; exit 1 ;;
            esac
            [ "$expires" -ge "$now" ] || { echo 'Approval expired.' >&2; exit 1; }
            break
        fi
    fi
    if [ -f "$web_approval" ]; then
        consumed="$web_approval.used.$$"
        if mv "$web_approval" "$consumed" 2>/dev/null; then
            if web_actor=$(/usr/local/sbin/mixhome-verify-web-approval "$environment" "$digest" "$consumed"); then
                logger -t mixhome-ci-gate "web approval for $environment package $digest by app user $web_actor"
                break
            fi
            rm -f "$consumed"
            consumed=
        fi
    fi
    attempt=$((attempt + 1))
    sleep 15
done
[ -n "$consumed" ] && [ -f "$consumed" ] || { echo 'Approval timed out.' >&2; exit 1; }

logger -t mixhome-ci-gate "approved $environment package $digest"
runuser -u admin -- tar -xzf - -C "$target" < "$incoming"
runuser -u admin -- sh -c 'cd "$1" && sh deploy.sh' sh "$target"
logger -t mixhome-ci-gate "deployed $environment package $digest"
