#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR"
API="$ROOT/database_project_api"
FRONT="$ROOT/database_project_front"
LOG_DIR="$ROOT/.local-logs"
API_PORT="${API_PORT:-8000}"
FRONT_PORT="${FRONT_PORT:-5173}"
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
        echo "  ./start-local.sh"
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
    echo "  ./start-local.sh"
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
    echo "Stopping local servers..."
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

    for _ in $(seq 1 "$attempts"); do
        if curl -fsS "$url" >/dev/null 2>&1; then
            return 0
        fi
        sleep 1
    done

    echo "$label did not become ready: $url"
    echo "See logs:"
    echo "  API:      $API_LOG"
    echo "  Frontend: $FRONT_LOG"
    exit 1
}

ensure_env_file() {
    local dir="$1"
    local label="$2"

    if [[ ! -f "$dir/.env" ]]; then
        if [[ -f "$dir/.env.example" ]]; then
            cp "$dir/.env.example" "$dir/.env"
            echo "Created $label .env from .env.example"
        else
            echo "Missing $label .env and .env.example"
            exit 1
        fi
    fi
}

require_cmd php
require_cmd composer
require_cmd node
require_cmd npm
require_cmd curl
require_cmd lsof
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

ensure_env_file "$API" "backend"
ensure_env_file "$FRONT" "frontend"

echo "Updating local .env values..."
update_env_value "$API/.env" "APP_ENV" "local"
update_env_value "$API/.env" "APP_DEBUG" "true"
update_env_value "$API/.env" "APP_URL" "http://127.0.0.1:$API_PORT"
update_env_value "$API/.env" "FRONTEND_URLS" "http://127.0.0.1:$FRONT_PORT,http://localhost:$FRONT_PORT"
update_env_value "$API/.env" "SESSION_DOMAIN" "null"
update_env_value "$API/.env" "SESSION_SECURE_COOKIE" "false"
update_env_value "$API/.env" "SESSION_SAME_SITE" "lax"
update_env_value "$FRONT/.env" "VITE_API_URL" "http://127.0.0.1:$API_PORT"

echo "Installing backend dependencies..."
cd "$API"
composer install --no-interaction

if ! grep -q '^APP_KEY=base64:' "$API/.env"; then
    echo "Generating Laravel app key..."
    php artisan key:generate
fi

php artisan config:clear

echo "Installing frontend dependencies..."
cd "$FRONT"
npm install

echo "Starting Laravel API on 127.0.0.1:$API_PORT..."
cd "$API"
php artisan serve --host=127.0.0.1 --port="$API_PORT" > "$API_LOG" 2>&1 &
echo $! >> "$PID_FILE"
wait_for_url "http://127.0.0.1:$API_PORT/" "Laravel API"

echo "Starting Vite frontend on 127.0.0.1:$FRONT_PORT..."
cd "$FRONT"
npm run dev -- --host 127.0.0.1 --port "$FRONT_PORT" > "$FRONT_LOG" 2>&1 &
echo $! >> "$PID_FILE"
wait_for_url "http://127.0.0.1:$FRONT_PORT/" "Vite frontend"

echo
echo "Ready."
echo "Open this URL:"
echo "  http://127.0.0.1:$FRONT_PORT"
echo
echo "Backend API:"
echo "  http://127.0.0.1:$API_PORT"
echo
echo "Logs:"
echo "  API:      $API_LOG"
echo "  Frontend: $FRONT_LOG"
echo
echo "Keep this terminal open. Press Ctrl+C to stop everything."

tail -f "$API_LOG" "$FRONT_LOG"
