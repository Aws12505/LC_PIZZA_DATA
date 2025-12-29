FROM php:8.2-apache

# Laravel needs rewrite + common headers
RUN a2enmod rewrite headers expires

# OS deps for common PHP extensions
RUN apt-get update && apt-get install -y \
    git zip unzip curl ca-certificates \
    libicu-dev \
    libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libxslt1-dev \
    libldap2-dev \
    libtidy-dev \
    libgmp-dev \
    libmagickwand-dev \
    libcurl4-openssl-dev \
    default-mysql-client \
 && rm -rf /var/lib/apt/lists/*

# GD with jpeg/freetype/webp
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# PHP extensions
RUN docker-php-ext-install -j"$(nproc)" \
    bcmath \
    calendar \
    exif \
    gd \
    gettext \
    gmp \
    intl \
    ldap \
    mbstring \
    mysqli \
    opcache \
    pcntl \
    pdo \
    pdo_mysql \
    soap \
    sockets \
    tidy \
    xsl \
    zip

# PECL extensions
RUN pecl install redis imagick \
 && docker-php-ext-enable redis imagick

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Sensible PHP limits
RUN { \
      echo "memory_limit=2G"; \
      echo "upload_max_filesize=1G"; \
      echo "post_max_size=1G"; \
      echo "max_execution_time=600"; \
      echo "max_input_time=600"; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Point Apache to Laravel /public and allow htaccess
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf '\n<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

# Entrypoint
COPY docker-entrypoint-backend.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
