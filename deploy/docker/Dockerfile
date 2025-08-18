FROM php:8.1-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor

# Clear cache
RUN rm -rf /var/cache/apk/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories
RUN mkdir -p /var/www/logs /var/www/storage/rate_limits /var/log/supervisor
RUN chown -R www-data:www-data /var/www/logs /var/www/storage
RUN chmod -R 755 /var/www/logs /var/www/storage /var/log/supervisor

# Copy nginx configuration
COPY deploy/docker/nginx.conf /etc/nginx/nginx.conf
COPY deploy/docker/default.conf /etc/nginx/conf.d/default.conf

# Copy supervisor configuration
COPY deploy/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy PHP configuration
COPY deploy/docker/php.ini /usr/local/etc/php/php.ini

# Expose port 80
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]