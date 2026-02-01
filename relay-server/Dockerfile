# CVerify Relay Server - Simplified Docker Image
FROM php:8.2-cli

LABEL maintainer="CVerify Team"
LABEL description="CVerify Relay Server"

# Install curl for healthcheck and required extensions
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install opcache

# Copy application files
COPY relay-server /var/www/html/

# Copy shared src directory
COPY public/src /var/www/html/../src/

# Create data directories
RUN mkdir -p /var/www/html/data/pending \
             /var/www/html/data/attestations \
             /var/www/html/data/rate_limits && \
    chmod -R 777 /var/www/html/data

# PHP production settings
RUN echo 'opcache.enable=1' >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'display_errors=Off' >> /usr/local/etc/php/conf.d/production.ini && \
    echo 'log_errors=On' >> /usr/local/etc/php/conf.d/production.ini && \
    echo 'expose_php=Off' >> /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www/html

EXPOSE 8080

# Use PHP built-in server (port 8080 doesn't require root)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]