# =============================================================================
# Stage 1: Node.js — compilar assets con Vite
# =============================================================================
FROM node:20-alpine AS node_builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --frozen-lockfile

COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build

# =============================================================================
# Stage 2: Composer — instalar dependencias PHP sin dev
# =============================================================================
FROM composer:2.8 AS composer_builder

WORKDIR /app

# Copiar solo los manifiestos primero para aprovechar cache de capas
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copiar todo el código fuente
COPY . .

# Copiar assets compilados del stage de Node
COPY --from=node_builder /app/public/build ./public/build

# Generar autoloader optimizado de producción
RUN composer dump-autoload \
    --optimize \
    --classmap-authoritative \
    --no-dev

# =============================================================================
# Stage 3: Imagen de producción final
# =============================================================================
FROM php:8.2-fpm-alpine AS production

LABEL description="Krayin CRM — optimizado para Railway"

# Variables de entorno base para producción
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    FILESYSTEM_DISK=public

# --------------------------------------------------------------------------
# Sistema: librerías nativas + extensiones PHP en un solo RUN
# --------------------------------------------------------------------------
RUN apk add --no-cache \
        # Servidor web y orquestador de procesos
        nginx \
        supervisor \
        # Utilidades
        curl \
        bash \
        # Librerías para GD
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        # Librerías runtime de GD
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
        # Mbstring
        oniguruma-dev \
        # ZIP
        libzip-dev \
        libzip \
        # Intl / ICU
        icu-dev \
        icu-libs \
        # IMAP + SSL (webklex/laravel-imap usa imap_open en LegacyProtocol)
        imap-dev \
        openssl-dev \
        c-client \
        krb5-dev \
        # XML stack (dompdf, phpspreadsheet)
        libxml2-dev \
    && \
    # -----------------------------------------------------------------------
    # Compilar extensiones PHP
    # -----------------------------------------------------------------------
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && \
    docker-php-ext-configure imap \
        --with-kerberos \
        --with-imap-ssl \
    && \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        intl \
        bcmath \
        pcntl \
        opcache \
        imap \
        exif \
        dom \
        xml \
        xmlreader \
        xmlwriter \
        simplexml \
        fileinfo \
    && \
    # iconv, curl, openssl, json, zlib, libxml ya están compilados en php:fpm-alpine
    rm -rf /var/cache/apk/* /tmp/*

# --------------------------------------------------------------------------
# Configuración PHP — producción
# --------------------------------------------------------------------------
RUN { \
    echo '[PHP]'; \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 512M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_time = 300'; \
    echo 'date.timezone = UTC'; \
    echo 'expose_php = Off'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /dev/stderr'; \
    } > /usr/local/etc/php/conf.d/app.ini \
    && \
    { \
    echo '[opcache]'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.enable_cli = 0'; \
    echo 'opcache.memory_consumption = 256'; \
    echo 'opcache.max_accelerated_files = 20000'; \
    echo 'opcache.validate_timestamps = 0'; \
    echo 'opcache.revalidate_freq = 0'; \
    echo 'opcache.interned_strings_buffer = 16'; \
    echo 'opcache.fast_shutdown = 1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# --------------------------------------------------------------------------
# Configuración php-fpm — pool www (socket Unix para comunicación con nginx)
# --------------------------------------------------------------------------
RUN { \
    echo '[www]'; \
    echo 'user = www-data'; \
    echo 'group = www-data'; \
    echo 'listen = /run/php-fpm.sock'; \
    echo 'listen.owner = www-data'; \
    echo 'listen.group = www-data'; \
    echo 'listen.mode = 0660'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 4'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 8'; \
    echo 'pm.max_requests = 500'; \
    echo 'clear_env = no'; \
    echo 'catch_workers_output = yes'; \
    echo 'php_admin_flag[log_errors] = on'; \
    echo 'php_admin_value[error_log] = /dev/stderr'; \
    } > /usr/local/etc/php-fpm.d/www.conf

# --------------------------------------------------------------------------
# Copiar archivos de configuración Docker
# --------------------------------------------------------------------------
COPY docker/nginx.conf        /etc/nginx/nginx.conf
COPY docker/supervisord.conf  /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh     /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# --------------------------------------------------------------------------
# Código de la aplicación
# --------------------------------------------------------------------------
WORKDIR /var/www/html

COPY --from=composer_builder --chown=www-data:www-data /app .

# Garantizar directorios escribibles con permisos correctos
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    # Directorio para el socket de php-fpm
    && mkdir -p /run \
    && chown www-data:www-data /run

# --------------------------------------------------------------------------
# Railway inyecta $PORT en tiempo de ejecución.
# El entrypoint reemplaza el placeholder en nginx.conf antes de arrancar.
# Documentamos 8080 como puerto por defecto.
# --------------------------------------------------------------------------
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
