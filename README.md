# Portal VuFind de LA Referencia

Este repositorio contiene el portal personalizado **VuFind 11** para **LA Referencia**, la red latinoamericana de repositorios de acceso abierto.  
VuFind® es una interfaz de descubrimiento de bibliotecas de código abierto desarrollada y mantenida por la Universidad de Villanova.  
Para obtener más información, visita [https://vufind.org](https://vufind.org).

---

## Requisitos

- Debian 10+ / Ubuntu 20+  
- Apache 2.4+  
- PHP 8.2+  
- OpenJDK 11+  
- PostgreSQL 11+  
- Node.js 16+  

---

## Configuración de la base de datos (PostgreSQL)

1. Acceder a PostgreSQL:
   ```bash
   sudo -u postgres psql
   ```

2. Crear la base de datos:
   ```sql
   create database vufind;
   ```

3. Crear el usuario de base de datos:
   ```sql
   create user vufind with encrypted password 'vufind';
   ```

4. Conceder privilegios:
   ```sql
   grant all privileges on database vufind to vufind;
   ```

5. Cambiar el propietario del esquema:
   ```sql
   \c vufind
   ALTER SCHEMA public OWNER TO vufind;
   ```

6. Salir de PostgreSQL:
   ```bash
   exit
   ```

7. Conectarse usando el nuevo usuario (ingresa la contraseña `vufind` cuando se solicite):
   ```bash
   psql -U vufind -h 127.0.0.1 -d vufind
   ```

8. Ejecutar los scripts SQL de VuFind:
   ```bash
   wget https://raw.githubusercontent.com/vufind-org/vufind/dev/module/VuFind/sql/pgsql.sql
   psql -U vufind -h 127.0.0.1 -d vufind -f pgsql.sql
   ```

---

## Instalación de VuFind

1. Clonar el repositorio de VuFind:
   ```bash
   git clone https://github.com/vufind-org/vufind.git
   ```

2. Entrar en la carpeta del proyecto:
   ```bash
   cd vufind
   ```

3. Instalar dependencias:
   ```bash
   composer install
   ```

4. Ejecutar el asistente de instalación:
   ```bash
   php install.php
   ```
   Responder las preguntas:
   - **Ruta de configuración local:** *(presiona Enter)*  
   - **Nombre del módulo:** `LAReferencia`  
   - **Ruta base:** `/vufind`

5. Asignar los permisos correctos:
   ```bash
   sudo chown -R www-data:www-data local/cache
   sudo chown -R www-data:www-data local/config
   sudo mkdir local/cache/cli
   sudo chmod 777 local/cache/cli
   ```

> En **Ubuntu/Debian**, el usuario de Apache es `www-data`.  
> En **Amazon Linux/RHEL/CentOS**, es `apache`.

6. Crear un enlace simbólico para la configuración de Apache:
   ```bash
   sudo ln -s /path-vufind/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf
   ```

| Plataforma | Directorio de configuración de Apache |
|-------------|----------------------------------------|
| Debian/Ubuntu | `/etc/apache2/conf-enabled` |
| Amazon Linux / RHEL | `/etc/httpd/conf.d` |

7. Copiar el archivo principal de configuración:
   ```bash
   cp config/vufind/config.ini local/config/vufind/config.ini
   ```

8. Editar `local/config/vufind/config.ini` y actualizar:
   ```ini
   database = "pgsql://vufind:vufind@localhost/vufind"
   url = "https://testesolr7.ibict.br/solr/"
   ```

---

## Configuración inicial

1. Acceder al instalador de VuFind:
   ```
   http://localhost/vufind/Install
   ```
   Verifica que todos los elementos estén en **verde**, excepto **ILS** (puede permanecer en rojo).

2. Configurar la variable de entorno:
   ```bash
   sudo sh -c 'echo export VUFIND_HOME="/path/vufind" >> /etc/profile.d/vufind.sh'
   ```

---

## Aplicar CSS personalizado

1. Instalar dependencias de Node:
   ```bash
   npm install
   ```

2. Compilar CSS:
   ```bash
   npm run build
   ```

---

**Mantenedor:** [LA Referencia](https://www.lareferencia.info/)  
**Versión:** VuFind 11  
**Basado en:** [https://github.com/vufind-org/vufind](https://github.com/vufind-org/vufind)  
**Licencia:** GNU GPL v2 o posterior


---
[pt-br]

# Portal VuFind da LA Referencia

Este repositório contém o portal personalizado **VuFind 11** da **LA Referencia**, a rede latino-americana de repositórios de acesso aberto.  
VuFind® é uma interface de descoberta de bibliotecas de código aberto desenvolvida e mantida pela Universidade Villanova.  
Para saber mais, acesse [https://vufind.org](https://vufind.org).

---

## Requisitos

- Debian 10+ / Ubuntu 20+  
- Apache 2.4+  
- PHP 8.2+  
- OpenJDK 11+  
- PostgreSQL 11+  
- Node.js 16+  

---

## Configuração do banco de dados (PostgreSQL)

1. Acesse o PostgreSQL:
   ```bash
   sudo -u postgres psql
   ```

2. Crie o banco de dados:
   ```sql
   create database vufind;
   ```

3. Crie o usuário do banco de dados:
   ```sql
   create user vufind with encrypted password 'vufind';
   ```

4. Conceda privilégios:
   ```sql
   grant all privileges on database vufind to vufind;
   ```

5. Altere o dono do esquema:
   ```sql
   \c vufind
   ALTER SCHEMA public OWNER TO vufind;
   ```

6. Saia do PostgreSQL:
   ```bash
   exit
   ```

7. Conecte-se com o novo usuário (digite a senha `vufind` quando solicitado):
   ```bash
   psql -U vufind -h 127.0.0.1 -d vufind
   ```

8. Execute os scripts SQL do VuFind:
   ```bash
   wget https://raw.githubusercontent.com/vufind-org/vufind/dev/module/VuFind/sql/pgsql.sql
   psql -U vufind -h 127.0.0.1 -d vufind -f pgsql.sql
   ```

---

## Instalação do VuFind

1. Clone o repositório do VuFind:
   ```bash
   git clone https://github.com/vufind-org/vufind.git
   ```

2. Entre na pasta do projeto:
   ```bash
   cd vufind
   ```

3. Instale as dependências:
   ```bash
   composer install
   ```

4. Execute o assistente de instalação:
   ```bash
   php install.php
   ```
   Responda às perguntas:
   - **Caminho para configurações locais:** *(aperte Enter)*  
   - **Nome do módulo:** `LAReferencia`  
   - **Caminho base:** `/vufind`

5. Ajuste as permissões:
   ```bash
   sudo chown -R www-data:www-data local/cache
   sudo chown -R www-data:www-data local/config
   sudo mkdir local/cache/cli
   sudo chmod 777 local/cache/cli
   ```

> No **Ubuntu/Debian**, o usuário do Apache é `www-data`.  
> No **Amazon Linux/RHEL/CentOS**, é `apache`.

6. Crie um link simbólico para o arquivo de configuração do Apache:
   ```bash
   sudo ln -s /path-vufind/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf
   ```

| Plataforma | Diretório de configuração do Apache |
|-------------|--------------------------------------|
| Debian/Ubuntu | `/etc/apache2/conf-enabled` |
| Amazon Linux / RHEL | `/etc/httpd/conf.d` |

7. Copie o arquivo principal de configuração:
   ```bash
   cp config/vufind/config.ini local/config/vufind/config.ini
   ```

8. Edite `local/config/vufind/config.ini` e atualize:
   ```ini
   database = "pgsql://vufind:vufind@localhost/vufind"
   url = "https://testesolr7.ibict.br/solr/"
   ```

---

## Configuração inicial

1. Acesse o instalador do VuFind:
   ```
   http://localhost/vufind/Install
   ```
   Verifique se todos os itens estão **verdes**, exceto **ILS** (pode permanecer vermelho).

2. Configure a variável de ambiente:
   ```bash
   sudo sh -c 'echo export VUFIND_HOME="/path/vufind" >> /etc/profile.d/vufind.sh'
   ```

---

## Aplicar CSS personalizado

1. Instale as dependências do Node:
   ```bash
   npm install
   ```

2. Compile o CSS:
   ```bash
   npm run build
   ```

---

**Mantenedor:** [LA Referencia](https://www.lareferencia.info/)  
**Versão:** VuFind 11  
**Baseado em:** [https://github.com/vufind-org/vufind](https://github.com/vufind-org/vufind)  
**Licença:** GNU GPL v2 ou posterior
