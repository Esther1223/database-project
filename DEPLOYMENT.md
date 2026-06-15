# Database Project 部署指令

這份文件整理兩種啟動方式：

- 本地開發：只有自己電腦使用。
- 外網展示：建議用 ngrok；Cloudflare Tunnel 保留作備用。

專案路徑：

```bash
API=/Volumes/Eric5TB/programming/database/database-project/database_project_api
FRONT=/Volumes/Eric5TB/programming/database/database-project/database_project_front
```

## 快速一鍵外網啟動 ngrok

建議先用 ngrok 版本：

第一次使用 ngrok 需要帳號與 authtoken。若尚未設定，先做一次：

```bash
ngrok config add-authtoken YOUR_TOKEN
```

authtoken 在 ngrok dashboard：

```text
https://dashboard.ngrok.com/get-started/your-authtoken
```

```bash
cd /Volumes/Eric5TB/programming/database/database-project
./start-ngrok.sh
```

腳本會自動：

- build 前端 tunnel 版。
- 啟動 Laravel API：`127.0.0.1:8000`。
- 啟動前端靜態檔與 `/api` proxy：`127.0.0.1:5173`。
- 啟動 ngrok。
- 從 ngrok local API 抓出公開 URL。
- 自動更新後端 `.env`：
  - `APP_URL`
  - `FRONTEND_URLS`
  - `SESSION_DOMAIN`
  - `SESSION_SECURE_COOKIE`
  - `SESSION_SAME_SITE`
- 自動執行 `php artisan config:clear`。

成功後會顯示：

```text
Ready.
Open this URL:
  https://xxxx.ngrok-free.app
```

把這個網址給別人即可。

停止全部服務：

```bash
cd /Volumes/Eric5TB/programming/database/database-project
./stop-ngrok.sh
```

Cloudflare 備用版本：

```bash
cd /Volumes/Eric5TB/programming/database/database-project
./start-tunnel.sh
```

停止 Cloudflare 版本：

```bash
cd /Volumes/Eric5TB/programming/database/database-project
./stop-tunnel.sh
```

如果腳本說 `8000` 或 `5173` 被佔用：

```bash
lsof -i :8000
kill PID

lsof -i :5173
kill PID
```

## 一、本地開發

本地開發網址：

```text
前端：http://127.0.0.1:5173
後端：http://127.0.0.1:8000
```

### 1. 後端 `.env`

編輯：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
```

`.env` 建議設定：

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

FRONTEND_URLS=http://127.0.0.1:5173,http://localhost:5173

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database_project
DB_USERNAME=你的資料庫帳號
DB_PASSWORD=你的資料庫密碼
```

改完後清設定快取：

```bash
php artisan config:clear
```

### 2. 前端 `.env`

編輯：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front
```

`.env` 設定：

```env
VITE_API_URL=http://127.0.0.1:8000
```

### 3. 啟動後端

開一個 terminal：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api

composer install
php artisan config:clear
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

如果 `8000` 被佔用：

```bash
lsof -i :8000
kill PID
```

### 4. 啟動前端

開另一個 terminal：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

npm install
npm run dev -- --host 127.0.0.1 --port 5173
```

打開：

```text
http://127.0.0.1:5173
```

## 二、外網展示 Cloudflare Tunnel

外網展示需要 4 個 terminal：

1. Laravel 後端
2. 後端 Cloudflare Tunnel
3. Vue 前端
4. 前端 Cloudflare Tunnel

### 1. 啟動後端

Terminal 1：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api

php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. 開後端 Tunnel

Terminal 2：

```bash
cloudflared tunnel --url http://127.0.0.1:8000
```

它會給一個後端網址，例如：

```text
https://backend-example.trycloudflare.com
```

記下這個後端網址。

### 3. 設定後端 `.env`

回到：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
```

先設定後端網址與 cookie：

```env
APP_URL=https://backend-example.trycloudflare.com

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

`FRONTEND_URLS` 要等前端 tunnel 產生後再補。

清設定：

```bash
php artisan config:clear
```

如果後端已經在跑，建議 `Ctrl + C` 後重開：

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 4. 啟動前端

Terminal 3：

把 `https://backend-example.trycloudflare.com` 換成你的後端 tunnel 網址。

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

VITE_API_URL=https://backend-example.trycloudflare.com npm run dev -- --host 0.0.0.0 --port 5173
```

### 5. 開前端 Tunnel

Terminal 4：

```bash
cloudflared tunnel --url http://127.0.0.1:5173
```

它會給一個前端網址，例如：

```text
https://frontend-example.trycloudflare.com
```

這個網址才是給別人開的網址。

### 6. 允許 Vite 的前端 tunnel host

編輯：

```text
/Volumes/Eric5TB/programming/database/database-project/database_project_front/vite.config.js
```

把前端 tunnel host 加到 `allowedHosts`：

```js
export default defineConfig({
    plugins: [vue(), tailwindcss()],
    server: {
        port: 5173,
        allowedHosts: [
            'frontend-example.trycloudflare.com',
        ],
    },
});
```

如果 tunnel 重新開過，Cloudflare 給了新網址，這裡也要更新。

改完後重啟前端：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

VITE_API_URL=https://backend-example.trycloudflare.com npm run dev -- --host 0.0.0.0 --port 5173
```

### 7. 允許後端 CORS 來源

回到後端 `.env`：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
```

設定：

```env
APP_URL=https://backend-example.trycloudflare.com
FRONTEND_URLS=https://frontend-example.trycloudflare.com,http://localhost:5173,http://127.0.0.1:5173

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

清設定快取：

```bash
php artisan config:clear
```

重啟後端：

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 8. 給別人的網址

只給前端網址：

```text
https://frontend-example.trycloudflare.com
```

不要給後端網址。後端網址只是給前端打 API 用。

## 三、外網展示較順的跑法

如果用 `npm run dev` 開前端，Cloudflare Tunnel 會連到 Vite dev server。這種方式有 HMR、開發模式檢查與 websocket，外網容易卡。

展示給別人看時，建議前端先 build，再用靜態伺服器跑。

目前實測可用的範例：

```text
後端 tunnel：https://designs-hourly-washington-pathology.trycloudflare.com
前端 tunnel：https://zero-tape-barbie-lake.trycloudflare.com
```

Quick Tunnel 每次重開都可能換網址。如果網址換了，下面指令中的網址也要一起換。

### 1. 後端照常啟動

Terminal 1：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api

php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```

Terminal 2：

```bash
cloudflared tunnel --url http://127.0.0.1:8000
```

假設後端 tunnel 是：

```text
https://backend-example.trycloudflare.com
```

### 2. 前端 build 成正式檔

Terminal 3：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

VITE_API_URL=https://backend-example.trycloudflare.com npm run build
npx serve -s dist -l 5173
```

實測範例：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

VITE_API_URL=https://designs-hourly-washington-pathology.trycloudflare.com npm run build
npx serve -s dist -l 5173
```

這種 build 版展示不需要修改前端 `.env`。`VITE_API_URL=... npm run build` 會在 build 當下把後端 API 網址寫進 `dist`。前端 `.env` 可以保留本地開發設定：

```env
VITE_API_URL=http://127.0.0.1:8000
```

如果第一次使用 `serve`，它可能會問是否安裝，輸入：

```text
y
```

### 3. 前端 tunnel 指到靜態伺服器

Terminal 4：

```bash
cloudflared tunnel --url http://127.0.0.1:5173
```

拿到前端 tunnel 後，記得更新後端 `.env`：

```env
APP_URL=https://backend-example.trycloudflare.com
FRONTEND_URLS=https://frontend-example.trycloudflare.com,http://localhost:5173,http://127.0.0.1:5173
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

然後：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
php artisan config:clear
```

這個方式通常會比 Vite dev server 順很多。

實測範例：

```env
APP_URL=https://designs-hourly-washington-pathology.trycloudflare.com
FRONTEND_URLS=https://zero-tape-barbie-lake.trycloudflare.com,http://localhost:5173,http://127.0.0.1:5173
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

如果 `npx serve` terminal 出現：

```text
GET /
Returned 200
GET /assets/index-xxxx.css
GET /assets/index-xxxx.js
Returned 200
```

這是正常的，代表前端首頁、CSS、JS 都成功送出了。

## 四、外網展示最快的單一 Tunnel 跑法

這是目前最推薦的展示方式。前端靜態檔和後端 API 都走同一個 Cloudflare Tunnel：

```text
https://你的前端網址/
https://你的前端網址/api/...
```

優點：

- 只需要 3 個 terminal。
- 只需要 1 條 Cloudflare Tunnel。
- 前端不需要跨網域打 API。
- 比前後端分兩條 tunnel 順。

目前實測單一 tunnel 網址：

```text
https://repeated-optimal-constraint-blue.trycloudflare.com
```

如果 Cloudflare Tunnel 顯示 `control stream encountered a failure` 或一直重試，建議改用 HTTP/2 protocol：

```bash
cloudflared tunnel --protocol http2 --url http://127.0.0.1:5173
```

### 1. 後端 Laravel

Terminal 1：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api

php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

### 2. 前端 build + API proxy server

Terminal 2：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

npm run build:tunnel
npm run serve:tunnel
```

這會：

- 用 `window.location.origin` 當 API base URL。
- 送出 `dist` 前端靜態檔。
- 把 `/api/*` 代理到 `http://127.0.0.1:8000/api/*`。

如果後端不是跑在 `127.0.0.1:8000`，可以指定：

```bash
API_TARGET=http://127.0.0.1:8001 npm run serve:tunnel
```

### 3. Cloudflare Tunnel

Terminal 3：

```bash
cloudflared tunnel --url http://127.0.0.1:5173
```

較穩的替代指令：

```bash
cloudflared tunnel --protocol http2 --url http://127.0.0.1:5173
```

它會給你一個網址，例如：

```text
https://single-example.trycloudflare.com
```

這個網址就是給別人開的網址。

### 4. 後端 `.env`

拿到 tunnel 網址後，把後端 `.env` 設成：

```env
APP_URL=https://single-example.trycloudflare.com
FRONTEND_URLS=https://single-example.trycloudflare.com,http://localhost:5173,http://127.0.0.1:5173
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

然後：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
php artisan config:clear
```

如果後端已經在跑，建議 `Ctrl + C` 後重開：

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

目前實測 `.env` 範例：

```env
APP_URL=https://repeated-optimal-constraint-blue.trycloudflare.com
FRONTEND_URLS=https://repeated-optimal-constraint-blue.trycloudflare.com,http://localhost:5173,http://127.0.0.1:5173
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

## 五、ngrok 單一入口跑法

如果不用一鍵腳本，也可以手動跑 ngrok。架構一樣是單一入口：

```text
https://你的-ngrok-網址/
https://你的-ngrok-網址/api/...
```

### 1. 後端 Laravel

Terminal 1：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api

php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

### 2. 前端 build + API proxy server

Terminal 2：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front

npm run build:tunnel
npm run serve:tunnel
```

### 3. ngrok

Terminal 3：

```bash
ngrok http 5173
```

ngrok 會給你一個網址，例如：

```text
https://example.ngrok-free.app
```

### 4. 後端 `.env`

把後端 `.env` 設成：

```env
APP_URL=https://example.ngrok-free.app
FRONTEND_URLS=https://example.ngrok-free.app,http://localhost:5173,http://127.0.0.1:5173
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

然後：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
php artisan config:clear
```

## 六、常見問題

### 8000 被佔用

```bash
lsof -i :8000
kill PID
```

### 5173 被佔用

```bash
lsof -i :5173
kill PID
```

### Vite 顯示 Blocked request

錯誤類似：

```text
This host is not allowed.
```

把前端 tunnel host 加到 `vite.config.js` 的 `server.allowedHosts`。

### 登入失敗或跨網路無法保持登入

確認後端 `.env`：

```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
FRONTEND_URLS=https://你的前端.trycloudflare.com
```

然後：

```bash
php artisan config:clear
```

### Cloudflare tunnel 網址改變

Quick Tunnel 每次重開都可能換網址。換網址後要同步更新：

- 後端 `.env` 的 `APP_URL`
- 後端 `.env` 的 `FRONTEND_URLS`
- 前端啟動指令的 `VITE_API_URL`
- 前端 `vite.config.js` 的 `server.allowedHosts`

## 七、測試帳號

```text
admin@example.com / admin
staff@example.com / staff
wu@example.com / wu
KaBuo@example.com / kabuo
```
api
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000 
front
npm run build:tunnel
npm run serve:tunnel
front
cloudflared tunnel --url http://127.0.0.1:5173
然後更新.env網址
