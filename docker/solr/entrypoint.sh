#!/bin/sh
set -eu

SOLR_DATA_DIR="/var/solr/data"
SOLR_TEMPLATE_DIR="/opt/vufind-solr"
export SOLR_MODULES="${SOLR_MODULES:-analysis-extras}"
export SOLR_SECURITY_MANAGER_ENABLED="${SOLR_SECURITY_MANAGER_ENABLED:-false}"
export SOLR_OPTS="${SOLR_OPTS:-} -Ddisable.configEdit=true -Dsolr.config.lib.enabled=true"

mkdir -p "${SOLR_DATA_DIR}"
mkdir -p /var/solr/vendor

if [ ! -f "${SOLR_DATA_DIR}/.vufind_initialized" ]; then
  echo "Initializing Solr home from VuFind templates..."
  cp -a "${SOLR_TEMPLATE_DIR}/." "${SOLR_DATA_DIR}/"
  touch "${SOLR_DATA_DIR}/.vufind_initialized"
fi

if [ ! -e /var/solr/vendor/modules ]; then
  ln -s /opt/solr/modules /var/solr/vendor/modules
fi

chown -R solr:solr "${SOLR_DATA_DIR}"
chown -R solr:solr /var/solr/vendor

exec su -s /bin/sh solr -c "/opt/solr/bin/solr start -f -s ${SOLR_DATA_DIR} -p 8983"
