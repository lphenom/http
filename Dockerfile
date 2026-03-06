FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer 2.7.x
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader 2>/dev/null || true

COPY . .

RUN composer install --optimize-autoloader

CMD ["php", "-v"]


