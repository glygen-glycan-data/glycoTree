#!/bin/sh

#
# Update the exported files after build and populate
#
set -e
DIR=`dirname $0`
$DIR/glygendump.sh
$DIR/allpaths.sh
