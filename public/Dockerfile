# CVerify Public - Simplified Docker Image
FROM php:8.2-cli

LABEL maintainer="CVerify Team"
LABEL description="CVerify Public - User/Company/Verifier portals"

# Install curl for healthcheck
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

# Install required extensions
RUN docker-php-ext-install opcache

# Copy application files
COPY public /var/www/html/

# Create data directories
RUN mkdir -p /var/www/html/user/data \
             /var/www/html/company/data \
             /var/www/html/user/data/rate_limits \
             /var/www/html/company/data/rate_limits && \
    chmod -R 777 /var/www/html/user/data \
                 /var/www/html/company/data

# PHP production settings
RUN echo 'opcache.enable=1' >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'display_errors=Off' >> /usr/local/etc/php/conf.d/production.ini && \
    echo 'log_errors=On' >> /usr/local/etc/php/conf.d/production.ini && \
    echo 'expose_php=Off' >> /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www/html

EXPOSE 8080

# Use PHP built-in server (port 8080 doesn't require root)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]