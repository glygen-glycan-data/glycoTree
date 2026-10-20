#!/bin/sh
DIR=`dirname $0`
DIR=`readlink -f $DIR`
cd $DIR
# set environment variables
. ./bashrc
docker-compose exec -u `id -u`:`id -g` -it $MYSQL_SERVER_NAME mysql -u gt_user -p -h localhost -P 3306 glycotree
