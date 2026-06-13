FROM php:8.2-apache

# Install PHP extensions needed for MySQL + file ops
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    gd

# Enable Apache mod_rewrite
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini

# Copy Apache virtual host config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www

# Fix file permissions
RUN chown -R www-data:www-data /var/www

