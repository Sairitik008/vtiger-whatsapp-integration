FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libc-client2007e-dev \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libkrb5-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j"$(nproc)" curl gd imap intl mbstring mysqli zip \
    && a2enmod headers rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && printf '%s\n' \
        'display_errors=Off' \
        'memory_limit=512M' \
        'upload_max_filesize=32M' \
        'post_max_size=32M' \
        'max_execution_time=120' \
        'max_input_vars=5000' \
        > /usr/local/etc/php/conf.d/vtiger.ini \
    && rm -rf /var/lib/apt/lists/*
