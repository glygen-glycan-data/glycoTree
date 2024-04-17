#!/bin/sh
set -x
DIR=`dirname $0`
DIR=`readlink -f $DIR`
cd $DIR
ls -l
# set environment variables
. ./bashrc
cd ..
# start php-mysql-apache
docker-compose exec web apt-get install -y jq wget
docker-compose exec -u `id -u`:`id -g` web sh -c "cd /glycoTree/portal/api/paths; wget -q -O - 'http://localhost/api/list.php?mode=mapped' | jq -r '.[].glytoucan_ac' > mappedglycans.txt; ./genAllPaths.sh mappedglycans.txt allPaths.json"
