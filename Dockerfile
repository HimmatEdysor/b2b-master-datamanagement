# B2B CRM Master Portal — extends shared PHP base (no PECL compile at app build time).
# PHP_BASE_IMAGE is built by the php-base compose service (or compose.sh ensure_php_base).
ARG PHP_BASE_IMAGE=b2b-php-base:8.3-bookworm
FROM ${PHP_BASE_IMAGE}

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-master.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/scripts/env-file.sh /usr/local/bin/env-file.sh
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/env-file.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
