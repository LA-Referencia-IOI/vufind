# Solr 9.11 com VuFind via Docker Compose

O ambiente Docker do Solr foi separado no repositório `solr-vufind`. Ele usa a
imagem estável `solr:9.11.0` com os cores e JARs do VuFind preparados no volume
persistente. Não é necessário usar `docker cp` nem entrar no container para
copiar arquivos.

## Subir o Solr

Execute a partir da raiz do repositório `solr-vufind`:

```shell
./solr.sh start
```

O servico usa:

- imagem `solr:9.11.0`
- container `solr-9-11`
- porta `8983`
- bind mount `./volume/solr` em `/var/solr`
- heap padrão `2g`
- modulo `analysis-extras`
- `SOLR_OPTS` com G1GC, log de GC e `-Dsolr.security.allow.urls.enabled=false`

## Volumes montados

O script prepara os cores dentro de `/var/solr/data`:

```yaml
./vufind/biblio -> /var/solr/data/biblio
./vufind/authority -> /var/solr/data/authority
./vufind/reserves -> /var/solr/data/reserves
./vufind/website -> /var/solr/data/website
./vufind/solr.xml -> /var/solr/data/solr.xml
```

Os JARs do VuFind tambem sao montados:

```yaml
./vufind/jars -> /var/solr/data/jars
MarcImporter.jar -> /opt/solr/server/solr-webapp/webapp/WEB-INF/lib/MarcImporter.jar
browse-handler.jar -> /opt/solr/server/solr-webapp/webapp/WEB-INF/lib/browse-handler.jar
sqlite-jdbc-3.39.3.0.jar -> /opt/solr/server/solr-webapp/webapp/WEB-INF/lib/sqlite-jdbc-3.39.3.0.jar
```

Os JARs sao montados arquivo por arquivo dentro de `WEB-INF/lib` para nao
sobrepor as bibliotecas nativas do Solr.

## Logs

```shell
docker logs -f solr-9-11
```

## Parar e remover

```shell
./solr.sh stop
```

Para descartar índices incompatíveis antes de uma reindexação, pare o Solr e
remova apenas os diretórios de índice dentro de `./volume/solr/data`. Índices
criados com Solr/Lucene 10 não podem ser abertos pelo Solr 9.11.

## Permissoes

Como os cores estão em bind mount, o Solr grava os dados de índice no diretório
local `./volume/solr`. Esses dados não devem ser versionados.

No Linux, execute o `solr.sh` com um usuário que tenha permissão para executar
`docker`. Se o container não conseguir gravar em `/var/solr`, confirme o mount
efetivo:

```shell
docker inspect solr-9-11 --format '{{range .Mounts}}{{println .Source "->" .Destination}}{{end}}'
```
