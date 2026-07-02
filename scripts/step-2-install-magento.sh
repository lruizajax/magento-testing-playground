#!/bin/bash
set -e

echo "=============================================="
echo "  Step 2/4: Installing Magento"
echo "=============================================="
echo ""
echo "  This step will:"
echo "    - Copy Magento code to the shared volume"
echo "    - Apply Elasticsearch 8 compatibility patches"
echo "    - Run setup:install"
echo "    - Disable 2FA"
echo "  It may take 3-5 minutes."
echo ""

docker compose --profile init run --rm magento-init sh -c "
  echo 'Backing up existing media...' &&
  if [ -d /data/pub/media/catalog/product ] && ls /data/pub/media/catalog/product/*/ >/dev/null 2>&1; then
    mkdir -p /tmp/media_backup &&
    cp -a /data/pub/media/catalog/product/. /tmp/media_backup/
  fi &&
  echo 'Copying Magento code to volume...' &&
  cp -a /var/www/html/. /data/ &&
  echo 'Restoring media files...' &&
  if [ -d /tmp/media_backup ]; then
    mkdir -p /data/pub/media/catalog/product &&
    cp -a /tmp/media_backup/. /data/pub/media/catalog/product/ &&
    rm -rf /tmp/media_backup
  fi &&
  echo 'Running Magento setup...' &&
  php /scripts/setup.php &&
  echo 'Magento installation complete!'
"

echo ""
echo "  Magento installed successfully!"
echo "  Admin: admin / Admin12345"
echo ""
