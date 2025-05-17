# Suitescape PH (API)

![Suitescape Presentation](https://github.com/user-attachments/assets/c991c17c-51c9-4a19-a837-074c22781810)

## Introduction

SuitescapePH is the first Filipino video-first mobile booking platform that transforms how users discover and book staycations, villas, and unique getaways across the Philippines. Inspired by the TikTok experience, it features an immersive short-form video feed with chapter-based navigation, allowing users to quickly jump to highlights within each listing. It features a seamless in-app chat system for guest-host communication, secure payment integration, a dedicated host dashboard, user authentication with password recovery, and a smooth booking experience. SuitescapePH redefines travel discovery by blending social-style content with powerful booking functionality—all in one mobile-first platform.

## **Clone github repository**

```
git clone https://github.com/kevinpauneljohn/suitescape-api.git
```

## Requirements

- PHP:
    - [XAMPP](https://www.apachefriends.org) or [Laragon](https://laragon.org) (for Windows)
    - [Homebrew](https://brew.sh) and the [PHP Formulate](https://formulae.brew.sh/formula/php) (for Mac)
- [Composer](https://getcomposer.org)
- [Zrok](https://zrok.io) (Optional, free and open-source alternative for ngrok)
- [Laravel Valet](https://laravel.com/docs/10.x/valet) (Optional for Mac only, allows serving the project in the background and on LAN)

## Setup

1. Create a database in your preferred database software ([HeidiSQL](https://www.heidisql.com) for Windows, [Sequel Ace](https://sequel-ace.com) for Mac, or [TablePlus](https://tableplus.com) available for both). You can also use the CLI to create a database, open CMD or Terminal then type the commands below:
    
    ```
    mysql -h localhost
    CREATE DATABASE suitescape_api;
    SHOW DATABASES;
    exit;
    ```
    
2. Open the project, then copy the `.env.example` into `.env`. Then modify the following variables to setup the database:
    
    ```
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=suitescape_api [MODIFY DATABASE NAME, IF DIFFERENT]
    DB_USERNAME=root [MODIFY USERNAME, IF DIFFERENT]
    DB_PASSWORD=[ADD PASSWORD, IF ANY]
    ```
    
3. Setup **Gmail SMTP** for sending email notifications with [Laravel Mail](https://laravel.com/docs/10.x/mail). Use this [tutorial](https://itsupport.umd.edu/itsupport?id=kb_article_view&sysparm_article=KB0015112) to get the app password. After getting the password modify the env file:
    
    ```
    MAIL_MAILER=smtp
    MAIL_HOST=smtp.gmail.com
    MAIL_PORT=465
    MAIL_USERNAME=example@gmail.com [USE YOUR GMAIL HERE]
    MAIL_PASSWORD="usqk mhnp eqpu mzsa" [ENTER APP PASSWORD HERE]
    MAIL_ENCRYPTION=tls
    MAIL_FROM_ADDRESS="example@gmail.com" [USE YOUR GMAIL HERE]
    MAIL_FROM_NAME="${APP_NAME}"
    ```
    
4. Setup **Pusher** for broadcasting features. Visit the [Pusher](https://pusher.com) website and follow the official instructions. If you have a key already visit the [App Keys](https://dashboard.pusher.com/apps/1831461/keys) tab in the dashboard. After setting up, make sure you modify the env file as well:
    
    ```
    PUSHER_APP_ID=1234567 [ENTER APP ID HERE]
    PUSHER_APP_KEY="1231231234abcabcabca" [ENTER APP KEY HERE]
    PUSHER_APP_SECRET="1234567891abcabcabca" [ENTER APP SECRET HERE]
    PUSHER_HOST=
    PUSHER_PORT=443
    PUSHER_SCHEME=https
    PUSHER_APP_CLUSTER=ap1 [ENTER APP CLUSTER HERE]
    ```
    
5. Setup **Laravel Paymongo** for payment gateway capability. Check the [official documentation](https://paymongo.rigelkentcarbonel.com) to know more about the details. Make sure to modify the env file as well:
    
    ```
    PAYMONGO_SECRET_KEY=sk_test_abcdefghijklmnopqrstuvwxyz
    PAYMONGO_PUBLIC_KEY=pk_test_abcdefghijklmnopqrstuvwxyz
    PAYMONGO_WEBHOOK_SIG=whsk_abcdefghijklmnopqrstuvwxyz
    ```
    
## Install

1. `composer update`
2. `php artisan key:generate`
3. `php artisan migrate:fresh —seed`
4. `php artisan storage:link`

## Run the server

- `php artisan serve`
    - Add `--host`  and `--port` to allow mobile devices to access the served IP address locally (e.g. `php artisan serve --host=192.168.1.12 --port=80` then use `http://192.168.1.12/api` in the mobile app)
    - The **host** IP address can be retrieved through the `ipconfig` command.
    - The **port** can be any number as long as it is not being used by other programs. (You can check through `netstat -na` command.)
- If you’re using **Laragon**, you can also use [pretty URLs](https://laragon.org/docs/pretty-urls) to create a readable url for your project that can be reused, even in the background.
- If you’re using **Laravel Valet**, you can serve sites through [parking directories](https://laravel.com/docs/11.x/valet#the-park-command) or [linking sites](https://laravel.com/docs/11.x/valet#the-link-command), which will also have a readable url for your projects that also works in the background.

## API Documentation

- Visit `/docs/api` route in the api to visit the documentation of each routes.
- For more details: [https://scramble.dedoc.co](https://scramble.dedoc.co/)
