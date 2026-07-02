#!/bin/bash
set -e

echo "=============================================="
echo "  Magento Ecommerce - Setup Completo"
echo "=============================================="
echo ""
echo "  Este script ejecuta los 4 pasos secuencialmente."
echo "  También puedes ejecutarlos uno por uno:"
echo "    bash scripts/step-1-start-deps.sh"
echo "    bash scripts/step-2-install-magento.sh"
echo "    bash scripts/step-3-create-products.sh"
echo "    bash scripts/step-4-start-web.sh"
echo ""

confirm() {
  echo ""
  echo -n "  >> $1 (Enter para continuar, Ctrl+C para cancelar) "
  read -r _
}

if ! docker compose version > /dev/null 2>&1; then
  echo "Error: Docker Compose is required. Install it first."
  exit 1
fi

confirm "Paso 1/4: Iniciar dependencias (MariaDB, Elasticsearch, Redis)"
bash "$(dirname "$0")/step-1-start-deps.sh"

confirm "Paso 2/4: Instalar Magento (copiar código + setup:install)"
bash "$(dirname "$0")/step-2-install-magento.sh"

confirm "Paso 3/4: Crear catálogo (5 categorías, 10 productos con imágenes)"
bash "$(dirname "$0")/step-3-create-products.sh"

confirm "Paso 4/4: Iniciar servidores web (PHP-FPM + Nginx)"
bash "$(dirname "$0")/step-4-start-web.sh"

echo ""
echo "=============================================="
echo "  Setup completo!"
echo "=============================================="
echo ""
echo "  Frontend: http://localhost:8080"
echo "  Admin:    http://localhost:8080/admin"
echo "  Username: admin"
echo "  Password: Admin12345"
echo ""
