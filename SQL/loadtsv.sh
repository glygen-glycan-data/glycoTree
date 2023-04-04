#!/bin/sh
help() {
  if [ "$1" != "" ]; then
    echo "$1" 1>&2
  fi
  echo "Usage: $0 [-w] [-a] <tablename>.(txt|tsv|csv)" 1>&2
}
 
WIN=0
APPEND=0
COMMAS=0
while getopts ":wah" o; do
  case $o in 
    a) APPEND=1;;
    w) WIN=1;;
    h) help ""; exit 0;;
    *) help "Invalid option: -$OPTARG"; exit 1;;
  esac
done
while [ $OPTIND -gt 1 ]; do shift; OPTIND=`expr $OPTIND - 1`; done
if [ $# -lt 1 ]; then
  help ""
  exit 1;
fi
cols=`head -n 1 "$1" | sed -e 's/\t/,/g' -e 's/\r*$//g'`
base=`echo "$1" | sed -e 's/\.[^.]*$//'`
base=`basename $base`
case $1 in 
  *.tsv) sep='\\t';;
  *.txt) sep='\\t';;
  *.csv) sep=',';;
esac
TRUNCATE=""
if [ "$APPEND" -eq "0" ]; then
TRUNCATE="TRUNCATE TABLE $base;"
fi
if [ "$WIN" -eq "1" ]; then
read -d '' SQL <<EOF1
$TRUNCATE
LOAD DATA LOCAL INFILE '$1' INTO TABLE $base FIELDS TERMINATED BY '$sep' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\\\\r\\\\n' IGNORE 1 LINES ($cols);
SHOW WARNINGS
EOF1
else
read -d '' SQL <<EOF2
$TRUNCATE
LOAD DATA LOCAL INFILE '$1' INTO TABLE $base FIELDS TERMINATED BY '$sep' OPTIONALLY ENCLOSED BY '\"' IGNORE 1 LINES ($cols);
SHOW WARNINGS
EOF2
fi
echo "$SQL"
