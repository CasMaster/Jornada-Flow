#!/bin/sh
set -eu

BASE_URL="${SMOKE_BASE_URL:-https://mixhome.app.br}"
ROUTE_PREFIX="${SMOKE_ROUTE_PREFIX:-}"
EMAIL="${SMOKE_EMAIL:-}"
PASSWORD="${SMOKE_PASSWORD:-}"

if { [ -n "$EMAIL" ] && [ -z "$PASSWORD" ]; } || { [ -z "$EMAIL" ] && [ -n "$PASSWORD" ]; }; then
    echo "SMOKE_EMAIL and SMOKE_PASSWORD must be configured together" >&2
    exit 2
fi

case "$ROUTE_PREFIX" in
    ""|/homologacao) ;;
    *) echo "SMOKE_ROUTE_PREFIX must be empty or /homologacao" >&2; exit 2 ;;
esac

BASE_URL="${BASE_URL%/}"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT HUP INT TERM
COOKIE_JAR="$WORK_DIR/cookies"
HEADERS="$WORK_DIR/headers"
LOGIN_HTML="$WORK_DIR/login.html"

fail() {
    echo "Smoke test failed: $1" >&2
    exit 1
}

request_with_retry() {
    url="$1"
    attempts=0
    until curl --fail --silent --show-error --location --max-time 20 "$url"; do
        attempts=$((attempts + 1))
        [ "$attempts" -ge 6 ] && return 1
        sleep 5
    done
}

APP_URL="${BASE_URL}${ROUTE_PREFIX}"

health="$(request_with_retry "$APP_URL/health/ready")" || fail "/health/ready is unavailable"
printf '%s' "$health" | grep -Eq '"database"[[:space:]]*:[[:space:]]*"ok"' \
    || fail "/health/ready did not report database ok"

curl --fail --silent --show-error \
    --cookie-jar "$COOKIE_JAR" --dump-header "$HEADERS" \
    --output "$LOGIN_HTML" "$APP_URL/login"

grep -Fq "${ROUTE_PREFIX}/assets/style.css" "$LOGIN_HTML" \
    || fail "login HTML does not reference assets through the expected prefix"
curl --fail --silent --show-error --output /dev/null "$APP_URL/assets/style.css" \
    || fail "prefixed stylesheet is unavailable"

session_cookie="$(grep -i '^set-cookie:' "$HEADERS" | grep -i -- '-session=' | head -n 1 || true)"
[ -n "$session_cookie" ] || fail "login did not set a session cookie"
printf '%s' "$session_cookie" | grep -Eqi '(^|;[[:space:]]*)Secure([;[:space:]]|$)' \
    || fail "session cookie is missing Secure"
printf '%s' "$session_cookie" | grep -Eqi '(^|;[[:space:]]*)HttpOnly([;[:space:]]|$)' \
    || fail "session cookie is missing HttpOnly"

csrf_token="$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$LOGIN_HTML" | head -n 1)"
[ -n "$csrf_token" ] || fail "login form has no CSRF token"

if [ -z "$EMAIL" ]; then
    echo "Smoke test passed for ${APP_URL} (public checks; authenticated checks skipped)"
    exit 0
fi

curl --silent --show-error \
    --cookie "$COOKIE_JAR" --cookie-jar "$COOKIE_JAR" \
    --dump-header "$HEADERS" --output /dev/null \
    --data-urlencode "_token=$csrf_token" \
    --data-urlencode "email=$EMAIL" \
    --data-urlencode "password=$PASSWORD" \
    --data-urlencode "profile=employee" \
    "$APP_URL/login"

status="$(awk 'toupper($1) ~ /^HTTP\// { code=$2 } END { print code }' "$HEADERS")"
[ "$status" = "302" ] || fail "synthetic account login did not redirect (HTTP $status)"
location="$(awk 'tolower($1) == "location:" { sub(/\r$/, ""); print $2 }' "$HEADERS" | tail -n 1)"
printf '%s' "$location" | grep -Eq "${ROUTE_PREFIX}/painel$" \
    || fail "synthetic account login did not reach the employee dashboard"
renewed_session_cookie="$(grep -i '^set-cookie:' "$HEADERS" | grep -i -- '-session=' | head -n 1 || true)"
[ -n "$renewed_session_cookie" ] || fail "login did not renew the session cookie"
printf '%s' "$renewed_session_cookie" | grep -Eqi '(^|;[[:space:]]*)Secure([;[:space:]]|$)' \
    || fail "renewed session cookie is missing Secure"
printf '%s' "$renewed_session_cookie" | grep -Eqi '(^|;[[:space:]]*)HttpOnly([;[:space:]]|$)' \
    || fail "renewed session cookie is missing HttpOnly"

curl --fail --silent --show-error --location \
    --cookie "$COOKIE_JAR" --output "$WORK_DIR/dashboard.html" "$APP_URL/painel"
grep -Fq "${ROUTE_PREFIX}/logout" "$WORK_DIR/dashboard.html" \
    || fail "authenticated dashboard could not be confirmed"

echo "Smoke test passed for ${APP_URL}"
