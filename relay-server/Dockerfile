# CVerify Relay Server - Docker Image
FROM php:8.2-apache

LABEL maintainer="CVerify Team"
LABEL description="CVerify Relay Server - Central relay for validation requests and attestations"

# Install PHP extensions
RUN docker-php-ext-install opcache

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Copy application files
COPY relay-server /var/www/html/

# Copy shared src directory
COPY src /var/www/html/../src/

# Create data directories with proper permissions
RUN mkdir -p /var/www/html/data/pending \
             /var/www/html/data/attestations \
             /var/www/html/data/rate_limits && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 755 /var/www/html/data

# Apache configuration for production
RUN echo 'ServerTokens Prod\n\
ServerSignature Off\n\
TraceEnable Off\n\
Header always unset "X-Powered-By"\n\
Header always set X-Content-Type-Options "nosniff"\n\
Header always set X-Frame-Options "DENY"\n\
Header always set X-XSS-Protection "1; mode=block"' >> /etc/apache2/conf-available/security.conf

# PHP production settings
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/var/log/apache2/php_errors.log'; \
    echo 'expose_php=Off'; \
    echo 'session.cookie_httponly=1'; \
    echo 'session.cookie_secure=1'; \
    echo 'session.cookie_samesite=Strict'; \
} > /usr/local/etc/php/conf.d/production.ini

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80

WORKDIR /var/www/html

# Use Apache as process manager
CMD ["apache2-foreground"]