#!/bin/sh

set -x

#N-linked

if [ ! -f glycotree_nlinked_gct.zip ]; then
    wget https://raw.githubusercontent.com/glygen-glycan-data/PyGly/GlyGen-GlycanData-Export-Current/smw/glycandata/export/glycotree_nlinked_gct.zip
fi
mkdir -p input_N
rm -f input_N/*
cd input_N
unzip ../glycotree_nlinked_gct.zip
cd ..

#O-linked
if [ ! -f glycotree_nlinked_gct.zip ]; then
    wget https://raw.githubusercontent.com/glygen-glycan-data/PyGly/GlyGen-GlycanData-Export-Current/smw/glycandata/export/glycotree_olinked_gct.zip
fi
mkdir -p input_O
rm -f input_O/*
cd input_O
unzip ../glycotree_olinked_gct.zip
cd ..

