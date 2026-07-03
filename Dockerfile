FROM mappia/magento2:fpm-alpine2.4.7

USER root

RUN apk add --no-cache nginx

COPY build/code/ /var/www/html/

COPY media/product-images/ /var/www/html/pub/media/catalog/product/

COPY nginx/standalone.conf /etc/nginx/http.d/default.conf

RUN chown -R www-data:www-data /var/www/html/var /var/www/html/generated /var/www/html/pub/static /var/www/html/pub/media 2>/dev/null || true

COPY scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
