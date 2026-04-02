# Fortelescopes MVP (MySQL Ready)

Proyecto base en PHP puro para `fortelescopes.com`, preparado para trabajar en local con XAMPP y subir después al hosting.

## Stack
- PHP 8+
- MySQL / MariaDB
- Apache + `.htaccess`

## Estructura
- `index.php`: router principal (`/`, `/category/{slug}`, `/product/{slug}`), `robots.txt` y `sitemap.xml`
- `enma/index.php`: panel con login, CSRF y carga de productos
- `scripts/cron_refresh.php`: refresco automático de `last_synced_at`
- `scripts/update_db_schema.php`: crea o valida tablas base en MySQL
- `includes/`: config, db y funciones
- `templates/`: vistas públicas

## Arranque en local (XAMPP)
1. Inicia Apache y MySQL desde XAMPP Control Panel.
2. Crea una base de datos local, por ejemplo: `fortelescopes_local`.
3. Copia `.env.example` a `.env`.
4. Ajusta en `.env`:
   - `APP_BASE_URL=http://localhost/fortelescopes`
   - `DB_DRIVER=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_NAME=fortelescopes_local`
   - `DB_USER=root`
   - `DB_PASS=`
5. Ejecuta:
   ```powershell
   php scripts/update_db_schema.php