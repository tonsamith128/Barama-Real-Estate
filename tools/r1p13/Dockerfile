# Test-only image: no customer package or live-server use.
FROM php:8.2-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev libicu-dev libzip-dev libpng-dev libcurl4-openssl-dev \
    && docker-php-ext-install -j2 pdo_mysql mbstring intl zip gd curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv/barama
COPY . /srv/barama
RUN mkdir -p /srv/barama/application/storage
USER 65534:65534
CMD ["php", "-r", "sleep(7200);"]
