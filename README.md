# Magento Testing Playground — Entorno de Pruebas para BDD/Cucumber

Magento 2.4.7-p4 corriendo en Docker con **Elasticsearch 8** (vía engine `elasticsearch7`), MariaDB 11 y Redis 7.
El proyecto incluye **10 productos reales con fotos** y **5 categorías**, listo para usar en cursos de automatización de pruebas con Cucumber BDD.

> Setup por pasos: `bash scripts/init.sh` (ejecuta los 4 pasos automáticamente)
> O paso a paso: `bash scripts/step-1-start-deps.sh`, etc.

---

## Requisitos

- Docker 24+
- Docker Compose v2+
- Docker con mínimo **6 GB de RAM** asignada
- macOS o Linux (en Windows usar WSL2)

## Inicio rápido

```sh
git clone <repo-url> && cd ecommerce
bash scripts/init.sh
```

**Setup completo:** ~10–15 minutos la primera vez (descarga imágenes Docker + instalación).
**Reanudar después:** `docker compose start` (~1 minuto, datos persistentes).

El `init.sh` ejecuta 4 pasos con confirmación entre cada uno:

| Paso | Script | Duración | Qué hace |
|------|--------|----------|----------|
| 1 | `step-1-start-deps.sh` | ~1 min | Arranca MariaDB, Elasticsearch, Redis |
| 2 | `step-2-install-magento.sh` | ~3-5 min | Copia código, **parches ES8**, `setup:install`, desactiva 2FA |
| 3 | `step-3-create-products.sh` | ~5-8 min | Crea 5 categorías, 10 productos, imágenes, compile, reindex |
| 4 | `step-4-start-web.sh` | ~30 seg | Arranca PHP-FPM + Nginx |

Puedes ejecutarlos individualmente si algún paso falla, sin repetir los anteriores:

```sh
bash scripts/step-1-start-deps.sh
bash scripts/step-2-install-magento.sh
bash scripts/step-3-create-products.sh
bash scripts/step-4-start-web.sh
```

Al finalizar:
| Recurso | URL |
|---------|-----|
| Tienda | http://localhost:8080 |
| Admin | http://localhost:8080/admin |
| REST API | http://localhost:8080/rest/V1 |

**Admin:** `admin` / `Admin12345`

---

## Catálogo

### Categorías

| Categoría | URL | Productos |
|-----------|-----|-----------|
| Electrónica | `/electronica.html` | 3 |
| Sport | `/sport.html` | 2 |
| Clothing | `/clothing.html` | 2 |
| Home & Kitchen | `/home-kitchen.html` | 2 |
| Accessories | `/accessories.html` | 1 |

> Las URL rewrites se generan automáticamente durante `init.sh`.

### Productos

Cada producto accesible vía `/{sku}.html` (ej. `/airpods-pro-2.html`).

| SKU | Producto | Precio | Categoría | Imagen |
|-----|----------|--------|-----------|--------|
| `airpods-pro-2` | Apple AirPods Pro 2 | $249.99 | Electrónica | Sí |
| `galaxy-s25-ultra` | Samsung Galaxy S25 Ultra | $1,399.99 | Electrónica | Sí |
| `wh-1000xm6` | Sony WH-1000XM6 Headphones | $349.99 | Electrónica | Sí |
| `ultraboost-running` | Adidas Ultraboost Running Shoes | $189.99 | Sport | Sí |
| `roland-garros-racket` | Wilson Roland Garros Tennis Racket | $259.99 | Sport | Sí |
| `levis-501-original` | Levi's 501 Original Fit Jeans | $79.99 | Clothing | Sí |
| `nike-club-hoodie` | Nike Sportswear Club Hoodie | $69.99 | Clothing | Sí |
| `essenza-mini-coffee` | Nespresso Essenza Mini Coffee Machine | $149.99 | Home & Kitchen | Sí |
| `electrolux-eaf50` | Electrolux Air Fryer EAF50 | $129.99 | Home & Kitchen | Sí |
| `rayban-wayfarer` | Ray-Ban Wayfarer Sunglasses | $179.99 | Accessories | Sí |

> Las imágenes son fotos reales de Pexels (licencia CC0) almacenadas localmente en `media/product-images/`.

---

## Arquitectura

```
ecommerce/
├── docker-compose.yml          # Servicios Docker
├── .env                        # Variables de entorno (git-ignored)
├── nginx/
│   └── default.conf            # Configuración Nginx
├── scripts/
│   ├── init.sh                 # Orquestación (ejecuta step-1 a step-4)
│   ├── step-1-start-deps.sh    # Arranca dependencias (MariaDB, ES, Redis)
│   ├── step-2-install-magento.sh  # Copia código + setup:install + parches ES8
│   ├── step-3-create-products.sh  # Crea categorías, productos e imágenes
│   ├── step-4-start-web.sh     # Arranca PHP-FPM + Nginx
│   ├── setup.php               # Instalación de Magento + parches ES8
│   ├── create_products.php     # Creación de categorías, productos e imágenes
│   └── add_images.php          # Utilidad standalone para agregar imágenes
└── README.md
```

### Servicios Docker

| Servicio | Imagen | Propósito |
|----------|--------|-----------|
| `nginx` | nginx:alpine | Proxy reverso (puerto 8080) |
| `magento-fpm` | mappia/magento2:fpm-alpine2.4.7 | PHP-FPM |
| `magento-init` | mappia/magento2:cli-alpine2.4.7 | Instalación (se ejecuta una vez) |
| `mariadb` | mariadb:11 | Base de datos |
| `elasticsearch` | elasticsearch:8.19.15 | Motor de búsqueda |
| `redis` | redis:7 | Caché + sesiones |

### Volúmenes Docker

| Volumen | Montaje | Persiste |
|---------|---------|----------|
| `magento_code` | `/var/www/html` (FPM) / `/data` (init) | Código + media + contenido estático |
| `mariadb_data` | `/var/lib/mysql` | Base de datos |
| `elasticsearch_data` | `/usr/share/elasticsearch/data` | Índices de búsqueda |
| `redis_data` | `/data` | Caché |

---

## Flujo de instalación (paso a paso)

```
init.sh  (orquestador interactivo)
  │
  ├── step-1-start-deps.sh
  │     ├── docker compose down --remove-orphans
  │     └── docker compose up -d mariadb elasticsearch redis
  │
  ├── step-2-install-magento.sh
  │     └── docker compose --profile init run --rm magento-init
  │           ├── Backup pub/media/catalog/product/
  │           ├── cp -a /var/www/html/. /data/   (copia código Magento)
  │           ├── Restaura pub/media/catalog/product/
  │           └── php /scripts/setup.php
  │                 ├── Parches Elasticsearch 8 (vendor)  ← ANTES de setup:install
  │                 ├── setup:install (modo developer)
  │                 └── Disable Two-Factor Auth
  │
  ├── step-3-create-products.sh
  │     └── docker compose --profile init run --rm magento-init
  │           └── php /scripts/create_products.php
  │                 ├── Crea categorías (5)
  │                 ├── Crea productos (10) con asignación a categorías
  │                 ├── Genera URL rewrites
  │                 ├── Asigna imágenes reales a productos
  │                 ├── setup:di:compile
  │                 ├── setup:static-content:deploy -f
  │                 ├── catalog:images:resize
  │                 ├── deploy:mode:set production
  │                 ├── indexer:reindex
  │                 └── cache:enable full_page
  │
  └── step-4-start-web.sh
        └── docker compose up -d magento-fpm nginx
```

---

## Elasticsearch 8 — Parches aplicados

Magento 2.4.7-p4 trae el engine `elasticsearch7` que envía consultas incompatibles con ES 8.
Los parches se aplican en `setup.php` **antes** de `setup:install` para que la instalación funcione correctamente.

| # | Problema | Solución | Archivo parcheado |
|---|----------|----------|-------------------|
| 1 | `PUT /index` con `include_type_name=true` | Eliminar `type` y `include_type_name` del mapping | `module-elasticsearch-7/Model/Client/Elasticsearch.php` |
| 2 | Bulk index con `_type` en metadatos | Eliminar `'type'` del array bulk y `'_type'` por documento | `module-elasticsearch/Model/Adapter/Elasticsearch.php` |
| 3 | Search URL con segmento `type` | Eliminar `'type' => $this->clientConfig->getEntityType()` | `SearchAdapter/Query/Builder.php` (x2) |
| 4 | `stored_fields` y `docvalue_fields` en initQuery | Eliminar ambas líneas del body | `SearchAdapter/Query/Builder.php` (x2) |
| 5 | Sort con `"order": ""` (campo vacío) | `if (empty($item['field']) \|\| empty($item['direction'])) { continue; }` | `SearchAdapter/Query/Builder/Sort.php` |
| 6 | XSD no determinista en PHP 8.1+ | Reemplazar `<xs:choice>` por `<xs:sequence>` | `module-elasticsearch/etc/esconfig.xsd` |
| 7 | Suggestions con `type` | Eliminar `'type' => Config::ELASTICSEARCH_TYPE_DEFAULT` | `module-elasticsearch/Model/DataProvider/Base/Suggestions.php` |

---

## Cucumber / BDD

### Datos de prueba

Variables de entorno recomendadas para tests:

| Variable | Valor |
|----------|-------|
| `BASE_URL` | `http://localhost:8080` |
| `ADMIN_USER` | `admin` |
| `ADMIN_PASS` | `Admin12345` |
| `CUSTOMER_USER` | *(crear cliente manualmente)* |
| `CUSTOMER_PASS` | *(crear cliente manualmente)* |

Los SKUs de los productos pueden usarse como datos parametrizados en los escenarios.

### Ejemplos de escenarios

```gherkin
@api
Feature: REST API — Autenticación y productos

  Background:
    Given la URL base es "http://localhost:8080/rest/V1"

  Scenario: Obtener token de admin
    When envío POST a "/integration/admin/token"
      | username | admin      |
      | password | Admin12345 |
    Then la respuesta tiene status 200
    And el body contiene un token JWT

  Scenario: Obtener producto por SKU vía API
    Given tengo un token de admin válido
    When envío GET a "/products/airpods-pro-2"
    Then la respuesta tiene status 200
    And el nombre del producto es "Apple AirPods Pro 2"
    And el precio es "249.99"

  Scenario: Buscar productos por nombre vía API
    Given tengo un token de admin válido
    When envío GET a "/products?searchCriteria[filterGroups][0][filters][0][field]=name&searchCriteria[filterGroups][0][filters][0][value]=AirPods&searchCriteria[filterGroups][0][filters][0][conditionType]=like"
    Then la respuesta tiene status 200
    And total_count es mayor a 0
    And todos los resultados contienen "AirPods" en el nombre

  Scenario: Crear carrito de customer vía API
    Given tengo un token de customer válido
    When envío POST a "/carts/mine"
    Then la respuesta tiene status 200
    And el body contiene un cartId numérico


@web
Feature: Catálogo y búsqueda

  Scenario: Buscar producto por nombre
    Given estoy en la página principal
    When busco "AirPods Pro 2"
    Then veo el producto "Apple AirPods Pro 2" en los resultados

  Scenario: Navegar categoría y ver productos
    Given estoy en la página principal
    When navego a la categoría "Electrónica"
    Then veo 3 productos en la lista

  Scenario: Buscar sin resultados
    When busco "zzzzz"
    Then veo el mensaje "no se encontraron productos"

  Scenario: Ver detalle de producto
    Given estoy en la página del producto "galaxy-s25-ultra"
    Then veo el nombre "Samsung Galaxy S25 Ultra"
    And veo el precio "$1,399.99"
    And la imagen del producto se muestra correctamente


@web
Feature: Carrito de compras

  Scenario: Agregar producto al carrito
    Given estoy en la página del producto "airpods-pro-2"
    When agrego el producto al carrito
    Then el carrito muestra el producto "Apple AirPods Pro 2"
    And el subtotal es "$249.99"

  Scenario: Agregar múltiples productos
    Given estoy en la página del producto "levis-501-original"
    When agrego 2 unidades al carrito
    And agrego el producto "rayban-wayfarer" al carrito
    Then el carrito tiene 3 artículos
    And el subtotal es "$339.97"

  Scenario: Modificar cantidad en carrito
    Given tengo el producto "airpods-pro-2" en el carrito
    When actualizo la cantidad a 3
    Then el subtotal del producto es "$749.97"

  Scenario: Eliminar producto del carrito
    Given tengo el producto "wh-1000xm6" en el carrito
    When elimino el producto del carrito
    Then el carrito está vacío


@web
Feature: Checkout

  Scenario: Guest compra un producto con envío y pago estándar
    Given tengo el producto "essenza-mini-coffee" en el carrito
    When completo el checkout como guest con datos de envío y pago por defecto
    Then veo la orden confirmada con el mensaje "Thank you for your purchase"

  Scenario: Guest compra múltiples productos y el total incluye envío
    Given tengo los productos "airpods-pro-2" y "ultraboost-running" en el carrito
    When completo el checkout con envío "Flat Rate" y pago "Check / Money Order"
    Then el total de la orden incluye el costo de los productos y el envío

  Scenario: Guest checkout con dirección de facturación diferente a la de envío
    Given tengo el producto "nike-club-hoodie" en el carrito
    When completo el checkout con direcciones de envío y facturación distintas
    Then la orden se genera con ambas direcciones registradas correctamente


@web
Feature: Admin

  Scenario: Login como admin
    Given estoy en la página de login del admin
    When ingreso con "admin" y "Admin12345"
    Then veo el dashboard de administración

  Scenario: Buscar orden en admin
    Given estoy logueado como admin
    When navego a "Ventas > Órdenes"
    And busco por el email "juan@example.com"
    Then veo la orden en los resultados


@web
Feature: Productos agotados

  Scenario: Producto sin stock
    Given estoy en la página del producto "{sku_sin_stock}"
    Then el botón "Agregar al carrito" no está disponible
    And veo el mensaje "Agotado" o "Sin stock"
```

### Escenarios BDD para REST API

```gherkin
@api
Feature: Autenticación

  Background:
    Given la URL base es "http://localhost:8080/rest/V1"

  Scenario: Obtener token de admin
    When envío POST a "/integration/admin/token"
      | campo    | valor      |
      | username | admin      |
      | password | Admin12345 |
    Then la respuesta tiene status 200
    And el body contiene un token JWT


@api
Feature: Productos

  Background:
    Given la URL base es "http://localhost:8080/rest/V1"
    And tengo un token de admin válido

  Scenario: Obtener producto por SKU
    When envío GET a "/products/airpods-pro-2"
    Then la respuesta tiene status 200
    And el nombre del producto es "Apple AirPods Pro 2"

  Scenario: Buscar productos por filtro
    When envío GET a "/products?searchCriteria[filterGroups][0][filters][0][field]=name&searchCriteria[filterGroups][0][filters][0][value]=Samsung&searchCriteria[filterGroups][0][filters][0][conditionType]=like"
    Then la respuesta tiene status 200
    And total_count es 1

  Scenario: Consultar producto inexistente
    When envío GET a "/products/SKU-INEXISTENTE"
    Then la respuesta tiene status 404
    And el mensaje contiene "doesn't exist"


@api
Feature: Carrito

  Background:
    Given la URL base es "http://localhost:8080/rest/V1"
    And tengo un token de customer válido

  Scenario: Crear carrito vacío
    When envío POST a "/carts/mine"
    Then la respuesta tiene status 200
    And el body contiene un cartId

  Scenario: Agregar producto al carrito
    Given existe un carrito con ID "{cartId}"
    When envío POST a "/carts/mine/items"
      | sku  | airpods-pro-2 |
      | qty  | 1             |
    Then la respuesta tiene status 200
    And el precio es "249.99"
```

### Endpoints REST útiles

```sh
# Obtener token admin
curl -s -X POST http://localhost:8080/rest/V1/integration/admin/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Admin12345"}'

# Obtener productos
curl -s http://localhost:8080/rest/V1/products/airpods-pro-2 \
  -H "Authorization: Bearer $TOKEN"

# Buscar productos
curl -s "http://localhost:8080/rest/V1/products?searchCriteria[filterGroups][0][filters][0][field]=name&searchCriteria[filterGroups][0][filters][0][value]=AirPods&searchCriteria[filterGroups][0][filters][0][conditionType]=like"

# Crear carrito (customer)
curl -s -X POST http://localhost:8080/rest/V1/carts/mine \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json"
```

---

## Comandos útiles

| Acción | Comando |
|--------|---------|
| Setup completo (interactivo) | `bash scripts/init.sh` |
| Setup paso 1 (deps) | `bash scripts/step-1-start-deps.sh` |
| Setup paso 2 (Magento) | `bash scripts/step-2-install-magento.sh` |
| Setup paso 3 (catálogo) | `bash scripts/step-3-create-products.sh` |
| Setup paso 4 (web) | `bash scripts/step-4-start-web.sh` |
| Iniciar diariamente | `docker compose start` |
| Pausar | `docker compose stop` |
| Detener completamente | `docker compose down` |
| Reset completo (borrar todo) | `docker compose down -v && bash scripts/init.sh` |
| Solo recrear catálogo | `bash scripts/step-3-create-products.sh` |
| Logs del FPM | `docker compose logs -f magento-fpm` |
| Shell en el contenedor | `docker compose exec magento-fpm sh` |
| Reindexar | `docker compose exec magento-fpm php bin/magento indexer:reindex` |
| Regenerar caché de imágenes | `docker compose exec magento-fpm php bin/magento catalog:images:resize` |
| Limpiar caché | `docker compose exec magento-fpm php bin/magento cache:flush` |
| Modo developer | `docker compose exec magento-fpm php bin/magento deploy:mode:set developer` |
| Modo production | `docker compose exec magento-fpm php bin/magento deploy:mode:set production --skip-compilation` |
| Compilar DI | `docker compose exec magento-fpm php bin/magento setup:di:compile` |
| Desplegar contenido estático | `docker compose exec magento-fpm php bin/magento setup:static-content:deploy -f` |

---

## Variables de entorno

Copiar y **cambiar todos los valores antes de usar** (especialmente las contraseñas):

```sh
cp .env.example .env
```

Archivo `.env` en la raíz del proyecto (git-ignored):

```env
MARIADB_USER=bn_magento
MARIADB_PASSWORD=bitnami123           # ← CAMBIAR
MARIADB_DATABASE=bitnami_magento
MARIADB_ROOT_PASSWORD=root123          # ← CAMBIAR

MAGENTO_ADMIN_USERNAME=admin
MAGENTO_ADMIN_PASSWORD=Admin12345      # ← CAMBIAR
MAGENTO_ADMIN_EMAIL=admin@example.com

REDIS_PASSWORD=redis123                # ← CAMBIAR
```

---

## Persistencia de datos

Los datos sobreviven a reinicios de contenedor (`docker compose restart`) gracias a los volúmenes Docker.
Para resetear completamente el entorno (útil entre sesiones de prueba):

```sh
docker compose down -v && bash scripts/init.sh
```

> Las imágenes de productos se respaldan automáticamente antes de copiar código nuevo,
> por lo que no se pierden aunque se re-ejecute `init.sh`.

### Flujo diario para alumnos

```sh
# Día 1 — Setup inicial (solo una vez)
git clone <repo> && cd ecommerce
bash scripts/init.sh    # ~10-15 min (con confirmación entre pasos)

# O paso a paso, ideal para entender cada fase:
bash scripts/step-1-start-deps.sh         # ~1 min
bash scripts/step-2-install-magento.sh    # ~3-5 min
bash scripts/step-3-create-products.sh    # ~5-8 min  (usan pausa para explicar)
bash scripts/step-4-start-web.sh          # ~30 seg

# Usar durante la clase
# ... pruebas con Cucumber ...

# Pausar al final del día
docker compose stop

# Día 2 — Reanudar (todo sigue igual: productos, imágenes, carrito)
docker compose start

# Si algo se corrompe, repetir solo el paso necesario:
bash scripts/step-3-create-products.sh    # recrea catálogo sin reinstalar Magento

# Fin del curso
docker compose down
```

---

## Solución de problemas

| Problema | Causa | Solución |
|----------|-------|----------|
| Admin pide 2FA | Autenticación de dos factores habilitada | `setup.php` la deshabilita automáticamente |
| Página lenta (>5s) | Modo developer sin contenido estático | `setup:static-content:deploy -f` + modo production |
| Imágenes no cargan (404) | Caché de imágenes desactualizada | `catalog:images:resize` |
| Category page sin productos | Índice de búsqueda desactualizado | `indexer:reindex` |
| Error 500 en category/product | Parche ES8 no aplicado | Re-ejecutar `bash scripts/step-2-install-magento.sh` |
| Productos no aparecen en categorías | ES8 patches aplicados después de setup:install (bug antiguo) | Re-ejecutar `step-2` y `step-3` con el código corregido |

---

## Notas técnicas

- **Engine de búsqueda:** Se usa `elasticsearch7` por compatibilidad, pero se conecta a Elasticsearch 8.19.15.
  Los parches se aplican en `setup.php` **antes** de `setup:install` para que la instalación funcione correctamente.
- **Magento 2FA:** Los módulos `Magento_TwoFactorAuth` y `Magento_AdminAdobeImsTwoFactorAuth`
  se deshabilitan durante `setup.php` para facilitar el acceso al admin.
- **Imágenes:** Fotos reales desde Pexels (licencia CC0) almacenadas localmente en `media/product-images/`
  y asignadas vía `addImageToMediaGallery()`.
- **Modo producción:** El proyecto se despliega en modo production con Full Page Cache habilitado
  para máximo rendimiento (~120ms por página).
