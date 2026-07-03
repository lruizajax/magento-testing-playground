#!/bin/bash
set -e

IMAGE_NAME="lruizajax/magento-playground"
TAG="latest"
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$PROJECT_DIR/build"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[BUILD]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; }

cleanup() {
    echo ""
    log "Cleaning up..."
    cd "$PROJECT_DIR"
    docker compose down -v 2>/dev/null || true
    rm -rf "$BUILD_DIR"
}
trap cleanup EXIT

cd "$PROJECT_DIR"

mkdir -p db-init
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/code"

log "Step 1/7: Starting dependencies..."
docker compose up -d mariadb elasticsearch redis

log "  Waiting for MariaDB..."
for i in $(seq 1 30); do
    if docker compose exec -T mariadb mariadb-admin ping -h localhost -u root -proot123 2>/dev/null; then
        log "  MariaDB healthy"
        break
    fi
    if [ "$i" -eq 30 ]; then
        err "MariaDB not healthy"
        exit 1
    fi
    sleep 2
done

log "  Waiting for Elasticsearch..."
for i in $(seq 1 60); do
    if curl -s http://localhost:9200 >/dev/null 2>&1; then
        log "  Elasticsearch healthy"
        break
    fi
    if [ "$i" -eq 60 ]; then
        err "Elasticsearch not healthy"
        exit 1
    fi
    sleep 2
done

log "Step 2/7: Installing Magento..."
docker compose --profile init run --rm magento-init sh -c "
    cp -a /var/www/html/. /data/ &&
    php /scripts/setup.php
"

log "Step 3/7: Creating catalog..."
docker compose --profile init run --rm magento-init php /scripts/create_products.php

log "Step 4/7: Exporting code..."
docker compose run --rm -v "$BUILD_DIR/code:/export" magento-init sh -c "
    rm -rf /data/var/cache /data/var/log /data/var/report /data/var/tmp /data/var/session 2>/dev/null || true &&
    cp -a /data/. /export/ 2>/dev/null || true
"
if [ ! -f "$BUILD_DIR/code/app/etc/env.php" ]; then
    err "Code export failed: app/etc/env.php not found"
    exit 1
fi
log "  Code exported (app/etc/env.php present)"

log "Step 5/7: Dumping database..."
docker compose exec -T mariadb mysqldump \
    -u bn_magento -pbitnami123 bitnami_magento \
    --add-drop-table --create-options --extended-insert \
    > db-init/dump.sql

DUMP_SIZE=$(wc -c < db-init/dump.sql | tr -d ' ')
if [ "$DUMP_SIZE" -lt 1000 ]; then
    err "Database dump too small ($DUMP_SIZE bytes)"
    exit 1
fi
log "  Database dumped ($DUMP_SIZE bytes)"

log "Step 6/7: Building Docker image..."
docker build -t ${IMAGE_NAME}:${TAG} .

log "Step 7/7: Pushing to Docker Hub..."
docker push ${IMAGE_NAME}:${TAG}

log "=============================================="
log "  Build complete!"
log "  Image: ${IMAGE_NAME}:${TAG}"
log "=============================================="
