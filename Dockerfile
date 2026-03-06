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


CMD ["php", "-v"]


