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
EXIT;

```

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
cp .env.example .env
//打開 .env

//改設定
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database_project
DB_USERNAME=root
DB_PASSWORD=你的密碼
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
```bash
//開第一個終端機：
php artisan serve

//開第二個終端機：
npm run dev

//瀏覽器打開：
http://127.0.0.1:8000
```

### github push & pull 使用方式??

1. 每次要開始寫code前，一定記得先pull，確保更新到最新的
2. 每次push上去前，可以先 fetch 確認沒有人 push 新的東西
    - 如果沒有人push 就直接push上去吧
    - 如果有：
        ```bash
        git stash
        //把新的pull下來
        git stash pop
        //確認有沒有conflict，沒有就很幸運，有的話就會比較辛苦要解衝突，請小心不要直接覆蓋，不然就丟群組問之類的
        ```
3. commit message 可以好好命名，比較知道大家在幹麻，有個常用的格式 (type():message)，ex： 
    - feat(login): 完成login功能
    - fix(user)：解決登入bug

// 目前想到這些 待更新



<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
