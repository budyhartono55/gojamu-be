#!/bin/sh
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
SPTH=$SCRIPT_DIR/.env 

export $(grep -v '^#' $SPTH | xargs)


SOURCEPTH=$SCRIPT_DIR
DESTPTH=/home/backup/layanan-informasi/data
#copy data dari folder storage ppid
cp -R $SOURCEPTH/storage $DESTPTH/storage_`date '+%Y-%m-%d@%H:%M'`

#jika perhari  gunakan -mtime, jika permenit gunakan -mmin
# find $DESTPTH -empty -type d -mtime +45 -delete
find $DESTPTH -mtime +45 -exec rm -rf {} \; 