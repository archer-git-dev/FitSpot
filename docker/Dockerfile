FROM php:8.3-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    procps

# Очистка кэша apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений (включая pdo_pgsql для PostgreSQL и sockets для RabbitMQ)
RUN docker-php-ext-configure gd --with-jpeg --with-webp
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd sockets zip

# Установка PECL-расширения Redis
RUN pecl install redis && docker-php-ext-enable redis

# Решает проблему с правами на разных хостах
RUN git config --global --add safe.directory /var/www

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]
