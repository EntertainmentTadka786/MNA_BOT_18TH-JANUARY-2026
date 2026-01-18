# Base image
FROM php:8.2-apache

# System dependencies install karo
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    libzip-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && docker-php-ext-install zip curl mbstring

# Apache mod_rewrite enable karo
RUN a2enmod rewrite headers

# Working directory set karo
WORKDIR /var/www/html

# PHP configuration copy karo
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy apache configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy application files
COPY . .

# File permissions set karo
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/users.json \
    && chmod -R 777 /var/www/html/movies.csv \
    && chmod -R 777 /var/www/html/bot_log.txt \
    && touch /var/www/html/error.log \
    && chmod 777 /var/www/html/error.log

# Expose port
EXPOSE 8080

# Apache ko port 8080 pe start karo
CMD ["sh", "-c", "sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf && apache2-foreground"]