# CVerify Public - Docker Image
FROM php:8.2-apache

LABEL maintainer="CVerify Team"
LABEL description="CVerify Public - User/Company/Verifier portals"

# Install PHP extensions
RUN docker-php-ext-install opcache

# Enable Apache modules (no SSL - handled by reverse proxy)
RUN a2enmod rewrite headers

# Copy public application files (includes src directory)
COPY public /var/www/html/

# Create data directories with proper permissions
RUN mkdir -p /var/www/html/user/data \
             /var/www/html/company/data \
             /var/www/html/user/data/rate_limits \
             /var/www/html/company/data/rate_limits && \
    chown -R www-data:www-data /var/www/html/user/data \
                                /var/www/html/company/data && \
    chmod -R 700 /var/www/html/user/data \
                 /var/www/html/company/data

# Set permissions for public CV files
RUN chmod 644 /var/www/html/user/cv.json 2>/dev/null || true

# Apache configuration for production
RUN echo 'ServerTokens Prod\n\
ServerSignature Off\n\
TraceEnable Off\n\
Header always unset "X-Powered-By"\n\
Header always set X-Content-Type-Options "nosniff"\n\
Header always set X-Frame-Options "DENY"\n\
Header always set X-XSS-Protection "1; mode=block"\n\
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"' >> /etc/apache2/conf-available/security.conf

# Enable security config
RUN a2enconf security

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
    echo 'upload_max_filesize=2M'; \
    echo 'post_max_size=2M'; \
} > /usr/local/etc/php/conf.d/production.ini

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80

WORKDIR /var/www/html

CMD ["apache2-foreground"]