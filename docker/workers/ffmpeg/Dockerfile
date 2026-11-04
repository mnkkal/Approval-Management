FROM php:8.3-cli-alpine

# Install system dependencies including FFmpeg and PDF utilities
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    ffmpeg \
    poppler-utils \
    ghostscript \
    curl \
    git \
    bash

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        bcmath \
        gd \
        zip \
        intl \
        pcntl

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

USER www-data

ENTRYPOINT ["php"]
CMD ["artisan", "queue:work", "redis", "--queue=media", "--sleep=5", "--tries=1", "--timeout=600"]
