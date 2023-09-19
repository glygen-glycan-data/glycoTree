#!/bin/sh

set -x

#N-linked
rm -f glycotree_nlinked_gct.zip
wget https://raw.githubusercontent.com/glygen-glycan-data/PyGly/GlyGen-GlycanData-Export-Current/smw/glycandata/export/glycotree_nlinked_gct.zip
rm -f input_N/*
cd input_N
unzip ../glycotree_nlinked_gct.zip
cd ..

#O-linked
rm -f glycotree_olinked_gct.zip
wget https://raw.githubusercontent.com/glygen-glycan-data/PyGly/GlyGen-GlycanData-Export-Current/smw/glycandata/export/glycotree_olinked_gct.zip
rm -f input_O/*
cd input_O
unzip ../glycotree_olinked_gct.zip
cd ..

