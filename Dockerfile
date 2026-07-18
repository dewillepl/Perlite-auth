FROM php:8.2-fpm

WORKDIR /var/www/perlite

# Install dependencies, yaml extension, and Nginx (rarely changes, kept early for layer caching)
RUN apt-get update && apt-get install -y vim nginx supervisor libyaml-dev libzip-dev \
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY --chown=www-data:www-data ./perlite/index.php ./
COPY --chown=www-data:www-data ./perlite/helper.php ./
COPY --chown=www-data:www-data ./perlite/content.php ./
COPY --chown=www-data:www-data ./perlite/*.svg ./
COPY --chown=www-data:www-data ./perlite/*.ico ./
COPY --chown=www-data:www-data ./perlite/.styles/ ./.styles/
COPY --chown=www-data:www-data ./perlite/.js/ ./.js/
COPY --chown=www-data:www-data ./perlite/.src/ ./.src/
COPY --chown=www-data:www-data ./perlite/vendor/ ./vendor/

# Copy authentication files
COPY --chown=www-data:www-data web/auth/ /var/www/perlite/auth/

# Copy configurations
COPY web/config/perlite.conf /etc/nginx/sites-available/default
COPY web/config/supervisord.conf /etc/supervisord.conf

# Enable the Nginx site
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Volume for notes
VOLUME ["/var/www/perlite/"]

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
