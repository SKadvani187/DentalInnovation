# Dental Innovation — single-origin production image.
# Stage 1 builds the React storefront to static files; stage 2 serves both the
# storefront (at /) and the PHP backend (at /dentinno) from one Apache vhost,
# matching the local XAMPP / VPS layout (no CORS, same origin).

# ---- Stage 1: build the React storefront ----------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app

# Install deps first (better layer caching).
COPY smart-dental-innovation/package*.json ./
RUN npm ci

# Build. VITE_API_URL is baked in at build time — it MUST be the public URL of
# the backend API (same domain, /dentinno/api/v1). Override via --build-arg.
COPY smart-dental-innovation/ ./
ARG VITE_API_URL=http://localhost/dentinno/api/v1
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build         # outputs ./dist (includes public/.htaccess -> dist/.htaccess SPA fallback)

# ---- Stage 2: PHP 8.2 + Apache serving frontend + backend -----------------
FROM php:8.2-apache AS app

# PHP extensions the app needs: pdo_mysql (DB), gd + zip (image/product handling).
RUN apt-get update && apt-get install -y --no-install-recommends \
      libpng-dev libjpeg-dev libfreetype6-dev libzip-dev default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip \
 && rm -rf /var/lib/apt/lists/*

# Apache modules the .htaccess files rely on (rewrite for SPA + auth header pass-through).
RUN a2enmod rewrite headers expires deflate setenvif

# Upload limits (matches the intent of dentinno/.htaccess: 5MB product images).
RUN { \
      echo 'upload_max_filesize=6M'; \
      echo 'post_max_size=8M'; \
      echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# Vhost: DocumentRoot = React build at /, the PHP backend lives at /dentinno.
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Application code. Backend under /dentinno, storefront build at the web root.
COPY dentinno/ /var/www/html/dentinno/
COPY --from=frontend /app/dist/ /var/www/html/

# Entrypoint waits for the DB, applies migrations, then starts Apache.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
