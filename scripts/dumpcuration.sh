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
docker-compose exec -u `id -u`:`id -g` $MYSQL_SERVER_NAME sh -c "cd /glycoTree/SQL; ./dumptables.sh"
