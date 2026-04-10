#!/bin/bash

## Usage:  ./2_clear_data.sh
# Removes selected (e.g., temporary) files from the glycoTree directory structure,
#   while keeping basic model files (e.g., canonical node files) in place
# set -e
# set -o pipefail

here=`pwd`
echo "The script (2_clear_data.sh) was called from $here"
echo "Removing intermediate GlycoTree data"

echo
echo "Removing lists from $here/data"
rm $here/data/*.lst
echo "### Files left in directory $here/data ###"
ls -F $here/data

echo
echo "Removing txt and lst files from $here/data/Nlinked"
rm  $here/data/Nlinked/*.lst
## find $here/data/Nlinked -name "G*.txt" -print -maxdepth 1 | xargs -I % rm %
target=$here/data/Nlinked
ls -1 $target | awk -v t=$target '$1 ~ "G*.txt" {printf("%s/%s\n", t, $1);}' | xargs -I % rm % 
echo "### Files left in directory $here/data/gct/Nlinked ###"
ls -F $here/data/Nlinked

echo
echo "Removing csv and lst files from $here/data/Nlinked/csv"
## find $here/data/Nlinked/csv/ -name "G*.csv" -print -maxdepth 1 | xargs -I % rm %
target=$here/data/Nlinked/csv
ls -1 $target | awk -v t=$target '$1 ~ "G*.csv" {printf("%s/%s\n", t, $1);}' | xargs -I % rm % 
rm $here/data/Nlinked/csv/*.lst
echo "### Files left in directory $here/data/gct/Nlinked/csv###"
ls -F $here/data/Nlinked/csv/

echo
echo "Removing txt and lst files from $here/data/Olinked"
rm  $here/data/Olinked/*.lst
## find $here/data/Nlinked -name "G*.txt" -print -maxdepth 1 | xargs -I % rm %
target=$here/data/Olinked
ls -1 $target | awk -v t=$target '$1 ~ "G*.txt" {printf("%s/%s\n", t, $1);}' | xargs -I % rm % 
echo "### Files left in directory $here/data/gct/Olinked ###"
ls -F $here/data/Olinked

echo
echo "Removing csv and lst files from $here/data/Olinked/csv"
## find $here/data/Olinked/csv/ -name "G*.csv" -print -maxdepth 1 | xargs -I % rm %
target=$here/data/Olinked/csv
ls -1 $target | awk -v t=$target '$1 ~ "G*.csv" {printf("%s/%s\n", t, $1);}' | xargs -I % rm %
rm $here/data/Olinked/csv/*.lst
echo "### Files left in directory $here/data/gct/Olinked/csv###"
ls -F $here/data/Olinked/csv/

echo
echo "Removing csv files from $here/data/mapped"
## find $here/data/mapped/ -name "G*.csv" -print -maxdepth 1 | xargs -I % rm %
target=$here/data/mapped
ls -1 $target | awk -v t=$target '$1 ~ "G*.csv" {printf("%s/%s\n", t, $1);}' | xargs -I % rm %
echo "### Files left in directory $here/data/mapped ###"
ls -F $here/data/mapped/

echo
echo "Removing csv files from $here/data/mapped/sorted"
## find $here/data/mapped/sorted/ -name "G*.csv" -print -maxdepth 1 | xargs -I % rm %
target=$here/data/mapped/sorted
ls -1 $target | awk -v t=$target '$1 ~ "G*.csv" {printf("%s/%s\n", t, $1);}' | xargs -I % rm %
echo "### Files left in directory $here/data/mapped/sorted ###"
ls -F $here/data/mapped/sorted/

echo
echo "Removing model data that was auto-generated"
echo
rm $here/model/unassigned.csv
rm $here/model/annotated_glycans.csv
rm $here/model/annotated_glycans.json
rm $here/model/glycansLackingSVG.lst
echo "### Files left in directory $here/model ###"
ls -F $here/model/

echo
echo "### Files left in directory $here/model/gTree_svg ###"
ls -F $here/model/gTree_svg/

echo
echo "### Files left in directory $here/portal/svg ###"
ls -F $here/portal/svg

echo
echo "Current directory is $here"
echo

