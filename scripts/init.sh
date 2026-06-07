#!/bin/bash
set -e

echo "=============================================="
echo "  Magento Ecommerce - Setup"
echo "=============================================="
echo ""

if ! docker compose version > /dev/null 2>&1; then
  echo "Error: Docker Compose is required. Install it first."
  exit 1
fi

echo "[1/4] Starting Docker services..."
docker compose up -d mariadb elasticsearch redis

echo "[2/4] Waiting for dependencies to be healthy..."
echo "  Waiting for MariaDB..."
until docker compose exec -T mariadb mariadb-admin ping -h localhost -u root -proot123 2>/dev/null; do
  sleep 5
done
echo "  MariaDB is ready."

echo "  Waiting for Elasticsearch..."
until curl -sf http://localhost:9200 > /dev/null 2>&1; do
  sleep 5
done
echo "  Elasticsearch is ready."

echo "  Waiting for Redis..."
until docker compose exec -T redis redis-cli -a redis123 ping 2>/dev/null; do
  sleep 5
done
echo "  Redis is ready."

echo "[3/4] Installing Magento and creating products..."
echo "  This will run the init container (copy code, setup:install, create products)."
echo "  It may take 5-10 minutes on first run."
docker compose --profile init up magento-init

echo "[4/4] Starting web servers (PHP-FPM + Nginx)..."
docker compose up -d magento-fpm nginx

echo ""
echo "=============================================="
echo "  Setup complete!"
echo "=============================================="
echo ""
echo "  Frontend: http://localhost:8080"
echo "  Admin:    http://localhost:8080/admin"
echo "  Username: admin"
echo "  Password: Admin12345"
echo ""
echo "  Wait a moment for Nginx to start, then open the URL."
echo ""
