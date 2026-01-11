FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql zip gd mbstring xml bcmath intl

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set memory limit to unlimited for build to prevent OOM errors
ENV COMPOSER_MEMORY_LIMIT=-1

# Install PHP dependencies
# --no-scripts: Skip post-install scripts to avoid runtime errors during build
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts

# Create required directories manually (replacing post-install-cmd)
RUN mkdir -p logs temp && chmod -R 777 logs temp

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache to listen on Render's PORT
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Default PORT if not set
ENV PORT=80

EXPOSE ${PORT}
