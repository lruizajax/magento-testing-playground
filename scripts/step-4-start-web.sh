#!/bin/bash
set -e

echo "=============================================="
echo "  Step 4/4: Starting web servers"
echo "=============================================="
echo ""

docker compose up -d magento-fpm nginx

echo ""
echo "=============================================="
echo "  Setup complete!"
echo "=============================================="
echo ""
echo "  Frontend: http://localhost:8081"
echo "  Admin:    http://localhost:8081/admin"
echo "  Username: admin"
echo "  Password: Admin12345"
echo ""
echo "  Wait a moment for Nginx to start, then open the URL."
echo ""
