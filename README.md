# CENUSIS Ops

## What is CENUSIS
Computer Engineering - Nahrain University Students Information System

## What is CENUSIS Ops
CENUSIS Ops is a web application that provides a platform for university faculty to access and assign students courses, manage their coursework grades and track thier attendance. It also provides a platform for instructors to manage their courses and students.

## Tech Stack
- Frontend: React
- Backend: PHP
- Database: MySQL
- Deployment: Docker + Docker-Compose

## Architecture

there 3 containers

1. for MySQL called `cenusis-mysql` container exposed to `cenusis-backend` only
2. for backend called `cenusis-backend` container exposed to the `cenusis-frontend` only
3. for frontend there is `cenusis-frontend` container exposed to the Internet

the container `cenusis-frontend` exposes backend endpoints and frontend pages to the Internet using NGINX

## install

first log in to the server using ssh

then access

clone this repository on the server

```bash
git clone https://github.com/Hussein-L-AlMadhachi/CENUSIS-Operations.git
```

```bash
cd CENUSIS-Operations
```

Download the latest docker image release from [releases](https://github.com/Hussein-L-AlMadhachi/CENUSIS-Ops/releases) and upload it to the server using ssh

then extract it on the server
```bash
tar -xzvf file-you-downloaded-from-releases.tar.gz
```

> Note: all the following commands must be executed on the server not your local machine

to start all the containers run in the cloned directory
```bash
sudo docker-compose up -d
```

> on ssh docker-compose doesn't echo when executed on the server

now our database is empty. to create the tables inside it

```bash
docker-compose exec backend php /app/cli/create.php
```

to create default admin and superadmin accounts
you need to create file called `.default_accounts.json` then upload it to server.
then you have to copy it to `backend` container using this command:

```bash
sudo docker cp .default_accounts.json backend:/app/cli/.default_accounts.json
```

then create the accounts in the database using:
```bash
sudo docker-compose exec backend php /app/cli/admin.php
```

now you can navigate to the servers ip on your local network and access the platform

> NOTE: containers use unencrypted http for now
