#!/bin/sh

PYGLY_SCRIPTS=../PyGly/scripts

if [ ! -d "$PYGLY_SCRIPTS" ]; then
  exit 1;
fi
$PYGLY_SCRIPTS/glycotree_glygendump.py | gzip -c > glycotree_annotated_glycans.tsv.gz
$PYGLY_SCRIPTS/glycotree_glygendumpcaveat.py | gzip -c > glycotree_glycan_caveats.tsv.gz
