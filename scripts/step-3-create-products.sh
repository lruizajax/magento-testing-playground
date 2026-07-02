#!/bin/bash
set -e

echo "=============================================="
echo "  Step 3/4: Creating catalog"
echo "=============================================="
echo ""
echo "  This step will:"
echo "    - Create 5 categories (Electrónica, Sport, etc.)"
echo "    - Create 10 products with images"
echo "    - Generate URL rewrites"
echo "    - Compile DI and deploy static content"
echo "    - Reindex all indexes"
echo "  It may take 5-8 minutes."
echo ""

docker compose --profile init run --rm magento-init php /scripts/create_products.php

echo ""
echo "  Catalog created successfully!"
echo "  5 categories, 10 products with images."
echo ""
