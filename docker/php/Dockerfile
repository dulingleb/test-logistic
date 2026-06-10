FROM php:8.5-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash \
        git \
        curl \
        libpq \
        icu-libs \
        libzip \
        oniguruma \
        shadow \
        su-exec \
    && addgroup -g ${GID} -S app \
    && adduser  -u ${UID} -S app -G app -s /bin/bash

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_pgsql \
        bcmath \
        intl \
        pcntl \
        sockets \
        opcache \
        redis \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

USER app

EXPOSE 9000

CMD ["php-fpm"]
