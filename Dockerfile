FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Enable Rewrite
RUN a2enmod rewrite

# Workdir
WORKDIR /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html
