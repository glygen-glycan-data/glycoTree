#!/bin/sh

set -x

PYGLY_SCRIPTS=../../PyGly/scripts
GCTCONID=$PYGLY_SCRIPTS/glycotree_gctconid.py
GGACCS=$PYGLY_SCRIPTS/glyres.py

# GlyGen accessions

$GGACCS GlyGen allglycans > glygen_allacc.txt

#N-linked

rm -rf input_N
mkdir -p input_N
$GCTCONID N-linked input_N
mkdir -p extra_N

#O-linked

rm -rf input_O
mkdir -p input_O
$GCTCONID O-linked input_O
mkdir -p extra_O

