#!/bin/sh
if [ ! -d data -o ! -d model -o ! -d SQL ]; then
  exit 1
fi
set -x
DIR=`dirname $0`
DIR=`readlink -f $DIR`
$DIR/populate.sh
$DIR/glygendump.sh
$DIR/structprops.py data/glygen_allacc.txt SQL/compositions.csv model/enzymes.csv model/enzyme_mappings.csv model/rule_data.tsv glycotree_annotated_glycans.tsv.gz > SQL/structure.csv
$DIR/populate.sh
