# Magento Testing Playground — Testing Environment for BDD/Cucumber

Magento 2.4.7 running in Docker with **Elasticsearch 8**, MariaDB 11 and Redis 7.
It includes **10 real products with photos** and **5 categories**, ready to use in test automation courses with Cucumber BDD.

---

## Quick start (students)

1. Clone the repository:

```sh
git clone https://github.com/lruizajax/magento-testing-playground.git
cd magento-testing-playground
```

2. Start the environment:

```sh
docker compose -f docker-compose.student.yml up -d
```

Wait ~30 seconds, then open **http://localhost:8081**

| URL | Description |
|---|---|
| http://localhost:8081 | Frontend (Luma theme) |
| http://localhost:8081/admin | Admin (`admin` / `Admin12345`) |

## Requirements

- Docker 24+
- Docker Compose v2+
- Docker with at least **6 GB of RAM** allocated
- macOS or Linux (use WSL2 on Windows)

## Stack

| Service | Image | Purpose |
|---|---|---|
| Magento | `lruizajax/magento-playground:latest` | Nginx + PHP 8.2 FPM + pre-installed Magento |
| MariaDB | `mariadb:11` | Database |
| Elasticsearch | `elasticsearch:8.19.15` | Search engine |
| Redis | `redis:7` | Cache and sessions |

## Credentials

- **Admin:** `admin` / `Admin12345` (2FA disabled)
- **MariaDB:** `bn_magento` / `bitnami123` — DB: `bitnami_magento`
- **Redis:** password `redis123`

## Catalog content

- 5 categories: Electronics, Sport, Clothing, Home & Kitchen, Accessories
- 10 products with real images
- URL rewrites generated
- Compiled DI, deployed static content
- Production mode, all indexes updated

## Admin parameters

- Backend frontname: `admin`
- Username: `admin`
- Password: `Admin12345`
- Email: `admin@example.com`

## Disabling CAPTCHA for automated tests

The dump already ships with CAPTCHA and reCAPTCHA disabled. If you ever need to re-apply
(or toggle) those settings without rebuilding the image, run the following SQL directly
inside the MariaDB container to set all CAPTCHA-related paths to `0` (disabled):

```sh
docker compose -f docker-compose.student.yml exec mariadb mysql \
  -u bn_magento -pbitnami123 bitnami_magento \
  -e "UPDATE core_config_data SET value='0' WHERE path IN
     ('admin/captcha/enable','customer/captcha/enable',
      'msp_securitysuite_recaptcha/frontend/enabled',
      'msp_securitysuite_recaptcha/backend/enabled');"
```

Then clear the config cache inside the Magento container:

```sh
docker compose -f docker-compose.student.yml exec magento bin/magento cache:clean config
```

## How it works

The `lruizajax/magento-playground` image bundles Nginx + PHP-FPM + a pre-installed Magento in a single image.
It needs no shared volumes or init containers. On first boot, MariaDB automatically imports
the SQL dump with the catalog from `db-init/dump.sql`.

### To develop / rebuild the image

```sh
bash scripts/build-playground.sh
```

Requires `docker login` and takes ~15 minutes (installation + catalog creation).

## Troubleshooting

| Problem | Solution |
|---|---|
| `port already allocated` | `docker compose -f docker-compose.student.yml down`, then `up -d` again |
| Page does not load | Wait 30 seconds for MariaDB to finish the initial import |
| Platform error (amd64/arm64) | Normal on Apple Silicon. The image works with emulation |
| Want to start from scratch | `docker compose -f docker-compose.student.yml down -v`, then `up -d` |
