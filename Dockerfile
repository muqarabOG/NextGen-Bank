# Use the official PHP 8.2 with Apache image
FROM php:8.2-apache

# Install system dependencies and enable PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli

# Enable Apache mod_rewrite (optional but good for future routing)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files to the container
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port (Render sets PORT env, Apache listens on 80 by default)
EXPOSE 80
