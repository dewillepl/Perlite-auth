FROM php:8.2-fpm

WORKDIR /var/www/perlite

COPY ./perlite/index.php ./
COPY ./perlite/helper.php ./
COPY ./perlite/content.php ./
COPY ./perlite/*.svg ./
COPY ./perlite/*.ico ./
COPY ./perlite/.styles/ ./.styles/
COPY ./perlite/.js/ ./.js/
COPY ./perlite/.src/ ./.src/
COPY ./perlite/vendor/ ./vendor/

# Copy application and authentication files
COPY web/auth/ /var/www/perlite/auth/

# Install dependencies and Nginx
RUN apt-get update && apt-get install -y vim nginx supervisor \
    && rm -rf /var/lib/apt/lists/*

# Inject logout button after line 156 in index.php
COPY logout-index-patch.html /tmp/logout-index-patch.html
RUN sed -i '156r /tmp/logout-index-patch.html' /var/www/perlite/index.php

# Set correct permissions for web files
RUN chown -R www-data:www-data /var/www/perlite

# Copy configurations
COPY web/config/perlite.conf /etc/nginx/sites-available/default
COPY web/config/supervisord.conf /etc/supervisord.conf

# Enable the Nginx site
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Volume for notes
VOLUME ["/var/www/perlite/"]

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
