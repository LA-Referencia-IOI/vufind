FROM solr:9.8

USER root

COPY solr/vufind /opt/vufind-solr
COPY import /import
COPY docker/solr/entrypoint.sh /usr/local/bin/vufind-solr-entrypoint.sh

RUN chmod +x /usr/local/bin/vufind-solr-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/vufind-solr-entrypoint.sh"]
