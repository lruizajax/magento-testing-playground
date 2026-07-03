# Magento Testing Playground — Entorno de Pruebas para BDD/Cucumber

Magento 2.4.7 corriendo en Docker con **Elasticsearch 8**, MariaDB 11 y Redis 7.
Incluye **10 productos reales con fotos** y **5 categorías**, listo para usar en cursos de automatización de pruebas con Cucumber BDD.

---

## Inicio rápido (alumnos)

```sh
docker compose -f docker-compose.student.yml up -d
```

Esperar ~30 segundos, abrir **http://localhost:8080**

| URL | Descripción |
|---|---|
| http://localhost:8080 | Frontend (Luma theme) |
| http://localhost:8080/admin | Admin (`admin` / `Admin12345`) |

## Requisitos

- Docker 24+
- Docker Compose v2+
- Docker con mínimo **6 GB de RAM** asignada
- macOS o Linux (en Windows usar WSL2)

## Stack

| Servicio | Imagen | Propósito |
|---|---|---|
| Magento | `lruizajax/magento-playground:latest` | Nginx + PHP 8.2 FPM + Magento pre-instalado |
| MariaDB | `mariadb:11` | Base de datos |
| Elasticsearch | `elasticsearch:8.19.15` | Motor de búsqueda |
| Redis | `redis:7` | Caché y sesiones |

## Credenciales

- **Admin:** `admin` / `Admin12345` (2FA desactivado)
- **MariaDB:** `bn_magento` / `bitnami123` — DB: `bitnami_magento`
- **Redis:** password `redis123`

## Contenido del catálogo

- 5 categorías: Electrónica, Sport, Clothing, Home & Kitchen, Accessories
- 10 productos con imágenes reales
- URL rewrites generados
- DI compilado, static content desplegado
- Modo producción, todos los índices actualizados

## Parámetros del admin

- Backend frontname: `admin`
- Usuario: `admin`
- Contraseña: `Admin12345`
- Email: `admin@example.com`

## Cómo funciona

La imagen `lruizajax/magento-playground` contiene Nginx + PHP-FPM + Magento pre-instalado en una sola imagen.
No necesita volúmenes compartidos ni containers init. En el primer inicio, MariaDB importa automáticamente
el dump SQL con el catálogo desde `db-init/dump.sql`.

### Para desarrollar / rebuildear la imagen

```sh
bash scripts/build-playground.sh
```

Requiere `docker login` y tarda ~15 minutos (instalación + creación de catálogo).

## Troubleshooting

| Problema | Solución |
|---|---|
| `port already allocated` | `docker compose -f docker-compose.student.yml down`, luego `up -d` de nuevo |
| La página no carga | Esperar 30 segundos a que MariaDB termine la importación inicial |
| Error de platform (amd64/arm64) | Es normal en Apple Silicon. La imagen funciona con emulación |
| Querés empezar de cero | `docker compose -f docker-compose.student.yml down -v`, luego `up -d` |
