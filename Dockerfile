FROM php:8.2-fpm   AS php_builder

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip \
    curl \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copy existing application directory
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 9000 and start php-fpm server
EXPOSE 9000

CMD ["php-fpm", "-F"]

FROM nginx:latest AS nginx_builder

RUN apt-get update && apt-get install -y \
    netcat-openbsd \
    curl

COPY .docker/nginx/nginx.conf /etc/nginx/nginx.conf

COPY --from=php_builder /var/www /var/www

RUN chmod 777 /var/run && \
    mkdir -p /var/run/nginx && \
    chmod 777 /var/run/nginx && \
    chown -R www-data:www-data /etc/nginx && chmod -R 755 /etc/nginx && \
    mkdir -p /var/cache/nginx && chown -R www-data:www-data /var/cache/nginx

RUN chmod -R 755 /var/www && chown -R www-data:www-data /var/www

USER www-data

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
