#!/bin/sh
help() {
  if [ "$1" != "" ]; then
    echo "$1" 1>&2
  fi
  echo "Usage: $0 <tablename> <csv-headers>" 1>&2
}
 
COMMAS=0
while getopts ":h" o; do
  case $o in 
    h) help ""; exit 0;;
    *) help "Invalid option: -$OPTARG"; exit 1;;
  esac
done
while [ $OPTIND -gt 1 ]; do shift; OPTIND=`expr $OPTIND - 1`; done
if [ $# -lt 1 ]; then
  help ""
  exit 1;
fi
base="$1"
read -d '' SQL <<EOF2
SELECT $2 FROM $base;
EOF2
echo "$SQL"

