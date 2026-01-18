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
RUN a2enmod rewrite headers ssl

# Working directory set karo
WORKDIR /var/www/html

# Copy configuration files
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy application files
COPY . .

# File permissions set karo
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 /var/www/html/*.json \
    && chmod 666 /var/www/html/*.csv \
    && chmod 666 /var/www/html/*.txt \
    && chmod 666 /var/www/html/*.log

# Expose port
EXPOSE 8080

# Apache ko port 8080 pe start karo
CMD ["sh", "-c", "sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf && echo 'ServerName localhost' >> /etc/apache2/apache2.conf && apache2-foreground"]
