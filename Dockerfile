    FROM php:8.4-apache AS web

    RUN apt-get update && apt-get install -y \
        libzip-dev \
        zip \
        unzip \
        git \
        nano \
        curl \
        ca-certificates \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/*

    RUN a2enmod rewrite

    RUN docker-php-ext-install pdo_mysql zip

    ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
    RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
    RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

    WORKDIR /var/www/html

    COPY . .

    # Install Composer
    RUN curl -sS https://getcomposer.org/installer | php -- \
        --install-dir=/usr/local/bin \
        --filename=composer

    RUN composer diagnose

    # Install dependencies WITHOUT running scripts
    RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --no-scripts \
        -vvv

    RUN chown -R www-data:www-data storage bootstrap/cache
    
    EXPOSE 80

    COPY docker-entrypoint.sh /usr/local/bin/
    RUN chmod +x /usr/local/bin/docker-entrypoint.sh

    ENTRYPOINT ["docker-entrypoint.sh"]
    CMD ["apache2-foreground"]