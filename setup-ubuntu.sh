#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR"
API="$ROOT/database_project_api"
FRONT="$ROOT/database_project_front"
API_PORT="${API_PORT:-8000}"
FRONT_PORT="${FRONT_PORT:-5173}"
DB_DATABASE="${DB_DATABASE:-database_project}"
DB_USERNAME="${DB_USERNAME:-database_project_user}"
DB_PASSWORD="${DB_PASSWORD:-database_project_password}"

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing command after installation: $1"
        exit 1
    fi
}

require_not_root() {
    if [[ "$(id -u)" -eq 0 ]]; then
        echo "Do not run this script with sudo."
        echo "Run it as your normal user. The script will ask for sudo when needed:"
        echo "  ./setup-ubuntu.sh"
        exit 1
    fi
}

require_apt() {
    if ! command -v apt-get >/dev/null 2>&1; then
        echo "This setup script is for Ubuntu/Debian systems with apt."
        exit 1
    fi
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

node_version_ok() {
    node -e '
        const [major, minor] = process.versions.node.split(".").map(Number);
        process.exit((major === 20 && minor >= 19) || (major === 22 && minor >= 12) || major > 22 ? 0 : 1);
    ' >/dev/null 2>&1
}

install_system_packages() {
    echo "Installing system packages..."
    sudo apt-get update
    sudo apt-get install -y curl ca-certificates gnupg unzip git software-properties-common lsb-release mysql-server

    if ! apt-cache show php8.4 >/dev/null 2>&1; then
        sudo add-apt-repository ppa:ondrej/php -y
        sudo apt-get update
    fi

    sudo apt-get install -y \
        php8.4 php8.4-cli php8.4-common php8.4-mbstring php8.4-xml \
        php8.4-curl php8.4-mysql php8.4-zip php8.4-bcmath php8.4-sqlite3

    if [[ -x /usr/bin/php8.4 ]]; then
        sudo update-alternatives --set php /usr/bin/php8.4 >/dev/null 2>&1 || true
    fi

    if ! command -v composer >/dev/null 2>&1; then
        echo "Installing Composer..."
        curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
        sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm -f /tmp/composer-setup.php
    fi

    if ! command -v node >/dev/null 2>&1 || ! node_version_ok; then
        echo "Installing Node.js 20..."
        curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
        sudo apt-get install -y nodejs
    fi
}

setup_mysql() {
    echo "Starting MySQL..."
    sudo systemctl start mysql || sudo service mysql start
    sudo systemctl enable mysql >/dev/null 2>&1 || true

    echo "Creating database and user..."
    sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

install_project_packages() {
    echo "Fixing project permissions..."
    sudo chown -R "$(id -un):$(id -gn)" "$ROOT"
    chmod -R u+rwX "$ROOT"
    chmod +x "$ROOT"/setup-ubuntu.sh "$ROOT"/start-local.sh "$ROOT"/stop-local.sh "$ROOT"/start-ngrok.sh "$ROOT"/stop-ngrok.sh "$ROOT"/start-tunnel.sh "$ROOT"/stop-tunnel.sh

    ensure_env_file "$API" "backend"
    ensure_env_file "$FRONT" "frontend"

    echo "Updating .env files..."
    update_env_value "$API/.env" "APP_ENV" "local"
    update_env_value "$API/.env" "APP_DEBUG" "true"
    update_env_value "$API/.env" "APP_URL" "http://127.0.0.1:$API_PORT"
    update_env_value "$API/.env" "FRONTEND_URLS" "http://127.0.0.1:$FRONT_PORT,http://localhost:$FRONT_PORT"
    update_env_value "$API/.env" "SESSION_DOMAIN" "null"
    update_env_value "$API/.env" "SESSION_SECURE_COOKIE" "false"
    update_env_value "$API/.env" "SESSION_SAME_SITE" "lax"
    update_env_value "$API/.env" "DB_CONNECTION" "mysql"
    update_env_value "$API/.env" "DB_HOST" "127.0.0.1"
    update_env_value "$API/.env" "DB_PORT" "3306"
    update_env_value "$API/.env" "DB_DATABASE" "$DB_DATABASE"
    update_env_value "$API/.env" "DB_USERNAME" "$DB_USERNAME"
    update_env_value "$API/.env" "DB_PASSWORD" "$DB_PASSWORD"
    update_env_value "$FRONT/.env" "VITE_API_URL" "http://127.0.0.1:$API_PORT"

    echo "Installing backend dependencies..."
    cd "$API"
    composer install --no-interaction

    if ! grep -q '^APP_KEY=base64:' "$API/.env"; then
        php artisan key:generate
    fi

    php artisan config:clear
    php artisan migrate --seed

    echo "Installing frontend dependencies..."
    cd "$FRONT"
    npm install
}

require_not_root
require_apt
install_system_packages
require_cmd php
require_cmd composer
require_cmd node
require_cmd npm
require_cmd mysql

if ! node_version_ok; then
    echo "Node.js $(node -v) is still too old. Please open a new terminal and run this script again."
    exit 1
fi

setup_mysql
install_project_packages

echo
echo "Setup complete."
echo "Run the local server with:"
echo "  ./start-local.sh"
echo
echo "Default database settings:"
echo "  DB_DATABASE=$DB_DATABASE"
echo "  DB_USERNAME=$DB_USERNAME"
echo "  DB_PASSWORD=$DB_PASSWORD"
