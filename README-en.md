# LA Referencia VuFind Portal

This repository contains the customized VuFind 11 portal for **LA Referencia** — the Latin American network of open access repositories.  
VuFind®  is an open-source library discovery interface developed and maintained by Villanova University. To learn more, visit https://vufind.org.



---

## Requirements

- Debian 10+ / Ubuntu 20+  
- Apache 2.4+  
- PHP 8.2+  
- OpenJDK 11+  
- PostgreSQL 11+  
- Node.js 16+  

---

## Database Setup (PostgreSQL)

1. Access PostgreSQL:
   ```bash
   sudo -u postgres psql
   ```

2. Create the database:
   ```sql
   create database vufind;
   ```

3. Create the database user:
   ```sql
   create user vufind with encrypted password 'vufind';
   ```

4. Grant privileges:
   ```sql
   grant all privileges on database vufind to vufind;
   ```

5. Change the database owner:
   ```sql
   \c vufind
   ALTER SCHEMA public OWNER TO vufind;
   ```

6. Exit PostgreSQL:
   ```bash
   exit
   ```

7. Connect using the new user (enter password `vufind` when prompted):
   ```bash
   psql -U vufind -h 127.0.0.1 -d vufind
   ```

8. Execute the VuFind SQL scripts:
   ```bash
   wget https://raw.githubusercontent.com/vufind-org/vufind/dev/module/VuFind/sql/pgsql.sql
   psql -U vufind -h 127.0.0.1 -d vufind -f pgsql.sql
   ```

---

## VuFind Installation

1. Clone the VuFind repository:
   ```bash
   git clone https://github.com/vufind-org/vufind.git
   ```

2. Navigate to the project folder:
   ```bash
   cd vufind
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Run the installation wizard:
   ```bash
   php install.php
   ```
   Respond to the prompts as follows:
   - **Local settings path:** *(press Enter)*  
   - **Module name:** `LAReferencia`  
   - **Base path:** `/vufind`

5. Set the correct permissions:
   ```bash
   sudo chown -R www-data:www-data local/cache
   sudo chown -R www-data:www-data local/config
   sudo mkdir local/cache/cli
   sudo chmod 777 local/cache/cli
   ```

> On **Ubuntu/Debian**, Apache user is `www-data`.  
> On **Amazon Linux/RHEL/CentOS**, Apache user is `apache`.

6. Create a symbolic link for the Apache configuration file:
   ```bash
   sudo ln -s /path-vufind/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf
   ```

| Platform | Apache Config Directory |
|-----------|--------------------------|
| Debian/Ubuntu | `/etc/apache2/conf-enabled` |
| Amazon Linux / RHEL | `/etc/httpd/conf.d` |

7. Copy the main configuration file:
   ```bash
   cp config/vufind/config.ini local/config/vufind/config.ini
   ```

8. Edit `local/config/vufind/config.ini` and update:
   ```ini
   database = "pgsql://vufind:vufind@localhost/vufind"
   url = "https://testesolr7.ibict.br/solr/"
   ```

---

## Initial Configuration

1. Access VuFind installer:
   ```
   http://localhost/vufind/Install
   ```
   Ensure all items are marked **green**, except **ILS** (can remain red).

2. Configure the environment variable:
   ```bash
   sudo sh -c 'echo export VUFIND_HOME="/path/vufind" >> /etc/profile.d/vufind.sh'
   ```

---

## Apply custom CSS

1. Install Node dependencies:
   ```bash
   npm install
   ```

2. Build the CSS:
   ```bash
   npm run build
   ```

---

**Maintainer:** [LA Referencia](https://www.lareferencia.info/)  
**Version:** VuFind 11  
**Forked from:** [https://github.com/vufind-org/vufind](https://github.com/vufind-org/vufind)  
**License:** GNU GPL v2 or later
