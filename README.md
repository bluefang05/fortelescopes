# Fortelescopes

PHP + MySQL site for `fortelescopes.com`, with a public catalog/blog frontend and the `enma/` admin panel.

## Stack

- PHP 8+
- MySQL / MariaDB
- Apache + `.htaccess`

## Main Structure

- `index.php`: public router for home, categories, guides, blog, product pages, `robots.txt`, and `sitemap.xml`
- `enma/index.php`: admin panel for products, posts, users, analytics, and maintenance
- `includes/`: shared config, DB access, and frontend helpers
- `templates/`: public templates
- `scripts/update_db_schema.php`: installs or updates the DB schema
- `scripts/cron_refresh.php`: refreshes product sync labels

## Local Setup

1. Start Apache and MySQL in XAMPP.
2. Create a database, for example `fortelescopes_local`.
3. Copy `.env.example` to `.env`.
4. Update `.env` with your real values:
   - `APP_BASE_URL=http://localhost/fortelescopes`
   - `DB_DRIVER=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_NAME=fortelescopes_local`
   - `DB_USER=root`
   - `DB_PASS=`
   - `ADMIN_USER=admin`
   - `ADMIN_PASS=<strong password>`
5. Run:

```powershell
php scripts/update_db_schema.php
```

That command creates/updates the schema and creates the first admin user only when `ADMIN_USER` and `ADMIN_PASS` are set to non-placeholder values.

## Notes

- Public requests no longer run schema installation automatically. Use `scripts/update_db_schema.php` after schema changes.
- `healthcheck.php` returns a minimal status instead of exposing DB details.
- Category, blog, and guides hubs are paginated and linked for easier crawling.
