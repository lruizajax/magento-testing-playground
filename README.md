# Magento Testing Playground — Entorno de Pruebas para BDD/Cucumber

Magento 2.4.7-p4 corriendo en Docker con **Elasticsearch 8** (vía engine `elasticsearch7`), MariaDB 11 y Redis 7.
El proyecto incluye **10 productos reales con fotos** y **5 categorías**, listo para usar en cursos de automatización de pruebas con Cucumber BDD.

> Todo el setup se hace con un solo comando: `bash scripts/init.sh`

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

**Primera ejecución:** 5–10 minutos (descarga imágenes Docker + instalación de Magento).
**Ejecuciones siguientes:** ~1 minuto (los volúmenes Docker persisten los datos).

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

> Las imágenes se descargan de Pexels (fotos reales con licencia CC0) durante `init.sh`.

---

## Arquitectura

```
ecommerce/
├── docker-compose.yml          # Servicios Docker
├── .env                        # Variables de entorno (git-ignored)
├── nginx/
│   └── default.conf            # Configuración Nginx
├── scripts/
│   ├── init.sh                 # Orquestación del setup (único comando)
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

## Flujo de instalación (`init.sh`)

```
init.sh
  ├── docker compose up -d mariadb elasticsearch redis
  ├── docker compose up magento-init
  │     ├── Backups pub/media/catalog/product/ (si existe)
  │     ├── cp -a /var/www/html/. /data/    (copia código Magento)
  │     ├── Restaura pub/media/catalog/product/
  │     ├── php /scripts/setup.php
  │     │     ├── setup:install (modo developer)
  │     │     ├── Parches Elasticsearch 8 (vendor)
  │     │     └── Disable Two-Factor Auth
  │     └── php /scripts/create_products.php
  │           ├── Crea categorías (5)
  │           ├── Crea productos (10)
  │           ├── Genera URL rewrites
  │           ├── Descarga imágenes reales desde Pexels
  │           ├── Asigna imágenes a productos
  │           ├── setup:di:compile
  │           ├── setup:static-content:deploy -f
  │           ├── catalog:images:resize
  │           ├── deploy:mode:set production
  │           ├── indexer:reindex
  │           └── cache:enable full_page
  └── docker compose up -d magento-fpm nginx
```

---

## Elasticsearch 8 — Parches aplicados

Magento 2.4.7-p4 trae el engine `elasticsearch7` que envía consultas incompatibles con ES 8.
Los scripts `setup.php` y `create_products.php` aplican estos parches automáticamente:

| # | Problema | Solución | Archivo parcheado |
|---|----------|----------|-------------------|
| 1 | `PUT /index` con `include_type_name=true` | Eliminar `type` y `include_type_name` del mapping | `module-elasticsearch-7/Model/Client/Elasticsearch.php` |
| 2 | Bulk index con `_type` en metadatos | Eliminar `'type'` del array bulk y `'_type'` por documento | `module-elasticsearch/Model/Adapter/Elasticsearch.php` |
| 3 | Search URL con segmento `type` | Eliminar `'type' => $this->clientConfig->getEntityType()` | `SearchAdapter/Query/Builder.php` (x2) |
| 4 | `stored_fields` y `docvalue_fields` en initQuery | Eliminar ambas líneas del body | `SearchAdapter/Query/Builder.php` (x2) |
| 5 | Sort con `"order": ""` (campo vacío) | `if (empty($item['field']) \|\| empty($item['direction'])) { continue; }` | `SearchAdapter/Query/Builder/Sort.php` |
| 6 | XSD no determinista en PHP 8.1+ | Reemplazar `<xs:choice>` por `<xs:sequence>` | `module-elasticsearch/etc/esconfig.xsd` |

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
Scenario: Buscar producto por nombre
  Given estoy en la página principal
  When busco "AirPods Pro 2"
  Then veo el producto "Apple AirPods Pro 2" en los resultados

Scenario: Agregar producto al carrito
  Given estoy en la página del producto "airpods-pro-2"
  When agrego el producto al carrito
  Then el carrito muestra el producto "Apple AirPods Pro 2"
  And el total es "$249.99"

Scenario: Login como admin
  Given estoy en la página de login del admin
  When ingreso con "admin" y "Admin12345"
  Then veo el dashboard de administración

Scenario: Producto sin stock
  Given estoy en la página del producto "{sku_sin_stock}"
  Then el botón "Agregar al carrito" no está disponible

Scenario: Buscar sin resultados
  When busco "zzzzz"
  Then veo el mensaje "no se encontraron productos"
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
| Iniciar (1ra vez o tras clonar) | `bash scripts/init.sh` |
| Iniciar diariamente | `docker compose start` |
| Pausar | `docker compose stop` |
| Detener completamente | `docker compose down` |
| Reset completo (borrar todo) | `docker compose down -v && bash scripts/init.sh` |
| Logs del FPM | `docker compose logs -f magento-fpm` |
| Shell en el contenedor | `docker compose exec magento-fpm bash` |
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
bash scripts/init.sh    # ~5-10 min

# Usar durante la clase
# ... pruebas con Cucumber ...

# Pausar al final del día
docker compose stop

# Día 2 — Reanudar (todo sigue igual: productos, imágenes, carrito)
docker compose start

# ... más pruebas ...

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
| Error 500 en category/product | Parche ES8 no aplicado | Verificar `vendor/` patches o re-ejecutar `init.sh` |

---

## Notas técnicas

- **Engine de búsqueda:** Se usa `elasticsearch7` por compatibilidad con la base de datos existente,
  pero se conecta a Elasticsearch 8.19.15. Los parches necesarios se aplican automáticamente.
- **Magento 2FA:** Los módulos `Magento_TwoFactorAuth` y `Magento_AdminAdobeImsTwoFactorAuth`
  se deshabilitan durante `setup.php` para facilitar el acceso al admin.
- **Imágenes:** Se descargan desde Pexels (licencia CC0 / libre para uso comercial y educativo)
  durante `create_products.php` y se asignan vía `addImageToMediaGallery()`.
- **Modo producción:** El proyecto se despliega en modo production con Full Page Cache habilitado
  para máximo rendimiento (~120ms por página).
