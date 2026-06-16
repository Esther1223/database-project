#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR"
API="$ROOT/database_project_api"
FRONT="$ROOT/database_project_front"
LOG_DIR="$ROOT/.ngrok-logs"
API_PORT="${API_PORT:-8000}"
FRONT_PORT="${FRONT_PORT:-5173}"
NGROK_API="${NGROK_API:-http://127.0.0.1:4040/api/tunnels}"
NGROK_LOG="$LOG_DIR/ngrok.log"
API_LOG="$LOG_DIR/api.log"
FRONT_LOG="$LOG_DIR/front.log"
PID_FILE="$LOG_DIR/pids"

mkdir -p "$LOG_DIR"
: > "$PID_FILE"

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing command: $1"
        exit 1
    fi
}

require_not_root() {
    if [[ "$(id -u)" -eq 0 ]]; then
        echo "Do not run this script with sudo."
        echo "Run it as your normal user:"
        echo "  ./start-ngrok.sh"
        echo
        echo "If you already ran it with sudo, fix ownership first:"
        echo "  sudo chown -R \"$USER:$USER\" ."
        exit 1
    fi
}

require_node_version() {
    local version major minor
    version="$(node -p 'process.versions.node')"
    IFS=. read -r major minor _ <<< "$version"

    if (( major == 20 && minor >= 19 )) || (( major == 22 && minor >= 12 )) || (( major > 22 )); then
        return 0
    fi

    echo "Node.js $version is too old for this frontend."
    echo "Please install Node.js 20.19.0 or newer, then re-run:"
    echo "  ./start-ngrok.sh"
    exit 1
}

port_in_use() {
    lsof -ti tcp:"$1" >/dev/null 2>&1
}

stop_previous() {
    if [[ -f "$PID_FILE" ]]; then
        while read -r pid; do
            [[ -n "$pid" ]] || continue
            if kill -0 "$pid" >/dev/null 2>&1; then
                kill "$pid" >/dev/null 2>&1 || true
            fi
        done < "$PID_FILE"
    fi
    : > "$PID_FILE"
}

cleanup() {
    echo
    echo "Stopping servers..."
    stop_previous
}

update_env_value() {
    local file="$1"
    local key="$2"
    local value="$3"

    if grep -q "^${key}=" "$file"; then
        perl -0pi -e "s#^${key}=.*#${key}=${value}#m" "$file"
    else
        printf '\n%s=%s\n' "$key" "$value" >> "$file"
    fi
}

wait_for_url() {
    local url="$1"
    local label="$2"
    local attempts="${3:-30}"
    local log_file="${4:-}"

    for _ in $(seq 1 "$attempts"); do
        if curl -fsS "$url" >/dev/null 2>&1; then
            return 0
        fi
        sleep 1
    done

    echo "$label did not become ready: $url"
    if [[ -n "$log_file" && -f "$log_file" ]]; then
        echo
        echo "Last lines from $log_file:"
        tail -n 80 "$log_file"
    fi
    exit 1
}

wait_for_ngrok_url() {
    local attempts=45

    for _ in $(seq 1 "$attempts"); do
        if grep -q "ERR_NGROK_4018" "$NGROK_LOG" 2>/dev/null; then
            echo "ngrok requires a verified account and authtoken." >&2
            echo "1. Sign up or log in: https://dashboard.ngrok.com/signup" >&2
            echo "2. Copy your authtoken: https://dashboard.ngrok.com/get-started/your-authtoken" >&2
            echo "3. Run: ngrok config add-authtoken YOUR_TOKEN" >&2
            echo "4. Re-run: ./start-ngrok.sh" >&2
            exit 1
        fi

        local url
        url="$(
            curl -fsS "$NGROK_API" 2>/dev/null \
                | node -e 'let input="";process.stdin.on("data",d=>input+=d);process.stdin.on("end",()=>{try{const data=JSON.parse(input);const tunnel=(data.tunnels||[]).find(t=>t.public_url&&t.public_url.startsWith("https://")); if (tunnel) console.log(tunnel.public_url);}catch{}});' \
                || true
        )"
        if [[ -n "$url" ]]; then
            echo "$url"
            return 0
        fi
        sleep 1
    done

    echo "ngrok URL was not found. See log: $NGROK_LOG" >&2
    exit 1
}

require_cmd php
require_cmd composer
require_cmd npm
require_cmd node
require_cmd curl
require_cmd lsof
require_cmd ngrok
require_not_root
require_node_version

trap cleanup EXIT INT TERM
stop_previous

if port_in_use "$API_PORT"; then
    echo "Port $API_PORT is already in use. Stop that process first:"
    echo "  lsof -i :$API_PORT"
    echo "  kill PID"
    exit 1
fi

if port_in_use "$FRONT_PORT"; then
    echo "Port $FRONT_PORT is already in use. Stop that process first:"
    echo "  lsof -i :$FRONT_PORT"
    echo "  kill PID"
    exit 1
fi

if port_in_use 4040; then
    echo "ngrok API port 4040 is already in use. Stop the existing ngrok process first."
    echo "  lsof -i :4040"
    echo "  kill PID"
    exit 1
fi

echo "Building frontend for ngrok single-entry mode..."
cd "$FRONT"
npm install
npm run build:tunnel

echo "Starting Laravel API on 127.0.0.1:$API_PORT..."
cd "$API"
composer install --no-interaction
php artisan config:clear
php artisan serve --host=127.0.0.1 --port="$API_PORT" > "$API_LOG" 2>&1 &
echo $! >> "$PID_FILE"
wait_for_url "http://127.0.0.1:$API_PORT/" "Laravel API" 30 "$API_LOG"

echo "Starting frontend/proxy server on 127.0.0.1:$FRONT_PORT..."
cd "$FRONT"
API_TARGET="http://127.0.0.1:$API_PORT" PORT="$FRONT_PORT" npm run serve:tunnel > "$FRONT_LOG" 2>&1 &
echo $! >> "$PID_FILE"
wait_for_url "http://127.0.0.1:$FRONT_PORT/" "Frontend server" 30 "$FRONT_LOG"

echo "Starting ngrok..."
: > "$NGROK_LOG"
ngrok http "$FRONT_PORT" --log=stdout > "$NGROK_LOG" 2>&1 &
echo $! >> "$PID_FILE"

NGROK_URL="$(wait_for_ngrok_url)"

echo "Updating backend .env with ngrok URL..."
update_env_value "$API/.env" "APP_URL" "$NGROK_URL"
update_env_value "$API/.env" "FRONTEND_URLS" "$NGROK_URL,http://localhost:$FRONT_PORT,http://127.0.0.1:$FRONT_PORT"
update_env_value "$API/.env" "SESSION_DOMAIN" "null"
update_env_value "$API/.env" "SESSION_SECURE_COOKIE" "true"
update_env_value "$API/.env" "SESSION_SAME_SITE" "none"

cd "$API"
php artisan config:clear >/dev/null

echo
echo "Ready."
echo "Open this URL:"
echo "  $NGROK_URL"
echo
echo "Logs:"
echo "  API:      $API_LOG"
echo "  Frontend: $FRONT_LOG"
echo "  ngrok:    $NGROK_LOG"
echo
echo "Keep this terminal open. Press Ctrl+C to stop everything."

tail -f "$API_LOG" "$FRONT_LOG" "$NGROK_LOG"
