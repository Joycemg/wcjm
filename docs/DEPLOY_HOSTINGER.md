# Guía rápida de despliegue en Hostinger

> Probado con hosting compartido Linux + PHP 8.2

## 1. Preparar el entorno
- PHP 8.2 o superior con extensiones `mbstring`, `openssl`, `pdo_mysql`, `intl`, `fileinfo` y `gd` activadas.
- Base de datos MySQL 5.7+ u 8.x. Crear un schema vacío y un usuario con privilegios completos.
- En el panel de Hostinger crear un **cron cada 1 minuto** que ejecute:
  ```bash
  php /home/USUARIO/proyecto/artisan schedule:run >> /home/USUARIO/logs/artisan.log 2>&1
  ```
  Ajustá `USUARIO` y el nombre de la carpeta a tu caso.

## 2. Subir el código
1. Subí el repo vía Git o FTP al directorio raíz (por ej. `/home/USUARIO/wcjm`).
2. En Hostinger el public_html es accesible desde el exterior. Recomendado:
   ```bash
   mv ~/public_html ~/public_html_backup
   ln -s ~/wcjm/public ~/public_html
   ```
   Así Laravel sirve todo desde `public/`.

## 3. Instalar dependencias
Desde el terminal SSH de Hostinger:
```bash
cd ~/wcjm
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan storage:link
```
Si tu hosting no permite `proc_open`, asegurate de tener `COMPOSER_ALLOW_SUPERUSER=1` en el entorno.

## 4. Configurar `.env`
Copia `.env.example` a `.env` y actualizá los datos:
```ini
APP_NAME="La Taberna"
APP_URL="https://tusitio.com"
APP_FORCE_HTTPS=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_base
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

FILESYSTEM_DISK=public
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

Opcionalmente podés ajustar las nuevas variables:
```ini
MESAS_IMAGE_DISK=public
MESAS_HOME_LATEST=6
MESAS_HOME_LATEST_CACHE=120
```

## 5. Migraciones y seeds
```bash
php artisan migrate --force
# php artisan db:seed --force   # si tenés seeders opcionales
```

## 6. Optimizar para producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Si necesitás limpiar caches: `php artisan optimize:clear`.

## 7. Permisos
Asegurate de que Hostinger tenga permisos de escritura en:
```
storage/
bootstrap/cache/
```

## 8. Enviar correos
Configura el apartado `mail` del `.env` con las credenciales SMTP de Hostinger o tu proveedor (Mailtrap, SendGrid, etc.).

Listo 🎉 Tu instancia queda funcionando sin dependencias extrañas y aprovechando la caché de archivos del hosting compartido.
