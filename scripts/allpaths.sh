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
wget -q -O - 'https://sandbox.glyomics.org/api/list.php?mode=mapped_N' | jq -r '.[].glytoucan_ac' > portal/api/paths/mappedNglycans.txt
docker-compose exec -u `id -u`:`id -g` web sh -c "cd /glycoTree/portal/api/paths; ./genAllPaths.sh mappedNglycans.txt allPaths.json"
