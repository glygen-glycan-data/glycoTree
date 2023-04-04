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
docker-compose down
