# Database Project

# 環境安裝及local部署步驟

## mac
### 1. 安裝環境

先安裝 Homebrew：
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

安裝 PHP、Composer、Node、MySQL：
```bash
brew update
brew install php composer node mysql
```

確認版本：
```bash
php -v        //建議8.4
composer -V   //2.xx (2開頭就行)
node -v       // 我是v20.19.6
npm -v        // 我是10.8.2
mysql --version
```

### 2. 啟動mysql

```bash
brew services start mysql

//登入
mysql -u root 

//建立資料庫
CREATE DATABASE database_project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'database_project_user'@'localhost' IDENTIFIED BY '你的密碼';
GRANT ALL PRIVILEGES ON database_project.* TO 'database_project_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

```

## linux (Ubuntu/Debian)

### 1. 安裝環境

先更新 apt，安裝基本工具：
```bash
sudo apt update
sudo apt install -y curl unzip git software-properties-common
```

安裝 PHP 8.4 和 Laravel 常用 extensions：
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-common php8.4-mbstring php8.4-xml php8.4-curl php8.4-mysql php8.4-zip php8.4-bcmath
```

安裝 Composer：
```bash
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

安裝 Node.js 20 和 npm：
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

安裝 MySQL：
```bash
sudo apt install -y mysql-server
```

確認版本：
```bash
php -v        //建議8.4
composer -V   //2.xx (2開頭就行)
node -v       // 我是v20.19.6
npm -v        // 我是10.8.2
mysql --version
```

### 2. 啟動mysql

```bash
sudo systemctl start mysql
sudo systemctl enable mysql

//登入 MySQL
sudo mysql

//建立資料庫和專案用帳號
CREATE DATABASE database_project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'database_project_user'@'localhost' IDENTIFIED BY '你的密碼';
GRANT ALL PRIVILEGES ON database_project.* TO 'database_project_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 共用部署步驟

### 3. Clone 專案
```bash
git clone 你的GitHubRepo網址
cd 專案資料夾
```

### 4. 安裝 Laravel 後端套件
```bash
composer install

//如果遇到token問題：
//到 https://github.com/settings/tokens
//新增一個token (不用設定任何權限) 然後複製

composer config --global github-oauth.github.com 你的GitHubToken
composer install
```

### 5. 設定 .env
```bash
//前端
cd database_project_front
cp .env.example .env

//後端
cd database_project_api
cp .env.example .env

//打開 .env
//改設定
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database_project
DB_USERNAME=root
DB_PASSWORD=你的密碼
//如果是照上面建立專案用帳號：
//DB_USERNAME=database_project_user
//DB_PASSWORD=你的密碼
```

### 6. 產生 Laravel key
```bash
php artisan key:generate
```

### 7. 安裝前端套件
```bash
npm install
```

### 8. 建立資料表
```bash
php artisan migrate:fresh --seed //會重新把資料庫的資料洗掉變成預設的，但一開始用這個就行
```

### 9. 啟動專案

不要用 `sudo` 跑啟動腳本，直接用一般使用者執行即可。

如果不用 `sudo` 會出現 `Permission denied`，先在專案根目錄修權限：

```bash
cd ~/database-project
sudo chown -R "$USER:$USER" .
chmod +x start-local.sh stop-local.sh start-ngrok.sh stop-ngrok.sh start-tunnel.sh stop-tunnel.sh
```

然後再跑：

```bash
./start-local.sh
```

前端需要 Node.js 20.19.0 以上；如果看到 `Unsupported engine` 或 `Node.js ... is too old`，先升級 Node.js。

#### 資料夾

```text
database_project_api/    Laravel API 後端
database_project_front/  Vite + Vue 前端
DEPLOYMENT.md            本地與外網部署指令
start-local.sh           一鍵本機開發
stop-local.sh            停止本機開發服務
start-ngrok.sh           一鍵 ngrok 外網展示
stop-ngrok.sh            停止 ngrok 外網展示服務
start-tunnel.sh          一鍵 Cloudflare 外網展示
stop-tunnel.sh           停止 Cloudflare 外網展示服務
```

#### 啟動

本機開發建議用：

```bash
cd database-project
./start-local.sh
```

成功後打開：

```text
http://127.0.0.1:5173
```

停止：

```bash
cd database-project
./stop-local.sh
```

如果要給外部同學或老師連，再用 ngrok。

外網展示可以用 ngrok：

第一次使用 ngrok 需要先設定 authtoken：

```bash
ngrok config add-authtoken YOUR_TOKEN
```

token 位置：

```text
https://dashboard.ngrok.com/get-started/your-authtoken
```

```bash
cd database-project
./start-ngrok.sh
```

成功後會顯示：

```text
Ready.
Open this URL:
  https://xxxx.ngrok-free.app
```

把這個網址給其他人即可。

停止：

```bash
cd database-project
./stop-ngrok.sh
```

Cloudflare 版本仍可使用：

```bash
cd database-project
./start-tunnel.sh
./stop-tunnel.sh
```

## 本地開發

後端：

```bash
cd database-project/database_project_api
php artisan serve --host=127.0.0.1 --port=8000
```

前端：

```bash
cd database-project/database_project_front
npm run dev -- --host 127.0.0.1 --port 5173
```

打開：

```text
http://127.0.0.1:5173
```

更多設定請看：

```text
DEPLOYMENT.md
```

## 測試帳號

```text
admin@gmail.com / 12345678
ko@gmail.com/ 12345678
you@gmail.com / 12345678
liu@gmail.com / 12345678
oi@gmail.com / 12345678
```
