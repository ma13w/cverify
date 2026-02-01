FROM php:8.3-apache

# Install opcache and enable rewrite
RUN docker-php-ext-install opcache && a2enmod rewrite

# Copy public files
COPY public/ /var/www/html/

# Create data directories for user and company
RUN mkdir -p /var/www/html/user/data/rate_limits \
             /var/www/html/company/data/rate_limits \
             /var/www/html/company/data/approved && \
    chown -R www-data:www-data /var/www/html/user/data \
                                /var/www/html/company/data

EXPOSE 80

CMD ["apache2-foreground"]