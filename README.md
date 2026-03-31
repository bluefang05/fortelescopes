# Fortelescopes MVP (Local First)

Proyecto base en PHP puro para `fortelescopes.com`, preparado para trabajar en local con XAMPP y subir despues al hosting.

## Stack
- PHP 8+
- SQLite (archivo local en `data/site.sqlite`)
- Apache + `.htaccess`

## Estructura
- `index.php`: router principal (`/`, `/category/{slug}`, `/product/{slug}`), `robots.txt` y `sitemap.xml`
- `enma/index.php`: panel con login, CSRF y carga de productos
- `scripts/cron_refresh.php`: refresco automatico de `last_synced_at`
- `includes/`: config, db y funciones
- `templates/`: vistas publicas

## Arranque en local (XAMPP)
1. Inicia Apache desde XAMPP Control Panel.
2. Abre `http://localhost/fortelescopes/`.
3. Panel admin: `http://localhost/fortelescopes/enma/`.
4. Credenciales por defecto: `admin` / `change-this-now`.
5. (Opcional) Para habilitar tareas de alto impacto en Enma, define `ENMA_ADVANCED_KEY` en `.env`.

## Configuracion local
1. Copia `.env.example` a `.env`.
2. Cambia `ADMIN_USER` y `ADMIN_PASS`.
3. Si usas otro path o dominio local, ajusta `APP_BASE_URL`.

## Cron local (manual)
Ejecuta:

```powershell
php scripts/cron_refresh.php
```

Esto marca productos publicados como refrescados cuando pasan ~23 horas sin sync.

## Siguiente integracion recomendada
- Reemplazar el refresco mock con integracion real a Amazon PA-API.
- Guardar logs de sync en tabla separada.
- Agregar edicion/eliminacion de productos en admin.
- Mejorar rendimiento con cache HTML parcial.

## Checklist antes de subir al hosting
1. Definir credenciales fuertes en `.env` (`ADMIN_USER`, `ADMIN_PASS`).
2. Verificar que `APP_BASE_URL` apunte al dominio final (`https://fortelescopes.com`).
3. Confirmar permisos de escritura para `data/` (SQLite).
4. Programar cron del hosting para `php /ruta/scripts/cron_refresh.php`.
5. Confirmar paginas publicas:
   - `/affiliate-disclosure`
   - `/privacy-policy`
   - `/terms-of-use`
   - `/robots.txt`
   - `/sitemap.xml`
