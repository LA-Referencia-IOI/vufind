# VuFind 11 Docker Environment

This document explains the Docker Compose environment in this repository in detail, how to operate it, and how to customize it.

## Goal

Run a local VuFind 11 development environment with:

- Apache + PHP for the VuFind application.
- MariaDB for the database.
- Solr for bibliographic and authority indexes.
- Optional SCSS watcher for live style recompilation.

## Architecture

Services defined in `/Users/lmatas/source/vufind/docker-compose.yml`:

- `web`
  - Local build from `/Users/lmatas/source/vufind/docker/vufind/Dockerfile`.
  - Base image: `php:8.3-apache-bullseye`.
  - Host port: `8080 -> 80`.
  - Mounts:
    - Full repository: `.:/usr/local/vufind`
    - PHP dependencies: volume `vufind_vendor:/usr/local/vufind/vendor`
- `db`
  - Image: `mariadb:11.4`.
  - Host port: `3307 -> 3306`.
  - Volume: `vufind_mariadb:/var/lib/mysql`
- `solr`
  - Local build from `/Users/lmatas/source/vufind/docker/solr/Dockerfile`.
  - Host port: `8983 -> 8983`.
  - Volume: `vufind_solr:/var/solr/data`
- `scss-watch` (optional)
  - Image: `node:20-alpine`.
  - Runs `npm run watch:scss`.
  - Mounts:
    - Full repository: `.:/usr/local/vufind`
    - `vufind_node_modules:/usr/local/vufind/node_modules`
    - `vufind_bootstrap5_node_modules:/usr/local/vufind/themes/bootstrap5/node_modules`

## Prerequisites

- Docker Engine + Docker Compose plugin (`docker compose`).
- Enough disk space for images, volumes, and dependencies.
- Free ports:
  - `8080` (VuFind)
  - `8983` (Solr)
  - `3307` (MariaDB)

## Quick Start

From the repo root (`/Users/lmatas/source/vufind`):

```bash
docker compose up --build
```

Access points:

- VuFind: `http://localhost:8080/`
- Legacy compatibility: `http://localhost:8080/vufind/` (redirects to `/`)
- Solr Admin: `http://localhost:8983/solr`
- MariaDB (host): `localhost:3307`

## Recommended Script: `docker/dev.sh`

There is a wrapper script to centralize common operations.

Enable:

```bash
chmod +x docker/dev.sh
./docker/dev.sh help
```

### Main Commands

- `./docker/dev.sh up [--build] [services...]`
- `./docker/dev.sh down [--volumes]`
- `./docker/dev.sh start [services...]`
- `./docker/dev.sh stop [services...]`
- `./docker/dev.sh restart [services...]`
- `./docker/dev.sh build [services...]`
- `./docker/dev.sh pull [services...]`
- `./docker/dev.sh ps`
- `./docker/dev.sh logs [service] [-f]`
- `./docker/dev.sh health`

### Daily Development

- `./docker/dev.sh watch start|stop|restart|logs|status`
- `./docker/dev.sh theme show`
- `./docker/dev.sh theme set <name>`
- `./docker/dev.sh shell [web|db|solr]`
- `./docker/dev.sh db`
- `./docker/dev.sh web-cli <args...>`
- `./docker/dev.sh exec <service> <cmd...>`

### Maintenance

- `./docker/dev.sh reset` (interactive confirmation)
- `./docker/dev.sh reset --yes` (non-interactive)
- `./docker/dev.sh compose <args...>` (passthrough to `docker compose`)

## Theme Configuration

Default environment theme:

- `bootstrap5`

Relevant variable:

- `VUFIND_THEME` (default: `bootstrap5`)

Configuration locations:

- Compose: `/Users/lmatas/source/vufind/docker-compose.yml`
- Web entrypoint: `/Users/lmatas/source/vufind/docker/vufind/entrypoint.sh`
- Effective VuFind config: `/Users/lmatas/source/vufind/local/docker/config/vufind/config.ini`

Change theme temporarily:

```bash
VUFIND_THEME=bootstrap5 docker compose up -d web
```

Change theme persistently for this repo:

```bash
./docker/dev.sh theme set bootstrap5
```

This writes/updates `.env` in the repo root and recreates `web`.

## SCSS Style Development

Recommended watcher flow:

```bash
./docker/dev.sh watch start
./docker/dev.sh watch logs
```

Stop watcher:

```bash
./docker/dev.sh watch stop
```

How it works:

- The watcher runs `npm run watch:scss`.
- Grunt watch pattern: `themes/*/scss/**/*.scss`.
- It recompiles themes that contain `themes/<theme>/scss/compiled.scss`.

Example (bootstrap5):

- Edit files under `themes/bootstrap5/scss/`
- Output goes to `themes/bootstrap5/css/compiled.css`

If you add a new theme with `compiled.scss` while watcher is already running, restart it:

```bash
./docker/dev.sh watch restart
```

Manual alternative without watcher:

```bash
npm run build:scss
npm run watch:scss
```

## What the `web` Entrypoint Does

Script: `/Users/lmatas/source/vufind/docker/vufind/entrypoint.sh`

When the container starts:

1. Creates `local/docker` structure.
2. Installs Composer dependencies if `vendor/autoload.php` is missing.
3. Runs non-interactive `php install.php` if `local/docker/.installed` does not exist.
4. Creates/updates `config.ini` with:
   - `System.autoConfigure = false`
   - `Site.url = http://localhost:8080` (or `VUFIND_SITE_URL`)
   - `Site.theme = <VUFIND_THEME>`
   - `Catalog.driver = NoILS`
   - `Index.url = http://solr:8983/solr` (or `VUFIND_SOLR_URL`)
   - `Database.database = mysql://...`
5. Updates `NoILS.ini` with `mode = ils-none` (or `VUFIND_NOILS_MODE`).
6. Waits for MariaDB and Solr.
7. Initializes VuFind database if the schema does not exist.
8. Starts Apache in foreground.

## Apache Configuration in This Setup

File: `/Users/lmatas/source/vufind/docker/vufind/apache-vufind.conf`

Key points:

- `FallbackResource /index.php` for routes like `/Search/Results`.
- Theme asset aliases:
  - `/themes/<theme>/(assets|css|images|js)/...`
- Public cache alias:
  - `/cache -> local/docker/cache/public`
- Compatibility redirects:
  - `/vufind` -> `/`
  - `/vufind/*` -> `/*`

## Persistent Data and Directories

Docker volume persistence:

- `vufind_mariadb` (MariaDB data)
- `vufind_solr` (Solr data)
- `vufind_vendor` (Composer vendor inside `web`)
- `vufind_node_modules` (root node_modules for watcher)
- `vufind_bootstrap5_node_modules` (bootstrap5 node_modules)

Repository filesystem persistence:

- `local/docker/` (VuFind runtime config and cache for this environment)

Note:

- `local/docker` is excluded in `.gitignore` and `.dockerignore`.

## Supported Environment Variables (`web` service)

Variables exposed from compose:

- `VUFIND_LOCAL_DIR` (default `/usr/local/vufind/local/docker`)
- `VUFIND_BASEPATH` (default `/`)
- `VUFIND_SITE_URL` (default `http://localhost:8080`)
- `VUFIND_THEME` (default `bootstrap5`)
- `VUFIND_NOILS_MODE` (default `ils-none`)
- `VUFIND_DB_HOST` (default `db`)
- `VUFIND_DB_PORT` (default `3306`)
- `VUFIND_DB_NAME` (default `vufind`)
- `VUFIND_DB_USER` (default `vufind`)
- `VUFIND_DB_PASSWORD` (default `vufind`)
- `VUFIND_DB_ROOT_USER` (default `root`)
- `VUFIND_DB_ROOT_PASSWORD` (default `root`)
- `VUFIND_SOLR_URL` (default `http://solr:8983/solr`)

## Common Operations (Recipes)

Start everything in background:

```bash
./docker/dev.sh up --build
```

Check status:

```bash
./docker/dev.sh ps
./docker/dev.sh health
```

Logs:

```bash
./docker/dev.sh logs web -f
./docker/dev.sh logs solr -f
./docker/dev.sh logs db -f
```

Open shells in containers:

```bash
./docker/dev.sh shell web
./docker/dev.sh shell solr
./docker/dev.sh shell db
```

SQL console:

```bash
./docker/dev.sh db
```

VuFind CLI commands:

```bash
./docker/dev.sh web-cli --help
```

## Full Reset

To reset everything:

```bash
./docker/dev.sh reset --yes
```

Manual equivalent:

```bash
docker compose down -v --remove-orphans
rm -rf local/docker
```

## Troubleshooting

### 1) `/Search/Results ... not found`

Validate `web` is running with this repo's Apache config:

```bash
./docker/dev.sh ps
./docker/dev.sh logs web -f
```

If you changed `docker/vufind/entrypoint.sh`, `docker/vufind/Dockerfile`, or `docker/vufind/apache-vufind.conf`, rebuild:

```bash
./docker/dev.sh up --build web
```

### 2) CSS does not load or visual changes do not appear

Checks:

- Confirm active theme:
  - `./docker/dev.sh theme show`
- If using SCSS watcher:
  - `./docker/dev.sh watch status`
  - `./docker/dev.sh watch logs`
- Force manual rebuild:
  - `npm run build:scss`

### 3) Maintenance banner appears

This environment uses `NoILS` with `mode = ils-none` to avoid maintenance mode by default. If it appears, check:

- `local/docker/config/vufind/NoILS.ini`
- `VUFIND_NOILS_MODE` value

### 4) Defaults changed but behavior did not update

If you changed scripts or Dockerfiles, you must rebuild image(s):

```bash
./docker/dev.sh up --build web
```

## Security (Local Development Only)

Current credentials/settings are local-dev oriented (`root/root`, `vufind/vufind`, etc.). Do not use this setup as-is in production.
