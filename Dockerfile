# B2B CRM Master Portal — extends shared PHP base (no PECL compile at app build time).
# Master portal — extends shared PHP base (same tag as B2B CRM).
# Compose: additional_contexts maps ARG php_base → local b2b-php-base image (no Docker Hub pull).
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
RUN grep -qE '^<<<<<<<|^>>>>>>>' /usr/local/bin/entrypoint.sh && \
    echo "BUILD FAILED: docker/scripts/entrypoint.sh has git merge conflict markers" && exit 1 || true
RUN chmod +x /usr/local/bin/env-file.sh /usr/local/bin/entrypoint.sh
RUN git config --global safe.directory '*' 2>/dev/null || true

# prepare-permissions.sh lives in B2B_CRM; master compose bind-mounts it at runtime.

WORKDIR /var/www/html

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
