#solr

### 1. Install Solr 9.11 beta or Solr 10.1 beta
```
sudo docker pull apache/solr-nightly:9.11.0-SNAPSHOT
sudo docker pull apache/solr-nightly:10.1.0-SNAPSHOT
```
### 2. Run Solr in detached
```shell

#9.11
sudo docker run -d --name solr-nightly -p 8983:8983 -v solr_data:/var/solr -e SOLR_OPTS='-Dsolr.disable.allowUrls=true' apache/solr-nightly:9.11.0-SNAPSHOT

#10.1
sudo docker run -d --name solr-nightly -p 8983:8983 -v solr_data:/var/solr -e SOLR_OPTS="-Dsolr.security.allow.urls.enabled=false" apache/solr-nightly:10.1.0-SNAPSHOT solr start -f --user-managed

```
### 3. Copy the core into the Solr container
```shell
sudo docker cp /home/jesielviana/Dev/ioi/vufind/solr/vufind/biblio solr-nightly:/var/solr/data/

#EC2
sudo docker cp /usr/local/vufind/solr/vufind/biblio solr-nightly:/var/solr/data/
```
### 4. Copy the Vufind libs to Solr

```shell
sudo docker cp /home/jesielviana/Dev/ioi/vufind/solr/vendor/modules/analysis-extras/lib/icu4j-74.2.jar solr-nightly:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/ 

sudo docker cp /home/jesielviana/Dev/ioi/vufind/solr/vendor/modules/analysis-extras/lib/lucene-analysis-icu-9.11.1.jar solr-nightly:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/

sudo docker cp /home/jesielviana/Dev/ioi/vufind/solr/vufind/jars/. solr-nightly:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/

#EC2
sudo docker cp /usr/local/vufind/solr/vendor/modules/analysis-extras/lib/icu4j-74.2.jar solr-nightly:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/ 

sudo docker cp  /usr/local/vufind/solr/vendor/modules/analysis-extras/lib/lucene-analysis-icu-9.11.1.jar solr-nightly:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/

sudo docker cp  /usr/local/vufind/solr/vufind/jars/. solr-nightly:/opt/solr/server/solr-webapp/webapp/WEB-INF/lib/
```

### 5. Fix permissions inside the container

Enter the container shell:
```shell
sudo docker exec -u 0 -it solr-nightly bash
```
Then run:
```shell
chown -R solr:solr /var/solr/data/biblio
chown solr:solr /opt/solr/server/solr-webapp/webapp/WEB-INF/lib/*.jar
exit
```
### 6. Restart Solr (to detect the core)
```shell
sudo docker stop solr-nightly  
sudo docker start solr-nightly
```
## Logs
```shell
sudo docker logs -f solr-nightly
```

