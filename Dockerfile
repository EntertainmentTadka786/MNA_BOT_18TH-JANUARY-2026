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

# File permissions set karo - FIXED VERSION
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 777 /var/www/html/users.json \
    && chmod 777 /var/www/html/movies.csv \
    && chmod 777 /var/www/html/bot_log.txt \
    && chmod 777 /var/www/html/error.log \
    # Ensure files exist
    && touch /var/www/html/users.json \
    && touch /var/www/html/movies.csv \
    && touch /var/www/html/bot_log.txt \
    && touch /var/www/html/error.log \
    # Create initial data if files are empty
    && if [ ! -s /var/www/html/users.json ]; then echo '{"users": {}, "owner_id": 1080317415, "bot_username": "@MNA_2_Bot", "last_updated": ""}' > /var/www/html/users.json; fi \
    && if [ ! -s /var/www/html/movies.csv ]; then echo 'movie-name,message_id,channel_username\nThe Family Man S01 2019,69,@EntertainmentTadka786\nThe Family Man S02 2022,67,@EntertainmentTadka786\nThe Family Man S03 2025,73,@EntertainmentTadka786' > /var/www/html/movies.csv; fi \
    && if [ ! -s /var/www/html/bot_log.txt ]; then echo '# Telegram Bot Log File\n# Created on Render.com' > /var/www/html/bot_log.txt; fi

# Expose port
EXPOSE 8080

# Apache ko port 8080 pe start karo
CMD ["sh", "-c", "sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf && apache2-foreground"]
