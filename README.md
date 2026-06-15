# Database Project

這個資料夾是前後端分離後的整合版。

## 資料夾

```text
database_project_api/    Laravel API 後端
database_project_front/  Vite + Vue 前端
DEPLOYMENT.md            本地與外網部署指令
start-ngrok.sh           一鍵 ngrok 外網展示
stop-ngrok.sh            停止 ngrok 外網展示服務
start-tunnel.sh          一鍵 Cloudflare 外網展示
stop-tunnel.sh           停止 Cloudflare 外網展示服務
```

根目錄的 `.env` 是舊單體專案留下的設定，現在主要使用：

```text
database_project_api/.env
database_project_front/.env
```

## 一鍵外網展示

建議先用 ngrok：

第一次使用 ngrok 需要先設定 authtoken：

```bash
ngrok config add-authtoken YOUR_TOKEN
```

token 位置：

```text
https://dashboard.ngrok.com/get-started/your-authtoken
```

```bash
cd /Volumes/Eric5TB/programming/database/database-project
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
cd /Volumes/Eric5TB/programming/database/database-project
./stop-ngrok.sh
```

Cloudflare 版本仍可使用：

```bash
cd /Volumes/Eric5TB/programming/database/database-project
./start-tunnel.sh
./stop-tunnel.sh
```

## 本地開發

後端：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_api
php artisan serve --host=127.0.0.1 --port=8000
```

前端：

```bash
cd /Volumes/Eric5TB/programming/database/database-project/database_project_front
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
admin@example.com / admin
staff@example.com / staff
wu@example.com / wu
KaBuo@example.com / kabuo
```
