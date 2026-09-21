FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    linux-headers

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    opcache \
    bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "memory_limit=2048M" >> /usr/local/etc/php/conf.d/docker-php-memory-limit.ini && \
    echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/docker-php-memory-limit.ini

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]