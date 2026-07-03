#!/bin/sh
set -e

ES_REINDEX_LOCK="/var/www/html/var/.es_reindex_done"
MAGENTO_DIR="/var/www/html"

chown -R www-data:www-data "$MAGENTO_DIR/var" "$MAGENTO_DIR/generated" "$MAGENTO_DIR/pub/static" "$MAGENTO_DIR/pub/media" 2>/dev/null || true

if [ -f "$MAGENTO_DIR/app/etc/env.php" ]; then
    ES_HOST="${ES_HOST:-elasticsearch}"
    ES_PORT="${ES_PORT:-9200}"

    # Wait for Elasticsearch
    for i in $(seq 1 30); do
        if curl -sf "http://${ES_HOST}:${ES_PORT}" >/dev/null 2>&1; then break; fi
        sleep 2
    done

    # Check if ES index exists
    INDEX_COUNT=$(curl -sf "http://${ES_HOST}:${ES_PORT}/_cat/indices/magento2_product_*" 2>/dev/null | wc -l | tr -d ' ')

    if [ "$INDEX_COUNT" = "0" ]; then
        # Always attempt rebuild if index is missing, regardless of lock file
        rm -f "$ES_REINDEX_LOCK"
        echo "docker-entrypoint: Rebuilding Elasticsearch catalog search index..."
        for attempt in 1 2 3; do
            cd "$MAGENTO_DIR" && php bin/magento indexer:reindex catalogsearch_fulltext 2>&1 || true

            INDEX_COUNT=$(curl -sf "http://${ES_HOST}:${ES_PORT}/_cat/indices/magento2_product_*" 2>/dev/null | wc -l | tr -d ' ')
            if [ "$INDEX_COUNT" -gt 0 ]; then
                echo "docker-entrypoint: Elasticsearch index created ($INDEX_COUNT indices)."
                touch "$ES_REINDEX_LOCK"
                break
            fi
            echo "docker-entrypoint: Attempt $attempt/3 failed, retrying..."
            sleep 3
        done
        INDEX_COUNT=$(curl -sf "http://${ES_HOST}:${ES_PORT}/_cat/indices/magento2_product_*" 2>/dev/null | wc -l | tr -d ' ')
        if [ "$INDEX_COUNT" = "0" ]; then
            echo "docker-entrypoint: WARNING - Elasticsearch index could not be created. Run manually: php bin/magento indexer:reindex catalogsearch_fulltext"
        fi
    else
        echo "docker-entrypoint: Elasticsearch index exists ($INDEX_COUNT indices)."
    fi

    # Ensure clean URL rewrites are enabled (nginx handles the rewrite,
    # Magento needs to know so it doesn't generate /index.php/ URLs)
    REWRITES=$(cd "$MAGENTO_DIR" && php bin/magento config:show web/seo/use_rewrites 2>/dev/null | tr -d ' ')
    if [ "$REWRITES" != "1" ]; then
        echo "docker-entrypoint: Enabling web/seo/use_rewrites..."
        cd "$MAGENTO_DIR" && php bin/magento config:set web/seo/use_rewrites 1 2>/dev/null || true
    fi
fi

php-fpm -D

exec nginx -g 'daemon off;'
