FROM php:8.3-apache

# Install opcache and enable rewrite
RUN docker-php-ext-install opcache && a2enmod rewrite

# Copy relay-server files
COPY relay-server/ /var/www/html/

# Copy shared src directory (Security, Auth, etc.)
COPY public/src/ /var/www/html/../src/

# Create data directories
RUN mkdir -p /var/www/html/data/pending \
             /var/www/html/data/attestations \
             /var/www/html/data/rate_limits && \
    chown -R www-data:www-data /var/www/html/data

EXPOSE 80

CMD ["apache2-foreground"]
