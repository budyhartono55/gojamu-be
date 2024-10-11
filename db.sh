#!/bin/sh
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
SPTH=$SCRIPT_DIR/.env  

export $(grep -v '^#' $SPTH | xargs)

DESTPTH=/home/backup/layanan-informasi/db
# SPTH=/home/ion/backup/$OPD_CODE/db
docker exec $DB_HOST mysqldump -u $DB_USERNAME -p$DB_PASSWORD layanan_informasi > $DESTPTH/database_`date '+%Y-%m-%d@%H:%M'`.sql

#jika perhari  gunakan -mtime, jika permenit gunakan -mmin
find $DESTPTH -type f -mtime +45 -delete