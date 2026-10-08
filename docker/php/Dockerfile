FROM php:8.4.26-fpm-trixie

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    librabbitmq-dev \
    tesseract-ocr \
    tesseract-ocr-rus \
    tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    intl \
    pdo_pgsql \
    zip

RUN pecl install amqp \
    && docker-php-ext-enable amqp

RUN pecl install redis-6.3.0 \
    && docker-php-ext-enable redis

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
