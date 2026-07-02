#!/bin/bash
set -e

echo "=============================================="
echo "  Step 1/4: Starting dependencies"
echo "=============================================="

if ! docker compose version > /dev/null 2>&1; then
  echo "Error: Docker Compose is required. Install it first."
  exit 1
fi

echo "[1/4] Cleaning up previous environment..."
docker compose down --remove-orphans 2>/dev/null || true

echo "[2/4] Starting MariaDB, Elasticsearch and Redis..."
docker compose up -d mariadb elasticsearch redis

echo "[3/4] Waiting for MariaDB..."
until docker compose exec -T mariadb mariadb-admin ping -h localhost -u root -proot123 2>/dev/null; do
  sleep 3
done
echo "  MariaDB is ready."

echo "[4/4] Waiting for Elasticsearch..."
until curl -sf http://localhost:9200 > /dev/null 2>&1; do
  sleep 3
done
echo "  Elasticsearch is ready."

echo ""
echo "  Redis is starting in the background."
echo "  Dependencies ready!"
echo ""
