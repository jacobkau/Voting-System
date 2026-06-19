# Dockerfile for PHP Voting System - Deploy separately
FROM php:8.4-apache

# ============================================
# System Dependencies
# ============================================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# ============================================
# PHP Extensions
# ============================================
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    mbstring \
    xml

# ============================================
# Apache Configuration
# ============================================
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork
RUN a2enmod rewrite

# ============================================
# Set Document Root
# ============================================
ENV APACHE_DOCUMENT_ROOT /var/www/html/voting

# Update Apache configuration
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ============================================
# Copy Application Files
# ============================================
# Copy the entire repository (contains both HTML and voting folders)
COPY . /var/www/html/

# ============================================
# Set Permissions
# ============================================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ============================================
# Health Check
# ============================================
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/ || exit 1

# ============================================
# Expose Port and Start Apache
# ============================================
EXPOSE 8080
EXPOSE 80

CMD ["apache2-foreground"]
