#!/bin/bash

source ./scripts/tunnel.sh

LOCAL_DB="cheshunt_backgammon"
LOCAL_USER="cheshunt_backgammon"
LOCAL_PASS="cheshunt_backgammon"

REMOTE_DB="cbgc_staging"
REMOTE_USER="cbgc_staging"
REMOTE_PASS="8u8v&Qf78"

DUMP="local_dump.sql"
LOCAL_PORT=3307

echo "Dumping LOCAL Docker database..."
docker compose exec -T mariadb mariadb-dump -u${LOCAL_USER} -p${LOCAL_PASS} ${LOCAL_DB} > ${DUMP}

echo "Uploading local dump to VPS via SSH tunnel..."
mysql -h 127.0.0.1 -P ${LOCAL_PORT} -u${REMOTE_USER} -p"${REMOTE_PASS}" ${REMOTE_DB} < ${DUMP}

echo "Cleaning up database dump..."
rm ${DUMP}

echo "Database PUSH complete."

echo "Closing SSH tunnel..."
kill $TUNNEL_PID