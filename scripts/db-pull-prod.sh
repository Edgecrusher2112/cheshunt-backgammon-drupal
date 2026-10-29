#!/bin/bash

set -e

echo "Starting SSH tunnel..."
source ./scripts/tunnel.sh
sleep 2

LOCAL_DB="cheshunt_backgammon"
LOCAL_USER="cheshunt_backgammon"
LOCAL_PASS="cheshunt_backgammon"

REMOTE_DB="cbgc_prod"
REMOTE_USER="cbgc_prod"
REMOTE_PASS="wq1Eo3*69"

DUMP="remote_dump.sql"
LOCAL_PORT=3307

echo "Dumping REMOTE database via SSH tunnel..."
mysqldump --column-statistics=0 -h 127.0.0.1 -P ${LOCAL_PORT} -u${REMOTE_USER} -p"${REMOTE_PASS}" \
  ${REMOTE_DB} > ${DUMP}

echo "Rebuilding LOCAL Docker database..."
docker compose exec -T mariadb mysql -u${LOCAL_USER} -p${LOCAL_PASS} \
  -e "DROP DATABASE IF EXISTS ${LOCAL_DB}; CREATE DATABASE ${LOCAL_DB};"

echo "Importing REMOTE dump into LOCAL database..."
docker compose exec -T mariadb mysql -u${LOCAL_USER} -p${LOCAL_PASS} \
  ${LOCAL_DB} < ${DUMP}

echo "Cleaning up database dump..."
rm ${DUMP}

echo "Database PULL complete."

echo "Closing SSH tunnel..."
kill ${TUNNEL_PID}