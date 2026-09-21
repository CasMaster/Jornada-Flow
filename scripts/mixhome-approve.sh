#!/bin/sh
# Install as root:root, mode 0755, at /usr/local/sbin/mixhome-approve.
set -eu
umask 077

[ "$(id -u)" -eq 0 ] || { echo 'Root required.' >&2; exit 1; }
[ "$#" -eq 2 ] || { echo 'Usage: mixhome-approve <homologacao|producao> <sha256>' >&2; exit 1; }
case "$1" in
    homologacao|producao) environment=$1 ;;
    *) echo 'Invalid environment.' >&2; exit 1 ;;
esac
digest=$2
case "$digest" in
    *[!a-f0-9]*|'') echo 'Invalid digest.' >&2; exit 1 ;;
esac
[ "${#digest}" -eq 64 ] || { echo 'Invalid digest length.' >&2; exit 1; }

approval="/var/lib/mixhome-ci/approved/$environment-$digest"
[ ! -e "$approval" ] || { echo 'Approval already exists.' >&2; exit 1; }
expires=$(( $(date +%s) + 900 ))
temporary=$(mktemp "/var/lib/mixhome-ci/approved/.approval.XXXXXXXX")
trap 'rm -f "$temporary"' EXIT HUP INT TERM
printf '%s\n' "$expires" > "$temporary"
mv "$temporary" "$approval"
logger -t mixhome-ci-gate "approval issued for $environment package $digest by ${SUDO_USER:-unknown}"
echo "Approved $environment package $digest for 15 minutes."
