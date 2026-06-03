FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /app"]
