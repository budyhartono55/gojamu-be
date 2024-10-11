#!/bin/sh
# Show env vars
# grep -v '^#' html/.env
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
SPTH=$SCRIPT_DIR/.env 
# Export env vars
export $(grep -v '^#' $SPTH | xargs)
docker exec -w /var/www/html/be-ppid-pembantu-$OPD_CODE php-be-ppid-pembantu php artisan schedule:run >> /dev/null 2>&1