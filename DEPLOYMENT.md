# Setup and shared-hosting deployment

## Requirements

- PHP 8.3 or newer, with Laravel-required extensions including PDO MySQL.
- Composer 2.
- MySQL or MariaDB.
- No Node.js or npm is needed. Tailwind CSS is loaded from its CDN.

## Local installation

From the `vibe-app` directory:

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your MySQL database name, username, and password. `APP_URL` is set to `http://localhost`. Start the local development server with:

```sh
php artisan serve
```

Open <http://localhost:8000> in your browser. Laravel listens on port 8000 by default even though `APP_URL` is `http://localhost`.

## Shared-hosting deployment

1. Upload the complete `vibe-app` directory outside the publicly served folder when your host allows it.
2. Configure the domain document root to `vibe-app/public`. This keeps application code, `.env`, and `vendor` outside the web root. The included `public/.htaccess` routes Apache requests through `public/index.php`.
3. Use PHP 8.3 or newer and enable PDO MySQL. Set the hosting account's MySQL values in `.env`, then set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` to your real HTTPS site URL. Keep `APP_KEY` private and preserve it when deploying updates.
4. In the project directory, run `composer install --no-dev --optimize-autoloader` if SSH and Composer are available. Otherwise, run it locally and upload the resulting `vendor` directory with the application.
5. Ensure `storage` and `bootstrap/cache` are writable by PHP. Run `php artisan config:cache` after setting production environment values. Clear and rebuild that cache whenever those values change.
6. This starter does not need a database migration to render the home page or accept the placeholder form. Configure MySQL before adding features that persist data.

Do not expose the project root as the document root. Only `public` should be web-accessible.
