#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.yml"
ENV_FILE="${ROOT_DIR}/.env"

dc() {
  docker compose -f "${COMPOSE_FILE}" "$@"
}

usage() {
  cat <<'EOF'
Uso: ./docker/dev.sh <comando> [opciones]

Comandos principales:
  up [--build] [servicios...]    Levanta servicios (default: web db solr)
  down [--volumes]               Baja servicios (opcional borrar volúmenes)
  start [servicios...]           Inicia servicios existentes
  stop [servicios...]            Detiene servicios
  restart [servicios...]         Reinicia servicios
  build [servicios...]           Reconstruye imágenes
  pull [servicios...]            Descarga imágenes base
  ps                             Estado de servicios
  logs [servicio] [-f]           Logs (default: todos)
  health                         Chequeo rápido de servicios y endpoints

Comandos de desarrollo:
  watch <start|stop|restart|logs|status>
                                 Manejo del watcher SCSS
  theme show                     Muestra theme activo
  theme set <nombre>             Define VUFIND_THEME en .env y recrea web
  shell [web|db|solr]            Shell interactiva en contenedor
  db                             Consola MariaDB (root/root)
  web-cli <args...>              Ejecuta: php public/index.php <args...>
  exec <servicio> <cmd...>       Ejecuta comando en servicio

Mantenimiento:
  reset [--yes]                  down -v + borrar local/docker
  compose <args...>              Passthrough a docker compose
  help                           Esta ayuda
EOF
}

set_env_var() {
  local key="$1"
  local value="$2"
  local file="$3"

  touch "${file}"
  if grep -Eq "^[[:space:]]*${key}[[:space:]]*=" "${file}"; then
    sed -i.bak -E "s|^[[:space:]]*${key}[[:space:]]*=.*$|${key}=${value}|g" "${file}"
    rm -f "${file}.bak"
  else
    printf "%s=%s\n" "${key}" "${value}" >> "${file}"
  fi
}

get_current_theme() {
  local theme=""
  if [ -f "${ENV_FILE}" ]; then
    theme="$(awk -F= '/^[[:space:]]*VUFIND_THEME[[:space:]]*=/ {gsub(/[[:space:]"]/, "", $2); print $2; exit}' "${ENV_FILE}" || true)"
  fi
  if [ -z "${theme}" ] && [ -f "${ROOT_DIR}/local/docker/config/vufind/config.ini" ]; then
    theme="$(awk -F= '/^[[:space:]]*theme[[:space:]]*=/ {gsub(/[[:space:]"]/, "", $2); print $2; exit}' "${ROOT_DIR}/local/docker/config/vufind/config.ini" || true)"
  fi
  if [ -z "${theme}" ]; then
    theme="bootstrap5"
  fi
  printf "%s\n" "${theme}"
}

cmd="${1:-help}"
if [ "$#" -gt 0 ]; then
  shift
fi

case "${cmd}" in
  help|-h|--help)
    usage
    ;;

  up)
    services=()
    build_flag=false
    while [ "$#" -gt 0 ]; do
      case "$1" in
        --build)
          build_flag=true
          ;;
        *)
          services+=("$1")
          ;;
      esac
      shift
    done
    if [ "${#services[@]}" -eq 0 ]; then
      services=(web db solr)
    fi
    args=(up -d)
    if [ "${build_flag}" = true ]; then
      args+=(--build)
    fi
    args+=("${services[@]}")
    dc "${args[@]}"
    ;;

  down)
    if [ "${1:-}" = "--volumes" ]; then
      dc down -v --remove-orphans
    else
      dc down --remove-orphans
    fi
    ;;

  start)
    services=("$@")
    if [ "${#services[@]}" -eq 0 ]; then
      services=(web db solr)
    fi
    dc start "${services[@]}"
    ;;

  stop)
    services=("$@")
    if [ "${#services[@]}" -eq 0 ]; then
      services=(web db solr)
    fi
    dc stop "${services[@]}"
    ;;

  restart)
    services=("$@")
    if [ "${#services[@]}" -eq 0 ]; then
      services=(web db solr)
    fi
    dc restart "${services[@]}"
    ;;

  build)
    if [ "$#" -eq 0 ]; then
      dc build web solr
    else
      dc build "$@"
    fi
    ;;

  pull)
    if [ "$#" -eq 0 ]; then
      dc pull web db solr scss-watch
    else
      dc pull "$@"
    fi
    ;;

  ps)
    dc ps
    ;;

  logs)
    if [ "$#" -eq 0 ]; then
      dc logs -f --tail=200
    else
      service="$1"
      shift || true
      dc logs --tail=200 "$@" "${service}"
    fi
    ;;

  health)
    echo "== docker compose ps =="
    dc ps
    echo
    echo "== web =="
    curl -fsS -o /dev/null -w "http://localhost:8080 -> HTTP %{http_code}\n" http://localhost:8080/ || true
    echo "== solr =="
    curl -fsS -o /dev/null -w "http://localhost:8983/solr -> HTTP %{http_code}\n" http://localhost:8983/solr || true
    ;;

  watch)
    sub="${1:-status}"
    case "${sub}" in
      start) dc up -d scss-watch ;;
      stop) dc stop scss-watch ;;
      restart) dc restart scss-watch ;;
      logs) dc logs -f --tail=200 scss-watch ;;
      status) dc ps scss-watch ;;
      *) echo "Subcomando inválido para watch: ${sub}" >&2; exit 1 ;;
    esac
    ;;

  theme)
    sub="${1:-show}"
    case "${sub}" in
      show)
        echo "VUFIND_THEME=$(get_current_theme)"
        ;;
      set)
        name="${2:-}"
        if [ -z "${name}" ]; then
          echo "Uso: ./docker/dev.sh theme set <nombre>" >&2
          exit 1
        fi
        set_env_var "VUFIND_THEME" "${name}" "${ENV_FILE}"
        echo "VUFIND_THEME=${name} escrito en ${ENV_FILE}"
        dc up -d web
        ;;
      *)
        echo "Subcomando inválido para theme: ${sub}" >&2
        exit 1
        ;;
    esac
    ;;

  shell)
    service="${1:-web}"
    case "${service}" in
      db) dc exec db sh ;;
      web|solr) dc exec "${service}" bash ;;
      *) echo "Servicio inválido: ${service} (usa web|db|solr)" >&2; exit 1 ;;
    esac
    ;;

  db)
    dc exec db mariadb -uroot -proot
    ;;

  web-cli)
    if [ "$#" -eq 0 ]; then
      echo "Uso: ./docker/dev.sh web-cli <args...>" >&2
      exit 1
    fi
    dc exec web php public/index.php "$@"
    ;;

  exec)
    if [ "$#" -lt 2 ]; then
      echo "Uso: ./docker/dev.sh exec <servicio> <cmd...>" >&2
      exit 1
    fi
    service="$1"
    shift
    dc exec "${service}" "$@"
    ;;

  reset)
    if [ "${1:-}" != "--yes" ]; then
      read -r -p "Esto borrará volúmenes Docker y local/docker. ¿Continuar? [y/N] " ans
      case "${ans}" in
        y|Y|yes|YES) ;;
        *) echo "Cancelado."; exit 0 ;;
      esac
    fi
    dc down -v --remove-orphans
    rm -rf "${ROOT_DIR}/local/docker"
    echo "Entorno reiniciado."
    ;;

  compose)
    dc "$@"
    ;;

  *)
    echo "Comando inválido: ${cmd}" >&2
    usage
    exit 1
    ;;
esac
