#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/var /var/www/html/generated /var/www/html/pub/static /var/www/html/pub/media 2>/dev/null || true

php-fpm -D

exec nginx -g 'daemon off;'
