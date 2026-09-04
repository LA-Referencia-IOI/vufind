#!/bin/sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.solr-nightly.yml"
SOURCE_DIR="$SCRIPT_DIR/vufind"
RUNTIME_DIR="$PROJECT_DIR/volumes/solr"

CORE_NAMES="biblio authority reserves website"

usage() {
    echo "Usage: $0 {start|restart|stop}" >&2
    exit 1
}

prepare_runtime_files() {
    mkdir -p "$RUNTIME_DIR/data"
    install -m 0644 "$SOURCE_DIR/solr.xml" "$RUNTIME_DIR/data/solr.xml"

    for core in $CORE_NAMES; do
        mkdir -p "$RUNTIME_DIR/data/$core"
        rsync -a --exclude='data/' "$SOURCE_DIR/$core/" "$RUNTIME_DIR/data/$core/"
    done

    mkdir -p "$RUNTIME_DIR/data/jars"
    rsync -a "$SOURCE_DIR/jars/" "$RUNTIME_DIR/data/jars/"
}

[ "$#" -eq 1 ] || usage

case "$1" in
    start)
        prepare_runtime_files
        exec docker compose -f "$COMPOSE_FILE" up -d
        ;;
    restart)
        prepare_runtime_files
        exec docker compose -f "$COMPOSE_FILE" up -d --force-recreate
        ;;
    stop)
        exec docker compose -f "$COMPOSE_FILE" stop
        ;;
    *)
        usage
        ;;
esac
