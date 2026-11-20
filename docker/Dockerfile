# Image aplikasi BLJ (web + worker + scheduler memakai image yang sama).
FROM php:8.3-apache

ARG INSTALL_LIBREOFFICE=true

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
        ffmpeg poppler-utils fonts-dejavu-core \
    && if [ "$INSTALL_LIBREOFFICE" = "true" ]; then apt-get install -y --no-install-recommends libreoffice-impress; fi \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd intl zip bcmath pcntl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/blj.ini

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/blj-entrypoint
RUN chmod +x /usr/local/bin/blj-entrypoint
ENTRYPOINT ["blj-entrypoint"]
CMD ["apache2-foreground"]
