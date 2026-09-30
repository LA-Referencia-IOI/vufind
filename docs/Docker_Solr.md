# Solr 10.1 nightly com VuFind via Docker Compose

Este Compose sobe o Solr 10.1 beta/nightly com os cores e JARs do VuFind
montados como volumes. Assim nao e necessario usar `docker cp` nem entrar no
container para copiar arquivos.

## Subir o Solr

Execute a partir da raiz do VuFind:

```shell
docker compose -f solr/docker-compose.solr-nightly.yml up -d
```

O servico usa:

- imagem `apache/solr-nightly:10.1.0-SNAPSHOT`
- container `solr-nightly`
- porta `8983`
- volume Docker `solr_data` em `/var/solr`
- heap `4g`
- modulo `analysis-extras`
- `SOLR_OPTS` com G1GC, log de GC e `-Dsolr.security.allow.urls.enabled=false`

## Volumes montados

Os cores do VuFind sao montados diretamente dentro de `/var/solr/data`:

```yaml
./vufind/biblio:/var/solr/data/biblio
./vufind/authority:/var/solr/data/authority
./vufind/reserves:/var/solr/data/reserves
./vufind/website:/var/solr/data/website
./vufind/solr.xml:/var/solr/data/solr.xml:ro
```

Os JARs do VuFind tambem sao montados:

```yaml
./vufind/jars:/var/solr/data/jars:ro
./vufind/jars/MarcImporter.jar:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/MarcImporter.jar:ro
./vufind/jars/browse-handler.jar:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/browse-handler.jar:ro
./vufind/jars/sqlite-jdbc-3.39.3.0.jar:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/sqlite-jdbc-3.39.3.0.jar:ro
```

Os JARs sao montados arquivo por arquivo dentro de `WEB-INF/lib` para nao
sobrepor as bibliotecas nativas do Solr.

## Logs

```shell
docker logs -f solr-nightly
```

## Parar e remover

```shell
docker compose -f solr/docker-compose.solr-nightly.yml down
```

Para apagar tambem o volume Docker persistente `solr_data`:

```shell
docker compose -f solr/docker-compose.solr-nightly.yml down -v
```

## Permissoes

Como os cores estao em bind mount, o Solr grava os dados de indice nos
diretorios locais dos cores. Esses caminhos ja estao ignorados pelo Git em
`solr/.gitignore`.

No Linux, se o Solr subir mas nao conseguir escrever nos cores, ajuste a posse
dos diretorios para o usuario do container:

```shell
sudo chown -R 8983:8983 solr/vufind/biblio solr/vufind/authority solr/vufind/reserves solr/vufind/website
```
