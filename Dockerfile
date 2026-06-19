# Dockerfile for Voting System - Optimized for Render.com
FROM php:8.4-apache

# ============================================
# Environment Variables
# ============================================
ENV APACHE_DOCUMENT_ROOT /var/www/html
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

# ============================================
# System Dependencies
# ============================================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
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
    xml \
    && docker-php-ext-enable mysqli

# ============================================
# Apache Configuration - Fix MPM Issues
# ============================================
# Disable conflicting MPM modules
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true

# Enable the correct MPM (prefork works best with PHP)
RUN a2enmod mpm_prefork

# Enable mod_rewrite for clean URLs
RUN a2enmod rewrite

# Enable mod_headers for security
RUN a2enmod headers

# ============================================
# Set Document Root
# ============================================
# Update Apache configuration to point to the correct directory
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy custom Apache configuration
RUN echo '<Directory ${APACHE_DOCUMENT_ROOT}>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.php index.html\n\
</Directory>' > /etc/apache2/conf-available/voting.conf

RUN a2enconf voting

# ============================================
# Copy Application Files
# ============================================
# Copy the entire voting application
COPY voting/ /var/www/html/

# Copy root index.html if it exists (redirect to voting)
COPY index.html /var/www/html/index.html
COPY witty.jpg /var/www/html/witty.jpg 2>/dev/null || true
COPY face.png /var/www/html/face.png 2>/dev/null || true

# ============================================
# Set Permissions
# ============================================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true \
    && chown -R www-data:www-data /var/www/html/faces 2>/dev/null || true \
    && chmod -R 775 /var/www/html/uploads 2>/dev/null || true \
    && chmod -R 775 /var/www/html/faces 2>/dev/null || true

# ============================================
# Create Upload Directories
# ============================================
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/faces \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/faces \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/faces

# ============================================
# Health Check
# ============================================
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# ============================================
# Expose Port and Start Apache
# ============================================
EXPOSE 8080
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
